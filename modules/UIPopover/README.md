# UIPopover

An accessible popover module for non-modal floating UI. Built on two native browser APIs:

- **Popover API** (`<div popover>`, `.showPopover()`) for show/hide/dismiss behavior
- **CSS Anchor Positioning** (`anchor-name`, `position-anchor`, `position-area`) for trigger-relative placement

Click-activated, arbitrary inline content, light dismiss, optional backdrop and arrow. Complements UIModal (modal dialogs) for non-modal use cases.

## Usage: PHP

Inject `UIPopover` from the container and call `render()`:

```php
use Sitchco\Modules\UIPopover\UIPopover;

$popover = $container->get(UIPopover::class);

echo $popover->render('Options', '<p>Popover content here</p>');
```

Assets are enqueued automatically when `render()` or `enqueue()` is called.

### `render(string $triggerContent, string $panelContent, array $options = []): string`

Renders the trigger and panel as adjacent elements, preserving natural tab order. A unique panel ID is generated automatically (`sitchco-popover-1`, `sitchco-popover-2`, etc.).

The trigger is always a `<button>` element — the correct semantic for an interactive popover control.

**Options:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `trigger_attributes` | `array` | `[]` | Merge/override trigger attributes |
| `panel_attributes` | `array` | `[]` | Merge/override panel attributes |

```php
echo $popover->render('Menu', $menuHtml, [
    'trigger_attributes' => ['class' => 'my-trigger'],
    'panel_attributes' => ['class' => ['sitchco-popover', 'sitchco-popover--backdrop', 'sitchco-popover--arrow']],
]);
```

**Default trigger attributes:**

| Attribute | Value |
|-----------|-------|
| `class` | `sitchco-popover-trigger` |
| `aria-expanded` | `false` |
| `aria-controls` | `$panelId` |
| `aria-haspopup` | `dialog` |
| `data-popover-trigger` | `$panelId` |
| `style` | `anchor-name: --$panelId` |

**Default panel attributes:**

| Attribute | Value |
|-----------|-------|
| `id` | `$panelId` |
| `class` | `sitchco-popover` |
| `popover` | (boolean attribute) |
| `style` | `position-anchor: --$panelId` |

### `enqueue(): void`

Enqueues assets without rendering any markup. Use when writing HTML manually.

## Usage: Markup-Only

Write the HTML manually with the required data attributes, then call `$popover->enqueue()`:

```html
<button
    class="sitchco-popover-trigger"
    aria-expanded="false"
    aria-controls="my-popover"
    aria-haspopup="dialog"
    data-popover-trigger="my-popover"
    style="anchor-name: --my-popover"
>
    Open
</button>

<div
    id="my-popover"
    class="sitchco-popover"
    popover
    style="position-anchor: --my-popover"
>
    <p>Content here</p>
</div>
```

The panel should appear directly after its trigger in the DOM so that tab order remains intact.

The JS discovers panels via `[popover].sitchco-popover` and triggers via `[data-popover-trigger]`.

## Dismissing

- **Click outside** (light dismiss) — native Popover API behavior
- **Escape** key — native Popover API behavior
- **Tab out** — keyboard-only; tabbing past the last focusable element closes the popover. Mouse clicks inside the panel (text selection, interacting with content) do not dismiss.

### Manual mode

Set `popover="manual"` on the panel to disable all automatic dismissal (light dismiss, Escape, and tab-out). Only the trigger toggle and programmatic JS hooks will close it. Useful for debugging or persistent popovers.

```php
echo $popover->render('Open', $content, [
    'panel_attributes' => ['popover' => 'manual'],
]);
```

## JS Hooks

Uses the `sitchco.hooks` system (WordPress-style hooks in JS).

### Actions

| Hook | Argument | Description |
|------|----------|-------------|
| `ui-popover-show` | `panelElement` | Open a popover programmatically |
| `ui-popover-hide` | `panelElement` | Close a popover programmatically |
| `ui-popover-toggle` | `panelElement` | Toggle a popover programmatically |

```js
const panel = document.getElementById('my-popover');
sitchco.hooks.doAction('ui-popover-show', panel);
sitchco.hooks.doAction('ui-popover-hide', panel);
sitchco.hooks.doAction('ui-popover-toggle', panel);
```

## PHP Hooks

All hook names are generated via `UIPopover::hookName()`, producing the pattern `ui-popover/{suffix}`.

### `ui-popover/trigger-attributes`

Modify trigger element attributes.

```php
add_filter(UIPopover::hookName('trigger-attributes'), function (array $attrs, string $panelId) {
    $attrs['class'] .= ' my-custom-trigger';
    return $attrs;
}, 10, 2);
```

### `ui-popover/panel-attributes`

Modify panel element attributes.

```php
add_filter(UIPopover::hookName('panel-attributes'), function (array $attrs, string $panelId) {
    $attrs['class'] .= ' my-custom-panel';
    return $attrs;
}, 10, 2);
```

## CSS Customization

Override CSS custom properties on `.sitchco-popover` or a specific panel:

```css
.sitchco-popover {
    --popover-bg: #fff;
    --popover-color: #000;
    --popover-padding: 1rem;
    --popover-border-radius: 0.25rem;
    --popover-shadow: 0 4px 16px rgb(0 0 0 / 0.15);
    --popover-max-width: 24rem;
    --popover-offset: 0.5rem;
    --popover-position-area: bottom;
    --popover-backdrop-bg: rgb(0 0 0 / 0.7);
    --popover-arrow-size: 1rem;
}
```

### Backdrop

Add the `sitchco-popover--backdrop` modifier class to the panel for a semi-transparent backdrop overlay (matches UIModal's backdrop opacity):

```php
echo $popover->render('Open', $content, [
    'panel_attributes' => ['class' => ['sitchco-popover', 'sitchco-popover--backdrop']],
]);
```

### Arrow

Add the `sitchco-popover--arrow` modifier class to the panel for a triangle arrow pointing toward the trigger:

```php
echo $popover->render('Open', $content, [
    'panel_attributes' => ['class' => ['sitchco-popover', 'sitchco-popover--arrow']],
]);
```

The arrow uses `--popover-bg` for its color and `--popover-arrow-size` for its dimensions. It is automatically positioned at the trigger's horizontal center via JS.

Both modifiers can be combined:

```php
echo $popover->render('Open', $content, [
    'panel_attributes' => ['class' => ['sitchco-popover', 'sitchco-popover--backdrop', 'sitchco-popover--arrow']],
]);
```

## Positioning

### Anchor Positioning (modern browsers)

The popover is positioned relative to the trigger using CSS Anchor Positioning. The default position is below the trigger, with automatic flipping when there isn't enough space.

Override `--popover-position-area` to change placement:

```css
#my-popover {
    --popover-position-area: top;
}
```

Supported values: `top`, `bottom`, `left`, `right`, and combinations like `top right`.

The gap between trigger and popover is controlled by `--popover-offset` (default `0.5rem`). When the `--arrow` modifier is used, the arrow height is added automatically.

### Fallback (older browsers)

For browsers without anchor positioning support, wrap the trigger and panel in a `.sitchco-popover-wrapper` div:

```html
<div class="sitchco-popover-wrapper">
    <button data-popover-trigger="my-popover" style="anchor-name: --my-popover">Open</button>
    <div id="my-popover" class="sitchco-popover" popover style="position-anchor: --my-popover">
        Content
    </div>
</div>
```

The wrapper provides `position: relative` context for `position: absolute` fallback.

## Accessibility

- Trigger and panel are adjacent in the DOM, preserving natural tab order
- Trigger has `aria-expanded` toggled on open/close
- `aria-controls` links trigger to panel
- `aria-haspopup="dialog"` communicates trigger purpose
- Focus moves to first focusable child on open, returns to trigger on close
- Tab-out dismisses the popover (keyboard only — mouse interactions inside the panel are unaffected)
- Multiple popovers can coexist independently (no modal blocking)

## Browser Support

| Feature | Chrome | Edge | Firefox | Safari |
|---------|--------|------|---------|--------|
| Popover API | 114+ | 114+ | 125+ | 17+ |
| CSS Anchor Positioning | 125+ | 125+ | 147+ | 26+ |
| `@starting-style` | 117+ | 117+ | 129+ | 17.4+ |

Older browsers get instant show/hide (no animation) and require the `.sitchco-popover-wrapper` fallback for positioning.

## Dependencies

- **TimberModule** — Twig templating
- **UIFramework** — JS hook system, CSS transition variables

## File Structure

```
UIPopover/
├── UIPopover.php              # Module class
├── templates/popover.twig     # Trigger + panel template
├── assets/
│   ├── scripts/main.js        # Frontend behavior
│   └── styles/main.css        # Styles + anchor positioning
└── README.md                  # Documentation
```

## Future

Roles (`listbox`, `menu`, `tooltip`) planned for v2.
