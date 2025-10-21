# Sitchco Framework - Quick Reference

## What Is This?

A modular WordPress architecture enabling reusable, dependency-managed functionality across three layers: **Core (mu-plugin) → Parent Theme → Child Theme**. Each layer builds on the previous, with config cascading and automatic dependency resolution.

## Navigation

| Task | Documentation |
|------|---------------|
| **Create a module** | `guides/creating-a-module.md` |
| **Add dependencies** | `guides/adding-dependencies.md` |
| **Use feature flags** | `guides/feature-flags.md` |
| **Manage CSS/JS assets** | `guides/asset-management.md` |
| **Module API reference** | `reference/base-module-api.md` |
| **Core modules list** | `reference/core-modules.md` |
| **System architecture** | `architecture/overview.md` |
| **Code examples** | `examples/` directory |

## 30-Second Overview

**Three Layers:**
- **Layer 1 (Core):** Framework foundation + 22 infrastructure modules
- **Layer 2 (Parent Theme):** Reusable UI components (header, footer, etc.)
- **Layer 3 (Child Theme):** Project-specific features

**Bootstrap Process:**
1. WordPress fires `after_setup_theme` (priority 5)
2. ConfigRegistry loads `sitchco.config.php` files (core → parent → child)
3. ModuleRegistry activates modules: Register → Extend → Initialize
4. Modules' `init()` methods and enabled features execute

**Key Concepts:**
- **Modules** extend `Sitchco\Framework\Module` base class
- **Dependencies** declared via `DEPENDENCIES` constant
- **Features** (optional methods) enabled via config
- **DI Container** (PHP-DI) handles constructor injection
- **Config Cascade** merges hierarchically (child overrides parent overrides core)

## File Locations

### Framework Core
- Entry: `sitchco-core.php`
- Bootstrap: `src/Framework/Bootstrap.php`
- Base Module: `src/Framework/Module.php`
- Module Registry: `src/Framework/ModuleRegistry.php`
- Config Registry: `src/Framework/ConfigRegistry.php`
- Assets: `src/Framework/ModuleAssets.php`

### Configuration Files
- Core: `sitchco.config.php`
- Parent: `/themes/sitchco-parent-theme/sitchco.config.php`
- Child: `/themes/{child-theme}/sitchco.config.php`

### Core Modules (22 total)
Located in `modules/`:
- **Models:** PostModel, TermModel, ImageModel
- **ACF:** AcfOptions, AcfPostTypeQueries, AcfPostTypeAdminColumns, AcfPostTypeAdminSort, AcfPostTypeAdminFilters
- **WordPress:** Cleanup, SearchRewrite, SvgUpload, BlockConfig
- **Performance:** WPRocket, Imagify, YoastSEO, Stream, AmazonCloudfront
- **UI:** UIFramework, Flash, SvgSprite, AdminTools, PageOrder
- **Infrastructure:** BackgroundProcessing

## Quick Start

### 1. Create a Module

```
modules/MyFeature/MyFeatureModule.php
```

Module extends `Module` class, implements `init()` method.

### 2. Register in Config

```php
// sitchco.config.php
return [
    'modules' => [
        \Sitchco\App\Modules\MyFeature\MyFeatureModule::class,
    ],
];
```

### 3. Details

See `guides/creating-a-module.md` for complete walkthrough.

## Module Structure

```
modules/MyModule/
├── MyModuleModule.php    # Main module class
├── MyPost.php            # Timber post class (optional)
├── MyRepository.php      # Data access (optional)
├── assets/               # CSS/JS source files
├── blocks/               # Gutenberg blocks
└── acf-json/            # ACF field groups

# Assets build to root-level dist/ (shared by all modules)
dist/                     # Built assets (sibling to sitchco.config.php)
└── assets/
    └── *.js, *.css       # Bundled, hashed filenames
```

## Common Module Patterns

| Pattern | Constant | Purpose |
|---------|----------|---------|
| **Dependencies** | `DEPENDENCIES = [...]` | Other modules required |
| **Features** | `FEATURES = [...]` | Optional methods |
| **Timber Posts** | `POST_CLASSES = [...]` | Custom post classes |
| **DI Injection** | `__construct(...)` | Inject services |
| **Initialization** | `init()` method | Always called |

## Three-Layer System

```
┌─────────────────────────────────────┐
│ Child Theme                         │
│ • Project-specific modules          │
│ • Extends parent + core             │
└─────────────────────────────────────┘
              ↑ extends
┌─────────────────────────────────────┐
│ Parent Theme                        │
│ • Reusable components               │
│ • Builds on core                    │
└─────────────────────────────────────┘
              ↑ extends
┌─────────────────────────────────────┐
│ Core (sitchco-core)                 │
│ • Framework + infrastructure        │
│ • Base classes + 22 modules         │
└─────────────────────────────────────┘
```

## Config Cascade

Configs merge recursively: **Child > Parent > Core**

Example:
```php
// Core: disableEmojis = true
// Parent: disableGutenbergStyles = false
// Result: Both settings active
```

## Getting Help

- **Examples:** See `examples/` for working module templates
- **API Reference:** `reference/base-module-api.md` for complete method list
- **Architecture:** `architecture/overview.md` for deep dive
- **Core Modules:** `reference/core-modules.md` for all 22 modules

## Related Existing Docs

- `devops.md` - Deployment and DevOps
- `artifact-generation.md` - Build artifacts

---

**Last Updated:** 2025-10-17
**Version:** See `sitchco-core.php` for framework version
