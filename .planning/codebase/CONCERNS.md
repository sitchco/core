# Codebase Concerns

**Analysis Date:** 2026-03-09

## Tech Debt

**Incomplete TermBase Model:**
- Issue: `TermBase` is a stub class with only TODO comments and no implementation. The class extends `Timber\Term` but adds nothing. There is no corresponding `TermRepository` class, and no shared interface between `PostBase` and `TermBase`.
- Files: `src/Model/TermBase.php`
- Impact: Term-related querying must use raw WordPress functions instead of the repository pattern used for posts. Inconsistent abstraction across content types.
- Fix approach: Implement `TermBase` with local meta tracking (mirroring `PostBase`), create `TermRepository`, and extract a shared interface.

**Potentially Obsolete Template Utility:**
- Issue: `Template::getTemplateScoped()` has a TODO questioning whether the method is still needed. It is still referenced by `Stream::summaryPageContent()` and `AcfPostTypeAdminFilters`.
- Files: `src/Utils/Template.php`, `modules/Stream.php`, `modules/AdvancedCustomFields/AcfPostTypeAdminFilters.php`
- Impact: Two parallel template systems coexist -- PHP-based `Template` class and Timber/Twig-based rendering. Unclear which to use for new code.
- Fix approach: Audit remaining `Template::getTemplateScoped()` callers and migrate to Twig if possible, then deprecate the PHP template system.

**Stale @package Annotations:**
- Issue: Multiple module files reference `@package Sitchco\Integration` or `@package Sitchco\Integration\Wordpress`, but the actual namespace is `Sitchco\Modules` or `Sitchco\Modules\Wordpress`. These annotations are incorrect/outdated from a refactor.
- Files: `modules/AmazonCloudfront.php`, `modules/Imagify.php`, `modules/WPRocket.php`, `modules/YoastSEO.php`, `modules/TimberModule.php`, `modules/Wordpress/Cleanup.php`, `modules/Wordpress/SvgUpload.php`, `modules/AdvancedCustomFields/AcfPostTypeQueries.php`, `modules/AdvancedCustomFields/AcfPostTypeAdminSort.php`, `modules/AdvancedCustomFields/AcfPostTypeAdminColumns.php`, `modules/AdvancedCustomFields/AcfPostTypeAdminFilters.php`
- Impact: Misleading documentation. Low severity but indicates incomplete refactor.
- Fix approach: Batch find-and-replace `@package Sitchco\Integration` with `@package Sitchco\Modules` across all module files.

**Stale @package in OptionsBase:**
- Issue: `OptionsBase` has `@package Backstage\Models` which is an entirely different project's namespace.
- Files: `src/Support/OptionsBase.php`
- Impact: Misleading documentation.
- Fix approach: Update to `@package Sitchco\Support`.

**Unpinned Composer Dependencies:**
- Issue: Several critical dependencies use wildcard (`*`) version constraints: `illuminate/support`, `illuminate/collections`, `timber/timber`, `deliciousbrains/wp-background-processing`. Only `php-di/php-di` and `nesbot/carbon` have proper version constraints.
- Files: `composer.json`
- Impact: Major version upgrades could introduce breaking changes without warning. Builds are not reproducible across environments without the lockfile. If the lockfile is regenerated, wildcard constraints allow untested major versions.
- Fix approach: Pin all dependencies to at least a major version range (e.g., `^2.0` instead of `*`).

**Imagify Module Lacks Environment Guard:**
- Issue: The Imagify module has a TODO to disable on local/staging environments but currently runs everywhere.
- Files: `modules/Imagify.php`
- Impact: Image optimization may run unnecessarily in non-production environments, wasting API calls.
- Fix approach: Add `wp_get_environment_type()` check to skip initialization on `local` and `staging`.

**AmazonCloudfront Module Configuration:**
- Issue: The module has a TODO suggesting configuration should be environment-driven but is currently hardcoded to always run.
- Files: `modules/AmazonCloudfront.php`
- Impact: Minor. The module's behavior is benign (rich editor user-agent fix) but should be controllable.
- Fix approach: Gate behind environment configuration or make it a feature flag in the module config.

**Test Import in Production Bootstrap:**
- Issue: `Bootstrap.php` imports `Sitchco\Tests\Fakes\TestFileRegistry` at the top of the file (as a use statement). While it is only used inside a `WP_TESTS_CONFIG_FILE_PATH` conditional, the class import itself is unconditional.
- Files: `src/Framework/Bootstrap.php`
- Impact: The autoloader will attempt to load the test class definition on every request. With PSR-4 autoloading this is deferred, but it's a code smell mixing test and production concerns.
- Fix approach: Use a fully qualified class name inside the conditional block instead of a top-level `use` statement.

## Known Bugs

**Pre-existing Test Failures:**
- Symptoms: Two tests fail consistently: `TimberModuleTest::test_blockRenderCallback_loads_metadata_and_sets_innerBlocksConfig` (error) and `ModuleAssetsTest::test_inlineScript` (failure).
- Files: `tests/Modules/TimberModuleTest.php`, `tests/Framework/ModuleAssetsTest.php`
- Trigger: Running `ddev test-phpunit` from the plugin directory.
- Workaround: These are known failures documented in project memory.

**Commented-Out Stream Test:**
- Symptoms: `StreamTest::testAddOptionsPageAddsSubmenu` is entirely commented out with a `TODO: fix this test!` marker.
- Files: `tests/Modules/StreamTest.php` (line 66)
- Trigger: Test relies on `wp_stream_get_instance()` and global `$submenu` state that is difficult to set up in the test environment.
- Workaround: Test is skipped by being commented out.

## Security Considerations

**SVG Upload Validation is Superficial:**
- Risk: The SVG upload validation in `SvgUpload::checkFileType()` uses string matching (`str_starts_with('<svg')` and `!str_contains('<script')`) rather than proper XML parsing. A crafted SVG could bypass these checks using encoded entities, CDATA sections, event handlers (`onload`, `onerror`), embedded `<foreignObject>`, or `<use xlink:href>` references.
- Files: `modules/Wordpress/SvgUpload.php` (lines 48-57)
- Current mitigation: Upload is restricted to users with `manage_options` capability (administrators only).
- Recommendations: Use a dedicated SVG sanitization library (e.g., `enshrined/svg-sanitize`) that parses the XML DOM and strips dangerous elements/attributes. The admin-only capability check provides defense in depth but should not be the primary validation.

**Incorrect MIME Type for SVG:**
- Risk: `SvgUpload::uploadMimes()` registers SVG with MIME type `text/html` instead of the correct `image/svg+xml`. This could cause browsers or downstream systems to interpret SVG files as HTML.
- Files: `modules/Wordpress/SvgUpload.php` (line 32)
- Current mitigation: `checkFileType()` later sets the correct `image/svg+xml` MIME type, but only for files that pass validation.
- Recommendations: Change the initial MIME type to `image/svg+xml`.

**Unsanitized $_GET Input in Stream Module:**
- Risk: `Stream::summaryPageContent()` reads `$_GET['start']` directly without sanitization and passes it to `DateTime::createFromTimeString()` and the Stream API query.
- Files: `modules/Stream.php` (line 43)
- Current mitigation: The function is called only from an admin page requiring `manage_options` capability. `DateTime::createFromTimeString()` would throw on malformed input (caught by try/catch).
- Recommendations: Sanitize with `sanitize_text_field()` and validate date format before use.

**Direct $_POST Access in Block Preview Check:**
- Risk: `Block::isPreview()` reads `$_POST['action']` and `$_POST['query']['preview']` without null coalescing or sanitization.
- Files: `src/Utils/Block.php` (lines 27-28)
- Current mitigation: The code is behind a `wp_doing_ajax()` check. Accessing undefined `$_POST['action']` will emit a PHP notice (or warning in PHP 8+).
- Recommendations: Use null coalescing: `($_POST['action'] ?? '') === 'acf/ajax/fetch-block'`.

**extract() Usage Throughout Codebase:**
- Risk: `extract()` is used in 6 locations across source and module code. While most use `EXTR_SKIP` or operate on controlled data, `extract()` can introduce unexpected variables into scope and makes code harder to audit.
- Files: `src/Utils/Template.php:50`, `src/Utils/WordPress.php:38`, `src/Utils/Block.php:41`, `modules/AdvancedCustomFields/AcfPostTypeQueries.php:128`, `modules/TimberModule.php:97`, `modules/TimberModule.php:177`
- Current mitigation: Most calls use `EXTR_SKIP` to prevent overwriting existing variables.
- Recommendations: Replace `extract()` with explicit variable assignments where feasible, particularly in `AcfPostTypeQueries::setDefaultQueryParameter()` and `Block::wrapperElement()`.

## Performance Bottlenecks

**Unbounded Post Queries:**
- Problem: Multiple locations use `'posts_per_page' => -1` which fetches all matching posts with no limit. On sites with large content volumes, these queries can consume significant memory and time.
- Files: `src/Repository/RepositoryBase.php:36` (`findAll`), `src/Repository/RepositoryBase.php:89` (`findAllDrafts`), `src/BackgroundProcessing/BackgroundActionQueue.php:95` (`addBulkPostsTask`), `modules/PageOrder.php:113`, `modules/Wordpress/SvgUpload.php:112` (`updateAllSvgMetadata`)
- Cause: No upper bound on returned results.
- Improvement path: Add sensible defaults (e.g., `'posts_per_page' => 500`) and implement pagination or batching for operations that truly need all posts. `SvgUpload::updateAllSvgMetadata()` is especially concerning as it runs on every admin load until the option flag is set.

**SvgUpload Metadata Check on Every Admin Load:**
- Problem: `SvgUpload::svgCheck()` runs on `admin_init` for every admin page load, checking an option and potentially querying all SVG attachments.
- Files: `modules/Wordpress/SvgUpload.php` (lines 92-101)
- Cause: The option check runs on every request; the actual migration runs once, but the conditional logic still fires.
- Improvement path: Move the migration to a one-time WP-CLI command or hook it to plugin activation rather than checking on every admin page load.

**Block Manifest Staleness Check on Every Request:**
- Problem: In local environments, `BlockManifestGenerator::shouldRegenerate()` reads and parses JSON manifest files, then re-discovers all block directories on every page load to compare hashes.
- Files: `src/Framework/BlockManifestGenerator.php`, `src/Framework/BlockManifestRegistry.php`
- Cause: Hash comparison requires directory scanning on every request.
- Improvement path: Use file modification time comparison or a development-only file watcher instead of full directory scanning. Consider caching the hash in object cache for the current request.

## Fragile Areas

**PostBase Magic Methods and Meta Tracking:**
- Files: `src/Model/PostBase.php` (lines 31-42)
- Why fragile: `__get()` and `__set()` intercept all property access and maintain a `_local_meta_reference` via reference (`&$this->$field`). This interacts with Timber's parent `Post::__get()` in non-obvious ways. The reference-based tracking means any property access creates a meta entry, including standard post fields.
- Safe modification: Always test with `RepositoryBase::add()` after changes. The `refresh()` method clears all local references.
- Test coverage: `RepositoryBaseTest.php` covers the add/update path.

**TimberModule::blockRenderCallback Static Method Chain:**
- Files: `modules/TimberModule.php` (lines 78-118)
- Why fragile: `blockRenderCallback()` uses `extract($scope)` to unpack variables, includes PHP files via `loadBlockScope()` which also uses `extract()`, and relies on variables magically appearing in scope. The `$render` variable on line 107 is only defined if `loadBlockScope()` sets it, but there is no default initialization.
- Safe modification: Ensure `$render = null` is initialized in the `$scope` array before `loadBlockScope()` calls. Add integration tests for block rendering.
- Test coverage: `TimberModuleTest.php` exists but has a known failing test.

**ModuleAssets Dev Server Detection:**
- Files: `src/Framework/ModuleAssets.php` (lines 29-48)
- Why fragile: Constructor contains branching logic for dev server detection that reads a hot file, parses URLs, and accesses `$_SERVER['HTTP_HOST']`. The `$devBuildPath` and `$devBuildUrl` properties are only set in certain code paths, and there is no null check when `findAncestor()` returns null for `devBuildPath`.
- Safe modification: Test with both dev server running and stopped. Verify admin pages work in both modes.
- Test coverage: `ModuleAssetsTest.php` exists but has a known failing test.

**Cleanup::disableFeedsRedirect Hard Exit:**
- Files: `modules/Wordpress/Cleanup.php` (lines 488-492)
- Why fragile: Contains a bare `exit()` call which is untestable and kills the PHP process. Any code registered to run after (shutdown hooks, logging) will not execute.
- Safe modification: Replace with a testable pattern using an exception-based exit (see `src/Support/Exception/ExitException.php` which exists in the codebase for this purpose).
- Test coverage: No test for the Cleanup module.

## Scaling Limits

**Object Cache remember() Pattern:**
- Current capacity: Relies on `wp_cache_get/set` with the `'sitchco'` group for all framework caching. Default TTL is `DAY_IN_SECONDS`.
- Limit: If the object cache backend (Redis/Memcached) evicts keys under memory pressure, all cached values regenerate simultaneously, causing a thundering herd.
- Scaling path: Add stale-while-revalidate semantics or probabilistic early expiration to the `Cache::remember()` method.

**BackgroundActionQueue Single-Process Model:**
- Current capacity: Background tasks are processed sequentially in a single WP-Background-Process worker.
- Limit: Bulk operations (e.g., `addBulkPostsTask` with `posts_per_page => -1`) create one queue entry per post. With thousands of posts, the queue may time out or stall.
- Scaling path: Implement batch processing in the `task()` method or use WP-CLI commands for bulk operations.

## Dependencies at Risk

**Wildcard Composer Dependencies:**
- Risk: `illuminate/support: *`, `illuminate/collections: *`, `timber/timber: *`, `deliciousbrains/wp-background-processing: *` have no version constraints.
- Impact: A `composer update` could pull in incompatible major versions. Timber 2.x had significant breaking changes from 1.x; a similar jump could break the entire framework.
- Migration plan: Pin to current major versions (e.g., `^2.0`).

## Missing Critical Features

**No TermRepository:**
- Problem: Posts have a full repository pattern (`RepositoryBase`, `PostRepository`, `PageRepository`) but terms have no equivalent.
- Blocks: Cannot use the same query abstraction for taxonomy terms as for posts.

**No Cleanup Module Tests:**
- Problem: The `Cleanup` module has 18 features, many of which modify WordPress globals and output, but zero test coverage.
- Blocks: Any refactoring of Cleanup features risks regressions that go undetected.

## Test Coverage Gaps

**Modules Without Any Test File:**
- What's not tested: `Cleanup`, `BlockManager`, `AdminTools`, `Flash`, `UIFramework`, `UIPopover`, `SvgSprite`, `AcfLifecycle`, `PostLifecycle`, `PostDeployment`, `AmazonCloudfront`, `BlockConfig`, `SearchRewrite`, `SvgUpload`, `PageOrder` (has test but limited)
- Files: `modules/Wordpress/Cleanup.php`, `modules/BlockManager.php`, `modules/AdminTools.php`, `modules/Flash.php`, `modules/UIFramework/UIFramework.php`, `modules/UIPopover/UIPopover.php`, `modules/SvgSprite/SvgSprite.php`, `modules/AcfLifecycle.php`, `modules/PostLifecycle.php`, `modules/PostDeployment.php`, `modules/AmazonCloudfront.php`, `modules/Wordpress/BlockConfig.php`, `modules/Wordpress/SearchRewrite.php`, `modules/Wordpress/SvgUpload.php`
- Risk: These modules modify WordPress behavior globally (disabling features, rewriting URLs, modifying queries). Changes could break frontend or admin functionality silently.
- Priority: High for `Cleanup` (most features, most impact), `PostLifecycle` (signals used by CacheInvalidation), `SvgUpload` (security-sensitive).

**Source Classes Without Tests:**
- What's not tested: `Rewrite/Route.php`, `Rewrite/QueryRewrite.php`, `Rewrite/RedirectRoute.php`, `Utils/Str.php`, `Utils/Block.php`, `Utils/Acf.php`, `Utils/WordPress.php`, `Utils/Url.php`, `Utils/ValueUtil.php`, `Utils/Method.php`, `Utils/Image.php`, `Utils/Env.php`, `Utils/TimberUtil.php`, `Utils/BlockPattern.php`, `Model/Image.php` (has test but limited), `Support/OptionsBase.php`, `Support/DateTime.php`, `Support/CropDirection.php`, `Support/AcfSettings.php`
- Files: Listed above in `src/` directory
- Risk: Utility classes like `Str`, `Block`, and `Acf` are widely used across modules. Bugs in these utilities cascade.
- Priority: Medium -- utility classes are typically simple, but `Block` and `Acf` contain non-trivial logic.

---

*Concerns audit: 2026-03-09*
