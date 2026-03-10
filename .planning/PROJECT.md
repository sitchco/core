# Video Component (`sitchco/video`)

## What This Is

A native Gutenberg video wrapper block for sitchco-core that provides click-to-play video embedding with three display modes (inline, modal, modal-only). It composes with UIModal for modal playback, uses InnerBlocks for custom poster overrides, auto-fetches oEmbed thumbnails as the default poster, and enforces privacy-first click-to-load behavior. Supports YouTube and Vimeo with provider-specific play icon branding and SDK-driven playback control.

## Core Value

Paste a video URL, get a privacy-respecting, accessible video player with zero additional author effort — the poster, title, and modal ID all auto-populate from oEmbed metadata.

## Requirements

### Validated

<!-- Existing capabilities in sitchco-core that this project builds on -->

- ✓ UIModal system with `<dialog>`, focus trapping, scroll locking, hash syncing, ARIA — existing
- ✓ Module system with DI container, lifecycle management, asset pipeline — existing
- ✓ `sitchco.hooks` JS action/filter system for inter-module coordination — existing
- ✓ Vite-based asset pipeline with dev server and production builds — existing
- ✓ Block registration via `BlockManifestRegistry` and `block.json` — existing
- ✓ ACF block pattern for simple blocks — existing (this project introduces the native block pattern)

### Active

<!-- Current scope. Building toward these. -->

- [ ] UIModal supports content-based modals (arbitrary HTML, not just Post objects)
- [ ] Native Gutenberg block registered via `block.json` with React `edit` component
- [ ] Three display modes: inline, modal, modal-only
- [ ] Video URL attribute with provider auto-detection (YouTube, Vimeo)
- [ ] oEmbed auto-fetch poster when no InnerBlocks present
- [ ] InnerBlocks override for custom poster composition (any block type)
- [ ] Play icon overlay with configurable X/Y position
- [ ] YouTube-branded play icon (dark/light/red) per API ToS
- [ ] Generic play icon for non-YouTube providers (dark/light)
- [ ] Click-to-play with provider SDK lazy loading (YouTube IFrame API, Vimeo Player SDK)
- [ ] Inline playback with layout-shift prevention (dimension locking)
- [ ] Modal playback via UIModal composition (pause on close, persist iframe)
- [ ] Modal-only mode (invisible on page, `<dialog>` in `wp_footer`)
- [ ] Decoupled triggers via `href="#id"` or `data-target="#id"`
- [ ] Deep linking via URL hash navigation
- [ ] oEmbed metadata auto-populates video title and modal ID
- [ ] Click behavior mode: entire poster vs play icon only
- [ ] Mutual exclusion (only one video plays at a time)
- [ ] GTM analytics events (start, pause, progress milestones)
- [ ] Privacy-enhanced embed domains (youtube-nocookie.com, Vimeo dnt)
- [ ] Accessible play button (`<button>`, `aria-label`, keyboard activation)
- [ ] Accessible poster wrapper in entire-poster mode (`role="button"`, `tabindex="0"`)
- [ ] Start time support from URL parameters
- [ ] Extension points: JS hooks (`video-play`, `video-pause`, `video-ended`) and filters (`playerVars`, play icon SVG)

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Modal title/description display beyond accessibility heading — can be added later as visible attributes
- Carousel slide-change auto-pause — carousel component owns its own policy, can use `video-pause` hook
- Custom end-state behavior — provider defaults are sufficient
- Background video / self-hosted video — separate system with different requirements
- Consent management / CMP integration — click-to-load architecture provides natural interception points for future consent layer
- Provider expansion beyond YouTube/Vimeo — architecture supports it but no v1 implementation
- Aspect ratio logic for the player — iframe fills 100% of wrapper, poster determines dimensions

## Context

- This is the first native Gutenberg block in sitchco-core, establishing the pattern for future blocks that need rich editor interactions beyond ACF block mode
- UIModal currently requires a `Post` object — a prerequisite refactor enables content-based modals for this block and future non-post modals
- The existing `sitchco.hooks` system provides the coordination layer for mutual exclusion, analytics, and external pause requests
- YouTube API ToS requires branded play buttons — this is a legal/compliance requirement, not a preference
- The block is a platform-level concern (sitchco-core), not theme-level, enabling cross-site reuse

## Constraints

- **Privacy**: No provider iframe, SDK, or CDN resource loads in the browser before user clicks play
- **YouTube ToS**: Must use official YouTube branded play button (dark, light, or red variant)
- **Platform-level**: Block lives in sitchco-core, no theme-level dependencies
- **Native block**: Must be a native Gutenberg block (not ACF), registered via `block.json` with React `edit` component
- **InnerBlocks opacity**: Wrapper checks only whether InnerBlocks exist, never inspects their content
- **Provider SDKs required**: YouTube IFrame API and Vimeo Player SDK needed for mutual exclusion, analytics, and programmatic control

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Native Gutenberg block over ACF block | InnerBlocks flexibility, conditional inspector UI, oEmbed preview fetching require full editor control | — Pending |
| Provider SDKs over raw iframes | Mutual exclusion, analytics, and programmatic pause require SDK-level control | — Pending |
| oEmbed auto-fetch as default poster | Zero-config experience — paste URL, get working video with thumbnail | — Pending |
| Compose with UIModal for modals | Reuse existing dialog infrastructure (focus trap, hash sync, ARIA) | — Pending |
| UIModal content-based modal refactor | Enables non-post modals for video and future use cases | — Pending |

---
*Last updated: 2026-03-09 after initialization*
