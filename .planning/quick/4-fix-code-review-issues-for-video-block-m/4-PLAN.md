---
phase: quick-4
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - modules/VideoBlock/blocks/video/view.js
  - modules/VideoBlock/blocks/video/editor.jsx
  - modules/VideoBlock/VideoBlockRenderer.php
autonomous: true
requirements: [CR-FIX]
must_haves:
  truths:
    - "Modal opens for video blocks whose title starts with a digit"
    - "Closing modal during SDK loading does not leave audio playing"
    - "User-edited title persists when oEmbed fetch resolves"
    - "Double activation in inline mode creates only one player"
    - "Vimeo URLs with #t=1m30s start at 90 seconds"
    - "Inline poster-click mode suppresses child interactive elements"
    - "Non-Latin video titles produce a non-empty modalId"
  artifacts:
    - path: "modules/VideoBlock/blocks/video/view.js"
      provides: "Fixed Vimeo time parsing, modal close cancellation, double-play guard, poster click suppression"
    - path: "modules/VideoBlock/blocks/video/editor.jsx"
      provides: "Fixed stale closures, slugify fallback, removed dead AbortController guard"
    - path: "modules/VideoBlock/VideoBlockRenderer.php"
      provides: "Fixed modal ID mismatch, moved buildPlayButton after modal early return"
  key_links:
    - from: "VideoBlockRenderer.php data-modal-id"
      to: "ModalData.php constructor id normalization"
      via: "Both must apply identical digit-prefix logic"
      pattern: "modal-.*\\d"
    - from: "view.js handleModalHide"
      to: "view.js createYouTubePlayer/createVimeoPlayer onReady"
      via: "cancelled flag in modalPlayers entry"
      pattern: "entry\\.cancelled"
---

<objective>
Fix all code review issues for the video block: 7 bugs and 2 nitpicks across view.js, editor.jsx, and VideoBlockRenderer.php.

Purpose: Resolve modal ID mismatches, autoplay-after-close race conditions, stale closures, double play activation, Vimeo time parsing, inline poster click suppression, and slugify empty-string fallback.
Output: All three source files updated with targeted fixes.
</objective>

<execution_context>
@/Users/jstrom/.claude/get-shit-done/workflows/execute-plan.md
@/Users/jstrom/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@modules/VideoBlock/blocks/video/view.js
@modules/VideoBlock/blocks/video/editor.jsx
@modules/VideoBlock/VideoBlockRenderer.php
@modules/UIModal/ModalData.php

<interfaces>
<!-- ModalData.php constructor normalizes IDs that start with digits -->
From modules/UIModal/ModalData.php:
```php
public function __construct(string $id, private string $heading, private string $content, public ModalType $type)
{
    $id = sanitize_title($id);
    if (preg_match('/^\d/', $id)) {
        $id = 'modal-' . $id;
    }
    $this->id = $id;
}
```

<!-- modalPlayers Map entry shape in view.js -->
From modules/VideoBlock/blocks/video/view.js:
```js
// Maps modalId -> { player: SDKPlayer|null, provider: string, loading: boolean }
const modalPlayers = new Map();
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix view.js bugs (4 issues + modal close race)</name>
  <files>modules/VideoBlock/blocks/video/view.js</files>
  <action>
Fix five issues in view.js:

1. **Vimeo start time parsing (line 214-218):** Replace the regex `/t=(\d+)s?/` with a parser that handles `XmYs` notation. Parse optional `(\d+)m` and `(\d+)s` groups, compute `minutes * 60 + seconds`. Model after the existing `extractYouTubeStartTime` approach:
```js
function extractVimeoStartTime(url) {
    const hash = url.split('#')[1] || '';
    const mMatch = hash.match(/t=(?:\d+m)?/); // need t= anchor
    // Better approach:
    const tMatch = hash.match(/t=([\dhms]+)/);
    if (!tMatch) return 0;
    const t = tMatch[1];
    const mins = t.match(/(\d+)m/);
    const secs = t.match(/(\d+)s?$/);
    let seconds = 0;
    if (mins) seconds += parseInt(mins[1], 10) * 60;
    if (secs && !mins) seconds = parseInt(secs[1], 10);
    else if (secs) seconds += parseInt(secs[1], 10);
    return seconds;
}
```

2. **Modal close before player ready (lines 262-266):** Add a `cancelled` flag to the modalPlayers entry. In `handleModalHide`, when `!entry.player` but `entry.loading` is true, set `entry.cancelled = true` and return. In `createYouTubePlayer` onReady callback (line 93-105), check `entry.cancelled` before calling `playVideo()` -- if cancelled, call `pauseVideo()` instead and clear the cancelled flag. In `createVimeoPlayer` ready callback (line 155-168), do the same: check `entry.cancelled`, if true call `player.pause()` instead of letting autoplay continue, and clear the flag. In `handleModalShow` (line 282), when resuming or first-opening, clear `entry.cancelled = false`. This preserves the player instance (no container destruction) so reopen still works.

3. **Double play activation (lines 410-425):** Add an early return guard at the top of `handlePlay`: `if (wrapper.classList.contains('sitchco-video--playing')) return;`. This prevents duplicate player creation since the class is added on first activation (line 333).

4. **Inline poster click suppression (lines 410-414):** When `clickBehavior === 'poster'` (entire wrapper is click target), add `pointer-events: none` to all child elements of the wrapper so that links/buttons inside InnerBlocks don't trigger navigation. Do this in `initVideoBlock` for the poster-click inline case. Add a style: `wrapper.querySelector('.sitchco-video__poster').style.pointerEvents = 'none';` -- actually, more robustly, at the point where the click handler is attached for poster mode, set the poster div's `pointerEvents = 'none'` to prevent child interactive elements from intercepting. The wrapper itself keeps pointer-events so the click handler fires on it.

  Specifically, in the inline poster-click branch (around line 405), after `clickTarget = wrapper;`, add:
  ```js
  const posterEl = wrapper.querySelector('.sitchco-video__poster');
  if (posterEl) posterEl.style.pointerEvents = 'none';
  ```

5. **handleModalHide full fix (lines 262-266):** Update the early return to handle the cancellation case as described in item 2 above. The updated function should be:
```js
function handleModalHide(modal) {
    const entry = modalPlayers.get(modal.id);
    if (!entry) return;
    if (!entry.player) {
        if (entry.loading) {
            entry.cancelled = true;
        }
        return;
    }
    if (entry.provider === 'youtube') {
        entry.player.pauseVideo();
    } else {
        entry.player.pause();
    }
}
```
  </action>
  <verify>
    <automated>cd /Users/jstrom/Projects/web/roundabout/public && make build 2>&1 | tail -5</automated>
  </verify>
  <done>All five view.js issues fixed: Vimeo time parsing handles XmYs, modal close sets cancellation flag checked in onReady, handlePlay has double-activation guard, inline poster-click suppresses child pointer events. Build succeeds.</done>
</task>

<task type="auto">
  <name>Task 2: Fix editor.jsx bugs (3 issues)</name>
  <files>modules/VideoBlock/blocks/video/editor.jsx</files>
  <action>
Fix three issues in editor.jsx:

1. **Stale closure in oEmbed fetch (line 122-186):** The `useEffect` callback closes over `videoTitle` and `modalId` but depends only on `[url]`. Fix by tracking current values in refs. Add two refs near the existing refs (line 75-76):
```jsx
const videoTitleRef = useRef(videoTitle);
const modalIdRef = useRef(modalId);
```
Add sync effects to keep refs current:
```jsx
videoTitleRef.current = videoTitle;
modalIdRef.current = modalId;
```
(Place these assignments right after the ref declarations or at the top of the component body, OUTSIDE the useEffect.)

In the `.then()` callback (line 146-167), replace `videoTitle` with `videoTitleRef.current` and `modalId` with `modalIdRef.current`:
```js
if (!videoTitleRef.current || videoTitleRef.current === prevTitle) {
    updates.videoTitle = response.title;
}
if (!modalIdRef.current || modalIdRef.current === slugify(prevTitle || '')) {
    updates.modalId = slugify(response.title);
}
```

Also move `setIsLoading(true)` from line 130 to inside the setTimeout callback (right before the abort/fetch logic), so the loading indicator only shows after the debounce. And clear oembedData on URL change by adding `setOembedData(null)` at the top of the effect (before the setTimeout), so stale preview data doesn't linger during debounce.

2. **slugify() empty string fallback (lines 32-37):** After the slugify logic, if the result is empty, return a fallback. The fallback needs the video ID which isn't available inside `slugify()` itself. Instead, fix the TWO call sites:
   - Line 160: `updates.modalId = slugify(response.title) || 'video';` -- but 'video' is too generic. Better: extract videoId from the URL at that point. Actually, the simplest approach: modify `slugify()` to accept an optional fallback parameter:
   ```js
   function slugify(text, fallback) {
       const result = text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
       return result || fallback || '';
   }
   ```
   Then at the two call sites in the `.then()` callback where `slugify(response.title)` is used, extract a videoId from the URL for fallback. Add a small helper or inline it:
   ```js
   const videoIdFromUrl = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})|vimeo\.com\/(?:video\/)?(\d+)/);
   const videoIdFallback = videoIdFromUrl ? (videoIdFromUrl[1] || videoIdFromUrl[2]) : '';
   ```
   Place this extraction inside the `.then()` callback, then use `slugify(response.title, videoIdFallback)` for the modalId assignment. This mirrors PHP where `sanitize_title()` already handles non-Latin, and the videoId is always ASCII.

   Also update the `onChange` handler for Modal ID (line 302): `onChange={(value) => setAttributes({ modalId: slugify(value) })}` -- this doesn't need the fallback since user is typing interactively.

3. **Remove AbortController dead code guard (line 139):** Replace:
   ```js
   const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
   ```
   with:
   ```js
   const controller = new AbortController();
   ```
   And update the signal usage from `signal: controller ? controller.signal : undefined` to just `signal: controller.signal`.
  </action>
  <verify>
    <automated>cd /Users/jstrom/Projects/web/roundabout/public && make build 2>&1 | tail -5</automated>
  </verify>
  <done>All three editor.jsx issues fixed: stale closures resolved via refs, slugify falls back to video ID for non-Latin titles, dead AbortController guard removed. Build succeeds.</done>
</task>

<task type="auto">
  <name>Task 3: Fix VideoBlockRenderer.php (modal ID mismatch + nitpick)</name>
  <files>modules/VideoBlock/VideoBlockRenderer.php</files>
  <action>
Fix two issues in VideoBlockRenderer.php:

1. **Modal ID mismatch for digit-leading IDs (line 208):** The `data-modal-id` attribute on the wrapper stores the raw `$modal_id` value, but `ModalData`'s constructor normalizes it (prepends "modal-" for digit-leading IDs). The JS `getElementById()` lookup uses `data-modal-id` to find the `<dialog>`, so it must match the final normalized ID.

   Fix: After the `ModalData` is created (line 200), read back the normalized ID and use that for the data attribute. Change the code around lines 200-208 to:
   ```php
   $modalData = new ModalData($modal_id, $video_title, $modal_content, ModalType::VIDEO);
   $uiModal->loadModal($modalData);

   // Modal-only: render nothing on page
   if ($display_mode === 'modal-only') {
       return '';
   }

   // Modal mode: use normalized ID (ModalData may prefix digit-leading IDs)
   $wrapper_attrs['data-modal-id'] = $modalData->id();
   ```
   This ensures the wrapper's `data-modal-id` matches the `<dialog>` element's actual `id`.

2. **buildPlayButton called before modal-only early return (line 138):** Move the `$play_button = self::buildPlayButton(...)` call from line 138 to AFTER the modal-only early return (after line 205). Place it right before the Phase 5 accessibility section (around line 211). This avoids computing and discarding the play button HTML for modal-only blocks.
  </action>
  <verify>
    <automated>cd /Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core && ddev test-phpunit 2>&1 | tail -20</automated>
  </verify>
  <done>Modal ID mismatch fixed -- data-modal-id now uses ModalData's normalized ID. buildPlayButton moved after modal-only early return to avoid wasted computation.</done>
</task>

</tasks>

<verification>
All three source files build without errors (`make build`). PHP tests pass (`ddev test-phpunit`). The fixes are isolated to the specific lines identified in the code review.
</verification>

<success_criteria>
- `make build` succeeds (JS compilation)
- `ddev test-phpunit` passes (PHP)
- All 7 bugs addressed: modal ID mismatch, autoplay-on-close race, stale closures, double play, Vimeo time parsing, poster click suppression, slugify fallback
- Both nitpicks addressed: dead AbortController guard removed, buildPlayButton moved after early return
</success_criteria>

<output>
After completion, create `.planning/quick/4-fix-code-review-issues-for-video-block-m/4-SUMMARY.md`
</output>
