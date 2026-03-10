# External Integrations

**Analysis Date:** 2026-03-09

## APIs & External Services

**CDN / Cache Purging:**
- Cloudflare - Cache purge on content changes and deployments
  - SDK/Client: Direct HTTP via `wp_remote_request()` to `https://api.cloudflare.com/client/v4/zones/{zoneId}/purge_cache`
  - Auth: `SITCHCO_CLOUDFLARE_API_TOKEN` constant (Bearer token), `SITCHCO_CLOUDFLARE_ZONE_ID` constant
  - Implementation: `modules/CacheInvalidation/CloudflareInvalidator.php`
  - Purges by host list (www + non-www variants), filterable via `sitchco/cache/cloudflare_purge_hosts`

- Amazon CloudFront - CDN cache invalidation
  - SDK/Client: `CloudFront_Clear_Cache` WordPress plugin (C3 plugin)
  - Auth: Handled by the C3 plugin internally
  - Implementation: `modules/CacheInvalidation/CloudFrontInvalidator.php`
  - Availability check: `class_exists('CloudFront_Clear_Cache')`

**Image Optimization:**
- Imagify - Image compression and WebP conversion
  - SDK/Client: Imagify WordPress plugin
  - Auth: Managed by Imagify plugin (API key in plugin settings)
  - Implementation: `modules/Imagify.php`
  - Integration: Adjusts `imagify_site_root` for correct path resolution

**SEO:**
- Yoast SEO Premium - SEO management
  - SDK/Client: WordPress plugin (custom fork at `situation/wordpress-seo-premium`)
  - Implementation: `modules/YoastSEO.php`
  - Integration: Adjusts metabox priority, disables pro dashboard features, controls redirect behavior

**Forms:**
- Gravity Forms - Form builder and submission handling
  - SDK/Client: WordPress plugin via `gravity/gravityforms`
  - Auth: Plugin license key (managed in WP admin)
  - No direct module in sitchco-core; integration at project/theme level

**Data Import:**
- WP All Import Pro - Data import/export
  - SDK/Client: WordPress plugin via `situation/wp-all-import-pro`
  - Companion: ACF add-on via `situation/wpai-acf-add-on`
  - No direct module in sitchco-core

## Data Storage

**Databases:**
- MariaDB 10.4 (development via DDEV)
  - Connection: Standard WordPress `DB_*` constants in `wp-config.php`
  - Client: WordPress `$wpdb` global (raw queries in `modules/PageOrder.php`, `modules/AdvancedCustomFields/AcfPostTypeQueries.php`)
  - ORM: Timber `Post`/`Term` models wrapping `WP_Query` and `WP_Post`

**Object Cache:**
- Redis
  - Plugin: `wpackagist-plugin/redis-cache`
  - Client: WordPress `wp_cache_*` functions (abstracted via `src/Utils/Cache.php`)
  - Three-tier caching strategy in `src/Utils/Cache.php`:
    1. Object cache (`Cache::remember()`) - Volatile, fastest (Redis/Memcached in production)
    2. Transients (`Cache::rememberTransient()`) - Medium persistence, can be lost with Redis flush
    3. Options (`Cache::rememberOption()`) - Ultra-persistent, DB-backed, survives cache flushes

**File Storage:**
- WordPress uploads directory (`wp-content/uploads/`)
  - Deployment trigger file: `.clear-cache` in uploads base dir (`modules/PostDeployment.php`)
  - Log files: `wp-content/uploads/logs/{date}.log` (when `SITCHCO_LOG_FILE` enabled)
  - SVG sprite sheets: `dist/assets/images/sprite.svg` (build output)

**Caching:**
- WP Rocket - Full page caching + htaccess optimization
  - Implementation: `modules/WPRocket.php`
  - Controls: preload batch size (25), cron interval (120s), delay between requests (2s), SaaS batch size (25)
  - Cache invalidation: `modules/CacheInvalidation/WPRocketInvalidator.php` calls `rocket_clean_domain()`

## Cache Invalidation System

**Architecture:** Dual-mode orchestrator in `modules/CacheInvalidation/CacheInvalidation.php`

**Delegated Mode (WP Rocket active):**
- WP Rocket + editors handle day-to-day content changes
- Synchronous `wp_cache_flush()` on `before_rocket_clean_domain`
- CDN invalidators (CloudFront + Cloudflare) queued on `after_rocket_clean_domain`
- Unattended events (visibility_changed, deploy, clear_all) queue Rocket + CDNs

**Standalone Mode (WP Rocket inactive):**
- All content signals queue Object Cache + CDNs directly

**Queue System:** `modules/CacheInvalidation/CacheQueue.php`
- Persisted via `wp_options` row (`sitchco_cache_queue`)
- Buffered writes flushed once at shutdown
- Processed on minutely cron (`sitchco/cron/minutely`)
- Cascade pattern: processes one invalidator per cron tick with delay-based ordering

**Invalidator Interface:** `modules/CacheInvalidation/Invalidator.php`
- `ObjectCacheInvalidator` - Priority 0, delay 10s
- `WPRocketInvalidator` - Priority 10, delay 50s
- `CloudFrontInvalidator` - Priority 50, delay 100s
- `CloudflareInvalidator` - Priority 100, delay 100s

**Signal Hooks (triggers):**
- `PostLifecycle::hookName('content_updated')` - Published post saved (standalone only)
- `PostLifecycle::hookName('visibility_changed')` - Post enters/leaves publish status
- `AcfLifecycle::hookName('fields_saved')` - ACF options/user/term fields saved (standalone only)
- `PostDeployment::hookName('complete')` - Deployment detected
- `CacheInvalidation::hookName('clear_all')` - Manual full clear

## Authentication & Identity

**Auth Provider:**
- WordPress built-in authentication
  - Implementation: Standard WordPress user system
  - REST API: Capability-based permissions via `RestRoute` class (`src/Rest/RestRoute.php`)

## Monitoring & Observability

**Activity Logging:**
- WP Stream plugin - Activity audit trail
  - Implementation: `modules/Stream.php`
  - Custom summary admin page at `wp_stream_summary`
  - Default TTL set to 90 days

**Application Logging:**
- Custom Logger utility: `src/Utils/Logger.php`
  - Writes to `error_log()` by default
  - Optional persistent file logging to `wp-content/uploads/logs/{date}.log`
  - Log levels: DEBUG, INFO, WARNING, ERROR (`src/Utils/LogLevel.php`)
  - Request ID tracking (5-char unique suffix per request)
  - Environment-aware: DEBUG level in local, INFO in production

**Debug Tools (dev only):**
- Kint (`kint-php/kint`) - Rich variable dumper via `Logger::dump()`
- Xdebug - Available via DDEV (`ddev xdebug` to enable)
- XHProf - Profiling available via DDEV config (`xhprof/` directory present)

**Error Tracking:**
- No dedicated error tracking service (Sentry, Bugsnag, etc.) detected
- Errors go to PHP error_log and optional file logging

## CI/CD & Deployment

**Local Development:**
- DDEV (Docker-based)
  - Project name: `roundabout`
  - Web: Apache-FPM with PHP 8.2
  - DB: MariaDB 10.4
  - TLD: `.test`
  - Vite dev server ports: 5173-5178 exposed via DDEV router

**Deployment Detection:**
- Trigger file: `.clear-cache` in uploads directory (`modules/PostDeployment.php`)
  - Checked on minutely cron
  - Fires `sitchco/deploy/complete` action when found and deleted
- WP Migrate DB Pro migration complete event also fires `sitchco/deploy/complete`

**CI Pipeline:**
- Not detected in plugin directory (likely at repository/project level)

**Hosting:**
- Not explicitly configured in plugin; platform-agnostic
- Production indicators: WP Rocket, Redis, CloudFront, Cloudflare suggest managed WordPress hosting

## WordPress REST API

**Custom Endpoints:**
- REST route service: `src/Rest/RestRouteService.php`
  - Namespace pattern: `sitchco/v1` (via `Hooks::name()`)
  - Supports GET (`addReadRoute`) and POST (`addCreateRoute`) routes
  - Capability-based permission checks
- Route registration: `src/Rest/RestRoute.php`

## Background Processing

**Queue System:**
- `deliciousbrains/wp-background-processing` - Async task queue
  - Implementation: `src/BackgroundProcessing/BackgroundActionQueue.php`
  - Event types: `BackgroundRequestEvent` (HTTP-triggered), `BackgroundQueueEvent` (queue-based)
  - Module: `modules/BackgroundProcessing.php`
  - Features: `savePermalinksRequestEvent`, `savePostQueueEvent`

**Cron System:**
- Custom cron module: `modules/Cron.php`
  - Schedules: minutely (60s), hourly, twicedaily, daily
  - Hook pattern: `sitchco/cron/{schedule}`
  - Dispatches via WordPress cron (`wp_schedule_event`)

## Database Migrations

**WP Migrate DB Pro:**
- SDK/Client: `deliciousbrains-plugin/wp-migrate-db-pro`
- Integration: `modules/PostDeployment.php` hooks into `wpmdb_migration_complete`

## Environment Configuration

**Required constants (in `wp-config.php` or `local-config.php`):**
- Standard WordPress `DB_*` constants
- `WP_ENVIRONMENT_TYPE` - Controls framework behavior (local/staging/production)

**Optional constants:**
- `SITCHCO_CLOUDFLARE_API_TOKEN` - Enables Cloudflare cache purging
- `SITCHCO_CLOUDFLARE_ZONE_ID` - Cloudflare zone for purge requests
- `SITCHCO_LOG_LEVEL` - Override minimum log level
- `SITCHCO_LOG_FILE` - Enable persistent file-based logging

**Secrets location:**
- WordPress `wp-config.php` / `local-config.php` (not tracked in git)
- Plugin-managed API keys (Imagify, Gravity Forms, ACF Pro) stored in `wp_options`

## Webhooks & Callbacks

**Incoming:**
- WordPress REST API endpoints (via `RestRouteService`)
- WordPress cron callbacks (pseudo-cron via HTTP)

**Outgoing:**
- Cloudflare cache purge API (`POST https://api.cloudflare.com/client/v4/zones/{zoneId}/purge_cache`)
- CloudFront cache invalidation (via C3 plugin)
- Imagify image optimization API (via Imagify plugin)

## Plugin Integration Points

**Advanced Custom Fields Pro:**
- Deep integration across multiple modules
- Custom settings tabs: `modules/AdvancedCustomFields/AcfPostTypeQueries.php` (queries tab)
- Admin columns, filters, sorting: `modules/AdvancedCustomFields/AcfPostTypeAdminColumns.php`, `AcfPostTypeAdminFilters.php`, `AcfPostTypeAdminSort.php`
- Options pages: `modules/AdvancedCustomFields/AcfOptions.php`
- ACF JSON paths managed by `src/ModuleExtension/AcfPathsModuleExtension.php`
- Lifecycle hooks: `modules/AcfLifecycle.php` fires `sitchco/acf/fields_saved` for non-post entities
- Date field transformation: ACF date pickers auto-converted to `Sitchco\Support\DateTime` via Timber filters

**Gutenberg Blocks:**
- Block manifest registry: `src/Framework/BlockManifestRegistry.php`
- Block registration extension: `src/ModuleExtension/BlockRegistrationModuleExtension.php`
- Block configuration management: `modules/Wordpress/BlockConfig.php`
- Block visibility control: Per-post-type block restrictions via `sitchco.config.php` `disallowedBlocks`
- Custom block category: "Situation" (`sitchco` slug)
- Registered blocks: `sitchco/icon` (SvgSprite), `sitchco/modal` (UIModal)

---

*Integration audit: 2026-03-09*
