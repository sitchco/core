

2 themes need design context · 3 need investigation · 4 actionable

## Needs Design Context

### Filter hook key rename without deprecation
**Decision:** Was dropping the old `rocket_active`/`cloudflare_installed`/`cloudfront_installed` filter keys intentional, and are there any external consumers that need a migration path?

- `Invalidator.php:21` — Condition filter keys renamed from enum values (`rocket_active`, `cloudflare_installed`, `cloudfront_installed`) to slugs (`wp_rocket`, `cloudflare`, `cloudfront`) with no compatibility layer (codex-invalidator, severity: high)
- `SPEC.md:111` — Documentation introduces only the new hook names with no mention of old keys being removed (codex-invalidator, severity: low)
- `ObjectCacheInvalidator.php:7-17` — Object cache availability changed from unconditional `true` to filterable via `sitchco/cache/condition/object_cache`, a behavior change from prior implementation (codex-invalidator, severity: low)

**Agent reasoning:** Codex flagged this as high severity because any external code hooked to the old filter names would silently stop working. The rename is clean and consistent, but the question is whether anyone outside this codebase uses these hooks. If this is purely internal, it's fine. If not, a deprecation notice or dual-fire would be needed.

### Invalidator changed from interface to abstract class
**Decision:** Are there any external/third-party classes that `implements Invalidator`, and is the BC break acceptable?

- `Invalidator.php:7` — `Invalidator` changed from `interface` to `abstract class`, breaking any code using `implements Invalidator` (codex-invalidator, severity: medium)
- `Invalidator.php:19` — `isAvailable()` is not `final`, so subclasses can bypass `checkAvailability()` and the standard filter hook (codex-invalidator, severity: low)

**Agent reasoning:** Codex noted that `implements Invalidator` → `extends Invalidator` is a source-level BC break. The non-final `isAvailable()` is a related design question: if the template method pattern is intended to enforce uniform filter application, making it `final` would lock that in. Both findings hinge on whether external extension is expected.

## Needs Investigation

### Malformed queue data can crash processing
**Decision:** Should the queue processing path be hardened against non-array rows in the WordPress option?

- `CacheQueue.php:117-119` — `fn(array $row)` type hint throws `TypeError` if `wp_options` data contains non-array elements before `fromArray()` can handle it (claude-queue, codex-queue, severity: low/medium)
- `PendingInvalidation.php:24-32` — `fromArray()` coerces type-invalid payloads (e.g., `'expires' => 'foo'` → `0`) instead of rejecting them, producing immediately-expired items that reprocess every cron tick (claude-queue, codex-queue, severity: low)

**Agent reasoning:** Both agents independently identified the same two-layer problem: the outer `array` type hint crashes before `fromArray()` runs, and `fromArray()` itself doesn't validate types, only key presence. Claude noted the theoretical infinite-loop scenario with `delay=0` from bad casts. Codex rated the TypeError path as medium. The practical question is how likely database corruption or manual editing of this option is.

### CDN_INVALIDATORS ⊆ ALL_INVALIDATORS not enforced
**Decision:** Should `CDN_INVALIDATORS` be derived from `ALL_INVALIDATORS` rather than maintained as a separate list?

- `CacheInvalidation.php:37-44` — `CDN_INVALIDATORS` and `ALL_INVALIDATORS` duplicate CDN class names with no single source of truth; adding to one but not the other causes silent failures (claude-di, severity: medium; codex-di, severity: low)

**Agent reasoning:** Both agents found the same issue but disagreed on severity. Claude rated medium because a CDN invalidator added to `CDN_INVALIDATORS` but not `ALL_INVALIDATORS` would be queued but never registered in the slug map, causing silent drops. Codex rated low, likely because the list is small and rarely changes. A simple fix would be deriving `CDN_INVALIDATORS` from `ALL_INVALIDATORS` or adding a static assertion.

### Empty queue from malformed data skips completion hook
**Decision:** Should the malformed-drain path emit the completion hook for consistency?

- `CacheQueue.php:121-124` — When all rows are discarded as malformed, `delete_option()` runs without firing `Hooks::name('cache', 'complete')`, creating two distinct terminal behaviors for an empty queue (codex-queue, severity: low)

**Agent reasoning:** Codex noted that downstream listeners expecting the completion hook would never see it if the queue empties via the malformed-data path. Whether this matters depends on what's hooked to the completion event.

## Actionable

### Stale TESTING.md references deleted files and wrong types
**Decision:** Update or remove?

- `TESTING.md:27-28` — References `CacheCondition.php` (deleted) and describes `Invalidator.php` as an interface (now abstract class) (claude-invalidator, severity: low)

**Agent reasoning:** Straightforward documentation staleness. The file descriptions in the "Key Code Locations" table no longer match the codebase.

### Stale docblock on resolveInvalidator
**Decision:** Update the comment?

- `CacheQueue.php:168-169` — Docblock says "fresh invalidator instance" but method now returns a cached instance from `slugMap` (claude-queue, codex-queue, severity: low)

**Agent reasoning:** Both agents flagged the same stale comment. The old implementation did `new $class()` (fresh instance), but the refactored code returns the stored instance from `registerInvalidators()`.

### Test gaps in PendingInvalidation and queue serialization tests
**Decision:** Add the missing edge case coverage?

- `tests/Modules/PendingInvalidationTest.php:36-39` — `fromArray` malformed-row test doesn't cover non-array rows, wrong-type values, null values, or extra keys (claude-queue, codex-queue, severity: low)
- `tests/Modules/CacheInvalidationTest.php:359-366` — `test_queue_option_stores_arrays_not_objects` passes vacuously if nothing is stored; no assertion that `$stored` is non-empty (codex-queue, severity: low)
- `tests/Modules/CacheInvalidationTest.php:43-45` — `tearDown` doesn't clean up `sitchco/cache/condition/object_cache` filter; latent leak if a future test adds one (claude-invalidator, severity: low)

**Agent reasoning:** Claude provided specific edge cases worth testing: string-typed numeric fields that silently coerce, null values that pass `isset()` differently than expected, and the `delay=0` infinite-reprocess scenario. Codex noted the vacuous-pass risk in the serialization test.

### Redundant container lookups and inconsistent evaluation timing
**Decision:** Resolve all invalidators once in `init()` and reuse?

- `CacheInvalidation.php:52-55,96` — Same invalidator classes resolved from container 3+ times during `init()` despite line 52 already resolving all four (claude-di, codex-di, severity: low)
- `CacheInvalidation.php:66` — `cdnInvalidators()` called lazily inside `after_rocket_clean_domain` closure vs. eagerly in `delegatedRoutes()`; safe due to singletons but inconsistent (claude-di, severity: low)
- `CacheInvalidation.php:48` — Concrete `DI\Container` type-hint rather than `Psr\Container\ContainerInterface`; consistent with project pattern in `BackgroundProcessing.php` (claude-di, severity: low)

**Agent reasoning:** Claude noted that resolving once and storing would make the singleton assumption explicit and eliminate the inconsistent eager/lazy evaluation timing. The PSR-11 type-hint finding is included here as it relates to the same container usage pattern; Claude acknowledged it's consistent with existing project conventions.
