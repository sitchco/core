# render.php Architecture

Structural improvements to the server-side rendering template. These findings are interrelated -- addressing SRP (#5) naturally resolves the others.

Source review: `modules/VideoBlock/code-review.md`

---

## 5. SRP: render.php has 7 responsibilities

**Severity: High**

This 238-line procedural script handles:

1. oEmbed fetching + caching
2. Thumbnail URL upgrading
3. Video ID extraction
4. Poster HTML generation
5. Play button HTML generation
6. Modal content building + UIModal registration
7. Wrapper output

Each of these is a separable concern. Extract utility functions (oEmbed, thumbnail upgrade, video ID extraction) to static methods on `VideoBlock` or a dedicated `VideoProvider` helper class. The render template should focus on assembling HTML from pre-computed data.

---

## 7. DIP: Global container access

**Severity: Medium**

```php
$uiModal = $GLOBALS['SitchcoContainer']->get(UIModal::class);
```

This is a service locator pattern -- the render template reaches into the global container directly. The `VideoBlock` module already declares `UIModal` as a dependency (line 10 of `VideoBlock.php`). The module should make the UIModal instance available to its render template through the block's render context, not through globals.

---

## 8. function_exists() guards are fragile

**Severity: Medium**

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

These should be static methods on `VideoBlock` or a dedicated helper class.

---

## 9. Imperative style mixes data prep with side effects

**Severity: High**

The template is a top-to-bottom imperative script that mixes pure computation (ID extraction, URL upgrading) with side effects (oEmbed fetching, `error_log`, modal registration, HTML output). There's no separation between data preparation and rendering.

A functional approach would build a data object (view model) first, then render it:

```php
// Prepare data (pure)
$viewModel = VideoBlockViewModel::fromAttributes($attributes, $block);

// Render (side effect, isolated)
echo $viewModel->render();
```

---

## 15. Long sprintf chains

**Severity: Low**

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
