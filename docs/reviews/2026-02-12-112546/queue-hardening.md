# Queue Data Hardening

Malformed queue data can crash processing or cause silent misbehavior. Needs a design decision on validation strategy, plus test coverage for the hardened paths.

---

## `CacheQueue.php:117-119`, `PendingInvalidation.php:24-32` — Malformed queue data can crash processing

Two layers of the problem:

1. The `fn(array $row)` type hint in the `array_map` call throws a `TypeError` if the `wp_options` data contains non-array elements — the crash happens before `fromArray()` ever runs.

2. `fromArray()` silently coerces type-invalid payloads rather than rejecting them. For example, `'expires' => 'foo'` casts to `0`, producing an immediately-expired item that reprocesses every cron tick.

```php
// CacheQueue.php:117-118 — TypeError if $rawQueue contains non-array elements
$queue = array_values(
    array_filter(array_map(fn(array $row) => PendingInvalidation::fromArray($row), $rawQueue)),
);
```

### Design questions

- Should the outer type hint be relaxed to `mixed` with an `is_array()` check, or should the whole block be wrapped in a try/catch?
- Should `fromArray()` validate types and reject bad data (return `null`), or is silent coercion acceptable?
- If bad rows are logged and discarded, should there be a cap or alert for repeated corruption?

## `PendingInvalidationTest.php:36-39`, `CacheInvalidationTest.php:359-366,43-45` — Test gaps for queue data paths

Test coverage is needed for whichever validation strategy is chosen above:

- `fromArray` malformed-row test doesn't cover non-array rows, wrong-type values (e.g., string-typed numeric fields that silently coerce), null values, or extra keys
- `test_queue_option_stores_arrays_not_objects` passes vacuously if nothing is stored — no assertion that `$stored` is non-empty
- `tearDown` doesn't clean up the `sitchco/cache/condition/object_cache` filter (latent leak)

```php
// CacheInvalidationTest.php:359-366 — vacuous pass
$stored = get_option(CacheQueue::OPTION_NAME, []);
foreach ($stored as $item) {
    $this->assertIsArray($item);  // never executes if $stored is empty
}
```

### Suggested test cases

- Non-array row in queue option (string, int, null)
- Row with wrong-type values (`'expires' => 'foo'`, `'delay' => null`)
- Row with missing required keys
- Row with extra unexpected keys
- Assert `$stored` is non-empty before iterating in serialization test
