# Architecture Patterns

**Domain:** Native Gutenberg video block with provider SDK integration, modal composition, and InnerBlocks -- integrating with sitchco-core modular framework
**Researched:** 2026-03-09

## Recommended Architecture

The video block is a single sitchco-core module (`modules/UIVideo/`) that introduces the first native Gutenberg block in the framework. It has three runtime environments (editor, frontend, server) with distinct responsibilities, and composes with the existing UIModal module for modal playback.

### High-Level Component Map

```
Editor (React/JSX)                    Server (PHP)                     Frontend (Vanilla JS)
-----------------------               ----------------------           ----------------------
edit.js                               render.php                       view.js
  InspectorControls                     oEmbed metadata fetch            Provider SDK loader
  URL input + validation                Poster HTML generation           Click-to-play handler
  oEmbed preview fetch                  InnerBlocks rendering            Player lifecycle
  InnerBlocks editor                    UIModal composition              Mutual exclusion
  Play icon overlay preview             Play icon SVG output             Analytics events
  Display mode selector                 Modal-only footer output         Modal integration
save.js                               UIVideo.php (Module)
  <InnerBlocks.Content/>                Asset registration
  (attributes in comment)               oEmbed REST endpoint
                                        Provider detection
```

### Component Boundaries

| Component | Responsibility | Communicates With | Runtime |
|-----------|---------------|-------------------|---------|
| **UIVideo module** (PHP) | Module lifecycle: asset registration, oEmbed REST endpoint, provider detection utility, config | BlockRegistrationModuleExtension (auto), UIModal (DI), ModuleAssets | Server |
| **block.json** | Block metadata: attributes, scripts, styles, supports, InnerBlocks config | WordPress block registry (auto) | Registration |
| **edit.js** (React) | Editor UI: URL input, oEmbed preview, InnerBlocks, inspector controls, display mode, play icon position | WP REST API (`/oembed/1.0/proxy`), custom REST endpoint, `@wordpress/*` packages | Editor |
| **save.js** (React) | Persistence: saves `<InnerBlocks.Content/>` to DB; attributes stored in block comment delimiter | Block editor serialization | Editor (save) |
| **render.php** | Server-side HTML: poster, play icon SVG, wrapper attributes, modal loading | UIVideo module, UIModal module (via DI container), Twig templates | Server render |
| **view.js** (Vanilla JS) | Frontend behavior: click-to-play, SDK loading, player lifecycle, mutual exclusion, analytics | `sitchco.hooks` system, YouTube IFrame API, Vimeo Player SDK, UIModal JS | Frontend |
| **Provider SDK adapter** (in view.js) | Normalizes YouTube/Vimeo SDK differences behind a common interface | YouTube IFrame API CDN, Vimeo Player SDK CDN | Frontend (lazy) |
| **UIModal** (existing) | Dialog rendering, focus trap, hash sync, scroll lock, ARIA | `sitchco.hooks` actions (`ui-modal-show`, `ui-modal-hide`) | Frontend + Server |

## Data Flow

### 1. Editor: URL Entry to Preview

```
Author pastes URL in InspectorControls
  -> edit.js validates URL format, detects provider (YouTube/Vimeo)
  -> edit.js calls WP REST API: GET /oembed/1.0/proxy?url={url}
     (this is WordPress core's existing authenticated proxy endpoint)
  -> Response contains: { title, thumbnail_url, provider_name, ... }
  -> edit.js stores in block attributes:
     { videoUrl, videoId, provider, posterUrl, videoTitle }
  -> Editor renders poster preview with play icon overlay
  -> If author adds InnerBlocks: poster preview shows InnerBlocks instead of oEmbed thumbnail
```

**Why the existing oEmbed proxy**: WordPress core already provides `/oembed/1.0/proxy` which handles provider resolution, caching, and CORS. It returns the full oEmbed response object including `thumbnail_url`, `title`, and `provider_name`. No custom REST endpoint needed for metadata fetching -- use what WordPress provides. The endpoint requires `edit_posts` capability, which is appropriate since only editors interact with it.

### 2. Save: Block Serialization

```
Block editor serializes:
  -> Block comment delimiter stores attributes as JSON:
     <!-- wp:sitchco/video {"videoUrl":"...","videoId":"...","provider":"youtube",
          "displayMode":"modal","posterUrl":"...","videoTitle":"...","playIconPosition":{"x":50,"y":50}} -->
  -> save.js renders <InnerBlocks.Content/> between delimiters
     (only inner block markup persisted, parent has no static HTML)
  -> <!-- /wp:sitchco/video -->
```

**Critical pattern**: The `save` function returns `<InnerBlocks.Content/>`, not `null`. This is required because InnerBlocks content must be stored in the post content for the editor to restore it. The parent block itself is fully dynamic (PHP-rendered), but the inner blocks need their markup persisted. This is the standard WordPress pattern for dynamic blocks with InnerBlocks.

### 3. Server Render: PHP Output

```
WordPress calls render.php($attributes, $content, $block)
  -> $content contains serialized InnerBlocks HTML from save
  -> render.php accesses UIVideo module via $GLOBALS['SitchcoContainer']
  -> Detects if InnerBlocks present: $has_inner_blocks = !empty(trim($content))
  -> Fetches oEmbed data if needed (for auto-poster when no InnerBlocks):
     $oembed = (new \WP_oEmbed())->get_data($attributes['videoUrl'])
     -> Returns object with thumbnail_url, title, provider_name
     -> Cached by WordPress in oembed_cache post type
  -> Builds poster HTML:
     If $has_inner_blocks: poster = $content (rendered InnerBlocks)
     Else: poster = <img> from oEmbed thumbnail_url
  -> Selects play icon SVG based on provider + variant attribute
  -> Generates wrapper with data attributes for view.js hydration:
     data-video-url, data-video-id, data-provider, data-display-mode
  -> If displayMode is 'modal' or 'modal-only':
     Creates ModalData and calls UIModal::loadModal()
     -> UIModal renders <dialog> in wp_footer
  -> If displayMode is 'modal-only':
     No visible output on page; only the <dialog> is rendered
     External triggers use href="#modal-id" or data-target="#modal-id"
  -> Outputs HTML structure
```

### 4. Frontend: Click-to-Play

```
view.js initializes on DOMContentLoaded
  -> Scans for [data-sitchco-video] elements
  -> For each: reads data attributes, creates VideoPlayer instance
  -> On poster click (or play button click, depending on clickMode):
     1. Fires sitchco.hooks action: 'video-play-request' (allows cancel)
     2. Loads provider SDK if not yet loaded:
        YouTube: injects <script src="youtube.com/iframe_api">
                 waits for onYouTubeIframeAPIReady callback
        Vimeo:   injects <script src="player.vimeo.com/api/player.js">
                 waits for Vimeo.Player to exist
     3. Creates provider player instance:
        YouTube: new YT.Player(containerEl, {
           videoId, playerVars: { enablejsapi:1, origin, playsinline:1, start, rel:0 },
           host: 'https://www.youtube-nocookie.com',
           events: { onStateChange, onReady }
        })
        Vimeo: new Vimeo.Player(containerEl, {
           id: videoId, dnt: true, autopause: false,
           playsinline: true
        })
     4. On player ready: calls play()
     5. Fires sitchco.hooks action: 'video-play'
  -> Mutual exclusion:
     sitchco.hooks addAction('video-play', pauseAllOtherPlayers)
     -> Iterates registered players, calls pause() on all except current
  -> Modal integration:
     If displayMode is 'modal':
       On click: calls sitchco.hooks doAction('ui-modal-show', dialogEl)
       On modal close event: calls player.pause()
  -> Analytics:
     On state changes: fires GTM dataLayer.push events
     Tracks: start, pause, 25%/50%/75%/100% progress milestones
```

### 5. Data Attribute Contract (PHP -> JS)

The server-rendered HTML carries all configuration needed by view.js through data attributes. This is the contract between render.php and view.js:

```html
<div class="sitchco-video"
     data-sitchco-video
     data-video-id="dQw4w9WgXcQ"
     data-provider="youtube"
     data-display-mode="inline"
     data-click-mode="poster"
     data-start-time="0"
     data-modal-id="video-dQw4w9WgXcQ">
  <div class="sitchco-video__poster">
    <!-- InnerBlocks content OR <img> from oEmbed -->
  </div>
  <button class="sitchco-video__play" aria-label="Play Video Title">
    <!-- Play icon SVG -->
  </button>
  <div class="sitchco-video__player-container">
    <!-- Empty: SDK injects iframe here on click -->
  </div>
</div>
```

## Patterns to Follow

### Pattern 1: Native Block with Dynamic Render (New Pattern for sitchco-core)

**What:** Register a native Gutenberg block via `block.json` with `editorScript` (React), `viewScript` (vanilla JS), and `render` (PHP file) -- no ACF, no `renderCallback`.

**When:** Blocks needing rich editor interactions (InnerBlocks, conditional inspector panels, live previews) that exceed ACF block capabilities.

**Why this differs from existing blocks:** The current `sitchco/icon` and `sitchco/modal` blocks use ACF mode with `TimberModule::blockRenderCallback`. The video block cannot use ACF because it needs InnerBlocks for poster composition, a React `edit` component for oEmbed preview fetching, and conditional inspector controls based on display mode. This establishes the native block pattern for future blocks.

**block.json structure:**
```json
{
  "apiVersion": 3,
  "name": "sitchco/video",
  "title": "Video",
  "category": "sitchco",
  "icon": "video-alt3",
  "description": "Privacy-respecting click-to-play video embed with poster overlay",
  "keywords": ["video", "youtube", "vimeo"],
  "attributes": {
    "videoUrl": { "type": "string", "default": "" },
    "videoId": { "type": "string", "default": "" },
    "provider": { "type": "string", "default": "" },
    "displayMode": { "type": "string", "default": "inline", "enum": ["inline", "modal", "modal-only"] },
    "posterUrl": { "type": "string", "default": "" },
    "videoTitle": { "type": "string", "default": "" },
    "playIconVariant": { "type": "string", "default": "dark" },
    "playIconPosition": {
      "type": "object",
      "default": { "x": 50, "y": 50 }
    },
    "clickMode": { "type": "string", "default": "poster", "enum": ["poster", "icon"] },
    "modalId": { "type": "string", "default": "" },
    "startTime": { "type": "number", "default": 0 }
  },
  "supports": {
    "html": false,
    "align": ["wide", "full"],
    "spacing": { "margin": true }
  },
  "editorScript": "file:./editor/index.js",
  "viewScript": "file:./view.js",
  "style": "file:./style.css",
  "editorStyle": "file:./editor/style.css",
  "render": "file:./render.php"
}
```

**Integration with existing asset pipeline:** The `BlockRegistrationModuleExtension` already calls `register_block_type($fullPath)` which reads `block.json`. The existing `ModuleAssets::blockTypeMetadata()` filter resolves `file:` references to Vite-built assets. Native block scripts declared in `block.json` will be resolved through the same pipeline -- no changes needed to the framework.

**Build tooling note:** The `editorScript` uses React/JSX and imports from `@wordpress/*` packages. This requires the Vite build to handle JSX transformation and treat `@wordpress/*` as externals (they are provided by WordPress at runtime). The existing `@sitchco/cli` Vite setup may need configuration for JSX support -- this is a build-tooling concern, not an architecture concern.

### Pattern 2: Composition over Inheritance for Modal Integration

**What:** The video block composes with UIModal by calling `UIModal::loadModal()` in `render.php`, not by extending UIModal or duplicating dialog markup.

**When:** Any block or component needs modal behavior.

**Why:** UIModal already owns the `<dialog>` lifecycle (focus trap, scroll lock, hash sync, ARIA, dismiss). The video block should not reimplement any of this. It creates a `ModalData` instance and hands it to UIModal, which renders the dialog in `wp_footer`.

```php
// In render.php
$uiModal = $container->get(UIModal::class);
$modalData = new ModalData($modalId, $videoTitle, $playerHtml, ModalType::VIDEO);
$uiModal->loadModal($modalData);
```

**ModalData already supports content-based modals:** The recent refactor (commit `4fc37f7`) decoupled `ModalData` from `Timber\Post`. The constructor now accepts `(string $id, string $heading, string $content, ModalType $type)` directly. The `ModalType::VIDEO` case already exists in the enum. No further refactor of UIModal is needed for content-based modal support.

### Pattern 3: Provider SDK Adapter (Strategy Pattern)

**What:** Abstract YouTube and Vimeo SDK differences behind a common interface in view.js.

**When:** Multiple video providers need unified playback control.

**Structure:**
```javascript
// Provider adapter interface (conceptual, implemented as object shape)
// {
//   load(): Promise<void>     -- Lazy-loads the provider SDK
//   create(container, config): Promise<PlayerHandle>  -- Creates player
//   play(handle): void
//   pause(handle): void
//   destroy(handle): void
//   on(handle, event, callback): void  -- Normalized events: 'play', 'pause', 'ended', 'timeupdate'
// }

const providers = {
  youtube: { load, create, play, pause, destroy, on },
  vimeo:   { load, create, play, pause, destroy, on },
};
```

**Why adapters, not direct SDK calls:** Mutual exclusion, analytics, and modal-pause-on-close need to call `pause()` on any active player regardless of provider. Without adapters, every callsite would need `if (youtube) ... else if (vimeo) ...` branching. The adapter normalizes this to a single interface.

### Pattern 4: Hooks-Based Inter-Component Communication

**What:** Use `sitchco.hooks` actions/filters for cross-cutting concerns (mutual exclusion, analytics, external pause requests).

**When:** Components need to coordinate without direct coupling.

**Extension points exposed by the video block:**

| Hook | Type | Purpose |
|------|------|---------|
| `video-play` | action | Fired when a video starts playing; used by mutual exclusion listener and analytics |
| `video-pause` | action | Fired when a video is paused; analytics and external consumers |
| `video-ended` | action | Fired when playback completes |
| `video-play-request` | filter | Fired before play; can return `false` to cancel (future consent layer hook) |
| `video-player-vars` | filter | Modify provider playerVars before player creation |
| `video-play-icon` | filter (PHP) | Modify play icon SVG markup |

**This mirrors the existing UIModal pattern:** UIModal exposes `ui-modal-show`, `ui-modal-hide`, `ui-modal-enableDismiss` actions. The video block follows the same convention with `video-*` prefix.

## Anti-Patterns to Avoid

### Anti-Pattern 1: ServerSideRender in the Editor

**What:** Using `<ServerSideRender>` component in `edit.js` to show a PHP-rendered preview in the block editor.

**Why bad:** WordPress documentation explicitly states ServerSideRender "should be regarded as a fallback or legacy mechanism." It causes a full server round-trip for every attribute change, creating poor editor UX with loading spinners. It also prevents the editor from showing InnerBlocks in their editable form -- the user would see a rendered preview they cannot interact with.

**Instead:** Build the editor preview entirely in React. The edit component should show the poster (either from oEmbed `thumbnail_url` or InnerBlocks in editing mode), the play icon overlay, and the display mode indicator. Use the WP REST oEmbed proxy for thumbnail fetching. The editor preview does not need to match the frontend pixel-for-pixel -- it needs to give authors accurate feedback about their configuration.

### Anti-Pattern 2: Storing Provider Iframe in Post Content

**What:** Saving the provider iframe HTML in the block's `save` function or as an attribute.

**Why bad:** Provider embed URLs, parameters, and privacy domains change over time. Stored iframes become stale and must be migrated. The privacy-first architecture requires no iframe until click -- storing one suggests it should be rendered immediately.

**Instead:** Store only the video URL and derived metadata (videoId, provider) as block attributes. Generate the iframe at runtime via the provider SDK in view.js, or via PHP if a no-JS fallback is ever needed.

### Anti-Pattern 3: Loading Provider SDKs on Page Load

**What:** Enqueueing YouTube IFrame API or Vimeo Player SDK as viewScript dependencies that load on every page with a video block.

**Why bad:** Violates the privacy-first constraint (no provider resources before user click) and wastes bandwidth. YouTube's iframe_api.js is ~50KB and makes additional network requests. Loading it page-wide defeats the purpose of click-to-play.

**Instead:** Load provider SDKs dynamically in view.js only when the user clicks play. The SDK load is a one-time operation per provider per page -- subsequent plays reuse the loaded SDK. Store a module-level Promise for each provider's load state to prevent duplicate script injections.

### Anti-Pattern 4: Inspecting InnerBlocks Content

**What:** Checking what specific blocks are inside InnerBlocks, or reading their attributes from the parent block.

**Why bad:** Per the project constraints, the wrapper checks only whether InnerBlocks exist, never inspects their content. This keeps the poster composition fully flexible -- authors can put any blocks inside (Image, Cover, Group, etc.) without the video block needing to understand them.

**Instead:** Use `!empty(trim($content))` in render.php to detect InnerBlocks presence. If present, render `$content` as the poster. If absent, fall back to oEmbed thumbnail.

## File Structure Within the Module

```
modules/UIVideo/
  UIVideo.php              # Module class: DEPENDENCIES, asset registration, oEmbed utility
  blocks/
    video/
      block.json           # Native block metadata (attributes, scripts, styles, render)
      render.php           # Server-side render: poster HTML, play icon, modal loading
      style.css            # Frontend + editor shared styles (poster, play icon, player container)
      view.js              # Frontend JS: click-to-play, SDK loading, mutual exclusion, analytics
      editor/
        index.js           # React edit component (JSX, imports @wordpress/*)
        style.css          # Editor-only styles
        components/
          VideoInspector.js    # InspectorControls: URL, display mode, play icon config
          PosterPreview.js     # Poster area: oEmbed thumbnail or InnerBlocks
          PlayIconOverlay.js   # Play icon with drag-position
  assets/
    images/
      play-youtube-dark.svg
      play-youtube-light.svg
      play-youtube-red.svg
      play-generic-dark.svg
      play-generic-light.svg
    scripts/
      (empty -- all JS is in blocks/video/)
    styles/
      (empty -- all CSS is in blocks/video/)
  templates/
    video-poster.twig      # Optional: Twig partial for poster markup
```

**Why the editor/ subdirectory:** Editor scripts use React/JSX and import from `@wordpress/*` packages. They are a separate build entry point from the vanilla JS `view.js`. Placing them in a subdirectory makes the build configuration clearer and keeps the block directory scannable.

**Why play icon SVGs in assets/images/:** The SvgSprite module builds SVGs into a sprite sheet. The video block's play icons are provider-specific branding assets (YouTube ToS requires specific designs) that should not be part of the generic icon sprite. They are included inline by render.php or referenced as module assets.

## Build Order (Dependencies Between Components)

The following reflects the dependency chain that determines implementation order:

### Phase 1: Foundation (no external dependencies)

1. **UIVideo module class** -- Extends `Module`, declares `DEPENDENCIES = [UIModal::class, UIFramework::class]`, registers assets. This is scaffolding.
2. **block.json** -- Block metadata with attributes schema. Registered automatically by `BlockRegistrationModuleExtension`.
3. **render.php (poster-only)** -- Minimal: reads attributes, outputs poster `<img>` from a hardcoded test URL, no modal integration yet.
4. **Vite/build configuration** -- Ensure JSX compilation works for `editorScript`. This may require updating `@sitchco/cli` or adding a local Vite config override.

### Phase 2: Editor Experience

5. **edit.js (basic)** -- URL input in InspectorControls, provider auto-detection (regex on URL), stores attributes. No preview yet.
6. **edit.js (oEmbed preview)** -- Fetches thumbnail from `/oembed/1.0/proxy`, shows poster image in editor.
7. **edit.js (InnerBlocks)** -- Adds `<InnerBlocks>` with conditional rendering: if InnerBlocks present, show them; if not, show oEmbed thumbnail.
8. **save.js** -- Returns `<InnerBlocks.Content/>`.
9. **edit.js (inspector polish)** -- Display mode selector, play icon variant, play icon position, click mode.

### Phase 3: Frontend Playback

10. **view.js (click handler)** -- Poster click detection, display mode routing.
11. **Provider SDK adapter (YouTube)** -- Lazy SDK loading, player creation, state change events.
12. **Provider SDK adapter (Vimeo)** -- Same interface, Vimeo SDK.
13. **Inline playback** -- Player replaces poster, dimension locking to prevent layout shift.
14. **Mutual exclusion** -- `sitchco.hooks` action on play pauses other registered players.

### Phase 4: Modal Integration

15. **render.php (modal)** -- Creates `ModalData`, calls `UIModal::loadModal()` for modal/modal-only display modes.
16. **view.js (modal playback)** -- On click: shows modal via `doAction('ui-modal-show')`, creates player inside dialog, pauses on modal close.
17. **Modal-only mode** -- No visible poster on page; `<dialog>` rendered in footer; external triggers via `href="#id"`.

### Phase 5: Polish and Cross-Cutting

18. **Play icon system** -- YouTube-branded SVGs (dark/light/red), generic SVGs (dark/light), position CSS.
19. **Analytics** -- GTM dataLayer events on play/pause/progress milestones.
20. **Accessibility** -- `aria-label` on play button, `role="button"` + `tabindex="0"` on poster in entire-poster click mode, keyboard activation.
21. **Privacy domains** -- `youtube-nocookie.com` host for YouTube, `dnt=1` parameter for Vimeo.
22. **Start time** -- Parse `t=` / `start=` from URL, pass to provider playerVars.
23. **Extension points** -- PHP filters (`video-play-icon`), JS hooks (`video-player-vars`, `video-play-request`).

### Why This Order

- **Phases 1-2 first**: You cannot test frontend playback without a block that saves data. Editor work establishes the data model.
- **Phase 3 before Phase 4**: Inline playback is simpler than modal playback. Get SDK integration working in the simplest context first.
- **Phase 4 after Phase 3**: Modal playback composes inline playback (same SDK, same player) with UIModal. Adding modal is incremental once inline works.
- **Phase 5 last**: Polish items (icons, analytics, accessibility, privacy) are cross-cutting and benefit from a stable core. They do not change the architecture.

## Scalability Considerations

| Concern | At 1-5 videos/page | At 10-20 videos/page | At 50+ videos/page |
|---------|--------------------|-----------------------|---------------------|
| SDK loading | Single script load on first click, negligible | Same -- SDK loaded once per provider | Same |
| DOM elements | Minimal: poster + button per video | Manageable | Consider lazy initialization of view.js handlers (IntersectionObserver) |
| oEmbed caching | WordPress caches in DB per URL | Cached; no extra requests | Cached |
| Mutual exclusion | Iterate small player array | Linear scan is fine | Consider Map by ID for O(1) lookup |
| Memory (player iframes) | 1-2 iframes active | At most 1 active (mutual exclusion pauses/destroys others) | Destroy paused players after timeout to reclaim memory |

## Sources

- [WordPress block.json metadata reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/)
- [WordPress static and dynamic rendering](https://developer.wordpress.org/block-editor/getting-started/fundamentals/static-dynamic-rendering/)
- [WordPress creating dynamic blocks](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/creating-dynamic-blocks/)
- [WordPress block in the editor](https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-in-the-editor/)
- [YouTube IFrame Player API reference](https://developers.google.com/youtube/iframe_api_reference)
- [Vimeo Player SDK reference](https://developer.vimeo.com/player/sdk/reference)
- [Vimeo Player SDK embed options](https://developer.vimeo.com/player/sdk/embed)
- [Vimeo DNT parameter explanation](https://ignite.video/en/articles/tutorials/vimeo-do-not-track)
- [WordPress WP_oEmbed::get_data()](https://developer.wordpress.org/reference/classes/wp_oembed/get_data/)
- [WordPress oEmbed proxy endpoint (Trac #40450)](https://core.trac.wordpress.org/ticket/40450)
- [10up InnerBlocks best practices](https://gutenberg.10up.com/reference/Blocks/inner-blocks/)
- [GitHub discussion: Dynamic blocks with InnerBlocks](https://github.com/WordPress/gutenberg/discussions/44466)
- [WordPress ServerSideRender package docs](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/)

---

*Architecture analysis: 2026-03-09*
