# Problem Assessment: Post Type Visibility

## Summary

`getVisibleSinglePostTypes()` and `getVisibleArchivePostTypes()` in `src/Utils/WordPress.php:146-176` use `get_post_types(['public' => true])` as their starting filter. This exact-match query misses post types registered with `public => false, publicly_queryable => true` — a valid configuration that WordPress core's `is_post_type_viewable()` considers viewable. The taxonomy enumeration in `ContentTargeting::getArchiveChoices()` has an identical blind spot with `get_taxonomies(['public' => true])`.

## How `is_post_type_viewable()` Works

WordPress core (`wp-includes/post.php:2427`) defines viewability as:

```php
$is_viewable = $post_type->publicly_queryable || ( $post_type->_builtin && $post_type->public );
```

For **custom types**, only `publicly_queryable` matters. For **built-in types**, there is a special-case fallback: `_builtin && public`. This is how `page` passes — it has `publicly_queryable => false` (explicitly set at `post.php:65`) but passes via the built-in fallback.

Switching the primary filter to `is_post_type_viewable()` produces the same three built-in types that `get_post_types(['public' => true])` returns — `post`, `page`, `attachment` — while also capturing custom types with `public => false, publicly_queryable => true`. No unwanted built-in types (`revision`, `nav_menu_item`, `wp_block`, `wp_template`, etc.) pass, as they all have `public => false`.

## `getVisibleArchivePostTypes()` — Line 146

**Current flow:** `get_post_types(['public' => true])` → `is_post_type_viewable()` → `get_post_type_archive_link()`.

The `is_post_type_viewable()` check is redundant: all `public => true` types already pass it. The types it would catch (`public => false, publicly_queryable => true`) are excluded by the upstream `get_post_types()` query before they reach it.

**Fix:** Replace `get_post_types(['public' => true])` with `get_post_types([], 'names')` (all registered types) and let `is_post_type_viewable()` serve as the actual gate. The `get_post_type_archive_link() !== false` check remains essential — it filters out viewable types without archives (e.g., `page`, which has no `has_archive` and no special-case link). The `attachment` exclusion also remains.

**`get_post_type_archive_link()` behavior confirmed safe:**
- `post` always returns a link via a special case (`link-template.php:1312-1320`), bypassing `has_archive`
- All other types return `false` when `has_archive` is falsy (`link-template.php:1326`)
- Types with `has_archive => true` but `rewrite => false` get a query-string fallback (`?post_type=X`), so they still produce valid archive links

## `getVisibleSinglePostTypes()` — Line 158

**Current flow:** Queries built-in types (`public => true, _builtin => true`) and custom types (`public => true, publicly_queryable => true, _builtin => false`) separately. Custom types are then filtered by `!empty($post_type->rewrite)`. The merged set is filtered by `wp_count_posts()`.

Two secondary filters require decisions:

### Rewrite Filter (line 163)

`!empty($post_type->rewrite)` excludes custom types registered with `rewrite => false`. Since `rewrite` defaults to `true` (expanded to an array during registration at `class-wp-post-type.php:641-664`), this only fires when a developer explicitly sets `rewrite => false`. Such types are still accessible via query-string URLs (`?post_type=X&p=123`).

This filter is **not a correctness concern** — it's a UX judgment. Content targeting asks "what content should this apply to," not "what has pretty URLs." Removing it has minimal blast radius since the `publicly_queryable => true, rewrite => false` combination is rare and intentional. Recommendation: remove it to align with the principle that all publicly queryable content should be targetable.

### Has-Posts Filter (lines 170-175)

Excludes types with zero posts in any of `publish`, `future`, `draft`, `pending`, `private`. Since drafts and pending posts count, only truly empty types are hidden.

This is a **UX trade-off**, not a bug:
- **Keep:** Targeting a type with zero content has no effect. Showing it creates a "works but does nothing" state.
- **Remove:** Admins registering a new CPT can't set up targeting rules until they create content, with no indication why the type is missing from the picker.

Both positions are defensible. This is a product decision, not a technical one.

## Taxonomy Enumeration — Same Structural Issue

`ContentTargeting::getArchiveChoices()` at line 77:

```php
foreach (get_taxonomies(['public' => true], 'objects') as $tax) {
    if (is_taxonomy_viewable($tax)) {
```

This has the same blind spot. `get_taxonomies(['public' => true])` misses taxonomies with `public => false, publicly_queryable => true`. The `is_taxonomy_viewable()` check is redundant for the same reason — all `public => true` taxonomies already pass it. The fix pattern is identical: start with all taxonomies and filter through `is_taxonomy_viewable()`.

Unlike post types, there is no `getVisibleTaxonomies()` utility — the enumeration is inlined in `getArchiveChoices()`. It can be fixed in place or extracted to a utility for symmetry.

## Caller Safety

Both utilities have exactly **two callers**, both in `ContentTargeting.php`:
- `filterPagePostTypes()` (line 20) — populates an ACF relationship field's `post_type` filter
- `loadArchiveChoices()` (line 15 via line 26) — populates an ACF select field's choices

Widening the return set adds more options to admin UI pickers. No caller assumes a fixed set, does array indexing, or compares against hardcoded lists. The runtime matching logic in `matchesCurrentRequest()` operates on stored post IDs (for pages) and namespaced string keys like `post_type_archive:event` (for archives) — neither is affected by wider type sets.

The only consumer of the ContentTargeting field group is the **CustomTags module** (via ACF clone at `modules/CustomTags/acf-json/group_69bc17ce5c1b8.json:70`).

## Test Coverage

No existing tests exercise `getVisibleSinglePostTypes()`, `getVisibleArchivePostTypes()`, `filterPagePostTypes()`, or `loadArchiveChoices()`. All 13 tests in `ContentTargetingTest.php` cover `matchesCurrentRequest()` only. The `setPostTypeArchive()` test helper exists but is never called — post type archive matching has zero test coverage.

**Consequence:** No tests will break from this change, but new tests should be added.

## Decisions

1. **Has-posts filter:** Add `bool $include_empty = false` parameter to `getVisibleSinglePostTypes()`. Default preserves current behavior (hide empty types). ContentTargeting passes `true` so admins can configure targeting before content exists.
2. **Taxonomy fix scope:** Fix in the same change — same structural issue, same area of code.

## Recommended Focus

1. Replace `get_post_types(['public' => true])` with `is_post_type_viewable()`-based filtering in both utilities
2. Remove the rewrite filter from `getVisibleSinglePostTypes()`
3. Add `bool $include_empty = false` parameter; ContentTargeting passes `true`
4. Fix the taxonomy enumeration blind spot in `getArchiveChoices()`
5. Add test coverage for the utility functions and post type archive matching
