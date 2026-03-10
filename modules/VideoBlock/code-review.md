# Video Block Code Review

Reviewing `modules/VideoBlock/` against the spec at `/Users/jstrom/Projects/web/roundabout/video-component/video-design.md`.

Focus: DRY, SOLID, functional vs procedural, code elegance.

---

## DRY

### 1. Duplicated player creation functions (view.js)

`createYouTubePlayer` and `createModalYouTubePlayer` share ~90% of their code. Same for the Vimeo pair. The only differences: modal variants create a wrapper div, store a reference in `modalPlayers`, and add a `--ready` class.

```
createYouTubePlayer        (lines 63-87)
createModalYouTubePlayer   (lines 94-129)
createVimeoPlayer          (lines 144-161)
createModalVimeoPlayer     (lines 168-198)
```

Each pair should be a single function with a `modalId` parameter that optionally enables the modal behavior.

### 2. Duplicated thumbnail URL upgrade logic

The same regex replacements exist in two places with no shared abstraction:

- **render.php:48-65** `sitchco_video_upgrade_thumbnail_url()` as a named function
- **editor.jsx:348-353** inline in JSX as a nested ternary

```jsx
// editor.jsx - inlined
provider === 'youtube'
    ? oembedData.thumbnail_url.replace(/\/hqdefault\.jpg$/, '/maxresdefault.jpg')
    : provider === 'vimeo'
      ? oembedData.thumbnail_url.replace(/_\d+x\d+/, '_1280x720')
      : oembedData.thumbnail_url
```

This logic will drift. Extract a shared `upgradeThumbnailUrl(url, provider)` utility or at minimum a named function in the editor.

### 3. Duplicated event binding pattern (view.js)

`initVideoBlock()` repeats the same click + keyboard handler attachment pattern for both modal and inline modes (lines 332-405). The keyboard handler logic (Enter/Space check, `preventDefault`, dispatch action) appears twice with only the callback differing.

### 4. Redundant provider detection in oEmbed effect (editor.jsx)

Line 144 re-calls `detectProvider(url)` inside the `useEffect`, but `provider` is already set as an attribute by `onUrlChange()` and available in scope. The effect also doesn't include `_videoTitleEdited` or `_modalIdEdited` in its dependency array despite reading them in the `.then()` callback (lines 174, 178) -- a stale closure bug.

---

## SOLID

### 5. SRP: render.php does too much

This 238-line procedural script handles seven distinct responsibilities:

1. oEmbed fetching + caching
2. Thumbnail URL upgrading
3. Video ID extraction
4. Poster HTML generation
5. Play button HTML generation
6. Modal content building + UIModal registration
7. Wrapper output

Each of these is a separable concern. The file would benefit from a render class or at minimum moving the utility functions (oEmbed, thumbnail upgrade, video ID extraction) to a dedicated helper class on `VideoBlock` or a `VideoProvider` value object.

### 6. OCP: Provider logic is scattered if/else chains

Adding a third provider (e.g., Dailymotion) would require changes in **at least 8 locations** across 3 files:

| File | Locations |
|------|-----------|
| editor.jsx | `detectProvider()`, `getPlayIconSvg()`, thumbnail ternary, `playIconStyleOptions` |
| render.php | `sitchco_video_upgrade_thumbnail_url()`, `sitchco_video_extract_id()`, icon name selection |
| view.js | `handlePlay()` provider branch, start time extraction, SDK loader, player creator |

The spec explicitly notes "architecture supports [provider expansion], but no implementation." The current structure doesn't support it -- it actively resists it. A provider strategy (even a simple object map) would contain each provider's behavior in one place.

### 7. DIP: Global container access in render.php

```php
$uiModal = $GLOBALS['SitchcoContainer']->get(UIModal::class);
```

This is a service locator pattern -- the render template reaches into the global container directly. The `VideoBlock` module already declares `UIModal` as a dependency (line 10 of `VideoBlock.php`). The module should make the UIModal instance available to its render template through the block's render context, not through globals.

### 8. function_exists() guards are fragile (render.php)

Three global functions are defined inside the render template wrapped in `function_exists()`:

```php
if (!function_exists('sitchco_video_get_cached_oembed_data')) { ... }
if (!function_exists('sitchco_video_upgrade_thumbnail_url')) { ... }
if (!function_exists('sitchco_video_extract_id')) { ... }
```

This pattern:
- Pollutes the global namespace
- Silently accepts name collisions (first definition wins, no error)
- Runs the guard check on every block render

These should be static methods on the `VideoBlock` class or a dedicated `VideoProvider` helper class.

---

## Functional vs Procedural

### 9. render.php is imperative with scattered side effects

The template is a top-to-bottom imperative script that mixes pure computation (ID extraction, URL upgrading) with side effects (oEmbed fetching, `error_log`, modal registration, HTML output). There's no separation between data preparation and rendering.

A functional approach would build a data object (view model) first, then render it:

```php
// Prepare data (pure)
$viewModel = VideoBlockViewModel::fromAttributes($attributes, $block);

// Render (side effect, isolated)
echo $viewModel->render();
```

### 10. view.js uses global mutable state

`ytAPIPromise` and `modalPlayers` are module-level mutable variables (`var`). The file uses `var` exclusively instead of `const`/`let`, losing block-scoping guarantees. The `modalPlayers` Map mixes three concerns into one plain object: loading state, player reference, and provider type.

### 11. Complex conditional rendering in editor.jsx

The return JSX (lines 200-397) has 5 conditional blocks that are hard to follow as a flat sequence. Each represents a distinct visual state (no URL, loading, error, preview, placeholder, modal-only vs inline, play icon). Extracting these into named sub-components or a state-machine approach would clarify the rendering logic.

---

## Code Elegance

### 12. Debug logging in production code (render.php:114)

```php
error_log('$oembed: ' . stripcslashes(json_encode($oembed, JSON_PRETTY_PRINT)));
```

This is debug output that should not be in committed code. It fires on every page load for every video block without InnerBlocks.

### 13. Stale closure in oEmbed effect (editor.jsx)

The `useEffect` dependency array is `[url]` but the `.then()` callback reads `_videoTitleEdited` and `_modalIdEdited`. These values are captured at effect creation time and won't reflect changes that happen between the URL change and the async response:

```jsx
useEffect(() => {
    // ...
    .then((response) => {
        if (response.title && !_videoTitleEdited) {  // stale
            setAttributes({ videoTitle: response.title });
        }
        if (response.title && !_modalIdEdited) {      // stale
            setAttributes({ modalId: slugify(response.title) });
        }
    });
}, [url]);  // missing _videoTitleEdited, _modalIdEdited
```

If the author edits the title while an oEmbed request is in flight, the flag change won't be seen.

### 14. Nested ternary in JSX (editor.jsx:348-353)

The thumbnail URL computation is a nested ternary embedded directly in JSX `src` prop. This is hard to read at a glance and duplicates render.php logic (see finding #2).

### 15. Long sprintf chains (render.php)

Lines 148-155 and 199-208 have `sprintf` calls with 6-8 `%s` placeholders. Matching positional placeholders to arguments is error-prone:

```php
$modal_content = sprintf(
    '<div class="sitchco-video__modal-player" data-url="%s" data-provider="%s" data-video-id="%s" data-has-oembed-poster="%s" style="aspect-ratio: %s / %s">%s<div class="sitchco-video__spinner"></div></div>',
    esc_attr($url),
    esc_attr($provider),
    esc_attr($video_id),
    esc_attr($has_oembed_poster),
    esc_attr($aspect_w),
    esc_attr($aspect_h),
    $thumb_img,
);
```

Heredoc with interpolation or a template builder would be clearer.

### 16. Inconsistent play icon rendering

The editor renders inline SVGs (`getPlayIconSvg()`) while the frontend uses SVG sprites via `<use href="#icon-...">`. This means:
- Two different rendering codepaths to maintain
- Styling mechanisms diverge (editor uses direct attributes, frontend uses sprite inheritance)
- Visual parity between editor and frontend can't be guaranteed structurally

### 17. Missing `wp-data` dependency (editor.asset.php)

`editor.jsx` imports `useSelect` from `@wordpress/data` but `editor.asset.php` doesn't list `wp-data` in its dependencies array. This works by accident (another loaded script likely pulls it in) but is incorrect and could break in isolation.

### 18. Private attribute naming convention

`_videoTitleEdited` and `_modalIdEdited` use underscore prefixes as a convention for "internal" attributes, but they're stored in the block's serialized JSON alongside public attributes. This is a UI-state concern being persisted as content. Consider whether these flags could be derived (compare current value to what oEmbed would produce) rather than stored.

---

## Summary

| Category | # | Severity | Finding |
|----------|---|----------|---------|
| DRY | 1 | High | 4 player creation functions should be 2 |
| DRY | 2 | Medium | Thumbnail upgrade logic duplicated across JS/PHP |
| DRY | 3 | Medium | Event binding pattern repeated in initVideoBlock |
| DRY | 4 | Low | Redundant detectProvider() call in effect |
| SOLID | 5 | High | render.php has 7 responsibilities |
| SOLID | 6 | Medium | Provider logic scattered across 8+ locations in 3 files |
| SOLID | 7 | Medium | Service locator pattern via $GLOBALS |
| SOLID | 8 | Medium | function_exists() guards for global functions |
| Functional | 9 | High | render.php mixes data prep with side effects |
| Functional | 10 | Medium | Global mutable state + var usage in view.js |
| Functional | 11 | Low | Complex flat conditional JSX |
| Elegance | 12 | High | Debug error_log in production code |
| Elegance | 13 | High | Stale closure bug in oEmbed effect |
| Elegance | 14 | Low | Nested ternary in JSX |
| Elegance | 15 | Low | Long positional sprintf chains |
| Elegance | 16 | Low | Editor/frontend SVG rendering divergence |
| Elegance | 17 | Medium | Missing wp-data dependency declaration |
| Elegance | 18 | Low | Persisted UI state as block attributes |

**Top 3 actionable items:**

1. **Fix the stale closure bug (#13)** -- this is a correctness issue, not just style.
2. **Remove debug logging (#12)** -- fires in production on every render.
3. **Consolidate player creation functions (#1)** -- the most impactful DRY win; halves the code surface for SDK integration.
