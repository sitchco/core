# Coding Conventions

**Analysis Date:** 2026-03-09

## Languages

**PHP (Primary):** PSR-4 autoloaded, two root namespaces:
- `Sitchco\` maps to `src/`
- `Sitchco\Modules\` maps to `modules/`
- `Sitchco\Tests\` maps to `tests/` (dev only)

**JavaScript (Secondary):** Vanilla ES modules (`.js` / `.mjs`) in module `assets/scripts/` directories. No TypeScript, no JSX. Uses `@wp/hooks`-style action/filter system via `window.sitchco.hooks`.

**CSS (Secondary):** Plain CSS with CSS nesting (`&` syntax), CSS custom properties, and modern features (anchor positioning, `@starting-style`, `popover`). No preprocessor.

## Naming Patterns

**PHP Files:**
- One class per file, named after the class: `CacheInvalidation.php`, `PostBase.php`
- PascalCase for class files, matching the class name exactly
- Test files: `{ClassName}Test.php` -- e.g., `CacheInvalidationTest.php`
- Fakes/stubs: `{Name}Tester.php` in `tests/Fakes/`

**PHP Classes:**
- PascalCase: `ModuleAssets`, `CacheInvalidation`, `PostLifecycle`
- Abstract classes use a `Base` suffix: `PostBase`, `RepositoryBase`, `OptionsBase`
- Interfaces: PascalCase noun: `ModuleExtension`, `Repository`
- Enums: PascalCase: `LogLevel`, `CropDirection`, `ModalType`
- Traits: PascalCase verb phrase: `HasHooks`

**PHP Methods:**
- camelCase: `activateModules()`, `findOneBySlug()`, `syncObjectCacheFlush()`
- Getters: no `get` prefix for public accessors -- `path()`, `slug()`, `value()`
- Boolean methods: `is` prefix -- `isAvailable()`, `isFile()`, `isRoot()`
- WordPress hook callbacks: `on` prefix -- `onTransitionPostStatus()`, `onAfterInsertPost()`
- Factory methods: `create()` static method

**PHP Properties:**
- camelCase: `$modulePath`, `$registeredModuleClassnames`
- Private with underscore prefix for internal references: `$_local_meta_reference`
- Use `readonly` for immutable properties: `public readonly FilePath $moduleAssetsPath`

**PHP Constants:**
- SCREAMING_SNAKE_CASE: `FEATURES`, `POST_CLASSES`, `DEPENDENCIES`, `HOOK_SUFFIX`
- Defined as class constants, not global constants (except in the main plugin file `sitchco-core.php`)

**JavaScript Functions:**
- camelCase: `showModal()`, `positionArrow()`, `syncModalWithHash()`
- Constants: SCREAMING_SNAKE_CASE: `COMPONENT`, `SHOW_HOOK`, `HIDE_HOOK`

**CSS Classes:**
- BEM with `sitchco-` prefix: `.sitchco-modal`, `.sitchco-modal__container`, `.sitchco-modal--box`
- CSS custom properties: `--modal-bg-color`, `--popover-arrow-size`
- Internal/computed custom properties: `--_arrow-offset` (underscore prefix)

## PHP Namespace Organization

**Root namespace:** `Sitchco\`

```
Sitchco\Framework\       # Core framework classes (Module, Bootstrap, Registry)
Sitchco\Model\           # Timber post/term model classes
Sitchco\Repository\      # Data access / query classes
Sitchco\Rest\            # REST API route handling
Sitchco\Rewrite\         # URL rewrite/redirect rules
Sitchco\Support\         # Value objects, traits, exceptions
Sitchco\Utils\           # Static utility classes
Sitchco\ModuleExtension\ # Module extension system
Sitchco\Modules\         # Concrete module implementations (separate autoload root)
Sitchco\Flash\           # Admin notification system (under src/)
Sitchco\Events\          # Event classes
Sitchco\BackgroundProcessing\  # Background queue processing
```

## Code Style

**Formatting:**
- Tool: `@sitchco/prettier-config` (via `.prettierrc.js`)
- Run: `pnpm format` (uses `sitchco format` CLI command)
- Applies to both PHP and JS/CSS

**Linting:**
- Tool: `@sitchco/eslint-config` (via `eslint.config.mjs`)
- Run: `pnpm lint` (uses `sitchco lint` CLI command)
- JavaScript only (no PHP linter configured in this repo)

**PHP Style:**
- Use `declare(strict_types=1)` in new module files (seen in `CacheInvalidation.php`, `Invalidator.php`, `PostLifecycle.php`, `RewriteServiceTest.php`)
- Not all files use strict_types -- apply it to new files
- Arrow functions (`fn()`) preferred for short callbacks
- `match` expressions over `switch` where appropriate
- Union types: `\WP_Term|Term|string|int`
- Trailing commas in multi-line argument lists and arrays
- No trailing commas in single-line calls

## Import Organization

**PHP:**
1. PHP built-in classes first (e.g., `\ReflectionClass`, `\InvalidArgumentException`)
2. Third-party vendor classes (`DI\Container`, `Timber\Post`, `Illuminate\*`)
3. Framework classes (`Sitchco\Framework\*`)
4. Support/Utils classes (`Sitchco\Support\*`, `Sitchco\Utils\*`)
5. Module classes (`Sitchco\Modules\*`)
6. No blank lines between groups (single block of `use` statements)

**JavaScript:**
1. External dependencies (WordPress hooks)
2. Internal library imports (`./lib/*`)
3. No package imports -- vanilla JS only, dependencies loaded via WordPress `wp_enqueue_script`

## Hook Naming Convention

**PHP hooks use a namespaced forward-slash format:**
- Root namespace: `sitchco/`
- Generated via `Hooks::name('part1', 'part2', ...)` which produces `sitchco/part1/part2`
- Module-specific: `PostLifecycle::hookName('visibility_changed')` produces `sitchco/post/visibility_changed`
- The `HOOK_SUFFIX` constant on modules sets the middle segment: `public const HOOK_SUFFIX = 'post'`
- `HOOK_PREFIX` can also be defined if needed

**JavaScript hooks use the same `sitchco/` namespace:**
- Via `window.sitchco.hooks` (wraps `@wp/hooks`)
- Component hooks: `ui-modal-show`, `ui-popover-toggle`
- Lifecycle hooks: `sitchco/core/init` (CustomEvent), then action-based `INIT`, `REGISTER`, `READY`

## Module Pattern

Every module extends `Sitchco\Framework\Module` and follows this pattern:

```php
class MyModule extends Module
{
    public const DEPENDENCIES = [OtherModule::class];  // Auto-registered before this
    public const HOOK_SUFFIX = 'my-module';            // For hook namespacing
    public const FEATURES = ['featureA', 'featureB'];  // Toggleable features
    public const POST_CLASSES = [MyPost::class];       // Timber classmap entries

    public function init(): void
    {
        // Always called on activation. Register hooks here.
    }

    public function featureA(): void
    {
        // Called only if feature is enabled in config.
    }
}
```

- Modules are registered in `sitchco.config.php` as class names
- Features can be toggled per-config: `ModuleName::class => ['featureA' => true, 'featureB' => false]`
- Use `$this->assets()` for `ModuleAssets` instance (lazy-loaded)
- Use `$this->registerAssets(fn(ModuleAssets $assets) => ...)` for script/style registration
- Use `$this->enqueueGlobalAssets()`, `$this->enqueueFrontendAssets()`, etc. for context-specific enqueueing

## Error Handling

**Strategy:** Early return on invalid state, exceptions for programming errors, WordPress error handling for WP operations.

**Patterns:**
- Return `null` for "not found" cases: `findById()`, `findOne()`, `findOneBySlug()`
- Throw `InvalidArgumentException` for type mismatches: `checkBoundModelType()`
- Use custom exceptions for control flow: `RedirectExitException`, `ExitException`, `AjaxExitException`
- Wrap risky operations in try/catch with logging: `FileRegistry::loadFile()`, `ModuleRegistry::registerActiveModule()`
- Check `is_wp_error()` after WordPress API calls
- Guard clauses at method start, not nested conditionals

## Logging

**Framework:** `Sitchco\Utils\Logger` -- static utility class wrapping `error_log()`.

**Levels:** `DEBUG`, `INFO`, `WARNING`, `ERROR` (via `LogLevel` enum)

**Usage:**
```php
Logger::debug('Verbose message');           // Only in local environment by default
Logger::log('Info message');                // LogLevel::INFO default
Logger::warning('Something unexpected');
Logger::error('Failed operation: ' . $e->getMessage());
```

- Log level threshold: `DEBUG` in local, `INFO` in production
- Optional file logging: `SITCHCO_LOG_FILE = true` writes to `wp-content/uploads/logs/`
- Request ID included for tracing: `[rid] [LEVEL] message`

## Comments

**When to Comment:**
- Class-level PHPDoc for purpose and usage examples (see `PostLifecycle`, `OptionsBase`)
- Method-level PHPDoc for non-obvious parameters, return types, and `@throws`
- Inline comments for "why" decisions, not "what" the code does
- Section dividers using `// --- Group Name ---` in test files
- WordPress-style `@example` tags in PHPDoc for constants

**No Comments Needed:**
- Self-explanatory method names: `disableEmojis()`, `cleanLoginPage()`
- Simple getters and setters

## Function Design

**Size:** Methods are generally short (< 30 lines). Larger methods like `cleanBodyClass()` in `Cleanup.php` are the exception.

**Parameters:**
- Use named arguments for readability: `DateRange::format(separator: ' to ', with_end_date_year: true)`
- Use typed parameters with union types where needed
- Default values for optional parameters

**Return Values:**
- Nullable for "not found": `?Post`, `?PostBase`
- `static` for fluent/builder patterns: `return $this`
- `void` for side-effect-only methods
- Value objects (`FilePath`, `DateTime`, `Collection`) over raw types

## Module Design

**Exports:** Each module is a single class that extends `Module`. Complex modules use a directory:
```
modules/CacheInvalidation/
    CacheInvalidation.php    # Main module class
    CacheQueue.php           # Supporting class
    Invalidator.php          # Abstract base
    CloudflareInvalidator.php
    CloudFrontInvalidator.php
    ObjectCacheInvalidator.php
    WPRocketInvalidator.php
    PendingInvalidation.php
```

**Barrel Files:** Not used. Each class is imported directly by its fully-qualified name.

**Dependency Injection:** Via PHP-DI container (`$GLOBALS['SitchcoContainer']`). Modules receive dependencies via constructor injection:
```php
public function __construct(private CacheQueue $queue, private Container $container) {}
```

---

*Convention analysis: 2026-03-09*
