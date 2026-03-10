# Technology Stack

**Project:** sitchco/video -- Native Gutenberg Video Block with Provider SDK Integration
**Researched:** 2026-03-09

## Recommended Stack

### Existing Platform (No Changes Needed)

These are already in place in sitchco-core and require no additional installation or configuration for the video block.

| Technology | Version | Purpose | Confidence |
|------------|---------|---------|------------|
| Vite + `@sitchco/module-builder` | 2.1.5 | Build system -- already handles JSX, CSS, and WP externals | HIGH |
| `@kucrut/vite-for-wp` (`wp_scripts()`) | 0.12.0 | Externalizes `@wordpress/*`, `react`, `react-dom` to WP globals at build time | HIGH |
| `laravel-vite-plugin` | (bundled) | Entry point discovery and dev server integration | HIGH |
| `@sitchco/project-scanner` | 2.1.2 | Auto-discovers `*.{js,mjs,jsx,scss,css}` in `modules/*/blocks/*/` -- JSX already supported | HIGH |
| `ModuleAssets::blockTypeMetadata()` | existing | Resolves `file:` paths in `block.json` to Vite-built assets, loads `.asset.php` deps | HIGH |
| `BlockManifestRegistry` | existing | Auto-discovers and registers blocks via `block.json` | HIGH |
| `sitchco.hooks` JS system | existing | Frontend action/filter system for inter-module coordination (mutual exclusion, analytics) | HIGH |
| UIModal system | existing | `<dialog>`-based modals with focus trapping, scroll lock, hash sync, ARIA | HIGH |
| pnpm | workspace | Package manager | HIGH |

**Key insight:** The build pipeline already externalizes all `@wordpress/*` packages and `react`/`react-dom` to their WP global equivalents (`wp.*`, `React`, `ReactDOM`). JSX files in `modules/*/blocks/*/` are already auto-discovered as Vite entry points. The existing `ModuleAssets` class already handles `editorScript`, `viewScript`, `style`, and `editorStyle` fields from `block.json`. No build tooling changes are needed.

### WordPress Block API (Editor-side, Externalized)

These packages are imported in editor JS but **not installed as npm dependencies**. They are externalized by `wp_scripts()` to WordPress globals at build time. They are listed here for documentation -- use them via `import` and the build system handles the rest.

| Package | Global | Purpose | Why |
|---------|--------|---------|-----|
| `@wordpress/blocks` | `wp.blocks` | `registerBlockType()` to register the block from JS | Required for native block registration with React `edit` component |
| `@wordpress/block-editor` | `wp.blockEditor` | `useBlockProps`, `useInnerBlocksProps`, `InspectorControls`, `BlockControls` | Core hooks for block editor integration, InnerBlocks for poster override |
| `@wordpress/components` | `wp.components` | `TextControl`, `SelectControl`, `ToggleControl`, `PanelBody`, `Button`, `Placeholder` | Inspector sidebar controls and editor UI components |
| `@wordpress/element` | `wp.element` | `useState`, `useEffect`, `useCallback` (re-exported React hooks) | React hooks for editor component state |
| `@wordpress/api-fetch` | `wp.apiFetch` | Fetch oEmbed data via WP REST proxy (`/oembed/1.0/proxy`) | Server-side thumbnail URL fetching without exposing API keys |
| `@wordpress/i18n` | `wp.i18n` | `__()`, `_x()` for translatable strings | Block title, description, control labels |
| `@wordpress/data` | `wp.data` | `useSelect`, `useDispatch` for WP data store access | Access block store for InnerBlocks count detection |
| `react` | `React` | JSX runtime | Already externalized by `wp_scripts()` |
| `react/jsx-runtime` | `ReactJSXRuntime` | Automatic JSX transform | Already externalized by `wp_scripts()` |

**Important:** Do NOT install these as `devDependencies`. The `wp_scripts()` plugin in the Vite config already maps them to globals. Installing them would create version mismatches with whatever WordPress version is running. Import them directly in `.jsx` files and the build handles externalization.

### Video Provider SDKs (Frontend, Lazy-loaded)

These are CDN-loaded scripts, not npm packages. They load on-demand when a user clicks play -- never before.

| SDK | Load URL | Purpose | Why This Approach | Confidence |
|-----|----------|---------|-------------------|------------|
| YouTube IFrame API | `https://www.youtube.com/iframe_api` | `YT.Player` for programmatic playback control | No npm package exists -- Google provides only this CDN script. Loads async, calls `window.onYouTubeIframeAPIReady` when ready. | HIGH |
| Vimeo Player SDK | `https://player.vimeo.com/api/player.js` | `Vimeo.Player` for programmatic playback control | CDN loading avoids bundling a 30KB+ library that only loads when needed. Alternative: `@vimeo/player` npm package (see below). | MEDIUM |

#### YouTube IFrame API Details

- **Loading:** Inject `<script src="https://www.youtube.com/iframe_api">` dynamically on first play click
- **Ready signal:** Global callback `window.onYouTubeIframeAPIReady()`
- **Player creation:** `new YT.Player(element, { videoId, host, playerVars, events })`
- **Privacy host:** Set `host: 'https://www.youtube-nocookie.com'` in constructor options
- **Key methods:** `playVideo()`, `pauseVideo()`, `stopVideo()`, `destroy()`, `seekTo(seconds)`, `getCurrentTime()`, `getDuration()`
- **Key events:** `onReady`, `onStateChange` (states: UNSTARTED=-1, ENDED=0, PLAYING=1, PAUSED=2, BUFFERING=3, CUED=5), `onError`
- **Caveat:** The API script itself loads from `youtube.com` even when using `youtube-nocookie.com` as player host. The click-to-load architecture naturally handles this -- the script only loads after user interaction.
- **No versioning:** Google maintains this as a continuously-updated service, not a versioned package.

#### Vimeo Player SDK Details

- **Loading:** CDN at `https://player.vimeo.com/api/player.js` OR npm `@vimeo/player@2.30.3`
- **Player creation:** `new Vimeo.Player(element, { id, dnt: true })` or `new Vimeo.Player(existingIframe)`
- **Privacy parameter:** `dnt: true` disables tracking cookies (reduces but does not eliminate all cookies)
- **Key methods:** `play()`, `pause()`, `destroy()`, `getDuration()`, `getCurrentTime()`, `setCurrentTime(seconds)`, `on(event, cb)`, `off(event, cb)`
- **Key events:** `play`, `pause`, `ended`, `timeupdate`, `seeked`, `bufferstart`, `bufferend`, `error`
- **Browser support:** Last 2 versions of Chrome, Firefox, Safari, Edge, Opera

#### CDN vs npm for Vimeo: Use CDN

**Recommendation:** Load Vimeo Player SDK from CDN, not as an npm dependency.

**Rationale:**
- Consistent with YouTube approach (both CDN-loaded on demand)
- Avoids bundling ~30KB into the viewScript that loads on every page with a video block, even before play
- The click-to-load architecture means the SDK is only needed after user interaction
- CDN version is always current with Vimeo's player iframe
- If npm is ever needed (e.g., for TypeScript types during development), install `@vimeo/player@^2.30.3` as `devDependencies` only for type definitions, not for runtime bundling

### npm Dependencies to Install

| Package | Version | Type | Purpose | Confidence |
|---------|---------|------|---------|------------|
| `@vimeo/player` | `^2.30.3` | `devDependencies` | TypeScript type definitions only (optional -- only if using JSDoc type hints) | LOW |

**That's it.** No other npm packages are needed. The WordPress packages are externalized. The provider SDKs are CDN-loaded. The build system is already configured.

### PHP (Server-side, No Additional Dependencies)

| Component | Source | Purpose | Why |
|-----------|--------|---------|-----|
| `register_block_type()` | WP Core | Register block from `block.json` directory | Standard WP block registration |
| `WP_oEmbed_Controller` / `wp_oembed_get()` | WP Core | Fetch oEmbed data (thumbnail URL, title) from YouTube/Vimeo | Built-in, cached, respects provider allowlists |
| REST API oEmbed proxy | WP Core (`/wp-json/oembed/1.0/proxy`) | Editor-side oEmbed lookups via `@wordpress/api-fetch` | Already available, requires `edit_posts` capability |
| `wp_register_script()` / `wp_register_style()` | WP Core | Asset registration (handled by `ModuleAssets`) | Existing infrastructure |
| `Sitchco\Framework\Module` | sitchco-core | Module base class for DI, lifecycle, asset pipeline | Existing infrastructure |
| `Sitchco\Framework\ModuleAssets` | sitchco-core | Block asset resolution from `block.json` `file:` paths | Existing -- already handles `editorScript`, `viewScript`, `style` |
| `Sitchco\Modules\UIModal\UIModal` | sitchco-core | Modal rendering in `wp_footer` | Existing -- needs content-based modal refactor (prerequisite) |

## File Structure

Based on the existing module conventions discovered in `@sitchco/project-scanner`:

```
modules/
  Video/
    Video.php                          # Module class (DI, hooks, block registration)
    blocks/
      video/
        block.json                     # Native block metadata (editorScript, viewScript, style)
        block.php                      # Server-side render callback
        block.twig                     # Twig template for frontend output
        edit.jsx                       # React edit component (auto-discovered by project-scanner)
        style.css                      # Block styles (frontend + editor)
        editor.css                     # Editor-only styles (optional)
    assets/
      scripts/
        player-manager.js             # Frontend: SDK loader, player lifecycle, mutual exclusion
      images/
        play-youtube-dark.svg          # YouTube branded play button (dark variant)
        play-youtube-light.svg         # YouTube branded play button (light variant)
        play-youtube-red.svg           # YouTube branded play button (red variant)
        play-generic-dark.svg          # Generic play button for Vimeo (dark)
        play-generic-light.svg         # Generic play button for Vimeo (light)
```

**Entry point discovery:**
- `modules/Video/blocks/video/edit.jsx` -- auto-discovered as Vite entry point (matches `blocks/*/*.{js,mjs,jsx}`)
- `modules/Video/blocks/video/style.css` -- auto-discovered as Vite entry point
- `modules/Video/assets/scripts/player-manager.js` -- auto-discovered as Vite entry point (matches `assets/scripts/*.{js,mjs,jsx}`)

**block.json will reference:**
```json
{
  "editorScript": "file:./edit.jsx",
  "viewScript": "file:../../../assets/scripts/player-manager.js",
  "style": "file:./style.css"
}
```

Note: `viewScript` path may need adjustment based on how `ModuleAssets::blockAssetPath()` resolves relative paths. An alternative is to register `player-manager.js` via the module's PHP class using `$this->assets->registerScript()` and reference it by handle in `block.json`.

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Build system | Existing `@sitchco/module-builder` + Vite | `@wordpress/scripts` (wp-scripts) | wp-scripts uses Webpack, would conflict with existing Vite pipeline. The `wp_scripts()` plugin already provides the same WP externalization. |
| Block type | Native Gutenberg (`registerBlockType` + React `edit`) | ACF block mode | InnerBlocks flexibility, conditional inspector UI, oEmbed preview fetching, and first-class React control are all needed. ACF block mode cannot provide these. |
| Vimeo SDK loading | CDN lazy-load | `@vimeo/player` npm bundled into viewScript | Adds ~30KB to every page with a video block. CDN loading defers this cost to first play interaction. |
| YouTube wrapper | Direct `YT.Player` API | `youtube-player` npm (5.6.0) or `yt-player` npm (3.6.1) | Unnecessary abstraction. `YT.Player` is simple enough. These wrappers add bundle size and an extra dependency for minimal benefit. The main complexity (async loading, ready callbacks) we handle ourselves anyway. |
| oEmbed thumbnail fetch | WP Core REST proxy (`/oembed/1.0/proxy`) | Direct YouTube/Vimeo API calls from editor JS | WP proxy is already built-in, cached, handles auth, and doesn't expose API keys to the client. |
| React state management | `useState`/`useEffect` (component-local) | `@wordpress/data` custom store | Block state is simple (URL, mode, provider). No cross-block shared state needed. `useSelect` only for checking InnerBlocks count. |
| Video player library | Provider SDKs directly | `react-player`, `video.js`, `plyr` | These are full player UIs. This block needs programmatic SDK control with custom poster/play-button UI. Player libraries would fight the custom UI layer. |

## What NOT to Use

| Technology | Why Not |
|------------|---------|
| `@wordpress/scripts` (wp-scripts) | Webpack-based, conflicts with existing Vite build. Already have `wp_scripts()` externalization. |
| `react-player` | React wrapper around video embeds. Adds 50KB+, provides its own UI layer that conflicts with custom poster/play-button design. Not privacy-first (loads iframes eagerly). |
| `video.js` | Full video player framework. Massive overkill -- we only need SDK control of provider iframes, not a custom player UI. |
| `plyr` | Same as video.js -- provides a player UI skin. This block has its own poster/play-button design. |
| `lite-youtube-embed` / `lite-vimeo-embed` | Web components for lightweight YouTube/Vimeo embeds. Close in spirit but they own the full UI, don't support InnerBlocks poster override, and don't integrate with `sitchco.hooks` for mutual exclusion. The block needs deeper control. |
| WordPress Interactivity API (`viewScriptModule`) | Too new for this use case (WP 6.5+). The existing `sitchco.hooks` system provides the inter-module coordination layer. The block's frontend JS is vanilla (no React on frontend), so Interactivity API's reactive data binding adds complexity without benefit. |
| `@wordpress/create-block` | Scaffolding tool for wp-scripts projects. Not applicable -- this project has its own module conventions and build system. |
| npm-installed `@wordpress/*` packages | The build system externalizes these to WP globals. Installing them creates version drift between your `node_modules` and the WordPress runtime. |

## block.json Configuration

```json
{
  "apiVersion": 3,
  "name": "sitchco/video",
  "title": "Video",
  "category": "sitchco",
  "description": "Privacy-respecting video embed with click-to-play, provider branding, and modal support.",
  "keywords": ["video", "youtube", "vimeo", "embed"],
  "icon": "video-alt3",
  "editorScript": "file:./edit.jsx",
  "viewScript": "sitchco/video",
  "style": "file:./style.css",
  "supports": {
    "html": false,
    "align": ["wide", "full"],
    "spacing": {
      "margin": true,
      "padding": false
    }
  },
  "attributes": {
    "url": { "type": "string", "default": "" },
    "provider": { "type": "string", "enum": ["youtube", "vimeo", ""], "default": "" },
    "videoId": { "type": "string", "default": "" },
    "posterUrl": { "type": "string", "default": "" },
    "videoTitle": { "type": "string", "default": "" },
    "displayMode": { "type": "string", "enum": ["inline", "modal", "modal-only"], "default": "inline" },
    "playIconVariant": { "type": "string", "default": "dark" },
    "playIconX": { "type": "number", "default": 50 },
    "playIconY": { "type": "number", "default": 50 },
    "clickBehavior": { "type": "string", "enum": ["poster", "icon"], "default": "poster" },
    "modalId": { "type": "string", "default": "" },
    "startTime": { "type": "number", "default": 0 }
  }
}
```

**Note:** This is a native block, NOT an ACF block. There is no `"acf"` key. The `editorScript` points to a React JSX component that uses `registerBlockType()` with an `edit` function. The `save` function returns `null` (dynamic block, server-side rendered via `block.php`).

## WordPress Version Requirements

| Feature | Minimum WP Version | Notes |
|---------|-------------------|-------|
| `block.json` `apiVersion: 3` | 6.3 | Block API v3 with improved attribute handling |
| `useInnerBlocksProps` | 5.9 | Required for InnerBlocks poster override |
| `viewScript` auto-enqueue | 6.1 | Dynamic blocks auto-enqueue viewScript on pages using the block |
| oEmbed REST proxy | 4.7 | Long-standing, stable endpoint |
| `register_block_type()` from directory | 5.8 | Pass directory path containing `block.json` |

The project should target WordPress 6.3+ as minimum, which is reasonable for a platform-level mu-plugin in 2025/2026.

## Sources

- [WordPress Block Metadata (block.json)](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/) - HIGH confidence
- [WordPress Block Registration](https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/) - HIGH confidence
- [WordPress Edit and Save](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/) - HIGH confidence
- [WordPress InnerBlocks](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/nested-blocks-inner-blocks/) - HIGH confidence
- [YouTube IFrame Player API Reference](https://developers.google.com/youtube/iframe_api_reference) - HIGH confidence
- [YouTube Embedded Player Parameters](https://developers.google.com/youtube/player_parameters) - HIGH confidence
- [YouTube API ToS / Branding Guidelines](https://developers.google.com/youtube/terms/branding-guidelines) - MEDIUM confidence (specific play button requirements were not fully detailed in docs)
- [Vimeo Player SDK (GitHub)](https://github.com/vimeo/player.js/) - HIGH confidence
- [Vimeo Player SDK Embed Options](https://developer.vimeo.com/player/sdk/embed) - HIGH confidence
- [@kucrut/vite-for-wp](https://github.com/kucrut/vite-for-wp) - HIGH confidence (verified in codebase)
- [WordPress oEmbed Proxy (Trac #40450)](https://core.trac.wordpress.org/ticket/40450) - HIGH confidence
- [@wordpress/api-fetch](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/) - HIGH confidence
- [YouTube Privacy-Enhanced Mode](https://support.google.com/youtube/answer/171780) - MEDIUM confidence (nocookie limitations documented)
- [Vimeo DNT Parameter](https://help.vimeo.com/hc/en-us/articles/26080940921361-Vimeo-Player-Cookies) - MEDIUM confidence
