# Code Review: Queue Value Object

## Context

Read the Review Brief at `/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/docs/reviews/2026-02-12-112546/define/review-brief.md` for overall change context, focusing on the **queue-value-object** section.

A design document is available at `/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/docs/reviews/2026-02-12-112546/define/design.md`. Check the implementation against the third design suggestion ("Introduce a Value Object for Queue Items").

## Scope

**Your review covers:**
- `PendingInvalidation.php` (new) — readonly value object for queue items
- `CacheQueue.php` — slug map now stores instances, write buffer uses `PendingInvalidation`, process loop uses value object methods, `resolveInvalidator` simplified
- `PendingInvalidationTest.php` (new) — unit tests for the value object
- `test_queue_option_stores_arrays_not_objects` in `CacheInvalidationTest.php`

**Do not report findings outside this scope.** The Invalidator abstraction and DI container changes are handled by other branches.

## The Diff

```diff
diff --git a/modules/CacheInvalidation/PendingInvalidation.php b/modules/CacheInvalidation/PendingInvalidation.php
new file mode 100644
index 0000000..c59e38e
--- /dev/null
+++ b/modules/CacheInvalidation/PendingInvalidation.php
@@ -0,0 +1,51 @@
+<?php
+
+declare(strict_types=1);
+
+namespace Sitchco\Modules\CacheInvalidation;
+
+use Sitchco\Utils\Logger;
+
+final readonly class PendingInvalidation
+{
+    public function __construct(public string $slug, public int $expires, public int $delay) {}
+
+    public static function fromInvalidator(Invalidator $invalidator, ?int $now = null): self
+    {
+        $now ??= time();
+
+        return new self(
+            slug: $invalidator->slug(),
+            expires: $now + $invalidator->delay(),
+            delay: $invalidator->delay(),
+        );
+    }
+
+    public static function fromArray(array $row): ?self
+    {
+        if (!isset($row['slug'], $row['expires'], $row['delay'])) {
+            Logger::warning('[Cache] Dropping malformed queue row');
+
+            return null;
+        }
+
+        return new self(slug: (string) $row['slug'], expires: (int) $row['expires'], delay: (int) $row['delay']);
+    }
+
+    public function isExpired(?int $now = null): bool
+    {
+        return ($now ?? time()) >= $this->expires;
+    }
+
+    public function refresh(?int $now = null): self
+    {
+        $now ??= time();
+
+        return new self($this->slug, $now + $this->delay, $this->delay);
+    }
+
+    public function toArray(): array
+    {
+        return ['slug' => $this->slug, 'expires' => $this->expires, 'delay' => $this->delay];
+    }
+}
diff --git a/modules/CacheInvalidation/CacheQueue.php b/modules/CacheInvalidation/CacheQueue.php
index e3ed14d..6013cc7 100644
--- a/modules/CacheInvalidation/CacheQueue.php
+++ b/modules/CacheInvalidation/CacheQueue.php
@@ -22,9 +22,10 @@ class CacheQueue

     private bool $processing = false;

-    /** @var array<string, class-string> slug → invalidator class */
+    /** @var array<string, Invalidator> slug -> invalidator instance */
     private array $slugMap = [];

+    /** @var PendingInvalidation[]|null */
     private ?array $pendingWrite = null;

     private bool $shutdownRegistered = false;
@@ -42,7 +43,7 @@ class CacheQueue
     public function registerInvalidators(array $invalidators): void
     {
         foreach ($invalidators as $invalidator) {
-            $this->slugMap[$invalidator->slug()] = $invalidator::class;
+            $this->slugMap[$invalidator->slug()] = $invalidator;
         }
     }

@@ -70,17 +71,10 @@ class CacheQueue
         usort($available, fn($a, $b) => $a->priority() <=> $b->priority());

         $now = time();
-        $queue = [];
-
-        foreach ($available as $invalidator) {
-            $queue[] = [
-                'slug' => $invalidator->slug(),
-                'expires' => $now + $invalidator->delay(),
-                'delay' => $invalidator->delay(),
-            ];
-        }
-
-        $this->pendingWrite = $queue;
+        $this->pendingWrite = array_map(
+            fn(Invalidator $invalidator) => PendingInvalidation::fromInvalidator($invalidator, $now),
+            $available,
+        );

         if (!$this->shutdownRegistered) {
             add_action('shutdown', [$this, 'flushWriteBuffer']);
@@ -100,7 +94,11 @@ class CacheQueue

         $slugs = array_column($this->pendingWrite, 'slug');
         Logger::debug('[Cache] Queue written: ' . implode(', ', $slugs));
-        update_option(self::OPTION_NAME, $this->pendingWrite, false);
+        update_option(
+            self::OPTION_NAME,
+            array_map(fn(PendingInvalidation $item) => $item->toArray(), $this->pendingWrite),
+            false,
+        );
         $this->pendingWrite = null;
     }

@@ -110,42 +108,48 @@ class CacheQueue
      */
     public function process(): void
     {
-        $queue = get_option(self::OPTION_NAME, []);
+        $rawQueue = get_option(self::OPTION_NAME, []);
+
+        if (empty($rawQueue)) {
+            return;
+        }
+
+        $queue = array_values(
+            array_filter(array_map(fn(array $row) => PendingInvalidation::fromArray($row), $rawQueue)),
+        );

         if (empty($queue)) {
+            delete_option(self::OPTION_NAME);
             return;
         }

         $now = time();
         $first = $queue[0];

-        if ($first['expires'] > $now) {
+        if (!$first->isExpired($now)) {
             return;
         }

         array_shift($queue);

-        $invalidator = $this->resolveInvalidator($first['slug']);
+        $invalidator = $this->resolveInvalidator($first->slug);

         if ($invalidator !== null) {
-            Logger::debug("[Cache] Processing {$first['slug']}, " . count($queue) . ' remaining');
+            Logger::debug("[Cache] Processing {$first->slug}, " . count($queue) . ' remaining');
             $this->processing = true;

             try {
                 $invalidator->flush();
-                Logger::debug("[Cache] {$first['slug']} flushed successfully");
+                Logger::debug("[Cache] {$first->slug} flushed successfully");
             } catch (\Throwable $e) {
-                Logger::error("[Cache] Flush failed for {$first['slug']}: {$e->getMessage()}");
+                Logger::error("[Cache] Flush failed for {$first->slug}: {$e->getMessage()}");
             } finally {
                 $this->processing = false;
             }

-            foreach ($queue as &$item) {
-                $item['expires'] = $now + $item['delay'];
-            }
-            unset($item);
+            $queue = array_map(fn(PendingInvalidation $item) => $item->refresh($now), $queue);
         } else {
-            Logger::warning("[Cache] Unknown invalidator slug '{$first['slug']}', removing from queue");
+            Logger::warning("[Cache] Unknown invalidator slug '{$first->slug}', removing from queue");
         }

         if (empty($queue)) {
@@ -153,7 +157,11 @@ class CacheQueue
             Logger::debug('[Cache] Cascade complete');
             do_action(Hooks::name('cache', 'complete'));
         } else {
-            update_option(self::OPTION_NAME, $queue, false);
+            update_option(
+                self::OPTION_NAME,
+                array_map(fn(PendingInvalidation $item) => $item->toArray(), $queue),
+                false,
+            );
         }
     }

@@ -162,8 +170,6 @@ class CacheQueue
      */
     private function resolveInvalidator(string $slug): ?Invalidator
     {
-        $class = $this->slugMap[$slug] ?? null;
-
-        return $class ? new $class() : null;
+        return $this->slugMap[$slug] ?? null;
     }
 }
diff --git a/tests/Modules/PendingInvalidationTest.php b/tests/Modules/PendingInvalidationTest.php
new file mode 100644
index 0000000..c36216a
--- /dev/null
+++ b/tests/Modules/PendingInvalidationTest.php
@@ -0,0 +1,82 @@
+<?php
+
+namespace Sitchco\Tests\Modules;
+
+use Sitchco\Modules\CacheInvalidation\ObjectCacheInvalidator;
+use Sitchco\Modules\CacheInvalidation\PendingInvalidation;
+use Sitchco\Tests\TestCase;
+
+class PendingInvalidationTest extends TestCase
+{
+    public function test_fromInvalidator_creates_with_correct_values(): void
+    {
+        $invalidator = $this->container->get(ObjectCacheInvalidator::class);
+        $now = 1000000;
+        $item = PendingInvalidation::fromInvalidator($invalidator, $now);
+
+        $this->assertSame('object_cache', $item->slug);
+        $this->assertSame($now + 10, $item->expires);
+        $this->assertSame(10, $item->delay);
+    }
+
+    public function test_fromArray_hydrates_valid_row(): void
+    {
+        $item = PendingInvalidation::fromArray([
+            'slug' => 'cloudfront',
+            'expires' => 1000100,
+            'delay' => 100,
+        ]);
+
+        $this->assertNotNull($item);
+        $this->assertSame('cloudfront', $item->slug);
+        $this->assertSame(1000100, $item->expires);
+        $this->assertSame(100, $item->delay);
+    }
+
+    public function test_fromArray_returns_null_for_malformed_row(): void
+    {
+        $this->assertNull(PendingInvalidation::fromArray(['slug' => 'test']));
+        $this->assertNull(PendingInvalidation::fromArray([]));
+    }
+
+    public function test_isExpired_returns_true_when_time_equals_or_exceeds_expires(): void
+    {
+        $item = new PendingInvalidation('test', 1000, 10);
+        $this->assertTrue($item->isExpired(1000));
+        $this->assertTrue($item->isExpired(1001));
+    }
+
+    public function test_isExpired_returns_false_when_not_expired(): void
+    {
+        $item = new PendingInvalidation('test', 1000, 10);
+        $this->assertFalse($item->isExpired(999));
+    }
+
+    public function test_refresh_returns_new_instance_with_reset_expiration(): void
+    {
+        $item = new PendingInvalidation('cloudfront', 1000, 100);
+        $now = 2000;
+        $refreshed = $item->refresh($now);
+
+        $this->assertNotSame($item, $refreshed);
+        $this->assertSame('cloudfront', $refreshed->slug);
+        $this->assertSame($now + 100, $refreshed->expires);
+        $this->assertSame(100, $refreshed->delay);
+    }
+
+    public function test_toArray_returns_associative_array(): void
+    {
+        $item = new PendingInvalidation('object_cache', 1010, 10);
+        $this->assertSame(['slug' => 'object_cache', 'expires' => 1010, 'delay' => 10], $item->toArray());
+    }
+
+    public function test_toArray_roundtrips_through_fromArray(): void
+    {
+        $original = new PendingInvalidation('cloudflare', 2000, 100);
+        $restored = PendingInvalidation::fromArray($original->toArray());
+
+        $this->assertSame($original->slug, $restored->slug);
+        $this->assertSame($original->expires, $restored->expires);
+        $this->assertSame($original->delay, $restored->delay);
+    }
+}
diff --git a/tests/Modules/CacheInvalidationTest.php b/tests/Modules/CacheInvalidationTest.php
(relevant new test only)
+    public function test_queue_option_stores_arrays_not_objects(): void
+    {
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_false');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');
+        $module = new CacheInvalidation($this->queue, $this->container);
+        $module->init();
+
+        do_action('sitchco/post/content_updated');
+
+        $this->queue->flushWriteBuffer();
+        $stored = get_option(CacheQueue::OPTION_NAME, []);
+
+        foreach ($stored as $item) {
+            $this->assertIsArray($item, 'Queue items must be stored as arrays, not objects');
+            $this->assertArrayHasKey('slug', $item);
+            $this->assertArrayHasKey('expires', $item);
+            $this->assertArrayHasKey('delay', $item);
+        }
+    }
```

## Review Process

**Before analyzing the diff, investigate the codebase:**

1. **Understand context** - Read the full files being modified, not just the diff. Understand what the code does and how it fits into the system.
2. **Check patterns** - Look at similar value objects elsewhere in the project. Does `PendingInvalidation` follow the same conventions?
3. **Trace dependencies** - How is the queue option read by WordPress? Could the serialization format affect anything (e.g., `array_column` still works on objects with public properties)?
4. **Review related tests** - Are there edge cases not covered by `PendingInvalidationTest`?
5. **Look for ripple effects** - Does anything else read `CacheQueue::OPTION_NAME` from the database directly?

**Key questions for this branch:**
- `array_column($this->pendingWrite, 'slug')` is used in `flushWriteBuffer()`. Does `array_column` work on an array of objects with public properties? (It does in PHP 7.0+, but verify this is intentional.)
- The old `process()` checked `$first['expires'] > $now` (strict greater-than). The new code uses `$first->isExpired($now)` which checks `$now >= $this->expires` (greater-than-or-equal). This is a behavioral change — is it intentional?
- `fromArray()` silently drops malformed rows and logs a warning. In `process()`, if ALL rows are malformed, the option is deleted. Is this the right behavior?
- The `readonly` modifier means `PendingInvalidation` properties can't be reassigned after construction. Combined with `final`, this prevents subclassing. Is this intentional and appropriate?

## Review Criteria

Evaluate against these criteria:

1. **Correctness** - Is serialization/deserialization lossless? Are there behavioral differences from the array-based implementation (especially the expiration check boundary)? Does `array_column` work on object arrays?
2. **Testing** - Is `PendingInvalidationTest` comprehensive? Missing edge cases? Does `test_queue_option_stores_arrays_not_objects` adequately verify the serialization contract?
3. **Maintainability** - Is the value object well-designed? Is the `fromArray`/`toArray` pattern clear? Are the nullable `$now` parameters a good pattern or a testing convenience that leaks into the API?

## Output Format

Output your findings as YAML. Each finding must include:

| Field | Description |
|-------|-------------|
| `branch` | queue-value-object |
| `location` | file:line or file:line-range |
| `what` | Technical description of the problem |
| `evidence` | Code snippet, trace, or pattern that supports this |
| `severity` | high / medium / low |
| `confidence` | high / medium / low |

### Explicit Non-Goals

- Don't report findings outside this branch's scope
- Don't worry about tone or how findings will be communicated
- Don't filter based on whether something is "worth mentioning" — report everything
- Don't include positive notes or praise

### Example Output

```yaml
findings:
  - branch: "queue-value-object"
    location: "PendingInvalidation.php:37"
    what: "isExpired uses >= but old code used > — items expiring at exactly $now are now processed immediately"
    evidence: |
      Old: if ($first['expires'] > $now)
      New: return ($now ?? time()) >= $this->expires
    severity: medium
    confidence: high
```

If you find no issues, output:

```yaml
findings: []
```
