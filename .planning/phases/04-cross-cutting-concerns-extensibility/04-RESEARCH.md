# Phase 4: Cross-Cutting Concerns & Extensibility - Research

**Researched:** 2026-03-10
**Domain:** JavaScript event coordination, polling-based milestone tracking, WordPress/sitchco hook APIs
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Analytics Architecture**
- Video block fires hooks ONLY via `sitchco.hooks.doAction()` — no direct `dataLayer.push()` in view.js
- TagManager module (separate project) will subscribe to these hooks and translate to dataLayer events
- ANLT-01/02/03 requirements are satisfied when TagManager adds its video hook subscribers
- Hook names are internal sitchco.hooks names, not GA4 event names

**Hook Design (JS — sitchco.hooks)**
- `video-play`: Action fired when playback starts. Payload: `{id, provider, url}`
- `video-pause`: Notification action fired when a video pauses (any reason). Payload: `{id, provider, url}`
- `video-request-pause`: Command action for external code to request a pause. Called via `doAction('video-request-pause', videoId)` where videoId is the provider video ID string. Video block subscribes and handles the pause internally.
- `video-progress`: Action fired at milestones (25%, 50%, 75%). Payload: `{id, provider, url, milestone: 25}`
- `video-ended`: Action fired when video reaches the end. Payload: `{id, provider, url}`. Also fires `video-progress` with `milestone: 100`.
- Two-hook pattern for pause: `video-pause` is notification (something paused), `video-request-pause` is command (please pause this)

**Progress Milestone Tracking**
- Milestones: 25%, 50%, 75%, 100%
- Each milestone fires at most once per video instance per page load — no reset on replay
- Seeking past a milestone fires it immediately (e.g., seeking from 10% to 60% fires 25% and 50%)
- 100% milestone detected via native ended event (YouTube `onStateChange(ENDED)`, Vimeo `ended` event), not polling
- 25/50/75% milestones detected via setInterval polling (e.g., ~1s) while video is playing; polling pauses when video pauses
- Unified polling approach for both YouTube and Vimeo (same code path)

**Video Identification**
- Provider video ID is the canonical identifier across all hooks and external API
- YouTube: 11-character video ID (e.g., `dQw4w9WgXcQ`)
- Vimeo: numeric ID (e.g., `123456789`)
- External code uses provider video ID to target specific videos via `video-request-pause`
- Video block maintains internal mapping from provider ID to player instance

**JS Filter Design**
- `sitchco/video/playerVars/youtube`: Receives `(playerVars, {url, videoId, displayMode})` — allows conditional overrides per video
- `sitchco/video/playerVars/vimeo`: Receives `(playerVars, {url, videoId, displayMode})` — same pattern
- Applied via `sitchco.hooks.applyFilters()` before player creation

**PHP Filter Design**
- Filter hook: `VideoBlock::hookName('play_icon_svg')` producing `sitchco/video/play_icon_svg`
- HOOK_SUFFIX for VideoBlock module: `'video'`
- Filter targets SVG markup only, NOT the wrapping `<button>` element
- Arguments: `apply_filters('sitchco/video/play_icon_svg', $svg, $provider, $play_icon_style)`
- Themes can swap icon shapes without breaking accessibility or layout

**Mutual Exclusion**
- Starting a second video (inline or modal) pauses the first via `video-request-pause`
- Opening a video modal pauses any currently playing inline video
- Video block maintains an internal "active player" reference; on new play, pauses the previous active player before starting

**No-Op Behavior (NOOP-02)**
- Video block does NOT auto-pause on visibility changes (scroll, tab switch, carousel slide)
- External code (carousels, tab components) uses `doAction('video-request-pause', videoId)` to request pause

### Claude's Discretion
- Polling interval duration (1s suggested but flexible)
- Internal data structure for tracking active players and milestone state
- How mutual exclusion interacts with modal pause/resume (edge cases)
- Error handling for SDK state queries during polling
- Whether to stop polling on tab visibility change (performance optimization)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| MXCL-01 | Starting a second video (inline or modal) pauses the first | Player registry keyed by provider video ID; `video-request-pause` handler calls `pauseVideo()`/`pause()` on registered instances |
| MXCL-02 | Opening a video modal pauses any currently playing inline video | `handleModalShow` fires `video-request-pause` before creating new modal player; inline players registered in same registry |
| ANLT-01 | GTM interaction event fires on video start: `{action: 'start', provider, url, id}` | `sitchco.hooks.doAction('video-play', {id, provider, url})` in player `onReady`/`play` callback |
| ANLT-02 | GTM interaction events fire at progress milestones: 25%, 50%, 75%, 100% | `setInterval` polling + `ended` event; fires `video-progress` with `milestone` field |
| ANLT-03 | GTM interaction event fires on video pause: `{action: 'pause'}` | `video-pause` action in YouTube `onStateChange(PAUSED)` and Vimeo `pause` event |
| EXTN-01 | JS action `video-play` fires when a video starts playing with `{id, provider, url}` payload | `sitchco.hooks.doAction('video-play', payload)` in onReady/play event handlers |
| EXTN-02 | JS action `video-pause` allows external code to pause a video by ID | `video-request-pause` subscriber in view.js; looks up player in registry by videoId |
| EXTN-03 | JS action `video-ended` fires when a video reaches the end | `sitchco.hooks.doAction('video-ended', payload)` in ENDED state / Vimeo `ended` event |
| EXTN-04 | JS filter `sitchco/video/playerVars/youtube` allows overriding YouTube player parameters | `sitchco.hooks.applyFilters('sitchco/video/playerVars/youtube', playerVars, context)` before `new YT.Player()` |
| EXTN-05 | JS filter `sitchco/video/playerVars/vimeo` allows overriding Vimeo player parameters | `sitchco.hooks.applyFilters('sitchco/video/playerVars/vimeo', playerVars, context)` before `new Vimeo.Player()` |
| EXTN-06 | PHP filter `sitchco/video/play_icon_svg` allows replacing play button SVG markup | `apply_filters(VideoBlock::hookName('play_icon_svg'), $svg, $provider, $play_icon_style)` in `buildPlayButton()` |
| NOOP-02 | Video block does not auto-pause on visibility changes | Confirmed by implementation: no IntersectionObserver, no visibilitychange listener in view.js |
</phase_requirements>

---

## Summary

Phase 4 is a purely additive layer on top of the already-complete Phase 3 implementation. There are no new dependencies to install — the `sitchco.hooks` system (a `@wordpress/hooks` wrapper), YouTube IFrame API, and Vimeo Player SDK are all already present and loaded.

The work falls into three orthogonal streams that can be planned and executed in parallel: (1) player registry + mutual exclusion in `view.js`, (2) hook firing + milestone tracking in `view.js`, and (3) the PHP filter on the play icon SVG in `VideoBlockRenderer.php`. The JS filter pass-through (`applyFilters` for playerVars) threads through the same player-creation functions.

The biggest internal design decision is the player registry data structure. Because both inline and modal players must be tracked by provider video ID for `video-request-pause`, the existing `modalPlayers` Map (keyed by modal DOM ID) needs a parallel or complementary registry. The simplest approach: a module-level `activePlayers` Map keyed by provider video ID, storing `{ player, provider }` — set when a player starts playing, cleared when it ends, updated on `video-request-pause`.

**Primary recommendation:** Implement in two tasks — task 1: player registry + mutual exclusion + hook firing + JS filters + milestone tracking (all in view.js); task 2: PHP play icon filter + HOOK_SUFFIX fix (VideoBlock.php + VideoBlockRenderer.php). Both tasks are small and low-risk additions to existing functions.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| sitchco.hooks | (bundled) | JS action/filter hooks via `@wordpress/hooks` | Already in use; view.js already calls `addAction`/`doAction` |
| YouTube IFrame API | (CDN, loaded on demand) | Player state events: `onStateChange` with PLAYING/PAUSED/ENDED constants | Already loaded via `loadYouTubeAPI()` singleton |
| Vimeo Player SDK | (CDN, loaded on demand) | Player events: `play`, `pause`, `ended`; `getCurrentTime()`, `getDuration()` | Already loaded via `loadVimeoSDK()` |
| `apply_filters()` | WordPress core | PHP extensibility hook | Standard WordPress pattern; `VideoBlockRenderer` already uses WordPress functions |

### No New Installs Required
All dependencies for this phase are already present. No `npm install` or `composer require` is needed.

---

## Architecture Patterns

### Recommended File Structure (changes only)

```
modules/VideoBlock/
├── VideoBlock.php              # Add: HOOK_SUFFIX = 'video' (was 'video-block')
├── VideoBlockRenderer.php      # Add: apply_filters() in buildPlayButton()
└── blocks/video/
    └── view.js                 # Major expansion: registry, hooks, milestones
```

### Pattern 1: Player Registry for Mutual Exclusion

**What:** A module-level Map keyed by provider video ID tracks active player instances. On each new play event, the previous active player is paused before the new one starts.

**When to use:** Both inline and modal player creation paths call into this.

**Example:**
```javascript
// Module-level registry — add alongside modalPlayers
const activePlayers = new Map();
// Entry shape: { player: SDKInstance, provider: 'youtube'|'vimeo' }

function pausePlayerById(videoId) {
    const entry = activePlayers.get(videoId);
    if (!entry) return;
    if (entry.provider === 'youtube') {
        entry.player.pauseVideo();
    } else {
        entry.player.pause();
    }
}

function registerActivePlayer(videoId, player, provider) {
    // Pause any currently playing video first (MXCL-01)
    activePlayers.forEach(function (entry, id) {
        if (id !== videoId) {
            if (entry.provider === 'youtube') {
                entry.player.pauseVideo();
            } else {
                entry.player.pause();
            }
        }
    });
    activePlayers.set(videoId, { player, provider });
}
```

### Pattern 2: Hook Firing in Player Callbacks

**What:** Player SDK callbacks (`onReady`, `onStateChange`, `play`, `pause`, `ended`) are the integration points for firing sitchco hooks.

**When to use:** Inside the existing `createYouTubePlayer` and `createVimeoPlayer` functions. The `onReady`/`play` callback already runs after the player is live.

**Example — YouTube onStateChange extension:**
```javascript
// Inside createYouTubePlayer, add onStateChange to the events object
onStateChange: function (event) {
    const state = event.data;
    if (state === YT.PlayerState.PLAYING) {
        registerActivePlayer(videoId, event.target, 'youtube');
        sitchco.hooks.doAction('video-play', { id: videoId, provider: 'youtube', url: url });
        startMilestonePolling(videoId, event.target, 'youtube', url);
    } else if (state === YT.PlayerState.PAUSED) {
        sitchco.hooks.doAction('video-pause', { id: videoId, provider: 'youtube', url: url });
        stopMilestonePolling(videoId);
    } else if (state === YT.PlayerState.ENDED) {
        sitchco.hooks.doAction('video-progress', { id: videoId, provider: 'youtube', url: url, milestone: 100 });
        sitchco.hooks.doAction('video-ended', { id: videoId, provider: 'youtube', url: url });
        stopMilestonePolling(videoId);
        activePlayers.delete(videoId);
    }
}
```

**Example — Vimeo event binding:**
```javascript
// After player.ready(), bind events:
player.on('play', function () {
    registerActivePlayer(videoId, player, 'vimeo');
    sitchco.hooks.doAction('video-play', { id: videoId, provider: 'vimeo', url: url });
    startMilestonePolling(videoId, player, 'vimeo', url);
});
player.on('pause', function () {
    sitchco.hooks.doAction('video-pause', { id: videoId, provider: 'vimeo', url: url });
    stopMilestonePolling(videoId);
});
player.on('ended', function () {
    sitchco.hooks.doAction('video-progress', { id: videoId, provider: 'vimeo', url: url, milestone: 100 });
    sitchco.hooks.doAction('video-ended', { id: videoId, provider: 'vimeo', url: url });
    stopMilestonePolling(videoId);
    activePlayers.delete(videoId);
});
```

### Pattern 3: Milestone Polling

**What:** A per-video `setInterval` fires at ~1s intervals while playing. It reads current time + duration and fires `video-progress` for any newly-crossed milestone thresholds.

**When to use:** Started from the PLAYING state callback, stopped on PAUSED/ENDED.

**Key design points:**
- Store fired milestones per video ID in a module-level `milestonesFired` Map (e.g., `milestonesFired.get(videoId)` returns a Set like `{25, 50}`).
- Check ALL milestones up to current percentage on each tick — handles seeking (CONTEXT.md decision).
- YouTube: `player.getCurrentTime()` and `player.getDuration()` are synchronous (return numbers directly).
- Vimeo: `player.getCurrentTime()` and `player.getDuration()` are async (return Promises) — use `Promise.all()` inside interval.

**Example:**
```javascript
const pollIntervals = new Map(); // videoId -> intervalId
const milestonesFired = new Map(); // videoId -> Set of fired percentages

const MILESTONES = [25, 50, 75]; // 100 handled by ended event

function startMilestonePolling(videoId, player, provider, url) {
    if (pollIntervals.has(videoId)) return; // already polling
    if (!milestonesFired.has(videoId)) {
        milestonesFired.set(videoId, new Set());
    }

    const intervalId = setInterval(function () {
        if (provider === 'youtube') {
            const current = player.getCurrentTime();
            const duration = player.getDuration();
            checkMilestones(videoId, provider, url, current, duration);
        } else {
            Promise.all([player.getCurrentTime(), player.getDuration()])
                .then(function ([current, duration]) {
                    checkMilestones(videoId, provider, url, current, duration);
                })
                .catch(function () {
                    // SDK may error if player is destroyed; ignore
                });
        }
    }, 1000);

    pollIntervals.set(videoId, intervalId);
}

function stopMilestonePolling(videoId) {
    const intervalId = pollIntervals.get(videoId);
    if (intervalId !== undefined) {
        clearInterval(intervalId);
        pollIntervals.delete(videoId);
    }
}

function checkMilestones(videoId, provider, url, current, duration) {
    if (!duration || duration <= 0) return;
    const pct = (current / duration) * 100;
    const fired = milestonesFired.get(videoId);

    MILESTONES.forEach(function (milestone) {
        if (pct >= milestone && !fired.has(milestone)) {
            fired.add(milestone);
            sitchco.hooks.doAction('video-progress', { id: videoId, provider: provider, url: url, milestone: milestone });
        }
    });
}
```

### Pattern 4: video-request-pause Subscriber

**What:** The video block registers a `video-request-pause` action listener at module load time. External code calls `doAction('video-request-pause', videoId)` to pause a specific player.

**Example:**
```javascript
// At module init (alongside existing ui-modal-show/hide registrations)
sitchco.hooks.addAction('video-request-pause', function (videoId) {
    pausePlayerById(videoId);
    stopMilestonePolling(videoId);
}, 10, 'video-block');
```

### Pattern 5: JS Player Parameter Filters

**What:** Before constructing the player config object, pass it through `sitchco.hooks.applyFilters()` with context. External code registers a filter to override specific params.

**Example — YouTube:**
```javascript
const defaultPlayerVars = {
    autoplay: 1,
    playsinline: 1,
    enablejsapi: 1,
    origin: window.location.origin,
    start: startTime,
    rel: 0,
    iv_load_policy: 3,
};
const playerVars = sitchco.hooks.applyFilters(
    'sitchco/video/playerVars/youtube',
    defaultPlayerVars,
    { url: url, videoId: videoId, displayMode: displayMode }
);
new YT.Player(target, { videoId: videoId, host: '...', playerVars: playerVars, events: { ... } });
```

**Example — Vimeo:**
```javascript
const defaultOptions = {
    id: parseInt(videoId, 10),
    autoplay: true,
    dnt: true,
    title: false,
    byline: false,
    portrait: false,
    badge: false,
    vimeo_logo: false,
};
const options = sitchco.hooks.applyFilters(
    'sitchco/video/playerVars/vimeo',
    defaultOptions,
    { url: url, videoId: videoId, displayMode: displayMode }
);
const player = new Vimeo.Player(target, options);
```

### Pattern 6: PHP Play Icon SVG Filter

**What:** In `buildPlayButton()`, wrap the built `$svg` string in `apply_filters()` before putting it inside the `<button>`.

**Important:** `VideoBlock::HOOK_SUFFIX` currently is `'video-block'` — it must be changed to `'video'` so that `hookName('play_icon_svg')` produces `sitchco/video/play_icon_svg`. Verify this does not break any existing PHP hook subscribers before changing.

**Example:**
```php
// In VideoBlockRenderer::buildPlayButton(), after building $svg:
$svg = apply_filters(
    VideoBlock::hookName('play_icon_svg'),
    $svg,
    $provider,
    $play_icon_style
);

return sprintf(
    '<button class="sitchco-video__play-button ..." ...>%s</button>',
    $svg
);
```

**PHP hook registration pattern (VideoBlock.php):**
```php
class VideoBlock extends Module
{
    const HOOK_SUFFIX = 'video'; // Changed from 'video-block'
    // ...
}
```

### Anti-Patterns to Avoid

- **Passing player objects across hook boundaries:** The `video-request-pause` action takes only a videoId string — not a player reference. External code should not receive SDK player instances.
- **Resetting milestone state on replay:** Milestones fire at most once per page load per video instance (CONTEXT.md decision). Do not clear `milestonesFired` on PLAYING state transitions.
- **Polling without pause:** The interval must stop on PAUSED and ENDED states to avoid wasted CPU and spurious re-fires.
- **Direct dataLayer.push in view.js:** All analytics go through `sitchco.hooks.doAction()`. TagManager is the sole consumer.
- **Extending the SVG filter to the `<button>` wrapper:** The filter is SVG-only. The button's `aria-label`, `class`, and `style` remain block-controlled.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JS action/filter system | Custom event emitter, CustomEvents, pub/sub library | `sitchco.hooks.doAction` / `applyFilters` | Already in use; consistent namespacing; external code already knows the pattern |
| Player state tracking | Custom iframe message listeners | YouTube `onStateChange`, Vimeo SDK `play`/`pause`/`ended` events | SDK events are reliable and already loaded on-demand |
| Polling deduplication | Flag variables scattered through functions | `pollIntervals` Map — presence of key means polling is active | Clean start/stop semantics; Map is already the pattern used for `modalPlayers` |
| PHP hook name construction | Inline string concatenation | `VideoBlock::hookName('play_icon_svg')` via `HasHooks` trait | Consistent namespacing; matches pattern used throughout sitchco-core |

---

## Common Pitfalls

### Pitfall 1: HOOK_SUFFIX Mismatch

**What goes wrong:** `VideoBlock::HOOK_SUFFIX` is currently `'video-block'`, which produces hook name `sitchco/video-block/play_icon_svg`. The spec requires `sitchco/video/play_icon_svg`.

**Why it happens:** The suffix was set to `'video-block'` in Phase 1 as a JS namespace string. It was reused for the PHP hook suffix without noticing the mismatch.

**How to avoid:** Change `HOOK_SUFFIX = 'video-block'` to `HOOK_SUFFIX = 'video'` in `VideoBlock.php` before adding the PHP filter.

**Warning signs:** If any existing code calls `VideoBlock::hookName(...)`, test that it still resolves correctly after the change. Search the codebase for `hookName` calls on VideoBlock — there are none currently, so the change is safe.

### Pitfall 2: Vimeo Async API in Polling

**What goes wrong:** `player.getCurrentTime()` returns a Promise in the Vimeo SDK, not a number. Code that treats it as synchronous will poll against `undefined`.

**Why it happens:** YouTube API is synchronous; Vimeo is async — they look similar but behave differently.

**How to avoid:** Use `Promise.all([player.getCurrentTime(), player.getDuration()]).then(...)` inside the Vimeo branch of the polling interval. Wrap in `.catch(() => {})` to suppress errors if the player is destroyed mid-poll.

### Pitfall 3: `video-pause` Fired by `video-request-pause`

**What goes wrong:** When `video-request-pause` pauses a player via SDK call, the SDK fires its own `pause` event, which triggers `video-pause` action. This is correct behavior — `video-pause` means "a video paused" regardless of the cause. But do not double-fire by explicitly calling `doAction('video-pause')` inside the `video-request-pause` handler.

**Why it happens:** Two code paths both try to fire the notification.

**How to avoid:** Only fire `video-pause` from the SDK's native pause event handler (`onStateChange(PAUSED)` / Vimeo `pause` event). The `video-request-pause` subscriber only calls the SDK method — the SDK then fires the event naturally.

### Pitfall 4: Mutual Exclusion Timing with Modal Players

**What goes wrong:** When a modal opens and its player starts loading (async), the inline player fires its own PLAYING state almost immediately. The ordering of `registerActivePlayer` calls may mean the modal player isn't yet registered when mutual exclusion runs.

**Why it happens:** Modal player creation is async (SDK load + player init). Inline player is also async but may resolve at a different time.

**How to avoid:** Mutual exclusion fires on the PLAYING event, not on player-creation initiation. By the time `onStateChange(PLAYING)` or Vimeo `play` fires, `registerActivePlayer` runs and pauses all other entries in `activePlayers` at that point. If the modal player hasn't fired PLAYING yet, it won't be in the registry yet — so no premature pause occurs. This is the correct, race-condition-safe approach.

### Pitfall 5: `displayMode` Not Available in Player Creation Scope

**What goes wrong:** The JS filter context requires `displayMode`, but the player creation functions (`createYouTubePlayer`, `createVimeoPlayer`) currently don't receive it as a parameter.

**Why it happens:** `displayMode` is read from `wrapper.dataset.displayMode` in `handlePlay()` and from `playerContainer.dataset` in `handleModalShow()`, but neither currently passes it down.

**How to avoid:** Add `displayMode` as an additional parameter to `createYouTubePlayer` and `createVimeoPlayer`, or pass it as part of a context object. The call sites already have `displayMode` available.

---

## Code Examples

### Existing Hook Registration Pattern (view.js)
```javascript
// Source: modules/VideoBlock/blocks/video/view.js (line 483-484)
sitchco.hooks.addAction('ui-modal-show', handleModalShow, 20, 'video-block');
sitchco.hooks.addAction('ui-modal-hide', handleModalHide, 20, 'video-block');
```

### Existing doAction Pattern (view.js)
```javascript
// Source: modules/VideoBlock/blocks/video/view.js (line 430)
sitchco.hooks.doAction('ui-modal-show', modal);
```

### applyFilters Signature (hooks.js)
```javascript
// Source: modules/UIFramework/assets/scripts/hooks.js (line 44-47)
function addFilter(hookName, callback, priority = 10, subNamespace = '') {
    _addFilter(hookName, buildNamespace(subNamespace), callback, priority);
}
// Called as: sitchco.hooks.applyFilters('hook-name', value, ...args)
// applyFilters is re-exported directly from @wordpress/hooks, not wrapped
```

### PHP hookName() Pattern (HasHooks trait)
```php
// Source: src/Support/HasHooks.php (line 27-33)
public static function hookName(...$name_parts): string
{
    $prefix = defined('static::HOOK_PREFIX') ? static::HOOK_PREFIX : '';
    return Hooks::name($prefix, static::HOOK_SUFFIX, ...$name_parts);
}
// With HOOK_SUFFIX = 'video':
// VideoBlock::hookName('play_icon_svg') => 'sitchco/video/play_icon_svg'
```

### Existing PHP apply_filters Usage (WordPress pattern)
```php
// Standard WordPress pattern — no existing example in VideoBlockRenderer.php yet
$svg = apply_filters('sitchco/video/play_icon_svg', $svg, $provider, $play_icon_style);
```

---

## State of the Art

| Old Approach | Current Approach | Notes |
|--------------|-----------------|-------|
| YouTube `timeupdate` polling via postMessage | YouTube `onStateChange` + `getCurrentTime()` synchronously | YouTube IFrame API provides synchronous time access; no postMessage hacks needed |
| Vimeo `timeupdate` event (fires per-frame) | `setInterval` at ~1s calling `getCurrentTime()` async | Unified polling approach across providers; avoids high-frequency Vimeo timeupdate events |
| Custom pub/sub for inter-widget coordination | `sitchco.hooks.doAction` / `addAction` | Already the sitchco standard; reuse rather than introduce new pattern |

---

## Open Questions

1. **`applyFilters` wrapping in hooks.js**
   - What we know: `doAction` is re-exported directly from `@wordpress/hooks`. `applyFilters` is also re-exported directly (line 14 of hooks.js).
   - What's unclear: The `addFilter` wrapper uses `buildNamespace(subNamespace)` for the registrant namespace, but `applyFilters` is the raw `@wordpress/hooks` version. Hook names (e.g., `sitchco/video/playerVars/youtube`) are passed directly as strings to both `addFilter` and `applyFilters` — no additional namespacing happens on the hook name itself.
   - Recommendation: Use `sitchco.hooks.applyFilters('sitchco/video/playerVars/youtube', value, ctx)` and `sitchco.hooks.addFilter('sitchco/video/playerVars/youtube', callback, 10, 'my-namespace')`. The hook name string is the same in both calls.

2. **YouTube `displayMode` for filter context**
   - What we know: `createYouTubePlayer` currently takes `(container, videoId, startTime, modalId)`. `displayMode` is not a parameter.
   - Recommendation: Either add `displayMode` as a 5th parameter, or derive it from `modalId` (if modalId is non-null, it's modal mode). Using `modalId` presence as proxy for `displayMode` avoids signature churn and is semantically correct.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (via WPTest) |
| Config file | No phpunit.xml found — test runner is `ddev test-phpunit` from sitchco-core/ cwd |
| Quick run command | `ddev test-phpunit tests/Modules/VideoBlock/VideoBlockTest.php` |
| Full suite command | `ddev test-phpunit` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| MXCL-01 | Starting second video pauses the first | PHP unit (renderer output) + manual browser | `ddev test-phpunit tests/Modules/VideoBlock/VideoBlockTest.php` | Existing file needs new test methods |
| MXCL-02 | Opening modal pauses inline video | Manual browser verification | N/A — JS behavior | N/A |
| ANLT-01 | video-play hook fires with correct payload | Manual browser / JS | N/A — JS behavior | N/A |
| ANLT-02 | video-progress fires at 25/50/75/100 | Manual browser / JS | N/A — JS behavior | N/A |
| ANLT-03 | video-pause hook fires on pause | Manual browser / JS | N/A — JS behavior | N/A |
| EXTN-01 | video-play action fires | Manual browser / JS | N/A — JS behavior | N/A |
| EXTN-02 | video-request-pause pauses target by ID | Manual browser / JS | N/A — JS behavior | N/A |
| EXTN-03 | video-ended fires on completion | Manual browser / JS | N/A — JS behavior | N/A |
| EXTN-04 | sitchco/video/playerVars/youtube filter applied | Manual browser / JS | N/A — JS behavior | N/A |
| EXTN-05 | sitchco/video/playerVars/vimeo filter applied | Manual browser / JS | N/A — JS behavior | N/A |
| EXTN-06 | PHP filter sitchco/video/play_icon_svg applied | PHP unit | `ddev test-phpunit tests/Modules/VideoBlock/VideoBlockTest.php` | ✅ Needs new test method |
| NOOP-02 | No auto-pause on visibility changes | PHP (no observer in output) | `ddev test-phpunit tests/Modules/VideoBlock/VideoBlockTest.php` | ✅ Implied by no IntersectionObserver |

### Sampling Rate
- **Per task commit:** `ddev test-phpunit tests/Modules/VideoBlock/VideoBlockTest.php`
- **Per wave merge:** `ddev test-phpunit`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
The test file `tests/Modules/VideoBlock/VideoBlockTest.php` already exists. New test methods needed (not new files):
- `test_play_icon_svg_filter_is_applied()` — verifies `apply_filters('sitchco/video/play_icon_svg', ...)` is called and result appears in output
- `test_hook_suffix_produces_correct_filter_name()` — verifies `VideoBlock::hookName('play_icon_svg')` === `'sitchco/video/play_icon_svg'`

No new test files or framework config needed.

---

## Sources

### Primary (HIGH confidence)
- Direct code inspection of `modules/VideoBlock/blocks/video/view.js` — current player creation, modal handling, hook registration patterns
- Direct code inspection of `modules/UIFramework/assets/scripts/hooks.js` — `sitchco.hooks` API: `doAction`, `applyFilters`, `addAction`, `addFilter` signatures
- Direct code inspection of `src/Support/HasHooks.php` — `hookName()` method behavior
- Direct code inspection of `src/Utils/Hooks.php` — `Hooks::name()` logic
- Direct code inspection of `modules/VideoBlock/VideoBlockRenderer.php` — `buildPlayButton()` method to be modified
- Direct code inspection of `modules/VideoBlock/VideoBlock.php` — current `HOOK_SUFFIX = 'video-block'`
- `.planning/phases/04-cross-cutting-concerns-extensibility/04-CONTEXT.md` — all design decisions

### Secondary (MEDIUM confidence)
- YouTube IFrame API docs: `onStateChange` constants (`YT.PlayerState.PLAYING = 1`, `PAUSED = 2`, `ENDED = 0`), `getCurrentTime()` returns seconds synchronously, `getDuration()` returns seconds synchronously
- Vimeo Player SDK docs: `.on('play')`, `.on('pause')`, `.on('ended')` events; `getCurrentTime()` and `getDuration()` return Promises

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already in use, verified via direct code inspection
- Architecture: HIGH — patterns derived from existing code; no new dependencies
- Pitfalls: HIGH — HOOK_SUFFIX issue and Vimeo async API directly observed in source code

**Research date:** 2026-03-10
**Valid until:** 2026-04-10 (stable codebase; no fast-moving dependencies)
