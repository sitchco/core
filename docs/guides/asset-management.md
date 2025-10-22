# Asset Management

## TL;DR

Use `ModuleAssets` helper to enqueue CSS/JS from module's `assets/` directory. Supports development (Vite) and production builds with automatic dependency management.

## Quick Example

**See:** [Common Patterns - Module with Assets](common-patterns.md#3-module-with-assets)

```php
$this->enqueueFrontendAssets(function (ModuleAssets $assets) {
    $assets->style('main.css');      // Resolves to: assets/styles/main.css
    $assets->script('main.js', dependencies: ['jquery']);  // Resolves to: assets/scripts/main.js
});
```

**Note:** Asset paths are automatically resolved:
- `script('file.js')` → `assets/scripts/file.js`
- `style('file.css')` → `assets/styles/file.css`

---

## Asset Directory Structure

```
your-theme/              # Or mu-plugin
├── sitchco.config.php
├── dist/                # ← Single build output for ALL modules (production)
│   ├── .vite/
│   └── assets/
│       ├── module-main-ABC123.js    # Hashed filenames
│       └── module-styles-GHI789.css
└── modules/
    └── MyModule/
        ├── MyModuleModule.php
        └── assets/      # ← Source files (development)
            ├── scripts/ # ← JavaScript files
            │   ├── main.js
            │   ├── admin.js
            │   └── editor-ui.js
            └── styles/  # ← CSS files
                ├── main.css
                └── admin.css
```

**Development:** Uses `modules/*/assets/` (Vite dev server)
**Production:** All modules bundled into single root-level `dist/` folder

**Important:** Assets must be organized in `scripts/` and `styles/` subdirectories within the `assets/` folder. The ModuleAssets helper automatically resolves paths like `'main.js'` to `assets/scripts/main.js`.

---

## ModuleAssets API

### Enqueue Methods

| Method | Hook | Use For | Typical Asset Names |
|--------|------|---------|---------------------|
| `enqueueFrontendAssets()` | `wp_enqueue_scripts` | Frontend CSS/JS | `main.js`, `main.css` |
| `enqueueAdminAssets()` | `admin_enqueue_scripts` | Admin dashboard | `admin.js`, `admin.css` |
| `enqueueEditorUIAssets()` | `enqueue_block_editor_assets` | Block editor toolbar/inspector | `editor-ui.js` |
| `enqueueEditorPreviewAssets()` | `enqueue_block_assets` (admin only) | Block editor preview pane | `editor-preview.js` |
| `enqueueGlobalAssets()` | `enqueue_block_assets` | All contexts (frontend + editor) | `core.css`, `main.css` |
| `registerAssets()` | `init` (priority 20) | Register without enqueueing | Any |
| `enqueueBlockStyles()` | `init` (priority 30) | Per-block styles | `block-{name}.css` |

### Asset Registration

```php
// CSS
$assets->style(
    'main.css',                     // File name
    dependencies: ['wp-components'], // Optional
    media: 'all'                     // Optional
);

// JavaScript
$assets->script(
    'main.js',                      // File name
    dependencies: ['jquery'],        // Optional
    inFooter: true                   // Optional (default: true)
);
```

---

## Common Patterns

### Frontend Only

```php
public function init(): void
{
    $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
        $assets->style('main.css');
        $assets->script('main.js');
    });
}
```

### Admin Dashboard

```php
$this->enqueueAdminAssets(function (ModuleAssets $assets) {
    $assets->style('admin.css');
    $assets->script('admin.js', dependencies: ['jquery']);
});
```

### Block Editor

```php
// Block editor toolbar and inspector controls
$this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
    $assets->script('editor-ui.js', dependencies: [
        'wp-blocks',
        'wp-element',
        'wp-hooks',
        'wp-compose',
    ]);
});

// Block editor preview pane (shows how blocks look in editor)
$this->enqueueEditorPreviewAssets(function (ModuleAssets $assets) {
    $assets->script('editor-preview.js', dependencies: ['sitchco/ui-framework']);
    $assets->style('admin-editor.css');
});
```

### Conditional Loading

```php
$this->enqueueFrontendAssets(function (ModuleAssets $assets) {
    // Always load
    $assets->style('main.css');

    // Conditional
    if (is_singular('event')) {
        $assets->script('event-single.js');
    }

    if (is_post_type_archive('event')) {
        $assets->script('event-archive.js');
    }
});
```

### Register Without Enqueueing

```php
// Register
$this->registerAssets(function (ModuleAssets $assets) {
    $assets->script('gallery-modal.js', dependencies: ['jquery']);
});

// Enqueue conditionally elsewhere
add_action('wp_enqueue_scripts', function () {
    if (has_block('sitchco/gallery')) {
        wp_enqueue_script('gallery-modal');
    }
});
```

---

## Dependencies

### WordPress Core

```php
$assets->script('main.js', dependencies: [
    'jquery',           // jQuery
    'wp-element',       // React
    'wp-blocks',        // Block editor
    'wp-components',    // WP Components
    'wp-api-fetch',     // REST API
    'wp-data',          // Data stores
]);

$assets->style('editor.css', dependencies: [
    'wp-components',    // WP Component styles
    'wp-edit-blocks',   // Block editor styles
]);
```

### Module Dependencies

Assets from other modules are auto-registered:

```php
// Parent: ContentPartialModule
$assets->style('content-partial.css');  // Handle: content-partial

// Child: EventModule
$assets->style('event.css', dependencies: ['content-partial']);
```

### External Dependencies

```php
// Register external library
add_action('wp_enqueue_scripts', function () {
    wp_register_script('swiper',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        [], '11.0.0'
    );
}, 5);

// Use as dependency
$this->enqueueFrontendAssets(function (ModuleAssets $assets) {
    $assets->script('gallery.js', dependencies: ['swiper']);
});
```

---

## Development vs. Production

### Development (Vite Dev Server)

```php
// Loads: http://localhost:5173/assets/main.js
$assets->script('main.js');
```

### Production (Built Assets)

```php
// Loads: /wp-content/themes/my-theme/dist/assets/main-ABC123.js (hashed)
$assets->script('main.js');  // ModuleAssets resolves to bundled asset
```

**Key Points:**
- Build creates ONE `dist/` folder at root level (sibling to `sitchco.config.php`)
- All modules bundled together with hashed filenames
- ModuleAssets automatically resolves source file names to bundled assets

### Build Process

```bash
# Development (Vite dev server)
npm run dev

# Production build (outputs to root-level dist/)
npm run build
```

---

## Inline Styles and Scripts

### Inline Script

```php
$this->enqueueFrontendAssets(function (ModuleAssets $assets) {
    $assets->script('main.js');
});

add_action('wp_enqueue_scripts', function () {
    wp_add_inline_script('main', '
        window.gallerySettings = ' . json_encode([
            'autoplay' => true,
            'interval' => 5000,
        ]) . ';
    ', 'before');
});
```

### Inline Style

```php
add_action('wp_enqueue_scripts', function () {
    $customCss = '.gallery { background: ' . get_theme_mod('gallery_bg', '#fff') . '; }';
    wp_add_inline_style('main', $customCss);
});
```

---

## Localization

```php
$this->enqueueFrontendAssets(function (ModuleAssets $assets) {
    $assets->script('main.js');
});

add_action('wp_enqueue_scripts', function () {
    wp_localize_script('main', 'galleryData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gallery-nonce'),
        'strings' => [
            'loading' => __('Loading...', 'sitchco'),
            'error' => __('Error loading gallery', 'sitchco'),
        ],
    ]);
});
```

**In JavaScript:**
```javascript
console.log(galleryData.ajaxUrl);
console.log(galleryData.strings.loading);
```

---

## Asset Naming Conventions

### Standard Entry Points

The framework has informal conventions for naming asset entry points based on their loading context:

| Filename | Enqueue Method | Hook | Use Case |
|----------|---------------|------|----------|
| `main.js` / `main.css` | `enqueueFrontendAssets()` | `wp_enqueue_scripts` | General frontend assets |
| `main.mjs` | `registerAssets()` / `enqueueFrontendAssets()` | `init` / `wp_enqueue_scripts` | ES modules |
| `admin.js` / `admin.css` | `enqueueAdminAssets()` | `admin_enqueue_scripts` | Admin dashboard |
| `editor-ui.js` | `enqueueEditorUIAssets()` | `enqueue_block_editor_assets` | Block editor toolbar/inspector |
| `editor-preview.js` | `enqueueEditorPreviewAssets()` | `enqueue_block_assets` (admin) | Block editor preview pane |
| `core.css` | `enqueueFrontendAssets()` | `wp_enqueue_scripts` | Core theme/framework styles |

**File Extensions Supported:** `.js`, `.mjs`, `.jsx`, `.css`

### When to Use Standard Names

Use standard entry point names when your module has assets that:
- Load globally in their context (all frontend pages, all admin pages, etc.)
- Represent the primary asset for that loading context
- Don't require conditional loading logic

**Example:**
```php
// Theme module with standard entry points
public function init(): void
{
    // Frontend
    $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
        $assets->enqueueStyle('theme', 'main.css');  // → assets/styles/main.css
        $assets->enqueueScript('theme', 'main.js');  // → assets/scripts/main.js
    });

    // Admin
    $this->enqueueAdminAssets(function (ModuleAssets $assets) {
        $assets->enqueueStyle('admin', 'admin.css');  // → assets/styles/admin.css
    });

    // Block Editor UI
    $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
        $assets->enqueueScript('editor', 'editor-ui.js', [
            'wp-blocks', 'wp-element'
        ]);
    });
}
```

### When to Use Custom Names

Use custom/descriptive filenames when your assets:
- Load conditionally based on page type, settings, or features
- Provide specific functionality (e.g., `sticky.js`, `overlay.js`)
- Are block-specific styles (e.g., `block-media-text.css`)
- Represent multiple entry points in the same context

**Example:**
```php
// SiteHeader module with conditional features
public function stickyHeader(): void
{
    $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
        // Custom names for feature-specific assets
        $assets->registerScript('site-header/overlay/js', 'overlay.js');
        $assets->enqueueScript('site-header/sticky/js', 'sticky.js', [
            'sitchco/ui-framework',
            'sitchco/site-header/overlay/js',
        ]);
    });
}

// Gallery module with conditional loading
public function init(): void
{
    $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
        // Always load base styles
        $assets->style('main.css');

        // Conditionally load gallery features
        if (is_singular('gallery')) {
            $assets->script('gallery-lightbox.js');
            $assets->style('gallery-styles.css');
        }
    });
}
```

---

## File Organization

**Important:** All modules share single `dist/` folder at root level. Examples show source file organization in `assets/`.

### Simple Module (Standard Entry Points)

```
modules/Gallery/
├── GalleryModule.php
└── assets/
    ├── scripts/
    │   └── main.js          # Frontend functionality
    └── styles/
        └── main.css         # Frontend styles
```

### Frontend + Admin (Standard Entry Points)

```
modules/Gallery/
├── GalleryModule.php
└── assets/
    ├── scripts/
    │   ├── main.js          # Frontend scripts
    │   └── admin.js         # Admin dashboard scripts
    └── styles/
        ├── main.css         # Frontend styles
        └── admin.css        # Admin dashboard styles
```

### Theme Module (Multiple Standard Entry Points)

```
modules/Theme/
├── ThemeModule.php
└── assets/
    ├── scripts/
    │   ├── main.js          # Frontend scripts
    │   ├── editor-ui.js     # Block editor UI controls
    │   └── editor-preview.js # Block editor preview
    └── styles/
        ├── main.css         # Frontend styles
        ├── admin.css        # Admin styles
        └── admin-editor.css # Editor preview styles
```

### Conditional Features (Custom Entry Points)

```
modules/SiteHeader/
├── SiteHeaderModule.php
└── assets/
    └── scripts/
        ├── overlay.js       # Overlay header feature
        └── sticky.js        # Sticky header feature
```

### Complex Module (Mixed Approach)

```
modules/Gallery/
├── GalleryModule.php
└── assets/
    ├── scripts/
    │   ├── main.js                # Base gallery scripts
    │   ├── gallery-lightbox.js    # Conditional: lightbox feature
    │   ├── gallery-carousel.js    # Conditional: carousel variant
    │   └── admin.js               # Admin settings
    └── styles/
        ├── main.css               # Base styles
        ├── gallery-lightbox.css   # Lightbox-specific styles
        └── admin.css              # Admin styles
```

**Build output** (all modules combined):
```
dist/assets/
├── gallery-main-ABC123.js
├── gallery-lightbox-DEF456.js
├── gallery-carousel-GHI789.js
└── gallery-main-JKL012.css
```

---

## Examples

### Example 1: Frontend Module

**Pattern:** [Common Patterns #3](common-patterns.md#3-module-with-assets)

```php
class TestimonialsModule extends Module
{
    public function init(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->style('testimonials.css');
            $assets->script('testimonials.js', dependencies: ['jquery']);
        });
    }
}
```

### Example 2: Admin + Frontend

```php
class EventsModule extends Module
{
    public function init(): void
    {
        // Frontend
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->style('events.css');
            $assets->script('events.js');
        });

        // Admin
        $this->enqueueAdminAssets(function (ModuleAssets $assets) {
            $assets->style('admin.css');
            $assets->script('admin.js', dependencies: ['jquery-ui-datepicker']);
        });
    }
}
```

### Example 3: Conditional Loading

```php
class GalleryModule extends Module
{
    public function init(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->style('base.css');  // Always

            if ($this->hasGallery()) {
                $assets->script('gallery.js', dependencies: ['swiper']);
                $assets->style('gallery.css');
            }
        });
    }

    private function hasGallery(): bool
    {
        return is_singular('gallery') || has_block('sitchco/gallery');
    }
}
```

---

## CSS Authoring with PostCSS

This framework uses PostCSS in the build process, which allows you to write modern CSS with nested selectors for better organization and maintainability.

### Nested Selectors

Use the `&` symbol to reference the parent selector:

```css
/* Instead of this */
.button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
}
.button:hover {
    background-color: #005a87;
}
.button:focus {
    outline: 2px solid #0073aa;
}

/* Use this */
.button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;

    &:hover {
        background-color: #005a87;
    }

    &:focus {
        outline: 2px solid #0073aa;
    }
}
```

### Benefits of Nested CSS

- **Organization:** Related styles are grouped together
- **Readability:** Clear visual hierarchy of selectors
- **Maintainability:** Easier to modify component variants
- **DRY:** Reduces repetition of parent selectors

### Example: Component Variants

```css
.gallery {
    display: grid;
    gap: 1rem;

    &__item {
        position: relative;
        overflow: hidden;
        border-radius: 8px;

        &:hover {
            transform: scale(1.02);
        }
    }

    &__caption {
        padding: 0.75rem;
        background: rgba(0, 0, 0, 0.8);
        color: white;

        &--centered {
            text-align: center;
        }
    }

    // Layout variants
    &--grid {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    }

    &--masonry {
        column-count: 3;
        column-gap: 1rem;
    }
}
```

**See:** [Simple Module Example](../examples/simple-module/assets/main.css) for a working example of nested CSS.

---

## Related

- [Common Patterns - Module with Assets](common-patterns.md#3-module-with-assets)
- [Creating a Module](creating-a-module.md) - Module basics
- [Base Module API](../reference/base-module-api.md) - Complete API
