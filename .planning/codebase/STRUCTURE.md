# Codebase Structure

**Analysis Date:** 2026-03-09

## Directory Layout

```
sitchco-core/
├── .github/                    # GitHub CI workflows
│   └── workflows/
├── .husky/                     # Git hooks (pre-commit)
├── .planning/                  # GSD planning documents
│   └── codebase/               # Codebase analysis docs
├── dist/                       # Vite production build output
│   ├── .vite/                  # Vite manifest (manifest.json)
│   └── assets/                 # Compiled JS/CSS bundles
├── docs/                       # Project documentation
├── modules/                    # Feature modules (self-contained units)
│   ├── AdvancedCustomFields/   # ACF integration modules
│   ├── CacheInvalidation/      # Multi-layer cache invalidation system
│   ├── Model/                  # Timber model registration modules
│   ├── SvgSprite/              # SVG sprite system + icon block
│   ├── UIFramework/            # Core frontend JS/CSS framework
│   ├── UIModal/                # Dialog/modal component
│   ├── UIPopover/              # Popover component (CSS anchor positioning)
│   ├── Wordpress/              # WordPress cleanup and config modules
│   └── *.php                   # Standalone module files
├── src/                        # Framework core + shared code
│   ├── BackgroundProcessing/   # Async task queue system
│   ├── Events/                 # Deferred event classes
│   ├── Flash/                  # Flash message system
│   ├── Framework/              # Bootstrap, Module base, registries
│   ├── Model/                  # PostBase, TermBase, concrete models
│   ├── ModuleExtension/        # Cross-cutting module extensions
│   ├── Repository/             # Query/persistence layer
│   ├── Rest/                   # REST API route builder
│   ├── Rewrite/                # URL rewrite/route builder
│   ├── Support/                # Value objects, traits, interfaces
│   └── Utils/                  # Static utility classes
├── templates/                  # Shared Twig/PHP templates
├── tests/                      # PHPUnit test suite
│   ├── Fakes/                  # Test doubles and fixture modules
│   ├── fixtures/               # ACF fixture data files
│   ├── Flash/                  # Flash system tests
│   ├── Framework/              # Framework layer tests
│   ├── Model/                  # Model tests
│   ├── ModuleExtension/        # Extension tests
│   ├── Modules/                # Module integration tests
│   ├── Support/                # Support class tests
│   └── Utils/                  # Utility tests
├── composer.json               # PHP dependencies + PSR-4 autoload
├── package.json                # Node dependencies + build scripts
├── pnpm-lock.yaml              # pnpm lockfile
├── sitchco-core.php            # WordPress plugin entry point
├── sitchco.config.php          # Default module/container configuration
├── sitchco.blocks.json         # Auto-generated block manifest
├── .editorconfig               # Editor formatting rules
├── .prettierrc.js              # Prettier config
└── eslint.config.mjs           # ESLint config
```

## Directory Purposes

**`modules/`:**
- Purpose: Self-contained feature modules that extend `Sitchco\Framework\Module`
- Contains: PHP module classes, co-located assets, blocks, templates, ACF JSON
- Key files: Each module has a main PHP class; complex modules have subdirectories
- PSR-4 namespace: `Sitchco\Modules\`

**`modules/{ModuleName}/assets/`:**
- Purpose: Frontend source files for a specific module
- Contains: `scripts/` (JS/MJS files), `styles/` (CSS files)
- Built by Vite into `dist/`

**`modules/{ModuleName}/blocks/`:**
- Purpose: Gutenberg block definitions for a module
- Contains: Directories per block, each with `block.json`, `block.php`, `block.twig`, optional `.asset.php`

**`modules/{ModuleName}/acf-json/`:**
- Purpose: ACF field group JSON definitions scoped to a module
- Contains: JSON files auto-saved by ACF when editing field groups

**`modules/{ModuleName}/templates/`:**
- Purpose: Twig templates scoped to a module
- Contains: `.twig` files registered as Timber template locations

**`src/`:**
- Purpose: Framework core classes and shared infrastructure
- Contains: PHP classes organized by concern
- PSR-4 namespace: `Sitchco\`

**`src/Framework/`:**
- Purpose: Bootstrap lifecycle, module system, asset pipeline, configuration loading
- Contains: `Bootstrap.php`, `Module.php`, `ModuleRegistry.php`, `ConfigRegistry.php`, `FileRegistry.php`, `ModuleAssets.php`, `BlockManifestRegistry.php`, `BlockManifestGenerator.php`

**`src/Model/`:**
- Purpose: Domain model classes extending Timber post/term types
- Contains: `PostBase.php`, `TermBase.php`, `Post.php`, `Page.php`, `Image.php`, `Category.php`, `PostTag.php`, `PostFormat.php`

**`src/ModuleExtension/`:**
- Purpose: Cross-cutting extensions applied to all modules during activation
- Contains: `ModuleExtension.php` (interface), `TimberPostModuleExtension.php`, `AcfPathsModuleExtension.php`, `BlockRegistrationModuleExtension.php`

**`src/Support/`:**
- Purpose: Shared value objects, traits, interfaces, and exceptions
- Contains: `FilePath.php`, `HasHooks.php`, `HookName.php`, `DateTime.php`, `DateRange.php`, `AcfSettings.php`, `Repository/` interfaces, `Exception/` classes

**`src/Utils/`:**
- Purpose: Static utility/helper classes
- Contains: `Hooks.php`, `Logger.php`, `Cache.php`, `ArrayUtil.php`, `Acf.php`, `Block.php`, `Env.php`, `Image.php`, `Str.php`, `Url.php`, `WordPress.php`, `Method.php`, `TimberUtil.php`, `ValueUtil.php`, `BlockPattern.php`, `Template.php`, `LogLevel.php`

**`templates/`:**
- Purpose: Shared Twig and PHP templates available to all modules
- Contains: `image.twig`, `admin-filter-select.php`, `options-class.php.tpl`, `stream-activity-report.php`

**`tests/`:**
- Purpose: PHPUnit integration tests (run against WordPress test environment via DDEV)
- Contains: Test classes mirroring `src/` and `modules/` structure, `TestCase.php` base class
- PSR-4 namespace: `Sitchco\Tests\`

**`dist/`:**
- Purpose: Vite production build output
- Contains: Compiled/hashed JS/CSS bundles and `.vite/manifest.json`
- Generated: Yes (by `pnpm build` / `sitchco build`)
- Committed: Yes

## Key File Locations

**Entry Points:**
- `sitchco-core.php`: WordPress plugin entry point; defines constants, hooks `plugins_loaded` to create `Bootstrap`
- `sitchco.config.php`: Default module registry and container configuration
- `sitchco.blocks.json`: Auto-generated block manifest mapping block names to paths

**Configuration:**
- `composer.json`: PHP dependencies and PSR-4 autoload mapping
- `package.json`: Node dev dependencies and build scripts (uses `@sitchco/cli`)
- `.prettierrc.js`: Prettier configuration for JS/CSS formatting
- `eslint.config.mjs`: ESLint configuration
- `.editorconfig`: Editor indentation/whitespace rules

**Core Logic:**
- `src/Framework/Bootstrap.php`: Application bootstrap and initialization
- `src/Framework/Module.php`: Abstract base class all modules extend
- `src/Framework/ModuleRegistry.php`: Three-pass module activation pipeline
- `src/Framework/ConfigRegistry.php`: Layered config file loading and merging
- `src/Framework/ModuleAssets.php`: Vite-integrated asset registration and enqueueing
- `src/Framework/FileRegistry.php`: Abstract base for file discovery/merge/cache registries

**Models:**
- `src/Model/PostBase.php`: Base Timber Post with local meta/term mutation tracking
- `src/Model/TermBase.php`: Base Timber Term (minimal, placeholder for future expansion)
- `src/Repository/RepositoryBase.php`: WP_Query-based CRUD repository

**Testing:**
- `tests/TestCase.php`: Base PHPUnit test class
- `tests/sitchco.config.php`: Test-specific module configuration
- `tests/Fakes/`: Test doubles (`PostTester.php`, `EventPostTester.php`, `ModuleTester/`, etc.)
- `tests/fixtures/`: ACF fixture data (field groups, post types, taxonomies)

## Naming Conventions

**Files:**
- PHP classes: PascalCase matching class name (e.g., `CacheInvalidation.php`, `PostBase.php`)
- JS entry points: kebab-case `main.js` or descriptive (e.g., `hooks.js`, `editor-ui-main.js`)
- CSS entry points: kebab-case `main.css`
- Twig templates: kebab-case (e.g., `modal.twig`, `popover.twig`, `image.twig`)
- Block directories: kebab-case (e.g., `blocks/icon/`, `blocks/modal/`)
- Block files: `block.json`, `block.php`, `block.twig` (always these exact names)
- Test files: PascalCase with `Test` suffix matching tested class (e.g., `CacheInvalidationTest.php`)

**Directories:**
- Module directories: PascalCase matching module class or group name (e.g., `CacheInvalidation/`, `UIModal/`, `Wordpress/`)
- Source directories: PascalCase matching namespace segment (e.g., `BackgroundProcessing/`, `ModuleExtension/`)
- Asset subdirectories: lowercase plural (`scripts/`, `styles/`)
- Special directories: lowercase kebab-case (`acf-json/`)

**PHP Namespaces:**
- `Sitchco\` -> `src/`
- `Sitchco\Modules\` -> `modules/`
- `Sitchco\Tests\` -> `tests/`

## Where to Add New Code

**New Module (simple, standalone):**
- Create `modules/MyModule.php` with class extending `Sitchco\Framework\Module`
- Register in `sitchco.config.php` under the `modules` array
- Namespace: `Sitchco\Modules\MyModule`

**New Module (complex, with assets/blocks):**
- Create `modules/MyModule/` directory
- Create `modules/MyModule/MyModule.php` as the main module class
- Add `modules/MyModule/assets/scripts/` and `modules/MyModule/assets/styles/` for frontend assets
- Add `modules/MyModule/blocks/{block-name}/` with `block.json`, `block.php`, `block.twig` for blocks
- Add `modules/MyModule/templates/` for Twig templates
- Add `modules/MyModule/acf-json/` for ACF field group JSON
- Register in `sitchco.config.php` under the `modules` array
- Block manifest auto-regenerates in local env

**New Model (PostBase subclass):**
- Create `src/Model/MyModel.php` extending `Sitchco\Model\PostBase`
- Set `const POST_TYPE = 'my_post_type'`
- Register in a module's `POST_CLASSES` constant (e.g., `modules/Model/PostModel.php`)

**New Repository:**
- Create class in `src/Repository/` extending `RepositoryBase`
- Set `protected string $model_class = MyModel::class`

**New Utility:**
- Add static class to `src/Utils/`
- Use namespace `Sitchco\Utils`

**New Support Class (value object, trait, interface):**
- Add to `src/Support/`
- Use namespace `Sitchco\Support`

**New Module Extension:**
- Create class in `src/ModuleExtension/` implementing `ModuleExtension` interface
- Register in `ModuleRegistry::EXTENSIONS` array in `src/Framework/ModuleRegistry.php`

**New REST Endpoint:**
- Use `RestRouteService` in a module's `init()` method
- Call `$restService->addReadRoute()` or `$restService->addCreateRoute()`

**New Rewrite Rule:**
- Use `RewriteService` in a module's `init()` method
- Call `$rewriteService->register()` with path and query/callback args

**New Test:**
- Create `tests/{Area}/{ClassName}Test.php` extending `Sitchco\Tests\TestCase`
- Mirror the structure of the tested class (e.g., `tests/Modules/` for module tests)
- Add test fakes to `tests/Fakes/`

**New Block:**
- Create `modules/{ModuleName}/blocks/{block-name}/` directory
- Add `block.json` with block metadata (name, title, category, etc.)
- Add `block.php` for server-side context (receives `$context`, `$container`)
- Add `block.twig` for Twig template
- Block manifest auto-regenerates in local env; run build for production

## Special Directories

**`dist/`:**
- Purpose: Vite production build artifacts
- Generated: Yes (via `pnpm build` / `sitchco build`)
- Committed: Yes

**`dist/.vite/`:**
- Purpose: Vite manifest mapping source files to hashed output files
- Generated: Yes
- Committed: Yes (required for production asset resolution)

**`tests/dist/`:**
- Purpose: Vite build output for test module assets
- Generated: Yes
- Committed: Yes

**`node_modules/`:**
- Purpose: Node.js dependencies
- Generated: Yes (via `pnpm install`)
- Committed: No (gitignored)

**`vendor/`:**
- Purpose: Composer PHP dependencies (not present in this repo; managed by parent project)
- Generated: Yes (via `composer install` at parent level)
- Committed: No

**`modules/*/acf-json/`:**
- Purpose: ACF field group JSON definitions, auto-saved by ACF admin
- Generated: Partially (ACF writes on save, but treated as source files)
- Committed: Yes

**`.planning/`:**
- Purpose: GSD planning and analysis documents
- Generated: By GSD tooling
- Committed: Varies

---

*Structure analysis: 2026-03-09*
