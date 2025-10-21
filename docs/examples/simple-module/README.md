# Simple Module Example

A minimal module demonstrating basic structure and functionality.

## What This Example Shows

- Basic module structure
- Asset enqueueing (CSS and JS)
- WordPress hook registration
- Simple filter implementation

## Files

- `SimpleModule.php` - Main module class
- `config-example.php` - Configuration example
- `assets/main.css` - Example stylesheet
- `assets/main.js` - Example JavaScript

## Usage

### 1. Copy to Your Theme

```bash
cp -r simple-module /path/to/your-theme/modules/MyFeature
cd /path/to/your-theme/modules/MyFeature
```

### 2. Rename Files

```bash
mv SimpleModule.php MyFeatureModule.php
```

### 3. Update Namespace and Class Name

Edit `MyFeatureModule.php`:

```php
// Change namespace
namespace Sitchco\App\Modules\MyFeature;

// Change class name
class MyFeatureModule extends Module
```

### 4. Register in Config

Add to your theme's `sitchco.config.php`:

```php
return [
    'modules' => [
        \Sitchco\App\Modules\MyFeature\MyFeatureModule::class,
    ],
];
```

### 5. Create Assets

Create your module's assets:

```bash
mkdir assets
touch assets/main.css
touch assets/main.js
```

### 6. Test

Visit your site - the module should be active and assets should load.

## Customization

### Add More Hooks

```php
public function init(): void
{
    add_action('init', [$this, 'customInit']);
    add_filter('the_title', [$this, 'customizeTitle']);
}

public function customInit(): void
{
    // Your initialization code
}

public function customizeTitle(string $title): string
{
    return $title;
}
```

### Conditional Asset Loading

```php
$this->enqueueFrontendAssets(function (ModuleAssets $assets) {
    // Load only on specific pages
    if (is_page('example')) {
        $assets->script('main.js');
        $assets->style('main.css');
    }
});
```

## Next Steps

- [Add dependencies](../../guides/adding-dependencies.md)
- [Use feature flags](../../guides/feature-flags.md)
- [See custom post type example](../custom-post-type-module/)
