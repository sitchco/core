# Common Module Patterns

## TL;DR

Canonical examples for common module patterns. Reference these instead of duplicating code in your documentation.

## Pattern Index

| Pattern | Use Case | Tokens |
|---------|----------|--------|
| [Simple Module](#1-simple-module) | Basic hooks, no assets | ~200 |
| [Module with Dependencies](#2-module-with-dependencies) | Depends on other modules | ~300 |
| [Module with Assets](#3-module-with-assets) | CSS/JS frontend code | ~400 |
| [Custom Post Type Module](#4-custom-post-type-module) | CPT with Timber | ~600 |
| [Module with Blocks](#5-module-with-gutenberg-blocks) | Gutenberg blocks | ~300 |
| [Module with Feature Flags](#6-module-with-feature-flags) | Optional features | ~400 |
| [Module with DI](#7-module-with-dependency-injection) | Services/repositories | ~300 |
| [Admin Module](#8-admin-only-module) | WordPress admin only | ~300 |

---

## 1. Simple Module

**Use:** Basic WordPress hooks, no frontend assets

```php
<?php
namespace Sitchco\App\Modules\SimpleFeature;

use Sitchco\Framework\Module;

class SimpleFeatureModule extends Module
{
    public function init(): void
    {
        add_action('init', [$this, 'setup']);
        add_filter('the_content', [$this, 'modifyContent']);
    }

    public function setup(): void
    {
        // Your setup logic
    }

    public function modifyContent(string $content): string
    {
        return $content;
    }
}
```

**Real example:** `modules/Cleanup/CleanupModule.php`

---

## 2. Module with Dependencies

**Use:** When your module requires other modules to load first

```php
<?php
namespace Sitchco\App\Modules\Event;

use Sitchco\Framework\Module;
use Sitchco\Modules\PostModel\TimberModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;

class EventModule extends Module
{
    public const DEPENDENCIES = [
        TimberModule::class,           // For custom post classes
        ContentPartialModule::class,   // For content partials
    ];

    public function init(): void
    {
        // Safe to use Timber and ContentPartial here
        add_action('init', [$this, 'registerPostType']);
    }
}
```

**Real examples:**
- `modules/Production/ProductionModule.php:8` → TimberModule
- `modules/SiteHeader/SiteHeaderModule.php:12` → ContentPartialModule

---

## 3. Module with Assets

**Use:** Frontend CSS/JS that needs enqueueing

```php
<?php
namespace Sitchco\App\Modules\Gallery;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class GalleryModule extends Module
{
    public function init(): void
    {
        // Frontend
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->style('main.css');
            $assets->script('main.js', dependencies: ['jquery']);
        });

        // Admin
        $this->enqueueAdminAssets(function (ModuleAssets $assets) {
            $assets->script('admin.js');
        });

        // Block Editor
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->script('editor.js', dependencies: ['wp-blocks']);
        });
    }
}
```

**File structure:**
```
modules/Gallery/
├── GalleryModule.php
└── assets/
    ├── main.js
    ├── main.css
    ├── admin.js
    └── editor.js
```

**Real example:** `modules/Theme/ThemeModule.php:18-28`

---

## 4. Custom Post Type Module

**Use:** Custom post type with Timber post class

```php
<?php
namespace Sitchco\App\Modules\Event;

use Sitchco\Framework\Module;
use Sitchco\Modules\PostModel\TimberModule;

class EventModule extends Module
{
    public const DEPENDENCIES = [TimberModule::class];
    public const POST_CLASSES = [EventPost::class];

    public function init(): void
    {
        add_action('init', [$this, 'registerPostType']);
    }

    public function registerPostType(): void
    {
        // Post type registration handled through ACF Pro UI
        // No PHP registration needed - ACF Pro handles this
        // This method can be used for additional setup if needed
    }
}
```

**EventPost.php:**
```php
<?php
namespace Sitchco\App\Modules\Event;

use Timber\Post;

class EventPost extends Post
{
    public function startDate(): string
    {
        return get_field('start_date', $this->ID) ?: '';
    }

    public function isUpcoming(): bool
    {
        return strtotime($this->startDate()) > time();
    }
}
```

**Complete example:** `examples/custom-post-type-module/`
**Real example:** `modules/Production/ProductionModule.php`

---

## 5. Module with Gutenberg Blocks

**Use:** Blocks that auto-register from `blocks/` directory

```php
<?php
namespace Sitchco\App\Modules\Gallery;

use Sitchco\Framework\Module;

class GalleryModule extends Module
{
    public function init(): void
    {
        // Blocks auto-register from blocks/ directory
        // No manual registration needed!
    }
}
```

**File structure:**
```
modules/Gallery/
├── GalleryModule.php
└── blocks/
    ├── blocks-config.php     # Auto-generated
    └── gallery-grid/
        ├── block.json
        ├── block.php         # Context preparation
        ├── block.twig        # Twig template
        └── edit.js
```

**Real example:** `modules/Theme/blocks/featured-link/`

**See:** [Creating a Module](creating-a-module.md#blocks) for block structure details

---

## 6. Module with Feature Flags

**Use:** Optional methods enabled per-environment in config

```php
<?php
namespace Sitchco\App\Modules\Event;

use Sitchco\Framework\Module;

class EventModule extends Module
{
    public const FEATURES = [
        'customAdminColumn',
        'emailNotifications',
    ];

    public function init(): void
    {
        add_action('init', [$this, 'registerPostType']);
        // Feature methods called automatically if enabled
    }

    protected function customAdminColumn(): void
    {
        add_filter('manage_event_posts_columns', [$this, 'addColumn']);
    }

    protected function emailNotifications(): void
    {
        add_action('publish_event', [$this, 'sendEmail']);
    }
}
```

**Enable in config:**
```php
// sitchco.config.php
return [
    'modules' => [
        EventModule::class => [
            'features' => ['customAdminColumn'], // Enable only this feature
        ],
    ],
];
```

**See:** [Feature Flags Guide](feature-flags.md) for complete details

---

## 7. Module with Dependency Injection

**Use:** Inject services, repositories, or utilities

```php
<?php
namespace Sitchco\App\Modules\Event;

use Sitchco\Framework\Module;
use Sitchco\App\Services\EventRepository;
use Sitchco\App\Options\GlobalSettings;

class EventModule extends Module
{
    public function __construct(
        private EventRepository $repository,
        private GlobalSettings $settings
    ) {}

    public function init(): void
    {
        add_action('init', [$this, 'setup']);
    }

    public function setup(): void
    {
        $events = $this->repository->findUpcoming();
        $limit = $this->settings->get('events_per_page', 10);
    }
}
```

**EventRepository.php:**
```php
<?php
namespace Sitchco\App\Services;

class EventRepository
{
    public function __construct(private \wpdb $wpdb) {}

    public function findUpcoming(): array
    {
        // Query logic
        return [];
    }
}
```

**Real example:** `modules/SiteHeader/SiteHeaderModule.php:15-17`

---

## 8. Admin-Only Module

**Use:** WordPress admin customizations

```php
<?php
namespace Sitchco\App\Modules\AdminCustom;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class AdminCustomModule extends Module
{
    public function init(): void
    {
        // Admin hooks only
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);

        // Admin assets
        $this->enqueueAdminAssets(function (ModuleAssets $assets) {
            $assets->style('admin.css');
            $assets->script('admin.js');
        });
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            'Custom Settings',
            'Custom Settings',
            'manage_options',
            'custom-settings',
            [$this, 'renderPage']
        );
    }
}
```

**Real example:** `modules/Cleanup/CleanupModule.php` (admin cleanup)

---

## Combining Patterns

Most real modules combine multiple patterns:

```php
<?php
namespace Sitchco\App\Modules\Event;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\PostModel\TimberModule;

class EventModule extends Module
{
    // Pattern 2: Dependencies
    public const DEPENDENCIES = [TimberModule::class];

    // Pattern 4: Custom post type
    public const POST_CLASSES = [EventPost::class];

    // Pattern 6: Feature flags
    public const FEATURES = ['customAdminColumn'];

    // Pattern 7: Dependency injection
    public function __construct(
        private EventRepository $repository
    ) {}

    public function init(): void
    {
        // Pattern 4: Register CPT
        add_action('init', [$this, 'registerPostType']);

        // Pattern 3: Assets
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->style('event.css');
        });
    }

    // Pattern 6: Feature method
    protected function customAdminColumn(): void
    {
        add_filter('manage_event_posts_columns', [$this, 'addColumn']);
    }
}
```

**Complete example:** `examples/custom-post-type-module/`

---

## Quick Reference Table

| Need to... | Pattern | Config Required |
|------------|---------|-----------------|
| Add WordPress hooks | [#1](#1-simple-module) | Module class only |
| Use another module's functionality | [#2](#2-module-with-dependencies) | `DEPENDENCIES` constant |
| Load CSS/JS | [#3](#3-module-with-assets) | Assets folder + enqueue |
| Create custom post type | [#4](#4-custom-post-type-module) | `POST_CLASSES` + ACF Pro registration |
| Add Gutenberg blocks | [#5](#5-module-with-gutenberg-blocks) | `blocks/` directory |
| Enable optional features | [#6](#6-module-with-feature-flags) | `FEATURES` + config |
| Inject services/repos | [#7](#7-module-with-dependency-injection) | Constructor params |
| Customize admin | [#8](#8-admin-only-module) | Admin hooks + assets |

---

## Related

- [Creating a Module](creating-a-module.md) - Step-by-step guide
- [Adding Dependencies](adding-dependencies.md) - Dependency system details
- [Feature Flags Guide](feature-flags.md) - Feature flag system
- [Asset Management](asset-management.md) - CSS/JS enqueueing
- [Base Module API](../reference/base-module-api.md) - Complete API reference
