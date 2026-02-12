# Quick Fixes

Straightforward changes from code review — no design decisions needed.

---

## `CacheInvalidation.php:37-44` — Derive `CDN_INVALIDATORS` from `ALL_INVALIDATORS`

`CDN_INVALIDATORS` and `ALL_INVALIDATORS` duplicate CDN class names with no single source of truth. If a new CDN invalidator were added to `CDN_INVALIDATORS` but not `ALL_INVALIDATORS`, it would be queued via `write()` but never registered in the `slugMap`, causing silent drops during `process()`.

```php
public const CDN_INVALIDATORS = [CloudFrontInvalidator::class, CloudflareInvalidator::class];

public const ALL_INVALIDATORS = [
    ObjectCacheInvalidator::class,
    WPRocketInvalidator::class,
    CloudFrontInvalidator::class,    // duplicated
    CloudflareInvalidator::class,    // duplicated
];
```

**Fix:** Derive `CDN_INVALIDATORS` from `ALL_INVALIDATORS` (e.g., filtering by a marker interface or tag), or add a static assertion that `CDN_INVALIDATORS` is a subset of `ALL_INVALIDATORS`.

## `CacheInvalidation.php:52-55,69,96,107` — Resolve invalidators once and reuse

The same invalidator classes are resolved from the DI container 3+ times during `init()`. Line 52 already resolves all four, but lines 55, 69, 96, and 107 resolve them again individually. Safe due to singletons, but makes the code harder to follow. Additionally, `cdnInvalidators()` is called lazily (line 66) vs. eagerly (line 96) — inconsistent.

```php
// Line 52 — resolves all four
$invalidators = array_map(fn(string $class) => $this->container->get($class), self::ALL_INVALIDATORS);

// Line 55 — resolves WPRocketInvalidator again
$rocketInvalidator = $this->container->get(WPRocketInvalidator::class);

// Line 69 — resolves CloudflareInvalidator again
$cloudflareInvalidator = $this->container->get(CloudflareInvalidator::class);
```

**Fix:** Store references from the initial resolve on line 52 and reuse them throughout `init()`, `delegatedRoutes()`, `standaloneRoutes()`, and `cdnInvalidators()`.

## `CacheQueue.php:168-169` — Stale docblock on `resolveInvalidator`

The docblock says "fresh invalidator instance" but the method now returns a cached instance from `slugMap`.

**Fix:** Update the docblock to reflect that this returns a registered instance, not a new one.

## `TESTING.md:27-28` — Stale references to deleted files and wrong types

The "Key Code Locations" table references `CacheCondition.php` (deleted) and describes `Invalidator.php` as an interface (now an abstract class).

**Fix:** Update or remove the stale entries to match the current codebase.
