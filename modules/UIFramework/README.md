# UIFramework Module

## Overview

The UIFramework provides JavaScript lifecycle and event systems for both the frontend and the block editor, built on top of WordPress's `@wordpress/hooks` package. It creates deterministic boot pipelines that give themes and plugins a predictable order of execution.

Everything is exposed on `window.sitchco`.

## Architecture

The module ships three entry scripts:

| Script | Handle | Context | Purpose |
|--------|--------|---------|---------|
| `hooks.js` | `sitchco/hooks` | Both | Isolated hooks instance shared across all contexts |
| `main.js` | `sitchco/ui-framework` | Frontend | Frontend lifecycle, event normalization, utilities |
| `editor-ui-main.js` | `sitchco/editor-ui-framework` | Block Editor | Editor lifecycle with two-phase pipeline |

`hooks.js` is a dependency of both `main.js` and `editor-ui-main.js`, ensuring the same `sitchco.hooks` instance is available everywhere without duplication.

## Hooks

The hooks system creates an isolated `wp.hooks` instance (separate from the WordPress global) and auto-namespaces all callbacks under `"sitchco"`. Consumers never manage namespaces manually.

```js
sitchco.hooks.addAction('myHook', callback, priority);
sitchco.hooks.addFilter('myFilter', callback, priority);
sitchco.hooks.doAction('myHook', ...args);
sitchco.hooks.applyFilters('myFilter', value, ...args);
```

An optional `subNamespace` parameter (4th argument) scopes callbacks further to `"sitchco/{subNamespace}"`.

## Frontend Lifecycle

On `DOMContentLoaded`, three hook phases fire in sequence:

| Phase | Hook Name | Constant | Purpose |
|-------|-----------|----------|---------|
| 1 | `init` | `INIT` | Theme registers filters and configuration |
| 2 | `initRegister` | `REGISTER` | Components register, applying theme filters |
| 3 | `initReady` | `READY` | Post-registration work, everything is wired up |

This guarantees themes configure the system *before* components read that configuration — the same pattern as WordPress PHP's `plugins_loaded` → `init` → `wp_loaded`.

A `CustomEvent` (`sitchco/core/init`) is dispatched on `document` just before phase 1 for external code that can't use the hooks pipeline.

### Convenience Functions

```js
sitchco.init(callback, priority);     // Hook into phase 1
sitchco.register(callback, priority); // Hook into phase 2
sitchco.ready(callback, priority);    // Hook into phase 3
```

Priority defaults to `100`. Lower numbers run first.

## Editor Lifecycle

The block editor has its own two-phase pipeline that fires synchronously before the editor React app initializes:

| Phase | Hook Name | Purpose |
|-------|-----------|---------|
| 1 | `editorInit` | Theme configures filters (e.g. color options, icon options) |
| 2 | `editorReady` | Components register using APIs and consume filters |

### How It Works

1. `sitchco/editor-ui-framework` is enqueued at priority **1** on `enqueue_block_editor_assets`
2. Module scripts enqueue at the default priority (10) — no priority juggling needed
3. At `PHP_INT_MAX`, a flush script fires `sitchco.editorFlush()`, executing all registered phases in order
4. A `DOMContentLoaded` fallback catches edge cases where the PHP flush doesn't fire

Since WordPress outputs enqueued scripts in enqueue order, the flush naturally runs after all module scripts have registered their callbacks.

### Convenience Functions

```js
sitchco.editorInit(callback, priority);  // Hook into phase 1
sitchco.editorReady(callback, priority); // Hook into phase 2
```

Priority defaults to `100`. Lower numbers run first.

### Example: Theme + Module Integration

```js
// Parent theme editor-ui.js — Phase 1: configure filters
sitchco.editorInit(() => {
    sitchco.hooks.addFilter('theme.color-options', (options) => {
        return [...options, { label: 'Default', value: '' }, { label: 'White', value: 'white' }];
    }, 5, 'parent-theme');
});

// Phase 2: consume filters and register block extensions
sitchco.editorReady(() => {
    extendButton(sitchco.extendBlock);
});
```

```js
// Child theme editor-ui.js — extends parent filters at higher priority
sitchco.editorInit(() => {
    sitchco.hooks.addFilter('theme.color-options', (options) => {
        return [...options, { label: 'Brand Blue', value: 'brand-blue' }];
    }, 10, 'child-theme');
});
```

```js
// Module editor-ui.js — reads filters at editorReady, guaranteed to have all values
sitchco.editorReady(() => {
    const colors = sitchco.hooks.applyFilters('theme.color-options', []);
    // colors includes parent + child theme values
});
```

### Script Dependencies

Editor modules should depend on `sitchco/editor-ui-framework` (not `sitchco/ui-framework`). The framework handles the rest:

```php
$assets->registerScript('my-editor-script', 'editor-ui.js', [
    'sitchco/editor-ui-framework',
]);
```

## Event Actions

Browser events are normalized into hook actions with built-in throttle/debounce:

| Browser Event | Hook Actions |
|---|---|
| `scroll` | `scrollStart` → `scroll` → `scrollEnd` |
| `resize` / `orientationchange` | `layout` → `layoutEnd` |
| `keydown` | `key.tab`, `key.return`, `key.esc` |
| `hashchange` | `hashStateChange` |
| Mouse near top/left edge | `exitIntent` |
| First user input | `userFirstInteraction` (fires once) |

Throttle/debounce timing is filterable:

| Filter | Default | Controls |
|--------|---------|----------|
| `debounceDelay` | `300` | Debounce for layout end and scroll end |
| `layoutThrottle` | `300` | Throttle for layout actions |
| `scrollThrottle` | `100` | Throttle for scroll actions |

## Dynamic CSS Variables

Register CSS custom properties that update automatically on layout changes:

```js
sitchco.init(() => {
    sitchco.hooks.addFilter('css-vars.register', (styles) => {
        styles['my-value'] = () => `${computeSomething()}px`;
        return styles;
    });
});
```

This sets `--dynamic__my-value` on `<html>` and refreshes it on every `layout` / `layoutEnd` event.

### Scroll-reactive CSS vars

Opt in with the `css-vars.use-scroll` filter, then register via `css-vars.register-scroll`. A built-in `--dynamic__scroll-direction` variable (`1` or `-1`) is provided automatically when scroll vars are enabled.

## Built-in Filters

| Filter | Default | Purpose |
|--------|---------|---------|
| `header-height` | Auto-measured | Current header element height in px |
| `css-vars.register` | `{}` | Register dynamic CSS custom properties |
| `css-vars.use-scroll` | `false` | Enable scroll-reactive CSS vars |
| `css-vars.register-scroll` | `{}` | Register scroll-dependent CSS vars |
| `enableExitIntent` | `false` | Enable exit-intent detection |
| `debounceDelay` | `300` | Global debounce timing |
| `layoutThrottle` | `300` | Layout event throttle |
| `scrollThrottle` | `100` | Scroll event throttle |

## Utilities

### scrollWatch

Trigger callbacks when elements enter the viewport:

```js
sitchco.scrollWatch(document.querySelectorAll('.animate'), (el) => {
    el.classList.add('visible');
}, { prune: true, defer: false, force: false });
```

- `prune` (default `true`) — remove element after triggering once
- `defer` (default `false`) — wait for `window.load`
- `force` (default `false`) — trigger even if element isn't visible

### hashState

Read and write URL hash state:

```js
sitchco.hashState.get();        // Returns current HashState object
sitchco.hashState.isset();      // Boolean
sitchco.hashState.set('panel'); // Sets location.hash to #/panel
```

Changes fire the `hashStateChange` action with a `HashState` object containing `current`, `currentList`, `previous`, and `previousList`.

### updateLayout

Imperatively trigger a layout recalculation:

```js
sitchco.updateLayout();
```

### loadScript / registerScript

Lazy-load scripts by name:

```js
sitchco.registerScript('my-lib', 'https://example.com/lib.js');
sitchco.loadScript('my-lib').then(() => { /* ready */ });
```

## Example: Frontend Theme Integration

```js
// Phase 1 — configure
sitchco.init(() => {
    sitchco.hooks.addFilter('header-height', () => 80);
    sitchco.hooks.addFilter('enableExitIntent', () => true);
    sitchco.hooks.addFilter('css-vars.register', (styles) => {
        styles['brand-color'] = () =>
            getComputedStyle(document.documentElement)
                .getPropertyValue('--brand-color');
        return styles;
    });
});

// Phase 2 — register components
sitchco.register(() => {
    sitchco.scrollWatch(
        document.querySelectorAll('.fade-in'),
        (el) => el.classList.add('visible')
    );
});

// Phase 3 — post-registration
sitchco.ready(() => {
    console.log('All components registered');
});
```