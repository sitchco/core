<?php

namespace Sitchco\Tests\Framework;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Cache;

/**
 * Covers ConfigRegistry::isMergedDataCacheable() — the override that refuses to cache a config merge
 * which is missing an expected theme contribution. Tests behaviour through load() and the object
 * cache rather than the protected guard directly.
 */
class ConfigRegistryTest extends TestCase
{
    /** @var callable|null Active theme-directory filter, removed in tearDown. */
    private $themeDirFilter = null;

    /** @var string Directory the active theme resolves to; read live by the filter below. */
    private string $activeThemeDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(ConfigRegistry::CACHE_KEY);

        // The degraded-state assertion intentionally trips a Logger::warning; keep it out of output.
        ini_set('error_log', '/dev/null');

        // Point the active theme (template + stylesheet) at $this->activeThemeDir. The closure reads
        // the property on each call, so a test can make a sitchco.config.php "appear" mid-request by
        // reassigning the property after base paths have already been resolved.
        $this->themeDirFilter = fn() => $this->activeThemeDir;
        add_filter('template_directory', $this->themeDirFilter);
        add_filter('stylesheet_directory', $this->themeDirFilter);
    }

    protected function tearDown(): void
    {
        if ($this->themeDirFilter !== null) {
            remove_filter('template_directory', $this->themeDirFilter);
            remove_filter('stylesheet_directory', $this->themeDirFilter);
            $this->themeDirFilter = null;
        }

        Cache::forget(ConfigRegistry::CACHE_KEY);
        ini_restore('error_log');

        parent::tearDown();
    }

    public function test_does_not_cache_degraded_config_when_theme_config_appears_after_paths_resolve(): void
    {
        // The active theme initially contributes no config (the fixtures root holds no
        // sitchco.config.php), so resolved base paths contain only the core config dir.
        $this->activeThemeDir = SITCHCO_CORE_FIXTURES_DIR;

        $registry = new ConfigRegistry();
        $registry->getBasePaths(); // Resolve and memoize base paths while the theme is config-less.

        // Mid-deploy: the theme's config file becomes available only after paths were resolved. The
        // merge is now core-only (degraded) even though the theme config exists on disk.
        $this->activeThemeDir = SITCHCO_CORE_FIXTURES_DIR . '/degraded-theme';

        $result = $registry->load();

        // The degraded merge is neither served...
        $this->assertSame([], $result, 'A degraded config must resolve to safe empty data, not partial data.');

        // ...nor written to the object cache (which would freeze the degradation in for the full TTL).
        $found = false;
        wp_cache_get(ConfigRegistry::CACHE_KEY, 'sitchco', false, $found);
        $this->assertFalse($found, 'A merge missing an expected theme contribution must not be cached.');
    }

    public function test_caches_config_when_theme_contribution_is_present(): void
    {
        // The theme config is present from the start, so base paths include it and the merge is
        // complete — the override must allow caching as usual.
        $this->activeThemeDir = SITCHCO_CORE_FIXTURES_DIR . '/degraded-theme';

        $registry = new ConfigRegistry();
        $modules = $registry->load('modules');

        // The theme contribution merged in...
        $this->assertArrayHasKey('Sitchco\\Tests\\Fake\\ThemeOnlyModule', $modules);

        // ...and the complete merge was cached.
        $found = false;
        wp_cache_get(ConfigRegistry::CACHE_KEY, 'sitchco', false, $found);
        $this->assertTrue($found, 'A complete config merge should be cached.');
    }
}
