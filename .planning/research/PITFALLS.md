# Pitfalls Research

**Domain:** Native Gutenberg video block with provider SDK integration, oEmbed, InnerBlocks, and modal composition
**Researched:** 2026-03-09
**Confidence:** HIGH (verified against official docs and codebase)

## Critical Pitfalls

### Pitfall 1: YouTube IFrame API Global Singleton Callback Race Condition

**What goes wrong:**
The YouTube IFrame API requires a single global `window.onYouTubeIframeAPIReady` callback. If the video block loads the API script and another plugin or page element also loads it, or if multiple video blocks try to set the callback, only the last assignment wins. Blocks that set the callback before the API loads lose their reference; blocks that set it after the API has already loaded never fire at all.

**Why it happens:**
YouTube's API design mandates exactly one global callback function (`onYouTubeIframeAPIReady`) attached to `window`. This is fundamentally at odds with a component-based architecture where multiple independent video blocks may exist on a page. Developers often assign the callback directly (`window.onYouTubeIframeAPIReady = myInit`) without checking whether the API has already loaded or whether another consumer has already registered.

**How to avoid:**
Build a singleton SDK loader that:
1. Checks if `window.YT` and `window.YT.Player` already exist (API already loaded).
2. If already loaded, resolves immediately.
3. If not loaded, queues callbacks and injects the script tag once.
4. Wraps the global callback to flush the queue and resolve all pending promises.

Return a Promise from the loader so each block instance can `await loadYouTubeAPI()` without caring about load order.

```javascript
// Pattern: Promise-based singleton loader
let apiPromise = null;
function loadYouTubeAPI() {
    if (apiPromise) return apiPromise;
    apiPromise = new Promise((resolve) => {
        if (window.YT && window.YT.Player) {
            resolve(window.YT);
            return;
        }
        const prev = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            if (prev) prev();
            resolve(window.YT);
        };
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(tag);
    });
    return apiPromise;
}
```

**Warning signs:**
- Videos work individually but fail when multiple are on the same page
- Intermittent "YT is undefined" errors in console
- Videos work on first load but break on subsequent navigations (SPA-like behavior)

**Phase to address:**
Provider SDK integration phase -- this must be the foundation of all YouTube playback code.

---

### Pitfall 2: Dynamic Block + InnerBlocks Save Function Conflict

**What goes wrong:**
Dynamic blocks (server-rendered via `render_callback`) conventionally return `null` from their `save` function. But when a block uses InnerBlocks, returning `null` from `save` causes InnerBlocks content to not be persisted to the database at all. The editor shows the inner blocks, but after save and reload, they vanish.

**Why it happens:**
WordPress stores block content (including InnerBlocks) in the post_content column as serialized HTML comments. When `save` returns `null`, WordPress skips serializing the block entirely -- including its InnerBlocks. The `render_callback` receives `$content` (the serialized inner blocks) as a parameter, but there is nothing to receive if nothing was saved.

**How to avoid:**
The `save` function must return `<InnerBlocks.Content />` wrapped in the block's outer element, even though the block itself is dynamically rendered. The `render_callback` then receives the serialized InnerBlocks as the `$content` parameter.

```javascript
// WRONG: Loses InnerBlocks on save
save: () => null,

// CORRECT: Persists InnerBlocks, block still renders server-side
save: () => {
    const blockProps = useBlockProps.save();
    const innerBlocksProps = useInnerBlocksProps.save(blockProps);
    return <div {...innerBlocksProps} />;
},
```

The `render_callback` in PHP then replaces the wrapper but preserves `$content` (the serialized inner blocks).

**Warning signs:**
- InnerBlocks appear in the editor but disappear after saving and reloading the post
- `$content` parameter in `render_callback` is always empty string
- Block validation errors in browser console after save/reload cycle

**Phase to address:**
Block registration phase -- the very first working block skeleton must get this right, or all InnerBlocks work is built on a broken foundation.

---

### Pitfall 3: Hook Call Order -- useBlockProps Before useInnerBlocksProps

**What goes wrong:**
If `useInnerBlocksProps` is called before `useBlockProps` in the edit component, `useBlockProps` returns an empty object. The block loses its wrapper class, editor selection behavior, toolbar positioning, and all block supports. The block appears to work superficially but is actually broken in subtle ways.

**Why it happens:**
The WordPress block editor internal state management requires `useBlockProps` to initialize block context before `useInnerBlocksProps` can merge its props correctly. This is documented but easy to miss because no error is thrown -- it fails silently.

**How to avoid:**
Always call `useBlockProps()` first, then pass its result as the first argument to `useInnerBlocksProps()`:

```javascript
// CORRECT order
const blockProps = useBlockProps();
const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ['core/image', 'core/cover'],
});
return <div {...innerBlocksProps} />;
```

Enforce this in code review. Consider a lint rule or code comment convention that marks the call order as load-bearing.

**Warning signs:**
- Block wrapper element missing the `wp-block-sitchco-video` class in the editor
- Block toolbar not appearing when block is selected
- Block supports (align, spacing, etc.) not applying
- No visible error in console

**Phase to address:**
Block registration phase -- establish the correct edit component skeleton from the start.

---

### Pitfall 4: Video Continues Playing After Modal Close

**What goes wrong:**
When a user closes a modal containing a video iframe, the video audio continues playing in the background. This is the single most common complaint in modal+video implementations. The `<dialog>` element's `close` event fires and the modal visually disappears, but the iframe's media playback state is independent of DOM visibility.

**Why it happens:**
HTML iframes are self-contained browsing contexts. Hiding, moving, or visually obscuring an iframe does not pause its media. The `<dialog>` element's `close()` method only manages the dialog's open/close state and backdrop -- it has no awareness of iframe content. Developers assume closing the modal stops everything inside it.

**How to avoid:**
Hook into the UIModal `ui-modal-hide` action to pause video before the modal closes:

```javascript
addAction('ui-modal-hide', (modal) => {
    const videoContainer = modal.querySelector('[data-video-id]');
    if (videoContainer) {
        // Use the provider SDK to pause, not iframe src manipulation
        pauseVideo(videoContainer);
    }
}, 5, 'video'); // Priority 5 = runs before modal's own close at priority 10
```

Use provider SDK `player.pauseVideo()` (YouTube) or `player.pause()` (Vimeo) rather than clearing the iframe src. Clearing src destroys the player state and forces a full reload on next open, which is slow and wastes bandwidth.

**Warning signs:**
- Audio continues after closing modal (most obvious)
- Video position resets to start on modal re-open (indicates src-clearing workaround was used instead of SDK pause)
- Memory usage grows with each modal open/close cycle (indicates iframe recreation without cleanup)

**Phase to address:**
Modal composition phase -- implement pause-on-close immediately when wiring video into UIModal.

---

### Pitfall 5: YouTube Thumbnail 404 for maxresdefault

**What goes wrong:**
The oEmbed response from YouTube includes a `thumbnail_url` field, but this typically points to `hqdefault.jpg` (480x360). Developers often try to upgrade to `maxresdefault.jpg` (1280x720) for better quality posters. However, `maxresdefault.jpg` does not exist for all YouTube videos -- particularly older videos, shorts, and some live streams. The image request returns a 404 status code, but YouTube still serves a valid JPEG body (a gray placeholder), so `img.onerror` does not fire.

**Why it happens:**
YouTube's image CDN returns a 200-like response with `content-type: image/jpeg` even for missing thumbnails. The browser treats it as a successful image load. Standard error handling (`onerror`, checking `naturalWidth`) does not catch this failure mode.

**How to avoid:**
Use the thumbnail URL from the oEmbed response directly (`hqdefault.jpg`) rather than attempting to upgrade resolution. If higher resolution is desired:
1. Use `fetch()` to HEAD-request the `maxresdefault.jpg` URL server-side (in the REST endpoint that fetches oEmbed data).
2. If the response is 404, fall back to the oEmbed-provided thumbnail.
3. Cache the result alongside the oEmbed data.

Do not attempt client-side resolution detection -- it adds unnecessary complexity and network requests on every page load.

**Warning signs:**
- Gray placeholder images appearing as video posters on some videos but not others
- Poster images looking fine in development but broken on certain production content
- QA reports of "wrong thumbnail" on specific videos

**Phase to address:**
oEmbed integration phase -- build the thumbnail fetching logic with fallback from the start.

---

### Pitfall 6: Privacy-Enhanced Mode Does Not Prevent Script Loading

**What goes wrong:**
Using `youtube-nocookie.com` for the embed domain gives a false sense of privacy compliance. The YouTube IFrame API script itself (`https://www.youtube.com/iframe_api`) must still be loaded from `youtube.com`. Cookie consent tools (CMP) block this script because it originates from `youtube.com`, breaking the entire player. Additionally, YouTube still sets localStorage identifiers even in "privacy-enhanced" mode.

**Why it happens:**
Developers conflate "nocookie embed domain" with "no tracking." The embed domain (`youtube-nocookie.com`) only affects iframe content delivery. The SDK/API script that controls playback is always served from `youtube.com`. The project's click-to-load architecture should handle this, but the implementation must be precise.

**How to avoid:**
The click-to-load architecture is the correct solution: no provider resources (SDK, iframe, CDN) load until the user clicks play. Verify that:
1. No `<script src="youtube.com/iframe_api">` tag exists in the page source before user interaction.
2. No `<iframe>` with a provider domain exists before user interaction.
3. The SDK script is injected dynamically only after click.
4. The embed iframe uses `youtube-nocookie.com` as a belt-and-suspenders measure, not the primary privacy mechanism.

For Vimeo, append `dnt=1` to the embed URL. Note that `dnt=1` still sets some cookies (`player_clearance`, Cloudflare cookies), so click-to-load remains the primary privacy mechanism.

**Warning signs:**
- Network tab showing requests to `youtube.com` or `player.vimeo.com` on page load (before any user interaction)
- Cookie consent tool blocking the video player entirely
- Privacy audit tools flagging third-party cookies from video providers

**Phase to address:**
Provider SDK integration phase -- click-to-load is a core constraint, not an enhancement.

---

### Pitfall 7: Block Deprecation Debt from Early Attribute Changes

**What goes wrong:**
Every time a block's `save` output or attributes change, existing block instances in the database become "invalid." WordPress shows a block validation error in the editor, and the block falls back to the Classic editor or shows "This block contains unexpected or invalid content." Without a deprecation entry, users must manually recover every affected block instance.

**Why it happens:**
This is the first native Gutenberg block in sitchco-core. Without established patterns for handling block evolution, attribute renames and markup changes during early development create deprecation debt. Each change without a deprecation entry is a potential data migration problem. The pain compounds because migrations do not chain -- each deprecation must independently produce valid output from its `save` function.

**How to avoid:**
1. Lock the attribute schema and save markup early. Do not rename attributes or restructure save output after the block ships to any content.
2. During development (before any content exists), changes are free -- but establish a "freeze" point before the block is used in production.
3. After freeze: every attribute change or save output change requires a deprecation entry.
4. Critical: deprecation `save` functions must be self-contained. Do not import utility functions that might change -- inline or snapshot the logic.
5. When migrating InnerBlocks, the `migrate` function receives `(attributes, innerBlocks)` and must return `[newAttributes, newInnerBlocks]`.

**Warning signs:**
- Block validation errors in the editor console after a plugin update
- "This block contains unexpected or invalid content" messages for existing content
- Attribute names being discussed/renamed after blocks are already in use

**Phase to address:**
Block registration phase -- define the attribute schema with future-proofing in mind. Establish the deprecation pattern before shipping.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Clearing iframe `src` to stop video | Simple one-line pause | Destroys player state, forces full reload on next play, wastes bandwidth, loses watch position | Never -- use SDK `pause()` instead |
| Hardcoding provider detection via string matching | Quick YouTube/Vimeo detection | Breaks on URL format changes, short URLs, embed URLs, privacy domains | During prototyping only -- replace with robust URL parsing before shipping |
| Skipping oEmbed caching in the editor | Faster iteration during development | Hammers provider APIs, hits rate limits, slow editor experience | Never in production -- always cache oEmbed responses |
| Single monolithic edit component | Faster initial development | Unmaintainable as inspector controls, preview states, and provider logic grow | Only for the first working prototype -- refactor before adding second display mode |
| Using `ServerSideRender` in edit for InnerBlocks preview | Avoids writing JS preview logic | ServerSideRender does not support InnerBlocks (receives empty `$content`), hits URL length limits with complex blocks | Never for blocks with InnerBlocks |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| YouTube IFrame API | Loading the API script tag in the page head or footer unconditionally | Inject the script tag dynamically only after user clicks play. Use a singleton Promise-based loader that handles the global callback |
| YouTube IFrame API | Calling `player.stopVideo()` to pause | `stopVideo()` puts the player in an unpredictable "not playing" state. Use `player.pauseVideo()` for predictable pause behavior |
| YouTube IFrame API | Using `player.playVideo()` and expecting it to count as a view | Playback initiated via API does not count toward YouTube's official view count -- only native play button clicks do. This is by design per YouTube ToS |
| Vimeo Player SDK | Treating methods as synchronous | Every Vimeo Player method (except `on()`/`off()`) returns a Promise. Calling `player.play()` without awaiting can cause race conditions with subsequent state queries |
| Vimeo Player SDK | Constructing Player from an iframe that already has the Vimeo JS SDK loaded via `data-vimeo-*` attributes | If the Vimeo SDK auto-initializes from data attributes, manually constructing a `new Vimeo.Player()` on the same element creates a duplicate player. Use element-based construction only when you control the iframe creation |
| WordPress oEmbed proxy | Calling `/wp-json/oembed/1.0/proxy` without authentication | The proxy endpoint requires `edit_posts` capability. Unauthenticated requests return 403. This is intentional (SSRF prevention) but means the endpoint only works in the editor context, not on the front end |
| WordPress oEmbed | Using `wp_oembed_get()` in render_callback without caching | `wp_oembed_get()` does NOT cache results by default. Each page load makes an HTTP request to the provider. Use transients or post meta to cache results manually |
| `block.json` asset registration | Expecting `editorScript` with Vite builds to auto-generate `.asset.php` | WordPress expects a `.asset.php` sidecar file next to scripts declared in `block.json`. `@wordpress/scripts` generates these automatically; Vite does not. The sitchco-core asset pipeline handles this differently via `ModuleAssets` -- use the existing pattern rather than `block.json` script fields |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Loading provider SDK on page load instead of on click | 200-400KB of JavaScript loaded before any user interaction; poor LCP/TTI scores | Lazy-load SDK only after click-to-play. No `<script>` tags for provider APIs in initial page source | Immediately on pages with video blocks (especially mobile) |
| Creating a new iframe on every modal open | Each open creates a new iframe, loads the SDK, initializes the player. Noticeable 1-2s delay on each open. Memory grows with each cycle | Create the iframe once on first play, then pause/resume on subsequent open/close. The iframe persists in the `<dialog>` DOM | Noticeable after 2-3 open/close cycles, severe after 5+ |
| oEmbed fetch on every render_callback invocation | Server-side HTTP request to YouTube/Vimeo on every page view if not cached. Page load blocked on external API response time | Cache oEmbed data in post meta or transients with `oembed_ttl` filter. WordPress core caches oEmbed in post meta, but only for URLs embedded via the content parser -- not for `wp_oembed_get()` calls in custom code | Under normal traffic, but catastrophic if provider API is slow or rate-limited |
| No dimension locking on inline poster-to-iframe swap | CLS (Cumulative Layout Shift) spike when poster image is replaced with iframe. Content below the video jumps | Lock the container dimensions with `aspect-ratio` or explicit `width`/`height` before swapping poster for iframe. Use the oEmbed `width`/`height` values as the aspect ratio source | Fails Google Core Web Vitals; noticeable on any page with inline video |
| YouTube thumbnail resolution upgrade via client-side waterfall | Page load triggers img request for maxresdefault -> 404 placeholder -> JS detects failure -> requests hqdefault -> renders. Double the network requests, visible poster flash | Resolve thumbnail URL server-side during oEmbed fetch. Cache the verified URL | Any page with YouTube videos, more visible on slower connections |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Accepting arbitrary URLs in the video URL attribute without validation | SSRF if the URL is fetched server-side; XSS if rendered in iframe src without sanitization | Validate URLs against a whitelist of known provider patterns (YouTube, Vimeo). Use `esc_url()` on all URL outputs. Validate server-side, not just in the editor UI |
| Rendering user-provided oEmbed HTML without sanitization | XSS via malicious oEmbed response if the provider whitelist is bypassed | Use `wp_kses_post()` on oEmbed HTML. Only fetch from WordPress's registered oEmbed providers. Never accept arbitrary oEmbed endpoints |
| Exposing the oEmbed proxy endpoint to unauthenticated users | SSRF -- attackers can use the server as a proxy to scan internal networks | WordPress core already restricts `/oembed/1.0/proxy` to `edit_posts` capability. If building a custom REST endpoint for oEmbed, replicate this permission check |
| Storing raw provider API keys or tokens in block attributes | Keys visible in page source (block attributes are serialized in post_content) | Never store API keys in block attributes. Use server-side configuration (`wp-config.php` or environment variables) for any provider credentials |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| oEmbed fetch with no loading state in editor | Author pastes URL, nothing happens for 1-3 seconds, then poster appears. Feels broken | Show a spinner or skeleton immediately when URL changes. Debounce the fetch (300-500ms) to avoid hammering the API on every keystroke |
| Play icon positioned over face/important content in poster | The play button covers the most important visual element. Authors have no control | Provide X/Y position controls in the inspector panel so authors can offset the play icon. Default to center but allow adjustment |
| InnerBlocks poster with no visual boundary in editor | Authors don't understand what the InnerBlocks area is for. They add random blocks thinking it's a general content area | Use `allowedBlocks` to restrict to image/cover blocks. Add placeholder text: "Add an image or cover block to customize the video poster." Use an `Appender` with a descriptive label |
| Modal-only mode with no visual indicator in editor | Authors add a modal-only video block and see nothing on the page. They think it's broken or delete it | Show a clear visual placeholder in the editor: "This video will appear in a modal. Trigger it with a link to #{modalId}." Include the ID prominently so they can copy it |
| Play icon disappears or is invisible on light/dark posters | Generic dark play icon on dark poster, or light icon on light poster | Provide dark/light variant selection. For YouTube, the red branded icon has consistent visibility across backgrounds |
| No feedback when video URL is invalid or unsupported | Author pastes a URL, nothing happens. No error, no poster, no feedback | Validate the URL on change. Show an inline error notice: "This URL doesn't look like a YouTube or Vimeo video. Supported formats: ..." |

## "Looks Done But Isn't" Checklist

- [ ] **Inline playback:** Often missing dimension locking -- verify no CLS when poster swaps to iframe (test with Chrome Lighthouse)
- [ ] **Modal playback:** Often missing pause-on-close -- verify video stops when pressing Escape, clicking backdrop, and clicking close button (all three dismiss paths)
- [ ] **Mutual exclusion:** Often missing cross-mode coordination -- verify that playing an inline video pauses a modal video, and vice versa
- [ ] **Keyboard accessibility:** Often missing keyboard activation of play button -- verify play works with Enter and Space keys, not just click
- [ ] **Deep linking:** Often missing scroll-to behavior -- verify `example.com/page#video-id` opens the modal on page load, even for modal-only videos in `wp_footer`
- [ ] **YouTube branded play icon:** Often missing dark/light variants -- verify the icon is the official YouTube SVG, not a generic triangle (legal/ToS requirement)
- [ ] **oEmbed caching:** Often missing cache invalidation -- verify that changing the video URL in the editor fetches fresh oEmbed data (not stale cached data from the previous URL)
- [ ] **Block deprecation:** Often missing for attribute changes -- verify that editing a post with an older version of the block does not show validation errors
- [ ] **Privacy/click-to-load:** Often broken by preconnect hints -- verify no `<link rel="preconnect">` tags for provider domains appear before user interaction
- [ ] **Start time:** Often lost when constructing the iframe -- verify `?t=30` or `#t=30` in the original URL translates to `&start=30` in the YouTube embed or `#t=30s` in Vimeo

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| InnerBlocks not persisting (null save) | MEDIUM | Add correct save function with `<InnerBlocks.Content />`. Existing blocks without InnerBlocks are unaffected. Blocks where authors added InnerBlocks that were silently lost require re-adding the inner content |
| Block validation errors (missing deprecation) | HIGH | Write deprecation entries retroactively. Each previous version of the save output needs its own deprecation with `attributes`, `save`, and `migrate`. Must test against actual database content. Cannot be automated safely |
| Video playing after modal close | LOW | Add the `ui-modal-hide` hook listener. Purely additive fix, no data migration needed |
| YouTube thumbnail 404 placeholders | MEDIUM | Switch to server-side thumbnail resolution with caching. Existing blocks need their cached thumbnail URLs regenerated. Can be batched via WP-CLI |
| Provider SDK loaded on page load | LOW | Move script injection to the click handler. Purely architectural change, no data impact |
| Attribute schema changes without deprecation | HIGH | Must write deprecation chain covering all historical versions. The longer this is deferred, the more versions to cover. Worst case: manual block recovery in the database |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| YouTube API global callback race | SDK integration | Multiple video blocks on same page all play correctly. Console has no "YT is undefined" errors |
| Dynamic block + InnerBlocks save | Block registration (skeleton) | Save, reload, and verify InnerBlocks persist. Check `$content` parameter in render_callback is non-empty |
| useBlockProps/useInnerBlocksProps order | Block registration (skeleton) | Block wrapper has correct CSS class in editor. Block toolbar appears on selection |
| Video continues after modal close | Modal composition | Close modal via Escape, backdrop click, and close button. Verify silence after each |
| YouTube thumbnail 404 | oEmbed integration | Test with a video known to lack maxresdefault (older videos, shorts). Verify poster loads correctly |
| Privacy-enhanced mode false sense | SDK integration | Network tab audit: zero provider requests before user click. Cookie audit: no provider cookies on page load |
| Block deprecation debt | Block registration (schema freeze) | Define attribute schema and mark freeze point. After freeze, every change has a deprecation entry |
| CLS on inline poster-to-iframe swap | Inline playback | Lighthouse CLS score below 0.1 on pages with inline video |
| oEmbed caching in render_callback | oEmbed integration | Load a page with video 100 times. Verify zero outbound HTTP requests to provider oEmbed endpoints (all served from cache) |
| ServerSideRender with InnerBlocks | Block registration | Do not use ServerSideRender in the edit component. Use client-side React rendering for the editor preview |

## Sources

- [YouTube IFrame Player API Reference](https://developers.google.com/youtube/iframe_api_reference) -- official API docs (HIGH confidence)
- [YouTube API Required Minimum Functionality](https://developers.google.com/youtube/terms/required-minimum-functionality) -- ToS for play buttons, overlays, sizing (HIGH confidence)
- [YouTube Branding Guidelines](https://developers.google.com/youtube/terms/branding-guidelines) -- branded play button requirements (HIGH confidence)
- [Vimeo Player SDK Basics](https://developer.vimeo.com/player/sdk/basics) -- SDK architecture and async behavior (HIGH confidence)
- [Vimeo Player SDK Reference](https://developer.vimeo.com/player/sdk/reference) -- method signatures and return types (HIGH confidence)
- [Vimeo player.js GitHub](https://github.com/vimeo/player.js/) -- SDK source, issues, and community patterns (HIGH confidence)
- [WordPress InnerBlocks Tutorial](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/nested-blocks-inner-blocks/) -- useBlockProps/useInnerBlocksProps hook order requirement (HIGH confidence)
- [WordPress Block Deprecation](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/) -- deprecation mechanics and migrate function (HIGH confidence)
- [WordPress Dynamic Blocks](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/creating-dynamic-blocks/) -- save null vs InnerBlocks.Content (HIGH confidence)
- [WordPress oEmbed Proxy Trac #40450](https://core.trac.wordpress.org/ticket/40450) -- proxy endpoint architecture and security (HIGH confidence)
- [YouTube Thumbnail URLs](https://github.com/paulirish/lite-youtube-embed/blob/master/youtube-thumbnail-urls.md) -- maxresdefault 404 behavior documentation (MEDIUM confidence)
- [Drupal oEmbed Lazyload Issue #3346967](https://www.drupal.org/project/oembed_lazyload/issues/3346967) -- maxresdefault 404 with valid JPEG body (MEDIUM confidence)
- [Stefan Judis: The lie of YouTube's privacy-enhanced embed mode](https://www.stefanjudis.com/notes/the-lie-of-youtubes-privacy-enhanced-embed-mode/) -- nocookie limitations (MEDIUM confidence)
- [Elementor Issue #34684](https://github.com/elementor/elementor/issues/34684) -- privacy-enhanced mode still loads youtube.com script (MEDIUM confidence)
- [Vimeo DNT Documentation](https://help.vimeo.com/hc/en-us/articles/26080940921361-Vimeo-Player-Cookies) -- dnt=1 cookie behavior (HIGH confidence)
- [WordPress oEmbed Caching](https://polevaultweb.com/2019/05/clearing-the-wordpress-oembed-cache/) -- wp_oembed_get vs post meta caching (MEDIUM confidence)
- [Eliminating Layout Shifts in the Video Block](https://weston.ruter.net/2025/06/05/eliminating-layout-shifts-in-the-video-block/) -- CLS prevention patterns (MEDIUM confidence)
- [10up Inner Blocks Best Practices](https://gutenberg.10up.com/reference/Blocks/inner-blocks/) -- InnerBlocks patterns and save requirements (MEDIUM confidence)
- [Gutenberg InnerBlocks in Dynamic Blocks Discussion #44466](https://github.com/WordPress/gutenberg/discussions/44466) -- save null with InnerBlocks failure mode (MEDIUM confidence)

---
*Pitfalls research for: sitchco/video native Gutenberg block*
*Researched: 2026-03-09*
