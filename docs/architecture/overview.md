# Architecture Overview

## Introduction

The Sitchco Framework is a modular WordPress architecture that enables reusable, dependency-managed functionality across mu-plugins, parent themes, and child themes.

**Core Principle:** Build once, reuse everywhere. Modules created in core can be extended by parent themes, which can be further customized by child themes.

## Three-Layer Architecture

```
┌────────────────────────────────────────────┐
│  Layer 3: Child Theme                      │
│  • Project-specific modules                │
│  • Extends parent theme                    │
│  • Client/site customizations              │
└────────────────────────────────────────────┘
                    ↑
                 extends
                    ↑
┌────────────────────────────────────────────┐
│  Layer 2: Parent Theme                     │
│  • Reusable theme components               │
│  • Builds on core framework                │
│  • Shared across multiple clients          │
└────────────────────────────────────────────┘
                    ↑
                 extends
                    ↑
┌────────────────────────────────────────────┐
│  Layer 1: Core (sitchco-core mu-plugin)    │
│  • Framework foundation                    │
│  • Infrastructure modules                  │
│  • WordPress enhancements                  │
└────────────────────────────────────────────┘
```

### Layer 1: Core (sitchco-core)

**Location:** `wp-content/mu-plugins/sitchco-core/`
**Namespace:** `Sitchco\Framework`, `Sitchco\Modules`

**Purpose:** Provides the framework foundation and infrastructure modules.

**Contents:**
- **Framework classes:** `Module`, `ModuleRegistry`, `ConfigRegistry`, `Bootstrap`
- **22+ core modules:** PostModel, BlockConfig, ACF integrations, performance, utilities
- **Services:** DI container, REST API, hooks, flash messages, background processing
- **Utilities:** File path handling, image processing, template helpers

**When to add here:**
- Framework features needed by all projects
- WordPress enhancements that apply universally
- Infrastructure and tooling

### Layer 2: Parent Theme

**Location:** `wp-content/themes/sitchco-parent-theme/`
**Namespace:** `Sitchco\Parent\Modules`

**Purpose:** Reusable theme components shared across client sites.

**Contents:**
- **5 modules:** SiteHeader, SiteFooter, ContentPartial, ContentPartialBlock, Theme
- **Theme utilities:** Base styling, layout patterns, block patterns
- **Shared components:** Navigation, footer, reusable content blocks

**When to add here:**
- UI components used by multiple clients
- Theme features that need flexibility
- Shared design patterns

### Layer 3: Child Theme

**Location:** `wp-content/themes/{child-theme-name}/`
**Namespace:** `Sitchco\App\Modules`

**Purpose:** Project-specific functionality for individual client sites.

**Contents:**
- **Custom modules:** Client-specific features, post types, integrations
- **Site styling:** Brand colors, fonts, custom CSS
- **Data imports:** Custom data handling, third-party integrations

**When to add here:**
- Client-specific features
- Site-unique functionality
- Custom post types and taxonomies specific to one project

## Bootstrap Process

### 1. WordPress Initialization

```
WordPress loads
    ↓
plugins_loaded
    ↓
sitchco-core.php (mu-plugin entry point)
    ↓
new Bootstrap()
```

### 2. Bootstrap Class

**File:** `src/Framework/Bootstrap.php`

**Hooks into:** `after_setup_theme` (priority 5)

```php
after_setup_theme (priority 5)
    ↓
Bootstrap::__construct()
    1. Create DI\ContainerBuilder
    2. Load container config via ConfigRegistry
    3. Build DI container
    4. Store in $GLOBALS['SitchcoContainer']
    5. Load modules config via ConfigRegistry
    6. Pass to ModuleRegistry::activateModules()
```

### 3. Config Loading

**File:** `src/Framework/ConfigRegistry.php`

**Loads configs in order:**
```
1. Core config
   → /wp-content/mu-plugins/sitchco-core/sitchco.config.php

2. Filtered paths (optional)
   → apply_filters('sitchco_config_paths', [])

3. Parent theme config
   → /wp-content/themes/sitchco-parent-theme/sitchco.config.php

4. Child theme config
   → /wp-content/themes/{child-theme}/sitchco.config.php
```

**Merge strategy:** Recursive merge. Child overrides parent, parent overrides core.

**Example:**
```php
// Core config
['modules' => [Cleanup::class => ['disableEmojis' => true]]]

// Parent config
['modules' => [Cleanup::class => ['disableGutenbergStyles' => false]]]

// Result
['modules' => [Cleanup::class => [
    'disableEmojis' => true,
    'disableGutenbergStyles' => false,
]]]
```

### 4. Module Activation (3-Pass System)

**File:** `src/Framework/ModuleRegistry.php`

```php
ModuleRegistry::activateModules(array $moduleConfigs)
{
    1. Registration Pass
       → Resolve dependencies recursively
       → Instantiate modules via DI container
       → Detect circular dependencies

    2. Extension Pass
       → TimberPostModuleExtension (POST_CLASSES)
       → AcfPathsModuleExtension (ACF paths)
       → BlockRegistrationModuleExtension (blocks)

    3. Initialization Pass
       → Call module->init()
       → Execute enabled feature methods
}
```

**Pass 1: Registration**
- Reads `DEPENDENCIES` constant from each module
- Builds dependency graph
- Resolves in topological order (dependencies first)
- Instantiates modules via `$container->get(ModuleClass::class)`
- Detects circular dependencies

**Pass 2: Extension**
- Applies module extensions:
  - **TimberPostModuleExtension:** Registers `POST_CLASSES` with Timber
  - **AcfPathsModuleExtension:** Configures ACF JSON paths
  - **BlockRegistrationModuleExtension:** Registers Gutenberg blocks

**Pass 3: Initialization**
- Calls `module->init()` on each module (always)
- Reads config for feature flags
- Calls enabled feature methods (protected methods matching `FEATURES` constant)

### 5. WordPress Continues

```
after_setup_theme (priority 10+)
    ↓
init (priority 10)
    ↓
Modules' registered hooks execute
```

## Dependency Injection

**Library:** PHP-DI (https://php-di.org/)

**Container:** `$GLOBALS['SitchcoContainer']` (DI\Container instance)

**How it works:**
```php
// Module with dependencies
class EventModule extends Module
{
    public function __construct(
        private EventRepository $repo,
        private GlobalSettings $settings
    ) {}
}

// Container automatically resolves and injects
$container->get(EventModule::class);
// → Instantiates EventRepository
// → Instantiates GlobalSettings
// → Injects both into EventModule constructor
```

**Configuration:** `sitchco.config.php['container']`

```php
return [
    'container' => [
        // Bind interface to implementation
        EventRepositoryInterface::class => \DI\autowire(EventRepository::class),

        // Factory
        ApiClient::class => function () {
            return new ApiClient(get_option('api_key'));
        },

        // Singleton
        CacheService::class => \DI\create(CacheService::class)->lazy(),
    ],
];
```

## Module System Design Patterns

### 1. Modular Architecture

Each feature is encapsulated in a module class.

**Benefits:**
- Clear separation of concerns
- Easy to test individual modules
- Reusable across projects
- Optional features via config

### 2. Dependency Declaration

Modules explicitly declare dependencies via `DEPENDENCIES` constant.

**Benefits:**
- Clear dependency graph
- Automatic resolution
- Prevents missing dependencies
- Detects circular dependencies

### 3. Feature Flags

Optional functionality controlled via config, not code.

**Benefits:**
- Enable/disable features per environment
- No code changes needed
- Client-specific customization
- Gradual feature rollout

### 4. Hierarchical Configuration

Config files cascade from core → parent → child.

**Benefits:**
- DRY (Don't Repeat Yourself)
- Override only what changes
- Clear config precedence
- Environment-specific configs

### 5. Constructor Injection

Services injected via constructor, not global state.

**Benefits:**
- Testable (mock dependencies)
- Explicit dependencies
- No hidden coupling
- Type safety

### 6. Hook-Based Integration

Modules use WordPress hooks for loose coupling.

**Benefits:**
- Extensible by other modules
- Standard WordPress patterns
- Compatible with plugins
- Familiar to WordPress developers

## File Structure

### Core (sitchco-core)

```
wp-content/mu-plugins/sitchco-core/
├── sitchco-core.php           # Entry point
├── sitchco.config.php         # Core module config
├── src/
│   ├── Framework/
│   │   ├── Bootstrap.php
│   │   ├── Module.php
│   │   ├── ModuleRegistry.php
│   │   ├── ConfigRegistry.php
│   │   └── ModuleAssets.php
│   ├── ModuleExtension/
│   │   ├── TimberPostModuleExtension.php
│   │   ├── AcfPathsModuleExtension.php
│   │   └── BlockRegistrationModuleExtension.php
│   ├── Utils/
│   ├── Support/
│   └── ...
└── modules/
    ├── Model/
    │   ├── PostModel.php
    │   ├── TermModel.php
    │   └── ImageModel.php
    ├── Wordpress/
    │   ├── Cleanup.php
    │   ├── BlockConfig.php
    │   └── ...
    ├── AdvancedCustomFields/
    │   ├── AcfOptions.php
    │   └── ...
    └── ...
```

### Parent Theme

```
wp-content/themes/sitchco-parent-theme/
├── functions.php
├── sitchco.config.php         # Parent module config
├── composer.json
└── modules/
    ├── SiteHeader/
    │   ├── SiteHeaderModule.php
    │   ├── assets/
    │   └── templates/
    ├── SiteFooter/
    │   └── SiteFooterModule.php
    ├── ContentPartial/
    │   ├── ContentPartialModule.php
    │   ├── ContentPartialPost.php
    │   ├── ContentPartialRepository.php
    │   └── ContentPartialService.php
    └── Theme/
        └── ThemeModule.php
```

### Child Theme

```
wp-content/themes/roundabout/
├── functions.php
├── sitchco.config.php         # Child module config
├── composer.json
├── src/
│   └── Options/
│       └── GlobalSettings.php
└── modules/
    ├── Production/
    │   ├── ProductionModule.php
    │   ├── ProductionPost.php
    │   ├── ProductionRepository.php
    │   ├── assets/
    │   └── acf-json/
    ├── Theme/
    │   ├── ThemeModule.php
    │   └── assets/
    └── WpAllImport/
        └── WpAllImportModule.php
```

### Directory Structure: src/ vs modules/

**Critical distinction:** Code organization is determined by whether it has frontend assets.

#### `src/` Directory
**Purpose:** Pure PHP code with no frontend assets (no JS/CSS/images).

**Build system:** Ignored by Vite - no asset compilation.

**Contains:**
- Framework infrastructure (`Framework/`, `ModuleExtension/`)
- Services and utilities (`Services/`, `Utils/`, `Support/`)
- Repositories and data access (`Repository/`)
- API clients and integrations
- Background processing classes
- Options and settings classes

**Example:**
```
src/
├── Framework/          # Core framework classes
├── Services/
│   ├── EmailService.php
│   └── CacheService.php
├── Repository/
│   └── UserRepository.php
└── Options/
    └── GlobalSettings.php
```

**When to use:** Backend-only functionality, utilities, services.

#### `modules/` Directory
**Purpose:** Modules with frontend assets (JavaScript, CSS, images, Gutenberg blocks).

**Build system:** Scanned by Vite for asset compilation. Assets in `modules/*/assets/` are bundled to single root-level `dist/` folder.

**Contains:**
- Custom post types with frontend templates
- Gutenberg blocks
- Frontend interactive components
- Theme styling modules
- Any module requiring JS/CSS/images

**Example:**
```
modules/
├── Event/
│   ├── EventModule.php
│   ├── EventPost.php
│   ├── EventRepository.php    # Kept with module for cohesion
│   └── assets/                 # ← Build system looks here
│       ├── event.js
│       └── event.css
└── Gallery/
    ├── GalleryModule.php
    ├── blocks/
    │   └── gallery-grid/
    └── assets/

# All module assets bundled together
dist/                            # ← Build output (sibling to sitchco.config.php)
└── assets/
    ├── event-ABC123.js          # Hashed filenames
    ├── gallery-DEF456.js
    └── event-GHI789.css
```

**When to use:** Anything with frontend assets, Gutenberg blocks, frontend UI.

#### Decision Guide

```
Does your code need frontend assets (JS/CSS/blocks)?
├─ YES → modules/
│         (Build system will compile assets)
│
└─ NO  → src/
          (Pure PHP backend code)
```

**Hybrid modules:** If a module has both backend logic (services/repositories) and frontend assets, keep everything in `modules/ModuleName/` for cohesion. The build system will still find the `assets/` subdirectory.

**See:** `guides/module-organization.md` for complete guide.

## Module Lifecycle

### Complete Flow

```
1. WordPress: plugins_loaded
     ↓
2. sitchco-core.php loads
     ↓
3. new Bootstrap()
     ↓
4. after_setup_theme (priority 5) ◄── MODULE->INIT() IS CALLED HERE
     ↓
5. ConfigRegistry::load('container')
     ↓ Loads from: core → filtered → parent → child
6. Build DI container
     ↓
7. ConfigRegistry::load('modules')
     ↓ Loads from: core → filtered → parent → child
8. ModuleRegistry::activateModules()
     │
     ├─ Registration Pass
     │  ├─ Resolve dependencies
     │  ├─ Topological sort
     │  └─ Instantiate modules (constructor injection)
     │
     ├─ Extension Pass
     │  ├─ TimberPostModuleExtension
     │  ├─ AcfPathsModuleExtension
     │  └─ BlockRegistrationModuleExtension
     │
     └─ Initialization Pass ◄── MODULE->INIT() IS CALLED HERE
        ├─ Call module->init() (REGISTERS hooks, doesn't execute them)
        └─ Execute enabled features
     ↓
9. WordPress: after_setup_theme (priority 10+)
     ↓
10. WordPress: init (priority 10) ◄── WORDPRESS 'INIT' HOOK FIRES HERE
     ↓
11. Registered hooks execute (hooks that were registered in module->init())
     ↓
12. WordPress continues normal flow
```

**CRITICAL TIMING NOTE:**
Despite the naming similarity, `module->init()` is **NOT** called during WordPress's `init` hook:
- `module->init()` is called during `after_setup_theme` at **priority 5**
- WordPress's `init` hook fires later at **priority 10**
- Use `module->init()` to **REGISTER** hooks (e.g., `add_action('init', ...)`)
- Those registered hooks will execute later when WordPress fires them

### Module Method Call Order

```
1. __construct()
   ↑ DI container injects dependencies
   ↑ Called during Registration Pass
   ↑ During after_setup_theme (priority 5)

2. Extension pass runs
   ↑ POST_CLASSES registered with Timber
   ↑ ACF paths configured
   ↑ Blocks registered
   ↑ During after_setup_theme (priority 5)

3. init() ◄── Called during after_setup_theme (priority 5)
   ↑ Always called
   ↑ Use to REGISTER hooks, post types, assets, etc.
   ↑ Called during Initialization Pass
   ↑ NOT called during WordPress's 'init' hook

4. Feature methods (if enabled) ◄── Called during after_setup_theme (priority 5)
   ↑ Only called if config enables them
   ↑ Called after init()
   ↑ Called during Initialization Pass

5. Registered hooks fire
   ↑ WordPress hook system
   ↑ During normal WP execution
```

## Design Principles

### 1. Convention over Configuration

**Convention:** Modules follow standard structure and naming.

```
modules/MyFeature/
└── MyFeatureModule.php

namespace Sitchco\App\Modules\MyFeature;
class MyFeatureModule extends Module {}
```

**Benefit:** Less configuration needed, more predictable code.

### 2. Explicit Dependencies

**Explicit:** Dependencies declared in `DEPENDENCIES` constant.

```php
public const DEPENDENCIES = [TimberModule::class];
```

**Benefit:** Clear what each module needs, automatic resolution.

### 3. Composition over Inheritance

**Composition:** Modules compose functionality via dependencies.

```php
public function __construct(
    private EventRepository $repo,
    private EmailService $email
) {}
```

**Benefit:** Flexible, testable, loosely coupled.

### 4. Open/Closed Principle

**Open for extension:** Child themes extend parent functionality.
**Closed for modification:** Core and parent theme code unchanged.

```php
// Parent theme provides
class SiteHeaderModule extends Module
{
    public const FEATURES = ['stickyHeader', 'searchBar'];
}

// Child theme configures (no code changes)
return [
    'modules' => [
        SiteHeaderModule::class => [
            'stickyHeader' => true,
            'searchBar' => false,  // Disable parent feature
        ],
    ],
];
```

### 5. Single Responsibility

**Each module:** One feature area, one responsibility.

**Good:**
- `EventModule` - Event post type and related features
- `GalleryModule` - Gallery functionality
- `ProductModule` - Product management

**Bad:**
- `FeaturesModule` - Everything mixed together

### 6. Dependency Inversion

**Depend on abstractions:** Use interfaces and DI.

```php
interface EventRepositoryInterface
{
    public function findUpcoming(): array;
}

class EventModule extends Module
{
    public function __construct(
        private EventRepositoryInterface $repo  // Interface, not concrete
    ) {}
}
```

## Integration Points

### Timber Integration

**Module:** `PostModel` (aliased as `TimberModule`)

**Purpose:** Advanced post/term models with Timber.

```php
class EventModule extends Module
{
    public const DEPENDENCIES = [TimberModule::class];
    public const POST_CLASSES = [EventPost::class];
}
```

**Extension:** `TimberPostModuleExtension` registers `POST_CLASSES` with Timber's classmap.

### ACF Integration

**Modules:** `AcfPostTypeQueries`, `AcfOptions`, `AcfPostTypeAdminColumns`, etc.

**Purpose:** Advanced Custom Fields integration.

**Extension:** `AcfPathsModuleExtension` configures ACF JSON save/load paths per module.

### Gutenberg Integration

**Module:** `BlockConfig`

**Purpose:** Custom block registration and management.

**Extension:** `BlockRegistrationModuleExtension` registers blocks from module `blocks/` directories.

### Asset Management

**Class:** `ModuleAssets`

**Purpose:** Context-aware CSS/JS enqueueing.

**Development:** Loads from `modules/*/assets/` (Vite dev server)
**Production:** Loads from root-level `dist/` (Vite build output - all modules bundled)

## Performance Considerations

### Config Caching

**Cache:** Object cache (if available)
**Key:** `sitchco_config_{key}`
**Invalidation:** Automatic in 'local' environment

**Benefit:** Config files only parsed once per request.

### Lazy Loading

**DI Container:** Uses lazy loading for services.

**Benefit:** Services only instantiated when needed.

### Dependency Resolution

**Optimization:** Topological sort ensures minimal passes.

**Benefit:** Each module instantiated exactly once.

### Asset Loading

**Conditional:** Load assets only when needed.

```php
$this->enqueueFrontendAssets(function (ModuleAssets $assets) {
    if (is_singular('event')) {
        $assets->script('event-single.js');
    }
});
```

**Benefit:** Smaller payload, faster page loads.

## Testing Strategy

### Unit Tests

**Test:** Individual module methods in isolation.

**Mock:** Dependencies via DI container.

```php
$mockRepo = $this->createMock(EventRepository::class);
$module = new EventModule($mockRepo);
```

### Integration Tests

**Test:** Module interactions and dependency resolution.

**Verify:** Modules load in correct order, config merges properly.

### Functional Tests

**Test:** Complete WordPress integration.

**Verify:** Hooks registered, post types created, assets enqueued.

## Debugging

### Check Active Modules

```php
global $SitchcoContainer;
$registry = $SitchcoContainer->get(\Sitchco\Framework\ModuleRegistry::class);
$modules = $registry->getActiveModules();
var_dump(array_keys($modules));
```

### Inspect Config

```php
$config = \Sitchco\Framework\ConfigRegistry::load('modules');
var_dump($config);
```

### Enable WP_DEBUG

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Check logs
tail -f wp-content/debug.log
```

### Trace Module Loading

Add temporary logging:

```php
public function init(): void
{
    error_log('EventModule initialized');
}
```

## Related Documentation

- [Getting Started](../00-START-HERE.md)
- [Creating a Module](../guides/creating-a-module.md)
- [Adding Dependencies](../guides/adding-dependencies.md)
- [Base Module API](../reference/base-module-api.md)
- [Core Modules](../reference/core-modules.md)
