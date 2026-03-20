# Milestones: Content Targeting

## ContentTargeting Module

- [X] **M1: ContentTargeting service module and ACF clone source** — Module class with `HOOK_SUFFIX`, registered in `sitchco.config.php`. Unassigned ACF field group (`group_content_targeting`) in `acf-json/` serves as clone source — not assigned to any post type or options page directly. Fields: `mode` (button_group, named keys `exclude`/`include`), `pages` (relationship, `return_format: id`), `archives` (select, multiple). `acf/load_field` hooks filter the relationship field to `getVisibleSinglePostTypes()` and dynamically populate archive choices. Consumers clone this group into their own field groups via ACF clone field. `🤝 Collaborative: consumers add clone field in ACF admin UI.`

- [X] **M2: Single-page runtime matching** — `matchesCurrentRequest($config)` resolves single-page targeting. Gated behind `is_singular()` to avoid term/user ID collisions on archive views. Include mode with selected pages shows only on those pages. Exclude mode hides on selected pages. Empty selection (either mode) shows everywhere.

- [X] **M3: Archive utility and runtime matching** — `getVisibleArchivePostTypes()` utility in `WordPress.php` returns post types with archive URLs. Archive choices dynamically populated with composite keys: `posts_index`, `post_type_archive:{type}`, `taxonomy_archive:{taxonomy}`, `author_archive`, `search_results`. Resolver tests stored archive targets against WordPress conditionals (`is_home()`, `is_post_type_archive()`, `is_category()`, `is_tag()`, `is_tax()`, `is_author()`, `is_search()`). Combined with single-page matching — include/exclude mode applies to the union of both. `is_home()` page ID overlap handled correctly (Posts page only reachable via `posts_index`, not as a singular page match).

## CustomTags Migration

- [X] **M4: CustomTags adopts ContentTargeting** — CustomTags declares `ContentTargeting::class` in `DEPENDENCIES`, receives it via constructor injection. Inline `script_assignment` group field replaced with ACF clone field referencing `group_content_targeting`. Old `shouldRenderTag()` method removed; rendering delegates to `contentTargeting->matchesCurrentRequest($config)`. Existing targeting behavior preserved with named mode keys and filtered post types.

## Deferred

- Per-term archive targeting (e.g., only `/category/news/` rather than all category archives)
- Date archive targeting
- Visual rule summary in admin list table columns
