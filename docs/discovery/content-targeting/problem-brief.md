# Problem Brief: Reusable Content Targeting

## Summary

Multiple modules across the platform need the ability to target content to specific pages — both individual posts/pages (single views) and archive views. This pattern currently exists in at least two places (RLF Messages plugin, sitchco-core CustomTags module) with duplicated logic and no shared abstraction. The archive-targeting dimension hasn't been built yet.

## Goal

Design and build a reusable content-targeting primitive in sitchco-core that any module can use to:

1. **Target single pages** — assign content to specific posts/pages, with the post type selector filtered to only show types with public single views (excluding media, data-only CPTs)
2. **Target archive views** — assign content to post type archives, taxonomy archives, author pages, and similar listing views
3. **Provide both the admin UI (ACF fields) and the runtime resolution** ("does the current page match this targeting config?")

## Success Criteria

- A single, well-documented abstraction that modules can adopt with minimal code
- CustomTags module uses the new abstraction instead of its current hand-rolled assignment fields
- Post type dropdown is filtered to relevant types (single-view types for page targeting, archive-enabled types for archive targeting)
- Runtime matching logic is centralized — modules don't each implement their own "am I on a matching page?" check
- The pattern is general-purpose enough for unknown future consumers

## Relevant Paths

### sitchco-core (primary target)
- `modules/CustomTags/acf-json/group_69bc17ce5c1b8.json` — Current assignment fields (unfiltered relationship field)
- `modules/CustomTags/` — Module that will be first adopter
- `src/Utils/WordPress.php:141-159` — `getVisibleSinglePostTypes()` already exists here

### RLF Producers plugin (prior art)
- `wp-content/plugins/producers/src/MessageProducer.php:30-36` — ACF filter that wires up post type filtering
- `wp-content/plugins/producers/config/message/acf/group_5c07fe0514509.json:177-230` — Assignment field group (button_group + relationship)
- `wp-content/plugins/backstage/src/Util.php:853-877` — Original `getVisibleSinglePostTypes()` implementation

## Existing Pattern (Single-Page Targeting)

Both implementations share the same structure:
- **Button group**: "Exclude From..." / "Only Include On..." (include/exclude toggle)
- **Relationship field**: Multi-select post picker with post type filter tab
- **Post type filtering**: `getVisibleSinglePostTypes()` restricts the dropdown via `acf/load_field` hook (only wired in RLF Messages, not yet in CustomTags)

### `getVisibleSinglePostTypes()` logic
- Built-in types: `public => true`, `_builtin => true`, minus `attachment`
- Custom types: `public => true`, `publicly_queryable => true`, has `rewrite` rules
- Additional filter: only includes types with at least one publishable post

## Archive Targeting (New Capability)

Not yet built anywhere on the platform. Needs to cover:
- **Post type archives** — e.g., `/blog/`, `/events/`
- **Taxonomy archives** — e.g., `/category/news/`, `/tag/featured/`
- **Author archives** — `/author/john/`
- **Other listing views** — date archives, search results, front page (if it's a blog listing)

Needs a companion utility (e.g., `getVisibleArchivePostTypes()`) that filters to post types where `has_archive` is true or that otherwise have publicly accessible archive URLs.

## Abstraction Approaches to Evaluate

### 1. Reusable ACF Field Group
PHP-registered field group containing the full assignment UI (toggle + single picker + archive picker). Modules clone or reference it into their own field groups using ACF's clone field or `acf_add_local_field_group`.

### 2. Custom ACF Field Type
A new composite field type (e.g., `content_targeting`) registered with ACF's field type API. Modules drop a single field into any group and get the full targeting UI.

### 3. PHP Module with ACF Fields
A sitchco-core module that provides:
- ACF field registration (programmatic, not JSON)
- Post-type filtering hooks
- Runtime resolution API (`ContentTargeting::matches($post_id, $config)`)
- Modules integrate via trait, interface, or service injection

## Open Questions

- Should archive targeting get granular (specific taxonomy terms) or stay at post-type level?
- How does the runtime resolution work for archives? (Archives don't have a single post ID — matching logic differs)
- What's the right data structure for storing combined single + archive targeting config?
- Are there ACF precedents for composite/reusable field patterns in the existing codebase?
