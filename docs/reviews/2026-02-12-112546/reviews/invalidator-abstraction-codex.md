```yaml
findings:
  - branch: "invalidator-abstraction"
    location: "modules/CacheInvalidation/Invalidator.php:21"
    what: "Condition filter keys were renamed from enum values to slugs without a compatibility layer, which breaks existing code hooked to legacy keys."
    evidence: |
      Old behavior (pre-refactor `CacheCondition::check()`):
      `apply_filters('sitchco/cache/condition/' . $this->value, $result)`
      with values `rocket_active`, `cloudflare_installed`, `cloudfront_installed`.

      New behavior:
      `apply_filters('sitchco/cache/condition/' . $this->slug(), $this->checkAvailability())`
      and slugs are `wp_rocket`, `cloudflare`, `cloudfront`.
      No legacy hook invocation remains in runtime code.
    severity: high
    confidence: high

  - branch: "invalidator-abstraction"
    location: "modules/CacheInvalidation/Invalidator.php:7"
    what: "`Invalidator` changed from interface to abstract class, creating a source-level BC break for any external/custom class that previously used `implements Invalidator`."
    evidence: |
      Before: `interface Invalidator`.
      After: `abstract class Invalidator`.

      Existing extensions that declare `class X implements Invalidator` will no longer be valid because `Invalidator` is no longer an interface type.
    severity: medium
    confidence: high

  - branch: "invalidator-abstraction"
    location: "modules/CacheInvalidation/Invalidator.php:19"
    what: "The template method is not enforced because `isAvailable()` is overridable (`final` is missing), allowing subclasses to bypass `checkAvailability()` and the standard filter hook."
    evidence: |
      `isAvailable()` is declared as:
      `public function isAvailable(): bool`
      (not `final`), while SPEC states each invalidator's `isAvailable()` applies `sitchco/cache/condition/{slug}` uniformly.
    severity: low
    confidence: high

  - branch: "invalidator-abstraction"
    location: "modules/CacheInvalidation/ObjectCacheInvalidator.php:7-17"
    what: "Object cache availability semantics changed from unconditional `true` to filterable via `sitchco/cache/condition/object_cache`, which is a behavior change from prior implementation."
    evidence: |
      Previous object cache invalidator implemented:
      `public function isAvailable(): bool { return true; }`

      Now it returns `true` from `checkAvailability()`, but inherited `Invalidator::isAvailable()` wraps it in:
      `apply_filters('sitchco/cache/condition/' . $this->slug(), ...)`
      with slug `object_cache`.
    severity: low
    confidence: high

  - branch: "invalidator-abstraction"
    location: "modules/CacheInvalidation/SPEC.md:111"
    what: "Documentation introduces only the new hook names and does not describe migration/deprecation of the old hook keys, leaving integrators no explicit upgrade path."
    evidence: |
      SPEC documents new hooks:
      `sitchco/cache/condition/{slug}` with `object_cache`, `wp_rocket`, `cloudfront`, `cloudflare`.

      It does not note that previous keys (`rocket_active`, `cloudflare_installed`, `cloudfront_installed`) were removed.
    severity: low
    confidence: medium
```
