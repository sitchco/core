# Phase 2: Poster Rendering & Inline Playback - Context

**Gathered:** 2026-03-09
**Status:** Ready for planning

<domain>
## Phase Boundary

Server-side poster rendering (oEmbed auto-fetch or InnerBlocks), accessible play button overlay, click-to-play inline playback with provider SDKs (YouTube IFrame API, Vimeo Player SDK), layout-shift prevention via dimension locking, and privacy-enhanced embed domains. No modal playback (Phase 3), no mutual exclusion or analytics (Phase 4).

</domain>

<decisions>
## Implementation Decisions

### oEmbed Poster Fallback (POST-05)
- Fallback chain: InnerBlocks > oEmbed thumbnail > generic placeholder
- InnerBlocks always win — wrapper checks only whether they exist (POST-04)
- Generic placeholder when no thumbnail: neutral dark/light box with generic play icon, no provider branding
- Poster aspect ratio should match the actual video dimensions (oEmbed response includes width/height) — YouTube and Vimeo both support portrait (9:16) videos now
- When oEmbed fails entirely (no response), default to 16:9 aspect ratio for the placeholder

### Layout Shift Prevention (INLN-02)
- Already specified in design spec (I1, I2): JS reads and locks current rendered dimensions via inline CSS on click
- Iframe fills 100% of wrapper (Axiom 8)
- No CSS aspect-ratio logic for the player — poster determines dimensions, JS locks them at click time
- Portrait poster (e.g., 3:4) locks to poster's rendered dimensions; no letterboxing

### Play Icon Rendering (Frontend)
- SVG sprite with `<use>` references — source SVGs in `modules/VideoBlock/assets/images/svg-sprite/`
- Build tooling generates sitchco-core sprite at `dist/assets/images/sprite.svg`
- render.php outputs `<svg><use href="sprite.svg#icon-name"></svg>`
- Fixed size: 68px width (matches editor preview) — play icons are a UI control, not content
- Hardcoded fills in SVG source files (rgba(0,0,0,0.8), #FF0000, #fff, etc.) — matches editor JSX exactly
- No `currentColor` — YouTube branded colors are specific per ToS; the PHP filter (sitchco/video/play-icon/svg) lets themes override entirely if needed
- 5 icon variants in sprite: youtube-play-dark, youtube-play-light, youtube-play-red, generic-play-dark, generic-play-light

### SDK Loading & Player Creation
- Single viewScript file (view.js) handles both YouTube and Vimeo — provider-specific logic branched internally
- Initialize via `sitchco.register()` lifecycle hook — query all `.sitchco-video` elements, read data attributes, attach click handlers
- YouTube IFrame API global callback (`onYouTubeIframeAPIReady`) wrapped in a Promise via `sitchco.loadScript()` — consistent async pattern with Vimeo
- Vimeo Player SDK loaded via `sitchco.loadScript()` as well
- SDKs load on first click only (INLN-07, PRIV-01) — subsequent clicks on other videos reuse the already-loaded SDK
- Once playing, player's native controls take over — no wrapper click behavior after iframe loads
- Start time from URL parameters respected (INLN-06)

### Claude's Discretion
- Exact generic placeholder styling (colors, icon sizing within placeholder)
- How render.php resolves the sprite URL (Vite manifest lookup vs direct path)
- Internal structure of view.js (class-based vs function-based, how provider branching is organized)
- Error handling for SDK load failures
- How to extract video ID from URL for SDK player construction

</decisions>

<specifics>
## Specific Ideas

- The rlf project has an existing YouTube play SVG at `set-design/video/images/svg-sprite/youtube-play.svg` using `currentColor` fill — can reference its shape/path data but use hardcoded fills instead
- The roundabout theme sprite at `dist/assets/images/sprite.svg` demonstrates the `<use>` pattern with symbol IDs like `icon-youtube`
- The design spec (video-design.md) scenarios I1, I2, F1, F2 are the canonical reference for this phase's frontend behavior
- oEmbed width/height from response should be used to determine poster aspect ratio — supports both landscape and portrait videos

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- **sitchco.loadScript()** (`modules/UIFramework/assets/scripts/lib/script-registration.js`): Promise-based lazy script loader with deduplication. Perfect for on-demand SDK loading.
- **sitchco.hooks** (`modules/UIFramework/assets/scripts/hooks.js`): Action/filter system wrapping `@wp/hooks`. Video lifecycle hooks (video-play, video-pause) will use this in Phase 4.
- **sitchco.register()** (`modules/UIFramework/assets/scripts/main.js`): Lifecycle hook for component registration on DOMContentLoaded. Video viewScript initializes here.
- **render.php** (`modules/VideoBlock/blocks/video/render.php`): Currently outputs a basic wrapper div with data attributes. Phase 2 expands this with poster rendering, play button, accessibility attributes, and oEmbed server-side fetch.
- **editor.jsx play icon SVGs** (`modules/VideoBlock/blocks/video/editor.jsx:42-115`): Reference implementation for icon shapes and colors. Frontend SVGs should match exactly.
- **UIModal** (`modules/UIModal/`): Not used in Phase 2 but the modal composition pattern informs how the video block will integrate in Phase 3.

### Established Patterns
- **BEM CSS**: `sitchco-video`, `sitchco-video__play-icon`, etc. — continue this for new elements (poster, player container)
- **Data attributes on wrapper**: `data-url`, `data-provider`, `data-display-mode`, etc. — viewScript reads these for initialization
- **Module asset registration**: block.json `viewScript` field for frontend JS. First native block viewScript.
- **SVG sprite**: Build tooling (`@sitchco/cli`) generates sprites from source SVG files. Theme already uses this pattern.

### Integration Points
- **block.json**: Add `viewScript` field pointing to `view.js` for frontend behavior
- **render.php**: Major expansion — oEmbed fetch, poster img/InnerBlocks conditional, play button, accessibility attributes
- **wp_oembed_get()**: WordPress oEmbed API for server-side thumbnail fetch. Note from STATE.md: caching strategy may need attention (custom code doesn't auto-cache like content-parsed URLs)
- **sitchco-core sprite**: New sprite source directory needed at `modules/VideoBlock/assets/images/svg-sprite/`

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 02-poster-rendering-inline-playback*
*Context gathered: 2026-03-09*
