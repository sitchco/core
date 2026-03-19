# Scenario Spec: Video Component (`sitchco/video`)

## Axioms

1. **Video URL as block attribute.** The block stores a video URL directly. Provider detection (YouTube, Vimeo) happens at render/runtime from the URL. The URL field is designed for future provider expansion without schema changes.

2. **The poster image is the child block's responsibility.** The video block is a wrapper using InnerBlocks for the poster visual. The child can be core/image, Kadence image, a group with overlays, or anything. The video block does not own image fields, responsive image handling, or overlay controls.

3. **The video block owns the play icon and click-to-play behavior.** The play icon overlay is rendered by the wrapper, positioned over InnerBlocks content via CSS. Position is configurable (X/Y, default centered).

4. **The block lives in sitchco-core.** It is a platform-level concern. It does not depend on theme-level packages.

5. **Modal mode composes with UIModal.** The `<dialog>` is rendered via an extended UIModal method that accepts arbitrary content (not just Posts). Triggering, focus trapping, scroll locking, hash syncing, and ARIA are handled by UIModal.

6. **Any element can trigger a video modal.** The modal has an ID. Any `<a href="#id">` or `[data-target="#id"]` elsewhere on the page triggers it via the existing UIModal system.

7. **Click-to-load.** No provider iframe or SDK loads in the browser until the user explicitly clicks to play. This is a privacy and performance requirement.

8. **The iframe fills 100% of the wrapper.** The block does not implement aspect-ratio logic for the player.

9. **YouTube videos must use the official YouTube branded play icon.** YouTube requires branded play buttons per their API terms of service. YouTube videos render the YouTube play button (dark, light, or red variant). Vimeo and other providers use a generic play icon.

10. **`sitchco.hooks` is the coordination layer.** Mutual exclusion, modal lifecycle, analytics, and external pause requests all flow through `sitchco.hooks`.

11. **Auto-fetch poster from oEmbed is the default experience.** The block inserts with no InnerBlocks. Once a URL is entered, the oEmbed thumbnail is the poster automatically. Authors can optionally add InnerBlocks (any block type) to override the auto-fetched poster with a custom composition. The wrapper checks statelessly: InnerBlocks exist? Use them. No InnerBlocks? Auto-fetch. It never inspects what InnerBlocks contain — only whether they exist.

12. **Provider SDKs are the playback interface.** The YouTube IFrame API and Vimeo Player SDK are used for playback control. They load on-demand when the user first clicks play (not on page load). SDKs enable programmatic play/pause, progress tracking, and mutual exclusion — all non-negotiable requirements.

13. **Native Gutenberg block.** This is a native WordPress block (not ACF), registered via `block.json` with a React `edit` component. The InnerBlocks flexibility, conditional inspector UI, and oEmbed preview fetching require full control over the editor experience. This is the first native block in sitchco-core and establishes the pattern for future blocks that need rich editor interactions beyond what ACF block mode provides.

14. **oEmbed metadata populates block defaults.** When a URL is entered, the oEmbed response provides the video title and thumbnail. The title auto-populates the block's title attribute (used for accessibility labels and modal headings). The modal ID auto-generates as a slugified version of the title regardless of display mode, so the value is ready if the author switches to a modal mode later. Both are editable. This ensures accessibility and deep-linking work out of the box with zero additional author effort.

## Chosen Approach

Single `sitchco/video` native Gutenberg wrapper block in sitchco-core with three display modes (inline, modal, modal-only). Default experience is zero-config: paste a URL and the oEmbed thumbnail becomes the poster with a play icon, the video title populates from oEmbed metadata, and in modal modes the modal ID derives from the title. Authors can optionally add InnerBlocks to override the poster with any custom block composition. Composes with UIModal for modal playback. Provider detection from the URL attribute determines play icon branding, embed configuration, and which SDK to load. Provider SDKs (YouTube IFrame API, Vimeo Player SDK) load on first click to enable playback control, mutual exclusion, and analytics.

## Prerequisites

### Phase Zero: UIModal Content-Based Modal Support (Complete)

`ModalData` was decoupled from `Post` and accepts primitives directly. The `ModalType` enum was replaced with an extensible string-based type registry on `UIModal`. VideoBlock registers a `'video'` type and creates modals via:

```php
$this->uiModal->registerType('video');
// ...
$modalData = new ModalData(string $id, string $heading, string $content, 'video');
$uiModal->loadModal($modalData);
```

`ModalData::fromPost()` provides backward compatibility for post-backed modals. Type resolution in `loadModal()` falls back unregistered types to `'box'`.

## Scenarios

### Authoring

#### A1. Author inserts video block

**Trigger:** Author adds `sitchco/video` from the block inserter.  
**Expected:** Block appears empty (no InnerBlocks). Inspector panel prompts for a video URL. Display mode defaults to "Inline." No play icon is shown until a URL is entered. Title field is empty.  
**Must NOT:** Pre-populate with a child image block.

#### A2. Author pastes a video URL

**Trigger:** Author enters a YouTube or Vimeo URL in the inspector panel.  
**Expected:** URL is stored as a block attribute. Provider is auto-detected. The editor fetches the oEmbed response via WordPress's internal proxy endpoint (`/wp-json/oembed/1.0/proxy`). From the response:

- The thumbnail is displayed as the poster preview with the provider-appropriate play icon (YouTube branded or generic).
- The video title is extracted and stored as the `videoTitle` block attribute (editable by the author).
- The modal ID auto-generates as a slugified version of the title (editable by the author). This happens regardless of display mode so the value is ready if the author switches to a modal mode later.

No iframe or embed preview is loaded.  
**Must NOT:** Load an iframe in the editor. Must NOT make direct client-side requests to YouTube/Vimeo. Must NOT overwrite a title or modal ID that the author has already manually edited.

#### A3. Author selects display mode

**Trigger:** Author selects Inline, Modal, or Modal Only in the inspector.  
**Expected:**

- **Inline**: No additional modal fields shown.
- **Modal**: Title field and Modal ID field appear. Both are pre-populated from oEmbed metadata if a URL has been entered (title from oEmbed title, ID from slugified title). Both are editable.
- **Modal Only**: Title field and Modal ID field appear. InnerBlocks area collapses or indicates that no poster will render. Editor shows a compact placeholder with the modal ID and video URL.

**Must NOT:** Show modal-specific options in inline mode. Must NOT show InnerBlocks editing UI in modal-only mode.

#### A4. Author adds a custom poster (InnerBlocks override)

**Trigger:** Author adds block(s) inside the video wrapper (e.g., Kadence image, core/image, core/group with overlays).  
**Expected:** The InnerBlocks content replaces the auto-fetched oEmbed thumbnail as the poster. The play icon overlay renders on top of whatever is inside. The editor still fetches oEmbed data for title/modalId auto-populate regardless of InnerBlocks presence.
**Editor must NOT:** Restrict InnerBlocks to specific block types. Must NOT use oEmbed thumbnail as poster preview when InnerBlocks are present.
**Frontend must NOT:** Render oEmbed poster when InnerBlocks are present. Must NOT inspect InnerBlocks content.

#### A5. Author positions the play icon

**Trigger:** Author adjusts X/Y position sliders in the inspector (default: 50%/50%).  
**Expected:** The play icon overlay moves to the specified position within the poster area. Editor preview updates to show the new position.

#### A6. Author configures play icon style

**Trigger:** Author selects play icon color/style in the inspector.  
**Expected:** For YouTube URLs, options are: dark, light, red (all YouTube-branded). For non-YouTube URLs, options are: dark, light (generic icon). Editor preview updates.  
**Must NOT:** Allow a non-branded play icon on YouTube videos.

#### A7. Author sets click behavior mode

**Trigger:** Author toggles between "Entire poster" (default) and "Play icon only" for the click target.  
**Expected:**

- **Entire poster** (default): The whole poster area is the click target. All child interactive elements are suppressed.
- **Play icon only**: Only the play button triggers video. Child interactive elements (links, buttons) behave normally.

### Inline Playback

#### I1. User clicks poster to play inline

**Trigger:** User clicks the play target (entire poster or play icon, per author setting) on an inline video block.  
**Expected:**

1. On click, wrapper reads and locks its current rendered dimensions via inline CSS to prevent layout shift during the poster-to-iframe swap.
2. Poster content (InnerBlocks output or auto-fetched thumbnail) and play icon are hidden.
3. Provider SDK loads if not already loaded (YouTube IFrame API or Vimeo Player SDK).
4. An iframe is created inside the wrapper at 100% width and 100% height.
5. Playback begins automatically once the player is ready.

**Must NOT:** Load the provider SDK or iframe before the click. Must NOT cause layout shift.

#### I2. Inline video with non-standard poster aspect ratio

**Trigger:** Poster is a portrait image (e.g., 3:4). User clicks to play.  
**Expected:** Wrapper locks to the poster's rendered dimensions at click time. The iframe fills 100% of the wrapper.  
**Must NOT:** Override the poster's aspect ratio. Must NOT implement custom letterboxing.

#### I3. Inline video with start time

**Trigger:** Video URL contains a start time (e.g., YouTube `?t=60`, Vimeo `#t=1m0s`).  
**Expected:** Playback begins at the specified start time.

#### I4. Inline video reaches the end

**Trigger:** Video playback reaches the end.  
**Expected:** Provider's native end behavior (YouTube end screen, Vimeo replay prompt, etc.). No custom end-state logic.

### Modal Playback

#### M1. User clicks poster to play in modal

**Trigger:** User clicks the play target on a modal-mode video block.  
**Expected:**

1. `<dialog>` opens via UIModal's `showModal()`.
2. URL hash updates to `#modal-id`.
3. Provider SDK loads if not already loaded.
4. An iframe is created inside the dialog's content area.
5. Playback begins.
6. Poster on the page remains visible (not hidden/swapped).

**Must NOT:** Load the iframe before the modal opens.

#### M2. User closes video modal

**Trigger:** User clicks close button, clicks backdrop, or presses Escape.  
**Expected:**

1. Player pauses (iframe remains in DOM).
2. `<dialog>` closes via UIModal's standard close flow.
3. URL hash clears.
4. Focus returns to the trigger element.

**Must NOT:** Leave video playing after modal closes. Must NOT destroy the player/iframe on close.

#### M3. User reopens the same video modal

**Trigger:** User closes and then re-triggers the same video modal.  
**Expected:** Modal reopens. Player is still present (paused from previous close). Playback can resume or restart. No duplicate iframes or players.  
**Must NOT:** Create a second iframe on reopen.

### Auto-Fetch Poster

#### F1. Video block with no InnerBlocks (inline or modal mode)

**Trigger:** Author has a video URL set but has removed or never added InnerBlocks content.  
**Expected:** Server-side oEmbed resolves the video URL and extracts the provider thumbnail URL. The block renders an `<img>` with the fetched thumbnail as the poster, with the play icon overlay.  
**Must NOT:** Make client-side API calls. Must NOT fail if oEmbed returns no thumbnail (render a generic placeholder or omit the poster).

#### F2. Video block with InnerBlocks present

**Trigger:** Author has placed any block(s) inside the video wrapper.  
**Expected:** InnerBlocks content is rendered as the poster. The server-side oEmbed auto-fetch poster is skipped. The wrapper does not inspect what the InnerBlocks contain.
**Frontend must NOT:** Render oEmbed poster when InnerBlocks are present. Must NOT inspect InnerBlocks content.

### Modal-Only Mode

#### MO1. Modal-only block on the page

**Trigger:** Author sets display mode to "Modal Only."  
**Expected:** No visible element renders on the frontend. A `<dialog>` is rendered in `wp_footer` with the block's modal ID. The dialog contains the player container. In the editor, a compact placeholder shows the modal ID and video URL.  
**Must NOT:** Render any visible frontend element. Must NOT show InnerBlocks or play icon in the editor.

#### MO2. Modal-only block with no trigger on the page

**Trigger:** A modal-only video block exists but no element links to its modal ID.  
**Expected:** The `<dialog>` is rendered in the footer but never opens. No errors. The modal is inert until something triggers it.

### Decoupled Triggers

#### D1. External link triggers video modal

**Trigger:** An `<a href="#video-modal-id">` elsewhere on the page is clicked. A video block (modal or modal-only mode) with that ID exists.  
**Expected:** The video modal opens and playback begins. UIModal handles trigger ARIA decoration, keyboard activation, and focus management.  
**Must NOT:** Require special markup beyond `href="#id"` or `data-target="#id"`.

#### D2. Multiple triggers for the same modal

**Trigger:** Several links on the page point to the same `#video-modal-id`.  
**Expected:** Any of them opens the same modal. UIModal handles decorating all matching triggers.

#### D3. Hash navigation to video modal (deep linking)

**Trigger:** User navigates directly to a URL with `#video-modal-id` in the hash (e.g., shared link, bookmark, or external reference).  
**Expected:** The video modal opens on page load. UIModal's hash sync handles this. The modal ID is stable and human-readable (derived from slugified video title by default), making deep links predictable and shareable.

### Mutual Exclusion

#### X1. Starting a second video pauses the first

**Trigger:** Video A is playing (inline or modal). User clicks to play Video B.  
**Expected:** Video A pauses. Video B starts.  
**Must NOT:** Allow two videos to play simultaneously.

#### X2. Opening a video modal pauses an inline video

**Trigger:** An inline video is playing. User clicks a modal video trigger.  
**Expected:** Inline video pauses. Modal opens and modal video starts.

### Analytics

#### G1. Video start fires GTM event

**Trigger:** Provider SDK confirms playback has started.  
**Expected:** GTM interaction event: `{action: 'start', provider, url, id}`.

#### G2. Video progress milestones

**Trigger:** Playback reaches 25%, 50%, 75%, 100% of duration.  
**Expected:** GTM interaction event with progress percentage.

#### G3. Video pause fires GTM event

**Trigger:** User pauses (via player controls or mutual exclusion).  
**Expected:** GTM interaction event: `{action: 'pause'}`.

### Privacy

#### P1. No provider contact in the browser before interaction

**Trigger:** Page loads with video blocks.  
**Expected:** No browser-initiated network requests to YouTube, Vimeo, or any third-party video service. No iframes in the DOM. No provider SDKs loaded. Server-side oEmbed for auto-fetch posters is acceptable — this runs at render time on the server, not in the user's browser.  
**Must NOT:** Load provider SDKs, embed iframes, or fetch resources from provider CDNs in the browser on page load.

#### P2. Privacy-enhanced embed domains

**Trigger:** User clicks to play.  
**Expected:** YouTube: `youtube-nocookie.com`. Vimeo: `dnt: true` parameter.

### Accessibility

#### ACC1. Play button semantics

**Trigger:** Screen reader encounters the video block.  
**Expected:** Play overlay is a `<button>` with `aria-label` including "Play video" and the `videoTitle` attribute value (e.g., "Play video: How to Install the Widget"). Keyboard focusable, activatable with Enter/Space.  
**Must NOT:** Use a non-interactive element for the play trigger.

#### ACC2. Entire-poster click mode accessibility

**Trigger:** Click behavior is set to "Entire poster" and the poster contains non-interactive content.  
**Expected:** The poster wrapper has `role="button"`, `tabindex="0"`, and an appropriate `aria-label`. Activatable with Enter/Space.

#### ACC3. Modal accessibility

**Trigger:** Video modal opens.  
**Expected:** All modal accessibility is handled by UIModal (focus trap, `aria-modal`, Escape to close, focus restoration). The `<dialog>` has `aria-labelledby` referencing a heading element containing the `videoTitle`.

### No-Op Scenarios

#### N1. Page with video blocks loads — no playback triggered

**Trigger:** Page loads normally with one or more video blocks.  
**Expected:** Nothing happens beyond rendering poster images and play icons. No network requests to providers. No SDK loading. No iframe creation.

#### N2. Carousel slides away from a video

**Trigger:** A video block is inside a carousel (e.g., a theme-level content slider). The carousel advances to a different slide while no video is playing.  
**Expected:** Nothing. No special behavior on slide change.

#### N3. Carousel slides away from a playing video

**Trigger:** A video is playing inline inside a carousel slide. The carousel advances to a different slide.  
**Expected:** Video continues playing (audio still audible). The video block does not auto-pause on visibility changes. External code (e.g., a carousel module) may pause the video by calling `sitchco.hooks.doAction('video-request-pause', id)`.

#### N4. Video block with no URL set

**Trigger:** Author saves a video block without entering a URL.  
**Expected:** Frontend renders InnerBlocks (poster) without a play icon and without click-to-play behavior. It's just whatever the child blocks render. No JS initialization, no errors.

## Constraints

1. **Click-to-load.** No provider iframe or SDK loads in the browser before the user clicks play. Server-side oEmbed at render time is acceptable.
2. **No image duplication.** No image fields, srcset logic, or overlay controls on the video block. That is the child block's domain.
3. **Platform-level block.** Lives in sitchco-core. Does not depend on theme-level packages.
4. **YouTube branded play icon required.** YouTube's API terms of service require their official branded play button (dark, light, or red). A non-branded icon must not be used on YouTube videos.
5. **Empty on insert.** The block starts with no InnerBlocks; the oEmbed auto-fetch is the default poster.
6. **Modal-only mode is invisible.** The only frontend output is the `<dialog>` in `wp_footer`.
7. **InnerBlocks are opaque.** The wrapper checks only whether InnerBlocks exist (for auto-fetch logic), never what they contain.
8. **Pause on modal close, don't destroy.** The player iframe persists for fast reopen.
9. **Native Gutenberg block.** Registered via `block.json` with a React `edit` component. Not an ACF block.
10. **Provider SDKs for playback control.** YouTube IFrame API and Vimeo Player SDK are required for mutual exclusion, analytics, and programmatic pause. They load on first user click, not on page load.

## Extension Points

These hooks preserve the platform's multi-site extensibility pattern. The video block exposes hooks for both external coordination and customization.

### Coordination Hooks (JS — `sitchco.hooks`)

#### Lifecycle Events

Fired by the video block when player state changes. External code subscribes via `addAction` to observe.

| Hook | Purpose |
|------|---------|
| `video-play` | Fired when a video starts playing. Payload: `{id, provider, url}` |
| `video-pause` | Fired when a video pauses (user action or programmatic). Payload: `{id, provider, url}` |
| `video-progress` | Fired when playback crosses a milestone percentage. Payload: `{id, provider, url, percent}` |
| `video-ended` | Fired when a video reaches the end. Payload: `{id, provider, url}` |

#### Command Hooks

Called by external code (carousels, other components) to control video playback. The video block subscribes internally.

| Hook | Purpose |
|------|---------|
| `video-request-pause` | Pause a video by provider ID. Triggers native SDK pause, which in turn fires the `video-pause` lifecycle event. |

### Customization Filters

| Filter | Runtime | Purpose |
|--------|---------|---------|
| `sitchco/video/playerVars/youtube` | JS | Override YouTube IFrame API player parameters |
| `sitchco/video/playerVars/vimeo` | JS | Override Vimeo Player SDK embed parameters |
| `sitchco/video/play_icon_svg` | PHP | Replace play button SVG markup |

## Out of Scope (v1)

- Modal title/description display beyond accessibility heading (can be added later as visible block attributes)
- Carousel slide-change auto-pause (the carousel component decides its own policy and can use the `video-request-pause` hook)
- Custom end-state behavior (provider defaults are fine)
- Background video / self-hosted video (separate system)
- Consent management / CMP integration (the click-to-load architecture and `video-play` hook provide natural interception points for a future consent layer — e.g., checking CMP status at click time and opening the video URL in a new window instead of loading the SDK when the user has opted out)
- Provider expansion beyond YouTube/Vimeo (architecture supports it, but no implementation)
