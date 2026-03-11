---
phase: quick
plan: 1
subsystem: VideoBlock/view.js
tags: [refactor, dry, javascript, frontend]
dependency_graph:
  requires: []
  provides: [modules/VideoBlock/blocks/video/view.js]
  affects: [inline-play, modal-play, modal-lifecycle]
tech_stack:
  added: []
  patterns: [consolidated player factory with optional modalId, shared event binding helpers]
key_files:
  created: []
  modified:
    - modules/VideoBlock/blocks/video/view.js
decisions:
  - "modalId parameter distinguishes inline vs modal player creation -- null means inline, string means modal"
  - "bindPlayTrigger passes the raw event to the callback so modal handler can call e.preventDefault() while inline handler ignores it"
  - "bindKeyboardTrigger extracted to eliminate duplicated Enter/Space check in both modal and inline branches"
metrics:
  duration: 1min
  completed: "2026-03-10"
  tasks_completed: 1
  files_modified: 1
---

# Quick Task 1: view.js DRY Refactor Summary

**One-liner:** Merged 4 player creation functions into 2 via optional `modalId` parameter, extracted `bindPlayTrigger`/`bindKeyboardTrigger` helpers, and replaced all `var` with `const`/`let`.

## What Was Done

Refactored `modules/VideoBlock/blocks/video/view.js` to address code review items #1 (High), #3 (Medium), and #10 (Medium):

**Item #1 -- Player consolidation:** `createYouTubePlayer` and `createModalYouTubePlayer` merged into a single `createYouTubePlayer(container, videoId, startTime, modalId)`. Same for the Vimeo pair. When `modalId` is provided, the function creates a wrapper div, stores the player reference in `modalPlayers` on ready, and adds the `--ready` class. When `modalId` is null/omitted, it behaves exactly as the former inline version.

**Item #3 -- Event binding helpers:** Extracted `bindPlayTrigger(element, callback, options)` and `bindKeyboardTrigger(element, callback, options)` to eliminate the duplicated click+keyboard patterns in `initVideoBlock()`. `bindKeyboardTrigger` early-returns if the element lacks `role="button"`. The raw event is passed to the `bindPlayTrigger` callback so callers can selectively call `e.preventDefault()` (modal path does; inline path does not).

**Item #10 -- var modernization:** Replaced all `var` declarations with `const` (for references that are never reassigned) and `let` (for `ytAPIPromise` and `seconds` which are reassigned). Zero `var` declarations remain.

## Tasks

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Consolidate player creation, extract event binding, replace var | 3ba61cc | modules/VideoBlock/blocks/video/view.js |

## Verification Results

- Build: PASS (no errors)
- No `var` remaining: PASS
- Player function count: 2 (down from 4): PASS
- `bindPlayTrigger` present: PASS (line 216)
- `bindKeyboardTrigger` present: PASS (line 228)
- ESLint: PASS (no linting errors)

## Deviations from Plan

None - plan executed exactly as written. The linter reformatted IIFEs and the `keydown` listener body into multi-line form consistent with the project style, but made no semantic changes.

## Self-Check: PASSED

- File exists: `modules/VideoBlock/blocks/video/view.js` -- FOUND
- Commit 3ba61cc exists -- FOUND
