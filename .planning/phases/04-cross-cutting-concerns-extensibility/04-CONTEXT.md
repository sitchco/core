# Phase 4: Cross-Cutting Concerns & Extensibility - Context

**Gathered:** 2026-03-10
**Status:** Ready for planning

<domain>
## Phase Boundary

Multiple videos coordinate (only one plays at a time), analytics track engagement via hooks, and external code can hook into the video lifecycle. Delivers mutual exclusion, progress tracking, JS action/filter hooks, and a PHP filter for play icon SVG. The video block fires domain-level hooks; dataLayer pushing is TagManager's responsibility (not built in this phase).

</domain>

<decisions>
## Implementation Decisions

### Analytics Architecture
- Video block fires hooks ONLY via `sitchco.hooks.doAction()` — no direct `dataLayer.push()` in view.js
- TagManager module (separate project) will subscribe to these hooks and translate to dataLayer events
- ANLT-01/02/03 requirements are satisfied when TagManager adds its video hook subscribers
- Hook names are internal sitchco.hooks names, not GA4 event names — GA4 reserved name avoidance (`video_start`, `video_progress`, `video_complete`) is TagManager's concern, not the video block's

### Hook Design (JS — sitchco.hooks)
- **video-play**: Action fired when playback starts. Payload: `{id, provider, url}` where `id` is the provider video ID (YouTube 11-char ID or Vimeo numeric ID)
- **video-pause**: Notification action fired when a video pauses (any reason). Payload: `{id, provider, url}`
- **video-request-pause**: Command action for external code to request a pause. Called via `doAction('video-request-pause', videoId)` where videoId is the provider video ID string. Video block subscribes and handles the pause internally.
- **video-progress**: Action fired at milestones (25%, 50%, 75%). Payload: `{id, provider, url, milestone: 25}`
- **video-ended**: Action fired when video reaches the end. Payload: `{id, provider, url}`. Also fires `video-progress` with `milestone: 100`.
- Two-hook pattern for pause: `video-pause` is notification (something paused), `video-request-pause` is command (please pause this)

### Progress Milestone Tracking
- Milestones: 25%, 50%, 75%, 100%
- Each milestone fires at most once per video instance per page load — no reset on replay
- Seeking past a milestone fires it immediately (e.g., seeking from 10% to 60% fires 25% and 50%)
- 100% milestone detected via native ended event (YouTube `onStateChange(ENDED)`, Vimeo `ended` event), not polling
- 25/50/75% milestones detected via setInterval polling (e.g., ~1s) while video is playing; polling pauses when video pauses
- Unified polling approach for both YouTube and Vimeo (same code path)

### Video Identification
- Provider video ID is the canonical identifier across all hooks and external API
- YouTube: 11-character video ID (e.g., `dQw4w9WgXcQ`)
- Vimeo: numeric ID (e.g., `123456789`)
- External code uses provider video ID to target specific videos via `video-request-pause`
- Video block maintains internal mapping from provider ID to player instance

### JS Filter Design
- `sitchco/video/playerVars/youtube`: Receives `(playerVars, {url, videoId, displayMode})` — allows conditional overrides per video
- `sitchco/video/playerVars/vimeo`: Receives `(playerVars, {url, videoId, displayMode})` — same pattern
- Applied via `sitchco.hooks.applyFilters()` before player creation

### PHP Filter Design
- Filter hook: `VideoBlock::hookName('play_icon_svg')` producing `sitchco/video/play_icon_svg`
- HOOK_SUFFIX for VideoBlock module: `'video'`
- Filter targets SVG markup only, NOT the wrapping `<button>` element — accessibility (aria-label), positioning, and CSS classes stay block-controlled
- Arguments: `apply_filters('sitchco/video/play_icon_svg', $svg, $provider, $play_icon_style)`
- Themes can swap icon shapes without breaking accessibility or layout

### Mutual Exclusion
- Starting a second video (inline or modal) pauses the first via `video-request-pause`
- Opening a video modal pauses any currently playing inline video
- Video block maintains an internal "active player" reference; on new play, pauses the previous active player before starting

### No-Op Behavior (NOOP-02)
- Video block does NOT auto-pause on visibility changes (scroll, tab switch, carousel slide)
- External code (carousels, tab components) uses `doAction('video-request-pause', videoId)` to request pause

### Claude's Discretion
- Polling interval duration (1s suggested but flexible)
- Internal data structure for tracking active players and milestone state
- How mutual exclusion interacts with modal pause/resume (edge cases)
- Error handling for SDK state queries during polling
- Whether to stop polling on tab visibility change (performance optimization)

</decisions>

<specifics>
## Specific Ideas

- Tag-manager spec (roundabout/tag-manager/scenario-spec.md) establishes that TagManager is a subscriber — it consumes domain-level hooks via `sitchco.hooks` and translates to `dataLayer` pushes. The video block should NOT push to dataLayer directly.
- The spec's N3 scenario explicitly says: "The video block does not auto-pause on visibility changes. External code (e.g., a carousel module) may pause the video by calling `sitchco.hooks.doAction('video-pause', id)`" — renamed to `video-request-pause` for clarity.
- GA4 reserved event names (`video_start`, `video_progress`, `video_complete`) are TagManager's problem, not the video block's. Hook names are internal.
- The video-design.md spec's Extension Points table is the canonical reference for hook names and payload shapes.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- **sitchco.hooks** (`modules/UIFramework/assets/scripts/hooks.js`): Full `@wp/hooks` wrapper with `doAction`, `applyFilters`, namespaced via `sitchco/`. Already used by view.js for `ui-modal-show`/`ui-modal-hide`.
- **view.js player creation functions**: `createYouTubePlayer()` and `createVimeoPlayer()` already have `onReady` callbacks — extend these for hook firing and player registration.
- **modalPlayers Map**: Already tracks modal player instances by modal ID. Extend or complement with a provider-ID-keyed registry for mutual exclusion.
- **YouTube onStateChange**: YouTube Player API provides `onStateChange` event with states: PLAYING, PAUSED, ENDED. Add listener for hook firing and milestone detection.
- **Vimeo events**: Vimeo Player SDK provides `play`, `pause`, `ended`, `timeupdate` events. Add listeners for hook firing.
- **VideoBlockRenderer::buildPlayButton()**: Current method builds the full `<button>` with SVG `<use>` reference. Add `apply_filters()` call on the SVG markup before wrapping in `<button>`.
- **Hooks::name()** (`src/Support/Hooks.php`): Utility for generating namespaced hook names. VideoBlock needs `HOOK_SUFFIX = 'video'` to produce `sitchco/video/*` hooks.

### Established Patterns
- **Hook registration**: `sitchco.hooks.addAction('hook-name', callback, priority, 'namespace')` — video block uses `'video-block'` namespace.
- **PHP hook naming**: `Hooks::name('part1', 'part2')` produces `sitchco/part1/part2`. Module `hookName('suffix')` uses HOOK_SUFFIX as the first segment.
- **Player creation flow**: SDK load → player create → onReady callback. Hooks fire at onReady (play), onStateChange (pause/ended), and polling (progress).

### Integration Points
- **view.js**: Major expansion — add player registry, milestone tracking state, polling logic, hook firing, mutual exclusion, `video-request-pause` subscriber
- **VideoBlockRenderer.php**: Add `apply_filters()` for play icon SVG in `buildPlayButton()`
- **VideoBlock.php**: Add `HOOK_SUFFIX = 'video'` constant

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 04-cross-cutting-concerns-extensibility*
*Context gathered: 2026-03-10*
