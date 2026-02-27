# UIModal

An accessible modal dialog module built on the native `<dialog>` element. Modals are rendered in `wp_footer`, triggered by anchor links or `data-target` attributes, and support URL hash syncing and full keyboard/screen-reader accessibility.

Focus trapping, scroll locking, and background inertness are handled natively by `<dialog>.showModal()`.

## Modal Types

| Type | Enum | Description |
|------|------|-------------|
| Box | `ModalType::BOX` | Centered box over a semi-transparent backdrop. 80% width on desktop, full-width on mobile. |
| Centered | `ModalType::CENTERED` | Full-screen centered layout. |
| Video | `ModalType::VIDEO` | Full-screen layout optimized for video content. |

## Usage: Gutenberg Block

Add the **Modal** block (`sitchco/modal`) in the editor. It exposes two ACF fields:

- **Post** — select the post whose content becomes the modal body
- **Type** — `box` (default), `centered`, or `video`

In the editor, the block renders an inline preview showing a truncated excerpt and the modal's slug ID. On the front end, nothing renders inline — the modal is appended to `wp_footer` and stays hidden until triggered.

## Usage: PHP

Inject `UIModal` from the container and call `loadModal()` with a Timber `Post`. The modal will be queued and output in `wp_footer`.

```php
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Modules\UIModal\ModalType;

$modal = $container->get(UIModal::class);
$post = \Timber\Timber::get_post($postId);
$modal->loadModal($post, ModalType::CENTERED);
```

Assets are only enqueued when at least one modal has been loaded.

## Triggering a Modal

Any element can open a modal by referencing its ID (the post slug):

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
- Scroll locking is handled natively by the top layer (no `lock-scroll` class needed)
- `prefers-reduced-motion` disables transitions

## PHP Hooks

All hook names are generated via `UIModal::hookName()`, producing the pattern `ui-modal/{suffix}`.

### `ui-modal/pre-content`

Insert content before the modal body.

```php
add_filter(UIModal::hookName('pre-content'), function (string $html, ModalData $modal) {
    return '<p>Custom header for ' . $modal->id() . '</p>';
}, 10, 2);
```

### `ui-modal/close`

Customize the close button inner HTML. Default: `&#10005;` (✕).

```php
add_filter(UIModal::hookName('close'), function (string $html, ModalData $modal) {
    return '<span class="sr-only">Close</span><svg>...</svg>';
}, 10, 2);
```

### `ui-modal/attributes`

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

Override CSS custom properties on `.sitchco-modal` or a specific modal ID:

```css
.sitchco-modal {
    --modal-bg-color: rgb(0 0 0 / 0.7);
    --modal-max-width: 980px;
    --modal-text-align: center;
    --modal-container-bg: #fff;
    --modal-container-color: #000;
    --modal-container-padding: 3rem;
    --modal-container-mobile-padding: 1rem;
    --modal-box-container-bg: var(--modal-container-bg);
    --modal-box-border-radius: 0;
    --modal-box-size: 80%;
    --modal-box-padding: calc((100 - 80) * 0.5vh);
    --modal-close-size: 2rem;
    --modal-close-color: var(--modal-container-color);
    --modal-close-color-hover: var(--modal-close-color);
    --modal-close-top: calc(var(--modal-container-padding) - var(--modal-close-size));
    --modal-close-right: calc(var(--modal-container-padding) - var(--modal-close-size));
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
├── UIModal.php           # Module class: registration, loading, rendering
├── ModalType.php         # Enum: BOX, CENTERED, VIDEO
├── ModalData.php         # Readonly data model wrapping a Timber Post
├── acf-json/             # ACF field group for the block
├── blocks/modal/         # Gutenberg block (block.json, block.php, style.css)
├── templates/modal.twig  # Modal HTML template
└── assets/
    ├── scripts/main.js   # Frontend behavior
    └── styles/main.css   # Frontend styles
```
