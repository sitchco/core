# Core Modules Reference

Complete list of modules provided by sitchco-core (mu-plugin).

**Location:** `wp-content/mu-plugins/sitchco-core/modules/`
**Config:** `wp-content/mu-plugins/sitchco-core/sitchco.config.php`

## Module Categories

- [WordPress Enhancement](#wordpress-enhancement)
- [Models & Data](#models--data)
- [Advanced Custom Fields (ACF)](#advanced-custom-fields-acf)
- [Performance & Optimization](#performance--optimization)
- [UI & Utilities](#ui--utilities)
- [Infrastructure](#infrastructure)

---

## WordPress Enhancement

### Cleanup

**Class:** `Sitchco\Modules\Wordpress\Cleanup`
**File:** `modules/Wordpress/Cleanup.php`

Removes unnecessary WordPress features and cleans up the admin interface.

**Features:**
- Disable emoji scripts
- Remove WordPress version from head
- Clean up wp_head()
- Disable pingbacks
- Remove REST API links
- Clean up admin interface

**Common config:**
```php
Cleanup::class => [
    'disableEmojis' => true,
    'disableGutenbergStyles' => false,
]
```

---

### SearchRewrite

**Class:** `Sitchco\Modules\Wordpress\SearchRewrite`
**File:** `modules/Wordpress/SearchRewrite.php`

Improves search URL structure (e.g., `/search/query` instead of `/?s=query`).

**Purpose:**
- SEO-friendly search URLs
- Better analytics tracking
- Cleaner user experience

---

### SvgUpload

**Class:** `Sitchco\Modules\Wordpress\SvgUpload`
**File:** `modules/Wordpress/SvgUpload.php`

Enables SVG file uploads in WordPress media library.

**Features:**
- Adds SVG MIME type support
- Sanitizes SVG files for security
- Displays SVG previews in media library

**Security:** Sanitizes uploaded SVG files to prevent XSS attacks.

---

### BlockConfig

**Class:** `Sitchco\Modules\Wordpress\BlockConfig`
**File:** `modules/Wordpress/BlockConfig.php`

Manages Gutenberg block registration and configuration.

**Purpose:**
- Register custom blocks
- Configure block settings
- Manage block patterns
- Handle block category organization

**Dependency for:** Modules registering custom Gutenberg blocks.

---

## Models & Data

### PostModel

**Class:** `Sitchco\Modules\Model\PostModel` (aliased as `TimberModule`)
**File:** `modules/Model/PostModel.php`

Integrates Timber for advanced post models.

**Features:**
- Custom post class mapping
- Timber context setup
- Post model utilities
- Template rendering

**Usage:**
```php
class EventModule extends Module
{
    public const DEPENDENCIES = [TimberModule::class];
    public const POST_CLASSES = [EventPost::class];
}
```

**Most commonly depended upon** by modules creating custom post types.

---

### TermModel

**Class:** `Sitchco\Modules\Model\TermModel`
**File:** `modules/Model/TermModel.php`

Provides Timber integration for taxonomy terms.

**Features:**
- Custom term class mapping
- Term model utilities
- Taxonomy helpers

---

### ImageModel

**Class:** `Sitchco\Modules\Model\ImageModel`
**File:** `modules/Model/ImageModel.php`

Enhanced image handling and utilities.

**Features:**
- Advanced image processing
- Responsive image helpers
- Image optimization utilities

---

## Advanced Custom Fields (ACF)

### AcfPostTypeQueries

**Class:** `Sitchco\Modules\AdvancedCustomFields\AcfPostTypeQueries`
**File:** `modules/AdvancedCustomFields/AcfPostTypeQueries.php`

Enables querying posts by ACF field values.

**Features:**
- Query posts by custom field values
- Advanced WP_Query integration
- Meta query helpers

---

### AcfPostTypeAdminColumns

**Class:** `Sitchco\Modules\AdvancedCustomFields\AcfPostTypeAdminColumns`
**File:** `modules/AdvancedCustomFields/AcfPostTypeAdminColumns.php`

Adds ACF field values as columns in post type admin lists.

**Features:**
- Display ACF fields in admin columns
- Sortable ACF columns
- Custom column rendering

**Usage:** Configure which fields appear as columns via ACF field settings.

---

### AcfPostTypeAdminSort

**Class:** `Sitchco\Modules\AdvancedCustomFields\AcfPostTypeAdminSort`
**File:** `modules/AdvancedCustomFields/AcfPostTypeAdminSort.php`

Enables sorting post lists by ACF field values.

**Features:**
- Sort admin lists by ACF fields
- Custom sort handlers for different field types
- Integration with admin columns

---

### AcfPostTypeAdminFilters

**Class:** `Sitchco\Modules\AdvancedCustomFields\AcfPostTypeAdminFilters`
**File:** `modules/AdvancedCustomFields/AcfPostTypeAdminFilters.php`

Adds dropdown filters for ACF fields in admin post lists.

**Features:**
- Filter posts by ACF field values
- Custom filter dropdowns
- Select, checkbox, and taxonomy filters

---

### AcfOptions

**Class:** `Sitchco\Modules\AdvancedCustomFields\AcfOptions`
**File:** `modules/AdvancedCustomFields/AcfOptions.php`

Manages ACF options pages.

**Features:**
- Register ACF options pages
- Site-wide settings management
- Option page utilities

**Dependency for:** Modules creating theme options or global settings.

---

## Performance & Optimization

### WPRocket

**Class:** `Sitchco\Modules\WPRocket`
**File:** `modules/WPRocket.php`

Integration with WP Rocket caching plugin.

**Features:**
- Cache configuration
- Preload settings
- Exclusion rules
- Performance optimizations

**Requires:** WP Rocket plugin installed.

---

### Imagify

**Class:** `Sitchco\Modules\Imagify`
**File:** `modules/Imagify.php`

Integration with Imagify image optimization.

**Features:**
- Automatic image compression
- WebP conversion
- Lazy loading configuration
- Bulk optimization

**Requires:** Imagify plugin installed.

---

### YoastSEO

**Class:** `Sitchco\Modules\YoastSEO`
**File:** `modules/YoastSEO.php`

Integration with Yoast SEO plugin.

**Features:**
- SEO configuration
- Schema customization
- Sitemap enhancements
- Meta tag management

**Requires:** Yoast SEO plugin installed.

---

### AmazonCloudfront

**Class:** `Sitchco\Modules\AmazonCloudfront`
**File:** `modules/AmazonCloudfront.php`

CDN integration with Amazon CloudFront.

**Features:**
- Asset URL rewriting
- Cache invalidation
- CDN configuration
- Performance optimization

**Requires:** CloudFront configuration in wp-config.php.

---

### Stream

**Class:** `Sitchco\Modules\Stream`
**File:** `modules/Stream.php`

Integration with Stream plugin for activity logging.

**Features:**
- Activity logging configuration
- Custom connectors
- Log exclusions
- Admin activity tracking

**Requires:** Stream plugin installed.

---

## UI & Utilities

### UIFramework

**Class:** `Sitchco\Modules\UIFramework\UIFramework`
**File:** `modules/UIFramework/UIFramework.php`

Provides base UI framework styles and components.

**Features:**
- Base CSS framework
- Reusable UI components
- Typography system
- Utility classes

**Provides:** Foundation for theme styling.

---

### Flash

**Class:** `Sitchco\Modules\Flash`
**File:** `modules/Flash.php`

Flash message system for user notifications.

**Features:**
- Session-based flash messages
- Success, error, warning, info messages
- Template helpers
- Auto-dismiss functionality

**Usage:**
```php
use Sitchco\Flash\Flash;

Flash::success('Settings saved!');
Flash::error('An error occurred.');
```

---

### SvgSprite

**Class:** `Sitchco\Modules\SvgSprite`
**File:** `modules/SvgSprite.php`

SVG sprite generation and management.

**Features:**
- Combine SVGs into sprite sheet
- Icon system
- Template helpers for icons
- Automatic sprite generation

**Usage:**
```php
// In templates
echo svg_icon('icon-name', 'class-name');
```

---

### AdminTools

**Class:** `Sitchco\Modules\AdminTools`
**File:** `modules/AdminTools.php`

Admin interface enhancements and utilities.

**Features:**
- Admin UI improvements
- Quick links
- Utility functions for admin
- Developer tools

---

### PageOrder

**Class:** `Sitchco\Modules\PageOrder`
**File:** `modules/PageOrder.php`

Drag-and-drop page ordering in admin.

**Features:**
- Visual page reordering
- Hierarchical page sorting
- Menu order management
- Ajax-based reordering

---

## Infrastructure

### BackgroundProcessing

**Class:** `Sitchco\Modules\BackgroundProcessing`
**File:** `modules/BackgroundProcessing.php`

Async task processing system.

**Features:**
- Queue-based background jobs
- Long-running task support
- Progress tracking
- Error handling and retries

**Use cases:**
- Bulk data imports
- Email sending
- Image processing
- API synchronization

**Usage:**
```php
use Sitchco\BackgroundProcessing\BackgroundProcess;

class MyBackgroundProcess extends BackgroundProcess
{
    protected function task($item)
    {
        // Process $item
        return false; // or modified item
    }
}
```

---

## Module Quick Reference

| Module | Category | Common Dependency | Purpose |
|--------|----------|-------------------|---------|
| `PostModel` (TimberModule) | Models | **Yes** | Timber post integration |
| `BlockConfig` | WordPress | **Yes** | Gutenberg block registration |
| `AcfOptions` | ACF | Yes | Options pages |
| `Cleanup` | WordPress | No | Clean up WordPress |
| `SearchRewrite` | WordPress | No | SEO-friendly search |
| `SvgUpload` | WordPress | No | Enable SVG uploads |
| `TermModel` | Models | No | Timber term integration |
| `ImageModel` | Models | No | Image utilities |
| `AcfPostTypeQueries` | ACF | No | Query by ACF fields |
| `AcfPostTypeAdminColumns` | ACF | No | ACF admin columns |
| `AcfPostTypeAdminSort` | ACF | No | Sort by ACF fields |
| `AcfPostTypeAdminFilters` | ACF | No | Filter by ACF fields |
| `WPRocket` | Performance | No | Caching configuration |
| `Imagify` | Performance | No | Image optimization |
| `YoastSEO` | Performance | No | SEO configuration |
| `AmazonCloudfront` | Performance | No | CDN integration |
| `Stream` | Performance | No | Activity logging |
| `UIFramework` | UI | No | Base CSS framework |
| `Flash` | UI | No | Flash messages |
| `SvgSprite` | UI | No | SVG sprite system |
| `AdminTools` | UI | No | Admin enhancements |
| `PageOrder` | UI | No | Drag-drop page order |
| `BackgroundProcessing` | Infrastructure | No | Async task queue |

---

## Overriding Core Module Config

Parent and child themes can override core module configuration:

```php
// Parent theme sitchco.config.php
return [
    'modules' => [
        Cleanup::class => [
            'disableEmojis' => true,
            'disableGutenbergStyles' => false,
        ],
        WPRocket::class => [
            'enableLazyLoad' => true,
        ],
    ],
];
```

**Config merge order:** Core → Parent → Child (child wins).

---

## Adding Dependencies on Core Modules

```php
use Sitchco\Framework\Module;
use Sitchco\Modules\Model\PostModel as TimberModule;
use Sitchco\Modules\Wordpress\BlockConfig;

class MyModule extends Module
{
    public const DEPENDENCIES = [
        TimberModule::class,
        BlockConfig::class,
    ];
}
```

---

## Related

- [Base Module API Reference](base-module-api.md)
- [Adding Dependencies Guide](../guides/adding-dependencies.md)
- [Creating a Module Guide](../guides/creating-a-module.md)
- [Architecture Overview](../architecture/overview.md)
