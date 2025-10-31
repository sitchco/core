# Testing Guide

## Philosophy

Our testing approach prioritizes **behavior over implementation**. We write tests that verify what the system does, not how it does it. This keeps tests maintainable, focused, and resistant to refactoring.

### Core Principles

1. **Test outcomes, not mechanisms** - Verify that features work, not that internal methods are called
2. **Integration over isolation** - Prefer testing through the container and real WordPress APIs
3. **Simplicity over coverage** - A few clear tests are better than many brittle ones
4. **Consistency with existing patterns** - Follow the style established in the codebase

## What to Test

### ✅ High-Value Tests

**Public API and Behavior**
```php
// GOOD: Tests actual behavior users/developers experience
public function test_module_blocks_are_registered(): void
{
    $this->assertTrue(
        WP_Block_Type_Registry::get_instance()->is_registered('sitchco/test-block'),
        'Blocks from module should be auto-registered via manifest'
    );
}
```

**Integration Points**
```php
// GOOD: Tests how components work together
function test_active_module_initialization()
{
    $ModuleInstance = $this->container->get(ModuleTester::class);
    $this->assertTrue($ModuleInstance->featureOneRan);
}
```

**Utility Functions with Complex Logic**
```php
// GOOD: Tests reusable utilities with edge cases
public function test_remember_option_with_ttl_respects_expiration_metadata(): void
{
    $result = Cache::rememberOption('key', fn() => 'value-one', 60);
    $stored = get_option('key');
    $this->assertArrayHasKey('__cache_meta', $stored);
}
```

**Data Transformations**
```php
// GOOD: Tests that data flows correctly through the system
public function testCollectionWrapsPostQueryCorrectly()
{
    $collection = new Collection($post_query);
    $this->assertEquals($post_query->to_array(), $collection->to_array());
}
```

### ❌ Low-Value Tests to Avoid

**Infrastructure Implementation Details**
```php
// BAD: Tests internal mechanism, not behavior
public function test_manifest_generates_hash(): void
{
    $manifest = $this->generator->generate($path);
    $this->assertArrayHasKey('hash', $manifest);
}
```
*Why avoid?* If the hash algorithm changes but blocks still register correctly, this test breaks unnecessarily.

**Bootstrap and Container Setup**
```php
// BAD: Duplicates what integration tests already verify
public function test_bootstrap_sets_global_container(): void
{
    $bootstrap->initialize();
    $this->assertNotNull($GLOBALS['SitchcoContainer']);
}
```
*Why avoid?* If the container doesn't work, every integration test fails. No need to test it explicitly.

**Trivial Getters/Setters**
```php
// BAD: No logic to test
public function test_get_name_returns_name(): void
{
    $obj->setName('Test');
    $this->assertEquals('Test', $obj->getName());
}
```
*Why avoid?* These break when you refactor to public properties. Test behavior that matters instead.

**Cache Implementation Details**
```php
// BAD: Tests internal cache mechanics
public function test_cache_cleared_on_manifest_regenerate(): void
{
    $registry->ensureFreshManifests();
    $this->assertFalse(wp_cache_get('sitchco_blocks_manifest', 'sitchco'));
}
```
*Why avoid?* Caching is an optimization detail. If blocks work correctly, the cache works correctly.

## Testing Patterns

### Pattern 1: Integration Tests Through Container

Our primary testing pattern uses the dependency injection container, just like the application does in production.

```php
class CoreMuPluginTest extends TestCase
{
    function test_registers_and_activates_a_module()
    {
        $loaded_config = $this->container->get(ConfigRegistry::class)->load('modules');
        $this->assertEquals([
            'featureOne' => true,
            'featureTwo' => true,
        ], $loaded_config[ModuleTester::class]);
    }
}
```

**When to use:**
- Testing module activation and configuration
- Verifying services are wired correctly
- Testing feature flags and module features

### Pattern 2: Static Fixtures for Reference Data

Use committed fixture files for stable, read-only test data.

```php
class ImageTest extends TestCase
{
    protected function setUp(): void
    {
        $this->attachment_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_FIXTURES_DIR . '/sample-image.jpg'
        );
    }
}
```

**When to use:**
- Need real files (images, JSON configs, etc.)
- Data doesn't change between test runs
- Multiple tests share the same fixtures

**Fixtures location:** `tests/fixtures/`

### Pattern 3: Factory-Generated Test Data

Use WordPress factories for dynamic test data.

```php
class AcfPostTypeTest extends TestCase
{
    protected function createPosts(): void
    {
        $this->factory()->post->create([
            'post_type' => $this->post_type,
            'post_title' => 'Test Post',
            'meta_input' => ['active' => '1'],
        ]);
    }
}
```

**When to use:**
- Need posts, terms, users, comments
- Data varies per test
- Testing queries and filters

### Pattern 4: Utility Function Testing

Test reusable utilities with clear inputs and outputs.

```php
class FilePathTest extends TestCase
{
    public function test_append_and_parent()
    {
        $fp = FilePath::create('/path/to/dir')->append('child.txt');
        $this->assertStringEndsWith('child.txt', $fp->value());
    }
}
```

**When to use:**
- Testing helper/utility classes
- Functions with edge cases or complex logic
- Code reused across multiple features

## File Organization

```
tests/
├── fixtures/              # Static test data
│   ├── sample-image.jpg
│   ├── acf-post-type.php
│   └── acf-taxonomy.php
├── Fakes/                 # Test doubles and stubs
│   └── ModuleTester/
├── Framework/             # Core framework tests
├── Modules/               # Module-specific tests
├── ModuleExtension/       # Extension tests
├── Support/               # Support utility tests
├── Utils/                 # Utility class tests
└── TestCase.php           # Base test class
```

## Test Isolation and Setup

### Base TestCase

All tests extend `Sitchco\Tests\TestCase`, which provides:
- Access to the DI container via `$this->container`
- WordPress factory for creating test data
- Helper methods like `fakeHttp()` and `restoreHttp()`

### Setup and Teardown

```php
class MyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // Always call parent::setUp()
        // Your setup code here
    }

    protected function tearDown(): void
    {
        // Your cleanup code here
        parent::tearDown(); // Always call parent::tearDown()
    }
}
```

**Important:** Always call parent methods to ensure proper WordPress test state management.

## When to Add New Tests

### Add tests when:

1. **Adding a new public API or feature** - Test the happy path and key edge cases
2. **Fixing a bug** - Add a test that would have caught the bug
3. **Adding complex utilities** - Test utilities that will be reused across the codebase
4. **Refactoring with behavior changes** - Verify the new behavior works as intended

### Don't add tests when:

1. **Code is already covered by integration tests** - If existing tests would catch breakage, skip it
2. **Testing private implementation details** - If you need to use reflection or test helpers to access something, don't test it
3. **Refactoring without behavior changes** - If behavior stays the same, existing tests should pass
4. **Testing third-party code** - Trust that WordPress, ACF, etc. have their own tests

## Common Pitfalls

### ❌ Over-Engineering Test Infrastructure

**Problem:**
```php
trait ManagesTestFiles {
    protected function createTempTestDir(string $prefix = 'test'): string { ... }
    protected function recursiveDelete(string $path): void { ... }
    protected function createBlockJson(string $dir, string $blockName): void { ... }
}
```

**Better approach:** Use static fixtures in `tests/fixtures/` or test through existing integration points.

### ❌ Testing Implementation Instead of Behavior

**Problem:**
```php
public function test_loads_manifest_from_multiple_paths(): void
{
    $paths = $registry->getBasePaths();
    $this->assertCount(3, $paths);
}
```

**Better approach:**
```php
public function test_blocks_from_modules_are_registered(): void
{
    $this->assertTrue(WP_Block_Type_Registry::get_instance()->is_registered('module/block'));
}
```

### ❌ Duplicating Coverage

**Problem:** Writing a dedicated bootstrap test when CoreMuPluginTest already verifies the container and modules load.

**Better approach:** Let integration tests provide implicit coverage of infrastructure.

## Running Tests

```bash
# Run all tests
make test

# Run specific test file
make test ARGS="--filter CoreMuPluginTest"

# Run specific test method
make test ARGS="--filter test_active_module_initialization"

# Run tests in a specific directory
vendor/bin/phpunit tests/Framework/
```

## Examples from the Codebase

### Good Example: CoreMuPluginTest

Tests high-level module behavior through the container, verifying the whole system works together.

**File:** `tests/CoreMuPluginTest.php`

### Good Example: CollectionTest

Tests that Collection properly wraps PostQuery with clear assertions about behavior.

**File:** `tests/CollectionTest.php`

### Good Example: ImageTest

Uses static fixtures for real image files, tests transformation behavior.

**File:** `tests/Model/ImageTest.php`

### Good Example: CacheTest

Tests utility functions with clear inputs/outputs and edge cases (TTL expiration).

**File:** `tests/Utils/CacheTest.php`

## Questions to Ask Before Writing a Test

1. **What behavior am I protecting?** - If the answer is vague, reconsider
2. **Would this test break during safe refactoring?** - If yes, it's too coupled to implementation
3. **Do existing tests already cover this?** - Check integration tests for implicit coverage
4. **Will this test be easy to understand in 6 months?** - Complexity is a smell
5. **Am I testing my code or WordPress/third-party code?** - Only test your own code

## Getting Help

When in doubt about whether to write a test:
1. Check for similar tests in the codebase
2. Ask: "What would break if I don't write this?"
3. Consider starting without the test - if bugs appear, add tests then
4. Discuss with the team during code review

---

*Remember: Tests are code too. Keep them simple, focused, and maintainable.*
