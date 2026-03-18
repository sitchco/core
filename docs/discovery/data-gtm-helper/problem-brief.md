# Problem Brief: `data-gtm` Attribute Helper & Auto-Injection

## Summary

The TagManager module's click tracking uses `data-gtm` attributes to enrich tracking with context labels and interaction overrides. The old platform provided two mechanisms: a PHP helper (`sd_gtm_attr()`) and automatic injection on component root elements. The new platform needs equivalents that work within the Timber/Twig rendering pipeline while preserving the architectural constraint that no module depends on TagManager.

## Goal

Provide a mechanism for placing `data-gtm` attributes in Twig templates that:
- Integrates naturally with the Timber/Twig rendering pipeline
- Costs nothing (no markup, no overhead) when TagManager is disabled
- Does not create a dependency from templates or other modules on TagManager

Separately, determine whether automatic `data-gtm` injection on component/block root elements is feasible and desirable in the new architecture.

## Success Criteria

1. A clear recommendation for how to expose a `data-gtm` helper in Twig templates (function, filter, or `apply_filters` in the render path)
2. Understanding of the Timber/Twig extension points available in sitchco-core
3. A determination on auto-injection: feasible? desirable? where would it hook in?
4. A pattern that achieves zero-cost when TagManager is disabled
5. Clarity on whether structural context labels (Header, Footer, etc.) belong in the theme or the module

## Relevant Paths

- **sitchco-core plugin:** `/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/`
- **Parent theme:** `/Users/jstrom/Projects/web/roundabout/public/wp-content/themes/sitchco-parent-theme/`
- **Child theme:** `/Users/jstrom/Projects/web/roundabout/public/wp-content/themes/roundabout/`
- **Old platform TagManager:** `/Users/jstrom/Projects/web/rlf/public/wp-content/plugins/set-design/tag-manager/`

## Investigation Branches

### 1. Twig/Timber Rendering Pipeline

How does sitchco-core process and render Twig templates? What extension points exist for adding custom Twig functions or filters? This determines the mechanism for exposing a `data-gtm` helper.

Key files: `src/Utils/TimberUtil.php`, `src/Utils/Template.php`, and any Twig extension registration.

### 2. Component & Block Architecture

Does the new platform have a concept of "component root element" analogous to the old Backstage system? How do blocks and components render their markup? This determines whether auto-injection of `data-gtm` on root elements is feasible.

Key files: `src/ModuleExtension/BlockRegistrationModuleExtension.php`, block rendering paths.

### 3. Module Decoupling Patterns

How do optional modules in sitchco-core expose functionality to templates without creating hard dependencies? What patterns exist for "zero-cost when disabled" features? This informs the filter/hook approach for `data-gtm`.

Key files: `src/Framework/Module.php`, `src/Framework/ModuleRegistry.php`, existing filter patterns.

### 4. Theme Template Patterns

How do the parent and child themes structure their Twig templates? Where would `data-gtm` attributes naturally be placed? Are there existing patterns for conditional attributes? This grounds the solution in real template usage.

### 5. Old Platform Reference

What exactly did `sd_gtm_attr()` do and how was auto-injection implemented? Understanding the old implementation helps identify what worked well and what to improve.
