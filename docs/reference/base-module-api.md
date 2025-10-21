# Base Module API Reference

Complete API reference for the `Sitchco\Framework\Module` base class.

## Class: Module

**Location:** `src/Framework/Module.php`
**Namespace:** `Sitchco\Framework`
**Type:** Abstract class

All modules must extend this base class.

```php
abstract class Module
{
    // Constants
    public const DEPENDENCIES = [];
    public const FEATURES = [];
    public const POST_CLASSES = [];

    // Abstract/override methods
    public function init(): void {}

    // Path helpers
    public function path(string $relative = ''): FilePath
    public function assetsPath(): FilePath
    public function blocksPath(): FilePath

    // Asset management
    protected function enqueueGlobalAssets(callable $callable, int $priority = 10): void
    protected function enqueueAdminAssets(callable $callable, int $priority = 10): void
    protected function enqueueFrontendAssets(callable $callable, int $priority = 10): void
    protected function enqueueEditorPreviewAssets(callable $callable, int $priority = 10): void
    protected function enqueueEditorUIAssets(callable $callable, int $priority = 10): void
    protected function registerAssets(callable $callable, int $priority = 20): void
    protected function enqueueBlockStyles(callable $callable, int $priority = 30): void

    // Block utilities
    public function filterBlockAssets(array $blocksConfig): void

    // Inherited from HasHooks trait
    protected function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    protected function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
}
```

---

## Constants

### DEPENDENCIES

```php
public const DEPENDENCIES = [];
```

**Type:** `array<class-string>`

**Description:** Array of module class names that must be loaded before this module.

**Usage:**
```php
class EventModule extends Module
{
    public const DEPENDENCIES = [
        TimberModule::class,
        BlockConfigModule::class,
    ];
}
```

**Details:**
- The `ModuleRegistry` resolves dependencies recursively
- Dependencies are instantiated and initialized before this module
- Circular dependencies are detected and throw errors
- Transitive dependencies are automatically resolved

**See also:** [Adding Dependencies Guide](../guides/adding-dependencies.md)

---

### FEATURES

```php
public const FEATURES = [];
```

**Type:** `array<string>`

**Description:** Array of optional feature method names that can be enabled via config.

**Usage:**
```php
class EventModule extends Module
{
    public const FEATURES = [
        'customAdminColumn',
        'emailNotifications',
    ];

    protected function customAdminColumn(): void
    {
        // Runs only if enabled in config
    }

    protected function emailNotifications(): void
    {
        // Runs only if enabled in config
    }
}
```

**Details:**
- Feature methods must be `protected` (not public or private)
- Method names must match entries in FEATURES array (case-sensitive)
- Enable features in `sitchco.config.php`:
  ```php
  MyModule::class => [
      'customAdminColumn' => true,
      'emailNotifications' => false,
  ]
  ```

**See also:** [Feature Flags Guide](../guides/feature-flags.md)

---

### POST_CLASSES

```php
public const POST_CLASSES = [];
```

**Type:** `array<class-string>`

**Description:** Array of Timber post class names to register with Timber's classmap.

**Usage:**
```php
class EventModule extends Module
{
    public const DEPENDENCIES = [TimberModule::class];

    public const POST_CLASSES = [
        EventPost::class,
        VenuePost::class,
    ];
}
```

**Details:**
- Automatically registered with `timber/post/classmap` filter
- Post classes should extend `Timber\Post`
- Module must depend on `TimberModule::class`

**Example Post Class:**
```php
namespace Sitchco\App\Modules\Event;

use Timber\Post;

class EventPost extends Post
{
    public function startDate(): string
    {
        return get_field('start_date', $this->ID);
    }
}
```

---

## Methods

### init()

```php
public function init(): void
```

**Description:** Initialization method called for all modules during the initialization pass.

**When called:** During `after_setup_theme` hook at priority 5 (after the extension pass, before feature methods).

**IMPORTANT TIMING:**
- This method is called during **`after_setup_theme` at priority 5**
- It is **NOT** called during WordPress's `init` hook
- WordPress's `init` hook fires **later** at priority 10
- Despite the name similarity, these are different hooks at different times
- Use this method to **REGISTER** hooks/filters, not to execute code directly

**Usage:**
```php
public function init(): void
{
    // Register for WordPress's 'init' hook (fires later)
    add_action('init', [$this, 'registerPostType']);

    // Register other hooks
    add_filter('the_content', [$this, 'filterContent']);

    // Enqueue assets (registers on appropriate hooks)
    $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
        $assets->style('main.css');
    });
}

// This method is called later when WordPress fires 'init' hook
public function registerPostType(): void
{
    register_post_type('event', [ /* ... */ ]);
}
```

**Details:**
- Always called, regardless of feature flags
- Use for registering post types, taxonomies, hooks, etc.
- Can be empty if module only uses feature methods
- Called after DI container has resolved all dependencies
- Called during the ModuleRegistry initialization pass

---

### path()

```php
public function path(string $relative = ''): FilePath
```

**Parameters:**
- `$relative` (string, optional): Relative path to append

**Returns:** `FilePath` object

**Description:** Get the filesystem path to the module's directory or a subdirectory.

**Usage:**
```php
// Module directory
$moduleDir = $this->path();
echo $moduleDir; // /path/to/modules/MyModule

// Subdirectory
$templatesDir = $this->path('templates');
echo $templatesDir; // /path/to/modules/MyModule/templates

// File
$configFile = $this->path('config.php');
echo $configFile; // /path/to/modules/MyModule/config.php
```

**Details:**
- Returns `FilePath` object (can be cast to string)
- Automatically resolves from class location via reflection
- Cached after first call
- Use for loading templates, configs, or other module files

---

### assetsPath()

```php
public function assetsPath(): FilePath
```

**Returns:** `FilePath` object pointing to `modules/{Module}/assets`

**Description:** Get the path to the module's assets directory.

**Usage:**
```php
$assetsDir = $this->assetsPath();
echo $assetsDir; // /path/to/modules/MyModule/assets
```

**Details:**
- Equivalent to `$this->path('assets')`
- Used internally by `ModuleAssets` class
- Points to source assets (development) or built assets (production) depending on environment

---

### blocksPath()

```php
public function blocksPath(): FilePath
```

**Returns:** `FilePath` object pointing to `modules/{Module}/blocks`

**Description:** Get the path to the module's blocks directory.

**Usage:**
```php
// Register block from blocks directory
register_block_type($this->blocksPath('my-block'));

// Full path
$blocksDir = $this->blocksPath();
echo $blocksDir; // /path/to/modules/MyModule/blocks
```

**Details:**
- Equivalent to `$this->path('blocks')`
- Used for Gutenberg block registration
- Expects blocks to be in subdirectories with `block.json`

---

### enqueueGlobalAssets()

```php
protected function enqueueGlobalAssets(callable $callable, int $priority = 10): void
```

**Parameters:**
- `$callable` (callable): Callback receiving `ModuleAssets` instance
- `$priority` (int, optional): WordPress hook priority (default: 10)

**Hook:** `enqueue_block_assets`

**Description:** Enqueue assets in all contexts (frontend, admin, block editor).

**Usage:**
```php
public function init(): void
{
    $this->enqueueGlobalAssets(function (ModuleAssets $assets) {
        $assets->style('global.css');
        $assets->script('global.js');
    });
}
```

**When to use:**
- Styles/scripts needed everywhere
- Icon fonts, base utilities
- Rarely needed - prefer context-specific methods

---

### enqueueAdminAssets()

```php
protected function enqueueAdminAssets(callable $callable, int $priority = 10): void
```

**Parameters:**
- `$callable` (callable): Callback receiving `ModuleAssets` instance
- `$priority` (int, optional): WordPress hook priority (default: 10)

**Hook:** `admin_enqueue_scripts`

**Description:** Enqueue assets only in WordPress admin dashboard.

**Usage:**
```php
public function init(): void
{
    $this->enqueueAdminAssets(function (ModuleAssets $assets) {
        $assets->style('admin.css');
        $assets->script('admin.js', dependencies: ['jquery']);
    });
}
```

**When to use:**
- Admin-only functionality
- Custom admin columns, meta boxes
- Admin UI enhancements

---

### enqueueFrontendAssets()

```php
protected function enqueueFrontendAssets(callable $callable, int $priority = 10): void
```

**Parameters:**
- `$callable` (callable): Callback receiving `ModuleAssets` instance
- `$priority` (int, optional): WordPress hook priority (default: 10)

**Hook:** `wp_enqueue_scripts`

**Description:** Enqueue assets only on the frontend (public-facing pages).

**Usage:**
```php
public function init(): void
{
    $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
        $assets->style('main.css');
        $assets->script('main.js');
    });
}
```

**When to use:**
- Public-facing styles and scripts
- Interactive components
- Frontend functionality

**Most common:** This is the most frequently used asset method.

---

### enqueueEditorPreviewAssets()

```php
protected function enqueueEditorPreviewAssets(callable $callable, int $priority = 10): void
```

**Parameters:**
- `$callable` (callable): Callback receiving `ModuleAssets` instance
- `$priority` (int, optional): WordPress hook priority (default: 10)

**Hook:** `enqueue_block_assets` (admin only)

**Description:** Enqueue assets for block editor preview (editor canvas, not toolbar).

**Usage:**
```php
public function init(): void
{
    $this->enqueueEditorPreviewAssets(function (ModuleAssets $assets) {
        $assets->style('editor-preview.css');
    });
}
```

**When to use:**
- Styles that should match frontend in editor canvas
- Preview-specific styling adjustments
- Custom block preview styles

---

### enqueueEditorUIAssets()

```php
protected function enqueueEditorUIAssets(callable $callable, int $priority = 10): void
```

**Parameters:**
- `$callable` (callable): Callback receiving `ModuleAssets` instance
- `$priority` (int, optional): WordPress hook priority (default: 10)

**Hook:** `enqueue_block_editor_assets`

**Description:** Enqueue assets for block editor UI (sidebar, toolbar, panels).

**Usage:**
```php
public function init(): void
{
    $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
        $assets->script('editor.js', dependencies: [
            'wp-blocks',
            'wp-element',
            'wp-components',
        ]);
        $assets->style('editor.css');
    });
}
```

**When to use:**
- Custom Gutenberg blocks
- Block editor plugins
- Editor UI modifications

---

### registerAssets()

```php
protected function registerAssets(callable $callable, int $priority = 20): void
```

**Parameters:**
- `$callable` (callable): Callback receiving `ModuleAssets` instance
- `$priority` (int, optional): WordPress hook priority (default: 20)

**Hook:** `init`

**Description:** Register assets without enqueueing (make available for later use).

**Usage:**
```php
public function init(): void
{
    // Register
    $this->registerAssets(function (ModuleAssets $assets) {
        $assets->script('modal.js', dependencies: ['jquery']);
    });

    // Enqueue conditionally later
    add_action('wp_enqueue_scripts', function () {
        if (has_block('sitchco/modal')) {
            wp_enqueue_script('modal');
        }
    });
}
```

**When to use:**
- Assets that are conditionally enqueued
- Shared assets used by multiple contexts
- Assets enqueued by blocks or shortcodes

---

### enqueueBlockStyles()

```php
protected function enqueueBlockStyles(callable $callable, int $priority = 30): void
```

**Parameters:**
- `$callable` (callable): Callback receiving `ModuleAssets` instance
- `$priority` (int, optional): WordPress hook priority (default: 30)

**Hook:** `init`

**Description:** Register block-specific styles using `wp_enqueue_block_style()`.

**Usage:**
```php
public function init(): void
{
    $this->enqueueBlockStyles(function (ModuleAssets $assets) {
        wp_enqueue_block_style('core/button', [
            'handle' => 'custom-button-style',
            'src' => $assets->styleUrl('custom-button.css'),
        ]);
    });
}
```

**When to use:**
- Styles for specific core blocks
- Block variations
- Alternative block styles

---

### filterBlockAssets()

```php
public function filterBlockAssets(array $blocksConfig): void
```

**Parameters:**
- `$blocksConfig` (array): Array of block configurations

**Description:** Filter block metadata to customize asset loading.

**Usage:**
```php
public function init(): void
{
    $this->filterBlockAssets([
        'my-block' => [
            'editorScript' => 'editor.js',
            'style' => 'style.css',
        ],
    ]);
}
```

**Details:**
- Modifies `block_type_metadata` filter
- Used internally by block registration
- Advanced use case - most blocks use `block.json` instead

---

## Inherited Methods (HasHooks Trait)

### addAction()

```php
protected function addAction(
    string $hook,
    callable $callback,
    int $priority = 10,
    int $acceptedArgs = 1
): void
```

**Description:** Register a WordPress action hook.

**Usage:**
```php
public function init(): void
{
    $this->addAction('init', [$this, 'registerPostType']);
    $this->addAction('save_post', [$this, 'saveMetaData'], 10, 2);
}
```

**Details:**
- Wrapper around `add_action()`
- Hooks are automatically removed when module is deactivated
- Tracked internally for cleanup

---

### addFilter()

```php
protected function addFilter(
    string $hook,
    callable $callback,
    int $priority = 10,
    int $acceptedArgs = 1
): void
```

**Description:** Register a WordPress filter hook.

**Usage:**
```php
public function init(): void
{
    $this->addFilter('the_content', [$this, 'filterContent']);
    $this->addFilter('post_type_link', [$this, 'customPermalink'], 10, 2);
}
```

**Details:**
- Wrapper around `add_filter()`
- Hooks are automatically removed when module is deactivated
- Tracked internally for cleanup

---

## Protected Properties

### $assets

```php
private ModuleAssets $assets;
```

**Type:** `ModuleAssets`

**Access:** Via `$this->assets()` method

**Description:** Instance of `ModuleAssets` helper for managing CSS/JS.

**Usage:**
```php
protected function assets(): ModuleAssets
{
    return $this->assets;
}
```

---

## Complete Example

```php
<?php

namespace Sitchco\App\Modules\Event;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\PostModel\TimberModule;

class EventModule extends Module
{
    // Dependencies
    public const DEPENDENCIES = [TimberModule::class];

    // Optional features
    public const FEATURES = [
        'customAdminColumn',
        'emailNotifications',
    ];

    // Timber post classes
    public const POST_CLASSES = [EventPost::class];

    // Injected dependencies
    public function __construct(
        private EventRepository $repository
    ) {}

    // Always called
    public function init(): void
    {
        // Register post type
        add_action('init', [$this, 'registerPostType']);

        // Frontend assets
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->style('events.css');
            $assets->script('events.js', dependencies: ['jquery']);
        });

        // Admin assets
        $this->enqueueAdminAssets(function (ModuleAssets $assets) {
            $assets->script('admin.js');
        });

        // Register blocks
        add_action('init', [$this, 'registerBlocks']);
    }

    public function registerPostType(): void
    {
        register_post_type('event', [
            'label' => 'Events',
            'public' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
        ]);
    }

    public function registerBlocks(): void
    {
        register_block_type($this->blocksPath('event-list'));
    }

    // Feature method (only runs if enabled)
    protected function customAdminColumn(): void
    {
        add_filter('manage_event_posts_columns', [$this, 'addColumns']);
        add_action('manage_event_posts_custom_column', [$this, 'renderColumn'], 10, 2);
    }

    // Feature method (only runs if enabled)
    protected function emailNotifications(): void
    {
        add_action('publish_event', [$this, 'sendNotification']);
    }

    public function addColumns(array $columns): array
    {
        $columns['event_date'] = 'Event Date';
        return $columns;
    }

    public function renderColumn(string $column, int $postId): void
    {
        if ($column === 'event_date') {
            echo get_field('start_date', $postId);
        }
    }

    public function sendNotification(\WP_Post $post): void
    {
        // Send email notification
        wp_mail(
            get_option('admin_email'),
            'New Event Published',
            'A new event was published: ' . $post->post_title
        );
    }
}
```

---

## Related

- [Creating a Module Guide](../guides/creating-a-module.md)
- [Adding Dependencies Guide](../guides/adding-dependencies.md)
- [Feature Flags Guide](../guides/feature-flags.md)
- [Asset Management Guide](../guides/asset-management.md)
- [Core Modules Reference](core-modules.md)
