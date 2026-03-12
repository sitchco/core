## Issues

### `SvgSprite.php:68-89` — SVG sprite play icons missing in dev mode

`VideoBlockRenderer::buildPlayButton()` emits `<use href="#icon-youtube-play">` references, but `SvgSprite::buildSpriteContents()` skips the `wp_body_open` sprite injection in dev mode. The `renderIconSvg()` raw-file fallback is never called by VideoBlockRenderer. Play icons render as empty/invisible SVGs on the frontend during local development.

```php
// VideoBlockRenderer always emits <use> references:
<use href="#icon-youtube-play">
// But SvgSprite dev branch never injects the sprite sheet
```

**Verify:** Run the dev server and view any page with a video block — play icon will be missing.

---

### `VideoBlockRenderer.php:259-262` — Nested interactive elements and keyboard accessibility in poster-click mode

The wrapper div gets `role="button"` + `tabindex="0"` while containing a child `<button>` with the same `aria-label` — ARIA 1.2 prohibits interactive descendants inside `role=button`. Additionally, only `pointerEvents: 'none'` is applied to the poster; keyboard users can still Tab into interactive InnerBlocks children.

```php
// Wrapper: role="button" tabindex="0" aria-label="Play video: Title"
//   └─ <button aria-label="Play video: Title">  ← nested interactive element
```

**Verify:** Screen reader announces button-inside-button. Tab through a block with custom poster links — inner elements are focusable.

---

### `style.css:320` — Video modal missing mobile padding override

The video modal sets `--modal-container-padding: 0` but never overrides `--modal-container-mobile-padding`. Below 576px, the default `1rem` gutter persists around the video player.

```css
/* Current — only zeros desktop token */
.sitchco-modal--video {
  --modal-container-padding: 0;
  /* Missing: --modal-container-mobile-padding: 0; */
}
```

**Verify:** Open any video modal on a viewport below 576px — visible padding around the player.

---

### `view.js:228-241` — YouTube API load failure leaves player stuck forever

`loadYouTubeAPI()` creates a Promise with only a resolve path via `onYouTubeIframeAPIReady`. The `loadScript()` rejection is discarded, so network/CSP failures leave the promise pending indefinitely. The `.catch()` in `createYouTubePlayer()` is dead code on load failure. Vimeo does not have this bug.

```js
// loadScript() rejection is discarded — no reject path
new Promise(function (resolve) {
  window.onYouTubeIframeAPIReady = resolve;
  sitchco.loadScript('...'); // return value thrown away
});
```

**Verify:** Block `youtube.com/iframe_api` in DevTools, then click play — blank area with no retry.

---

### `style.css:325-327` — Video modal backdrop snaps instead of fading

The video modal overrides backdrop color but lacks the `@starting-style` rule that other modal variants use for the fade-in transition.

```css
/* Missing @starting-style — backdrop snaps to 85% opacity */
.sitchco-modal--video::backdrop {
  background: rgb(0 0 0 / 0.85);
}
/* Needs: .sitchco-modal--video[open]::backdrop {
     @starting-style { background: transparent; }
   } */
```

**Verify:** Click any modal-mode video block — backdrop appears instantly vs. fading.

---

### `style.css:19-25` — Dead `__play-icon` class likely removed from admin, losing `pointer-events: none`

The `.sitchco-video__play-icon` class is dead CSS (never emitted in any DOM output — replaced by `__play-button`). This likely means the admin editor lost the `pointer-events: none` rule that was on `__play-icon`, which may need to be re-applied to `__play-button` via the nested `.editor-styles-wrapper &` pattern.

```css
/* Dead rule — class never emitted */
.sitchco-video__play-icon {
  pointer-events: none; /* ← was this needed in the editor? */
}
```

**Verify:** In the block editor, check if the play button intercepts click events that should pass through to the block selection layer.

---

## Questions

### `design.md:334` — PHP filter name uses underscores vs design doc hyphens

Implementation generates `sitchco/video/play_icon_svg` (underscores via HookName framework) while the design doc specifies `sitchco/video/play-icon/svg` (hyphens with extra separator). Needs review for consistency with the rest of the platform's hook naming conventions — either update the docs or the code.

**Verify:** Check other `hookName()` usages across the platform to determine the canonical naming pattern.

---

## Suggestions

### `editor.jsx:477-484, VideoBlockRenderer.php:107` — Provider attribute not derived at render time

The editor correctly sets `provider` alongside `url`, but the PHP renderer has no fallback derivation. Programmatically-created or imported blocks with `url` but no `provider` would silently degrade. Adding a `detectProvider()` call in PHP would make blocks self-healing.

**Verify:** Create a video block via `wp_insert_post` with raw block markup containing `url` but no `provider` attribute.

---

### `editor.jsx:459, 259-314` — Editor renders play icon during loading and extra UI in modal-only mode

Play icon condition lacks `!isLoading` guard, producing spinner + play icon overlay. Modal-only mode renders loading/error/empty states above the compact placeholder because only `renderPreview()` checks `isModalOnly`.

**Verify:** Add a URL in the editor and watch for the brief spinner + play icon overlap. Switch to modal-only mode and observe loading state above the placeholder.

---

### `VideoBlockRenderer.php:307-308` — Play button missing `type="button"`

The `<button>` defaults to `type="submit"` per HTML spec. Add `type="button"` as a zero-cost best practice.

**Verify:** Trivial — inspect the rendered HTML.

---

### `VideoBlockTest.php` — Test suite missing Vimeo coverage and render pipeline fidelity

No tests for Vimeo video ID extraction, thumbnail upgrade (including portrait branch), or empty `modalId` fallback. The `renderBlock()` helper bypasses WordPress's block serialization, which is why the Save() double-wrapping question wasn't caught by tests.

**Verify:** Run the test suite and check coverage for Vimeo-specific code paths.

---

### `design.md:296,325` — Update design doc hook names to match implementation

Update the design doc to document: `video-request-pause` as the external command hook, `video-pause` as the lifecycle/analytics event, and the event/command separation pattern. The implementation's separation is better design than the doc's single `video-pause` for both purposes.

**Verify:** Diff design.md hook table against actual `doAction`/`addAction` calls in view.js.

---

## Nitpicks

### `VideoBlockRenderer.php:218-220` — Misleading "backward compatibility" comment

The `$GLOBALS['SitchcoContainer']` fallback has a "backward compatibility" comment but this is a new class with no prior callers. Comment should say "defensive fallback" if kept.

### `editor.jsx:235, view.js:665-669` — Minor code contract gaps

useEffect dependency array `[url]` works because `provider` is set atomically, but doesn't satisfy exhaustive-deps. `modalPlayers.set()` omits `cancelled` property, relying on `undefined` being falsy. Both work correctly but could be more explicit.
