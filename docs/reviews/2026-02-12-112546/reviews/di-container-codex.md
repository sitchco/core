findings:
  - branch: "di-container"
    location: "modules/CacheInvalidation/CacheInvalidation.php:52-120"
    what: "Invalidators are resolved multiple times via `$this->container->get()` instead of being resolved once and reused; if any invalidator is configured as non-shared, route-time instances and registered processing instances can diverge."
    evidence: |
      init():
        $invalidators = array_map(fn(string $class) => $this->container->get($class), self::ALL_INVALIDATORS);
        $rocketInvalidator = $this->container->get(WPRocketInvalidator::class);

      delegatedRoutes():
        $rocketAndCdns = [$this->container->get(WPRocketInvalidator::class), ...$this->cdnInvalidators()];

      cdnInvalidators():
        return array_map(fn(string $class) => $this->container->get($class), self::CDN_INVALIDATORS);
    severity: low
    confidence: high

  - branch: "di-container"
    location: "modules/CacheInvalidation/CacheInvalidation.php:37-67"
    what: "`CDN_INVALIDATORS` and `ALL_INVALIDATORS` duplicate CDN class names without a single source of truth, so list drift can cause invalidators to be queued from one list but not registered from the other."
    evidence: |
      Duplicated constants:
        CDN_INVALIDATORS = [CloudFrontInvalidator::class, CloudflareInvalidator::class]
        ALL_INVALIDATORS = [ObjectCacheInvalidator::class, WPRocketInvalidator::class, CloudFrontInvalidator::class, CloudflareInvalidator::class]

      Split usage:
        registerInvalidators(...) uses ALL_INVALIDATORS (line 52)
        delegated after-rocket hook queues $this->cdnInvalidators() (line 66), which maps CDN_INVALIDATORS (line 120)
    severity: low
    confidence: high