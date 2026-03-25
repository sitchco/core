# UIModal

An accessible modal dialog module built on the native `<dialog>` element. Modals are rendered in `wp_footer`, triggered by anchor links or `data-target` attributes, and support URL hash syncing and full keyboard/screen-reader accessibility.

Focus trapping, scroll locking, and background inertness are handled natively by `<dialog>.showModal()`.

## Modal Types

| Type | Key | Description |
|------|-----|-------------|
| Box | `box` | Centered box over a semi-transparent backdrop. Max-width constrained on desktop, full-width on mobile. |
| Full | `full` | Full-screen layout filling the viewport. No backdrop. |

Custom types can be registered by modules or themes via `registerType()`. A type with no CSS inherits box layout at all breakpoints.

## Usage: Gutenberg Block

Add the **Modal** block (`sitchco/modal`) in the editor. It exposes two ACF fields:

- **Post** — select the post whose content becomes the modal body
- **Type** — `box` (default) or `full`, plus any theme-registered types

In the editor, the block renders an inline preview showing a truncated excerpt and the modal's slug ID. On the front end, nothing renders inline — the modal is appended to `wp_footer` and stays hidden until triggered.

## Usage: PHP

Inject `UIModal` from the container and call `loadModal()` with a `ModalData` instance. The modal will be queued and output in `wp_footer`.

```php
use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;

$module = $container->get(UIModal::class);
$post = \Timber\Timber::get_post($postId);
$module->loadModal(ModalData::fromPost($post, 'full'));
```

Assets are only enqueued when at least one modal has been loaded.

## Custom Types

### Registering a Type

Modules can register custom types via `registerType()`. Types with a `label` appear in the block editor dropdown. Types without a label are module-only (usable programmatically but not selectable by editors).

```php
// In a module's init() — requires DEPENDENCIES = [UIModal::class]
$this->uiModal->registerType('slideshow', ['label' => 'Slideshow']);

// Module-only type (no dropdown entry)
$this->uiModal->registerType('video');
```

Last-write-wins: a child theme can override a type registered by its parent to change the label or options.

### Styling a Custom Type

Custom types are styled via CSS custom property overrides on the type's modifier class. No structural CSS is needed for common layout patterns.

```css
.sitchco-modal--slideshow {
    --modal-container-flex: 1 0 auto;
    --modal-container-margin: 0;
    --modal-container-max-width: none;
    --modal-backdrop-color: var(--modal-bg-color);
}
```

A type with no CSS inherits box layout (centered, max-width constrained) at all breakpoints.

## Triggering a Modal

Any element can open a modal by referencing its ID. The ID is derived from the post slug via `sanitize_title()`. Slugs with leading digits receive a `modal-` prefix (e.g. `2024-update` becomes `modal-2024-update`).

```html
<!-- Anchor link -->
<a href="#my-post-slug">Open Modal</a>

<!-- Any element with data-target -->
<button data-target="#my-post-slug">Open Modal</button>
<div data-target="#my-post-slug">Open Modal</div>
```

The JS automatically decorates triggers with ARIA attributes (`aria-haspopup="dialog"`, `aria-expanded`) and, for non-interactive elements, adds `role="button"` and `tabindex="0"`.

### Dismissing

- Click the close button (✕)
- Click the backdrop overlay (the area outside `__container`)
- Press **Escape**
- Add class `js-modal-close` to any element inside the modal to make it a dismiss trigger

### Block Dismiss

Add the class `sitchco-modal--blockdismiss` to prevent the close button and overlay dismissal. The Escape key is also suppressed via the native `cancel` event. Re-enable with:

```js
sitchco.hooks.doAction('ui-modal-enableDismiss', modalElement);
```

### URL Hash

Opening a modal sets the URL hash to `#modal-id`. Navigating directly to a URL with a modal hash opens that modal on page load. Closing clears the hash.

## Accessibility

- Native `<dialog>` element with `.showModal()` provides implicit `role="dialog"` and `aria-modal="true"`
- `aria-labelledby` set automatically from the first heading inside the modal
- If the post content has no `<h1>`–`<h6>`, the post title is rendered as a screen-reader-only `<h2>`
- Focus trapping is handled natively by the browser (no JS focus trap needed)
- Background content is made inert natively by `.showModal()` (no manual `inert` attribute needed)
- Scroll locking uses a `lock-scroll` class on `<body>`, added on open and removed on the native `close` event
- `prefers-reduced-motion` disables transitions

## PHP Hooks

All hook names are generated via `UIModal::hookName()`, producing the pattern `sitchco/ui-modal/{suffix}`.

### `sitchco/ui-modal/pre-content`

Insert content before the modal body.

```php
add_filter(UIModal::hookName('pre-content'), function (string $html, ModalData $modal) {
    return '<p>Custom header for ' . $modal->id() . '</p>';
}, 10, 2);
```

### `sitchco/ui-modal/close`

Customize the close button inner HTML. Default: `&#10005;` (✕).

```php
add_filter(UIModal::hookName('close'), function (string $html, ModalData $modal) {
    return '<span class="sr-only">Close</span><svg>...</svg>';
}, 10, 2);
```

### `sitchco/ui-modal/content-attributes`

Modify the `.sitchco-modal__content` div attributes. The primary use case is injecting layout classes from the parent theme based on modal type.

```php
add_filter(UIModal::hookName('content-attributes'), function (array $attrs, ModalData $modal) {
    if ($modal->type === 'video') {
        return $attrs;
    }
    $attrs['class'] = array_merge((array) ($attrs['class'] ?? []), ['is-layout-constrained', 'has-global-padding']);
    return $attrs;
}, 10, 2);
```

Default attributes: `['class' => 'sitchco-modal__content']`. The `class` key supports arrays (joined with spaces).

### `sitchco/ui-modal/attributes`

Modify the outer `<dialog>` attributes. Useful for adding classes or data attributes.

```php
add_filter(UIModal::hookName('attributes'), function (array $attrs, ModalData $modal) {
    $attrs['class'] .= ' sitchco-modal--blockdismiss';
    return $attrs;
}, 10, 2);
```

### `filterModalPostQuery()`

Restrict the ACF post selector in the block editor:

```php
$container->get(UIModal::class)->filterModalPostQuery([
    'post_type' => 'content_partial',
    'tax_query' => [['taxonomy' => 'partial_type', 'field' => 'slug', 'terms' => 'modal']],
]);
```

## JS Hooks

Uses the `sitchco.hooks` system (WordPress-style hooks in JS).

### Actions

| Hook | Argument | Description |
|------|----------|-------------|
| `ui-modal-show` | `modalElement` | Open a modal programmatically |
| `ui-modal-hide` | `modalElement` | Close a modal programmatically |
| `ui-modal-enableDismiss` | `modalElement?` | Remove `--blockdismiss` from a modal (or the currently open one) |

```js
const modal = document.getElementById('my-post-slug');
sitchco.hooks.doAction('ui-modal-show', modal);
sitchco.hooks.doAction('ui-modal-hide', modal);
```

## CSS Customization

Override CSS custom properties on `.sitchco-modal` or a type modifier class:

```css
.sitchco-modal {
    /* Layout (type-overridable) */
    --modal-backdrop-color: transparent;
    --modal-container-flex: 0 0 auto;
    --modal-container-margin: auto;
    --modal-container-width: 100%;
    --modal-container-max-width: var(--modal-max-width);   /* 980px */
    --modal-box-gap: 2rem;

    /* Container appearance */
    --modal-container-bg: #fff;
    --modal-container-color: #000;
    --modal-container-padding: 1rem;       /* 3rem at ≥576px; vertical (top/bottom) */
    --modal-container-padding-h: ...;      /* Horizontal; set by parent theme, fallback 1rem */
    --modal-container-border-radius: 0px;

    /* Global */
    --modal-bg-color: rgb(0 0 0 / 0.7);
    --modal-max-width: 980px;
    --modal-close-size: 2rem;
    --modal-close-color: var(--modal-container-color);
    --modal-close-color-hover: var(--modal-close-color);
}
```

## Browser Support

Open/close animations use `@starting-style` and `transition-behavior: allow-discrete`, which require Chrome 117+, Safari 17.4+, Firefox 129+. Older browsers get instant open/close — the dialog still functions, just without animation.

## Dependencies

- **TimberModule** — Twig templating
- **UIFramework** — JS hook system, CSS transition variables

## File Structure

```
UIModal/
├── UIModal.php           # Module class: type registry, loading, rendering
├── ModalData.php         # Readonly value object for modal ID, heading, content, and type
├── acf-json/             # ACF field group for the block
├── blocks/modal/         # Gutenberg block (block.json, block.php, block.twig, style.css)
├── templates/modal.twig  # Modal HTML template
└── assets/
    ├── scripts/main.js   # Frontend behavior
    └── styles/main.css   # Frontend styles
```
