# Technology Stack

**Analysis Date:** 2026-03-09

## Languages

**Primary:**
- PHP 8.2 - All backend framework code, modules, models, tests
- JavaScript (ES Modules) - Frontend UI scripts, editor integrations, block assets

**Secondary:**
- CSS - Module-level stylesheets (`modules/*/assets/styles/`)
- Twig - Templating for Gutenberg blocks and partials (`modules/*/templates/`, `templates/`)

## Runtime

**Environment:**
- PHP 8.2 (via DDEV Apache-FPM container)
- Node.js 20 (via DDEV container, also configured in `config.local.yaml`)
- WordPress (core installed via `johnpbloch/wordpress` Composer package)

**Package Manager:**
- Composer 2 - PHP dependencies (lockfile at project root `public/composer.lock`)
- pnpm - JavaScript dependencies (lockfile: `pnpm-lock.yaml` present, lockfile version 9.0)

**Vendor directory:**
- Composer vendor path: `public/wp-content/vendor` (non-standard, configured in `public/composer.json` `config.vendor-dir`)

## Frameworks

**Core:**
- WordPress - CMS platform (installed to `public/wp/`)
- Timber/Twig `*` - Templating layer over WordPress, provides `Timber\Post`, `Timber\Term`, Twig rendering
- Sitchco Core - Custom modular framework (`src/Framework/`) with DI container, module registry, config registry, asset pipeline

**Build/Dev:**
- `@sitchco/cli` `2.1.9` - Custom CLI for build/dev/format/lint/clean commands (wraps Vite + ESBuild)
- Vite - Frontend asset bundling (dev server via `.vite.hot` file, production builds to `dist/` with manifests at `dist/.vite/manifest.json`)
- ESLint 9 - JavaScript linting via `@sitchco/eslint-config` `^2.1.2`
- DDEV - Local development environment (Apache-FPM + MariaDB + Redis)

**Testing:**
- PHPUnit - PHP test runner (via `cyruscollier/wp-test` `dev-master`)
- `WPTest\Test\TestCase` - Base test case for WordPress integration tests
- `spatie/phpunit-watcher` - Auto-run tests on file change (dev dependency)
- Kint - Debug/dump utility for local development (dev dependency)

## Key Dependencies

**Critical (Composer - `composer.json`):**
- `php-di/php-di` `^7.0` - Dependency injection container (powers `$GLOBALS['SitchcoContainer']`)
- `timber/timber` `*` - WordPress templating framework (Twig integration, post models)
- `illuminate/support` `*` - Laravel Collections, string helpers, array utilities
- `illuminate/collections` `*` - Laravel Collection class (extended as `Sitchco\Collection`)
- `nesbot/carbon` `^3.8` - Date/time library (extended as `Sitchco\Support\DateTime`)
- `deliciousbrains/wp-background-processing` `*` - Background job queue for async WordPress tasks

**WordPress Plugins (project-level `public/composer.json`):**
- `wpengine/advanced-custom-fields-pro` `*` - Custom fields framework (heavily integrated via ACF modules)
- `wp-media/wp-rocket` `*` - Page caching and performance optimization
- `wpackagist-plugin/redis-cache` `*` - Object cache backend (Redis)
- `wpackagist-plugin/imagify` `*` - Image optimization service
- `wpackagist-plugin/stream` `*` - Activity logging/audit trail
- `wpackagist-plugin/block-manager` `*` - Gutenberg block management
- `wpackagist-plugin/wp-nested-pages` `*` - Page ordering UI
- `gravity/gravityforms` `*` - Form builder
- `kadencewp/kadence-blocks` `dev-release` - Extended Gutenberg blocks
- `deliciousbrains-plugin/wp-migrate-db-pro` `*` - Database migration tool
- `situation/wordpress-seo-premium` `dev-modified` - Yoast SEO Premium (custom fork)
- `situation/wp-all-import-pro` `dev-master` - Data import tool

**JavaScript (devDependencies in `package.json`):**
- `@sitchco/cli` `2.1.9` - Monorepo build CLI (wraps Vite, ESBuild, PostCSS)
- `@sitchco/eslint-config` `^2.1.2` - Shared ESLint configuration

## Configuration

**Environment:**
- WordPress environment type (`wp_get_environment_type()`) determines behavior:
  - `local`: Disables Timber cache, sets Logger minimum level to DEBUG, enables Vite dev server
  - Production: Uses production build assets from `dist/`
- `SITCHCO_LOG_LEVEL` constant - Controls Logger minimum level (`DEBUG`, `INFO`, `WARNING`, `ERROR`)
- `SITCHCO_LOG_FILE` constant - Enables persistent file logging to `wp-content/uploads/logs/{date}.log`
- `SITCHCO_CLOUDFLARE_API_TOKEN` constant - Cloudflare cache purge authentication
- `SITCHCO_CLOUDFLARE_ZONE_ID` constant - Cloudflare zone identifier
- `.env` files: Not detected in plugin directory (environment config lives in `wp-config.php` / `local-config.php`)

**Build:**
- `eslint.config.mjs` - ESLint config, delegates to `@sitchco/eslint-config`
- `pnpm-lock.yaml` - pnpm lockfile (lockfile version 9.0)
- `sitchco.blocks.json` - Block manifest registry (auto-generated hash + block path mappings)
- `sitchco.config.php` - Module/container/block configuration (loaded by `ConfigRegistry`)

**Framework Constants (defined in `sitchco-core.php`):**
- `SITCHCO_CORE_VERSION` - Plugin version (`0.0.1`)
- `SITCHCO_CORE_DIR` - Plugin root directory
- `SITCHCO_CORE_CONFIG_DIR` - Config search path (same as plugin root)
- `SITCHCO_CORE_TEMPLATES_DIR` - Twig templates directory
- `SITCHCO_CORE_TESTS_DIR` - Test directory
- `SITCHCO_CORE_FIXTURES_DIR` - Test fixtures directory
- `SITCHCO_CONFIG_FILENAME` - Config filename (`sitchco.config.php`)
- `SITCHCO_DEV_HOT_FILE` - Vite dev server indicator (`.vite.hot`)

## Platform Requirements

**Development:**
- DDEV with Docker
- PHP 8.2+
- Node.js 20
- pnpm (for JavaScript dependencies)
- Composer 2 (for PHP dependencies)
- MariaDB 10.4 (via DDEV)
- Redis (via `docker-compose.cache.yaml` / `redis-cache` plugin)

**Production:**
- PHP 8.2+ with WordPress
- MariaDB / MySQL
- Redis (object cache backend)
- Apache with mod_rewrite (htaccess rules via WP Rocket)
- Optional: CloudFront CDN, Cloudflare CDN

**Build Artifacts:**
- Production assets built to `dist/` directory with Vite manifests
- SVG sprite sheets generated to `dist/assets/images/sprite.svg`
- Block asset PHP files generated to `dist/` (`.asset.php` files with dependency arrays)

---

*Stack analysis: 2026-03-09*
