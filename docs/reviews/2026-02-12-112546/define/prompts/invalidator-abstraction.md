# Code Review: Invalidator Abstraction

## Context

Read the Review Brief at `/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/docs/reviews/2026-02-12-112546/define/review-brief.md` for overall change context, focusing on the **invalidator-abstraction** section.

A design document is available at `/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/docs/reviews/2026-02-12-112546/define/design.md`. Check the implementation against the first design suggestion ("Consolidate Per-Service Logic into Invalidator Classes").

## Scope

**Your review covers:**
- `Invalidator.php` — interface → abstract class with template method
- `CacheCondition.php` — deletion of the enum
- `ObjectCacheInvalidator.php`, `WPRocketInvalidator.php`, `CloudFrontInvalidator.php`, `CloudflareInvalidator.php` — `implements` → `extends`, `isAvailable()` → `checkAvailability()`
- `SPEC.md` — documentation of the new pattern
- Filter name changes in `CacheInvalidationTest.php` (e.g., `rocket_active` → `wp_rocket`)

**Do not report findings outside this scope.** The DI container integration and queue value object are handled by other branches.

## The Diff

```diff
diff --git a/modules/CacheInvalidation/CacheCondition.php b/modules/CacheInvalidation/CacheCondition.php
deleted file mode 100644
index b5778a4..0000000
--- a/modules/CacheInvalidation/CacheCondition.php
+++ /dev/null
@@ -1,25 +0,0 @@
-<?php
-
-declare(strict_types=1);
-
-namespace Sitchco\Modules\CacheInvalidation;
-
-enum CacheCondition: string
-{
-    case RocketActive = 'rocket_active';
-    case CloudflareInstalled = 'cloudflare_installed';
-    case CloudFrontInstalled = 'cloudfront_installed';
-
-    public function check(): bool
-    {
-        $result = match ($this) {
-            self::RocketActive => function_exists('rocket_clean_domain'),
-            self::CloudflareInstalled => defined('CLOUDFLARE_PLUGIN_DIR'),
-            self::CloudFrontInstalled => class_exists('CloudFront_Clear_Cache') &&
-                method_exists('CloudFront_Clear_Cache', 'get_instance') &&
-                method_exists('CloudFront_Clear_Cache', 'c3_invalidation'),
-        };
-
-        return (bool) apply_filters('sitchco/cache/condition/' . $this->value, $result);
-    }
-}
diff --git a/modules/CacheInvalidation/Invalidator.php b/modules/CacheInvalidation/Invalidator.php
index bd0bf60..d1c6987 100644
--- a/modules/CacheInvalidation/Invalidator.php
+++ b/modules/CacheInvalidation/Invalidator.php
@@ -4,15 +4,20 @@ declare(strict_types=1);

 namespace Sitchco\Modules\CacheInvalidation;

-interface Invalidator
+abstract class Invalidator
 {
-    public function slug(): string;
+    abstract public function slug(): string;

-    public function isAvailable(): bool;
+    abstract protected function checkAvailability(): bool;

-    public function priority(): int;
+    abstract public function priority(): int;

-    public function delay(): int;
+    abstract public function delay(): int;

-    public function flush(): void;
+    abstract public function flush(): void;
+
+    public function isAvailable(): bool
+    {
+        return (bool) apply_filters('sitchco/cache/condition/' . $this->slug(), $this->checkAvailability());
+    }
 }
diff --git a/modules/CacheInvalidation/CloudFrontInvalidator.php b/modules/CacheInvalidation/CloudFrontInvalidator.php
index ed5d599..b026882 100644
--- a/modules/CacheInvalidation/CloudFrontInvalidator.php
+++ b/modules/CacheInvalidation/CloudFrontInvalidator.php
@@ -4,16 +4,18 @@ declare(strict_types=1);

 namespace Sitchco\Modules\CacheInvalidation;

-class CloudFrontInvalidator implements Invalidator
+class CloudFrontInvalidator extends Invalidator
 {
     public function slug(): string
     {
         return 'cloudfront';
     }

-    public function isAvailable(): bool
+    protected function checkAvailability(): bool
     {
-        return CacheCondition::CloudFrontInstalled->check();
+        return class_exists('CloudFront_Clear_Cache') &&
+            method_exists('CloudFront_Clear_Cache', 'get_instance') &&
+            method_exists('CloudFront_Clear_Cache', 'c3_invalidation');
     }

     public function priority(): int
diff --git a/modules/CacheInvalidation/CloudflareInvalidator.php b/modules/CacheInvalidation/CloudflareInvalidator.php
index 2f281e3..695ec46 100644
--- a/modules/CacheInvalidation/CloudflareInvalidator.php
+++ b/modules/CacheInvalidation/CloudflareInvalidator.php
@@ -4,7 +4,7 @@ declare(strict_types=1);

 namespace Sitchco\Modules\CacheInvalidation;

-class CloudflareInvalidator implements Invalidator
+class CloudflareInvalidator extends Invalidator
 {
     public const PURGE_ACTION = 'sitchco/cloudflare_purge_cache';

@@ -13,9 +13,9 @@ class CloudflareInvalidator implements Invalidator
         return 'cloudflare';
     }

-    public function isAvailable(): bool
+    protected function checkAvailability(): bool
     {
-        return CacheCondition::CloudflareInstalled->check();
+        return defined('CLOUDFLARE_PLUGIN_DIR');
     }

     public function priority(): int
diff --git a/modules/CacheInvalidation/ObjectCacheInvalidator.php b/modules/CacheInvalidation/ObjectCacheInvalidator.php
index 1d6aaa2..f7586bc 100644
--- a/modules/CacheInvalidation/ObjectCacheInvalidator.php
+++ b/modules/CacheInvalidation/ObjectCacheInvalidator.php
@@ -4,14 +4,14 @@ declare(strict_types=1);

 namespace Sitchco\Modules\CacheInvalidation;

-class ObjectCacheInvalidator implements Invalidator
+class ObjectCacheInvalidator extends Invalidator
 {
     public function slug(): string
     {
         return 'object_cache';
     }

-    public function isAvailable(): bool
+    protected function checkAvailability(): bool
     {
         return true;
     }
diff --git a/modules/CacheInvalidation/WPRocketInvalidator.php b/modules/CacheInvalidation/WPRocketInvalidator.php
index 0184998..1fa5e3a 100644
--- a/modules/CacheInvalidation/WPRocketInvalidator.php
+++ b/modules/CacheInvalidation/WPRocketInvalidator.php
@@ -4,16 +4,16 @@ declare(strict_types=1);

 namespace Sitchco\Modules\CacheInvalidation;

-class WPRocketInvalidator implements Invalidator
+class WPRocketInvalidator extends Invalidator
 {
     public function slug(): string
     {
         return 'wp_rocket';
     }

-    public function isAvailable(): bool
+    protected function checkAvailability(): bool
     {
-        return CacheCondition::RocketActive->check();
+        return function_exists('rocket_clean_domain');
     }

     public function priority(): int
diff --git a/modules/CacheInvalidation/SPEC.md b/modules/CacheInvalidation/SPEC.md
index 0f8a556..ccf8af2 100644
--- a/modules/CacheInvalidation/SPEC.md
+++ b/modules/CacheInvalidation/SPEC.md
@@ -70,7 +70,7 @@ CDN means whichever CDN invalidators pass their condition checks, processed in p

 ### 1. Mode determination happens once, at the orchestration level

-The orchestrator (CacheInvalidation module) checks `CacheCondition::RocketActive` at initialization and configures the system accordingly. Individual invalidators do not branch on mode. They declare their capabilities (condition, delay, flush method) and the orchestrator decides when to invoke them.
+The orchestrator (CacheInvalidation module) checks the WP Rocket invalidator's `isAvailable()` at initialization and configures the system accordingly. Individual invalidators do not branch on mode. They declare their capabilities (condition, delay, flush method) and the orchestrator decides when to invoke them.

 **Why:** Distributing mode logic across every invalidator created branching complexity that was hard to reason about and test. Centralizing it means each invalidator is simple and stateless.

@@ -108,6 +108,8 @@ An invalidator does NOT declare what triggers it. The orchestrator decides what

 **Why:** Triggers are a property of the operating mode, not of the invalidator. CloudFront doesn't "know" it should listen to `AfterRocketClean` in Delegated Mode but `ContentChange` in Standalone Mode. That's an orchestration concern.

+Each invalidator's `isAvailable()` applies a WordPress filter `sitchco/cache/condition/{slug}` where `{slug}` is the invalidator's slug (`object_cache`, `wp_rocket`, `cloudfront`, `cloudflare`). This provides a uniform extension point for enabling or disabling any invalidator at runtime. The default value is the result of the invalidator's own availability check. Object cache defaults to `true` (always available) but is filterable like the rest.
+
 ### 6. Request-local write buffer eliminates redundant DB writes

 When multiple signals fire in the same request (e.g., draft→publish fires both `visibility_changed` and `content_updated`), the queue writer buffers writes in memory and flushes once at shutdown via a `register_shutdown_function` hook. Each signal handler still calls the writer normally — the writer just defers the `update_option` call. Since last-writer-wins is the queue's debounce mechanism, only the final write matters anyway.
@@ -142,9 +144,10 @@ The orchestrator also handles Cloudflare's setup requirement: the Cloudflare plu

 ### Invalidators

-Each invalidator is a value object with four properties:
+Each invalidator is a value object with five properties:

-- **`isAvailable(): bool`** — Is the backing service present?
+- **`slug(): string`** — Unique identifier used for queue storage and filter keys
+- **`isAvailable(): bool`** — Is the backing service present? (Template method: calls `checkAvailability()` then applies `sitchco/cache/condition/{slug}` filter)
 - **`priority(): int`** — Processing order (lower = earlier)
 - **`delay(): int`** — Settling time in seconds (relative to previous item)
 - **`flush(): void`** — The actual invalidation action
diff --git a/tests/Modules/CacheInvalidationTest.php b/tests/Modules/CacheInvalidationTest.php
index 9c06259..d0934ba 100644
--- a/tests/Modules/CacheInvalidationTest.php
+++ b/tests/Modules/CacheInvalidationTest.php
@@ -40,9 +40,9 @@ class CacheInvalidationTest extends TestCase
         foreach (self::SIGNAL_HOOKS as $hook) {
             remove_all_actions($hook);
         }
-        remove_all_filters('sitchco/cache/condition/rocket_active');
-        remove_all_filters('sitchco/cache/condition/cloudflare_installed');
-        remove_all_filters('sitchco/cache/condition/cloudfront_installed');
+        remove_all_filters('sitchco/cache/condition/wp_rocket');
+        remove_all_filters('sitchco/cache/condition/cloudflare');
+        remove_all_filters('sitchco/cache/condition/cloudfront');
         parent::tearDown();
     }

@@ -50,10 +50,10 @@ class CacheInvalidationTest extends TestCase

     public function test_delegated_content_updated_does_not_create_queue(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_true');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');

     public function test_delegated_visibility_changed_queues_rocket_and_cdns(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_true');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');

     public function test_delegated_deploy_complete_queues_rocket_and_cdns(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_true');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');

     public function test_delegated_clear_all_queues_rocket_and_cdns(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_true');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');

     public function test_delegated_after_rocket_clean_queues_cdns_only(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_true');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');

     public function test_delegated_queue_excludes_unavailable_invalidators(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_false');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_false');

     public function test_standalone_content_signal_queues_object_cache_and_cdns(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_false');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_false');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');

     public function test_standalone_queue_excludes_rocket(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_false');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_false');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');

     public function test_standalone_queue_excludes_unavailable_cdns(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_false');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_false');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_false');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_false');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_false');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_false');

     public function test_delegated_sync_flushes_object_cache_on_before_rocket_clean(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_false');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_false');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_true');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_false');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_false');

     public function test_delegated_sync_flush_executes_only_once_per_request(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_false');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_false');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_true');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_false');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_false');

     public function test_new_event_overwrites_existing_queue(): void
     {
-        add_filter('sitchco/cache/condition/rocket_active', '__return_false');
-        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
-        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
+        add_filter('sitchco/cache/condition/wp_rocket', '__return_false');
+        add_filter('sitchco/cache/condition/cloudflare', '__return_true');
+        add_filter('sitchco/cache/condition/cloudfront', '__return_true');
```

## Review Process

**Before analyzing the diff, investigate the codebase:**

1. **Understand context** - Read the full files being modified, not just the diff. Understand what the code does and how it fits into the system.
2. **Check patterns** - Look at similar code elsewhere in the project. Does this change follow established conventions?
3. **Trace dependencies** - Find code that calls into or is called by the modified code. Could this change break callers or callees?
4. **Review related tests** - Look at existing tests for the affected code.
5. **Look for ripple effects** - Are there other places that should be updated for consistency?

**Key questions for this branch:**
- The old filter keys were `rocket_active`, `cloudflare_installed`, `cloudfront_installed`. The new ones are `wp_rocket`, `cloudflare`, `cloudfront`. Is this a breaking change for any external code using these filters? Is it documented?
- Does the `isAvailable()` template method in the abstract class exactly reproduce the behavior from `CacheCondition::check()`?
- Are there any remaining references to `CacheCondition` anywhere in the codebase?
- The `checkAvailability()` method is `protected` — is that the right visibility for the template method pattern here?

## Review Criteria

Evaluate against these criteria:

1. **Correctness** - Does the template method preserve the exact same behavior as the deleted CacheCondition enum? Are filter keys consistently migrated everywhere?
2. **Consistency** - Do all four invalidators follow the same pattern uniformly? Does the abstract class fit with other abstractions in the project?
3. **Maintainability** - Is the template method pattern clear and easy to extend for future invalidators? Is the SPEC.md update accurate?

## Output Format

Output your findings as YAML. Each finding must include:

| Field | Description |
|-------|-------------|
| `branch` | invalidator-abstraction |
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
  - branch: "invalidator-abstraction"
    location: "Invalidator.php:18"
    what: "Filter key uses slug() but old code used CacheCondition enum value — these differ for WP Rocket"
    evidence: |
      Old: 'sitchco/cache/condition/rocket_active'
      New: 'sitchco/cache/condition/wp_rocket'
    severity: medium
    confidence: high
```

If you find no issues, output:

```yaml
findings: []
```
