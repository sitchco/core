```yaml
findings:
  - branch: "queue-value-object"
    location: "modules/CacheInvalidation/CacheQueue.php:117-119"
    what: "Malformed queue entries that are not arrays will crash processing before they can be dropped."
    evidence: |
      The sanitization path uses a typed callback:
      `array_map(fn(array $row) => PendingInvalidation::fromArray($row), $rawQueue)`
      If `$rawQueue` contains a non-array element (e.g. string/object), PHP throws a TypeError before `fromArray()` runs.
      Repro in PHP 8.2: `TypeError: {closure}(): Argument #1 ($row) must be of type array, string given`.
    severity: medium
    confidence: high

  - branch: "queue-value-object"
    location: "modules/CacheInvalidation/PendingInvalidation.php:24-32"
    what: "fromArray() treats type-invalid payloads as valid by coercing values instead of rejecting them."
    evidence: |
      Validation only checks key presence:
      `if (!isset($row['slug'], $row['expires'], $row['delay']))`
      Then coerces:
      `slug: (string) $row['slug'], expires: (int) $row['expires'], delay: (int) $row['delay']`
      So values like `'expires' => 'foo'` become `0` (immediate expiry) rather than being dropped as malformed.
    severity: low
    confidence: high

  - branch: "queue-value-object"
    location: "modules/CacheInvalidation/CacheQueue.php:121-124"
    what: "When all rows are discarded as malformed, the queue is deleted without emitting the completion hook."
    evidence: |
      Malformed-drain path:
      `if (empty($queue)) { delete_option(self::OPTION_NAME); return; }`
      Normal drain path later emits completion:
      `do_action(Hooks::name('cache', 'complete'));`
      This creates two terminal behaviors for an empty queue.
    severity: low
    confidence: medium

  - branch: "queue-value-object"
    location: "modules/CacheInvalidation/CacheQueue.php:168-170"
    what: "resolveInvalidator() docblock is stale and now contradicts behavior."
    evidence: |
      Comment says:
      `Resolve a slug to a fresh invalidator instance for flush execution.`
      Implementation now returns a stored instance:
      `return $this->slugMap[$slug] ?? null;`
    severity: low
    confidence: high

  - branch: "queue-value-object"
    location: "tests/Modules/CacheInvalidationTest.php:359-366"
    what: "test_queue_option_stores_arrays_not_objects can pass vacuously if nothing is stored."
    evidence: |
      The test iterates `foreach ($stored as $item)` and asserts each item is an array,
      but never asserts `$stored` is non-empty. If queue creation regresses to an empty array, this test still passes.
    severity: low
    confidence: high

  - branch: "queue-value-object"
    location: "tests/Modules/PendingInvalidationTest.php:36-40"
    what: "Malformed-input coverage is narrow and misses key edge cases introduced by the new hydration path."
    evidence: |
      Current malformed cases only assert:
      `PendingInvalidation::fromArray(['slug' => 'test'])`
      `PendingInvalidation::fromArray([])`
      There is no coverage for non-array queue rows (process() TypeError path) or type-invalid scalar values that are coerced.
    severity: low
    confidence: high
```
