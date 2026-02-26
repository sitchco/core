<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

use Sitchco\Utils\Logger;

class CloudflareInvalidator extends Invalidator
{
    public function slug(): string
    {
        return 'cloudflare';
    }

    protected function checkAvailability(): bool
    {
        return defined('SITCHCO_CLOUDFLARE_API_TOKEN') && defined('SITCHCO_CLOUDFLARE_ZONE_ID');
    }

    public function priority(): int
    {
        return 100;
    }

    public function delay(): int
    {
        return 100;
    }

    public function flush(): void
    {
        $hosts = $this->buildHostList();
        $this->validateHosts($hosts);
        $zoneId = SITCHCO_CLOUDFLARE_ZONE_ID;
        $response = wp_remote_request("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . SITCHCO_CLOUDFLARE_API_TOKEN,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['hosts' => $hosts]),
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('Cloudflare purge request failed: ' . $response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code > 299) {
            throw new \RuntimeException(
                "Cloudflare purge returned HTTP {$code}: " . wp_remote_retrieve_body($response),
            );
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['success']) || $body['success'] !== true) {
            $errors = $body['errors'] ?? [];
            $msg = !empty($errors) ? json_encode($errors) : 'unknown error';
            throw new \RuntimeException("Cloudflare purge failed: {$msg}");
        }
        Logger::debug('[Cache] Cloudflare purge succeeded for hosts: ' . implode(', ', $hosts));
    }

    private function buildHostList(): array
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (empty($host)) {
            throw new \RuntimeException('Unable to determine host from home_url()');
        }
        $hosts = [$host];
        if (str_starts_with($host, 'www.')) {
            $hosts[] = substr($host, 4);
        } else {
            $hosts[] = 'www.' . $host;
        }
        $hosts = apply_filters('sitchco/cache/cloudflare_purge_hosts', $hosts);
        $hosts = array_values(
            array_unique(
                array_filter($hosts, function ($h) {
                    return is_string($h) && $h !== '' && !str_contains($h, '/');
                }),
            ),
        );
        return $hosts;
    }

    private function validateHosts(array $hosts): void
    {
        if (empty($hosts)) {
            throw new \RuntimeException('Cloudflare purge requires at least one host');
        }
        if (count($hosts) > 30) {
            throw new \RuntimeException('Cloudflare purge supports a maximum of 30 hosts, got ' . count($hosts));
        }
    }
}
