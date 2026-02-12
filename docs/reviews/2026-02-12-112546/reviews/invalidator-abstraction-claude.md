I now have a thorough understanding of the codebase. Let me compile my findings.

```yaml
findings:
  - branch: "invalidator-abstraction"
    location: "modules/CacheInvalidation/TESTING.md:27-28"
    what: "TESTING.md still references CacheCondition.php and describes Invalidator.php as an interface"
    evidence: |
      Line 27: `| Invalidator.php | Interface — `isAvailable()`, `priority()`, `delay()`, `flush()` |`
      Line 28: `| CacheCondition.php | Enum — condition checks for each backing service |`
      
      CacheCondition.php was deleted and Invalidator.php is now an abstract class, not an interface.
      The Key Code Locations table is stale.
    severity: low
    confidence: high

  - branch: "invalidator-abstraction"
    location: "tests/Modules/CacheInvalidationTest.php:43-45"
    what: "tearDown does not clean up the `sitchco/cache/condition/object_cache` filter"
    evidence: |
      tearDown cleans up three filters:
        remove_all_filters('sitchco/cache/condition/wp_rocket');
        remove_all_filters('sitchco/cache/condition/cloudflare');
        remove_all_filters('sitchco/cache/condition/cloudfront');
      
      But `object_cache` is never cleaned up. Currently no test adds a filter for
      `sitchco/cache/condition/object_cache`, so this is latent — but the SPEC
      explicitly says "Object cache defaults to true (always available) but is
      filterable like the rest." A future test filtering object_cache would leak
      across tests.
    severity: low
    confidence: high
```