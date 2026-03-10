# Problem Brief: Video Block Platform Integration

## Summary

The `sitchco/video` block currently handles playback in isolation — each video instance manages its own click-to-play lifecycle with no awareness of other videos or platform systems. The spec requires deep integration with platform coordination layers: mutual exclusion (only one video plays at a time), modal lifecycle management, analytics event firing, and extensibility hooks that allow external blocks (content sliders, carousels, future components) to interact with video playback.

This is not just "add pause logic." It establishes the pattern for how blocks coordinate across the platform via `sitchco.hooks`, and how theme-level components interact with plugin-level blocks without tight coupling.

## Goal

Understand the full ecosystem of platform systems the video block must integrate with, so we can design the integration layer correctly — not just for video, but as a reference pattern for future cross-block coordination.

## Success Criteria

- Clear understanding of how `sitchco.hooks` works (JS API surface, registration patterns, priority system)
- Understanding of UIModal's lifecycle hooks and how video modal playback should wire into open/close events
- Understanding of how external blocks (e.g., content slider in the parent theme) can/should coordinate with video blocks
- Understanding of existing GTM/analytics patterns in the platform
- Identification of any existing cross-block coordination precedents to follow or extend

## Relevant Paths

- **Video block:** `/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/modules/VideoBlock/`
- **Design spec:** `/Users/jstrom/Projects/web/roundabout/video-component/video-design.md`
- **sitchco-core platform:** `/Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core/`
- **Content slider (parent theme):** `/Users/jstrom/Projects/web/roundabout/public/wp-content/themes/sitchco-parent-theme/modules/ContentSlider/blocks/content-slider/`
- **Parent theme modules:** `/Users/jstrom/Projects/web/roundabout/public/wp-content/themes/sitchco-parent-theme/modules/`

## Branch 1: sitchco.hooks System

The spec names `sitchco.hooks` as the coordination layer (Axiom 10). The video block must fire `video-play`, `video-pause`, and `video-ended` actions, and listen for `video-pause` to respond to external pause requests. Need to understand:

- How the hooks API works (JS implementation, action/filter distinction, priority system)
- What actions/filters are already registered across the platform
- How `sitchco.register()` and `sitchco.loadScript()` work (already used in view.js)
- Whether `sitchco.hooks` is a thin wrapper around `@wordpress/hooks` or custom

## Branch 2: UIModal Lifecycle

The video block already wires into `ui-modal-show` (view.js:410) and uses native `close` events. But the integration may be incomplete. Need to understand:

- Full UIModal lifecycle: what hooks fire, in what order, for open/close/hash-sync
- How the `ui-modal-show` and `ui-modal-hide` hooks work vs. native dialog events
- Why view.js uses native `close` event instead of `ui-modal-hide` (comment says the hook doesn't fire on Escape — is this still true?)
- How modal video playback should compose with mutual exclusion (modal open should pause inline videos)

## Branch 3: Cross-Block Interaction Patterns

The content slider in the parent theme may need to pause videos when sliding them off-screen. This is the canonical example of cross-block coordination. Need to understand:

- How the content slider currently works (JS behavior, events, lifecycle)
- Whether any cross-block coordination exists today in the platform
- What pattern should theme-level blocks use to coordinate with plugin-level blocks
- The coupling question: should the slider know about "videos" specifically, or fire a generic "content-hidden" action that the video block listens for?
- How `sitchco.hooks` enables loose coupling between components that don't directly depend on each other

## Branch 4: Analytics / GTM Integration

The spec requires GTM events for video start, pause, progress milestones (25/50/75/100%), and end (G1-G3). Need to understand:

- How other modules push GTM events (dataLayer pattern, existing helpers)
- Whether sitchco-core has a GTM/analytics abstraction or if modules push directly
- What event schema is used (event name, category, action, label conventions)
- How progress tracking should work with the provider SDKs (YouTube `onStateChange`, Vimeo `timeupdate`)
