# Problem Statement: `data-gtm` Attribute Helper & Auto-Injection

## Summary

The TagManager module's click tracking and context resolution depend on `data-gtm` attributes in the DOM markup. The old platform had two mechanisms for applying these attributes — a PHP helper function and automatic injection via the component system. The new platform needs equivalents, but the design intersects with the Timber/Twig rendering pipeline and must preserve the architectural constraint that no module depends on TagManager.

## What the Old Platform Did

### `sd_gtm_attr()` PHP Helper

A standalone function that takes a value (string, array, or object), JSON-encodes non-strings, escapes for HTML attribute output, and returns the full `data-gtm="..."` attribute string. Used directly in PHP templates:

```php
<a href="/donate" <?php echo sd_gtm_attr(['label' => 'Donate', 'role' => 'CTA']); ?>>
```

### Backstage Component Auto-Injection

The old component rendering system (`Util::componentAttributesArray()`) automatically added `data-gtm` set to the BEM base class name on every component root element. This gave free context labels (e.g., `data-gtm="card-grid"`) without any manual markup, meaning the ancestor context walk in click tracking always had meaningful labels to collect.

## Why This Needs Discovery

### Timber/Twig Rendering Pipeline

The new platform renders templates through Timber and Twig. The sitchco-core plugin has a render path that processes Twig templates. The question is how to expose a `data-gtm` helper within this pipeline — as a Twig function, a Twig filter, or something else — and whether the existing render path already has extension points for this.

### Zero-Cost When Disabled

TagManager is an optional module. If a site doesn't enable it, `data-gtm` attributes shouldn't litter the markup. The ideal pattern:

- A `apply_filters('sitchco/tag-manager/gtm-attr', '', $value)` call in templates
- When TagManager is disabled: filter returns empty string, no output
- When TagManager is enabled: filter returns the full `data-gtm="..."` attribute

This keeps the template code decoupled — it calls a filter, not a TagManager utility.

### Auto-Injection Scope

The old platform injected `data-gtm` on every component element. The new platform may not have an equivalent component abstraction, or may have a different one. Questions:

- Does the Twig render path have a concept of "component root element" where auto-injection could hook in?
- Is auto-injection even desirable, or is explicit `data-gtm` placement in templates sufficient?
- Should structural labels (`data-gtm="Header"`, `data-gtm="Footer"`) be a theme responsibility rather than a module feature?

## What This Does NOT Block

The TagManager module's click tracking works on all qualifying elements without any `data-gtm` markup. Labels are auto-resolved from `aria-label`, `title`, `value`, and `textContent`. The `data-gtm` attribute enriches tracking with context labels and interaction overrides — it doesn't enable it.

This discovery can happen in parallel with or after the core TagManager build.

## Questions — Resolved

All questions resolved in `docs/discovery/data-gtm-helper/problem-assessment.md`:

1. **Twig rendering pipeline**: Custom Twig functions registered via `timber/twig/functions` filter in `TimberModule`. `is_safe => ['html']` prevents escaping.
2. **Mechanism**: Twig function `gtm_attr()` with no-op stub in TimberModule. TagManager replaces the callable when active.
3. **Zero-cost**: No-op stub returns `''`. Disabled TagManager = no markup, no overhead.
4. **Auto-injection**: Not pursued. Explicit placement only — structural landmarks, interactive containers when analytically meaningful, interaction overrides as needed.
5. **Structural labels**: Theme-owned. Parent theme templates add `{{ gtm_attr('Header') }}`, `{{ gtm_attr('Footer') }}`, etc.
