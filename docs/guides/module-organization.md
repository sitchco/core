# Module Organization: src/ vs modules/

## TL;DR

**Two module locations with different purposes:**

- **`src/`** - Pure PHP modules (no frontend assets)
- **`modules/`** - Modules with frontend assets (JS, CSS, images, Gutenberg blocks)

**Why it matters:** The build system only looks in `modules/` for frontend assets to compile.

## Quick Decision Guide

```
Does your module need frontend assets?
├─ NO  → Put in src/
│        (Services, utilities, API integrations)
│
└─ YES → Put in modules/
         (Custom post types with templates, blocks, interactive UI)
```

## The Pattern Explained

### `src/` Directory - Backend PHP Only

**Purpose:** Pure PHP code with no frontend assets.

**Build system:** Ignores this directory (no Vite compilation).

**Common contents:**
- Services and utilities
- Repositories and data access layers
- API integrations
- Background processing
- Admin-only functionality (no custom JS/CSS)
- Framework infrastructure

**Example structure:**
```
src/
├── Services/
│   ├── EmailService.php
│   ├── CacheService.php
│   └── ApiClient.php
├── Repository/
│   ├── UserRepository.php
│   └── PostRepository.php
├── Utils/
│   ├── Hooks.php
│   ├── Image.php
│   └── Template.php
└── Options/
    └── GlobalSettings.php
```

### `modules/` Directory - Frontend-Integrated Modules

**Purpose:** Modules with frontend assets (JavaScript, CSS, images, Gutenberg blocks).

**Build system:** Scans this directory for assets to compile (Vite).

**Common contents:**
- Custom post types with frontend templates
- Gutenberg blocks
- Frontend interactive components
- Theme styling modules
- Anything requiring JS/CSS/images

**Example structure:**
```
modules/
├── Event/
│   ├── EventModule.php
│   ├── EventPost.php
│   └── assets/          ← Build system looks here
│       ├── main.js
│       └── main.css
├── Gallery/
│   ├── GalleryModule.php
│   ├── blocks/          ← Gutenberg blocks
│   │   └── gallery-grid/
│   └── assets/
└── Theme/
    ├── ThemeModule.php
    └── assets/

# All modules bundled to single dist/ at root
dist/                    ← Build output (sibling to sitchco.config.php)
└── assets/
    ├── event-ABC123.js
    ├── gallery-DEF456.js
    └── theme-GHI789.css
```

## When to Use `src/`

### ✅ Use `src/` for:

**1. Services and Utilities**
```php
// src/Services/EmailService.php
namespace Sitchco\App\Services;

class EmailService
{
    public function sendWelcomeEmail(User $user): void
    {
        // Pure PHP logic, no frontend assets
    }
}
```

**2. Repositories**
```php
// src/Repository/ProductRepository.php
namespace Sitchco\App\Repository;

class ProductRepository
{
    public function findFeatured(): array
    {
        // Database queries, no frontend code
    }
}
```

**3. API Integrations**
```php
// src/Integration/StripeClient.php
namespace Sitchco\App\Integration;

class StripeClient
{
    public function createPaymentIntent(float $amount): PaymentIntent
    {
        // Third-party API calls
    }
}
```

**4. Background Processing**
```php
// src/Jobs/ImportProducts.php
namespace Sitchco\App\Jobs;

class ImportProducts extends BackgroundProcess
{
    protected function task($item)
    {
        // Async job processing
    }
}
```

**5. Options/Settings Classes**
```php
// src/Options/GlobalSettings.php
namespace Sitchco\App\Options;

class GlobalSettings
{
    public function get(string $key): mixed
    {
        return get_option("app_{$key}");
    }
}
```

## When to Use `modules/`

### ✅ Use `modules/` for:

**1. Custom Post Types with Frontend Display**
```php
// modules/Event/EventModule.php
namespace Sitchco\App\Modules\Event;

class EventModule extends Module
{
    public function init(): void
    {
        // Has frontend templates and styles
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->style('event.css');
            $assets->script('event.js');
        });
    }
}
```

**2. Gutenberg Blocks**
```php
// modules/Testimonials/TestimonialsModule.php
namespace Sitchco\App\Modules\Testimonials;

class TestimonialsModule extends Module
{
    public function init(): void
    {
        register_block_type($this->blocksPath('testimonial-slider'));

        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->script('editor.js');  // Block editor script
        });
    }
}
```

**3. Interactive Frontend Components**
```php
// modules/Search/SearchModule.php
namespace Sitchco\App\Modules\Search;

class SearchModule extends Module
{
    public function init(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->script('search.js');  // AJAX search
            $assets->style('search.css');
        });
    }
}
```

**4. Theme Styling Modules**
```php
// modules/Theme/ThemeModule.php
namespace Sitchco\App\Modules\Theme;

class ThemeModule extends Module
{
    public function init(): void
    {
        $this->enqueueGlobalAssets(function (ModuleAssets $assets) {
            $assets->style('theme.css');   // Global styles
            $assets->script('theme.js');
        });
    }
}
```

## Hybrid Approach

Some modules need both backend logic and frontend assets. Use `modules/` as the primary location and include PHP classes there:

```
modules/Event/
├── EventModule.php           # Main module (extends Module)
├── EventPost.php             # Timber post class
├── EventRepository.php       # Data access (pure PHP)
├── EventService.php          # Business logic (pure PHP)
├── assets/                   # Frontend assets
│   ├── event.js
│   └── event.css
└── blocks/                   # Gutenberg blocks
    └── event-list/
```

**Why not split?**
- Keeps related code together
- Repository/Service classes are part of the Event feature
- Build system can still find assets in `modules/Event/assets/`

## Namespace Conventions

### `src/` Namespaces

```php
// Core
namespace Sitchco\Services;
namespace Sitchco\Repository;
namespace Sitchco\Utils;

// Parent theme
namespace Sitchco\Parent\Services;
namespace Sitchco\Parent\Repository;

// Child theme
namespace Sitchco\App\Services;
namespace Sitchco\App\Repository;
namespace Sitchco\App\Options;
```

### `modules/` Namespaces

```php
// Core
namespace Sitchco\Modules\{ModuleName};

// Parent theme
namespace Sitchco\Parent\Modules\{ModuleName};

// Child theme
namespace Sitchco\App\Modules\{ModuleName};
```

## Build System Integration

### Vite Configuration

The build system (Vite) is configured to scan `modules/` for entry points:

```javascript
// vite.config.js
export default {
  build: {
    outDir: 'dist',  // Single output directory at root level
    rollupOptions: {
      input: {
        // Automatically discovers assets in modules/*/assets/
        // Bundles all modules together with hashed filenames
      }
    }
  }
}
```

### Asset Discovery

**Build process:**
1. Scans `modules/*/assets/` for JS/CSS files
2. Compiles and bundles assets from all modules
3. Outputs to single root-level `dist/` folder (sibling to `sitchco.config.php`)
4. Creates hashed filenames for cache busting
5. `ModuleAssets` class resolves source files to bundled assets in production

**`src/` is ignored** - No asset compilation happens there.

## Examples

### Example 1: Pure Backend Module

**Scenario:** Email notification service

**Location:** `src/Services/EmailNotificationService.php`

**Reason:** No frontend assets, pure PHP logic.

```php
namespace Sitchco\App\Services;

class EmailNotificationService
{
    public function notifyAdmin(string $subject, string $message): void
    {
        wp_mail(get_option('admin_email'), $subject, $message);
    }
}
```

### Example 2: Frontend Module

**Scenario:** Event listing with interactive calendar

**Location:** `modules/Event/`

**Reason:** Has JavaScript for calendar, CSS for styling, Gutenberg blocks.

```php
namespace Sitchco\App\Modules\Event;

class EventModule extends Module
{
    public function init(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->script('calendar.js');  // Interactive calendar
            $assets->style('event.css');     // Event styling
        });
    }
}
```

### Example 3: Hybrid Module

**Scenario:** Product catalog with API integration

**Location:** `modules/Product/`

**Reason:** Has frontend display (JS/CSS) but also backend API logic.

```
modules/Product/
├── ProductModule.php          # Frontend assets + hooks
├── ProductPost.php            # Timber post class
├── ProductRepository.php      # Database queries (pure PHP)
├── ProductApiService.php      # API integration (pure PHP)
├── assets/
│   ├── product.js             # Product page interactions
│   └── product.css
└── blocks/
    └── product-grid/
```

**Why not split?**
- All Product-related code in one place
- Repository/API service are part of Product feature
- Build system still finds `assets/` for compilation

## Autoloading

Both directories use PSR-4 autoloading via Composer:

```json
{
  "autoload": {
    "psr-4": {
      "Sitchco\\App\\": "src/",
      "Sitchco\\App\\Modules\\": "modules/"
    }
  }
}
```

**After adding new classes:**
```bash
composer dump-autoload
```

## Best Practices

### ✅ Do

- **Keep frontend modules in `modules/`** - If it has JS/CSS, it goes here
- **Keep pure PHP in `src/`** - Services, utilities, repositories without assets
- **Use `modules/` for blocks** - Gutenberg blocks always need assets
- **Colocate related code** - Keep module's repository/service classes with module
- **Follow namespace conventions** - Match directory structure

### ❌ Don't

- **Don't put assets in `src/`** - Build system won't find them
- **Don't split related code** - Keep Product module code in `modules/Product/`
- **Don't use `modules/` for pure utilities** - Use `src/Utils/` instead
- **Don't forget composer dump-autoload** - After adding classes

## Migration Guide

### Moving from `modules/` to `src/`

If you have a pure PHP module in `modules/` with no assets:

1. **Move the files:**
   ```bash
   mv modules/EmailService src/Services/
   ```

2. **Update namespace:**
   ```php
   // From
   namespace Sitchco\App\Modules\EmailService;

   // To
   namespace Sitchco\App\Services;
   ```

3. **Update references:**
   ```php
   // Update imports in other files
   use Sitchco\App\Services\EmailService;
   ```

4. **Run autoload:**
   ```bash
   composer dump-autoload
   ```

### Moving from `src/` to `modules/`

If you need to add frontend assets to a `src/` class:

1. **Create module structure:**
   ```bash
   mkdir -p modules/MyFeature/assets
   ```

2. **Move PHP files:**
   ```bash
   mv src/Services/MyFeature* modules/MyFeature/
   ```

3. **Update namespace:**
   ```php
   // From
   namespace Sitchco\App\Services;

   // To
   namespace Sitchco\App\Modules\MyFeature;
   ```

4. **Convert to Module:**
   ```php
   class MyFeatureModule extends Module
   {
       public function init(): void
       {
           $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
               $assets->style('myfeature.css');
               $assets->script('myfeature.js');
           });
       }
   }
   ```

5. **Run autoload:**
   ```bash
   composer dump-autoload
   ```

## Summary

| Aspect | `src/` | `modules/` |
|--------|--------|------------|
| **Purpose** | Pure PHP backend | Frontend-integrated modules |
| **Build system** | Ignored | Scans for assets |
| **Assets** | None | JS, CSS, images, blocks |
| **Examples** | Services, repositories, utilities | Post types, blocks, themes |
| **Namespace** | `Sitchco\App\Services` | `Sitchco\App\Modules\Feature` |
| **Extends Module** | Usually no | Yes |

**Golden Rule:** If it has frontend assets that need compilation, use `modules/`. Otherwise, use `src/`.

## Related

- [Creating a Module](creating-a-module.md)
- [Asset Management](asset-management.md)
- [Architecture Overview](../architecture/overview.md)
