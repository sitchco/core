<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

use Sitchco\Framework\Module;
use Sitchco\Modules\AcfLifecycle;
use Sitchco\Modules\Cron;
use Sitchco\Modules\PostDeployment;
use Sitchco\Modules\PostLifecycle;
use Sitchco\Utils\Hooks;
use Sitchco\Utils\Logger;

/**
 * Cache invalidation orchestrator.
 *
 * Determines operating mode (Delegated or Standalone) based on WP Rocket presence,
 * builds a declarative route map of signals to invalidator lists, and wires up
 * signal handlers that feed the queue writer. Hooks the queue processor to minutely cron.
 *
 * Delegated Mode (Rocket active):
 *   - Rocket + editors handle day-to-day content changes
 *   - Sync wp_cache_flush() on before_rocket_clean_domain
 *   - CDN invalidators queued on after_rocket_clean_domain
 *   - Unattended events (visibility_changed, deploy, clear_all) queue Rocket + CDNs
 *
 * Standalone Mode (Rocket inactive):
 *   - All content signals queue Object Cache + CDNs
 */
class CacheInvalidation extends Module
{
    public const DEPENDENCIES = [Cron::class, PostLifecycle::class, PostDeployment::class, AcfLifecycle::class];
    public const HOOK_SUFFIX = 'cache';

    private bool $syncFlushed = false;

    public function __construct(private CacheQueue $queue) {}

    public function init(): void
    {
        $this->queue->registerInvalidators([
            new ObjectCacheInvalidator(),
            new WPRocketInvalidator(),
            new CloudFrontInvalidator(),
            new CloudflareInvalidator(),
        ]);

        $isDelegated = CacheCondition::RocketActive->check();

        $routes = $isDelegated ? $this->delegatedRoutes() : $this->standaloneRoutes();

        foreach ($routes as $hook => $invalidators) {
            add_action($hook, fn() => $this->queue->write($invalidators));
        }

        if ($isDelegated) {
            add_action('before_rocket_clean_domain', [$this, 'syncObjectCacheFlush']);
            add_action('after_rocket_clean_domain', fn() => $this->queue->write($this->cdnInvalidators()));
        }

        // Cloudflare filter registration
        if (CacheCondition::CloudflareInstalled->check()) {
            add_filter(
                'cloudflare_purge_everything_actions',
                fn(array $actions) => [...$actions, CloudflareInvalidator::PURGE_ACTION],
            );
        }

        // Hook queue processor to minutely cron
        add_action(Hooks::name('cron', 'minutely'), [$this->queue, 'process']);
    }

    /**
     * Synchronous object cache flush before Rocket rebuilds pages.
     * Single-execution guard prevents redundant flushes on multilingual sites.
     */
    public function syncObjectCacheFlush(): void
    {
        if ($this->syncFlushed) {
            return;
        }
        $this->syncFlushed = true;
        Logger::debug('[Cache] Sync object cache flush before Rocket clean');
        wp_cache_flush();
    }

    /**
     * Delegated Mode route map.
     *
     * @return array<string, Invalidator[]> hook name => invalidator instances
     */
    private function delegatedRoutes(): array
    {
        $rocketAndCdns = [new WPRocketInvalidator(), new CloudFrontInvalidator(), new CloudflareInvalidator()];

        return [
            PostLifecycle::hookName('visibility_changed') => $rocketAndCdns,
            PostDeployment::hookName('complete') => $rocketAndCdns,
            self::hookName('clear_all') => $rocketAndCdns,
        ];
    }

    /**
     * Standalone Mode route map.
     *
     * @return array<string, Invalidator[]> hook name => invalidator instances
     */
    private function standaloneRoutes(): array
    {
        $objectCacheAndCdns = [new ObjectCacheInvalidator(), new CloudFrontInvalidator(), new CloudflareInvalidator()];

        return [
            PostLifecycle::hookName('content_updated') => $objectCacheAndCdns,
            PostLifecycle::hookName('visibility_changed') => $objectCacheAndCdns,
            AcfLifecycle::hookName('fields_saved') => $objectCacheAndCdns,
            PostDeployment::hookName('complete') => $objectCacheAndCdns,
            self::hookName('clear_all') => $objectCacheAndCdns,
        ];
    }

    /**
     * CDN-only invalidators for after_rocket_clean_domain in Delegated Mode.
     *
     * @return Invalidator[]
     */
    private function cdnInvalidators(): array
    {
        return [new CloudFrontInvalidator(), new CloudflareInvalidator()];
    }
}
