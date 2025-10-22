# Feature Flags

## TL;DR

Modules declare optional features via `FEATURES` constant. Enable/disable per environment in `sitchco.config.php` without changing module code.

## Quick Example

**See:** [Common Patterns - Module with Feature Flags](common-patterns.md#6-module-with-feature-flags)

```php
public const FEATURES = ['customAdminColumn', 'emailNotifications'];

protected function customAdminColumn(): void
{
    // Only runs if enabled in config
}
```

**Enable in config:**
```php
return [
    'modules' => [
        EventModule::class => [
            'features' => ['customAdminColumn'],  // Enable only this
        ],
    ],
];
```

---

## How It Works

### Feature Execution Flow

| Step | Action |
|------|--------|
| 1 | Module declares `FEATURES` constant |
| 2 | Config enables/disables specific features |
| 3 | `init()` method always runs |
| 4 | Feature methods run only if enabled |

**Example:**
```
EventModule has 3 features, 2 enabled in config

Execution:
  1. Call init() → ✓ Always runs
  2. Check customAdminColumn in config → ✓ Enabled, call method
  3. Check emailNotifications in config → ✗ Disabled, skip
  4. Check exportCsv in config → ✓ Enabled, call method
```

---

## Feature Declaration

### 1. Declare Features

```php
class EventModule extends Module
{
    public const FEATURES = [
        'customAdminColumn',
        'emailNotifications',
        'exportCsv',
    ];
}
```

### 2. Implement Feature Methods

```php
protected function customAdminColumn(): void
{
    add_filter('manage_event_posts_columns', [$this, 'addColumn']);
}

protected function emailNotifications(): void
{
    add_action('publish_event', [$this, 'sendEmail']);
}

protected function exportCsv(): void
{
    add_action('admin_action_export_events', [$this, 'handleExport']);
}
```

**Requirements:**
- Methods must be `protected`
- Method names must match feature names exactly
- No parameters required

### 3. Enable in Config

```php
// sitchco.config.php
return [
    'modules' => [
        EventModule::class => [
            'features' => ['customAdminColumn', 'exportCsv'],
        ],
    ],
];
```

---

## Configuration Patterns

### Enable All Features

```php
EventModule::class => [
    'features' => true,  // Enable all declared features
],
```

### Enable Specific Features

```php
EventModule::class => [
    'features' => ['customAdminColumn', 'exportCsv'],
],
```

### Disable All Features

```php
EventModule::class => [
    // Omit 'features' key or set to false/empty array
],
```

### Environment-Specific

```php
// Local environment
EventModule::class => [
    'features' => ['debugMode', 'verboseLogging'],
],

// Production environment
EventModule::class => [
    'features' => ['caching', 'emailNotifications'],
],
```

---

## Common Use Cases

| Use Case | Example Feature | When to Use |
|----------|----------------|-------------|
| **Development tools** | `debugPanel`, `sqlLogging` | Local only |
| **Performance** | `caching`, `lazyLoading` | Production only |
| **Client-specific** | `customBranding`, `extraFields` | Per-site config |
| **Progressive rollout** | `newEditor`, `betaFeatures` | Gradual deployment |
| **Optional integrations** | `mailchimp`, `analytics` | External services |

---

## When to Use Feature Flags

### ✅ Use Feature Flags For:

- Optional functionality (not core to module)
- Environment-specific behavior (dev vs. prod)
- Client-specific customizations
- Experimental/beta features
- Performance optimizations (caching, etc.)
- External service integrations

### ❌ Don't Use Feature Flags For:

- Core functionality required by module
- Security features (always enable)
- Dependencies (use `DEPENDENCIES` instead)
- One-time setup (use `init()` instead)

---

## Real Examples

### Debug/Development Features

```php
public const FEATURES = ['queryMonitor', 'verboseErrors'];

protected function queryMonitor(): void
{
    add_action('shutdown', function () {
        global $wpdb;
        error_log('Queries: ' . $wpdb->num_queries);
    });
}
```

**Enable:** Local/staging only
```php
// local.sitchco.config.php
EventModule::class => ['features' => ['queryMonitor']],
```

### Performance Features

```php
public const FEATURES = ['caching', 'criticalCss'];

protected function caching(): void
{
    add_filter('event_query_results', [$this, 'cacheResults']);
}

protected function criticalCss(): void
{
    add_action('wp_head', function () {
        $css = file_get_contents(__DIR__ . '/dist/critical.css');
        echo "<style>{$css}</style>";
    });
}
```

**Enable:** Production only
```php
// production.sitchco.config.php
EventModule::class => ['features' => ['caching', 'criticalCss']],
```

### Client-Specific Features

```php
public const FEATURES = ['customReports', 'bulkExport', 'advancedSearch'];

protected function customReports(): void
{
    add_action('admin_menu', [$this, 'addReportsPage']);
}
```

**Enable:** Per-client
```php
// client-a.sitchco.config.php
EventModule::class => ['features' => ['customReports', 'bulkExport']],

// client-b.sitchco.config.php
EventModule::class => ['features' => ['advancedSearch']],
```

---

## Feature Flags vs. Other Patterns

| Pattern | Use For | Example |
|---------|---------|---------|
| **Feature Flags** | Optional module functionality | Email notifications, debug tools |
| **Dependencies** | Required modules | `TimberModule`, `ContentPartialModule` |
| **DI Constructor** | Required services | `EventRepository`, `GlobalSettings` |
| **Conditionals in init()** | WordPress state | `is_admin()`, `is_singular('event')` |

---

## Configuration Cascade

Feature flags respect the config cascade (core → parent → child):

```php
// Core: sitchco-core/sitchco.config.php
EventModule::class => [
    'features' => ['caching'],
],

// Parent: sitchco-parent-theme/sitchco.config.php
EventModule::class => [
    'features' => ['caching', 'customColumns'],
],

// Child: roundabout/sitchco.config.php
EventModule::class => [
    'features' => ['customColumns', 'exportCsv'],
],

// Result: ['customColumns', 'exportCsv'] (child wins)
```

**Child config overrides parent completely** - not merged.

---

## Debugging

### Check Which Features Are Enabled

```php
public function init(): void
{
    $enabled = $this->getEnabledFeatures();
    error_log('Enabled features: ' . implode(', ', $enabled));
}
```

### Verify Feature Method Exists

```php
public function init(): void
{
    foreach ($this->getEnabledFeatures() as $feature) {
        if (!method_exists($this, $feature)) {
            trigger_error("Feature method {$feature}() not found", E_USER_WARNING);
        }
    }
}
```

### Log Feature Execution

```php
protected function customAdminColumn(): void
{
    error_log('customAdminColumn feature executed');
    // Feature logic
}
```

---

## Complete Example

**See:** `examples/custom-post-type-module/EventModule.php` for working example

```php
<?php
namespace Sitchco\App\Modules\Event;

use Sitchco\Framework\Module;

class EventModule extends Module
{
    public const FEATURES = [
        'customAdminColumn',
        'emailNotifications',
        'exportCsv',
    ];

    public function init(): void
    {
        add_action('init', [$this, 'registerPostType']);
        // Feature methods called automatically if enabled
    }

    protected function customAdminColumn(): void
    {
        add_filter('manage_event_posts_columns', [$this, 'addColumn']);
    }

    protected function emailNotifications(): void
    {
        add_action('publish_event', [$this, 'sendEmail'], 10, 2);
    }

    protected function exportCsv(): void
    {
        add_action('admin_action_export_events', [$this, 'handleExport']);
    }

    public function registerPostType(): void
    {
        // Post type registration handled through ACF Pro UI
        // No PHP registration needed
    }

    public function addColumn($columns): array
    {
        $columns['event_date'] = 'Event Date';
        return $columns;
    }

    public function sendEmail($ID, $post): void
    {
        wp_mail(get_option('admin_email'), 'New Event', $post->post_title);
    }

    public function handleExport(): void
    {
        // CSV export logic
    }
}
```

**Config:**
```php
// Local
EventModule::class => ['features' => true],  // All features

// Production
EventModule::class => ['features' => ['customAdminColumn', 'emailNotifications']],
```

---

## Related

- [Common Patterns - Feature Flags](common-patterns.md#6-module-with-feature-flags)
- [Creating a Module](creating-a-module.md) - Module basics
- [Adding Dependencies](adding-dependencies.md) - DEPENDENCIES vs. features
- [Base Module API](../reference/base-module-api.md) - Complete API
