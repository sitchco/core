# Phase 1: Block Foundation & Editor - Context

**Gathered:** 2026-03-09
**Status:** Ready for planning

<domain>
## Phase Boundary

Deliver the complete editor authoring experience for `sitchco/video`: native block registration via `block.json` with React `edit` component, URL input with provider detection, oEmbed preview fetching, display mode controls, play icon configuration, and correct InnerBlocks persistence. The block saves and loads correctly. No frontend playback behavior (that's Phase 2).

Also includes the UIModal content-based modal prerequisite — though codebase scout reveals this is already implemented (commit 4fc37f7).

</domain>

<decisions>
## Implementation Decisions

### UIModal Prerequisite (PRE-01, PRE-02)

- **Already implemented.** Commit `4fc37f7` ("Decouple ModalData from Timber Post to support content-based modals") refactored ModalData so its constructor accepts raw `(string $id, string $heading, string $content, ModalType $type)`. The `fromPost()` method is now a convenience factory.
- The spec's proposed `loadModalContent()` method on UIModal is unnecessary — code can construct `ModalData` directly and pass to `UIModal::loadModal(ModalData)`. The capability exists.
- `ModalType::VIDEO` already exists in the enum.
- **Action:** Verify the refactor works for non-post modals. If `loadModalContent()` convenience method would improve DX, add it. Otherwise, mark PRE-01/PRE-02 as satisfied by existing code.

### Block Structure

- **First native Gutenberg block in sitchco-core.** All existing blocks (icon, modal) use ACF mode. This block uses a React `edit` component registered via `block.json`.
- Module directory: `modules/VideoBlock/` with subdirectories `blocks/video/`, `assets/scripts/`, `assets/styles/`
- `block.json` registers `sitchco/video` — auto-discovered by `BlockManifestRegistry`
- **Dynamic rendering:** PHP `render_callback` for server-side output. `save` function returns `<InnerBlocks.Content/>` (critical for persistence — InnerBlocks must not be lost).
- JSX compilation is required for the React edit component. The existing Vite build (`@sitchco/cli` 2.1.9) uses `@kucrut/vite-for-wp` which externalizes `@wordpress/*` packages. **JSX support needs validation** — the codebase currently has zero JSX files.

### Block Attributes (from spec axioms)

- `url` (string) — Video URL (YouTube or Vimeo)
- `provider` (string) — Auto-detected from URL: `youtube`, `vimeo`, or empty
- `videoTitle` (string) — Auto-populated from oEmbed, editable
- `displayMode` (string) — `inline` (default), `modal`, `modal-only`
- `modalId` (string) — Slugified from videoTitle, editable. Only used in modal/modal-only modes.
- `playIconStyle` (string) — YouTube: `dark`/`light`/`red`. Non-YouTube: `dark`/`light`. Default: `dark`.
- `playIconX` (number) — X position percentage. Default: 50.
- `playIconY` (number) — Y position percentage. Default: 50.
- `clickBehavior` (string) — `poster` (default, entire poster) or `icon` (play icon only)
- **Attribute schema must be frozen before any production content** — deprecation debt compounds (from pitfalls research).

### Editor Experience

- **Inspector panel** is the primary UI surface for configuration. URL input, display mode selector, play icon controls, click behavior toggle all live in the inspector.
- **oEmbed preview:** Editor fetches via WordPress proxy endpoint `/wp-json/oembed/1.0/proxy` using `@wordpress/api-fetch`. Response provides `thumbnail_url` and `title`. No direct client-side requests to YouTube/Vimeo.
- **Auto-population rules:** When URL changes, auto-populate `videoTitle` and `modalId` from oEmbed response — but only if the user hasn't manually edited them. Track "user-edited" state per field.
- **Conditional inspector UI:**
  - Inline mode: No modal fields shown
  - Modal/Modal Only: Show title field and modal ID field
  - Modal Only: Collapse/hide InnerBlocks editing area
- **Block with no URL:** Renders InnerBlocks without play icon or play behavior (NOOP-01). The editor should show a placeholder prompting for URL entry.

### Play Icon in Editor Preview

- Provider detection from URL determines which icon set is available
- YouTube URLs → YouTube-branded play button (dark/light/red variants per YouTube API ToS)
- Non-YouTube URLs → Generic play icon (dark/light)
- Play icon position preview updates in editor as X/Y sliders change
- Play icon overlay renders on top of the oEmbed thumbnail preview

### InnerBlocks

- Block inserts with no InnerBlocks (empty). Author optionally adds blocks later.
- No `allowedBlocks` restriction — any block type can be used as poster content
- In the editor, InnerBlocks area is always available (except in Modal Only mode where it's hidden)
- The edit component doesn't need to know whether InnerBlocks are present for Phase 1 — that logic is frontend-only (Phase 2)

### Claude's Discretion

- Editor component internal state management approach
- Exact inspector panel layout and control ordering
- Loading/error states for oEmbed preview fetching
- How to handle invalid URLs (non-video URLs, malformed URLs)
- Play icon SVG implementation details (inline vs asset)
- How to detect "user-edited" state for auto-population fields

</decisions>

<specifics>
## Specific Ideas

- The block is described as "zero-config" — paste a URL and everything auto-populates. The editor experience should reflect this by making the URL field prominent and showing immediate visual feedback when a URL is entered.
- YouTube branded play icons are a **legal requirement** (YouTube API ToS), not a design preference. The editor must enforce this — no option to use a generic icon on YouTube videos.
- Modal ID should be human-readable and shareable (slugified video title), making deep links predictable.
- The spec explicitly says the editor must NOT load an iframe or embed preview — only the oEmbed thumbnail.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets

- **ModalData** (`modules/UIModal/ModalData.php`): Already accepts raw strings. Constructor: `(string $id, string $heading, string $content, ModalType $type)`. Factory: `fromPost()`. Video block can create ModalData directly.
- **ModalType::VIDEO** (`modules/UIModal/ModalType.php`): Already exists in the enum. Optimized for video display.
- **UIModal::loadModal()** (`modules/UIModal/UIModal.php`): Queues ModalData for `wp_footer` output. Video block calls this in render callback.
- **ModuleAssets** (`src/Framework/ModuleAssets.php`): Handles `editorScript`/`viewScript`/`style` resolution from block.json via Vite manifest. Processes `file:` path references.
- **BlockManifestRegistry** (`src/Framework/BlockManifestRegistry.php`): Auto-discovers blocks in `modules/*/blocks/*/block.json`. New block auto-registered.
- **UIFramework hooks** (`modules/UIFramework/assets/scripts/hooks.js`): `sitchco.hooks` provides `addAction`/`doAction`/`addFilter`/`applyFilters`. Available via `sitchco/hooks` script dependency.
- **Editor lifecycle** (`modules/UIFramework/assets/scripts/editor-ui-main.js`): `sitchco.editorInit()` and `sitchco.editorReady()` for editor setup.

### Established Patterns

- **Module pattern:** Extend `Module`, declare `DEPENDENCIES`, `FEATURES`, `HOOK_SUFFIX`. Register in `sitchco.config.php`.
- **Block pattern (ACF):** `block.json` + `block.php` + `block.twig`. All existing blocks use ACF mode with `TimberModule::blockRenderCallback`. The video block breaks this pattern — native block with React edit.
- **Asset registration:** `$this->registerAssets(fn(ModuleAssets $assets) => ...)` in module `init()`. Block-specific assets via block.json fields.
- **CSS naming:** BEM with `sitchco-` prefix. CSS custom properties for theming.
- **JS convention:** Vanilla ES modules, no TypeScript, no JSX. The video block's React edit component will be the first JSX in the codebase.

### Integration Points

- `sitchco.config.php` — Register `VideoBlock::class` in modules array
- `sitchco.blocks.json` — Auto-regenerated to include `sitchco/video`
- `block.json` — Entry point for block registration. Must declare `editorScript` for React edit component.
- `ModuleAssets::blockTypeMetadata()` — Resolves `file:` paths in block.json to Vite-built URLs
- The native block will NOT use `TimberModule::blockRenderCallback` — it needs its own `render_callback` or `render` field in block.json pointing to a PHP file

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. All implementation decisions are well-specified by the scenario document.

</deferred>

---

*Phase: 01-block-foundation-editor*
*Context gathered: 2026-03-09*
