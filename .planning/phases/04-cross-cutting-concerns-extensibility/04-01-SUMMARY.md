---
phase: 04-cross-cutting-concerns-extensibility
plan: 01
subsystem: ui
tags: [javascript, video, hooks, analytics, events, vimeo, youtube, sitchco-hooks]

requires:
  - phase: 03-modal-integration-triggers
    provides: modal player creation pattern (modalPlayers Map, handleModalShow/handleModalHide), createYouTubePlayer/createVimeoPlayer base implementations

provides:
  - activePlayers registry with mutual exclusion (MXCL-01, MXCL-02)
  - video-play, video-pause, video-ended, video-progress lifecycle hooks (ANLT-01, ANLT-02, ANLT-03)
  - video-request-pause subscriber for external control (EXTN-02, NOOP-02)
  - sitchco/video/playerVars/youtube and sitchco/video/playerVars/vimeo JS filters (EXTN-04, EXTN-05)
  - Milestone polling at 25/50/75% with once-per-page-load firing guarantee

affects: [04-02-PLAN.md, analytics integrations, TagManager subscriber]

tech-stack:
  added: []
  patterns:
    - "activePlayers Map keyed by provider video ID for global mutual exclusion state"
    - "registerActivePlayer pauses all other entries before registering -- no explicit isPlaying flag needed"
    - "milestonesFired Set per videoId never cleared -- once-per-page-load milestone guarantee"
    - "Vimeo milestone polling uses Promise.all([getCurrentTime, getDuration]) with silent catch"
    - "video-request-pause handler deliberately does NOT call doAction('video-pause') -- SDK fires native pause event which triggers the hook naturally"

key-files:
  created: []
  modified:
    - modules/VideoBlock/blocks/video/view.js

key-decisions:
  - "Milestone 100% fired from 'ended' event handler, not polling -- avoids off-by-one timing issues"
  - "milestonesFired Sets are never cleared -- milestones fire at most once per page load (per CONTEXT.md decision)"
  - "No IntersectionObserver or visibilitychange listeners -- external code uses video-request-pause hook instead (NOOP-02)"
  - "applyFilters context object includes {url, videoId, displayMode} for all filter subscribers"
  - "createYouTubePlayer/createVimeoPlayer gained url and displayMode parameters -- call sites updated"

patterns-established:
  - "Mutual exclusion via activePlayers.forEach pause-all-others before registering new active player"
  - "Milestone polling: setInterval + pollIntervals Map; stop on pause/ended; milestonesFired Set per video"

requirements-completed: [MXCL-01, MXCL-02, ANLT-01, ANLT-02, ANLT-03, EXTN-01, EXTN-02, EXTN-03, EXTN-04, EXTN-05, NOOP-02]

duration: 4min
completed: 2026-03-10
---

# Phase 4 Plan 01: Cross-Cutting Concerns Extensibility Summary

**Player registry with mutual exclusion, sitchco.hooks lifecycle events (play/pause/ended/progress), milestone polling at 25/50/75/100%, video-request-pause subscriber, and applyFilters player parameter hooks for YouTube and Vimeo**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-10T22:55:15Z
- **Completed:** 2026-03-10T22:59:10Z
- **Tasks:** 2
- **Files modified:** 2 (view.js + UIModal main.js formatting side effect)

## Accomplishments

- Added `activePlayers` Map with `registerActivePlayer()` that pauses all other active players before registering a new one, implementing MXCL-01 (mutual exclusion) and MXCL-02 (modal-inline exclusion) automatically
- Wired `video-play`, `video-pause`, `video-ended`, and `video-progress` hooks to both YouTube `onStateChange` and Vimeo event listeners (`play`/`pause`/`ended`)
- Implemented milestone polling (25/50/75%) with `startMilestonePolling`/`stopMilestonePolling`/`checkMilestones`; 100% milestone fires from ended event; milestones never repeat per page load
- Applied `sitchco.hooks.applyFilters('sitchco/video/playerVars/youtube')` and `('sitchco/video/playerVars/vimeo')` before player construction with `{url, videoId, displayMode}` context
- Registered `video-request-pause` subscriber that calls `pausePlayerById()` and `stopMilestonePolling()` without redundantly firing `video-pause` (SDK fires native pause which triggers the hook naturally)

## Task Commits

Each task was committed atomically:

1. **Tasks 1 + 2: Player registry, hooks, milestones, JS filters** - `7c04cca` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `modules/VideoBlock/blocks/video/view.js` - All Phase 4 JS features: activePlayers registry, registerActivePlayer, pausePlayerById, milestone polling, lifecycle hooks, applyFilters player params, video-request-pause subscriber
- `modules/UIModal/assets/scripts/main.js` - Formatting side effect (blank line added by `make format`)

## Decisions Made

- Milestone 100% fired from `ended` event, not polling, to avoid timing edge cases
- `milestonesFired` Sets never cleared per CONTEXT.md spec -- once-per-page-load guarantee
- `video-request-pause` handler does NOT re-fire `video-pause` -- the SDK's native pause event triggers the hook automatically through `onStateChange(PAUSED)` / Vimeo `pause` listener
- `createYouTubePlayer` and `createVimeoPlayer` now accept `url` and `displayMode` as 5th and 6th parameters; all call sites (`handlePlay`, `handleModalShow`) updated accordingly

## Deviations from Plan

None - plan executed exactly as written. Both tasks were implemented together since they target the same file; `make format && make lint` ran after all changes as specified.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All JS-side Phase 4 features are complete in view.js
- TagManager subscriber (Plan 04-02) can now hook into `video-play`, `video-pause`, `video-ended`, `video-progress` to push analytics events
- External code can use `applyFilters('sitchco/video/playerVars/youtube')` and `('sitchco/video/playerVars/vimeo')` to customize player initialization
- External code can call `doAction('video-request-pause', videoId)` to pause any active player

---
*Phase: 04-cross-cutting-concerns-extensibility*
*Completed: 2026-03-10*
