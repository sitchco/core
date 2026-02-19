<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\CacheInvalidation\ObjectCacheInvalidator;
use Sitchco\Modules\CacheInvalidation\PendingInvalidation;
use Sitchco\Tests\TestCase;

class PendingInvalidationTest extends TestCase
{
    public function test_fromInvalidator_creates_with_correct_values(): void
    {
        $invalidator = $this->container->get(ObjectCacheInvalidator::class);
        $now = 1000000;
        $item = PendingInvalidation::fromInvalidator($invalidator, $now);

        $this->assertSame('object_cache', $item->slug);
        $this->assertSame($now + 10, $item->expires);
        $this->assertSame(10, $item->delay);
    }

    public function test_fromArray_hydrates_valid_row(): void
    {
        $item = PendingInvalidation::fromArray([
            'slug' => 'cloudfront',
            'expires' => 1000100,
            'delay' => 100,
        ]);

        $this->assertNotNull($item);
        $this->assertSame('cloudfront', $item->slug);
        $this->assertSame(1000100, $item->expires);
        $this->assertSame(100, $item->delay);
    }

    public function test_fromArray_returns_null_for_malformed_row(): void
    {
        // Missing keys
        $this->assertNull(PendingInvalidation::fromArray(['slug' => 'test']));
        $this->assertNull(PendingInvalidation::fromArray([]));

        // Wrong types
        $this->assertNull(PendingInvalidation::fromArray(['slug' => 'test', 'expires' => 'foo', 'delay' => 10]));
        $this->assertNull(PendingInvalidation::fromArray(['slug' => 'test', 'expires' => 1000, 'delay' => null]));
        $this->assertNull(PendingInvalidation::fromArray(['slug' => 123, 'expires' => 1000, 'delay' => 10]));
    }

    public function test_fromArray_accepts_extra_keys(): void
    {
        $item = PendingInvalidation::fromArray([
            'slug' => 'cloudfront',
            'expires' => 1000100,
            'delay' => 100,
            'extra' => 'ignored',
        ]);

        $this->assertNotNull($item);
        $this->assertSame('cloudfront', $item->slug);
    }

    public function test_isExpired_returns_true_when_time_equals_or_exceeds_expires(): void
    {
        $item = new PendingInvalidation('test', 1000, 10);
        $this->assertTrue($item->isExpired(1000));
        $this->assertTrue($item->isExpired(1001));
    }

    public function test_isExpired_returns_false_when_not_expired(): void
    {
        $item = new PendingInvalidation('test', 1000, 10);
        $this->assertFalse($item->isExpired(999));
    }

    public function test_refresh_returns_new_instance_with_reset_expiration(): void
    {
        $item = new PendingInvalidation('cloudfront', 1000, 100);
        $now = 2000;
        $refreshed = $item->refresh($now);

        $this->assertNotSame($item, $refreshed);
        $this->assertSame('cloudfront', $refreshed->slug);
        $this->assertSame($now + 100, $refreshed->expires);
        $this->assertSame(100, $refreshed->delay);
    }

    public function test_toArray_returns_associative_array(): void
    {
        $item = new PendingInvalidation('object_cache', 1010, 10);
        $this->assertSame(['slug' => 'object_cache', 'expires' => 1010, 'delay' => 10], $item->toArray());
    }

    public function test_toArray_roundtrips_through_fromArray(): void
    {
        $original = new PendingInvalidation('cloudflare', 2000, 100);
        $restored = PendingInvalidation::fromArray($original->toArray());

        $this->assertSame($original->slug, $restored->slug);
        $this->assertSame($original->expires, $restored->expires);
        $this->assertSame($original->delay, $restored->delay);
    }
}
