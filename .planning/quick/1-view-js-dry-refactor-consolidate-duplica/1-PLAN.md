---
phase: quick
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - modules/VideoBlock/blocks/video/view.js
autonomous: true
requirements: [CR-02-1, CR-02-3, CR-02-10]
must_haves:
  truths:
    - "YouTube and Vimeo player creation each use a single function for both inline and modal modes"
    - "Click and keyboard event binding uses a shared helper instead of duplicated blocks"
    - "No var declarations remain in the file -- all replaced with const/let"
    - "All existing behavior is preserved: inline play, modal play, pause on close, resume on reopen"
  artifacts:
    - path: "modules/VideoBlock/blocks/video/view.js"
      provides: "Refactored video player frontend script"
      contains: "function createYouTubePlayer"
  key_links:
    - from: "createYouTubePlayer"
      to: "handlePlay, handleModalShow"
      via: "modalId parameter (null for inline, string for modal)"
      pattern: "createYouTubePlayer\\(.*modalId"
    - from: "bindPlayTrigger"
      to: "initVideoBlock"
      via: "shared event binding helper"
      pattern: "bindPlayTrigger\\("
---

<objective>
DRY-refactor view.js to consolidate duplicated player creation functions, extract shared event binding, and modernize var to const/let.

Purpose: Address code review items #1 (High), #3 (Medium), and #10 (Medium) from 02-view-js-refactor.md. Reduces ~430 lines of code with significant duplication to a cleaner, more maintainable structure while preserving all existing behavior.

Output: Refactored view.js with identical runtime behavior.
</objective>

<execution_context>
@/Users/jstrom/.claude/get-shit-done/workflows/execute-plan.md
@/Users/jstrom/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@modules/VideoBlock/blocks/video/view.js
@modules/VideoBlock/code-review/02-view-js-refactor.md

<interfaces>
<!-- The file uses these external APIs that must be preserved exactly: -->

sitchco.hooks.addAction('ui-modal-show', handleModalShow, 20, 'video-block');
sitchco.register(function initVideoBlocks() { ... });
sitchco.loadScript(id, url);  // Returns Promise, deduplicates loads
sitchco.hooks.doAction('ui-modal-show', modal);

<!-- YouTube IFrame API: -->
new YT.Player(container, { videoId, host, playerVars, events: { onReady } })
player.playVideo() / player.pauseVideo()

<!-- Vimeo Player SDK: -->
new Vimeo.Player(container, { id, autoplay, dnt })
player.play() / player.pause() / player.ready() / player.setCurrentTime()

<!-- DOM contract (data attributes read from rendered HTML): -->
wrapper.dataset.displayMode   // 'inline' | 'modal' | 'modal-only'
wrapper.dataset.clickBehavior  // 'icon' | 'poster'
wrapper.dataset.modalId
wrapper.dataset.provider       // 'youtube' | 'vimeo'
wrapper.dataset.url
wrapper.dataset.videoId
playerContainer.dataset.provider / .dataset.videoId / .dataset.url
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Consolidate player creation, extract event binding helper, replace var with const/let</name>
  <files>modules/VideoBlock/blocks/video/view.js</files>
  <action>
Refactor view.js addressing all three code review items. All changes are within this single file. Preserve all existing runtime behavior exactly.

**Item #1 -- Consolidate player creation (lines 63-198):**

Merge `createYouTubePlayer` + `createModalYouTubePlayer` into a single `createYouTubePlayer(container, videoId, startTime, modalId)` where `modalId` is optional (defaults to `null`). When `modalId` is provided:
- Create a wrapper div with class `sitchco-video__player` and append to container
- Use the wrapper div as the YT.Player target instead of container directly
- In onReady: update the `modalPlayers` entry (set player, loading=false), add `--ready` class to container
When `modalId` is null (inline mode): behave exactly as the current `createYouTubePlayer` does (use container directly, no modalPlayers interaction, no --ready class).

Apply the same pattern to merge `createVimeoPlayer` + `createModalVimeoPlayer` into a single `createVimeoPlayer(container, videoId, startTime, modalId)`. Same conditional logic: when `modalId` is provided, create wrapper div, store in modalPlayers on ready, add --ready class. When null, behave as current inline version.

Update call sites:
- `handlePlay()` calls `createYouTubePlayer(container, videoId, startTime)` (no modalId -- inline)
- `handleModalShow()` calls `createYouTubePlayer(container, videoId, startTime, modalId)` (with modalId -- modal)
- Same pattern for Vimeo calls

**Item #3 -- Consolidate event binding (lines 332-405):**

Extract a helper function:
```
function bindPlayTrigger(element, callback, options) {
    element.addEventListener('click', function (e) {
        e.preventDefault();
        callback();
    }, options || {});
}
```

In `initVideoBlock()`, replace the duplicated click+keyboard patterns for both modal and inline modes:
- Modal mode: `bindPlayTrigger(modalClickTarget, function() { sitchco.hooks.doAction('ui-modal-show', modal); })`
- Inline mode: `bindPlayTrigger(clickTarget, function() { handlePlay(wrapper); }, { once: true })`

For keyboard handling, extract a similar pattern or inline it concisely. The key insight: both branches check `wrapper.getAttribute('role') === 'button'` and then add a keydown listener for Enter/Space. Extract:
```
function bindKeyboardTrigger(element, callback, options) {
    if (element.getAttribute('role') !== 'button') return;
    element.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            callback();
        }
    }, options || {});
}
```

IMPORTANT for inline mode: the click handler currently does NOT call `e.preventDefault()` -- it just calls `handlePlay(wrapper)`. Preserve that behavior. Only the modal click handler calls `e.preventDefault()`. Adjust `bindPlayTrigger` signature to accept a `preventDefault` flag, or keep click handlers slightly different. Choose the clearest approach -- the goal is reducing duplication, not forcing everything through one function if it hurts readability.

IMPORTANT for inline mode: click uses `{ once: true }` but modal does NOT. Ensure this distinction is preserved.

IMPORTANT for keyboard: inline uses `{ once: true }` but modal does NOT. The wrapper is the keyboard target in both cases (it has `role="button"`). Preserve this.

**Item #10 -- Replace var with const/let:**

Replace every `var` with `const` or `let` as appropriate throughout the entire file:
- `var ytAPIPromise = null` -> `let ytAPIPromise = null` (reassigned later)
- `var modalPlayers = new Map()` -> `const modalPlayers = new Map()` (reference never reassigned, only mutated)
- All function-local `var` declarations -> `const` if never reassigned, `let` if reassigned
- Examples: `var prev = ...` -> `const prev = ...`, `var seconds = 0` -> `let seconds = 0`, `var entry = ...` -> `const entry = ...`

**Preserve exactly:**
- The `/* global Vimeo */` directive at top
- The file's JSDoc comment block
- All function JSDoc comments (update them to reflect the consolidated API)
- The `sitchco.hooks.addAction` registration at bottom
- The `sitchco.register` block at bottom
- The `extractYouTubeStartTime` and `extractVimeoStartTime` functions unchanged
- The modal close/pause logic in the `sitchco.register` block
- The `handleModalShow` resume-existing-player and prevent-double-creation logic
  </action>
  <verify>
    <automated>cd /Users/jstrom/Projects/web/roundabout/public && make build 2>&1 | tail -20</automated>
  </verify>
  <done>
- view.js contains exactly 2 player creation functions (createYouTubePlayer, createVimeoPlayer) instead of 4
- A shared bindPlayTrigger (or similar) helper eliminates duplicated click/keyboard binding
- Zero `var` declarations remain in the file (verify with grep)
- Build succeeds without errors
- All behavioral contracts preserved: inline play, modal play, modal pause-on-close, modal resume-on-reopen, once-only inline activation, repeatable modal activation
  </done>
</task>

</tasks>

<verification>
```bash
# 1. Build succeeds
cd /Users/jstrom/Projects/web/roundabout/public && make build

# 2. No var declarations remain
grep -n '\bvar\b' modules/VideoBlock/blocks/video/view.js && echo "FAIL: var still present" || echo "PASS: no var"

# 3. Only 2 player creation functions (not 4)
grep -c 'function create.*Player' modules/VideoBlock/blocks/video/view.js
# Expected: 2

# 4. Shared event binding helper exists
grep -n 'function bindPlayTrigger\|function bindKeyboardTrigger' modules/VideoBlock/blocks/video/view.js
```
</verification>

<success_criteria>
- Build passes with zero errors
- File reduced from 4 player creation functions to 2
- Event binding duplication eliminated via shared helper(s)
- All var replaced with const/let
- No behavioral changes -- inline play, modal play, pause/resume lifecycle all work identically
</success_criteria>

<output>
After completion, create `.planning/quick/1-view-js-dry-refactor-consolidate-duplica/1-SUMMARY.md`
</output>
