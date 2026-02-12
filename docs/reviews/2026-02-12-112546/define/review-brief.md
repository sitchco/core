# Review Brief

## Change Summary

This PR implements three refactoring suggestions from a prior code review of the CacheInvalidation module. It (1) replaces the `CacheCondition` enum with a template method on an abstract `Invalidator` base class so availability logic lives with each invalidator, (2) switches from manual `new` instantiation to DI container resolution for all invalidators, and (3) introduces a `PendingInvalidation` readonly value object to replace the associative arrays previously used for queue items. The net effect is better cohesion, type safety, and testability with no behavioral changes.

## Design Document

A design document with the original review feedback is at:
`/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/docs/reviews/2026-02-12-112546/define/design.md`

## Branching Strategy

Split by review suggestion — each branch maps to one of the three design doc suggestions. Tests are reviewed within the branch they relate to. The test file (`CacheInvalidationTest.php`) has changes from all three branches; each branch prompt includes the full diff with a focus directive.

## Branches

### invalidator-abstraction
- **Scope:** `Invalidator.php` (interface → abstract class), `CacheCondition.php` (deleted), all four concrete invalidators (`ObjectCacheInvalidator`, `WPRocketInvalidator`, `CloudFrontInvalidator`, `CloudflareInvalidator`), `SPEC.md` documentation updates, and filter name changes in `CacheInvalidationTest.php`
- **Focus:** Does the template method pattern correctly preserve behavior? Is the filter key migration from condition enum values (`rocket_active`, `cloudflare_installed`, `cloudfront_installed`) to invalidator slugs (`wp_rocket`, `cloudflare`, `cloudfront`) complete and correct? Are all CacheCondition references eliminated?
- **Criteria:** Correctness, Consistency, Maintainability

### di-container
- **Scope:** `CacheInvalidation.php` (constructor, `init()`, route methods, `cdnInvalidators()`), class constant lists (`ALL_INVALIDATORS`, `CDN_INVALIDATORS`), and constructor signature changes in `CacheInvalidationTest.php`
- **Focus:** Are all manual `new Invalidator()` calls eliminated? Does container resolution follow project DI patterns? Are the constant lists used consistently? Could duplicate container lookups cause issues?
- **Criteria:** Correctness, Consistency, Maintainability

### queue-value-object
- **Scope:** `PendingInvalidation.php` (new readonly class), `CacheQueue.php` (slug map, write buffer, process loop, resolveInvalidator), `PendingInvalidationTest.php` (new), and `test_queue_option_stores_arrays_not_objects` in `CacheInvalidationTest.php`
- **Focus:** Is serialization/deserialization correct and complete? Does the readonly/immutable pattern hold? Are edge cases (malformed data, missing keys) handled? Is test coverage adequate for the value object?
- **Criteria:** Correctness, Testing, Maintainability
