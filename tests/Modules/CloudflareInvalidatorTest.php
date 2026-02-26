<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\CacheInvalidation\CloudflareInvalidator;
use Sitchco\Tests\TestCase;

class CloudflareInvalidatorTest extends TestCase
{
    private CloudflareInvalidator $invalidator;

    private ?array $lastRequest = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!defined('SITCHCO_CLOUDFLARE_API_TOKEN')) {
            define('SITCHCO_CLOUDFLARE_API_TOKEN', 'test-token-abc123');
        }
        if (!defined('SITCHCO_CLOUDFLARE_ZONE_ID')) {
            define('SITCHCO_CLOUDFLARE_ZONE_ID', 'test-zone-xyz789');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->invalidator = new CloudflareInvalidator();
        $this->lastRequest = null;
    }

    protected function tearDown(): void
    {
        $this->restoreHttp();
        parent::tearDown();
    }

    private function fakeCloudflareResponse(int $httpCode = 200, array $body = ['success' => true]): void
    {
        $this->fakeHttp(function ($args, $url) use ($httpCode, $body) {
            $this->lastRequest = [
                'url' => $url,
                'method' => $args['method'],
                'headers' => $args['headers'],
                'body' => $args['body'],
            ];
            return [
                'response' => ['code' => $httpCode, 'message' => ''],
                'body' => wp_json_encode($body),
            ];
        });
    }

    // ─── Availability ───

    public function test_isAvailable_when_constants_defined(): void
    {
        $this->assertTrue($this->invalidator->isAvailable());
    }

    public function test_isAvailable_can_be_disabled_via_filter(): void
    {
        add_filter('sitchco/cache/condition/cloudflare', '__return_false');
        $this->assertFalse($this->invalidator->isAvailable());
    }

    // ─── Flush: Request Shape ───

    public function test_flush_sends_post_to_cloudflare_purge_api(): void
    {
        $this->fakeCloudflareResponse();
        $this->invalidator->flush();

        $this->assertSame('POST', $this->lastRequest['method']);
        $this->assertSame(
            'https://api.cloudflare.com/client/v4/zones/' . SITCHCO_CLOUDFLARE_ZONE_ID . '/purge_cache',
            $this->lastRequest['url'],
        );
        $this->assertSame('Bearer ' . SITCHCO_CLOUDFLARE_API_TOKEN, $this->lastRequest['headers']['Authorization']);
        $this->assertSame('application/json', $this->lastRequest['headers']['Content-Type']);
    }

    public function test_flush_sends_host_and_www_twin(): void
    {
        $this->fakeCloudflareResponse();
        $this->invalidator->flush();

        $body = json_decode($this->lastRequest['body'], true);
        $this->assertContains('example.org', $body['hosts']);
        $this->assertContains('www.example.org', $body['hosts']);
        $this->assertCount(2, $body['hosts']);
    }

    public function test_flush_succeeds_on_valid_response(): void
    {
        $this->fakeCloudflareResponse(200, ['success' => true]);
        $this->invalidator->flush();
        // No exception = success. Verify request was actually made.
        $this->assertNotNull($this->lastRequest);
    }

    // ─── Host Derivation ───

    public function test_flush_strips_www_prefix_for_twin(): void
    {
        update_option('home', 'https://www.example.com');
        $this->fakeCloudflareResponse();
        $this->invalidator->flush();

        $body = json_decode($this->lastRequest['body'], true);
        $this->assertContains('www.example.com', $body['hosts']);
        $this->assertContains('example.com', $body['hosts']);
        $this->assertCount(2, $body['hosts']);
    }

    public function test_flush_deduplicates_hosts(): void
    {
        add_filter('sitchco/cache/cloudflare_purge_hosts', function ($hosts) {
            $hosts[] = $hosts[0]; // duplicate the first host
            return $hosts;
        });
        $this->fakeCloudflareResponse();
        $this->invalidator->flush();

        $body = json_decode($this->lastRequest['body'], true);
        $this->assertCount(
            count(array_unique($body['hosts'])),
            $body['hosts'],
            'Host list should contain no duplicates',
        );
    }

    // ─── Filter Hook ───

    public function test_flush_applies_purge_hosts_filter(): void
    {
        add_filter('sitchco/cache/cloudflare_purge_hosts', function ($hosts) {
            $hosts[] = 'cdn.example.com';
            return $hosts;
        });
        $this->fakeCloudflareResponse();
        $this->invalidator->flush();

        $body = json_decode($this->lastRequest['body'], true);
        $this->assertContains('cdn.example.com', $body['hosts']);
    }

    // ─── Validation ───

    public function test_flush_throws_on_empty_hosts(): void
    {
        add_filter('sitchco/cache/cloudflare_purge_hosts', fn() => []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('at least one host');
        $this->invalidator->flush();
    }

    public function test_flush_throws_on_too_many_hosts(): void
    {
        add_filter(
            'sitchco/cache/cloudflare_purge_hosts',
            fn() => array_map(fn($i) => "host{$i}.example.com", range(1, 31)),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum of 30 hosts');
        $this->invalidator->flush();
    }

    // ─── Error Paths ───

    public function test_flush_throws_on_wp_error(): void
    {
        $this->fakeHttp(fn() => new \WP_Error('http_request_failed', 'Connection timed out'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cloudflare purge request failed');
        $this->invalidator->flush();
    }

    public function test_flush_throws_on_http_error_status(): void
    {
        $this->fakeCloudflareResponse(403, [
            'success' => false,
            'errors' => [['code' => 10000, 'message' => 'Auth error']],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 403');
        $this->invalidator->flush();
    }

    public function test_flush_throws_on_api_failure_response(): void
    {
        $this->fakeCloudflareResponse(200, [
            'success' => false,
            'errors' => [['code' => 1234, 'message' => 'Zone not found']],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cloudflare purge failed');
        $this->invalidator->flush();
    }
}
