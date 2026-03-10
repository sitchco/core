# Phase 3: Modal Integration & Triggers - Context

**Gathered:** 2026-03-09
**Status:** Ready for planning

<domain>
## Phase Boundary

Videos can play in a modal dialog via UIModal composition, with decoupled triggers and deep linking. Modal-only blocks render no visible page element — only a `<dialog>` in `wp_footer`. No mutual exclusion, analytics, or extension hooks (Phase 4).

</domain>

<decisions>
## Implementation Decisions

### Modal play experience
- Click play on modal-mode poster → modal opens → SDK loads → video autoplays. One interaction = video playing.
- Adaptive loading state inside modal:
  - If oEmbed thumbnail was the page poster (already cached by browser) → show thumbnail + spinner overlay in modal during SDK load
  - If InnerBlocks were used as page poster (thumbnail not cached) → dark background + spinner in modal during SDK load
- Modal poster always uses oEmbed thumbnail `<img>`, never InnerBlocks content (InnerBlocks are arbitrary block content, not safe to duplicate into a dialog)

### On-page appearance by mode
- Modal-mode posters look identical to inline-mode posters — same poster + play icon, no visual indicator of modal behavior. Mode difference is what happens on click, not how it looks.
- Poster stays unchanged after modal close — no visual state change, clicking play again reopens modal and resumes
- Modal-only blocks render zero visible HTML on the page. Only a `<dialog>` in `wp_footer`. Triggers are separate elements (links, buttons) authored elsewhere with `href="#modal-id"` or `data-target="#modal-id"`

### Deep link & resume behavior
- Deep link autoplay: navigating to `#video-modal-id` opens modal and starts playing automatically. UIModal's `syncModalWithHash()` opens the modal; video block adds autoplay on top.
- Auto-resume on reopen: closing modal pauses video; reopening auto-resumes from where they left off
- Hash cleared on modal close — UIModal already handles this via `syncModalWithHash()`
- All three dismiss methods (backdrop click, close button, Escape) handled by UIModal — video block listens for modal close event to pause player

### Modal sizing & layout
- Max-width (e.g., 960px or 80vw) with centered positioning and dark backdrop
- Aspect ratio determined from oEmbed metadata (width/height from response) — supports both landscape (16:9) and portrait (9:16) videos
- Consistent with Phase 2 approach where poster aspect ratio comes from oEmbed
- `ModalType::VIDEO` CSS handles the video-specific modal styling

### Claude's Discretion
- Exact max-width value and responsive breakpoints for video modal
- Spinner implementation (CSS animation, SVG, etc.)
- How to detect whether oEmbed thumbnail was used as page poster (for adaptive loading state)
- Internal structure of modal play logic in view.js
- How to pass video config (url, provider, aspect ratio) from render.php to the dialog content
- Error handling for SDK load failures inside modal

</decisions>

<specifics>
## Specific Ideas

- All modal infrastructure (triggers, hash sync, ARIA labeling, focus management, scroll locking, dismiss handling) is already built in UIModal — the video block should compose with it, not rebuild any of it
- The user explicitly noted that backdrop click close, hash cleanup, and ARIA labeling should be handled by UIModal, not the video component — keep the integration surface minimal
- Player persistence (MODL-05/MODL-06): iframe stays in DOM when modal closes, player pauses via SDK API. Reopening calls play via SDK API. No iframe recreation.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- **UIModal.php `loadModal(ModalData)`**: Queues modal for `wp_footer` rendering. Video block calls this in render.php for modal/modal-only modes.
- **ModalData constructor**: `(string $id, string $heading, string $content, ModalType $type)` — accepts arbitrary HTML content. No post dependency.
- **ModalType::VIDEO**: Already exists in enum. Applies video-specific CSS to the `<dialog>`.
- **UIModal JS `showModal()`**: Opens dialog, triggers `ui-modal-show` action hook. Video block can listen for this to start SDK loading.
- **UIModal JS `hideModal()`**: Closes dialog, triggers `ui-modal-hide` action hook. Video block listens to pause player.
- **UIModal JS `syncModalWithHash()`**: Opens modal on page load if URL hash matches. Already handles hash clearing on close.
- **UIModal JS `getTriggersForModal()`**: Finds all `a[href="#id"]` and `[data-target="#id"]` elements automatically.
- **sitchco.loadScript()**: Promise-based SDK loader with deduplication. Already used by view.js for YouTube IFrame API and Vimeo Player SDK.
- **sitchco.hooks**: Action system for `ui-modal-show`/`ui-modal-hide` hooks.

### Established Patterns
- **SDK loading**: YouTube via `sitchco.loadScript('youtube-iframe-api', ...)`, Vimeo via `sitchco.loadScript('vimeo-player', ...)` — same pattern for modal playback.
- **Data attributes on wrapper**: `data-url`, `data-provider`, `data-display-mode`, `data-modal-id` — view.js reads these for initialization.
- **oEmbed caching**: Transients with `sitchco_voembed_` prefix and 30-day TTL. Aspect ratio (width/height) available from cached response.
- **BEM CSS**: `sitchco-video__*` prefix for all elements. Modal-specific classes like `sitchco-video__modal-player`.

### Integration Points
- **render.php**: Needs branching by displayMode — inline (current), modal (poster on page + dialog in footer), modal-only (dialog in footer only)
- **view.js**: Remove `if (displayMode !== 'inline') return;` gate. Add modal play logic: listen for `ui-modal-show`, load SDK inside dialog, handle pause/resume on close/reopen.
- **UIModal hooks**: `addAction('ui-modal-show', callback)` and `addAction('ui-modal-hide', callback)` for video lifecycle.
- **wp_footer**: UIModal's `unloadModals()` renders all queued `<dialog>` elements. Video block queues via `loadModal()` during render.

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 03-modal-integration-triggers*
*Context gathered: 2026-03-09*
