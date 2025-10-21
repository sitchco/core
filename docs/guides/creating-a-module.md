# Creating a Module

## TL;DR

Create a new module: (1) Create directory in `modules/`, (2) Create `{Name}Module.php` extending `Module`, (3) Implement `init()` method, (4) Register in `sitchco.config.php`.

## Quick Example

**See:** [Common Patterns - Simple Module](common-patterns.md#1-simple-module)

```php
<?php
namespace Sitchco\App\Modules\MyFeature;

use Sitchco\Framework\Module;

class MyFeatureModule extends Module
{
    public function init(): void
    {
        add_action('init', [$this, 'setup']);
    }
}
```

```php
// sitchco.config.php
return [
    'modules' => [
        \Sitchco\App\Modules\MyFeature\MyFeatureModule::class,
    ],
];
```

---

## Before You Start: Module Location

| Location | Use When | Build System |
|----------|----------|--------------|
| **`modules/`** | Has JS/CSS/blocks | Compiles `assets/` → `dist/` |
| **`src/`** | Pure PHP only | Ignored |

**This guide focuses on `modules/`** (frontend-integrated modules).

**See:** [Module Organization](module-organization.md) for src/ vs modules/ details

---

## Step-by-Step Guide

### Step 1: Create Module Directory

```bash
# Child theme
mkdir -p modules/MyFeature

# Parent theme (in sitchco-parent-theme)
mkdir -p modules/MyFeature

# Core (in sitchco-core mu-plugin)
mkdir -p modules/MyFeature
```

### Step 2: Create Module Class

**File:** `modules/MyFeature/MyFeatureModule.php`

```php
<?php
namespace Sitchco\App\Modules\MyFeature;  // Or Sitchco\Parent\, Sitchco\

use Sitchco\Framework\Module;

class MyFeatureModule extends Module
{
    public function init(): void
    {
        // Your hooks and setup here
        add_action('init', [$this, 'setup']);
    }

    public function setup(): void
    {
        // Implementation
    }
}
```

**Namespace conventions:**
- Child theme: `Sitchco\App\Modules\{ModuleName}`
- Parent theme: `Sitchco\Parent\Modules\{ModuleName}`
- Core: `Sitchco\Modules\{ModuleName}`

### Step 3: Register in Config

**File:** `sitchco.config.php` (root of theme/plugin)

```php
return [
    'modules' => [
        \Sitchco\App\Modules\MyFeature\MyFeatureModule::class,
    ],
];
```

**That's it!** Module loads automatically on `after_setup_theme` (priority 5).

---

## Common Module Types

For detailed patterns, see [Common Patterns Guide](common-patterns.md)

### Module with Dependencies

**Use:** Depends on other modules (e.g., Timber, ContentPartial)

```php
public const DEPENDENCIES = [
    TimberModule::class,
    ContentPartialModule::class,
];
```

**Pattern:** [Common Patterns #2](common-patterns.md#2-module-with-dependencies)

### Module with Assets

**Use:** Frontend CSS/JS

```php
public function init(): void
{
    $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
        $assets->style('main.css');
        $assets->script('main.js');
    });
}
```

**Pattern:** [Common Patterns #3](common-patterns.md#3-module-with-assets)
**See:** [Asset Management Guide](asset-management.md)

### Custom Post Type Module

**Use:** CPT with Timber post class

```php
public const DEPENDENCIES = [TimberModule::class];
public const POST_CLASSES = [MyPost::class];

public function init(): void
{
    add_action('init', [$this, 'registerPostType']);
}
```

**Pattern:** [Common Patterns #4](common-patterns.md#4-custom-post-type-module)
**Example:** `examples/custom-post-type-module/`

### Module with Blocks

**Use:** Gutenberg blocks

**Just create blocks/ directory** - auto-registers!

```
modules/MyFeature/
└── blocks/
    └── my-block/
        ├── block.json
        ├── block.php      # Context preparation
        ├── block.twig     # Twig template
        └── edit.js
```

**Pattern:** [Common Patterns #5](common-patterns.md#5-module-with-gutenberg-blocks)

### Module with Feature Flags

**Use:** Optional features enabled per-environment

```php
public const FEATURES = ['emailNotifications', 'customColumn'];

protected function emailNotifications(): void
{
    // Only runs if enabled in config
}
```

**Pattern:** [Common Patterns #6](common-patterns.md#6-module-with-feature-flags)
**See:** [Feature Flags Guide](feature-flags.md)

### Module with Dependency Injection

**Use:** Inject services/repositories

```php
public function __construct(
    private MyRepository $repository,
    private GlobalSettings $settings
) {}
```

**Pattern:** [Common Patterns #7](common-patterns.md#7-module-with-dependency-injection)
**See:** [Adding Dependencies](adding-dependencies.md#dependency-injection-container)

---

## Module File Structure

### Minimal Module
```
modules/MyFeature/
└── MyFeatureModule.php
```

### Module with Assets
```
modules/MyFeature/
├── MyFeatureModule.php
└── assets/              # Source files
    ├── main.js
    └── main.css
```

**Note:** Assets build to root-level `dist/` folder (not per-module).

### Module with Custom Post Type
```
modules/Event/
├── EventModule.php
├── EventPost.php        # Timber post class
└── EventRepository.php  # Optional
```

### Module with Blocks
```
modules/MyFeature/
├── MyFeatureModule.php
└── blocks/
    ├── blocks-config.php     # Auto-generated
    └── my-block/
        ├── block.json
        ├── block.php         # Context preparation
        ├── block.twig        # Twig template
        └── edit.js
```

### Full-Featured Module
```
modules/Event/
├── EventModule.php       # Main module class
├── EventPost.php         # Timber post class
├── EventRepository.php   # Data access
├── assets/               # Frontend assets
│   ├── event.js
│   └── event.css
├── blocks/               # Gutenberg blocks
│   └── event-list/
└── acf-json/            # ACF field groups
```

---

## Module Lifecycle

**Bootstrap process:**
1. WordPress fires `after_setup_theme` (priority 5)
2. Framework loads configs: core → parent → child
3. ModuleRegistry activates modules in 3 passes:

| Pass | What Happens | Example |
|------|--------------|---------|
| **1. Register** | Instantiate modules via DI | `new MyModule($repo, $settings)` |
| **2. Extend** | Run extensions (Timber, ACF, Blocks) | Register POST_CLASSES with Timber |
| **3. Initialize** | Call `init()` on each module | Your WordPress hooks run |

**Dependencies resolved automatically** - modules load in correct order.

**See:** [Architecture Overview](../architecture/overview.md) for complete bootstrap details

---

## Naming Conventions

### Class Names
- **Format:** `{Feature}Module`
- **Examples:** `EventModule`, `GalleryModule`, `SiteHeaderModule`

### File Names
- **Format:** `{Feature}Module.php` (matches class name)
- **Examples:** `EventModule.php`, `GalleryModule.php`

### Directory Names
- **Format:** `{Feature}` (no "Module" suffix)
- **Examples:** `modules/Event/`, `modules/Gallery/`

### Namespaces
- **Child:** `Sitchco\App\Modules\{Feature}`
- **Parent:** `Sitchco\Parent\Modules\{Feature}`
- **Core:** `Sitchco\Modules\{Feature}`

---

## Next Steps

1. **Review patterns:** [Common Patterns Guide](common-patterns.md)
2. **Add dependencies:** [Adding Dependencies](adding-dependencies.md)
3. **Enable features:** [Feature Flags Guide](feature-flags.md)
4. **Load assets:** [Asset Management](asset-management.md)
5. **See examples:** `examples/simple-module/` and `examples/custom-post-type-module/`

---

## Related

- [Common Patterns](common-patterns.md) - Canonical module examples
- [Module Organization](module-organization.md) - src/ vs modules/ decisions
- [Adding Dependencies](adding-dependencies.md) - Dependency system
- [Asset Management](asset-management.md) - CSS/JS enqueueing
- [Base Module API](../reference/base-module-api.md) - Complete API reference
