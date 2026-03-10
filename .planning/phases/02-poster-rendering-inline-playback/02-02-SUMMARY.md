---
phase: 02-poster-rendering-inline-playback
plan: 02
subsystem: ui
tags: [javascript, youtube-iframe-api, vimeo-player-sdk, click-to-play, privacy, inline-playback]

# Dependency graph
requires:
  - phase: 02-poster-rendering-inline-playback
    provides: Server-side poster rendering with data attributes (data-url, data-provider, data-video-id, data-click-behavior), CSS playing-state styles, player container styles
  - phase: 01-block-foundation-editor
    provides: Block registration, block.json attribute schema, editor.jsx, render.php skeleton
provides:
  - Click-to-play inline playback for YouTube and Vimeo
  - Provider SDK lazy loading on first click (zero resources before interaction)
  - Dimension locking for layout shift prevention
  - Privacy-enhanced embeds (youtube-nocookie.com, Vimeo dnt:true)
  - Start time extraction from URL parameters
  - viewScript registration via block.json and view.asset.php sidecar
affects: [03-modal-integration-triggers, 04-mutual-exclusion-analytics]

# Tech tracking
tech-stack:
  added: [YouTube IFrame API (CDN), Vimeo Player SDK (CDN)]
  patterns: [Promise-based singleton SDK loader, click-to-play with dimension locking, sitchco.loadScript deduplication]

key-files:
  created:
    - modules/VideoBlock/blocks/video/view.js
    - modules/VideoBlock/blocks/video/view.asset.php
  modified:
    - modules/VideoBlock/blocks/video/block.json

key-decisions:
  - "viewScript uses file:./view.js pattern in block.json (matching editorScript pattern) with view.asset.php sidecar for dependencies"
  - "YouTube IFrame API loaded via Promise-based singleton wrapper around onYouTubeIframeAPIReady global callback"
  - "Vimeo start time handled via player.ready().then(setCurrentTime) since SDK has no constructor start-time option"

patterns-established:
  - "SDK singleton loader: Promise-based wrapper with sitchco.loadScript() deduplication for third-party API scripts"
  - "Click-to-play lifecycle: lock dimensions -> add playing class -> remove ARIA -> create player container -> load SDK -> create player"
  - "viewScript sidecar: view.asset.php declares sitchco/ui-framework dependency for frontend scripts"

requirements-completed: [INLN-01, INLN-02, INLN-03, INLN-04, INLN-05, INLN-06, INLN-07, PRIV-02, PRIV-03]

# Metrics
duration: 2min
completed: 2026-03-09
---

# Phase 2 Plan 2: Inline Playback Summary

**Click-to-play view.js with YouTube/Vimeo SDK lazy loading, dimension locking, privacy-enhanced embeds (nocookie, dnt), and URL start time extraction**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-09T20:01:41Z
- **Completed:** 2026-03-09T20:04:25Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- view.js (238 lines) handles click-to-play for both YouTube and Vimeo with zero provider resources before user interaction
- YouTube IFrame API singleton loader wraps global callback in Promise, chains previous registrations to avoid conflicts
- Dimension locking reads offsetWidth/offsetHeight before any DOM changes to prevent layout shift (CLS prevention)
- Privacy-enhanced: YouTube uses youtube-nocookie.com host parameter, Vimeo uses dnt:true constructor option

## Task Commits

Each task was committed atomically:

1. **Task 1: Add viewScript to block.json and create view.asset.php sidecar** - `01f0f5e` (chore)
2. **Task 2: Create view.js with click-to-play SDK loading, dimension locking, and player creation** - `46644d0` (feat)

## Files Created/Modified
- `modules/VideoBlock/blocks/video/view.js` - Click-to-play handler, SDK loading, player creation for YouTube and Vimeo
- `modules/VideoBlock/blocks/video/view.asset.php` - viewScript dependency declaration (sitchco/ui-framework)
- `modules/VideoBlock/blocks/video/block.json` - Added viewScript field pointing to file:./view.js

## Decisions Made
- Used `file:./view.js` pattern in block.json (matching the working editorScript pattern) rather than registering a handle via VideoBlock.php
- YouTube IFrame API uses Promise-based singleton with `window.onYouTubeIframeAPIReady` callback chaining to avoid conflicts with other plugins
- Vimeo start time applied via `player.ready().then(() => player.setCurrentTime(seconds))` since the SDK constructor has no direct start-time option
- Used `{ once: true }` on event listeners for single-activation (no need to track played state separately)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Fixed ESLint errors for globals and unused catch variable**
- **Found during:** Task 2 (view.js creation)
- **Issue:** ESLint flagged `Vimeo` as undefined (no-undef) and catch variable as unused (no-unused-vars)
- **Fix:** Added `/* global Vimeo */` directive (sitchco already defined in eslint config), used optional catch binding (`catch {}`) for URL parsing
- **Files modified:** modules/VideoBlock/blocks/video/view.js
- **Verification:** ESLint passes cleanly
- **Committed in:** 46644d0 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** ESLint configuration compliance. No scope creep.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Inline playback complete; foundation ready for modal integration (Phase 3)
- view.js skips non-inline display modes (displayMode !== 'inline'), leaving modal handling for Phase 3
- All data attributes from render.php consumed correctly by view.js
- CSS playing-state transitions from Plan 01 work with the class toggle in view.js

---
*Phase: 02-poster-rendering-inline-playback*
*Completed: 2026-03-09*
