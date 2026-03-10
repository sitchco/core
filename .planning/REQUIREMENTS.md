# Requirements: sitchco/video

**Defined:** 2026-03-09
**Core Value:** Paste a video URL, get a privacy-respecting, accessible video player with zero additional author effort

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Prerequisites

- [x] **PRE-01**: UIModal supports content-based modals — `loadModalContent(id, title, content, type)` creates a `ModalData` from arbitrary HTML without a backing WordPress post
- [x] **PRE-02**: UIModal content-based modals render identical `<dialog>` structure and JS behavior as post-based modals

### Block Foundation

- [x] **BLK-01**: Native Gutenberg block registered via `block.json` with React `edit` component (not ACF block)
- [x] **BLK-02**: Block inserts empty — no InnerBlocks pre-populated, no play icon until URL is entered
- [x] **BLK-03**: Block uses dynamic rendering (PHP `render_callback`) with `save` returning `<InnerBlocks.Content/>`

### Authoring

- [x] **AUTH-01**: Author can enter a YouTube or Vimeo URL in the inspector panel, stored as a block attribute
- [x] **AUTH-02**: Provider is auto-detected from the URL (YouTube, Vimeo)
- [x] **AUTH-03**: Editor fetches oEmbed response via WordPress proxy endpoint (`/wp-json/oembed/1.0/proxy`) — no direct client-side requests to providers
- [x] **AUTH-04**: oEmbed thumbnail is displayed as the poster preview in the editor with provider-appropriate play icon
- [x] **AUTH-05**: Video title auto-populates from oEmbed response into `videoTitle` attribute (editable, not overwritten if manually edited)
- [x] **AUTH-06**: Author can select display mode: Inline (default), Modal, or Modal Only
- [x] **AUTH-07**: Modal/Modal Only modes show title field and modal ID field; modal ID auto-generates as slugified title (editable, not overwritten if manually edited)
- [x] **AUTH-08**: Inline mode hides modal-specific options; Modal Only mode hides InnerBlocks editing UI
- [x] **AUTH-09**: Author can configure play icon style — YouTube: dark/light/red (all branded); non-YouTube: dark/light (generic)
- [x] **AUTH-10**: Author can position play icon via X/Y sliders (default: 50%/50%)
- [x] **AUTH-11**: Author can toggle click behavior between "Entire poster" (default) and "Play icon only"

### Poster

- [x] **POST-01**: Server-side oEmbed resolves video URL and renders `<img>` with provider thumbnail as poster when no InnerBlocks present
- [x] **POST-02**: When InnerBlocks are present, InnerBlocks content renders as the poster — oEmbed auto-fetch is not executed
- [x] **POST-03**: Author can add any block type as InnerBlocks inside the video wrapper (no allowed block type restrictions)
- [x] **POST-04**: Wrapper checks only whether InnerBlocks exist (not what they contain)
- [x] **POST-05**: Graceful fallback when oEmbed returns no thumbnail (generic placeholder or omit poster)

### Inline Playback

- [x] **INLN-01**: User click on play target loads provider SDK (YouTube IFrame API or Vimeo Player SDK) if not already loaded
- [x] **INLN-02**: On click, wrapper reads and locks current rendered dimensions via inline CSS to prevent layout shift
- [x] **INLN-03**: Poster content and play icon are hidden after click
- [x] **INLN-04**: Iframe is created inside wrapper at 100% width and 100% height
- [x] **INLN-05**: Playback begins automatically once the player is ready
- [x] **INLN-06**: Start time from URL parameters is respected (YouTube `?t=`, Vimeo `#t=`)
- [x] **INLN-07**: No provider SDK, iframe, or CDN resource loads in the browser before user clicks play

### Modal Playback

- [x] **MODL-01**: Click on play target opens `<dialog>` via UIModal's `showModal()`
- [x] **MODL-02**: URL hash updates to `#modal-id` when modal opens
- [x] **MODL-03**: Provider SDK loads and iframe is created inside dialog's content area
- [x] **MODL-04**: Poster on the page remains visible (not hidden/swapped) when modal opens
- [x] **MODL-05**: Player pauses (not destroyed) when modal closes — iframe remains in DOM
- [x] **MODL-06**: Reopening the same modal resumes the existing player — no duplicate iframes
- [x] **MODL-07**: Modal-only mode renders no visible frontend element — only a `<dialog>` in `wp_footer`
- [x] **MODL-08**: Modal-only block with no trigger on page renders inert dialog — no errors

### Triggers & Deep Linking

- [x] **TRIG-01**: Any `<a href="#video-modal-id">` or `[data-target="#video-modal-id"]` triggers the video modal
- [x] **TRIG-02**: Multiple triggers for the same modal ID all work correctly
- [x] **TRIG-03**: Direct URL navigation with `#video-modal-id` hash opens the modal on page load
- [x] **TRIG-04**: Modal ID is stable, human-readable (slugified video title by default)

### Mutual Exclusion

- [x] **MXCL-01**: Starting a second video (inline or modal) pauses the first
- [x] **MXCL-02**: Opening a video modal pauses any currently playing inline video

### Analytics

- [x] **ANLT-01**: GTM interaction event fires on video start: `{action: 'start', provider, url, id}`
- [x] **ANLT-02**: GTM interaction events fire at progress milestones: 25%, 50%, 75%, 100%
- [x] **ANLT-03**: GTM interaction event fires on video pause: `{action: 'pause'}`

### Privacy

- [x] **PRIV-01**: No browser-initiated network requests to video providers on page load (server-side oEmbed at render time is acceptable)
- [x] **PRIV-02**: YouTube embeds use `youtube-nocookie.com` domain
- [x] **PRIV-03**: Vimeo embeds include `dnt: true` parameter

### Accessibility

- [x] **ACCS-01**: Play overlay is a `<button>` with `aria-label` including "Play video" and the video title
- [x] **ACCS-02**: Play button is keyboard focusable and activatable with Enter/Space
- [x] **ACCS-03**: Entire-poster click mode wrapper has `role="button"`, `tabindex="0"`, and appropriate `aria-label`
- [x] **ACCS-04**: Modal `<dialog>` has `aria-labelledby` referencing a heading with the video title

### Extension Points

- [x] **EXTN-01**: JS action `video-play` fires when a video starts playing with `{id, provider, url}` payload
- [x] **EXTN-02**: JS action `video-pause` allows external code to pause a video by ID
- [x] **EXTN-03**: JS action `video-ended` fires when a video reaches the end
- [x] **EXTN-04**: JS filter `sitchco/video/playerVars/youtube` allows overriding YouTube player parameters
- [x] **EXTN-05**: JS filter `sitchco/video/playerVars/vimeo` allows overriding Vimeo player parameters
- [x] **EXTN-06**: PHP filter `sitchco/video/play-icon/svg` allows replacing play button SVG markup

### No-Op Behavior

- [x] **NOOP-01**: Video block with no URL set renders InnerBlocks without play icon or click-to-play behavior
- [x] **NOOP-02**: Video block does not auto-pause on visibility changes (external code uses `video-pause` hook)

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Providers

- **PROV-01**: Support additional video providers beyond YouTube/Vimeo (Dailymotion, Wistia, etc.)

### Consent

- **CONS-01**: CMP integration — check consent status at click time, offer alternative when user has opted out

### Visual

- **VISL-01**: Modal title/description visible display beyond accessibility heading

## Out of Scope

| Feature | Reason |
|---------|--------|
| Autoplay on scroll/viewport | Violates privacy-first premise; browsers block autoplay with sound; conflicts with mutual exclusion |
| Self-hosted/HTML5 video | Different technical requirements; WordPress core Video block already handles this |
| Built-in analytics dashboard | Massive scope increase; GTM/GA4 handles collection and reporting |
| Background video mode | Fundamentally different UX (no poster, autoplay+muted+loop); separate block type |
| Aspect ratio controls for player | Iframe fills 100% of wrapper; poster determines dimensions; second source of truth creates conflicts |
| Video gallery/playlist | Collection/layout concern; use WordPress layout blocks to arrange multiple video blocks |
| Sticky/floating video on scroll | Complex z-index/intersection logic; can be added as separate behavior module later |
| Custom end-state behavior | Provider defaults are sufficient; `video-ended` hook enables external CTAs |
| Carousel auto-pause | Carousel component owns its policy; can use `video-pause` hook |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| PRE-01 | Phase 1 | Complete |
| PRE-02 | Phase 1 | Complete |
| BLK-01 | Phase 1 | Complete |
| BLK-02 | Phase 1 | Complete |
| BLK-03 | Phase 1 | Complete |
| AUTH-01 | Phase 1 | Complete |
| AUTH-02 | Phase 1 | Complete |
| AUTH-03 | Phase 1 | Complete |
| AUTH-04 | Phase 1 | Complete |
| AUTH-05 | Phase 1 | Complete |
| AUTH-06 | Phase 1 | Complete |
| AUTH-07 | Phase 1 | Complete |
| AUTH-08 | Phase 1 | Complete |
| AUTH-09 | Phase 1 | Complete |
| AUTH-10 | Phase 1 | Complete |
| AUTH-11 | Phase 1 | Complete |
| NOOP-01 | Phase 1 | Complete |
| POST-01 | Phase 2 | Complete |
| POST-02 | Phase 2 | Complete |
| POST-03 | Phase 2 | Complete |
| POST-04 | Phase 2 | Complete |
| POST-05 | Phase 2 | Complete |
| INLN-01 | Phase 2 | Complete |
| INLN-02 | Phase 2 | Complete |
| INLN-03 | Phase 2 | Complete |
| INLN-04 | Phase 2 | Complete |
| INLN-05 | Phase 2 | Complete |
| INLN-06 | Phase 2 | Complete |
| INLN-07 | Phase 2 | Complete |
| PRIV-01 | Phase 2 | Complete |
| PRIV-02 | Phase 2 | Complete |
| PRIV-03 | Phase 2 | Complete |
| ACCS-01 | Phase 2 | Complete |
| ACCS-02 | Phase 2 | Complete |
| ACCS-03 | Phase 2 | Complete |
| MODL-01 | Phase 3 | Complete |
| MODL-02 | Phase 3 | Complete |
| MODL-03 | Phase 3 | Complete |
| MODL-04 | Phase 3 | Complete |
| MODL-05 | Phase 3 | Complete |
| MODL-06 | Phase 3 | Complete |
| MODL-07 | Phase 3 | Complete |
| MODL-08 | Phase 3 | Complete |
| TRIG-01 | Phase 3 | Complete |
| TRIG-02 | Phase 3 | Complete |
| TRIG-03 | Phase 3 | Complete |
| TRIG-04 | Phase 3 | Complete |
| ACCS-04 | Phase 3 | Complete |
| MXCL-01 | Phase 4 | Complete |
| MXCL-02 | Phase 4 | Complete |
| ANLT-01 | Phase 4 | Complete |
| ANLT-02 | Phase 4 | Complete |
| ANLT-03 | Phase 4 | Complete |
| EXTN-01 | Phase 4 | Complete |
| EXTN-02 | Phase 4 | Complete |
| EXTN-03 | Phase 4 | Complete |
| EXTN-04 | Phase 4 | Complete |
| EXTN-05 | Phase 4 | Complete |
| EXTN-06 | Phase 4 | Complete |
| NOOP-02 | Phase 4 | Complete |

**Coverage:**
- v1 requirements: 60 total
- Mapped to phases: 60
- Unmapped: 0

---
*Requirements defined: 2026-03-09*
*Last updated: 2026-03-09 after roadmap creation*
