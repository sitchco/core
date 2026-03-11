<?php

namespace Sitchco\Tests\Utils;

use Sitchco\Utils\Cache;
use Sitchco\Tests\TestCase;

class CacheTest extends TestCase
{
    protected function tearDown(): void
    {
        wp_cache_delete('cache-key', 'custom_group');
        wp_cache_delete('null-key', 'sitchco');
        wp_cache_delete('false-key', 'sitchco');
        delete_option('persistent_cache_key');
        delete_option('persistent_cache_key_no_ttl');
        delete_option('option_null_key');
        delete_option('old_ttl_format_key');
        delete_transient('transient_falsy_key');
        delete_transient('transient_null_key');
        delete_transient('transient_null_no_opt_key');
        delete_transient('transient_zero_ttl_key');
        delete_transient('transient_false_key');
        delete_transient('wrapper_leak_key');
        delete_transient('forget_wrapped_key');
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
        $this->assertArrayHasKey('__sitchco_cache', $stored);
        $this->assertSame('value-one', $stored['_v']);
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

        $stored = get_option('persistent_cache_key_no_ttl');
        $this->assertIsArray($stored);
        $this->assertArrayHasKey('__sitchco_cache', $stored);
        $this->assertSame(['data' => 'value'], $stored['_v']);
    }

    // S4: Object cache stores null
    public function test_remember_caches_null(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return null;
        };

        $first = Cache::remember('null-key', $callback);
        $second = Cache::remember('null-key', $callback);

        $this->assertNull($first);
        $this->assertNull($second);
        $this->assertSame(1, $calls, 'Callback should only run once for null.');

        $found = false;
        $cached = wp_cache_get('null-key', 'sitchco', false, $found);
        $this->assertTrue($found);
        $this->assertNull($cached);
    }

    // S5: Object cache stores false
    public function test_remember_caches_false(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return false;
        };

        $first = Cache::remember('false-key', $callback);
        $second = Cache::remember('false-key', $callback);

        $this->assertFalse($first);
        $this->assertFalse($second);
        $this->assertSame(1, $calls, 'Callback should only run once for false.');

        $found = false;
        $cached = wp_cache_get('false-key', 'sitchco', false, $found);
        $this->assertTrue($found);
        $this->assertFalse($cached);
    }

    // S3: Transient caches falsy non-null values (0, '', [])
    public function test_rememberTransient_caches_falsy_non_null_values(): void
    {
        foreach ([0, '', []] as $falsyValue) {
            $key = 'transient_falsy_key';
            delete_transient($key);

            $calls = 0;
            $callback = function () use (&$calls, $falsyValue) {
                $calls++;
                return $falsyValue;
            };

            $first = Cache::rememberTransient($key, $callback);
            $second = Cache::rememberTransient($key, $callback);

            $this->assertSame($falsyValue, $first);
            $this->assertSame($falsyValue, $second);
            $this->assertSame(1, $calls, 'Callback should only run once for ' . var_export($falsyValue, true));
        }
    }

    // S6: Transient caches null with failureTtl
    public function test_rememberTransient_caches_null_with_failureTtl(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return null;
        };

        $first = Cache::rememberTransient('transient_null_key', $callback, DAY_IN_SECONDS, failureTtl: HOUR_IN_SECONDS);
        $second = Cache::rememberTransient(
            'transient_null_key',
            $callback,
            DAY_IN_SECONDS,
            failureTtl: HOUR_IN_SECONDS,
        );

        $this->assertNull($first);
        $this->assertNull($second);
        $this->assertSame(1, $calls, 'Callback should only run once when failureTtl is set.');

        $raw = get_transient('transient_null_key');
        $this->assertIsArray($raw);
        $this->assertArrayHasKey('__sitchco_cache', $raw);
        $this->assertNull($raw['_v']);
    }

    // S7: Transient does not cache null without failureTtl
    public function test_rememberTransient_does_not_cache_null_without_failureTtl(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return null;
        };

        Cache::rememberTransient('transient_null_no_opt_key', $callback);
        Cache::rememberTransient('transient_null_no_opt_key', $callback);

        $this->assertSame(2, $calls, 'Callback should run every time without failureTtl.');
    }

    // S8: failureTtl = 0 does not cache
    public function test_rememberTransient_failureTtl_zero_does_not_cache(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return null;
        };

        Cache::rememberTransient('transient_zero_ttl_key', $callback, DAY_IN_SECONDS, failureTtl: 0);
        Cache::rememberTransient('transient_zero_ttl_key', $callback, DAY_IN_SECONDS, failureTtl: 0);

        $this->assertSame(2, $calls, 'failureTtl=0 should not cache failures.');
    }

    // S9: Transient caches false with failureTtl
    public function test_rememberTransient_caches_false_with_failureTtl(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return false;
        };

        $first = Cache::rememberTransient(
            'transient_false_key',
            $callback,
            DAY_IN_SECONDS,
            failureTtl: HOUR_IN_SECONDS,
        );
        $second = Cache::rememberTransient(
            'transient_false_key',
            $callback,
            DAY_IN_SECONDS,
            failureTtl: HOUR_IN_SECONDS,
        );

        $this->assertFalse($first);
        $this->assertFalse($second);
        $this->assertSame(1, $calls, 'Callback should only run once for false with failureTtl.');
    }

    // S10: Option caches null with failureTtl
    public function test_rememberOption_caches_null_with_failureTtl(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return null;
        };

        $first = Cache::rememberOption('option_null_key', $callback, HOUR_IN_SECONDS, failureTtl: 300);
        $second = Cache::rememberOption('option_null_key', $callback, HOUR_IN_SECONDS, failureTtl: 300);

        $this->assertNull($first);
        $this->assertNull($second);
        $this->assertSame(1, $calls, 'Callback should only run once with failureTtl.');

        $raw = get_option('option_null_key');
        $this->assertIsArray($raw);
        $this->assertArrayHasKey('__sitchco_cache', $raw);
        $this->assertNull($raw['_v']);
        $this->assertArrayHasKey('__cache_meta', $raw);
    }

    // S11: Old TTL format coexistence
    public function test_rememberOption_reads_old_ttl_format(): void
    {
        // Seed with old format
        update_option(
            'old_ttl_format_key',
            [
                'value' => 'old-data',
                '__cache_meta' => [
                    'expires_at' => time() + 3600,
                    'created_at' => time(),
                ],
            ],
            false,
        );

        $result = Cache::rememberOption('old_ttl_format_key', fn() => 'new-data', 60);
        $this->assertSame('old-data', $result, 'Should read old TTL format correctly.');
    }

    // S1/S2: Wrapper not leaked to callers
    public function test_wrapper_not_leaked(): void
    {
        $result = Cache::rememberTransient('wrapper_leak_key', fn() => 'hello', DAY_IN_SECONDS);
        $this->assertSame('hello', $result);

        $cached = Cache::rememberTransient('wrapper_leak_key', fn() => 'should-not-run', DAY_IN_SECONDS);
        $this->assertSame('hello', $cached, 'Should return unwrapped value, not the wrapper array.');
    }

    // S2: forgetTransient works with wrapped values
    public function test_forgetTransient_works_with_wrapped_values(): void
    {
        Cache::rememberTransient('forget_wrapped_key', fn() => 'stored-value', DAY_IN_SECONDS);

        $raw = get_transient('forget_wrapped_key');
        $this->assertIsArray($raw);
        $this->assertArrayHasKey('__sitchco_cache', $raw);

        Cache::forgetTransient('forget_wrapped_key');

        $this->assertFalse(get_transient('forget_wrapped_key'), 'Transient should be deleted after forget.');

        $calls = 0;
        Cache::rememberTransient(
            'forget_wrapped_key',
            function () use (&$calls) {
                $calls++;
                return 'new-value';
            },
            DAY_IN_SECONDS,
        );

        $this->assertSame(1, $calls, 'Callback should run after forget.');
    }
}
