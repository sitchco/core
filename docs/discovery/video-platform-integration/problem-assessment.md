# Problem Assessment: Video Block Platform Integration

## Summary

The video block operates in isolation — it can play videos inline and in modals, but has no coordination with the rest of the platform. Integrating it requires building on `sitchco.hooks` (a private `@wordpress/hooks` instance), working around a confirmed gap in UIModal's Escape-key handling, and creating a player registry for inline videos (which currently have no external pause mechanism). A planned TagManager module (`tag-manager/scenario-spec.md`) will subscribe to platform hooks and bridge them to `dataLayer` — the video block's role is to fire well-structured hook events, not to push analytics directly. No cross-block coordination exists anywhere in the platform today; the video block integration will set the pattern.

## The Hooks System

`sitchco.hooks` is a thin namespacing wrapper around `window.wp.hooks.createHooks()`, creating an isolated hook store separate from WordPress's global hooks. Source: `modules/UIFramework/assets/scripts/hooks.js:23`.

### API Surface

The wrapper rearranges `@wordpress/hooks`'s native argument signature for convenience (`hooks.js:37-38`):

```js
// @wordpress/hooks native:  addAction(hookName, namespace, callback, priority)
// sitchco wrapper:           addAction(hookName, callback, priority, subNamespace)
```

The `subNamespace` (4th arg, optional) creates internal namespaces like `sitchco/video-block` for targeted removal via `removeAction`. It does not namespace the hook name itself — `doAction('ui-modal-show')` fires all listeners regardless of their `subNamespace`.

`doAction`, `doActionAsync`, `applyFilters`, and `applyFiltersAsync` are raw passthroughs from the underlying hooks instance — no wrapping or namespacing applied.

### Priority System

- Default priority: **10** for direct `addAction()` calls
- Lifecycle helpers (`sitchco.register()`, etc.): **100** (`main.js:13-15`)
- Lower number = runs first (same as WordPress PHP hooks)

### Lifecycle

On `DOMContentLoaded`, three phases fire synchronously (`main.js:39-47`):

1. `doAction('init')` — theme config (filters, settings)
2. `doAction('initRegister')` — component registration (`sitchco.register()` callbacks)
3. `doAction('initReady')` — post-registration (CSS vars, scroll-watch)

`sitchco.register(fn)` is syntactic sugar for `addAction('initRegister', fn, 100)`.

### `sitchco.loadScript()`

Promise-based deduplicating script loader (`lib/script-registration.js:13-35`). Multiple calls with the same name return the same cached Promise. The video block uses this for YouTube IFrame API and Vimeo Player SDK (`view.js:53, 136`). No retry on failure — a rejected promise is cached permanently.

### Hook Naming Conventions

| Pattern | Examples |
|---------|----------|
| UI component actions (kebab) | `ui-modal-show`, `ui-popover-toggle` |
| Lifecycle (camelCase) | `init`, `initRegister`, `initReady` |
| Platform events (camelCase) | `layout`, `scroll`, `scrollEnd` |
| Keyboard events (dot) | `key.tab`, `key.return`, `key.esc` |
| Config filters (dot) | `css-vars.register`, `content-slider.config` |

The proposed `video-play`, `video-pause`, `video-ended` names follow the UI component action pattern.

### No Video-Specific Hooks Exist Yet

The video block currently fires `doAction('ui-modal-show', modal)` to open modals and listens to the same hook at priority 20. It fires **no video-specific actions** — no `video-play`, `video-pause`, or `video-ended`. This is what the integration must add.

## UIModal Lifecycle

UIModal uses native `<dialog>.showModal()` — not a custom implementation (`UIModal/main.js:76`). Focus trapping, Escape key handling, and backdrop behavior are browser-native.

### Open Path

`doAction('ui-modal-show', modal)` fires the core handler at priority 10:
1. Guard against double-open
2. Close any currently-open modal via `hideModal()` (built-in mutual exclusion)
3. Set `aria-labelledby` from first heading
4. Sync URL hash via `history.replaceState`
5. Set `aria-expanded` on triggers
6. Lock body scroll
7. Call `modal.showModal()`

The video block's `handleModalShow` runs at priority 20 — after the dialog is already open — and creates/resumes the player (`view.js:410`).

Hash-sync opens (`syncModalWithHash()` at `main.js:34-52`) fire the same `doAction('ui-modal-show')`, so deep-linked modals work identically to click-triggered ones. This runs on `DOMContentLoaded` and `hashchange`.

### The Escape-Key Gap

**Confirmed:** `ui-modal-hide` does NOT fire when the user presses Escape. The comment at `view.js:417-418` is accurate.

| Close method | Fires `ui-modal-hide`? | Fires native `close`? |
|---|---|---|
| Close button / backdrop click | Yes | Yes |
| Programmatic `hideModal()` | Yes | Yes |
| Hash navigation away | Yes | Yes |
| **Escape key** | **No** | **Yes** |

When the user presses Escape, the browser fires `cancel` → `close` directly on the `<dialog>`, bypassing `hideModal()` entirely. The `cancel` handler (`main.js:100-103`) only prevents Escape when `--blockdismiss` is active.

The video block works around this by listening to the native `close` event (`view.js:419-431`), which fires for all close methods uniformly. This is the correct approach given the gap.

**Implication:** Any new coordination that needs to respond to modal close (e.g., firing `video-pause` when a modal closes) must also use the native `close` event — or UIModal must be fixed to fire `ui-modal-hide` from within the `close` listener.

### Hook Arguments

Both `ui-modal-show` and `ui-modal-hide` pass a single argument: the raw `<dialog>` DOM element. No wrapper object.

### Other Consumers

The video block is the **only** module that hooks into `ui-modal-show` or `ui-modal-hide` beyond UIModal itself. No other module in sitchco-core or the parent theme listens to modal lifecycle hooks.

## Player State Tracking

### Modal Players: Tracked

`modalPlayers` is a module-level `Map()` (`view.js:31`) keyed by `modalId`, storing `{ player, provider, loading }`. Modal player references are set in the `onReady` callback for YouTube (`view.js:114-118`) and in the `.ready()` promise for Vimeo (`view.js:182-186`). This enables pause on close and resume on reopen.

### Inline Players: Not Tracked

`handlePlay()` (`view.js:298-325`) creates a player container, calls `createYouTubePlayer()` or `createVimeoPlayer()`, and discards the player reference. For YouTube, `new YT.Player()` is called without capturing the return value (`view.js:66`). For Vimeo, the player is a local variable that goes out of scope (`view.js:147`).

**There is no inline player registry.** Once an inline video starts playing, no external system can pause it. The player exists in the DOM (as an iframe) but no JavaScript reference is retained. This is a prerequisite gap for:
- Mutual exclusion (pausing inline video A when inline video B starts)
- `video-pause` action handler for inline videos
- Analytics tracking (play, pause, progress, end events)

### No Player State Events

Neither provider has state change handlers wired up:
- **YouTube:** Only `onReady` is registered (`view.js:77-81`). No `onStateChange` handler exists for `PLAYING` (1), `PAUSED` (2), or `ENDED` (0).
- **Vimeo:** Only `.ready()` promise is used (`view.js:181-193`). No `play`, `pause`, `ended`, or `timeupdate` event listeners.

Adding state events is required for both mutual exclusion (`video-play` / `video-pause` hooks) and analytics (progress milestones, end tracking).

## Cross-Block Coordination

### Current State: None

The only cross-module coordination in the platform is VideoBlock reacting to UIModal hooks — and even this is unidirectional (video listens to modal, modal has no awareness of video). No theme-level blocks coordinate with plugin-level blocks at runtime.

### Content Slider

The content slider (`sitchco-parent-theme/modules/ContentSlider/blocks/content-slider/script.js`) wraps Kadence columns in Splide slides. It:
- Registers via `sitchco.register(initAllSliders)` at priority 100
- Applies a `content-slider.config` filter for config customization (`script.js:44`)
- Creates a local `const splide` and calls `splide.mount()` (`script.js:56-58`)
- **Does not store the Splide instance** — the reference is local to `initSlider()` and lost after mount
- **Fires zero lifecycle hooks** — no `doAction` on slide change, no event bridge
- **Has no awareness of child content** — renders `kadence/column` blocks generically

Splide natively provides `move`, `moved`, `active`, `inactive` events, but none are wired up. Adding slide-change coordination requires modifying `script.js` to retain the Splide instance and bridge Splide events into `sitchco.hooks`.

### The Coupling Design Question

The design spec explicitly names video-specific hooks:

> N3: External code (e.g., a carousel module) may pause the video by calling `sitchco.hooks.doAction('video-pause', id)`.

This means the slider would call `video-pause` directly — requiring it to know about the video block's hook name and to find video elements via DOM traversal.

An alternative is a generic `content-hidden` action (slider fires it with the slide element; video block listens and self-pauses). This is architecturally looser but has no current consumers beyond video, and the spec explicitly puts carousel auto-pause out of scope for v1. The spec's approach (`video-pause` as an extension point) is pragmatic: no other blocks have "playing" state, so a generic pattern would have exactly one consumer.

### DOM Traversal Path (for future slider integration)

```
.splide__slide (the slide being hidden)
  └── .kb-column (kadence column)
      └── .sitchco-video[data-url] (video wrapper)
```

Inline playing videos have class `sitchco-video--playing`. Modal triggers have `data-modal-id`. The actual modal player lives inside the `<dialog>` in `wp_footer`, not inside the slide.

## Analytics / GTM

### Planned TagManager Module

A TagManager module is specified but not yet built (`tag-manager/scenario-spec.md`). Its core architecture principle: **tag-manager is a subscriber, not a publisher** (Axiom 1). Consuming modules emit domain-level events via `sitchco.hooks`; tag-manager translates them into `dataLayer` pushes. Other modules do not depend on tag-manager.

This confirms the video block's role: fire hooks with structured payloads. The video block should not call `dataLayer.push()` directly.

### Hook Constants

Three hook name constants are defined in `modules/UIFramework/assets/scripts/lib/constants.mjs:29-33`:

```js
export const GA_EVENT = 'gaEvent';
export const GTM_INTERACTION = 'dataLayerInteraction';
export const GTM_STATE = 'dataLayerState';
```

These are exported globally via `sitchco.constants` but are currently dead code — never imported, fired, or listened to. The TagManager spec names `GTM_INTERACTION` and `GTM_STATE` as the intended inter-module communication channel (Axiom 3), confirming these are the correct hooks for video events to fire.

### Event Schema: Multiple Named Events

The TagManager spec establishes a **multiple named events** pattern: each interaction type fires its own named event (`site_click`, `modal_open`, `modal_close`, `gform_submit`, `hash_change`). Video events would follow this pattern with their own event names.

### GA4 Reserved Name Constraint

The TagManager spec (Constraint 5) explicitly lists GA4 reserved event names that must be avoided: `video_start`, `video_progress`, `video_complete`. The video block's analytics event names **cannot use these standard GA4 names**. Alternative names must be chosen (e.g., `video_play`, `video_milestone`, `video_end` — or names that fit the platform's own naming convention).

### The Escape-Key Gap Affects TagManager

The TagManager spec's S9 scenario says modal close tracking subscribes to `ui-modal-hide`. But `ui-modal-hide` does not fire on Escape (see UIModal Lifecycle section above). This means **S9 will miss Escape-key closes** unless UIModal is fixed or TagManager uses the native `close` event instead. This gap should be addressed before or during TagManager implementation.

### No Current Analytics Implementation

No `dataLayer.push()` calls, no GTM container snippet, no analytics module exist anywhere in sitchco-core or the parent theme. GTM is likely injected externally. The Gravity Forms bridge (`main.js:64-66`) — which translates a jQuery event into `doAction(GFORM_CONFIRM, formId)` — is the closest existing precedent for the producer pattern.

### Provider SDK Capabilities for Progress Tracking

- **YouTube:** No native progress event. Requires polling via `player.getCurrentTime()` / `player.getDuration()` with a `setInterval` while `state === PLAYING`.
- **Vimeo:** Has a `timeupdate` event that fires with `{ seconds, duration, percent }` — milestone detection is trivial.

A unified polling approach (consistent interval for both providers) is simpler. Each milestone (25/50/75/100%) should fire exactly once per video session using a `Set` of fired thresholds.

## Open Questions

- **Should UIModal's Escape gap be fixed?** Fixing it (firing `ui-modal-hide` from the native `close` listener) is a small change with platform-wide benefit. It would fix the video block's workaround need AND the TagManager S9 gap. But it could have side effects on any code that assumes `ui-modal-hide` means "programmatic close only."
- **What event names should video analytics use?** GA4 reserves `video_start`, `video_progress`, `video_complete`. The platform's multiple-named-events pattern needs video-specific names that avoid these reservations.
- **Should progress milestones reset on replay?** The spec doesn't address this.
- **Is GTM present on production pages?** No GTM code exists in the repo. It may be injected externally.

## Recommended Focus

1. **Inline player registry** — Create a Map for inline player references (analogous to `modalPlayers`). This unblocks mutual exclusion, `video-pause` handler, and analytics. Without it, inline videos are uncontrollable after play.

2. **Player state events** — Wire up `onStateChange` (YouTube) and `play`/`pause`/`ended` (Vimeo) handlers. These are the source of truth for firing `video-play`, `video-pause`, `video-ended` hooks and analytics events.

3. **Video-specific hooks** — Implement `video-play`, `video-pause`, `video-ended` as `sitchco.hooks.doAction()` calls. Include a `video-pause` listener so external code (carousel, other videos) can request pause.

4. **Mutual exclusion** — When `video-play` fires, pause any other playing video (both inline and modal). This composes with the UIModal Escape gap — modal close already pauses via native `close` event; the `video-pause` hook adds the cross-video coordination layer.

5. **Analytics hooks** — Fire `doAction(GTM_INTERACTION, eventData)` from player state handlers. Design event names that avoid GA4 reservations (`video_start`, `video_progress`, `video_complete` are off-limits). The TagManager module will subscribe and bridge to `dataLayer` — the video block should not push to `dataLayer` directly.

6. **Content slider coordination** — Out of scope for v1 per the spec. The `video-pause` hook is the extension point; the slider can adopt it independently.
