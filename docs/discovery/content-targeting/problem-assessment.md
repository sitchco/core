# Problem Assessment: Reusable Content Targeting

## Summary

Multiple sitchco-core modules need to control which pages their content appears on. The CustomTags module has a first-pass implementation — an include/exclude toggle with a post picker — but it has gaps (unfiltered post types, no archive support) and the logic is embedded directly in the module rather than being reusable. A shared content-targeting primitive in sitchco-core would let any module add page targeting with minimal code, while fixing the current issues and adding archive-view support.

## The Pattern

Content targeting is an include/exclude toggle paired with page selection: "show this only on these pages" or "show this everywhere except these pages." The old RLF platform had this same pattern in its Messages system, and the CustomTags module in sitchco-core reproduces it. The core mechanics are sound — what's needed is a proper abstraction that any module can use, with the gaps filled in.

## Current State in CustomTags

The `script_assignment` ACF group field (`group_69bc17ce5c1b8.json:52-114`) contains:

- **`type`** — a `button_group` with choices `["Exclude From...", "Only Include On..."]` (integer-indexed keys `0`/`1`)
- **`selection`** — a `relationship` field returning post IDs

Runtime matching (`CustomTags.php:70-79`) uses `get_queried_object_id()` to get the current page, then checks whether that ID is in the selection list, inverting based on the toggle.

### Issues to address

1. **Relationship field is unfiltered.** The field has `"post_type": ""` (`group_69bc17ce5c1b8.json:102`) — admins see every post type in the picker. A `getVisibleSinglePostTypes()` utility already exists at `src/Utils/WordPress.php:141-159` but was never wired to this field via `acf/load_field`.

2. **Integer-indexed toggle choices are fragile.** ACF stores the array index as a string (`"0"` or `"1"`). The code defensively casts to int (`CustomTags.php:47`), but reordering choices would silently invert behavior for all existing data. Named keys like `"exclude"` / `"include"` would be safer and self-documenting.

3. **Empty selection means "show everywhere."** If the toggle is set to "Only Include On..." but no pages are selected, the content still shows on all pages. This is a reasonable default but should be an explicit design choice in the abstraction.

4. **No archive support at all.** This is the biggest gap — covered in detail below.

5. **Logic is not reusable.** The matching logic is a private method on `CustomTags`. Any future module needing the same capability would duplicate the implementation.

## Archive Targeting: The Missing Dimension

The current post-ID-based model is structurally incapable of handling archive views. This is new capability that didn't exist on the old platform either.

### Why post IDs don't work for archives

`get_queried_object_id()` returns different things depending on the view:

| View type | `get_queried_object()` | `get_queried_object_id()` |
|---|---|---|
| Singular post/page | `WP_Post` | Post ID — works correctly |
| Post type archive (`/events/`) | `WP_Post_Type` | `0` — never matches any selection |
| Blog home (`is_home()`) | `WP_Post` (the Posts page) | Page ID if configured — accidental match possible |
| Category/tag/taxonomy archive | `WP_Term` | Term ID — collides with post ID namespace |
| Author archive | `WP_User` | User ID — collides with post ID namespace |
| Search results | `null` | `0` |

This means exclude-targeted content always renders on archives (the archive ID is never in the exclusion list), include-targeted content never renders on archives, and taxonomy/author archives could produce false matches when a term or user ID coincidentally equals an included post ID.

### Archive types relevant for targeting

| Archive type | WordPress detection | Notes |
|---|---|---|
| Post type archive | `is_post_type_archive($type)` | CPTs where `has_archive !== false` |
| Blog/posts index | `is_home()` | Special case — WP does not treat this as `is_post_type_archive('post')` |
| Category archive | `is_category()` | Built-in taxonomy; NOT covered by `is_tax()` |
| Tag archive | `is_tag()` | Built-in taxonomy; NOT covered by `is_tax()` |
| Custom taxonomy archive | `is_tax($taxonomy)` | Custom taxonomies only |
| Author archive | `is_author()` | `/author/*` |
| Search results | `is_search()` | No queried object |

Date archives are likely out of scope — no practical targeting use case identified. WordPress allows overlapping contexts (e.g., `is_home() && is_front_page()`), so the resolver should test all applicable conditions rather than assuming one exclusive type per request.

### New utility: `getVisibleArchivePostTypes()`

The criteria differ from the existing `getVisibleSinglePostTypes()`:

- `is_post_type_viewable($type)` — canonical WordPress check for front-end accessibility
- `get_post_type_archive_link($type->name) !== false` — definitive test for whether an archive URL exists (handles `has_archive` being `true`, a custom slug string, or the `post` type special case)
- `rewrite` rules are NOT required — WordPress generates query-string archive URLs when rewrites are off
- The "has posts" check can likely be dropped — archive URLs exist even with zero posts

A parallel taxonomy enumeration is also needed: `is_taxonomy_viewable($tax)` filters to publicly accessible taxonomies, including those with `rewrite: false`.

### Admin UI design

The targeting UI is an ACF group field with three sub-fields:

```
┌─ Content Targeting ──────────────────────────────────────────┐
│                                                              │
│  [ Exclude From... | Only Include On... ]    ← button_group  │
│                                                              │
│  ┌─ Pages ──────────────┐  ┌─ Archives ─────────────────┐   │
│  │                      │  │                             │   │
│  │  relationship field  │  │  select (multiple)          │   │
│  │  (filtered to visible│  │  (dynamically populated)    │   │
│  │   single post types) │  │                             │   │
│  │                      │  │  ☑ Posts Index               │   │
│  │  • About Us          │  │  ☑ Post Archive: Writers     │   │
│  │  • Contact           │  │  ☐ Taxonomy: Category        │   │
│  │                      │  │  ☐ Search Results            │   │
│  │                      │  │                             │   │
│  └──────────────────────┘  └─────────────────────────────┘   │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

**One toggle governs both fields.** Mixed modes (include pages but exclude archives) would always be a no-op — if you're including specific pages, excluding an archive is meaningless since you've already limited where content appears. The semantics are:

- **Include** + pages [A, B] + archives [blog, events] → show only on pages A and B, the blog index, and the events archive
- **Exclude** + pages [A, B] + archives [blog] → show everywhere except pages A and B and the blog index
- **Empty selections** (nothing picked in either field) → show everywhere, regardless of toggle

### Field specifications

**Mode toggle** — `button_group` with named keys:
- `exclude` → "Exclude From..."
- `include` → "Only Include On..."

Named keys eliminate the int-casting fragility of the current `0`/`1` approach.

**Pages** — `relationship` field, `return_format: "id"`, filtered via `acf/load_field` to `getVisibleSinglePostTypes()`. This is the same pattern the old platform used, now properly wired.

**Archives** — `select` field (multiple), dynamically populated via `acf/load_field` with composite key values:

| Label | Value |
|---|---|
| Posts Index | `posts_index` |
| Post Archive: Writers | `post_type_archive:writer` |
| Taxonomy: Category | `taxonomy_archive:category` |
| Taxonomy: Post Tag | `taxonomy_archive:post_tag` |
| Author Archives | `author_archive` |
| Search Results | `search_results` |

Options generated at admin time using new `getVisibleArchivePostTypes()` and taxonomy enumeration utilities.

### Data shape

```php
// What get_field('content_targeting', $post_id) returns:
[
    'mode'     => 'include',       // or 'exclude'
    'pages'    => [123, 456],      // post IDs
    'archives' => [                // composite key strings
        'posts_index',
        'post_type_archive:writer',
        'taxonomy_archive:category',
    ],
]
```

### Granularity

Start at taxonomy-level (e.g., "all category archives"), not per-term (e.g., "only `/category/news/`"). This matches established WordPress visibility plugins and covers practical targeting use cases. Per-term targeting can be a future extension if needed.

## Recommended Abstraction: PHP Service Module

A `ContentTargeting` service module that owns both the ACF field registration and the runtime matching logic, following the existing service-module pattern in the codebase.

### Why this approach

The `VideoModule` → `UIModal` pattern (`modules/Video/VideoModule.php:10-18`) is an exact structural match: a consuming module declares a dependency, receives the service via constructor injection, and calls a registration method in `init()`. This `DEPENDENCIES` + DI injection pattern is used by 10+ modules across the codebase.

The key advantages over other approaches considered (shared ACF clone field groups, custom ACF field types):

- **Per-consumer field isolation.** Programmatic registration generates unique field keys per consumer, so post-type filtering hooks are scoped correctly — no collision when multiple modules use the same targeting primitive.
- **Centralized runtime resolution.** The service owns the matching logic. Consumers call `matchesCurrentRequest($config)` instead of reimplementing include/exclude checks.
- **No new patterns.** `DEPENDENCIES` handles load order, the DI container handles injection.

### Consumer integration

```php
class CustomTags extends Module
{
    public const DEPENDENCIES = [ContentTargeting::class];

    public function __construct(
        protected CustomTagRepository $repository,
        protected ContentTargeting $contentTargeting
    ) {}

    public function init(): void
    {
        $this->contentTargeting->registerTargeting('custom_tag');
        // ...existing hooks
    }

    private function shouldRenderTag(array $tag): bool
    {
        return $this->contentTargeting->matchesCurrentRequest($tag['targeting_config']);
    }
}
```

### ACF style tradeoff

The codebase is ACF-JSON-first in production. Programmatic field registration via `acf_add_local_field_group()` is currently only used in tests. A `ContentTargeting` module registering fields programmatically would be a new production pattern — but a shared service that registers fields on behalf of multiple consumers can't use static JSON, since the fields need unique keys per consumer. This is a justified exception to the JSON convention.

### Runtime resolver design

The resolver derives request context from WordPress conditionals, then matches against stored config:

- **Request context detection** happens once per request. `TagManager.php:109-115` already demonstrates the `match(true)` pattern for classifying `get_queried_object()` by type. `Cleanup.php:366-438` provides the conditional dispatch pattern for archive detection.
- **Single matching** uses `in_array($postId, $singles, true)` with `get_queried_object_id()` — but only when `is_singular()` is true, avoiding the term/user ID collision on archive views.
- **Archive matching** iterates stored archive targets and tests each against active WordPress conditionals.
- **Combined result** applies the include/exclude mode to the union of single and archive matches.

## Resolved: The `is_home()` Page ID Overlap

When a static Posts page is configured (`page_for_posts`), `get_queried_object_id()` returns that page's post ID on `is_home()`. This initially appears to create an overlap — the page ID could match in the singles relationship field even though the user is viewing a listing.

WordPress already handles this. When the queried page matches `page_for_posts`, WordPress explicitly sets `is_page = false` and `is_posts_page = true` (`class-wp-query.php:1090-1094`). Since `is_singular` is derived from `is_single || is_page || is_attachment` (line 1135), the Posts page is NOT `is_singular()`. The resolver simply gates single-page matching behind `is_singular()` and the overlap disappears — the Posts page is only reachable via `posts_index` in the archives field.

**No migration is needed for existing CustomTags data.** CustomTags is greenfield and unused — the content targeting abstraction should land before the CustomTags work is merged, so CustomTags can adopt it directly.

## Recommended Focus

1. **Build the `ContentTargeting` service module** following the `UIModal` pattern — `DEPENDENCIES`, DI injection, `registerTargeting($postType)` API.
2. **Start with single-page targeting** to validate the abstraction with CustomTags as first adopter. Fix the unfiltered relationship field and switch to named choice keys.
3. **Add archive targeting** as a second phase — `getVisibleArchivePostTypes()`, archive selection UI, branching runtime resolver.
4. **Wire `getVisibleSinglePostTypes()`** into the field registration, closing the gap CustomTags has today.
