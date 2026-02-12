# Code Review: DI Container Integration

## Context

Read the Review Brief at `/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/docs/reviews/2026-02-12-112546/define/review-brief.md` for overall change context, focusing on the **di-container** section.

A design document is available at `/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/docs/reviews/2026-02-12-112546/define/design.md`. Check the implementation against the second and third design suggestions ("Use the DI Container for Invalidator Instantiation").

## Scope

**Your review covers:**
- `CacheInvalidation.php` — constructor now takes `Container`, invalidators resolved via `$this->container->get()`, new class constant lists (`ALL_INVALIDATORS`, `CDN_INVALIDATORS`), changes to `init()`, `delegatedRoutes()`, `standaloneRoutes()`, `cdnInvalidators()`
- Constructor signature changes in `CacheInvalidationTest.php` (i.e., `new CacheInvalidation($this->queue, $this->container)`)

**Do not report findings outside this scope.** The Invalidator abstraction hierarchy and the PendingInvalidation value object are handled by other branches.

## The Diff

```diff
diff --git a/modules/CacheInvalidation/CacheInvalidation.php b/modules/CacheInvalidation/CacheInvalidation.php
index 5a12e4a..433272a 100644
--- a/modules/CacheInvalidation/CacheInvalidation.php
+++ b/modules/CacheInvalidation/CacheInvalidation.php
@@ -11,6 +11,7 @@ use Sitchco\Modules\PostDeployment;
 use Sitchco\Modules\PostLifecycle;
 use Sitchco\Utils\Hooks;
 use Sitchco\Utils\Logger;
+use DI\Container;

 /**
  * Cache invalidation orchestrator.
@@ -33,20 +34,26 @@ class CacheInvalidation extends Module
     public const DEPENDENCIES = [Cron::class, PostLifecycle::class, PostDeployment::class, AcfLifecycle::class];
     public const HOOK_SUFFIX = 'cache';

+    public const CDN_INVALIDATORS = [CloudFrontInvalidator::class, CloudflareInvalidator::class];
+
+    public const ALL_INVALIDATORS = [
+        ObjectCacheInvalidator::class,
+        WPRocketInvalidator::class,
+        CloudFrontInvalidator::class,
+        CloudflareInvalidator::class,
+    ];
+
     private bool $syncFlushed = false;

-    public function __construct(private CacheQueue $queue) {}
+    public function __construct(private CacheQueue $queue, private Container $container) {}

     public function init(): void
     {
-        $this->queue->registerInvalidators([
-            new ObjectCacheInvalidator(),
-            new WPRocketInvalidator(),
-            new CloudFrontInvalidator(),
-            new CloudflareInvalidator(),
-        ]);
+        $invalidators = array_map(fn(string $class) => $this->container->get($class), self::ALL_INVALIDATORS);
+        $this->queue->registerInvalidators($invalidators);

-        $isDelegated = CacheCondition::RocketActive->check();
+        $rocketInvalidator = $this->container->get(WPRocketInvalidator::class);
+        $isDelegated = $rocketInvalidator->isAvailable();

         $routes = $isDelegated ? $this->delegatedRoutes() : $this->standaloneRoutes();

@@ -59,15 +66,14 @@ class CacheInvalidation extends Module
             add_action('after_rocket_clean_domain', fn() => $this->queue->write($this->cdnInvalidators()));
         }

-        // Cloudflare filter registration
-        if (CacheCondition::CloudflareInstalled->check()) {
+        $cloudflareInvalidator = $this->container->get(CloudflareInvalidator::class);
+        if ($cloudflareInvalidator->isAvailable()) {
             add_filter(
                 'cloudflare_purge_everything_actions',
                 fn(array $actions) => [...$actions, CloudflareInvalidator::PURGE_ACTION],
             );
         }

-        // Hook queue processor to minutely cron
         add_action(Hooks::name('cron', 'minutely'), [$this->queue, 'process']);
     }

@@ -85,14 +91,9 @@ class CacheInvalidation extends Module
         wp_cache_flush();
     }

-    /**
-     * Delegated Mode route map.
-     *
-     * @return array<string, Invalidator[]> hook name => invalidator instances
-     */
     private function delegatedRoutes(): array
     {
-        $rocketAndCdns = [new WPRocketInvalidator(), new CloudFrontInvalidator(), new CloudflareInvalidator()];
+        $rocketAndCdns = [$this->container->get(WPRocketInvalidator::class), ...$this->cdnInvalidators()];

         return [
             PostLifecycle::hookName('visibility_changed') => $rocketAndCdns,
@@ -101,14 +102,9 @@ class CacheInvalidation extends Module
         ];
     }

-    /**
-     * Standalone Mode route map.
-     *
-     * @return array<string, Invalidator[]> hook name => invalidator instances
-     */
     private function standaloneRoutes(): array
     {
-        $objectCacheAndCdns = [new ObjectCacheInvalidator(), new CloudFrontInvalidator(), new CloudflareInvalidator()];
+        $objectCacheAndCdns = [$this->container->get(ObjectCacheInvalidator::class), ...$this->cdnInvalidators()];

         return [
             PostLifecycle::hookName('content_updated') => $objectCacheAndCdns,
@@ -119,13 +115,8 @@ class CacheInvalidation extends Module
         ];
     }

-    /**
-     * CDN-only invalidators for after_rocket_clean_domain in Delegated Mode.
-     *
-     * @return Invalidator[]
-     */
     private function cdnInvalidators(): array
     {
-        return [new CloudFrontInvalidator(), new CloudflareInvalidator()];
+        return array_map(fn(string $class) => $this->container->get($class), self::CDN_INVALIDATORS);
     }
 }
diff --git a/tests/Modules/CacheInvalidationTest.php b/tests/Modules/CacheInvalidationTest.php
--- a/tests/Modules/CacheInvalidationTest.php
+++ b/tests/Modules/CacheInvalidationTest.php
(constructor signature changes throughout — every occurrence of:)
-        $module = new CacheInvalidation($this->queue);
+        $module = new CacheInvalidation($this->queue, $this->container);
```

## Review Process

**Before analyzing the diff, investigate the codebase:**

1. **Understand context** - Read the full files being modified, not just the diff. Understand what the code does and how it fits into the system.
2. **Check patterns** - Look at similar code elsewhere in the project. Does this change follow established conventions? How do other modules receive the DI container?
3. **Trace dependencies** - How is `CacheInvalidation` itself instantiated? Does the container auto-resolve the `Container` dependency? Are there any places that construct `CacheInvalidation` outside of tests?
4. **Review related tests** - Do all test cases pass the container correctly?
5. **Look for ripple effects** - Are there other modules that use a similar manual instantiation pattern that should also be migrated?

**Key questions for this branch:**
- Is it idiomatic in this project to inject `DI\Container` directly, or is there a preferred pattern (e.g., a factory, a service locator interface)?
- The `WPRocketInvalidator` is fetched from the container in `init()` AND again in `delegatedRoutes()` — are these the same instance (singleton)? Could this cause issues if they're different instances?
- The `cdnInvalidators()` method calls `$this->container->get()` each time it's invoked. In delegated mode, this happens both in `init()` (registering the `after_rocket_clean_domain` hook) and potentially when the hook fires. Are the instances stable?
- `ALL_INVALIDATORS` contains the same classes as `CDN_INVALIDATORS` plus two more. Is there risk of these lists drifting apart?

## Review Criteria

Evaluate against these criteria:

1. **Correctness** - Are all manual `new` instantiation calls eliminated? Does container resolution produce equivalent behavior? Are there identity/equality issues from resolving the same class multiple times?
2. **Consistency** - Does injecting `DI\Container` directly follow established patterns in this codebase? Do other modules use a similar approach?
3. **Maintainability** - Are the constant lists clear and maintainable? Is the relationship between `ALL_INVALIDATORS` and `CDN_INVALIDATORS` obvious?

## Output Format

Output your findings as YAML. Each finding must include:

| Field | Description |
|-------|-------------|
| `branch` | di-container |
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
  - branch: "di-container"
    location: "CacheInvalidation.php:52"
    what: "WPRocketInvalidator resolved twice — once in init() and once in delegatedRoutes()"
    evidence: |
      init():           $this->container->get(WPRocketInvalidator::class)
      delegatedRoutes(): $this->container->get(WPRocketInvalidator::class)
    severity: low
    confidence: high
```

If you find no issues, output:

```yaml
findings: []
```
