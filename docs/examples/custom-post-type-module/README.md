# Custom Post Type Module Example

A comprehensive example demonstrating custom post type registration with Timber integration, repository pattern, and admin customizations.

## What This Example Shows

- Custom post type registration
- Timber post class integration
- Repository pattern for data access
- Feature flags for optional functionality
- ACF field integration
- Admin column customization
- Asset management (frontend and admin)

## Files

- `EventModule.php` - Main module class
- `EventPost.php` - Timber post class
- `EventRepository.php` - Data access layer
- `config-example.php` - Configuration example

## Features Demonstrated

### Core Functionality
- Custom post type registration (`event`)
- Timber integration with custom post class
- Repository pattern for querying events

### Optional Features (via Feature Flags)
- `customAdminColumn` - Show event date in admin list
- `emailNotifications` - Send email when event is published

## Usage

### 1. Copy to Your Theme

```bash
cp -r custom-post-type-module /path/to/your-theme/modules/Event
cd /path/to/your-theme/modules/Event
```

### 2. Rename Files

```bash
# Rename module file
mv EventModule.php EventModule.php  # Keep as-is or rename

# If you want a different post type (e.g., "product"):
mv EventModule.php ProductModule.php
mv EventPost.php ProductPost.php
mv EventRepository.php ProductRepository.php
```

### 3. Update Code

If you renamed to a different post type, update:

**In ProductModule.php:**
```php
namespace Sitchco\App\Modules\Product;

class ProductModule extends Module
{
    public const POST_CLASSES = [ProductPost::class];

    public function registerPostType(): void
    {
        register_post_type('product', [  // Change 'event' to 'product'
            'label' => 'Products',
            'public' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
        ]);
    }
}
```

**In ProductPost.php:**
```php
namespace Sitchco\App\Modules\Product;

class ProductPost extends Post
{
    // Your custom methods
}
```

**In ProductRepository.php:**
```php
namespace Sitchco\App\Modules\Product;

class ProductRepository
{
    public function findAll(): array
    {
        return get_posts([
            'post_type' => 'product',  // Change here
            'posts_per_page' => -1,
        ]);
    }
}
```

### 4. Register in Config

Add to your theme's `sitchco.config.php`:

```php
return [
    'modules' => [
        // Basic (no features enabled)
        \Sitchco\App\Modules\Event\EventModule::class,

        // Or with features enabled
        \Sitchco\App\Modules\Event\EventModule::class => [
            'customAdminColumn' => true,
            'emailNotifications' => true,
        ],
    ],
];
```

### 5. Add ACF Fields (Optional)

If using ACF:

1. Create field group for your post type in ACF
2. Set location rule: Post Type = event
3. Add fields (e.g., `start_date`, `end_date`, `location`)
4. Export to JSON (saved to `acf-json/` directory)

### 6. Create Assets (Optional)

```bash
mkdir assets
touch assets/event.css
touch assets/event.js
touch assets/admin.css
touch assets/admin.js
```

## Customization Examples

### Add More Custom Methods to Post Class

```php
// In EventPost.php
public function isUpcoming(): bool
{
    return strtotime($this->startDate()) > time();
}

public function isPast(): bool
{
    return strtotime($this->startDate()) < time();
}

public function formattedDate(): string
{
    return date('F j, Y', strtotime($this->startDate()));
}
```

### Add Repository Methods

```php
// In EventRepository.php
public function findUpcoming(int $limit = 10): array
{
    return get_posts([
        'post_type' => 'event',
        'posts_per_page' => $limit,
        'meta_key' => 'start_date',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => 'start_date',
                'value' => date('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE',
            ],
        ],
    ]);
}

public function findByCategory(string $category): array
{
    return get_posts([
        'post_type' => 'event',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'event_category',
                'field' => 'slug',
                'terms' => $category,
            ],
        ],
    ]);
}
```

### Add More Feature Flags

```php
// In EventModule.php
public const FEATURES = [
    'customAdminColumn',
    'emailNotifications',
    'calendarView',       // NEW
    'icsExport',          // NEW
];

protected function calendarView(): void
{
    // Add calendar view to admin
}

protected function icsExport(): void
{
    // Add ICS export functionality
}
```

## Using the Post Class in Templates

```php
// In your Timber template
$context = Timber::context();
$context['events'] = Timber::get_posts([
    'post_type' => 'event',
    'posts_per_page' => 10,
]);

Timber::render('events.twig', $context);
```

```twig
{# In events.twig #}
{% for event in events %}
    <article class="event">
        <h2>{{ event.title }}</h2>
        <p>Date: {{ event.startDate }}</p>
        <p>Location: {{ event.location }}</p>
        {% if event.isUpcoming %}
            <span class="badge upcoming">Upcoming</span>
        {% endif %}
    </article>
{% endfor %}
```

## Using the Repository

```php
// Inject repository in another class
class EventListingController
{
    public function __construct(
        private EventRepository $eventRepo
    ) {}

    public function getUpcoming(): array
    {
        return $this->eventRepo->findUpcoming(5);
    }
}

// Or use directly in template
$repo = new EventRepository();
$upcomingEvents = $repo->findUpcoming();
```

## Next Steps

- Add taxonomy (e.g., event categories)
- Create custom Gutenberg block for events
- Add frontend templates
- Implement search and filtering
- Add calendar integration

## Related Documentation

- [Creating a Module](../../guides/creating-a-module.md)
- [Adding Dependencies](../../guides/adding-dependencies.md)
- [Feature Flags](../../guides/feature-flags.md)
- [Asset Management](../../guides/asset-management.md)
