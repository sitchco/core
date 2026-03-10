# Phase 3: Modal Integration & Triggers - Research

**Researched:** 2026-03-09
**Domain:** UIModal composition, video player lifecycle in dialog, deep linking
**Confidence:** HIGH

## Summary

Phase 3 extends the video block to support modal and modal-only display modes by composing with the existing UIModal infrastructure. The UIModal module already provides all dialog management (open/close, hash sync, ARIA labeling, focus trapping, triggers, dismiss handling). The video block's job is narrow: (1) branch render.php by displayMode to queue a `<dialog>` via `UIModal::loadModal()`, and (2) extend view.js to handle SDK loading, playback, pause, and resume within the modal context.

The critical technical challenges are player lifecycle management (pause on close, resume on reopen, no duplicate iframes) and the interaction between the hooks-based UIModal event system and native `<dialog>` events. Research into the UIModal JS source reveals that the `ui-modal-show` action hook is reliable for detecting all modal opens, but `ui-modal-hide` is NOT fired on Escape-key close -- only the native `<dialog>` `close` event fires universally. The video block must use the native `close` event listener on the dialog element for reliable pause behavior.

**Primary recommendation:** Compose with UIModal by calling `loadModal(new ModalData(...))` in render.php and using `sitchco.hooks.addAction('ui-modal-show', ...)` plus native `dialog.addEventListener('close', ...)` in view.js. Store player instances in a Map keyed by modal ID for pause/resume lifecycle.

<user_constraints>

## User Constraints (from CONTEXT.md)

### Locked Decisions
- Click play on modal-mode poster -> modal opens -> SDK loads -> video autoplays. One interaction = video playing.
- Adaptive loading state inside modal: oEmbed thumbnail + spinner if cached, dark background + spinner if not
- Modal poster always uses oEmbed thumbnail `<img>`, never InnerBlocks content
- Modal-mode posters look identical to inline-mode posters -- same poster + play icon, no visual indicator of modal behavior
- Poster stays unchanged after modal close -- no visual state change, clicking play again reopens modal and resumes
- Modal-only blocks render zero visible HTML on the page. Only a `<dialog>` in `wp_footer`.
- Deep link autoplay: navigating to `#video-modal-id` opens modal and starts playing automatically via UIModal's `syncModalWithHash()`
- Auto-resume on reopen: closing modal pauses video; reopening auto-resumes
- Hash cleared on modal close -- UIModal already handles this
- All three dismiss methods (backdrop click, close button, Escape) handled by UIModal
- Max-width (e.g., 960px or 80vw) with centered positioning and dark backdrop
- Aspect ratio determined from oEmbed metadata (width/height from response)
- `ModalType::VIDEO` CSS handles video-specific modal styling
- All modal infrastructure is already built in UIModal -- video block composes, does not rebuild
- Player persistence: iframe stays in DOM when modal closes, player pauses via SDK API. Reopening calls play via SDK API. No iframe recreation.

### Claude's Discretion
- Exact max-width value and responsive breakpoints for video modal
- Spinner implementation (CSS animation, SVG, etc.)
- How to detect whether oEmbed thumbnail was used as page poster (for adaptive loading state)
- Internal structure of modal play logic in view.js
- How to pass video config (url, provider, aspect ratio) from render.php to the dialog content
- Error handling for SDK load failures inside modal

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope.

</user_constraints>

<phase_requirements>

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| MODL-01 | Click on play target opens `<dialog>` via UIModal's `showModal()` | render.php queues dialog via `loadModal()`; view.js click handler calls `sitchco.hooks.doAction('ui-modal-show', dialogEl)` |
| MODL-02 | URL hash updates to `#modal-id` when modal opens | UIModal's `showModal` action handler already calls `history.replaceState(null, '', '#' + modal.id)` at priority 10 |
| MODL-03 | Provider SDK loads and iframe is created inside dialog's content area | view.js reuses existing `loadYouTubeAPI()`/`loadVimeoSDK()` + creates iframe inside modal content div |
| MODL-04 | Poster on the page remains visible (not hidden/swapped) when modal opens | Modal mode click handler does NOT add `sitchco-video--playing` class (unlike inline mode) |
| MODL-05 | Player pauses (not destroyed) when modal closes -- iframe remains in DOM | Native `close` event listener calls `player.pauseVideo()` (YT) or `player.pause()` (Vimeo); iframe stays in DOM |
| MODL-06 | Reopening same modal resumes existing player -- no duplicate iframes | Player Map check: if player exists for modal ID, call `player.playVideo()`/`player.play()` instead of creating new |
| MODL-07 | Modal-only mode renders no visible frontend element -- only `<dialog>` in `wp_footer` | render.php returns empty string (no HTML output) for modal-only, only calls `loadModal()` |
| MODL-08 | Modal-only block with no trigger renders inert dialog -- no errors | UIModal renders dialog regardless of triggers; no trigger = dialog never opens, no JS errors |
| TRIG-01 | Any `<a href="#id">` or `[data-target="#id"]` triggers the video modal | UIModal's `getTriggersForModal()` + delegated click handler already handles this |
| TRIG-02 | Multiple triggers for same modal ID all work correctly | UIModal's `querySelectorAll` returns all matching triggers; delegated click handles any of them |
| TRIG-03 | Direct URL navigation with `#video-modal-id` hash opens modal on page load | UIModal's `syncModalWithHash()` on DOMContentLoaded handles this; video block hooks `ui-modal-show` to autoplay |
| TRIG-04 | Modal ID is stable, human-readable (slugified video title by default) | Editor auto-generates via `slugify()`; render.php passes `modalId` attribute; ModalData's `sanitize_title()` validates |
| ACCS-04 | Modal `<dialog>` has `aria-labelledby` referencing heading with video title | UIModal's `setModalLabel()` sets `aria-labelledby` from first heading; ModalData heading passed as video title |

</phase_requirements>

## Standard Stack

### Core (Already in Project)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| UIModal module | in-project | Dialog management, triggers, hash sync, ARIA | Existing project infrastructure -- composition target |
| sitchco.hooks | in-project | WordPress-style JS hook system | Used by UIModal for `ui-modal-show`/`ui-modal-hide` actions |
| sitchco.loadScript() | in-project | Promise-based SDK loader with dedup | Already used in view.js for YouTube/Vimeo SDK loading |
| YouTube IFrame API | latest | Video playback control | `pauseVideo()`/`playVideo()` for pause/resume lifecycle |
| Vimeo Player SDK | latest | Video playback control | `pause()`/`play()` (Promise-based) for pause/resume lifecycle |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ModalData (PHP) | in-project | Value object for modal config | Construct with `(id, heading, content, ModalType::VIDEO)` in render.php |
| ModalType::VIDEO (PHP) | in-project | Video-specific modal CSS class | Adds `sitchco-modal--video` class to dialog element |

### No New Dependencies
This phase requires zero new libraries. Everything is built on existing project infrastructure.

## Architecture Patterns

### Recommended Changes to Existing Files

```
modules/VideoBlock/blocks/video/
  render.php           # ADD: modal/modal-only branching, loadModal() calls
  view.js              # ADD: modal play/pause/resume logic, remove inline-only gate
  style.css            # ADD: video modal sizing, spinner, modal player layout
```

### Pattern 1: render.php Display Mode Branching
**What:** Branch render output by `displayMode` attribute: inline (current), modal (poster + dialog), modal-only (dialog only)
**When to use:** All three display modes share oEmbed resolution and play icon generation but diverge in HTML output
**Key insight:** Modal and modal-only modes both call `UIModal::loadModal()` to queue the dialog. The difference is whether the poster HTML is rendered on the page.

```php
// Source: existing modal/block.php pattern + ModalData constructor
$container = $GLOBALS['SitchcoContainer'];
$uiModal = $container->get(UIModal::class);

// Build dialog content HTML (player container + thumbnail for loading state)
$modal_content = sprintf(
    '<div class="sitchco-video__modal-player" data-url="%s" data-provider="%s" data-video-id="%s">
        <img src="%s" alt="" width="%s" height="%s" class="sitchco-video__modal-poster-img">
        <div class="sitchco-video__spinner"></div>
    </div>',
    esc_attr($url),
    esc_attr($provider),
    esc_attr($video_id),
    esc_url($oembed->thumbnail_url ?? ''),
    esc_attr($oembed->width ?? ''),
    esc_attr($oembed->height ?? ''),
);

$modal_data = new ModalData($modal_id, $video_title, $modal_content, ModalType::VIDEO);
$uiModal->loadModal($modal_data);
```

### Pattern 2: Player Instance Map for Lifecycle Management
**What:** Store player instances in a `Map<string, {player, provider}>` keyed by modal ID
**When to use:** Essential for pause/resume without recreating iframes
**Key insight:** On first open, SDK loads and player is created and stored. On subsequent opens, stored player is used for `playVideo()`/`play()`.

```javascript
// Source: pattern derived from YouTube IFrame API + Vimeo Player SDK docs
var players = new Map(); // modalId -> { player, provider }

function handleModalShow(modal) {
    var playerContainer = modal.querySelector('.sitchco-video__modal-player');
    if (!playerContainer) return;

    var modalId = modal.id;
    if (players.has(modalId)) {
        // Resume existing player
        var entry = players.get(modalId);
        if (entry.provider === 'youtube') {
            entry.player.playVideo();
        } else {
            entry.player.play();
        }
        return;
    }

    // First open: create player (SDK loads via existing helpers)
    // ... createModalPlayer(playerContainer, modalId)
}
```

### Pattern 3: Native close Event for Universal Pause
**What:** Listen to the native `<dialog>` `close` event for pausing video on modal dismiss
**When to use:** Must catch all close methods (Escape, backdrop, close button, programmatic)

**Critical finding:** The `ui-modal-hide` hook is only fired by programmatic close (backdrop click, close button). Pressing Escape triggers the native `cancel` event on `<dialog>`, which (if not prevented) leads to the native `close` event. UIModal does NOT fire `ui-modal-hide` for Escape closes. Therefore:
- Use `ui-modal-show` hook (reliable for all opens) to detect opens
- Use native `dialog.addEventListener('close', ...)` to detect ALL closes

```javascript
// Source: UIModal main.js analysis
// Register on DOMContentLoaded -- find all video modal dialogs
document.querySelectorAll('dialog.sitchco-modal--video').forEach(function (modal) {
    modal.addEventListener('close', function () {
        var entry = players.get(modal.id);
        if (!entry) return;
        if (entry.provider === 'youtube') {
            entry.player.pauseVideo();
        } else {
            entry.player.pause();
        }
    });
});
```

### Pattern 4: Data Transfer from PHP to JS via Dialog Content
**What:** Embed video config as data attributes on an element inside the dialog's content area
**When to use:** The dialog is rendered in `wp_footer` by UIModal; view.js reads data attributes from within it

```html
<!-- Rendered inside dialog by UIModal via ModalData content -->
<div class="sitchco-video__modal-player"
     data-url="https://youtube.com/watch?v=..."
     data-provider="youtube"
     data-video-id="dQw4w9WgXcQ">
    <!-- Loading state shown until SDK ready -->
    <img src="https://img.youtube.com/..." alt="" class="sitchco-video__modal-poster-img">
    <div class="sitchco-video__spinner"></div>
</div>
```

### Pattern 5: Modal-Mode Poster Click Handler
**What:** For modal-mode blocks (not modal-only), clicking the poster opens the modal instead of loading the player inline
**When to use:** When `displayMode === 'modal'`

```javascript
// Modal mode: click poster -> open modal (not inline play)
if (displayMode === 'modal') {
    var modalId = wrapper.dataset.modalId;
    var modal = document.getElementById(modalId);
    if (modal) {
        clickTarget.addEventListener('click', function (e) {
            e.preventDefault();
            sitchco.hooks.doAction('ui-modal-show', modal);
        });
    }
}
```

### Anti-Patterns to Avoid
- **Rebuilding UIModal functionality:** Do not implement hash sync, focus trapping, scroll lock, backdrop dismiss, or ARIA labeling in the video block -- UIModal handles all of this.
- **Using `ui-modal-hide` hook for pause:** This hook does NOT fire on Escape close. Use native `close` event instead.
- **Destroying iframe on close:** The requirement is pause, not destroy. Keep the iframe in DOM for instant resume.
- **Creating player before modal opens:** SDK should only load when modal opens for the first time. Do not preload.
- **Using `{ once: true }` on modal click handlers:** Unlike inline mode, modal play handlers must fire on every click (reopen).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Dialog open/close | Custom dialog management | `UIModal.showModal()`/`hideModal()` via hooks | Focus trapping, scroll lock, backdrop, keyboard -- all handled |
| Hash sync | `hashchange` listener + `replaceState` | UIModal's `syncModalWithHash()` | Already handles open on load, clear on close, hash-away close |
| ARIA labeling | Manual `aria-labelledby` setup | UIModal's `setModalLabel()` | Automatically finds first heading, sets ID, links `aria-labelledby` |
| Trigger decoration | Custom trigger finding/ARIA | UIModal's `getTriggersForModal()` | Adds `aria-haspopup`, `aria-expanded`, `role="button"`, keyboard support |
| Focus restoration | Save/restore focus on close | Native `<dialog>.showModal()` | Browser handles focus restoration natively |
| SDK loading | Inline `<script>` tags | `sitchco.loadScript()` | Deduplication, promise-based, error handling |

**Key insight:** The UIModal module provides ~200 lines of battle-tested dialog management. The video block should treat it as a black box and compose through the defined interfaces (PHP `loadModal()`, JS hooks, CSS `--video` modifier class).

## Common Pitfalls

### Pitfall 1: Escape Key Close Not Firing ui-modal-hide
**What goes wrong:** Video continues playing when user presses Escape because pause handler was attached to `ui-modal-hide` hook
**Why it happens:** UIModal's `ui-modal-hide` action is only dispatched by programmatic calls (backdrop click, close button). Escape triggers the native `cancel` -> `close` event chain without going through the hook system.
**How to avoid:** Use native `dialog.addEventListener('close', pauseHandler)` which fires for ALL close methods
**Warning signs:** Video plays audio after pressing Escape but not after clicking X or backdrop

### Pitfall 2: Duplicate Player Iframes on Reopen
**What goes wrong:** Each time modal opens, a new iframe/player is created, stacking multiple players
**Why it happens:** Not checking whether a player already exists for this modal before creating one
**How to avoid:** Maintain a `Map<modalId, playerEntry>` and check `players.has(modalId)` before creating a new player. On reopen, call `play()` on existing player.
**Warning signs:** Multiple iframes visible in dialog, audio overlap

### Pitfall 3: YouTube Player Constructor Not Returning Storable Reference
**What goes wrong:** `new YT.Player()` is called but the return value is not stored, making pause/resume impossible
**Why it happens:** Current inline view.js does not store the player reference (not needed for inline where play is one-shot)
**How to avoid:** Always capture the return value: `var player = new YT.Player(container, options)`. For YouTube, the `onReady` event callback receives `event.target` which is the player instance -- store it there for reliability.
**Warning signs:** `player.pauseVideo is not a function` error

### Pitfall 4: Vimeo Player Constructor vs YouTube Player Constructor Differences
**What goes wrong:** Treating Vimeo and YouTube player APIs identically
**Why it happens:** Different APIs -- YouTube uses synchronous methods (`pauseVideo()`), Vimeo uses Promise-based methods (`pause()` returns Promise)
**How to avoid:** Branch on provider in pause/resume logic. Vimeo pause/play return Promises but do not need `.then()` for basic use -- fire-and-forget is fine for pause.
**Warning signs:** Unhandled promise rejections from Vimeo player

### Pitfall 5: Race Condition Between Modal Open and SDK Load
**What goes wrong:** User opens modal, SDK starts loading, user closes before SDK ready, then reopens -- player creation logic runs twice
**Why it happens:** The `ui-modal-show` handler fires again before the first SDK load completes
**How to avoid:** Track loading state per modal. Set a `loading` flag when SDK load starts, check it on subsequent opens. Use the Player Map as the authoritative state.
**Warning signs:** Occasional duplicate iframes, especially on slow connections

### Pitfall 6: render.php Cannot Access $container Directly
**What goes wrong:** Trying to use `$container` variable in video block's `render.php` like the ACF modal block does
**Why it happens:** ACF blocks inject `$container` into their render context. WordPress native blocks using `"render": "file:./render.php"` in block.json only provide `$attributes`, `$content`, `$block`.
**How to avoid:** Access container via `$GLOBALS['SitchcoContainer']`
**Warning signs:** Undefined variable `$container` error in render.php

### Pitfall 7: Aspect Ratio Not Passed to Modal Content
**What goes wrong:** Video modal renders at wrong aspect ratio or no aspect ratio
**Why it happens:** oEmbed width/height is available in render.php but not passed through to the dialog content
**How to avoid:** Include aspect ratio as data attributes or inline styles on the modal player container element inside the ModalData content HTML
**Warning signs:** Video appears squashed or stretched in modal

## Code Examples

### render.php: Full Display Mode Branching

```php
// Source: derived from existing render.php + modal/block.php patterns
use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\ModalType;
use Sitchco\Modules\UIModal\UIModal;

// ... (existing oEmbed resolution, poster HTML, play icon generation) ...

if ($display_mode === 'modal' || $display_mode === 'modal-only') {
    $modal_id = $attributes['modalId'] ?? '';
    if (empty($modal_id)) {
        // Fallback: slugify video title
        $modal_id = sanitize_title($video_title);
    }

    // Build modal content HTML with data attributes for JS
    $thumb_url = ($oembed && !empty($oembed->thumbnail_url)) ? $oembed->thumbnail_url : '';
    $aspect_w = ($oembed && !empty($oembed->width)) ? $oembed->width : 16;
    $aspect_h = ($oembed && !empty($oembed->height)) ? $oembed->height : 9;

    $modal_content = sprintf(
        '<div class="sitchco-video__modal-player" data-url="%s" data-provider="%s" data-video-id="%s" style="aspect-ratio: %s / %s">%s<div class="sitchco-video__spinner"></div></div>',
        esc_attr($url),
        esc_attr($provider),
        esc_attr($video_id),
        esc_attr($aspect_w),
        esc_attr($aspect_h),
        $thumb_url ? sprintf('<img src="%s" alt="" class="sitchco-video__modal-poster-img" width="%s" height="%s">', esc_url($thumb_url), esc_attr($aspect_w), esc_attr($aspect_h)) : ''
    );

    // Queue modal for wp_footer rendering
    $container = $GLOBALS['SitchcoContainer'];
    $uiModal = $container->get(UIModal::class);
    $uiModal->loadModal(new ModalData($modal_id, $video_title, $modal_content, ModalType::VIDEO));

    // Modal-only: render nothing on page
    if ($display_mode === 'modal-only') {
        return;
    }

    // Modal mode: render poster on page with modal trigger data attribute
    $wrapper_attrs['data-modal-id'] = $modal_id;
    // ... (render poster HTML with wrapper, same as inline but no dimension locking behavior) ...
}
```

### view.js: Modal Player Lifecycle (Structural Pattern)

```javascript
// Source: derived from YouTube IFrame API + Vimeo Player SDK + UIModal hooks
var players = new Map(); // modalId -> { player, provider, loading }

sitchco.hooks.addAction('ui-modal-show', function onVideoModalShow(modal) {
    var playerContainer = modal.querySelector('.sitchco-video__modal-player');
    if (!playerContainer) return; // Not a video modal

    var modalId = modal.id;
    var entry = players.get(modalId);

    // Resume existing player
    if (entry && entry.player) {
        if (entry.provider === 'youtube') {
            entry.player.playVideo();
        } else {
            entry.player.play();
        }
        return;
    }

    // Prevent double-creation during SDK load
    if (entry && entry.loading) return;

    // First open: load SDK and create player
    var provider = playerContainer.dataset.provider;
    var videoId = playerContainer.dataset.videoId;
    var url = playerContainer.dataset.url;

    players.set(modalId, { player: null, provider: provider, loading: true });

    // ... (create player using existing createYouTubePlayer/createVimeoPlayer pattern,
    //      but store the player reference in the Map on ready) ...
}, 20, 'video-block');

// Pause on ALL close methods (Escape, backdrop, close button)
sitchco.register(function initVideoModals() {
    document.querySelectorAll('dialog.sitchco-modal--video').forEach(function (modal) {
        modal.addEventListener('close', function () {
            var entry = players.get(modal.id);
            if (!entry || !entry.player) return;
            if (entry.provider === 'youtube') {
                entry.player.pauseVideo();
            } else {
                entry.player.pause();
            }
        });
    });
});
```

### YouTube Player with Reference Storage

```javascript
// Source: YouTube IFrame API reference (https://developers.google.com/youtube/iframe_api_reference)
function createModalYouTubePlayer(container, videoId, startTime, modalId) {
    loadYouTubeAPI().then(function (YT) {
        var iframeContainer = document.createElement('div');
        iframeContainer.className = 'sitchco-video__player';
        container.appendChild(iframeContainer);

        new YT.Player(iframeContainer, {
            videoId: videoId,
            host: 'https://www.youtube-nocookie.com',
            playerVars: {
                autoplay: 1,
                playsinline: 1,
                enablejsapi: 1,
                origin: window.location.origin,
                start: startTime,
                rel: 0,
            },
            events: {
                onReady: function (event) {
                    var entry = players.get(modalId);
                    if (entry) {
                        entry.player = event.target; // Store YT.Player instance
                        entry.loading = false;
                    }
                    // Hide loading state
                    container.classList.add('sitchco-video__modal-player--ready');
                    event.target.playVideo();
                },
            },
        });
    });
}
```

### Vimeo Player with Reference Storage

```javascript
// Source: Vimeo Player SDK (https://developer.vimeo.com/player/sdk/reference)
function createModalVimeoPlayer(container, videoId, startTime, modalId) {
    loadVimeoSDK().then(function () {
        var iframeContainer = document.createElement('div');
        iframeContainer.className = 'sitchco-video__player';
        container.appendChild(iframeContainer);

        var player = new Vimeo.Player(iframeContainer, {
            id: parseInt(videoId, 10),
            autoplay: true,
            dnt: true,
        });

        player.ready().then(function () {
            var entry = players.get(modalId);
            if (entry) {
                entry.player = player; // Store Vimeo Player instance
                entry.loading = false;
            }
            container.classList.add('sitchco-video__modal-player--ready');
            if (startTime > 0) {
                player.setCurrentTime(startTime);
            }
        });
    });
}
```

### CSS: Video Modal Styling

```css
/* Source: derived from existing UIModal CSS custom properties */
.sitchco-modal--video {
    --modal-container-bg: #000;
    --modal-container-color: #fff;
    --modal-container-padding: 1rem;
    --modal-close-color: #fff;
    --modal-close-top: 0.5rem;
    --modal-close-right: 0.5rem;
}

.sitchco-modal--video::backdrop {
    background: rgb(0 0 0 / 0.85);
}

.sitchco-modal--video[open]::backdrop {
    @starting-style {
        background: transparent;
    }
}

.sitchco-video__modal-player {
    position: relative;
    width: 100%;
    max-width: 960px;
    margin: auto;
}

.sitchco-video__modal-poster-img {
    width: 100%;
    height: auto;
    display: block;
}

.sitchco-video__modal-player--ready .sitchco-video__modal-poster-img,
.sitchco-video__modal-player--ready .sitchco-video__spinner {
    display: none;
}

.sitchco-video__modal-player .sitchco-video__player {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.sitchco-video__modal-player .sitchco-video__player iframe {
    width: 100%;
    height: 100%;
    border: 0;
}

/* Spinner */
.sitchco-video__spinner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 48px;
    height: 48px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: sitchco-spin 0.8s linear infinite;
}

@keyframes sitchco-spin {
    to { transform: translate(-50%, -50%) rotate(360deg); }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Custom modal/lightbox JS | Native `<dialog>` with `.showModal()` | 2023+ | Focus trapping, inertness, backdrop all handled by browser |
| JS focus trap libraries | `<dialog>.showModal()` native trapping | Chrome 37+, Safari 15.4+ | Zero-JS focus management |
| `@starting-style` transitions | CSS-native dialog open/close animations | Chrome 117, Safari 17.4 | No JS animation code needed |
| Separate player destroy/recreate | Pause-in-place with iframe persistence | Current best practice | Instant resume, no re-buffering |

## Open Questions

1. **oEmbed thumbnail cache detection for adaptive loading state**
   - What we know: When oEmbed thumbnail was used as the page poster (no InnerBlocks), the browser has it cached. When InnerBlocks were used, the thumbnail may not be cached.
   - What's unclear: How to reliably detect in JS whether the thumbnail is cached. Options: (a) add a data attribute in render.php indicating poster source, (b) always show thumbnail in modal with spinner overlay (cached image loads instantly anyway), (c) use `Image()` constructor to test cache.
   - Recommendation: Simplest approach -- add `data-has-oembed-poster="true"` attribute in render.php when oEmbed thumbnail was the page poster. JS reads this to decide whether to show thumbnail or dark background in modal loading state. Alternatively, always show the thumbnail in the modal -- if cached it appears instantly, if not it loads quickly alongside the SDK.

2. **Autoplay policy on deep link open**
   - What we know: Direct navigation with hash opens the modal via `syncModalWithHash()`. Video should autoplay. Browsers may block autoplay without user gesture.
   - What's unclear: Whether `dialog.showModal()` triggered by hash on page load counts as user-initiated for autoplay policy purposes.
   - Recommendation: Proceed with autoplay. Most browsers allow muted autoplay, and YouTube/Vimeo players have their own autoplay negotiation. If blocked, the player will simply show the first frame paused, which is acceptable degradation.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (via ddev test-phpunit) |
| Config file | existing test infrastructure |
| Quick run command | `ddev test-phpunit --filter VideoBlockTest` |
| Full suite command | `ddev test-phpunit` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| MODL-01 | Modal click opens dialog | manual | Browser test: click poster in modal mode | N/A |
| MODL-02 | URL hash updates on modal open | manual | Browser test: check URL after open | N/A |
| MODL-03 | SDK loads inside dialog | manual | Browser test: inspect iframe in dialog | N/A |
| MODL-04 | Page poster stays visible | manual | Browser test: poster visible behind modal | N/A |
| MODL-05 | Player pauses on modal close | manual | Browser test: close modal, check audio stops | N/A |
| MODL-06 | Reopen resumes without duplicate | manual | Browser test: close+reopen, inspect iframes | N/A |
| MODL-07 | Modal-only renders no visible element | unit | `ddev test-phpunit --filter VideoBlockTest::test_modal_only_renders_no_visible_html` | No -- Wave 0 |
| MODL-08 | Modal-only with no trigger is inert | unit | `ddev test-phpunit --filter VideoBlockTest::test_modal_only_no_trigger_no_errors` | No -- Wave 0 |
| TRIG-01 | href/data-target triggers modal | manual | Browser test: click trigger link | N/A |
| TRIG-02 | Multiple triggers work | manual | Browser test: test two triggers for same ID | N/A |
| TRIG-03 | Hash deep link opens modal | manual | Browser test: navigate to URL with hash | N/A |
| TRIG-04 | Modal ID is stable/readable | unit | `ddev test-phpunit --filter VideoBlockTest::test_modal_id_is_slugified` | No -- Wave 0 |
| ACCS-04 | aria-labelledby references heading | unit | `ddev test-phpunit --filter VideoBlockTest::test_modal_dialog_aria_labelledby` | No -- Wave 0 |

### Sampling Rate
- **Per task commit:** `ddev test-phpunit --filter VideoBlockTest`
- **Per wave merge:** `ddev test-phpunit`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` -- add `test_modal_only_renders_no_visible_html` (MODL-07)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` -- add `test_modal_only_no_trigger_no_errors` (MODL-08)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` -- add `test_modal_id_is_slugified` (TRIG-04)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` -- add `test_modal_dialog_aria_labelledby` (ACCS-04)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` -- add `test_modal_mode_renders_poster_and_dialog` (MODL-01/MODL-04)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` -- update `renderBlock()` helper to support modal dialog rendering via `UIModal::unloadModals()` capture

Note: Most Phase 3 requirements involve JS-driven behavior (player lifecycle, SDK loading, pause/resume) that require browser testing and cannot be automated with PHPUnit. The PHPUnit tests cover render.php output correctness -- ensuring the right HTML structure is produced for each display mode.

## Sources

### Primary (HIGH confidence)
- UIModal module source code (`modules/UIModal/`) -- all PHP, JS, CSS, templates read in full
- VideoBlock module source code (`modules/VideoBlock/`) -- all files read in full
- UIFramework hooks system (`modules/UIFramework/assets/scripts/hooks.js`) -- hook API verified
- ModalData.php, ModalType.php -- constructor signature and enum values verified
- Existing VideoBlockTest.php -- test patterns and helpers verified

### Secondary (MEDIUM confidence)
- [YouTube IFrame API Reference](https://developers.google.com/youtube/iframe_api_reference) -- `playVideo()`, `pauseVideo()`, constructor pattern verified
- [Vimeo Player SDK Reference](https://developer.vimeo.com/player/sdk/reference) -- `play()`, `pause()` Promise-based API verified

### Tertiary (LOW confidence)
- Autoplay policy behavior for hash-triggered dialog opens -- browser-specific, may vary

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all components are existing project code, thoroughly read and verified
- Architecture: HIGH -- integration points clearly defined by UIModal's public API (PHP loadModal, JS hooks, CSS classes)
- Pitfalls: HIGH -- identified through direct source code analysis of UIModal JS event handling (Escape key gap is verified, not hypothetical)

**Research date:** 2026-03-09
**Valid until:** Indefinite (project-internal code, no external version drift)
