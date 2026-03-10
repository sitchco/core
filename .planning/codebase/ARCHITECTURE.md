# Architecture

**Analysis Date:** 2026-03-09

## Pattern Overview

**Overall:** Modular framework built on a dependency-injection container with a three-pass module activation lifecycle, WordPress hooks as the primary communication mechanism, and Timber/Twig for templating.

**Key Characteristics:**
- PHP-DI container (`$GLOBALS['SitchcoContainer']`) manages all service instantiation
- Modules are self-contained units registered via `sitchco.config.php` and activated through a three-pass pipeline (registration, extension, initialization)
- Inter-module communication uses namespaced WordPress hooks (`sitchco/{module}/{event}`) rather than direct method calls
- Configuration is layered: core -> filtered paths -> parent theme -> child theme, merged recursively
- Frontend assets are co-located with modules and built via Vite with hot-reload support

## Layers

**Framework Layer:**
- Purpose: Bootstrap, module lifecycle management, configuration loading, asset pipeline
- Location: `src/Framework/`
- Contains: `Bootstrap.php`, `Module.php`, `ModuleRegistry.php`, `ConfigRegistry.php`, `FileRegistry.php`, `ModuleAssets.php`, `BlockManifestRegistry.php`, `BlockManifestGenerator.php`
- Depends on: PHP-DI container, WordPress core
- Used by: All modules

**Module Layer:**
- Purpose: Feature implementations (WordPress integrations, UI components, cache systems)
- Location: `modules/`
- Contains: Classes extending `Sitchco\Framework\Module`, each with optional `assets/`, `blocks/`, `templates/`, `acf-json/` subdirectories
- Depends on: Framework layer, PHP-DI container
- Used by: WordPress hooks, theme code

**Model Layer:**
- Purpose: Domain objects wrapping WordPress post types and taxonomies via Timber
- Location: `src/Model/`
- Contains: `PostBase.php` (extends `Timber\Post`), `TermBase.php` (extends `Timber\Term`), and concrete models: `Post.php`, `Page.php`, `Image.php`, `Category.php`, `PostTag.php`, `PostFormat.php`
- Depends on: Timber, WordPress, ACF
- Used by: Repository layer, modules, templates

**Repository Layer:**
- Purpose: Query abstraction for finding and persisting model objects
- Location: `src/Repository/`
- Contains: `RepositoryBase.php` implementing `Repository` interface
- Depends on: Model layer, Timber, WP_Query
- Used by: Modules, theme code

**Support Layer:**
- Purpose: Shared value objects, traits, and interfaces
- Location: `src/Support/`
- Contains: `FilePath.php` (filesystem abstraction), `HasHooks.php` (hook name generation trait), `HookName.php` (namespaced hook builder), `DateTime.php`, `DateRange.php`, `AcfSettings.php`, Repository interfaces
- Depends on: WordPress core
- Used by: All other layers

**Utils Layer:**
- Purpose: Static utility classes
- Location: `src/Utils/`
- Contains: `Hooks.php`, `Logger.php`, `Cache.php`, `ArrayUtil.php`, `Acf.php`, `Block.php`, `Env.php`, `Image.php`, `Str.php`, `Url.php`, `WordPress.php`, `Method.php`, `TimberUtil.php`, `ValueUtil.php`, `BlockPattern.php`, `Template.php`
- Depends on: WordPress core
- Used by: All other layers

**Events Layer:**
- Purpose: Asynchronous/deferred event processing via WP Background Processing
- Location: `src/Events/`, `src/BackgroundProcessing/`
- Contains: `SavePostQueueEvent.php`, `SavePermalinksRequestEvent.php`, `BackgroundActionQueue.php`, `BackgroundQueueEvent.php`, `BackgroundRequestEvent.php`
- Depends on: `deliciousbrains/wp-background-processing`, Support layer
- Used by: BackgroundProcessing module

**Services Layer:**
- Purpose: Reusable services for REST API routes and URL rewrites
- Location: `src/Rest/`, `src/Rewrite/`
- Contains: `RestRouteService.php`, `RestRoute.php`, `RewriteService.php`, `Route.php`, `QueryRewrite.php`, `RedirectRoute.php`, `Rewrite.php`
- Depends on: WordPress REST API, rewrite API
- Used by: Modules needing custom endpoints or URL patterns

**Module Extensions Layer:**
- Purpose: Cross-cutting concerns applied to all modules after registration
- Location: `src/ModuleExtension/`
- Contains: `ModuleExtension.php` (interface), `TimberPostModuleExtension.php`, `AcfPathsModuleExtension.php`, `BlockRegistrationModuleExtension.php`
- Depends on: Framework layer, modules
- Used by: `ModuleRegistry` during extension pass

## Data Flow

**Bootstrap Sequence:**

1. WordPress fires `plugins_loaded` -> `sitchco-core.php` creates `Bootstrap` instance
2. `Bootstrap` hooks `after_setup_theme` at priority 5
3. `Bootstrap::initialize()` creates `ConfigRegistry`, loads merged config from `sitchco.config.php` files across core, filtered paths, parent theme, and child theme
4. `ContainerBuilder` builds PHP-DI container from merged `container` config key
5. `BlockManifestRegistry::ensureFreshManifests()` regenerates `sitchco.blocks.json` if stale (local env only)
6. `ModuleRegistry::activateModules()` runs three passes:
   - **Registration Pass**: Instantiate each module class via DI container, resolving `DEPENDENCIES` recursively
   - **Extension Pass**: Run each `ModuleExtension` (Timber post classmap, ACF JSON paths, block registration)
   - **Initialization Pass**: Call `init()` on each module, then execute enabled features

**Module Activation Detail:**

1. Config file lists module class names with optional feature overrides
2. `ModuleRegistry::registerActiveModule()` resolves dependencies depth-first, instantiates via container
3. Extensions (`TimberPostModuleExtension`, `AcfPathsModuleExtension`, `BlockRegistrationModuleExtension`) iterate over all active module instances
4. Each module's `init()` registers WordPress hooks; feature methods (listed in `FEATURES` constant) are called if enabled

**Cache Invalidation Signal Flow:**

1. WordPress content events trigger lifecycle modules (`PostLifecycle`, `AcfLifecycle`, `PostDeployment`)
2. Lifecycle modules fire namespaced hooks (e.g., `sitchco/post/visibility_changed`)
3. `CacheInvalidation` module maps signals to invalidator lists based on operating mode (delegated vs standalone)
4. `CacheQueue::write()` buffers `PendingInvalidation` entries, flushes to `wp_options` at shutdown
5. Minutely cron calls `CacheQueue::process()` which executes expired invalidators one at a time in priority order

**Asset Pipeline:**

1. Modules declare assets in `assets/scripts/` and `assets/styles/` subdirectories
2. `ModuleAssets` resolves production paths via Vite manifest (`dist/.vite/manifest.json`)
3. In local dev, `ModuleAssets` detects `.vite.hot` file and rewrites asset URLs to dev server
4. Block assets declared in `block.json` are resolved by `ModuleAssets::blockTypeMetadata()` filter

**State Management:**
- No client-side state management framework; WordPress hooks and PHP-DI container are the primary coordination mechanisms
- Caching is managed through `Sitchco\Utils\Cache` with three tiers: object cache (volatile), transients (medium), options (persistent)
- Module configuration is cached in object cache via `FileRegistry::loadAndCacheMergedData()`

## Key Abstractions

**Module (`Sitchco\Framework\Module`):**
- Purpose: Base class for all feature modules
- Examples: `modules/Wordpress/Cleanup.php`, `modules/CacheInvalidation/CacheInvalidation.php`, `modules/UIModal/UIModal.php`
- Pattern: Extend `Module`, define `DEPENDENCIES`, `FEATURES`, `POST_CLASSES` constants, implement `init()` method and feature methods

**ModuleExtension (`Sitchco\ModuleExtension\ModuleExtension`):**
- Purpose: Cross-cutting logic applied to all active modules
- Examples: `src/ModuleExtension/TimberPostModuleExtension.php`, `src/ModuleExtension/AcfPathsModuleExtension.php`, `src/ModuleExtension/BlockRegistrationModuleExtension.php`
- Pattern: Implement `extend(array $modules): void` interface, registered in `ModuleRegistry::EXTENSIONS`

**FileRegistry (`Sitchco\Framework\FileRegistry`):**
- Purpose: Abstract base for loading, merging, and caching files from multiple layered locations
- Examples: `src/Framework/ConfigRegistry.php`, `src/Framework/BlockManifestRegistry.php`
- Pattern: Define `FILENAME`, `PATH_FILTER_HOOK`, `CACHE_KEY` constants; implement `parseFile()`

**FilePath (`Sitchco\Support\FilePath`):**
- Purpose: Immutable filesystem path value object with traversal, globbing, and URL resolution
- Examples: Used throughout for `$module->path()`, `$module->assetsPath()`, `$module->blocksPath()`
- Pattern: Created via `FilePath::create()` or `FilePath::createFromClassDir()`, chained with `append()`, `parent()`, `findAncestor()`

**PostBase (`Sitchco\Model\PostBase`):**
- Purpose: Extended Timber Post with local meta/term mutation tracking for repository persistence
- Examples: `src/Model/Post.php`, `src/Model/Page.php`, `src/Model/Image.php`
- Pattern: Extend `PostBase`, set `POST_TYPE` constant, register via module's `POST_CLASSES`

**Invalidator (`Sitchco\Modules\CacheInvalidation\Invalidator`):**
- Purpose: Abstract base for cache invalidation backends
- Examples: `modules/CacheInvalidation/WPRocketInvalidator.php`, `modules/CacheInvalidation/CloudFrontInvalidator.php`, `modules/CacheInvalidation/CloudflareInvalidator.php`, `modules/CacheInvalidation/ObjectCacheInvalidator.php`
- Pattern: Implement `slug()`, `checkAvailability()`, `priority()`, `delay()`, `flush()`

**HookName (`Sitchco\Support\HookName`):**
- Purpose: Build namespaced WordPress hook names with `sitchco/` prefix
- Examples: `Hooks::name('cron', 'minutely')` -> `sitchco/cron/minutely`; `PostLifecycle::hookName('visibility_changed')` -> `sitchco/post/visibility_changed`
- Pattern: Static `Hooks::name()` or instance-based via `HasHooks` trait with `HOOK_SUFFIX` and optional `HOOK_PREFIX`

## Entry Points

**Plugin Bootstrap:**
- Location: `sitchco-core.php`
- Triggers: WordPress `plugins_loaded` action
- Responsibilities: Define constants, instantiate `Bootstrap`, which hooks `after_setup_theme` to run `initialize()`

**Configuration:**
- Location: `sitchco.config.php` (root-level and theme overrides)
- Triggers: Loaded by `ConfigRegistry` during bootstrap
- Responsibilities: Declare module list, DI container definitions, disallowed blocks

**Block Manifest:**
- Location: `sitchco.blocks.json` (root-level and theme copies)
- Triggers: Auto-generated by `BlockManifestGenerator` in local env; read by `BlockManifestRegistry`
- Responsibilities: Map block names to relative file paths for registration

## Error Handling

**Strategy:** Defensive with logging, no exceptions thrown to WordPress

**Patterns:**
- Module instantiation failures are caught in `ModuleRegistry::registerActiveModule()` and logged via `Logger::error()`
- File loading failures in `FileRegistry::loadFile()` are caught, logged, and return `null`
- Cache queue processing wraps `flush()` calls in try/catch, logs errors, continues processing
- `Module::init()` methods guard on plugin availability (e.g., `if (!class_exists('ACF')) return;`)
- Logger supports four levels (`DEBUG`, `INFO`, `WARNING`, `ERROR`) with environment-aware thresholds

## Cross-Cutting Concerns

**Logging:**
- `Sitchco\Utils\Logger` - static utility with level-based filtering
- Writes to `error_log()` by default; optional file logging to `wp-content/uploads/logs/{date}.log` when `SITCHCO_LOG_FILE` is defined
- Includes request ID for correlation across log entries

**Caching:**
- `Sitchco\Utils\Cache` - three-tier static utility (object cache, transients, options)
- Framework registries use object cache tier for merged config data
- Cache invalidation system uses options tier (DB-backed, survives flushes) for queue persistence

**Validation:**
- ACF field groups provide admin-side validation
- `RepositoryBase::checkBoundModelType()` validates model class on add/remove
- `FileRegistry` validates parsed data is array before merging

**Hook Naming:**
- All hooks use `sitchco/` prefix via `HookName` value object
- Modules use `HasHooks` trait with `HOOK_SUFFIX` for consistent naming
- Format: `sitchco/{module_suffix}/{event}` (e.g., `sitchco/post/visibility_changed`, `sitchco/cache/clear_all`)

---

*Architecture analysis: 2026-03-09*
