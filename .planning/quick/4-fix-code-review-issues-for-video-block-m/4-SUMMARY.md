---
phase: quick-4
plan: 01
subsystem: VideoBlock
tags: [bug-fix, video, modal, editor, javascript, php]
dependency_graph:
  requires: []
  provides: [CR-FIX]
  affects: [view.js, editor.jsx, VideoBlockRenderer.php]
tech_stack:
  added: []
  patterns:
    - cancelled flag in modalPlayers map for async race guard
    - useRef for stale closure avoidance in async React effects
    - slugify fallback parameter for non-Latin title handling
    - ModalData.id() readback to get normalized ID
key_files:
  created: []
  modified:
    - modules/VideoBlock/blocks/video/view.js
    - modules/VideoBlock/blocks/video/editor.jsx
    - modules/VideoBlock/VideoBlockRenderer.php
decisions:
  - extractVimeoStartTime now mirrors extractYouTubeStartTime with full h/m/s parsing
  - cancelled flag stored in modalPlayers entry avoids destroying/recreating player for reopen
  - videoTitleRef/modalIdRef sync on every render (outside useEffect) as canonical stale-closure pattern
  - slugify fallback extracts video ID from URL using same regex as PHP extractVideoId
  - buildPlayButton moved after modal-only early return as pure optimization (no behavior change for non-modal-only blocks)
metrics:
  duration: 8min
  completed: "2026-03-10"
  tasks: 3
  files: 3
---

# Quick Task 4: Fix Code Review Issues for Video Block Summary

**One-liner:** Fixes 7 bugs and 2 nitpicks across view.js, editor.jsx, and VideoBlockRenderer.php: Vimeo XmYs time parsing, modal-close race condition via cancelled flag, double-play guard, inline poster pointer-event suppression, stale closures via refs, slugify non-Latin fallback, dead AbortController guard removal, modal ID digit-prefix mismatch, and misplaced buildPlayButton call.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Fix view.js bugs (4 issues + modal close race) | 61eb25d | modules/VideoBlock/blocks/video/view.js |
| 2 | Fix editor.jsx bugs (3 issues) | 36bd9f4 | modules/VideoBlock/blocks/video/editor.jsx |
| 3 | Fix VideoBlockRenderer.php (modal ID mismatch + nitpick) | 6619bfe | modules/VideoBlock/VideoBlockRenderer.php |

## What Was Built

### view.js (61eb25d)

Five bugs fixed:

1. **Vimeo start time parsing** — `extractVimeoStartTime` now mirrors `extractYouTubeStartTime`: parses `h`, `m`, `s` groups in the URL hash so `#t=1m30s` correctly resolves to 90 seconds.

2. **Modal close race condition** — Added `entry.cancelled` flag to the `modalPlayers` map entry. `handleModalHide` sets `entry.cancelled = true` when the modal is closed while the SDK is still loading. Both `createYouTubePlayer` (onReady) and `createVimeoPlayer` (ready promise) check `entry.cancelled` before calling `playVideo()`/`play()` — if set, they pause instead and clear the flag so reopen works normally.

3. **handleModalShow cancellation clear** — On modal reopen, `entry.cancelled` is explicitly set to `false` so a previously-cancelled player resumes correctly.

4. **Double play activation guard** — `handlePlay` returns early if `wrapper.classList.contains('sitchco-video--playing')` to prevent duplicate player creation on rapid clicks.

5. **Inline poster click suppression** — In the poster-click branch of `initVideoBlock`, `posterEl.style.pointerEvents = 'none'` prevents child interactive elements (links, buttons in InnerBlocks) from intercepting the wrapper click handler.

### editor.jsx (36bd9f4)

Three bugs fixed:

1. **Stale closures in oEmbed fetch** — Added `videoTitleRef` and `modalIdRef` refs updated on every render. The async `.then()` callback reads `videoTitleRef.current` and `modalIdRef.current` instead of the stale closure values of `videoTitle` and `modalId`.

2. **slugify() empty-string fallback** — Added optional `fallback` parameter. Call site in the `.then()` callback extracts a video ID from the URL and passes it as the fallback, so non-Latin titles (that slugify to empty string) produce a meaningful modal ID.

3. **Dead AbortController guard removed** — `typeof AbortController !== 'undefined' ? new AbortController() : null` replaced with `new AbortController()` and `controller.signal` used directly (no conditional).

Bonus improvements (Rule 2):
- `setIsLoading(true)` moved inside the `setTimeout` so the spinner only shows after the debounce delay.
- `setOembedData(null)` called immediately on URL change to clear stale preview during debounce.

### VideoBlockRenderer.php (6619bfe)

Two issues fixed:

1. **Modal ID mismatch** — `new ModalData(...)` captured as `$modalData`, then `$wrapper_attrs['data-modal-id'] = $modalData->id()` reads back the normalized ID. `ModalData` prepends `"modal-"` when the sanitized ID starts with a digit; the wrapper must use the same ID to match the `<dialog>` element.

2. **buildPlayButton nitpick** — `self::buildPlayButton(...)` call moved from before the modal-only early return to after it, avoiding wasted computation for modal-only blocks.

## Deviations from Plan

### Auto-applied improvements

**1. [Rule 2 - Missing functionality] editor.jsx debounce UX improvements**
- **Found during:** Task 2
- **Issue:** Plan specified moving `setIsLoading(true)` inside setTimeout; also noted stale `setOembedData` during debounce
- **Fix:** Added both improvements — spinner delay and immediate `setOembedData(null)` on URL change
- **Files modified:** modules/VideoBlock/blocks/video/editor.jsx
- **Commit:** 36bd9f4

**2. [Rule 3 - Blocking issue] Pre-commit hook path error on first commit attempt**
- **Found during:** Task 2 commit
- **Issue:** Hook invoked from wrong directory, produced MODULE_NOT_FOUND for @sitchco/cli
- **Fix:** Re-ran `pnpm sitchco pre-commit` from correct cwd manually, then re-staged and committed
- **Files modified:** None (tooling issue, not code)

## Verification

- `make build` succeeds (JS compilation) for both Tasks 1 and 2
- `ddev test-phpunit` shows Tests: 331, Assertions: 765, Errors: 9 — all 9 errors are pre-existing Cloudinary module errors unrelated to VideoBlock; zero VideoBlock test failures

## Self-Check: PASSED

- modules/VideoBlock/blocks/video/view.js exists and is modified
- modules/VideoBlock/blocks/video/editor.jsx exists and is modified
- modules/VideoBlock/VideoBlockRenderer.php exists and is modified
- Commits 61eb25d, 36bd9f4, 6619bfe all present in git log
