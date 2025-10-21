# Adding Module Dependencies

## TL;DR

Declare module dependencies using `DEPENDENCIES` constant. ModuleRegistry automatically resolves dependencies and loads in correct order.

## Quick Example

**See:** [Common Patterns - Module with Dependencies](common-patterns.md#2-module-with-dependencies)

```php
public const DEPENDENCIES = [
    TimberModule::class,
    ContentPartialModule::class,
];
```

---

## How It Works

### Dependency Resolution Process

| Step | Action |
|------|--------|
| 1 | Read all module configs from `sitchco.config.php` |
| 2 | Build dependency graph from `DEPENDENCIES` constants |
| 3 | Resolve recursively (dependencies of dependencies) |
| 4 | Detect circular dependencies (throws error if found) |
| 5 | Instantiate modules in correct order |

**Example:**
```
EventModule depends on TimberModule

Registration:
  1. Resolve TimberModule (no dependencies)
  2. Instantiate TimberModule
  3. Resolve EventModule (needs TimberModule ✓)
  4. Instantiate EventModule
```

---

## Common Module Dependencies

### Real Dependency Examples

| Dependency | Use When | Real Example (Codebase) |
|------------|----------|-------------------------|
| `TimberModule` | Custom post classes | `modules/Production/ProductionModule.php:8` |
| `ContentPartialModule` | Content partials | `modules/SiteHeader/SiteHeaderModule.php:12` |

**Note:** Blocks don't need `BlockConfigModule` - they auto-register from `blocks/` directory.

### TimberModule (For Custom Post Types)

**Use when:** Creating custom post types with Timber post classes

```php
use Sitchco\Modules\PostModel\TimberModule;

class ProductModule extends Module
{
    public const DEPENDENCIES = [TimberModule::class];
    public const POST_CLASSES = [ProductPost::class];
}
```

**Real examples:**
- `modules/Production/ProductionModule.php` (child theme)
- `modules/ContentPartial/ContentPartialModule.php` (parent theme)

**Why needed:** `TimberPostModuleExtension` registers your `POST_CLASSES` with Timber's classmap during extension pass.

### ContentPartialModule (Parent Theme)

**Use when:** Building modules that use content partials

```php
use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;

class SiteHeaderModule extends Module
{
    public const DEPENDENCIES = [ContentPartialModule::class];

    public function __construct(
        private ContentPartialService $contentService
    ) {}
}
```

**Real examples:**
- `modules/SiteHeader/SiteHeaderModule.php`
- `modules/SiteFooter/SiteFooterModule.php`

**Why needed:** Ensures `ContentPartialPost` post type and service are registered before your module.

---

## Dependency Chains

Dependencies can have their own dependencies - registry resolves recursively:

```php
// Core (no dependencies)
class TimberModule extends Module {}

// Parent (depends on core)
class ContentPartialModule extends Module
{
    public const DEPENDENCIES = [TimberModule::class];
}

// Child (depends on parent → core)
class EventModule extends Module
{
    public const DEPENDENCIES = [ContentPartialModule::class];
}
```

**Load order:**
1. TimberModule
2. ContentPartialModule (needs Timber)
3. EventModule (needs ContentPartial → Timber)

**You only declare direct dependencies** - transitive ones resolve automatically.

---

## Circular Dependency Detection

**This will throw an error:**
```php
class ModuleA extends Module {
    const DEPENDENCIES = [ModuleB::class];
}

class ModuleB extends Module {
    const DEPENDENCIES = [ModuleA::class];
}
```

**Error:**
```
Circular dependency detected: ModuleA → ModuleB → ModuleA
```

### How to Fix Circular Dependencies

| Solution | When to Use |
|----------|-------------|
| **Remove dependency** | If module doesn't actually need other loaded first |
| **Create shared dependency** | Extract common functionality to ModuleC |
| **Use events/hooks** | Fire actions instead of direct dependency |

**Example - Use hooks instead:**
```php
// Instead of depending on each other
class ModuleA extends Module
{
    public function init(): void
    {
        do_action('module_a_ready');  // Fire event
    }
}

class ModuleB extends Module
{
    public function init(): void
    {
        add_action('module_a_ready', [$this, 'handleReady']);  // Listen
    }
}
```

---

## What About Blocks?

**You do NOT need to depend on `BlockConfigModule` for Gutenberg blocks.**

Blocks auto-register via `BlockRegistrationModuleExtension`:

### How Block Registration Works

1. Create `modules/MyModule/blocks/` directory
2. Add block subdirectories with `block.json`
3. Create `blocks-config.php` (auto-generated if missing)
4. Blocks register automatically - no dependencies needed!

**Example from roundabout:**
```
modules/Theme/blocks/
├── blocks-config.php          # Auto-generated mapping
└── featured-link/
    ├── block.json
    ├── block.php              # Context preparation
    ├── block.twig             # Twig template
    └── edit.js
```

**See:** [Common Patterns - Module with Blocks](common-patterns.md#5-module-with-gutenberg-blocks)

---

## Dependency Injection Container

For **service/repository dependencies**, use constructor injection (not `DEPENDENCIES`):

```php
class EventModule extends Module
{
    // ✅ Constructor injection for services
    public function __construct(
        private EventRepository $repository,
        private GlobalSettings $settings
    ) {}

    // ✅ DEPENDENCIES for modules
    public const DEPENDENCIES = [TimberModule::class];
}
```

### When to Use Which

| Use | For |
|-----|-----|
| `DEPENDENCIES` constant | When you need another **module** to load first |
| Constructor injection | When you need a **service/repository/utility** class |

### Container Configuration

Add custom definitions in `sitchco.config.php`:

```php
return [
    'container' => [
        // Bind interface to implementation
        EventRepositoryInterface::class => \DI\autowire(EventRepository::class),

        // Provide factory
        ApiClient::class => function () {
            return new ApiClient(get_option('api_key'));
        },

        // Singleton
        CacheService::class => \DI\create(CacheService::class)->lazy(),
    ],
];
```

### Common Injections

```php
// WordPress globals
public function __construct(private \wpdb $wpdb) {}

// Services
public function __construct(
    private EventRepository $repo,
    private GlobalSettings $settings
) {}
```

**See:** [Common Patterns - Module with DI](common-patterns.md#7-module-with-dependency-injection)

---

## Debugging Dependencies

### Check Module Load Order

```php
global $SitchcoContainer;
$registry = $SitchcoContainer->get(\Sitchco\Framework\ModuleRegistry::class);
$modules = $registry->getActiveModules();

foreach ($modules as $name => $instance) {
    echo $name . "\n";  // Modules in load order
}
```

### Verify Dependency Resolution

```php
public function init(): void
{
    error_log('EventModule initialized - dependencies loaded');
}
```

Check `wp-content/debug.log` to see initialization order.

### Enable WP_DEBUG

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

---

## Examples

### Example 1: Event Module with Timber

**Complete pattern:** [Common Patterns #4](common-patterns.md#4-custom-post-type-module)
**Working example:** `examples/custom-post-type-module/`

```php
class EventModule extends Module
{
    public const DEPENDENCIES = [TimberModule::class];
    public const POST_CLASSES = [EventPost::class];

    public function __construct(
        private EventRepository $repository
    ) {}
}
```

### Example 2: Gallery Depending on Parent

```php
use Sitchco\Modules\PostModel\TimberModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;

class GalleryModule extends Module
{
    public const DEPENDENCIES = [
        TimberModule::class,
        ContentPartialModule::class,  // Uses content partials
    ];

    public const POST_CLASSES = [GalleryPost::class];
}
```

---

## Related

- [Common Patterns](common-patterns.md) - Canonical examples with dependencies
- [Creating a Module](creating-a-module.md) - Module basics
- [Feature Flags Guide](feature-flags.md) - Optional features
- [Base Module API Reference](../reference/base-module-api.md) - Complete API
- [Core Modules List](../reference/core-modules.md) - Available modules
