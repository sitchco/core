# Problem Brief: Post Type Visibility Utility Fix

## Summary

The `getVisibleSinglePostTypes()` and `getVisibleArchivePostTypes()` utilities in `src/Utils/WordPress.php` use `get_post_types(['public' => true])` as their starting filter. This does exact property matching and misses post types registered with `public => false, publicly_queryable => true` — a valid configuration that WordPress's own `is_post_type_viewable()` considers viewable. The ContentTargeting module and any future consumer (e.g., Yoast sitemap filtering) that depends on these utilities inherits this blind spot.

## Goal

Both utilities should use `is_post_type_viewable()` as the primary visibility check, aligning with WordPress core's definition of "viewable." Secondary filters (rewrite requirement, has-posts check) should be evaluated for correctness and necessity.

## Success Criteria

- Post types with `public => false, publicly_queryable => true` are included by both utilities
- No unwanted built-in types (e.g., `attachment`, `revision`, `nav_menu_item`) leak through
- No existing caller behavior breaks from the wider net
- The `is_post_type_viewable()` call in `getVisibleArchivePostTypes()` is no longer redundant

## Relevant Paths

- `src/Utils/WordPress.php:146-176` — both utility methods
- `modules/ContentTargeting/ContentTargeting.php` — primary consumer (lines 20, 73)
- `tests/Modules/ContentTargeting/ContentTargetingTest.php` — existing test coverage
- `research/wp-post-type-visibility/final.md` — research findings

## Utility: getVisibleSinglePostTypes() (lines 158-176)

Current implementation queries `get_post_types(['public' => true])` for both built-in and custom types. Two additional filters need evaluation:

1. **Rewrite filter** (`!empty($post_type->rewrite)`) — The research establishes that `rewrite` is not required for front-end queryability. WordPress generates query-string URLs (`?post_type=X`) when rewrites are off. This filter may be incorrectly excluding valid viewable types.

2. **Has-posts filter** (`wp_count_posts()`) — Currently excludes types with zero published content. This makes sense for some contexts (why show an empty type in a picker?) but could be wrong for others. Whether this stays depends on caller expectations.

## Utility: getVisibleArchivePostTypes() (lines 146-156)

Current implementation queries `get_post_types(['public' => true])` then applies `is_post_type_viewable()` as a secondary filter. The research identifies this as redundant — all `public => true` types already pass `is_post_type_viewable()`. The types that `is_post_type_viewable()` would catch (`public => false, publicly_queryable => true`) never make it past the initial query. Flipping the order — starting with all types and filtering through `is_post_type_viewable()` — fixes this.

## Caller Impact

Both utilities are called from the ContentTargeting module to populate ACF field choices. Widening the returned set means more options appear in the admin UI pickers. This is the desired behavior, but needs verification that no caller assumes a narrower set.
