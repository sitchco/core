## Issues

### `CacheQueue.php:117-119`, `PendingInvalidation.php:24-32` — Malformed queue data can crash processing

Two agents independently identified a two-layer problem in queue processing:

1. The `fn(array $row)` type hint in the `array_map` call throws a `TypeError` if the `wp_options` data contains non-array elements — the crash happens before `fromArray()` ever runs.

2. `fromArray()` silently coerces type-invalid payloads rather than rejecting them. For example, `'expires' => 'foo'` casts to `0`, producing an immediately-expired item that reprocesses every cron tick.

```php
// CacheQueue.php:117-118 — TypeError if $rawQueue contains non-array elements
$queue = array_values(
    array_filter(array_map(fn(array $row) => PendingInvalidation::fromArray($row), $rawQueue)),
);
```

**Verify:** Confirm whether the queue option can realistically contain non-array rows (e.g., via manual DB edits, serialization bugs, or plugin conflicts). Consider wrapping the type hint or adding validation in `fromArray()` to reject rather than coerce bad values.

### `tests/Modules/PendingInvalidationTest.php:36-39`, `CacheInvalidationTest.php:359-366,43-45` — Test gaps in PendingInvalidation and queue serialization

Three test gaps relate to the malformed data issue above:

- `fromArray` malformed-row test doesn't cover non-array rows, wrong-type values (e.g., string-typed numeric fields that silently coerce), null values, or extra keys
- `test_queue_option_stores_arrays_not_objects` passes vacuously if nothing is stored — there's no assertion that `$stored` is non-empty, so the test gives false confidence
- `tearDown` doesn't clean up the `sitchco/cache/condition/object_cache` filter — a latent leak if a future test hooks it

```php
// CacheInvalidationTest.php:359-366 — vacuous pass
$stored = get_option(CacheQueue::OPTION_NAME, []);
foreach ($stored as $item) {
    $this->assertIsArray($item);  // never executes if $stored is empty
}
```

**Verify:** Add edge case coverage for `fromArray()` (non-array input, wrong types, nulls). Assert `$stored` is non-empty before iterating. Evaluate whether the tearDown filter cleanup is worth adding now or can wait until a test actually needs it.

### `CacheInvalidation.php:52-55,96` — Redundant container lookups and inconsistent evaluation timing

The same invalidator classes are resolved from the DI container 3+ times during `init()`. Line 52 already resolves all four invalidators, but lines 55, 69, 96, and 107 resolve individual ones again. This is safe because the container returns singletons, but it makes the code harder to follow.

Additionally, `cdnInvalidators()` is called lazily inside a closure (line 66) but eagerly in `delegatedRoutes()` (line 96) — functionally equivalent due to singletons, but inconsistent.

```php
// Line 52 — resolves all four
$invalidators = array_map(fn(string $class) => $this->container->get($class), self::ALL_INVALIDATORS);

// Line 55 — resolves WPRocketInvalidator again
$rocketInvalidator = $this->container->get(WPRocketInvalidator::class);

// Line 69 — resolves CloudflareInvalidator again
$cloudflareInvalidator = $this->container->get(CloudflareInvalidator::class);
```

**Verify:** Consider resolving once in `init()` and storing references, which would make the singleton assumption explicit and eliminate the eager/lazy inconsistency.

### `CacheInvalidation.php:37-44` — `CDN_INVALIDATORS` not derived from `ALL_INVALIDATORS`

`CDN_INVALIDATORS` and `ALL_INVALIDATORS` duplicate CDN class names with no single source of truth. If a new CDN invalidator were added to `CDN_INVALIDATORS` but not `ALL_INVALIDATORS`, it would be queued via `write()` but never registered in the `slugMap`, causing silent drops during `process()` — items queued but never executed, with no error or log.

```php
public const CDN_INVALIDATORS = [CloudFrontInvalidator::class, CloudflareInvalidator::class];

public const ALL_INVALIDATORS = [
    ObjectCacheInvalidator::class,
    WPRocketInvalidator::class,
    CloudFrontInvalidator::class,    // duplicated
    CloudflareInvalidator::class,    // duplicated
];
```

**Verify:** Consider deriving `CDN_INVALIDATORS` from `ALL_INVALIDATORS` (e.g., filtering by a marker interface or tag), or adding a static assertion that `CDN_INVALIDATORS` is a subset of `ALL_INVALIDATORS`.

---

## Nitpicks

### `CacheQueue.php:168-169` — Stale docblock on `resolveInvalidator`

The docblock says "fresh invalidator instance" but the method now returns a cached instance from `slugMap`. The old implementation used `new $class()` (fresh instance), but the refactored code returns the stored instance from `registerInvalidators()`.

**Verify:** Update the docblock to reflect that this returns a registered instance, not a new one.

### `TESTING.md:27-28` — Stale references to deleted files and wrong types

The "Key Code Locations" table references `CacheCondition.php` (deleted) and describes `Invalidator.php` as an interface (now an abstract class).

**Verify:** Update or remove the stale entries to match the current codebase.
