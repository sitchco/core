# Testing Patterns

**Analysis Date:** 2026-03-09

## Test Framework

**Runner:**
- PHPUnit (version managed by Composer, via `wp-phpunit` integration)
- Config: `/Users/jstrom/Projects/web/roundabout/public/phpunit.xml`
- Bootstrap: `/Users/jstrom/Projects/web/roundabout/public/tests/phpunit.php`

**Assertion Library:**
- PHPUnit built-in assertions

**WordPress Test Integration:**
- Uses `WPTest\Test\PHPUnitBootstrap` (from `cyruscollier/wp-test` package)
- WordPress is fully loaded in tests -- real database, real hooks, real plugins
- The test bootstrap loads WordPress with a separate test database (`db_tests`)

**Run Commands:**
```bash
ddev test-phpunit                     # Run all tests (from sitchco-core/ cwd)
ddev test-phpunit --filter=CacheTest  # Run specific test class
ddev test-phpunit --filter=test_name  # Run specific test method
```

**Important:** Run `ddev test-phpunit` from the plugin directory (`sitchco-core/`), NOT from the project root. The ddev project root is `public/`.

## Test File Organization

**Location:** Tests mirror the source structure under `tests/`:

```
tests/
    TestCase.php                      # Base test case
    CoreMuPluginTest.php              # Plugin bootstrap tests
    CollectionTest.php                # Collection utility tests
    RepositoryBaseTest.php            # Repository pattern tests
    RestRouteServiceTest.php          # REST route tests
    RewriteServiceTest.php            # URL rewrite tests
    Framework/
        FileRegistryTest.php          # FileRegistry base class tests
        ModuleAssetsTest.php          # Asset pipeline tests
    Flash/
        AdminNotificationServiceTest.php
        FlashTest.php
    Model/
        ImageTest.php
    ModuleExtension/
        BlockRegistrationModuleExtensionTest.php
    Modules/
        AdvancedCustomFields/
            AcfOptionsTest.php
            AcfPostTypeAdminColumnsTest.php
            AcfPostTypeAdminFiltersTest.php
            AcfPostTypeAdminSortTest.php
            AcfPostTypeQueriesTest.php
            AcfPostTypeTest.php
        BackgroundProcessingTest.php
        CacheInvalidationTest.php
        CloudflareInvalidatorTest.php
        CronTest.php
        ImagifyTest.php
        PageOrderTest.php
        PendingInvalidationTest.php
        PostDeploymentTest.php
        StreamTest.php
        TimberModuleTest.php
        UIModal/
            ModalDataTest.php
        WPRocketTest.php
        YoastSEOTest.php
    Support/
        DateFormatTest.php
        DateRangeTest.php
        FilePathTest.php
    Utils/
        ArrayUtilTest.php
        CacheTest.php
        LoggerTest.php
    Fakes/                            # Test doubles
        EventPostTester.php
        EventRepositoryTester.php
        ModuleTester/
            ModuleTester.php          # Fake module for testing module system
            blocks/test-block/        # Fake block assets
        ParentModuleTester.php
        PostTester.php
        TestFileRegistry.php          # Fake FileRegistry for testing
    fixtures/                         # Test data files
        acf-field-group.php
        acf-post-type.php
        acf-taxonomy.php
        group_68fa4ca929b7c.json
        sample-image.jpg
    sitchco.config.php                # Test-specific module config
    sitchco.blocks.json               # Test-specific block manifest
    dist/                             # Pre-built test assets
        .vite/manifest.json           # Fake Vite manifest for asset tests
```

**Naming:**
- Test classes: `{SourceClassName}Test` -- e.g., `CacheInvalidationTest`, `CacheTest`
- Test methods: `test_{behavior_description}` using snake_case -- e.g., `test_delegated_content_updated_does_not_create_queue`
- Some older tests use camelCase: `testAddReadRoute`, `testRouteRule` (in `RestRouteServiceTest.php`, `RewriteServiceTest.php`)
- **For new tests, use `test_snake_case_description` pattern**

## Test Structure

**Base Test Case:**

All tests extend `Sitchco\Tests\TestCase`, which extends `WPTest\Test\TestCase` (WordPress PHPUnit integration):

```php
namespace Sitchco\Tests;

use DI\Container;

abstract class TestCase extends \WPTest\Test\TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        $this->container = $GLOBALS['SitchcoContainer'];
        parent::setUp();
    }

    protected function fakeHttp(?callable $handler = null): void
    {
        // Intercepts wp_remote_* calls
    }

    protected function restoreHttp(): void
    {
        remove_all_filters('pre_http_request');
    }
}
```

**Suite Organization:**

Tests are grouped with section comment dividers for complex test classes:

```php
class CacheInvalidationTest extends TestCase
{
    // --- Group 1: Delegated Mode -- Signal -> Queue Routing ---

    public function test_delegated_content_updated_does_not_create_queue(): void { ... }
    public function test_delegated_visibility_changed_queues_rocket_and_cdns(): void { ... }

    // --- Group 2: Standalone Mode -- Signal -> Queue Routing ---

    public function test_standalone_content_signal_queues_object_cache_and_cdns(): void { ... }

    // --- Group 3: Delegated Mode -- Sync Object Cache Flush ---
    // --- Group 4: Queue Processing ---
    // --- Group 5: Debounce ---
}
```

**Setup/Teardown:**

```php
protected function setUp(): void
{
    parent::setUp();
    // Get dependencies from DI container
    $this->queue = $this->container->get(CacheQueue::class);
    // Clean state
    $this->queue->flushWriteBuffer();
    delete_option(CacheQueue::OPTION_NAME);
    // Remove bootstrap hooks to isolate test
    foreach (self::SIGNAL_HOOKS as $hook) {
        remove_all_actions($hook);
    }
}

protected function tearDown(): void
{
    // Clean up WordPress state
    delete_option(CacheQueue::OPTION_NAME);
    // Restore default container bindings
    $this->container->set(WPRocketInvalidator::class, new WPRocketInvalidator());
    parent::tearDown();
}
```

## Mocking

**Framework:** PHPUnit built-in mocking (`$this->createMock()`)

**Patterns:**

Mock abstract classes with specific return values:
```php
private function createMockInvalidator(
    string $slug,
    bool $available,
    int $priority = 0,
    int $delay = 10,
): Invalidator {
    $mock = $this->createMock(Invalidator::class);
    $mock->method('slug')->willReturn($slug);
    $mock->method('isAvailable')->willReturn($available);
    $mock->method('priority')->willReturn($priority);
    $mock->method('delay')->willReturn($delay);
    return $mock;
}
```

Use mock expectations for verifying calls:
```php
$mockObjectCache = $this->createMockInvalidator('object_cache', true, 0, 10);
$mockObjectCache->expects($this->once())->method('flush');
// ... exercise code ...
// PHPUnit auto-verifies the expectation
```

Override DI container bindings for test isolation:
```php
$this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
$this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
```

**What to Mock:**
- External service invalidators (`CloudflareInvalidator`, `CloudFrontInvalidator`, etc.)
- HTTP requests (via `fakeHttp()` in base TestCase)
- Module dependencies when testing in isolation

**What NOT to Mock:**
- WordPress core functions (`add_action`, `apply_filters`, `wp_cache_*`, `get_option`)
- The DI container itself (use real container, override specific bindings)
- Database operations (tests use a real test database)
- The Timber/ACF framework (loaded and functional in tests)

## HTTP Mocking

The base `TestCase` provides `fakeHttp()` to intercept WordPress HTTP requests:

```php
// Intercept all HTTP requests with default handler (returns request details)
$this->fakeHttp();

// Custom handler for specific response patterns
$this->fakeHttp(function ($args, $url) {
    return [
        'response' => ['code' => 200],
        'body' => json_encode(['success' => true]),
    ];
});

// Restore real HTTP after test
$this->restoreHttp();
```

## Fixtures and Factories

**WordPress Factories:**
Tests use the WordPress `$this->factory()` for creating test data:

```php
$post_id = $this->factory()->post->create([
    'post_title' => 'Test Post',
    'post_status' => 'publish',
]);

$term_id = $this->factory()->term->create([
    'name' => 'Test Category',
    'taxonomy' => 'category',
]);

$thumbnail_id = $this->factory()->attachment->create_upload_object(
    SITCHCO_CORE_FIXTURES_DIR . '/sample-image.jpg',
);

$author_id = $this->factory()->user->create(['user_login' => 'author1']);
```

**Fixture Files:** Located in `tests/fixtures/`:
- `acf-field-group.php` -- ACF field group definition (returns array via `include`)
- `acf-post-type.php` -- ACF custom post type definition
- `acf-taxonomy.php` -- ACF taxonomy definition
- `group_68fa4ca929b7c.json` -- ACF JSON field group
- `sample-image.jpg` -- Test image for attachment tests
- Referenced via `SITCHCO_CORE_FIXTURES_DIR` constant

**Fake/Stub Classes:** Located in `tests/Fakes/`:
- `ModuleTester` -- Fake module with trackable state (`$initialized`, `$featureOneRan`, etc.)
- `PostTester` -- Simple `Post` subclass for testing Timber classmap
- `EventPostTester` -- Fake event post type for testing type checking
- `EventRepositoryTester` -- Fake repository for testing bound model types
- `ParentModuleTester` -- Fake parent module for testing dependency resolution
- `TestFileRegistry` -- Configurable fake for testing `FileRegistry` base class

**Test Config:** `tests/sitchco.config.php` registers the `ModuleTester` module with specific feature flags:
```php
return [
    'modules' => [
        ModuleTester::class => [
            'featureOne' => true,
            'featureTwo' => true,
            'featureThree' => false,
        ],
    ],
];
```

**Test Assets:** `tests/dist/.vite/manifest.json` contains a fake Vite build manifest for testing the asset pipeline without actual builds.

## Coverage

**Requirements:** None enforced. No coverage configuration in `phpunit.xml`.

**Excluded Groups:**
```xml
<groups>
    <exclude>
        <group>integration</group>
    </exclude>
</groups>
```

The `@group integration` annotation can mark slow/external-dependency tests for exclusion from the default suite.

## Test Types

**Integration Tests (Primary):**
- Most tests are integration tests using a real WordPress installation with database
- Tests exercise real WordPress hooks, options, cron, REST API
- DI container provides real module instances; specific dependencies are overridden with mocks

**Unit-style Tests:**
- `LoggerTest` -- Tests enum behavior in isolation
- `DateRangeTest`, `DateFormatTest` -- Tests value objects with no WordPress dependencies
- `CacheTest` -- Tests caching utilities (still uses WordPress `wp_cache_*`)

**No E2E/Browser Tests:** No Cypress, Playwright, or similar framework is configured.

## Common Patterns

**Testing WordPress Actions/Hooks:**
```php
// Verify an action dispatches correctly
$called = false;
add_action(Hooks::name('cron', 'minutely'), function () use (&$called) {
    $called = true;
});
do_action('sitchco_cron_minutely');
$this->assertTrue($called, 'Action should fire');
```

**Testing WordPress Options/State:**
```php
// Set up state
update_option(CacheQueue::OPTION_NAME, [
    ['slug' => 'object_cache', 'expires' => time() - 10, 'delay' => 10],
], false);

// Exercise
$this->queue->process();

// Verify
$remaining = get_option(CacheQueue::OPTION_NAME, []);
$this->assertCount(1, $remaining);
```

**Testing Module Initialization:**
```php
// Create module with mock dependencies injected via container
$this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
$module = new CacheInvalidation($this->queue, $this->container);
$module->init();

// Trigger the signal
do_action('sitchco/post/visibility_changed');

// Verify the result
$this->queue->flushWriteBuffer();
$slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
$this->assertSame(['wp_rocket', 'cloudfront', 'cloudflare'], $slugs);
```

**Testing Timber/Post Operations:**
```php
$post_id = $this->factory()->post->create(['post_title' => 'Test Post']);
$post = \Timber\Timber::get_post($post_id);
$this->assertInstanceOf(PostTester::class, $post);
$this->assertEquals('Test Post', $post->post_title);
```

**Testing Block Render Callbacks:**
```php
// Build block data
$block = [
    'name' => 'sitchco/test-block',
    'blockName' => 'sitchco/test-block',
    'path' => SITCHCO_CORE_TESTS_DIR . '/Fakes/ModuleTester/blocks/test-block',
    'return_context' => true,
];
WP_Block_Supports::$block_to_render = $block;

ob_start();
TimberModule::blockRenderCallback($block, $content, $isPreview, $postId, $wpBlock);
$output = ob_get_clean();

$context = maybe_unserialize($output);
$this->assertIsArray($context);
$this->assertArrayHasKey('block', $context);
```

**Testing REST Routes:**
```php
$this->service->addReadRoute('/example-read', fn() => $data);

$request = new WP_REST_Request('GET', '/sitchco/example-read');
$response = rest_do_request($request);

$this->assertEquals(200, $response->get_status());
$this->assertEquals($data, $response->get_data());
```

**Testing Exceptions:**
```php
$this->expectException(RedirectExitException::class);
$this->expectExceptionMessage('http://example.org/login/');
$route->processRoute();
```

**Assertion with Delta (Time-based):**
```php
$this->assertEqualsWithDelta($now + 100, $remaining[0]['expires'], 2);
```

**Assertion Messages:**
Include descriptive messages for non-obvious assertions:
```php
$this->assertTrue($completionFired, 'Completion hook should fire when queue is fully drained');
$this->assertSame('value2', $secondResult, 'Second flush should be guarded -- cache value should survive');
```

## Pre-existing Test Failures

Two known failing tests (as of 2026-02-08):
- `TimberModuleTest::test_blockRenderCallback_loads_metadata_and_sets_innerBlocksConfig` (error)
- `ModuleAssetsTest::test_inlineScript` (failure)

These are pre-existing and unrelated to recent work.

## Adding New Tests

1. Create test file in the directory matching the source structure under `tests/`
2. Extend `Sitchco\Tests\TestCase`
3. Use `$this->container->get(ClassName::class)` to get module/service instances
4. Use `$this->factory()` to create WordPress test data
5. Clean up state in `tearDown()` (delete options, remove hooks, restore container bindings)
6. Name test methods: `test_{scenario_description}` in snake_case
7. Group related tests with `// --- Group Name ---` comment dividers

---

*Testing analysis: 2026-03-09*
