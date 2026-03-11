# Phase 2: Poster Rendering & Inline Playback - Research

**Researched:** 2026-03-09
**Domain:** Server-side poster rendering (oEmbed + InnerBlocks), accessible play button overlay, click-to-play inline playback with provider SDKs, privacy-enhanced embeds, layout-shift prevention
**Confidence:** HIGH

## Summary

Phase 2 transforms the editor-only video block from Phase 1 into a functional frontend component. The work spans two distinct domains: (1) PHP server-side rendering in `render.php` that outputs poster images, play button SVGs, and accessibility attributes; and (2) JavaScript client-side behavior in `view.js` that handles click-to-play SDK loading, iframe creation, and dimension locking. The privacy constraint (zero provider resources before click) is the architectural spine -- it dictates that all poster content must be server-rendered and all SDK/iframe loading must be deferred to user interaction.

The existing codebase provides strong foundations: `sitchco.loadScript()` for deduplicating script loads, `sitchco.register()` for DOMContentLoaded lifecycle, the SvgSprite module for `<svg><use>` patterns, and the block's data attributes for passing configuration to JavaScript. The YouTube IFrame API's global `onYouTubeIframeAPIReady` callback requires a Promise-based singleton wrapper (documented in PITFALLS.md). The Vimeo Player SDK is simpler -- loaded via CDN script, instantiated with `new Vimeo.Player(element, options)`.

**Primary recommendation:** Structure the work as render.php expansion (poster + play button + accessibility), SVG sprite creation, view.js implementation (SDK loading + player creation), and CSS for frontend states -- in that order, with the server-side rendering delivering a fully functional static experience before any JavaScript is added.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Fallback chain: InnerBlocks > oEmbed thumbnail > generic placeholder
- InnerBlocks always win -- wrapper checks only whether they exist (POST-04)
- Generic placeholder when no thumbnail: neutral dark/light box with generic play icon, no provider branding
- Poster aspect ratio should match the actual video dimensions (oEmbed response includes width/height)
- When oEmbed fails entirely (no response), default to 16:9 aspect ratio for the placeholder
- JS reads and locks current rendered dimensions via inline CSS on click (no CSS aspect-ratio logic for the player)
- Portrait poster locks to poster's rendered dimensions; no letterboxing
- SVG sprite with `<use>` references -- source SVGs in `modules/VideoBlock/assets/images/svg-sprite/`
- Build tooling generates sitchco-core sprite at `dist/assets/images/sprite.svg`
- render.php outputs `<svg><use href="sprite.svg#icon-name"></svg>`
- Fixed size: 68px width (matches editor preview)
- Hardcoded fills in SVG source files (no currentColor)
- 5 icon variants in sprite: youtube-play-dark, youtube-play-light, youtube-play-red, generic-play-dark, generic-play-light
- Single viewScript file (view.js) handles both YouTube and Vimeo
- Initialize via `sitchco.register()` lifecycle hook
- YouTube IFrame API global callback wrapped in a Promise via `sitchco.loadScript()`
- Vimeo Player SDK loaded via `sitchco.loadScript()`
- SDKs load on first click only -- subsequent clicks reuse already-loaded SDK
- Once playing, player's native controls take over
- Start time from URL parameters respected (INLN-06)

### Claude's Discretion
- Exact generic placeholder styling (colors, icon sizing within placeholder)
- How render.php resolves the sprite URL (Vite manifest lookup vs direct path)
- Internal structure of view.js (class-based vs function-based, how provider branching is organized)
- Error handling for SDK load failures
- How to extract video ID from URL for SDK player construction

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| POST-01 | Server-side oEmbed resolves video URL and renders `<img>` with provider thumbnail as poster | wp_oembed_get() or WP_oEmbed::get_data() for raw response; must cache with transients; response includes thumbnail_url, width, height |
| POST-02 | When InnerBlocks present, InnerBlocks content renders as poster -- oEmbed not executed | render.php checks `!empty(trim($content))` to detect InnerBlocks presence |
| POST-03 | Author can add any block type as InnerBlocks | No allowedBlocks restriction needed; already unrestricted in Phase 1 editor.jsx |
| POST-04 | Wrapper checks only whether InnerBlocks exist (not content) | Simple string emptiness check on `$content` parameter |
| POST-05 | Graceful fallback when oEmbed returns no thumbnail | Generic placeholder div with play icon; neutral dark styling |
| INLN-01 | Click loads provider SDK if not already loaded | Promise-based singleton loaders for YouTube IFrame API and Vimeo Player SDK |
| INLN-02 | On click, wrapper locks current rendered dimensions via inline CSS | `element.style.width = el.offsetWidth + 'px'; element.style.height = el.offsetHeight + 'px'` |
| INLN-03 | Poster content and play icon hidden after click | CSS class toggle (e.g., `sitchco-video--playing`) hides poster elements |
| INLN-04 | Iframe created inside wrapper at 100% width and 100% height | SDK creates iframe; CSS sets `width: 100%; height: 100%` |
| INLN-05 | Playback begins automatically once player ready | YouTube: `events.onReady: (e) => e.target.playVideo()`; Vimeo: `player.play()` |
| INLN-06 | Start time from URL parameters respected | YouTube: `playerVars.start` (seconds); Vimeo: constructor option or `setCurrentTime()` |
| INLN-07 | No provider SDK/iframe/CDN loads before user clicks play | Enforced by architecture: view.js only injects scripts on click handler |
| PRIV-01 | No browser-initiated network requests to providers on page load | Server-side oEmbed is acceptable; no client-side provider requests |
| PRIV-02 | YouTube embeds use youtube-nocookie.com domain | YouTube IFrame API `host` parameter: `'https://www.youtube-nocookie.com'` |
| PRIV-03 | Vimeo embeds include dnt parameter | Vimeo Player constructor option: `{ dnt: true }` |
| ACCS-01 | Play overlay is a `<button>` with aria-label including video title | render.php outputs `<button class="sitchco-video__play-button" aria-label="Play video: {title}">` |
| ACCS-02 | Play button is keyboard focusable and activatable | Native `<button>` element provides this automatically |
| ACCS-03 | Entire-poster click mode wrapper has role="button", tabindex="0" | render.php conditionally adds `role="button" tabindex="0"` when clickBehavior is "poster" |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| YouTube IFrame API | Current (loaded dynamically) | YouTube player creation and control | Only official way to programmatically control YouTube embeds; required for autoplay, pause, state tracking |
| Vimeo Player SDK | 2.x (CDN) | Vimeo player creation and control | Official SDK; Promise-based API; handles iframe creation from div element |
| WordPress oEmbed | Core (6.x) | Server-side video metadata fetch | Built into WordPress; handles provider discovery, URL validation, thumbnail/title extraction |

### Supporting (Existing in Project)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| sitchco.loadScript() | Existing | Promise-based deduplicating script loader | Loading YouTube IFrame API and Vimeo Player SDK on first click |
| sitchco.register() | Existing | DOMContentLoaded lifecycle hook | Initializing video block click handlers |
| sitchco.hooks | Existing | @wp/hooks wrapper (action/filter system) | Play icon SVG filter (EXTN-06 in Phase 4); video lifecycle hooks (Phase 4) |
| SvgSprite module | Existing | SVG sprite generation and `<use>` rendering | Play icon SVGs in render.php |
| @sitchco/module-builder | Existing | Vite build pipeline with svgstore sprite generation | Building sprite.svg from source SVGs in svg-sprite/ directory |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| YouTube IFrame API | lite-youtube-embed | lite-youtube loses SDK control needed for mutual exclusion (Phase 4) and analytics (Phase 4) |
| Vimeo Player SDK via CDN | npm @vimeo/player bundled | CDN avoids bundle size; SDK is only needed after user click; no benefit to bundling |
| wp_oembed_get() | WP_oEmbed::get_data() | get_data() returns raw object with thumbnail_url/width/height directly; wp_oembed_get() returns HTML string requiring parsing. Use get_data() for structured access |

## Architecture Patterns

### Recommended Project Structure
```
modules/VideoBlock/
    blocks/video/
        block.json            # Add viewScript field
        editor.jsx            # Existing (Phase 1)
        editor.asset.php      # Existing (Phase 1)
        render.php            # Major expansion: poster, play button, accessibility
        style.css             # Expand: frontend poster/player states
        view.js               # NEW: click-to-play, SDK loading, player creation
        view.asset.php        # NEW: dependencies sidecar (sitchco/ui-framework)
    assets/
        images/
            svg-sprite/       # NEW: play icon source SVGs
                icon-youtube-play-dark.svg
                icon-youtube-play-light.svg
                icon-youtube-play-red.svg
                icon-generic-play-dark.svg
                icon-generic-play-light.svg
    VideoBlock.php            # May need expansion for oEmbed caching
```

### Pattern 1: Server-Side Poster Rendering (render.php)
**What:** render.php resolves the poster source (InnerBlocks vs oEmbed vs placeholder), outputs the poster markup, play button, and all accessibility attributes. Zero JavaScript needed for the static poster display.
**When to use:** Always -- the poster must render server-side for privacy (no client-side provider requests) and for SEO/accessibility.

```php
// Poster resolution chain
$has_inner_blocks = !empty(trim($content));

if ($has_inner_blocks) {
    // InnerBlocks ARE the poster -- render them directly
    $poster_html = $content;
} else {
    // Fetch oEmbed data for thumbnail
    $oembed = get_oembed_data($attributes['url']); // cached wrapper
    if ($oembed && !empty($oembed->thumbnail_url)) {
        $poster_html = sprintf(
            '<img class="sitchco-video__poster-img" src="%s" alt="%s" width="%s" height="%s" loading="lazy">',
            esc_url($oembed->thumbnail_url),
            esc_attr($oembed->title ?? ''),
            esc_attr($oembed->width ?? ''),
            esc_attr($oembed->height ?? '')
        );
    } else {
        // Generic placeholder
        $poster_html = '<div class="sitchco-video__placeholder-poster"></div>';
    }
}
```

### Pattern 2: oEmbed Data Caching with Transients
**What:** Wrap `WP_oEmbed::get_data()` with transient caching to avoid HTTP requests on every page load.
**When to use:** Every render.php invocation that needs oEmbed data.

```php
function get_cached_oembed_data(string $url): ?object {
    $cache_key = 'sitchco_oembed_' . md5($url);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached ?: null; // empty string = cached failure
    }
    $oembed = _wp_oembed_get_object()->get_data($url, []);
    // Cache for 30 days (YouTube/Vimeo thumbnails rarely change)
    set_transient($cache_key, $oembed ?: '', 30 * DAY_IN_SECONDS);
    return $oembed ?: null;
}
```

### Pattern 3: SVG Sprite Integration
**What:** Place source SVG files in `modules/VideoBlock/assets/images/svg-sprite/`. The build system (`@sitchco/module-builder`) automatically generates `dist/assets/images/sprite.svg`. The SvgSprite module injects it into the page via `wp_body_open` and provides `<use href="#icon-name">` references.
**When to use:** All play icon rendering in render.php.

The SvgSprite module's `buildSpriteContents()` method:
1. In production: reads `dist/assets/images/sprite.svg` and outputs it in `wp_body_open`
2. In dev server: reads source SVGs directly from `modules/*/assets/images/svg-sprite/`

render.php references icons via:
```php
$icon_name = $provider === 'youtube' ? "youtube-play-{$style}" : "generic-play-{$style}";
$svg = sprintf(
    '<svg class="sitchco-video__play-icon-svg" aria-hidden="true" width="68" height="%s"><use href="#icon-%s"></use></svg>',
    $provider === 'youtube' ? '48' : '68',
    esc_attr($icon_name)
);
```

Note: The SvgSprite module scans ALL config paths (both sitchco-core and theme). Source SVGs placed in `modules/VideoBlock/assets/images/svg-sprite/` will be discovered automatically -- the path pattern `modules/*/assets/images/svg-sprite/*.svg` matches. The sprite is inlined in the page body, so `<use href="#icon-name">` references work without external file fetching.

### Pattern 4: YouTube IFrame API Singleton Loader
**What:** A Promise-based wrapper around the YouTube IFrame API's global `onYouTubeIframeAPIReady` callback. Uses `sitchco.loadScript()` for script injection with deduplication.
**When to use:** Every YouTube video play click.

```javascript
let ytAPIPromise = null;

function loadYouTubeAPI() {
    if (ytAPIPromise) return ytAPIPromise;

    if (window.YT && window.YT.Player) {
        ytAPIPromise = Promise.resolve(window.YT);
        return ytAPIPromise;
    }

    ytAPIPromise = new Promise((resolve) => {
        const prev = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            if (prev) prev();
            resolve(window.YT);
        };
        // Use sitchco.loadScript for deduplication
        sitchco.loadScript('youtube-iframe-api', 'https://www.youtube.com/iframe_api');
    });

    return ytAPIPromise;
}
```

### Pattern 5: Vimeo Player SDK Loader
**What:** Loads the Vimeo Player SDK from CDN and creates player instances.
**When to use:** Every Vimeo video play click.

```javascript
function loadVimeoSDK() {
    return sitchco.loadScript('vimeo-player', 'https://player.vimeo.com/api/player.js');
}

async function createVimeoPlayer(container, videoId, startTime) {
    await loadVimeoSDK();
    const player = new Vimeo.Player(container, {
        id: videoId,
        width: '100%',
        autoplay: true,
        dnt: true,  // PRIV-03
    });
    if (startTime > 0) {
        player.ready().then(() => player.setCurrentTime(startTime));
    }
    return player;
}
```

### Pattern 6: Click-to-Play with Dimension Locking
**What:** On click, read rendered dimensions, lock them with inline CSS, hide poster, load SDK, create player.
**When to use:** Every play interaction.

```javascript
function handlePlay(wrapper) {
    // INLN-02: Lock dimensions before any DOM changes
    wrapper.style.width = wrapper.offsetWidth + 'px';
    wrapper.style.height = wrapper.offsetHeight + 'px';

    // INLN-03: Hide poster and play button
    wrapper.classList.add('sitchco-video--playing');

    // Create player container
    const playerContainer = document.createElement('div');
    playerContainer.className = 'sitchco-video__player';
    wrapper.appendChild(playerContainer);

    // Load SDK and create player based on provider
    const provider = wrapper.dataset.provider;
    const url = wrapper.dataset.url;
    // ... provider-specific logic
}
```

### Pattern 7: viewScript Registration via block.json
**What:** Add `viewScript` field to block.json pointing to `view.js`. The framework's `ModuleAssets` resolves the file path through Vite manifest in production, dev server URL in development.
**When to use:** Frontend JS that should only load when the block is present on the page.

```json
{
    "viewScript": "file:./view.js"
}
```

The `view.asset.php` sidecar declares dependencies:
```php
<?php
return [
    'dependencies' => ['sitchco/ui-framework'],
    'version' => null,
];
```

Important: `ModuleAssets` registers viewScript with `strategy => 'defer'`, which means the script loads deferred. The `sitchco.register()` pattern (DOMContentLoaded) handles initialization timing.

### Anti-Patterns to Avoid
- **Loading SDK in page head/footer unconditionally:** Violates PRIV-01/INLN-07. SDK must only load on user click.
- **Clearing iframe src to stop video:** Destroys player state. Use SDK `pauseVideo()` / `pause()` instead.
- **Using `wp_oembed_get()` without caching in render_callback:** Makes HTTP request to provider on every page view.
- **Client-side oEmbed fetch for poster:** Poster must be server-rendered. Client-side fetch would require provider resources before click.
- **Inline SVG in render.php (duplicated per block):** Use sprite `<use>` references to avoid DOM bloat with multiple video blocks.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Script deduplication | Custom Map of loaded scripts | `sitchco.loadScript()` | Already exists, handles race conditions, Promise-based |
| SVG sprite generation | Manual SVG concatenation | `@sitchco/module-builder` svgstore plugin | Existing build pipeline, auto-discovers from `svg-sprite/` dirs |
| SVG sprite page injection | Manual `wp_body_open` hook | SvgSprite module `buildSpriteContents()` | Already handles production vs dev server, scans all config paths |
| YouTube API callback management | Direct `window.onYouTubeIframeAPIReady` assignment | Promise-based singleton wrapper | Handles race conditions, multiple blocks, already-loaded state |
| URL validation for providers | Custom regex from scratch | Leverage the `detectProvider()` function from editor.jsx (port to both PHP and JS) | Already validated pattern; supports youtube.com, youtu.be, vimeo.com |
| oEmbed provider resolution | Custom HTTP fetch to YouTube/Vimeo APIs | `WP_oEmbed::get_data()` | WordPress handles provider discovery, URL validation, response parsing |

**Key insight:** The existing sitchco-core infrastructure (loadScript, SvgSprite, hooks, register lifecycle) provides nearly all the plumbing needed. The main new code is: render.php poster logic, view.js player logic, and SVG source files.

## Common Pitfalls

### Pitfall 1: wp_oembed_get() Does Not Cache
**What goes wrong:** Every page load with a video block makes an HTTP request to YouTube/Vimeo to fetch oEmbed data, slowing page loads and risking rate limiting.
**Why it happens:** `wp_oembed_get()` (and `WP_oEmbed::get_data()`) do not cache results when called in custom code. WordPress only auto-caches oEmbed for URLs parsed from post_content via the content filter.
**How to avoid:** Wrap with transient caching. Use a cache key based on `md5($url)`. Set TTL to 30 days (thumbnails rarely change).
**Warning signs:** Slow TTFB on pages with video blocks. Provider API rate limit errors.

### Pitfall 2: YouTube IFrame API Global Callback Race Condition
**What goes wrong:** Multiple video blocks or third-party plugins overwrite `window.onYouTubeIframeAPIReady`, causing some blocks to never initialize.
**Why it happens:** YouTube API design requires exactly one global callback. Direct assignment (`window.onYouTubeIframeAPIReady = fn`) overwrites previous registrations.
**How to avoid:** Use Promise-based singleton loader pattern (see Architecture Pattern 4). Check for `window.YT.Player` before loading. Chain previous callback.
**Warning signs:** First video works, second doesn't. Intermittent "YT is undefined" errors.

### Pitfall 3: CLS on Poster-to-Iframe Swap
**What goes wrong:** Content below the video jumps when poster is replaced with iframe. Fails Core Web Vitals CLS threshold.
**Why it happens:** Poster image has natural dimensions from `width`/`height` attributes. Iframe has no inherent size. Without dimension locking, the wrapper collapses briefly.
**How to avoid:** Read and lock `offsetWidth`/`offsetHeight` as inline CSS BEFORE any DOM changes (hiding poster, adding iframe).
**Warning signs:** Visual "jump" when clicking play. Lighthouse CLS score above 0.1.

### Pitfall 4: YouTube Thumbnail maxresdefault 404
**What goes wrong:** Attempting to upgrade thumbnail to higher resolution fails silently -- YouTube returns a gray placeholder image with 200 status for missing maxresdefault.
**Why it happens:** Not all YouTube videos have maxresdefault thumbnails (older videos, shorts, live streams). YouTube serves a valid JPEG body even for 404s.
**How to avoid:** Use the thumbnail URL from oEmbed response directly (`hqdefault` quality). Do not attempt client-side resolution upgrade.
**Warning signs:** Gray placeholder images on some videos. `img.onerror` does not fire.

### Pitfall 5: Privacy-Enhanced Mode Does Not Prevent API Script Load
**What goes wrong:** `youtube-nocookie.com` only affects the embed iframe domain. The IFrame API script (`youtube.com/iframe_api`) still loads from youtube.com.
**Why it happens:** YouTube's API architecture requires the script from youtube.com regardless of nocookie setting.
**How to avoid:** The click-to-load architecture is the primary privacy mechanism. `youtube-nocookie.com` is a belt-and-suspenders measure via the `host` parameter in `YT.Player` constructor. No provider resources load before click regardless.
**Warning signs:** Network requests to youtube.com/iframe_api on page load (would indicate a bug in click-to-load implementation).

### Pitfall 6: viewScript file: Path Resolution with Vite
**What goes wrong:** `"viewScript": "file:./view.js"` in block.json may not resolve correctly through the Vite build pipeline.
**Why it happens:** The `ModuleAssets::blockAssetPath()` method strips `file:` prefix and resolves relative to `blocksPath`. If `view.js` is in the block directory, it should resolve. But the `view.asset.php` sidecar must be manually created (Vite doesn't auto-generate it like @wordpress/scripts).
**How to avoid:** Place `view.js` next to `block.json` in `blocks/video/`. Create `view.asset.php` manually listing `sitchco/ui-framework` as dependency. This mirrors the working `editor.jsx` + `editor.asset.php` pattern from Phase 1.
**Warning signs:** Script not loading on frontend. Console error about missing handle.

## Code Examples

### oEmbed Data Retrieval (PHP)
```php
// Source: WordPress WP_oEmbed::get_data() + transient caching
function sitchco_video_get_oembed_data(string $url): ?object {
    $cache_key = 'sitchco_voembed_' . md5($url);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached ?: null;
    }
    $oembed = _wp_oembed_get_object()->get_data($url, []);
    set_transient($cache_key, $oembed ?: '', 30 * DAY_IN_SECONDS);
    return $oembed ?: null;
}
// oEmbed response object properties (YouTube/Vimeo):
// ->thumbnail_url, ->thumbnail_width, ->thumbnail_height
// ->title, ->width, ->height, ->provider_name
```

### Video ID Extraction (JavaScript)
```javascript
// Extract video ID from various URL formats
function extractYouTubeId(url) {
    const match = url.match(
        /(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/
    );
    return match ? match[1] : null;
}

function extractVimeoId(url) {
    const match = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    return match ? match[1] : null;
}
```

### Start Time Extraction (JavaScript)
```javascript
// YouTube: ?t=30s, ?t=1m30s, ?t=90, &start=90
function extractYouTubeStartTime(url) {
    const urlObj = new URL(url);
    // Check 't' parameter (watch URLs, short URLs)
    let t = urlObj.searchParams.get('t') || urlObj.searchParams.get('start');
    if (!t) return 0;
    // Handle formats: "90", "90s", "1m30s", "1h2m30s"
    t = String(t);
    const hMatch = t.match(/(\d+)h/);
    const mMatch = t.match(/(\d+)m/);
    const sMatch = t.match(/(\d+)s?$/);
    let seconds = 0;
    if (hMatch) seconds += parseInt(hMatch[1]) * 3600;
    if (mMatch) seconds += parseInt(mMatch[1]) * 60;
    if (sMatch && !mMatch && !hMatch) seconds = parseInt(sMatch[1]); // plain number
    else if (sMatch) seconds += parseInt(sMatch[1]);
    return seconds;
}

// Vimeo: #t=90s, #t=1m30s
function extractVimeoStartTime(url) {
    const hash = url.split('#')[1] || '';
    const match = hash.match(/t=(\d+)s?/);
    return match ? parseInt(match[1]) : 0;
}
```

### YouTube Player Creation (JavaScript)
```javascript
// Source: YouTube IFrame API Reference
async function createYouTubePlayer(container, videoId, startTime) {
    const YT = await loadYouTubeAPI();
    return new Promise((resolve) => {
        const player = new YT.Player(container, {
            videoId: videoId,
            host: 'https://www.youtube-nocookie.com', // PRIV-02
            playerVars: {
                autoplay: 1,        // INLN-05
                playsinline: 1,
                enablejsapi: 1,
                origin: window.location.origin,
                start: startTime,    // INLN-06
                rel: 0,
            },
            events: {
                onReady: (event) => {
                    event.target.playVideo(); // INLN-05
                    resolve(player);
                },
            },
        });
    });
}
```

### Vimeo Player Creation (JavaScript)
```javascript
// Source: Vimeo Player SDK Reference
async function createVimeoPlayer(container, videoId, startTime) {
    await loadVimeoSDK();
    const player = new Vimeo.Player(container, {
        id: parseInt(videoId),
        autoplay: true,   // INLN-05
        dnt: true,         // PRIV-03
    });
    if (startTime > 0) {
        player.ready().then(() => player.setCurrentTime(startTime));
    }
    return player;
}
```

### Accessible Play Button (PHP)
```php
// ACCS-01, ACCS-02: Native <button> with aria-label
$play_button = sprintf(
    '<button class="sitchco-video__play-button" aria-label="%s" style="left:%s%%;top:%s%%;transform:translate(-50%%,-50%%)">%s</button>',
    esc_attr(sprintf(__('Play video: %s', 'sitchco'), $video_title)),
    esc_attr($play_icon_x),
    esc_attr($play_icon_y),
    $play_icon_svg // The <svg><use href="#icon-name"></use></svg> markup
);
```

### Entire-Poster Click Mode (PHP)
```php
// ACCS-03: Wrapper gets role="button" and tabindex="0" when clickBehavior is "poster"
$is_poster_click = ($attributes['clickBehavior'] ?? 'poster') === 'poster';
$wrapper_attrs = [
    'class' => 'sitchco-video',
    'data-url' => $attributes['url'],
    'data-provider' => $attributes['provider'] ?? '',
    // ... other data attributes
];
if ($is_poster_click) {
    $wrapper_attrs['role'] = 'button';
    $wrapper_attrs['tabindex'] = '0';
    $wrapper_attrs['aria-label'] = sprintf(__('Play video: %s', 'sitchco'), $video_title);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Unconditional iframe embed | Click-to-load with poster facade | 2020+ (lite-youtube-embed popularized) | Zero provider resources before user intent; better privacy and performance |
| Client-side oEmbed fetch | Server-side oEmbed with caching | Always best practice | No client-side provider requests needed for poster |
| YouTube cookie domain | youtube-nocookie.com via `host` param | YouTube IFrame API feature | Privacy-enhanced embedding (belt-and-suspenders with click-to-load) |
| Vimeo iframe with tracking | `dnt: true` constructor option | Vimeo Player SDK feature | Blocks session tracking and cookies |

**Deprecated/outdated:**
- YouTube `player.stopVideo()`: Use `pauseVideo()` instead -- stopVideo puts player in unpredictable state
- YouTube `startSeconds` in loadVideoById: Use `playerVars.start` in constructor for initial load

## Open Questions

1. **oEmbed caching location: transients vs post meta**
   - What we know: WordPress core caches oEmbed in post meta for content-parsed URLs. Custom `wp_oembed_get()` calls are not cached.
   - What's unclear: Whether to use transients (simpler, auto-expires) or post meta (survives transient purges, tied to specific post).
   - Recommendation: Use transients. They are simpler, the oEmbed data can always be re-fetched if the transient expires, and tying to post meta adds unnecessary complexity for a cache that is URL-keyed not post-keyed.

2. **Vimeo start time: constructor vs setCurrentTime**
   - What we know: Vimeo SDK does not appear to have a direct "start at" constructor option. The `setCurrentTime()` method exists and returns a Promise.
   - What's unclear: Whether there is a URL parameter Vimeo honors for start time in the embedded player.
   - Recommendation: Use `player.ready().then(() => player.setCurrentTime(seconds))` after construction. This is reliable and documented.

3. **viewScript with Vite -- dependency chain**
   - What we know: `editorScript` with `editor.asset.php` works (proven in Phase 1). `viewScript` should follow the same pattern.
   - What's unclear: Whether the `sitchco/ui-framework` handle resolves correctly as a viewScript dependency (it works for UIModal's viewScript but that uses a registered handle, not `file:` prefix).
   - Recommendation: Follow the UIModal pattern: if `file:./view.js` has issues, fall back to registering the script via `VideoBlock.php::init()` using `$this->registerAssets()` and referencing the handle in block.json. But try `file:` first since `editor.jsx` already uses this pattern successfully.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (WordPress integration via wp-phpunit) |
| Config file | `/Users/jstrom/Projects/web/roundabout/public/phpunit.xml` |
| Quick run command | `ddev test-phpunit --filter=VideoBlockTest` |
| Full suite command | `ddev test-phpunit` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| POST-01 | oEmbed thumbnail renders as poster img | unit | `ddev test-phpunit --filter=test_render_with_oembed_thumbnail` | Wave 0 |
| POST-02 | InnerBlocks present, renders as poster | unit | `ddev test-phpunit --filter=test_render_innerblocks_as_poster` | Wave 0 |
| POST-04 | Wrapper checks InnerBlocks existence only | unit | `ddev test-phpunit --filter=test_innerblocks_presence_check` | Wave 0 |
| POST-05 | Generic placeholder on oEmbed failure | unit | `ddev test-phpunit --filter=test_render_generic_placeholder` | Wave 0 |
| PRIV-02 | YouTube nocookie domain in data attribute | unit | `ddev test-phpunit --filter=test_youtube_nocookie` | Wave 0 |
| ACCS-01 | Play button has aria-label with title | unit | `ddev test-phpunit --filter=test_play_button_aria_label` | Wave 0 |
| ACCS-03 | Poster click mode has role=button | unit | `ddev test-phpunit --filter=test_poster_click_mode_accessibility` | Wave 0 |
| INLN-01 through INLN-07 | Click-to-play JS behavior | manual-only | Browser DevTools Network tab verification | N/A (JS) |
| PRIV-01 | No provider requests on page load | manual-only | Browser DevTools Network tab verification | N/A |
| PRIV-03 | Vimeo dnt parameter | manual-only | Browser DevTools iframe src inspection | N/A (JS) |
| ACCS-02 | Keyboard activation | manual-only | Tab to play button, press Enter/Space | N/A |

### Sampling Rate
- **Per task commit:** `ddev test-phpunit --filter=VideoBlockTest`
- **Per wave merge:** `ddev test-phpunit`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` -- extend existing file with POST-01, POST-02, POST-04, POST-05, ACCS-01, ACCS-03, PRIV-02 test methods
- [ ] HTTP mocking via `$this->fakeHttp()` for oEmbed responses -- needed to test render.php without real network calls

## Sources

### Primary (HIGH confidence)
- [YouTube IFrame Player API Reference](https://developers.google.com/youtube/iframe_api_reference) -- YT.Player constructor, playerVars, host parameter, events
- [Vimeo Player SDK (npm)](https://www.npmjs.com/package/@vimeo/player) -- Constructor options, dnt parameter, methods, CDN URL
- [WordPress wp_oembed_get()](https://developer.wordpress.org/reference/functions/wp_oembed_get/) -- Function signature, return value
- [WordPress WP_oEmbed::get_data()](https://developer.wordpress.org/reference/classes/wp_oembed/get_data/) -- Raw oEmbed data access

### Secondary (MEDIUM confidence)
- [WordPress oEmbed caching patterns](https://salferrarello.com/caching-wordpress-oembed-calls/) -- wp_oembed_get() caching limitations, transient pattern
- [YouTube nocookie with IFrame API](https://portalzine.de/dev/html5/youtube-iframe-api-and-cookieless-domain-solution-gdpr-dsgvo/) -- host parameter for privacy-enhanced mode
- Project PITFALLS.md research -- verified against official docs (YouTube API race condition, thumbnail 404, privacy limitations)

### Tertiary (LOW confidence)
- YouTube start time URL parsing -- community regex patterns, not officially documented by YouTube

### Project Sources (HIGH confidence - direct codebase inspection)
- `modules/UIFramework/assets/scripts/lib/script-registration.js` -- loadScript() API
- `modules/UIFramework/assets/scripts/main.js` -- sitchco.register() lifecycle
- `modules/SvgSprite/SvgSprite.php` -- SVG sprite rendering pattern, `<use>` references, path discovery
- `node_modules/@sitchco/module-builder/src/vite-plugin/svgstore-sprite.js` -- sprite build from `dist/assets/images/svg-sprite/` source
- `modules/VideoBlock/blocks/video/render.php` -- current Phase 1 render output
- `modules/VideoBlock/blocks/video/block.json` -- current attribute schema
- `src/Framework/ModuleAssets.php` -- viewScript registration with `strategy => 'defer'`, Vite manifest resolution
- `tests/Modules/VideoBlock/VideoBlockTest.php` -- existing test patterns, renderBlock helper

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- YouTube IFrame API and Vimeo Player SDK are the only official options; WordPress oEmbed is built-in
- Architecture: HIGH -- patterns verified against existing codebase (SvgSprite, loadScript, ModuleAssets, UIModal viewScript)
- Pitfalls: HIGH -- documented in project's own PITFALLS.md and cross-verified with official API docs
- viewScript resolution: MEDIUM -- `file:` prefix works for editorScript but viewScript with Vite has a noted concern in STATE.md

**Research date:** 2026-03-09
**Valid until:** 2026-04-09 (stable APIs, unlikely to change)
