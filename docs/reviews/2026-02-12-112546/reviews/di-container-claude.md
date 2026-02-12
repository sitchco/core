Here are my findings:

```yaml
findings:
  - branch: "di-container"
    location: "CacheInvalidation.php:66"
    what: "cdnInvalidators() called inside closure — resolves container at hook-fire time, not at init() time"
    evidence: |
      Line 66:  add_action('after_rocket_clean_domain', fn() => $this->queue->write($this->cdnInvalidators()));
      Line 96:  $rocketAndCdns = [$this->container->get(WPRocketInvalidator::class), ...$this->cdnInvalidators()];

      In delegatedRoutes() (line 96), cdnInvalidators() is called eagerly during init(),
      producing instances that are captured in the $invalidators closure on line 61.

      But on line 66, cdnInvalidators() is inside a lazy closure — it runs each time
      after_rocket_clean_domain fires. Since PHP-DI returns singletons, these are the same
      instances, so behavior is correct. However, the two call sites have different evaluation
      timing (eager vs lazy), which is inconsistent and could confuse a reader about whether
      instance identity matters.
    severity: low
    confidence: high

  - branch: "di-container"
    location: "CacheInvalidation.php:37-44"
    what: "CDN_INVALIDATORS is a subset of ALL_INVALIDATORS with no code enforcing the relationship"
    evidence: |
      public const CDN_INVALIDATORS = [CloudFrontInvalidator::class, CloudflareInvalidator::class];
      public const ALL_INVALIDATORS = [
          ObjectCacheInvalidator::class,
          WPRocketInvalidator::class,
          CloudFrontInvalidator::class,
          CloudflareInvalidator::class,
      ];

      Adding a new CDN invalidator to CDN_INVALIDATORS without adding it to ALL_INVALIDATORS
      would mean it gets passed to queue->write() but never registered in the slugMap
      (via registerInvalidators on line 53), causing resolveInvalidator() to return null
      and the item to be silently dropped during cron processing. The inverse (adding to
      ALL_INVALIDATORS but forgetting CDN_INVALIDATORS) is less dangerous but still a
      maintenance trap. There is no compile-time or runtime assertion that
      CDN_INVALIDATORS ⊆ ALL_INVALIDATORS.
    severity: medium
    confidence: high

  - branch: "di-container"
    location: "CacheInvalidation.php:52-55,96"
    what: "Redundant container lookups for the same classes across init(), delegatedRoutes(), and standaloneRoutes()"
    evidence: |
      init():             array_map(fn($c) => $this->container->get($c), self::ALL_INVALIDATORS)  // resolves all 4
      init():             $this->container->get(WPRocketInvalidator::class)                        // resolves again
      delegatedRoutes():  $this->container->get(WPRocketInvalidator::class)                        // resolves again
      delegatedRoutes():  $this->cdnInvalidators()                                                 // resolves CF+Cloudflare again
      init():             $this->container->get(CloudflareInvalidator::class)                      // resolves again

      In delegated mode, WPRocketInvalidator is resolved 3 times and each CDN invalidator
      at least 3 times during init(). Since PHP-DI returns singletons this is functionally
      correct, but the pattern of calling container->get() repeatedly for classes already
      resolved on line 52 obscures the fact that these are all the same instances. The init()
      method could resolve all four once, store them, and reuse them — which would also make
      the singleton assumption explicit rather than implicit.
    severity: low
    confidence: high

  - branch: "di-container"
    location: "CacheInvalidation.php:48"
    what: "Concrete DI\\Container type-hint rather than Psr\\Container\\ContainerInterface"
    evidence: |
      use DI\Container;
      public function __construct(private CacheQueue $queue, private Container $container) {}

      The constructor type-hints the concrete DI\Container class rather than the PSR-11
      ContainerInterface. BackgroundProcessing.php does the same, so this is consistent
      within the project. However, it couples the module to PHP-DI specifically. Since
      the only method used is get(), ContainerInterface would suffice and is the more
      conventional choice in PHP projects using PSR-11.
    severity: low
    confidence: medium
```

**Summary of findings:**

| # | Severity | Finding |
|---|----------|---------|
| 1 | low | `cdnInvalidators()` has inconsistent evaluation timing — eager in `delegatedRoutes()`, lazy in the `after_rocket_clean_domain` closure. Safe due to singletons but could mislead readers. |
| 2 | **medium** | `CDN_INVALIDATORS` ⊆ `ALL_INVALIDATORS` is not enforced. A new CDN invalidator added to one list but not the other would cause silent failures during queue processing (slug not found in slugMap). |
| 3 | low | Same invalidator classes resolved from the container 3+ times during `init()`. Since line 52 already resolves all four, the subsequent calls are redundant lookups that obscure the singleton semantics. |
| 4 | low | Concrete `DI\Container` type-hint instead of `Psr\Container\ContainerInterface`. Consistent with existing project pattern (`BackgroundProcessing`) but couples to PHP-DI specifically. |