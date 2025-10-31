<?php

namespace Sitchco\Tests\Utils;

use Sitchco\Utils\Cache;
use Sitchco\Tests\TestCase;

class CacheTest extends TestCase
{
    protected function tearDown(): void
    {
        wp_cache_delete('cache-key', 'custom_group');
        delete_option('persistent_cache_key');
        delete_option('persistent_cache_key_no_ttl');
        parent::tearDown();
    }

    public function test_remember_uses_cache_group_and_ttl(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'cached-value';
        };

        $first = Cache::remember('cache-key', $callback, 60, 'custom_group');
        $second = Cache::remember('cache-key', $callback, 60, 'custom_group');

        $this->assertSame('cached-value', $first);
        $this->assertSame('cached-value', $second);
        $this->assertSame(1, $calls, 'Callback should only run once.');
        $this->assertSame('cached-value', wp_cache_get('cache-key', 'custom_group'));
    }

    public function test_remember_option_with_ttl_respects_expiration_metadata(): void
    {
        $result = Cache::rememberOption('persistent_cache_key', fn() => 'value-one', 60);
        $stored = get_option('persistent_cache_key');

        $this->assertSame('value-one', $result);
        $this->assertIsArray($stored);
        $this->assertSame('value-one', $stored['value']);
        $this->assertArrayHasKey('__cache_meta', $stored);
        $this->assertArrayHasKey('expires_at', $stored['__cache_meta']);

        $expired = $stored;
        $expired['__cache_meta']['expires_at'] = time() - 10;
        update_option('persistent_cache_key', $expired, false);

        $refreshed = Cache::rememberOption('persistent_cache_key', fn() => 'value-two', 60);
        $this->assertSame('value-two', $refreshed);
    }

    public function test_remember_option_without_ttl_persists_plain_value(): void
    {
        $result = Cache::rememberOption('persistent_cache_key_no_ttl', fn() => ['data' => 'value']);
        $this->assertSame(['data' => 'value'], $result);
        $this->assertSame(['data' => 'value'], get_option('persistent_cache_key_no_ttl'));
    }
}
