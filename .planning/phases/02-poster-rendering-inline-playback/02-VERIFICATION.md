---
phase: 02-poster-rendering-inline-playback
verified: 2026-03-09T20:12:23Z
status: human_needed
score: 10/10 must-haves verified
human_verification:
  - test: "Poster renders with oEmbed thumbnail and play icon overlay"
    expected: "Thumbnail image displays with provider-appropriate play icon positioned correctly"
    why_human: "Visual rendering, image loading from oEmbed, sprite display cannot be verified programmatically"
  - test: "Zero provider requests before clicking play (DevTools Network tab)"
    expected: "No requests to youtube.com, youtu.be, vimeo.com on page load"
    why_human: "Network behavior requires real browser observation"
  - test: "Click play button triggers inline playback with YouTube"
    expected: "Poster disappears, YouTube player loads via IFrame API, video plays; iframe src uses youtube-nocookie.com"
    why_human: "JavaScript runtime behavior, SDK loading, iframe creation require browser"
  - test: "No layout shift during poster-to-player transition"
    expected: "Content below the video does not jump or shift when play is clicked"
    why_human: "Visual layout stability requires human observation"
  - test: "Keyboard accessibility (Tab to play button, Enter/Space activates)"
    expected: "Play button receives visible focus; Enter or Space triggers playback"
    why_human: "Focus management and keyboard interaction require real browser"
  - test: "Start time respected (YouTube URL with ?t=30 starts at 30s)"
    expected: "Video begins playback at the specified time offset"
    why_human: "Player seek behavior requires real browser with SDK"
  - test: "InnerBlocks content renders as poster when present"
    expected: "Custom InnerBlocks HTML shown instead of oEmbed thumbnail"
    why_human: "WordPress block rendering pipeline requires live WP instance"
  - test: "Vimeo playback with dnt parameter"
    expected: "Vimeo player loads and plays; player constructor includes dnt:true"
    why_human: "Vimeo SDK loading and player creation require browser"
---

# Phase 2: Poster Rendering & Inline Playback Verification Report

**Phase Goal:** Visitors see a poster image with accessible play button, click to load the provider SDK and play inline with no layout shift -- zero provider resources load before the click
**Verified:** 2026-03-09T20:12:23Z
**Status:** human_needed
**Re-verification:** No -- initial verification

## Goal Achievement

### Observable Truths

#### Plan 02-01 Truths (Server-Side Poster Rendering)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Page with video block and no InnerBlocks renders an `<img>` poster from oEmbed thumbnail | VERIFIED | render.php lines 82-89: oEmbed thumbnail rendered as `<img>` with `sitchco-video__poster-img` class; test `test_render_with_oembed_thumbnail` confirms |
| 2 | Page with video block containing InnerBlocks renders the InnerBlocks as the poster instead of fetching oEmbed | VERIFIED | render.php line 75: `$has_inner_blocks = !empty(trim($content))`, lines 77-79: uses `$content` as poster; test `test_render_innerblocks_as_poster` confirms no `<img>` present |
| 3 | Page with video block whose oEmbed fails renders a generic placeholder with play icon | VERIFIED | render.php line 95: `<div class="sitchco-video__placeholder-poster">` rendered when no thumbnail; test `test_render_generic_placeholder` confirms |
| 4 | Play button is a native `<button>` element with aria-label containing the video title | VERIFIED | render.php lines 110-116: `<button>` with `aria-label="Play video: {title}"`; test `test_play_button_aria_label` confirms |
| 5 | Entire-poster click mode wrapper has role=button, tabindex=0, and aria-label | VERIFIED | render.php lines 133-137: conditional ACCS-03 attributes; test `test_poster_click_mode_accessibility` confirms all three; test `test_icon_click_mode_no_wrapper_role` confirms absence for icon mode |
| 6 | Zero browser-initiated requests to video providers on page load (server-side oEmbed only) | VERIFIED | render.php uses `_wp_oembed_get_object()->get_data()` server-side with transient cache; view.js only loads SDKs on click (`{ once: true }`); no `<script>` or `<iframe>` in render.php output |

#### Plan 02-02 Truths (Click-to-Play Inline Playback)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 7 | Click on play button loads provider SDK and begins inline playback | VERIFIED | view.js lines 158-184: `handlePlay()` creates player container, branches by provider, calls `createYouTubePlayer()` or `createVimeoPlayer()` |
| 8 | No provider SDK, iframe, or CDN resource loads before user clicks play | VERIFIED | view.js uses `sitchco.register()` for initialization (line 236), event listeners with `{ once: true }` (lines 217, 230); SDK URLs only appear inside click handlers; no top-level network calls |
| 9 | Wrapper dimensions remain stable when poster is replaced with player (no layout shift) | VERIFIED | view.js lines 160-161: `wrapper.style.width = wrapper.offsetWidth + 'px'` and `height` locked BEFORE `classList.add('sitchco-video--playing')` on line 164 |
| 10 | YouTube embeds use youtube-nocookie.com domain | VERIFIED | view.js line 56: `host: 'https://www.youtube-nocookie.com'` |
| 11 | Vimeo embeds include dnt:true parameter | VERIFIED | view.js line 96: `dnt: true` in Vimeo.Player constructor |
| 12 | Start time from URL parameters is respected for both providers | VERIFIED | view.js lines 113-152: `extractYouTubeStartTime()` handles `?t=`, `?start=` with h/m/s format; `extractVimeoStartTime()` handles `#t=`; both passed to player constructors (lines 181, 183) |
| 13 | Poster content and play icon are hidden after click | VERIFIED | view.js line 164: `wrapper.classList.add('sitchco-video--playing')`; style.css lines 144-149: `.sitchco-video--playing .sitchco-video__poster { display: none; }` and play button `display: none` |

**Score:** 10/10 truths verified (13 individual checks, grouped into 10 must-have truths across two plans)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `modules/VideoBlock/assets/images/svg-sprite/icon-youtube-play-dark.svg` | YouTube dark play icon | VERIFIED | 4 lines, correct shapes (rect 68x48 rx=12 + polygon), hardcoded fills rgba(0,0,0,0.8) + #fff |
| `modules/VideoBlock/assets/images/svg-sprite/icon-youtube-play-light.svg` | YouTube light play icon | VERIFIED | 4 lines, correct shapes, fills rgba(255,255,255,0.8) + #212121 |
| `modules/VideoBlock/assets/images/svg-sprite/icon-youtube-play-red.svg` | YouTube red play icon | VERIFIED | 4 lines, correct shapes, fill "red" (equivalent to #FF0000) + #fff |
| `modules/VideoBlock/assets/images/svg-sprite/icon-generic-play-dark.svg` | Generic dark play icon | VERIFIED | 4 lines, correct shapes (circle cx=34 cy=34 r=34 + polygon), fills rgba(0,0,0,0.8) + #fff |
| `modules/VideoBlock/assets/images/svg-sprite/icon-generic-play-light.svg` | Generic light play icon | VERIFIED | 4 lines, correct shapes, fills rgba(255,255,255,0.8) + #212121 |
| `modules/VideoBlock/blocks/video/render.php` | Server-side poster rendering with oEmbed caching | VERIFIED | 147 lines (min 80 required), oEmbed caching, poster fallback chain, play button, accessibility attrs |
| `modules/VideoBlock/blocks/video/style.css` | Frontend CSS for poster, play button, player | VERIFIED | 162 lines, includes poster, poster-img, play-button, placeholder-poster, playing-state, player, player iframe styles |
| `tests/Modules/VideoBlock/VideoBlockTest.php` | PHPUnit tests for poster rendering | VERIFIED | 362 lines, contains `test_render_with_oembed_thumbnail` plus 7 other poster tests |
| `modules/VideoBlock/blocks/video/view.js` | Click-to-play handler | VERIFIED | 238 lines (min 100 required), YouTube and Vimeo SDK loading, dimension locking, privacy settings |
| `modules/VideoBlock/blocks/video/view.asset.php` | viewScript dependency declaration | VERIFIED | Declares `sitchco/ui-framework` dependency |
| `modules/VideoBlock/blocks/video/block.json` | Updated with viewScript field | VERIFIED | Line 27: `"viewScript": "file:./view.js"` |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| render.php | oEmbed transient cache | `set_transient($cache_key, ...)` with `$cache_key = 'sitchco_voembed_' . md5($url)` | WIRED | Lines 25, 31: cache key created and used in set_transient; get_transient on line 26 |
| render.php | SVG sprite | `<use href="#icon-{name}">` | WIRED | Line 104: sprintf with `href="#icon-%s"` |
| render.php | InnerBlocks content | `$has_inner_blocks = !empty(trim($content))` | WIRED | Line 75: emptiness check on `$content` parameter |
| view.js | sitchco.register() | lifecycle hook for DOMContentLoaded | WIRED | Line 236: `sitchco.register(function initVideoBlocks() {...})` |
| view.js | sitchco.loadScript() | Promise-based SDK loading on first click | WIRED | Lines 41 (YouTube) and 82 (Vimeo): SDK URLs passed to loadScript |
| view.js | render.php wrapper div | `querySelectorAll('.sitchco-video')` + data attributes | WIRED | Line 237: queries `.sitchco-video[data-url]`, reads `dataset.*` throughout |
| view.js | YouTube IFrame API | `YT.Player` constructor with nocookie host | WIRED | Line 56: `host: 'https://www.youtube-nocookie.com'` |
| view.js | Vimeo Player SDK | `new Vimeo.Player()` with dnt:true | WIRED | Lines 93-97: `new Vimeo.Player(container, { id, autoplay, dnt: true })` |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| POST-01 | 02-01 | Server-side oEmbed renders `<img>` poster | SATISFIED | render.php lines 82-89, test `test_render_with_oembed_thumbnail` |
| POST-02 | 02-01 | InnerBlocks present renders InnerBlocks as poster | SATISFIED | render.php lines 75-78, test `test_render_innerblocks_as_poster` |
| POST-03 | 02-01 | No allowed block type restrictions on InnerBlocks | SATISFIED | editor.jsx line 411: `<InnerBlocks />` without `allowedBlocks` prop |
| POST-04 | 02-01 | Wrapper checks only whether InnerBlocks exist | SATISFIED | render.php line 75: `!empty(trim($content))` -- existence check only |
| POST-05 | 02-01 | Graceful fallback when oEmbed has no thumbnail | SATISFIED | render.php line 95: placeholder div, test `test_render_generic_placeholder` |
| INLN-01 | 02-02 | Click loads provider SDK | SATISFIED | view.js handlePlay -> createYouTubePlayer/createVimeoPlayer with loadYouTubeAPI/loadVimeoSDK |
| INLN-02 | 02-02 | Dimension locking on click | SATISFIED | view.js lines 160-161: offsetWidth/offsetHeight locked before DOM changes |
| INLN-03 | 02-02 | Poster and play icon hidden after click | SATISFIED | view.js line 164 + style.css lines 144-149 |
| INLN-04 | 02-02 | Iframe at 100% width/height | SATISFIED | style.css lines 152-161: `.sitchco-video__player` and iframe both 100% width/height |
| INLN-05 | 02-02 | Playback begins automatically | SATISFIED | view.js: YouTube `autoplay: 1` + `onReady: playVideo()` (lines 58, 67); Vimeo `autoplay: true` (line 95) |
| INLN-06 | 02-02 | Start time from URL respected | SATISFIED | view.js: extractYouTubeStartTime (lines 113-142), extractVimeoStartTime (lines 148-152), passed to constructors |
| INLN-07 | 02-02 | No provider resources before click | SATISFIED | SDK URLs only inside click-triggered functions; no top-level script/iframe injection |
| PRIV-01 | 02-01 | No browser requests to providers on load | SATISFIED | Server-side oEmbed only; view.js loads nothing until click |
| PRIV-02 | 02-02 | YouTube uses youtube-nocookie.com | SATISFIED | view.js line 56: `host: 'https://www.youtube-nocookie.com'` |
| PRIV-03 | 02-02 | Vimeo includes dnt:true | SATISFIED | view.js line 96: `dnt: true` |
| ACCS-01 | 02-01 | Play button is `<button>` with aria-label | SATISFIED | render.php lines 110-116, test `test_play_button_aria_label` |
| ACCS-02 | 02-01 | Play button keyboard focusable/activatable | SATISFIED | Native `<button>` is focusable; view.js lines 220-232: keydown handler for Enter/Space on wrapper |
| ACCS-03 | 02-01 | Poster mode wrapper has role/tabindex/aria-label | SATISFIED | render.php lines 133-137, tests confirm presence and absence |

**Orphaned requirements:** None -- all 18 phase requirements are covered by plan requirements.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| icon-youtube-play-red.svg | 2 | `fill="red"` instead of `fill="#FF0000"` per editor.jsx spec | Info | Visually identical; CSS `red` === `#FF0000`. Minor inconsistency with source of truth. |

### Human Verification Required

Plan 02-03 was designated as a human verification checkpoint but was auto-approved without actual browser testing. The following items require human verification in a real browser:

### 1. Poster Renders with oEmbed Thumbnail

**Test:** Visit a page with a video block containing a YouTube URL (no InnerBlocks). Verify thumbnail image displays with play icon overlay.
**Expected:** Provider thumbnail image visible with correctly positioned play icon from SVG sprite.
**Why human:** Visual rendering, image loading from oEmbed CDN, and SVG sprite display cannot be verified programmatically.

### 2. Zero Provider Requests Before Click

**Test:** Open DevTools Network tab, refresh page with video block. Filter for youtube.com, vimeo.com domains.
**Expected:** No requests to any video provider domain on page load.
**Why human:** Network waterfall analysis requires real browser DevTools.

### 3. Click-to-Play YouTube Inline

**Test:** Click the play button on a YouTube video block.
**Expected:** Poster disappears, YouTube IFrame API loads, video plays inline. In DevTools: first youtube.com request appears at click time. Iframe src contains "youtube-nocookie.com".
**Why human:** JavaScript runtime behavior, SDK loading, and iframe creation require live browser.

### 4. No Layout Shift

**Test:** Watch content below the video during click-to-play transition.
**Expected:** No visible jump or shift in surrounding page content.
**Why human:** CLS (Cumulative Layout Shift) requires visual observation of real rendering.

### 5. Keyboard Accessibility

**Test:** Refresh page, Tab to the play button. Press Enter or Space.
**Expected:** Play button receives visible focus ring. Activation triggers playback.
**Why human:** Focus management and keyboard interaction require real browser testing.

### 6. Start Time Support

**Test:** Use a YouTube URL with `?t=30` parameter. Click play.
**Expected:** Video begins playback at 30 seconds, not from the beginning.
**Why human:** Player seek behavior requires real SDK interaction.

### 7. InnerBlocks as Poster

**Test:** Create a video block with InnerBlocks content (e.g., a heading and paragraph). View on frontend.
**Expected:** InnerBlocks content shown as poster instead of oEmbed thumbnail.
**Why human:** WordPress block rendering pipeline requires live WP instance to verify end-to-end.

### 8. Vimeo Playback

**Test:** Visit a page with a Vimeo URL video block. Click play.
**Expected:** Vimeo Player SDK loads, video plays inline with dnt parameter.
**Why human:** Vimeo SDK loading and player creation require browser with network access.

### Gaps Summary

No code-level gaps found. All 18 requirement IDs are satisfied with substantive implementations. All artifacts exist, are non-stub, and are properly wired. All key links between components are verified.

The only outstanding concern is that Plan 02-03 (browser verification checkpoint) was auto-approved rather than tested by a human. The JavaScript runtime behavior (SDK loading, iframe creation, dimension locking, playback initiation) cannot be verified through code inspection alone. The code is structurally correct and follows established patterns (`sitchco.register()`, `sitchco.loadScript()`), but real browser testing is needed to confirm end-to-end functionality.

---

_Verified: 2026-03-09T20:12:23Z_
_Verifier: Claude (gsd-verifier)_
