## Issues

### `VideoBlockRenderer.php:208` — Modal ID mismatch for digit-leading IDs

When a modal ID starts with a digit (e.g., `"100-best-films"`), the wrapper's `data-modal-id` stores the raw value, but `ModalData`'s constructor prepends `"modal-"`, so the `<dialog>` gets `id="modal-100-best-films"`. The JS `getElementById()` lookup fails and the modal never opens.

```php
// VideoBlockRenderer.php:208
$wrapper_attrs['data-modal-id'] = $modal_id;
// But ModalData.php:13-16 normalizes: id="modal-100-best-films"
```

**Verify:** Create a video block with a title starting with a digit. Click the poster — modal should open.

---

### `view.js:262-266` — Modal close before player ready leaves autoplay running

`handleModalHide()` returns immediately when `!entry.player`. During SDK loading, `player` is `null`, so a fast close performs no cleanup. When the SDK resolves, `onReady` unconditionally calls `playVideo()` (YouTube) or the player is created with `autoplay: true` (Vimeo) inside the closed dialog — invisible audio plays with no UI to stop it.

```js
// view.js:262-266
if (!entry || !entry.player) return; // exits without cancellation
```

Fix should use a cancellation flag checked in `onReady`/Vimeo creation — do not destroy the player container (preserve playback position for reopen).

**Verify:** Open a modal on a slow connection, close within 1-3 seconds. No audio should play from the closed dialog.

---

### `editor.jsx:186` — oEmbed fetch lifecycle overwrites manual edits

The `useEffect` depends only on `[url]`, so `videoTitle` and `modalId` in the `.then()` callback are stale closures. If the user edits the title during the 500ms debounce + fetch, the callback sees the old value, evaluates the overwrite-protection check incorrectly, and replaces the user's edit. Violates spec A2.

```js
// editor.jsx:156 — stale closure comparison
if (!videoTitle || videoTitle === prevOembedTitleRef.current) {
    setAttributes({ videoTitle: response.title }); // overwrites manual edit
}
```

Fix: track latest values in refs (`videoTitleRef.current = videoTitle`), check refs in the callback. Secondary: move `setIsLoading(true)` inside the debounce callback; clear `oembedData` on URL change.

**Verify:** Enter a URL, immediately type a custom title during the spinner. Title should persist after fetch resolves.

---

### `view.js:410-425` — Double play activation in inline mode

Click and keyboard handlers are registered independently with `{ once: true }` on the same wrapper. Firing one doesn't remove the other. `handlePlay()` has no activation guard and creates a new player container unconditionally, so keyboard-then-click creates duplicate players. Low practical likelihood but a concrete bug.

```js
// view.js:327-343 — no guard
function handlePlay(wrapper) {
    // unconditionally creates player container
    const playerContainer = document.createElement('div');
    wrapper.appendChild(playerContainer);
}
```

Fix: add `if (wrapper.classList.contains('sitchco-video--playing')) return` at the top of `handlePlay()`.

**Verify:** Tab-focus a poster, press Enter, then click. Only one player should be created.

---

### `view.js:214-218` — Vimeo start time parsing broken for minute notation

`extractVimeoStartTime` uses `/t=(\d+)s?/` which captures `1` from `#t=1m30s` instead of computing 90. The design spec (scenario I3) explicitly lists `#t=1m0s` as a supported format. The YouTube counterpart correctly handles `XhYmZs`.

```js
// view.js:216 — only handles plain seconds
const match = hash.match(/t=(\d+)s?/);
// #t=1m30s -> captures "1", returns 1 instead of 90
```

Fix: parse optional `(\d+)m` and `(\d+)s` groups, compute `minutes * 60 + seconds`.

**Verify:** Embed a Vimeo URL with `#t=1m30s`. Video should start at 90 seconds.

---

### `view.js:410-414` — Inline poster click doesn't suppress interactive content

In inline poster-click mode, the handler omits the event parameter, so `preventDefault()` is never called. The design spec (A7) requires "all child interactive elements are suppressed" in entire-poster mode. If InnerBlocks contain links or buttons, clicking triggers both navigation and play.

```js
// view.js:412 — no event parameter
function () { handlePlay(wrapper); }
// vs modal mode at line 379: function (e) { e.preventDefault(); ... }
```

Fix: add CSS `pointer-events: none` to poster children when in poster-click mode.

**Verify:** Add a linked image as a custom poster in inline mode. Clicking should trigger play only, not navigate.

---

### `editor.jsx:32-37` — slugify() produces empty strings for non-Latin input

JS `slugify()` strips all non-ASCII characters. For CJK, Arabic, or emoji titles, the result is an empty string. Empty `modalId` creates `<dialog id="">` (invalid HTML), breaks `getElementById`, and re-triggers auto-population on every fetch.

```js
// editor.jsx:32-37
function slugify(str) {
    return str.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}
// slugify("...") -> "", slugify("...") -> ""
```

Fix: add a non-empty fallback (e.g., use video ID) or adopt transliteration.

**Verify:** Create a video block with a non-Latin title. The modalId should be non-empty and the modal should open.

---

## Suggestions

### `VideoBlockRenderer.php:196-198` — Dead `$GLOBALS` fallback and inaccurate docstring

The `$GLOBALS['SitchcoContainer']` fallback is unreachable — the only caller always passes `$uiModal`. The docstring claiming "pure functions" is inaccurate given DB I/O and side effects.

**Verify:** Confirm `render.php` always passes `$uiModal` as 4th parameter.

---

### `editor.jsx:403-409` — Save function serializes extra wrapper

`Save()` wraps `InnerBlocks.Content` in `<div {...useBlockProps.save()}>`, producing a nested duplicate `wp-block-sitchco-video` div inside the poster HTML. Functionally harmless but unnecessary markup.

**Verify:** Inspect rendered HTML of a block with InnerBlocks — look for nested `wp-block-sitchco-video` divs.

---

### `view.js:42-56` — YouTube API promise never rejects

`ytAPIPromise` has no `reject` parameter. `sitchco.loadScript()`'s rejection is discarded. If the YouTube CDN is unreachable, the promise hangs forever, the poster is already hidden, and the user sees a black box with no recovery. Wire `loadScript()`'s rejection and optionally add a timeout.

**Verify:** Block the YouTube CDN URL and attempt playback. Should show an error state, not a permanent black box.

---

### `editor.jsx:20-24` / `VideoBlockRenderer.php:64-78` — Provider and URL validation gaps

The editor broadly matches any `youtube.com/` or `vimeo.com/` URL, but the runtime extractor only handles specific patterns. Playlist, channel, and `live/` URLs get provider-tagged but produce empty video IDs, resulting in a broken player on click. Tighten editor detection, expand extraction regexes, or add an empty-video-ID guard.

**Verify:** Paste a YouTube playlist URL. Block should either reject it or handle it gracefully.

---

### `editor.jsx:66` — Editor preview missing `sitchco-video` class

`useBlockProps()` doesn't include `className: 'sitchco-video'`, so CSS selectors scoped under `.sitchco-video` (provider theming, poster styles) don't apply in the editor. Play icon positioning works via inline styles, but other styling is missed.

**Verify:** Compare editor preview styling with frontend output — provider-specific colors and poster styles should match.

---

### `VideoBlockTest.php` — Test coverage gaps

Zero Vimeo tests; YouTube ID extraction only tests `watch?v=` format; oEmbed only tests successful cache-miss; modal assertions use loose substring matching that passes with empty values; auto-derive modal ID fallback path is never exercised.

<details>
<summary>Priority additions</summary>

- Vimeo provider tests (ID extraction, thumbnail rewrite, play icon)
- Additional YouTube URL formats (`youtu.be`, `/embed/`, `/shorts/`)
- Exact-value modal data attribute assertions
- Empty `modalId` auto-derive path

</details>

**Verify:** Run test suite after additions — new tests should pass against current code (except where they reveal the bugs listed above).

---

## Nitpicks

### `editor.jsx:139` — AbortController existence guard is dead code

Unnecessary `typeof AbortController !== 'undefined'` check — baseline in all supported browsers since 2019.

### `VideoBlockRenderer.php:138` — `buildPlayButton()` called before modal-only early return

For modal-only blocks, the play button is built and then discarded. Moving the call after the early return avoids a wasted `sprintf`.
