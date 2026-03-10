# Project Research Summary

**Project:** sitchco/video -- Native Gutenberg Video Block
**Domain:** Privacy-first WordPress Gutenberg video embedding with provider SDK integration
**Researched:** 2026-03-09
**Confidence:** HIGH

## Executive Summary

The sitchco/video block is a native Gutenberg block that delivers privacy-respecting, click-to-play video embedding for YouTube and Vimeo. The established industry pattern -- pioneered by Paul Irish's lite-youtube-embed and endorsed by the WordPress Performance Team -- is the facade pattern: show a static poster image with a play button, load the provider SDK only after the user clicks. This block extends that pattern with three display modes (inline, modal, modal-only), InnerBlocks poster composition, and UIModal integration. The existing sitchco-core infrastructure (Vite build pipeline, `sitchco.hooks` JS system, UIModal, ModuleAssets, BlockManifestRegistry) handles nearly all foundational concerns. No new npm dependencies are needed for production; provider SDKs load from CDN on demand.

The recommended approach is to build this as the first native Gutenberg block in sitchco-core, establishing the pattern for future blocks that need rich editor interactions beyond ACF block mode. The block uses a React `edit` component (JSX) with WordPress externalized packages, a PHP `render.php` for server-side output, and vanilla JS `view.js` for frontend playback. The architecture follows composition over inheritance: video composes with UIModal for modal modes rather than reimplementing dialog behavior. The provider SDK adapter pattern (strategy pattern) normalizes YouTube and Vimeo differences behind a common interface for playback control, mutual exclusion, and analytics.

The primary risks are: (1) the dynamic block + InnerBlocks save function conflict, where returning `null` from `save` silently discards InnerBlocks content -- this must be solved in the first block skeleton; (2) YouTube IFrame API's global singleton callback race condition when multiple video blocks exist on a page; (3) block deprecation debt from attribute schema changes during early development. All three are well-documented with clear prevention strategies. The UIModal content-based modal refactor (commit `4fc37f7`) has already been completed, removing what was previously the largest prerequisite concern.

## Key Findings

### Recommended Stack

The existing sitchco-core platform provides nearly everything needed. The Vite build pipeline (`@sitchco/module-builder` + `@kucrut/vite-for-wp`) already handles JSX compilation and externalizes all `@wordpress/*` packages to WordPress globals. No new build tooling is required. Provider SDKs (YouTube IFrame API, Vimeo Player SDK) are loaded from CDN at runtime after user interaction, not bundled.

**Core technologies:**
- **Existing Vite pipeline**: Handles JSX, CSS, WP externals -- zero configuration changes needed
- **WordPress Block API** (`@wordpress/blocks`, `@wordpress/block-editor`, `@wordpress/components`): Externalized to WP globals, imported directly in JSX
- **YouTube IFrame API (CDN)**: `YT.Player` for programmatic playback -- no npm package exists
- **Vimeo Player SDK (CDN)**: `Vimeo.Player` for programmatic playback -- CDN preferred over npm to match YouTube pattern and avoid bundling 30KB+
- **WordPress oEmbed REST proxy** (`/oembed/1.0/proxy`): Built-in, cached, handles auth -- no custom endpoint needed for thumbnail fetching
- **UIModal system**: Existing `<dialog>`-based modals with focus trapping, scroll lock, hash sync, ARIA

**Critical version requirement:** WordPress 6.3+ (for `block.json` apiVersion 3, `useInnerBlocksProps`, `viewScript` auto-enqueue).

**What NOT to install:** `@wordpress/*` as npm deps (creates version drift), `react-player`/`video.js`/`plyr` (fight custom UI), `lite-youtube-embed` (insufficient control), WordPress Interactivity API (adds complexity without benefit for vanilla JS frontend).

### Expected Features

**Must have (table stakes):**
- URL input with YouTube/Vimeo auto-detection -- the entry point
- oEmbed auto-fetch poster thumbnail -- zero-config visual
- Click-to-play with provider SDK lazy loading -- core privacy/performance behavior
- Play icon overlay with YouTube-branded variants (dark/light/red) -- legal compliance
- Privacy-enhanced embed URLs (youtube-nocookie.com, Vimeo dnt=1)
- Responsive layout with CLS prevention -- Core Web Vitals compliance
- Accessible play button with ARIA labels, keyboard activation
- Inline display mode -- simplest working mode
- Native Gutenberg block with React edit component

**Should have (differentiators):**
- Three display modes (inline, modal, modal-only) -- no competitor offers all three
- UIModal composition for modal playback -- reuses battle-tested dialog system
- InnerBlocks custom poster -- any block type as poster, not just image upload
- Decoupled triggers and deep linking via URL hash
- Mutual exclusion (one video at a time) via `sitchco.hooks`
- Click behavior mode (entire poster vs icon only)
- Start time support from URL parameters
- Play icon X/Y positioning

**Defer (v2+):**
- GTM analytics events -- requires stabilized internal API
- Extension hooks (video-play, video-pause, video-ended) -- expose after API is stable
- Additional providers beyond YouTube/Vimeo -- architecture supports it, no demand yet
- Autoplay on scroll, self-hosted video, built-in analytics dashboard, consent management -- all anti-features for this block's scope

### Architecture Approach

The block has three runtime environments with distinct responsibilities: editor (React/JSX for block configuration and preview), server (PHP for HTML output and modal composition), and frontend (vanilla JS for click-to-play, SDK loading, and playback control). Data flows from editor attributes through PHP server-render to frontend JS via a data-attribute contract on the wrapper element. The provider SDK adapter pattern normalizes YouTube and Vimeo behind a common interface (load, create, play, pause, destroy, on). UIModal composition is achieved by creating a `ModalData` instance and passing it to `UIModal::loadModal()` in render.php.

**Major components:**
1. **UIVideo module (PHP)** -- Module lifecycle, asset registration, oEmbed utility, provider detection
2. **block.json** -- Block metadata, attribute schema, script/style declarations
3. **edit.js (React)** -- Editor UI: URL input, oEmbed preview, InnerBlocks, inspector controls
4. **save.js (React)** -- Persistence: returns `<InnerBlocks.Content/>` for inner block storage
5. **render.php** -- Server-side HTML: poster, play icon SVG, data attributes, modal loading
6. **view.js (Vanilla JS)** -- Frontend: click-to-play, SDK loading, player lifecycle, mutual exclusion
7. **Provider SDK adapters (in view.js)** -- Normalize YouTube/Vimeo SDK differences

### Critical Pitfalls

1. **Dynamic block + InnerBlocks save conflict** -- `save` must return `<InnerBlocks.Content/>`, not `null`. Returning null silently discards all inner blocks on save/reload. Must be correct in the first block skeleton.
2. **YouTube IFrame API global singleton race** -- Multiple blocks fight over `window.onYouTubeIframeAPIReady`. Build a Promise-based singleton loader that queues callbacks and resolves all pending consumers.
3. **Block deprecation debt** -- Every attribute rename after content exists requires a deprecation entry with self-contained save function. Lock the schema before shipping to production. Establish a "freeze" point.
4. **Video continues after modal close** -- Iframes do not pause when hidden. Hook into `ui-modal-hide` action with SDK `pause()` call (not iframe src clearing) before modal closes.
5. **Privacy-enhanced mode is not truly private** -- `youtube-nocookie.com` only affects iframe domain; the SDK script still loads from `youtube.com`. Click-to-load architecture is the real privacy boundary, not the domain swap.

## Implications for Roadmap

Based on research, the dependency chain and architecture boundaries suggest 5 phases:

### Phase 1: Block Foundation and Editor Skeleton

**Rationale:** You cannot test frontend playback without a block that saves data correctly. The InnerBlocks save function conflict (Critical Pitfall 2) must be solved first -- everything else builds on this foundation. The attribute schema should be locked at the end of this phase.
**Delivers:** A registered native Gutenberg block with correct save/edit cycle, attribute schema, URL input with provider detection, and oEmbed preview in the editor.
**Addresses:** URL input + provider detection, oEmbed auto-fetch, native block registration, attribute schema definition
**Avoids:** Dynamic block + InnerBlocks save conflict, useBlockProps/useInnerBlocksProps hook order, ServerSideRender anti-pattern, block deprecation debt (by locking schema early)

### Phase 2: Frontend Inline Playback

**Rationale:** Inline playback is the simplest display mode and exercises the entire click-to-play pipeline (poster render, SDK loading, player creation, CLS prevention). Get SDK integration working in the simplest context before adding modal complexity.
**Delivers:** Working click-to-play with poster image, play icon overlay, privacy-enhanced URLs, layout-shift prevention, and accessible play button.
**Uses:** YouTube IFrame API (CDN), Vimeo Player SDK (CDN), render.php server-side output, view.js frontend JS
**Implements:** Provider SDK adapter pattern, data-attribute contract (PHP to JS), click-to-play facade
**Avoids:** YouTube API global callback race (singleton loader), privacy-enhanced mode false sense (verify zero pre-click requests), CLS on poster-to-iframe swap

### Phase 3: Modal Integration

**Rationale:** Modal playback composes inline playback (same SDK, same player) with UIModal. Adding modal is incremental once inline works. The UIModal content-based refactor is already done (commit `4fc37f7`).
**Delivers:** Modal display mode, modal-only display mode, pause-on-close, decoupled triggers, deep linking via URL hash.
**Addresses:** Three display modes, UIModal composition, decoupled trigger system, deep linking
**Avoids:** Video continues after modal close (implement pause-on-close immediately), iframe recreation on each modal open (persist iframe, pause/resume)

### Phase 4: InnerBlocks Poster Composition and Editor Polish

**Rationale:** InnerBlocks custom poster is a differentiator but not required for core functionality. It depends on the block editor experience being solid (Phase 1) and display modes working (Phases 2-3). Group with remaining editor polish (display mode selector, play icon config, click behavior mode).
**Delivers:** InnerBlocks custom poster override, display mode inspector controls, play icon variant selection, click behavior mode, play icon X/Y positioning, start time support.
**Addresses:** InnerBlocks custom poster, click behavior mode, play icon positioning, start time, editor UX polish
**Avoids:** Inspecting InnerBlocks content (check only existence), YouTube thumbnail 404 for maxresdefault (use oEmbed URL directly)

### Phase 5: Cross-Cutting Concerns and Extensibility

**Rationale:** Mutual exclusion, analytics, and extension hooks are cross-cutting features that benefit from a stable core. They do not change the architecture -- they compose on top of it via `sitchco.hooks`.
**Delivers:** Mutual exclusion (one video at a time), GTM analytics events, extension hooks (video-play, video-pause, video-ended, playerVars filter).
**Addresses:** Mutual exclusion, GTM analytics, extension points
**Avoids:** Exposing public hooks before API is stable

### Phase Ordering Rationale

- **Phase 1 before all else:** The attribute schema and save function are the data model. Every subsequent phase depends on correct block persistence. Fixing save-function bugs after content exists requires deprecation entries.
- **Phase 2 before Phase 3:** Inline playback is simpler than modal. SDK integration, provider adapters, and the click-to-play pipeline should be proven in the simplest context first.
- **Phase 3 before Phase 4:** Modal modes are a higher-value differentiator than InnerBlocks poster. Modal-only mode unlocks decoupled triggers and deep linking -- features no competitor offers.
- **Phase 4 groups composition with polish:** InnerBlocks, play icon config, and click behavior are all editor-side enhancements that make sense to build together once the runtime is stable.
- **Phase 5 last:** Mutual exclusion and analytics are additive. They subscribe to existing hooks and player events. Building them last means the hook surface area is finalized.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 1:** JSX/Vite build integration -- the existing pipeline handles JSX but this is the first native block. May need Vite config adjustments for `editorScript` entry point discovery and `.asset.php` sidecar generation.
- **Phase 2:** Provider SDK adapter implementation -- YouTube and Vimeo have meaningfully different APIs (YouTube uses global callbacks, Vimeo returns Promises). The adapter normalization layer needs careful design.
- **Phase 3:** Modal player lifecycle -- persisting the iframe across modal open/close cycles while properly pausing/resuming requires careful DOM management. The interplay between UIModal's dialog lifecycle and video player state should be researched.

Phases with standard patterns (skip research-phase):
- **Phase 4:** InnerBlocks is well-documented in WordPress block editor docs. The pattern (check `$content` emptiness, render if present, fall back to oEmbed if absent) is straightforward.
- **Phase 5:** `sitchco.hooks` action/filter pattern is already proven by UIModal. Mutual exclusion is a simple listener that pauses all players except the current one.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Existing platform covers 95% of needs. Provider SDKs are well-documented. No novel dependencies. |
| Features | HIGH | Competitor analysis is thorough. Feature boundaries are clear. Anti-features list prevents scope creep. |
| Architecture | HIGH | Three-runtime model (editor/server/frontend) is standard WP block pattern. Composition with UIModal is well-defined. Provider adapter pattern is straightforward. |
| Pitfalls | HIGH | All critical pitfalls verified against official docs and codebase. Prevention strategies include concrete code patterns. |

**Overall confidence:** HIGH

### Gaps to Address

- **Vite JSX build for editorScript**: The existing `@sitchco/module-builder` auto-discovers JSX files in `blocks/*/` but this is the first block with a React `edit` component. Verify that the build pipeline generates correct WordPress script dependencies and handles the `editorScript` field in `block.json` with `file:` prefix. May need to register the editor script by handle instead.
- **`viewScript` path resolution in block.json**: The `file:` prefix in `block.json` for `viewScript` may not resolve correctly when the JS file is outside the block directory (e.g., in `assets/scripts/`). Alternative is registering via PHP and referencing by handle.
- **oEmbed caching in render_callback**: WordPress core caches oEmbed in post meta for content-parsed URLs, but `wp_oembed_get()` in custom code does not auto-cache. Need to verify caching strategy during Phase 2 planning.
- **YouTube thumbnail quality**: The oEmbed response returns `hqdefault.jpg` (480x360). If higher resolution is needed, server-side HEAD request to `maxresdefault.jpg` with fallback is the safe approach -- but this adds complexity. Decide during Phase 2 whether `hqdefault` is sufficient.

## Sources

### Primary (HIGH confidence)
- [WordPress Block Metadata (block.json)](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/)
- [WordPress Block Edit and Save](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/)
- [WordPress InnerBlocks](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/nested-blocks-inner-blocks/)
- [WordPress Dynamic Blocks](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/creating-dynamic-blocks/)
- [WordPress Block Deprecation](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/)
- [YouTube IFrame Player API Reference](https://developers.google.com/youtube/iframe_api_reference)
- [YouTube Embedded Player Parameters](https://developers.google.com/youtube/player_parameters)
- [YouTube Branding Guidelines](https://developers.google.com/youtube/terms/branding-guidelines)
- [Vimeo Player SDK Reference](https://developer.vimeo.com/player/sdk/reference)
- [Vimeo Player SDK Embed Options](https://developer.vimeo.com/player/sdk/embed)
- [Vimeo DNT Parameter](https://help.vimeo.com/hc/en-us/articles/26080940921361-Vimeo-Player-Cookies)
- [@kucrut/vite-for-wp](https://github.com/kucrut/vite-for-wp)
- [WordPress oEmbed Proxy (Trac #40450)](https://core.trac.wordpress.org/ticket/40450)

### Secondary (MEDIUM confidence)
- [lite-youtube-embed](https://github.com/paulirish/lite-youtube-embed) -- facade pattern reference implementation
- [WordPress Performance Team - Facade Embeds Issue #113](https://github.com/WordPress/performance/issues/113)
- [Gutenberg Issue #52185 - Video CLS](https://github.com/WordPress/gutenberg/issues/52185)
- [10up InnerBlocks Best Practices](https://gutenberg.10up.com/reference/Blocks/inner-blocks/)
- [Gutenberg InnerBlocks in Dynamic Blocks Discussion #44466](https://github.com/WordPress/gutenberg/discussions/44466)
- [YouTube Privacy-Enhanced Mode limitations](https://www.stefanjudis.com/notes/the-lie-of-youtubes-privacy-enhanced-embed-mode/)
- [GTM4WP Media Player Tracking](https://gtm4wp.com/setup-gtm4wp-features/how-to-track-embedded-media-players-youtube-vimeo-soundcloud)
- [web.dev Embed Best Practices](https://web.dev/articles/embed-best-practices)
- [Eliminating Layout Shifts in the Video Block](https://weston.ruter.net/2025/06/05/eliminating-layout-shifts-in-the-video-block/)

### Tertiary (LOW confidence)
- YouTube maxresdefault 404 behavior -- documented in community sources but not official YouTube docs
- Vimeo DNT cookie reduction scope -- Vimeo docs confirm "reduces but does not eliminate" cookies without specifics

---
*Research completed: 2026-03-09*
*Ready for roadmap: yes*
