# Problem Assessment: Tag Manager Module for Sitchco-Core

## Summary

The old Set Design plugin implements a full-featured tag management system covering GTM container injection, interaction tracking via `data-gtm` attributes and `dataLayer`, UTM parameter persistence, outbound link decoration, and arbitrary script injection. The architecture follows a hub-and-spoke pattern: consuming modules (modal, video, carousel) emit domain-level events, and tag-manager subscribes to translate them into `dataLayer` pushes. The new sitchco-core platform already has the key integration points stubbed — `GTM_INTERACTION`, `GTM_STATE`, and `GFORM_CONFIRM` constants in UIFramework, plus `ui-modal-show`/`ui-modal-hide` hooks in UIModal — but no module exists to consume them.

## GTM Container & Script Injection (PHP)

The old system injects GTM via ACF-configured container IDs stored in a repeater field (`setdesign_tagmanager_ids`) on a Settings options page. Each row has a `container_id` and optional `staging_snippet`.

**Head snippet** (`wp_head` priority 1): Standard GTM async loader script. In non-production environments (when the `ENVIRONMENT` PHP constant is defined and not `'production'`), a `staging_snippet` textarea value is echoed verbatim instead, allowing alternate GTM environment snippets.

**Body snippet** (`wp_body_open`): Standard `<noscript>` iframe. Has no staging logic — always uses the production container ID regardless of environment. A static flag prevents double-rendering from both `wp_body_open` and a legacy `after_opening_body` action.

**Global state** (`wp_footer` priority 1000): Pushes `dataLayer.push({state: {...}})` containing domain, page metadata (title, slug, postType), and ACF-configured custom key-value pairs. The `state` key is non-standard (not a GTM `event` — it's a variable update). Covers `is_singular`, `is_archive`, `is_search`, and `is_home` cases but not `is_front_page()` (static homepage), 404, or taxonomy archives.

**Additional tags system**: A separate ACF repeater (`setdesign_tagmanager_additional_tags`) allows arbitrary HTML/script injection in `wp_head` with per-page include/exclude rules via a relationship field. Tags inject independently of GTM — they fire even when GTM is disabled via the `tagmanager/enable-gtm` filter. The include/exclude logic works for singular posts and the blog homepage but behaves unexpectedly on archives/search pages (where `get_the_ID()` returns 0).

**PHP filters:**
- `tagmanager/gtm-id` — modify the container array programmatically
- `tagmanager/enable-gtm` — master switch for GTM snippets (not additional tags)
- `tagmanager/additional-tags` — modify the post-filter tag array
- `tagmanager/current-state` — add/modify global state properties before push

Multiple GTM containers are supported via the repeater, each with independent staging logic.

## Interaction Tracking (JS)

### Click Tracking

Delegated on `document` — covers dynamically inserted content. Tracks all `a`, `button`, `input[type=submit]`, and `[data-button]` elements. Elements with `data-gtm="0"` or `data-gtm="false"` are explicitly excluded from click tracking.

Each click pushes `{event: 'interaction', interaction: {...}}` followed immediately by a reset push `{event: '', interaction: undefined}` to clear GTM's persistent state. Both pushes are inside a 5ms `setTimeout` to allow navigation to begin before the event fires.

**Interaction object fields:**

| Field | Source |
|---|---|
| `type` | Default `'click'`; overridable via JSON `data-gtm` |
| `label` | `data-gtm` string, else `aria-label`, `aria-labelledby` text, `title`, `value`, `.text()` |
| `context` | Array of ancestor labels from DOM walk (root-first) |
| `direction` | `'outbound'` or `'internal'` (anchor elements only, via `HTMLAnchorElement.host`) |
| `destination` | `href` with origin stripped (anchor elements only) |
| `toggle` | Boolean from `aria-pressed` or `aria-expanded` (post-click state) |

### Context Resolution

`getContext()` walks ancestors matching `[data-gtm], [id], [aria-label], [aria-labelledby]` and returns an array of their labels in root-first order. For each ancestor, it picks the first available: `data-gtm` string > `id` > `aria-label` > `aria-labelledby` text. A `data-gtm="0"` ancestor contributes nothing meaningful (0 is falsy, falls through to `id`).

Theme templates add structural context labels: `data-gtm="Header"` on `<header>`, `data-gtm="footer"` on `<footer>`. The new platform themes do not have these yet.

### `data-gtm` Attribute Dual Role

The `data-gtm` attribute serves two distinct purposes:
1. **String value** — context label (e.g., `"Header"`, `"faq"`, `"filters"`) for ancestor context resolution
2. **JSON object value** — merged as overrides onto the default interaction object (e.g., `{"label":"Name","role":"Actor"}`)

The `sd_gtm_attr()` PHP helper encodes arrays/objects to JSON and escapes for attribute output. The old platform's Backstage component system (`Util::componentAttributesArray()`) also auto-injects `data-gtm` set to the BEM base class name on every component element.

### Specialized Event Types

**Form submissions**: Listens to Gravity Forms' `gform_confirmation_loaded` jQuery event (debounced 500ms). Pushes `{type: 'submit', formID, success: true}`.

**Modal open/close**: Subscribes to `showModal`/`hideModal` actions on the SetDesign hook bus. Label resolved from `$modal.find('#' + $modal.attr('aria-labelledby')).text()`, falling back to the modal's `id` attribute. Pushes `{type: 'modal-open'|'modal-close', label, destination: '#' + id}`.

**Scroll tracking**: On `window.load`, registers `[data-gtm-scroll]` elements and the last `<footer>` for viewport intersection monitoring. Each element fires exactly once when scrolled into view. Uses a polling approach on throttled scroll events (not IntersectionObserver). Pushes `{type: 'scroll-view'}` with full label/context from the element.

**Hash changes**: Subscribes to `HASH_STATE_CHANGE` from the hash-state module. Pushes `{state: {currentHash: '/path'}}` — a variable update, not a triggered event.

### Legacy `data-ga-event` Path

A separate `google-analytics.js` module handles `[data-ga-event]` clicks, sending events to Universal Analytics via the `ga()` tracker. When this module is active, it sets `gtm/disable-ga-click` filter to `true`, preventing tag-manager from also handling these clicks. This is legacy UA-only infrastructure — the new platform should not need it.

## UTM Persistence & Outbound Link Decoration (JS)

### UTM Capture

On page load, extracts the 5 standard UTM parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`) from `window.location.search`. Stores in `localStorage` under key `'utm_params'` as a JSON object. New URL params overwrite stored values (`Object.assign({}, stored, current)`). If no UTM params are present in the URL, localStorage is not touched.

### Link Decoration

`SetDesign.mergeUTMParams(url)` is the public API. It parses a URL, separates UTM from non-UTM query params, merges with localStorage (localStorage wins over existing URL params — inverse of capture behavior), and reconstructs with canonical UTM ordering. Uses manual regex for URL parsing, not the `URL` API. No `dataLayer` involvement — UTM is link-decoration only, completely independent from interaction tracking.

### Outbound Domain Matching

Currently hardcoded to `telecharge.com` in three locations within `telecharge.js`. Static DOM pass at `READY` time decorates existing links; a `TicketCalendar.init` event integration handles dynamically generated ticket URLs. No `MutationObserver` — links injected after initial pass are not decorated (except via the TicketCalendar hook).

**For the new platform**: The domain list needs to be configurable (ACF field), replacing the hardcoded `telecharge.com`. The `TicketCalendar` integration is RLF-specific and has no equivalent in the new platform. A `MutationObserver` approach would be more robust for dynamic content.

### Error Handling Gap

Neither `utm-storage.js` nor `telecharge.js` has try/catch around `localStorage` access or `JSON.parse`. Private browsing mode or corrupt localStorage data would cause uncaught exceptions.

## Cross-Module Integration Map

The old platform's tag-manager is a **subscriber**, not a publisher. Consuming modules emit domain-level events; tag-manager translates them into `dataLayer` pushes. This answers the Problem Brief's deferred responsibility boundary question.

### Old Platform Integration Points

| Module | Event/Mechanism | What Tag-Manager Does |
|---|---|---|
| Modal | `showModal`/`hideModal` actions | Pushes `modal-open`/`modal-close` interaction |
| Video | `video.playing`/`.paused`/`.progress` actions | Pushes `video` interaction with action, provider, url, progress |
| Media Carousel | Direct `GTM_INTERACTION` call from `CarouselGTM.js` | N/A — carousel pushes directly |
| Gravity Forms | `gform_confirmation_loaded` jQuery event | Pushes `submit` interaction |
| Sub-Nav | `data-ga-event` attribute (legacy UA) | Handled by `google-analytics.js`, not tag-manager |
| Talent Cards | `data-gtm` JSON attribute | Generic click handler resolves label/context |
| Tour Cities | `data-gtm` JSON attribute | Generic click handler resolves label/context |
| FAQ / Filters | `data-gtm` string attribute | Context label for DOM walk |
| Theme Header/Footer | `data-gtm` string attribute | Top-level context label |

### New Platform Readiness

| Integration | Status |
|---|---|
| `GTM_INTERACTION` / `GTM_STATE` constants | Defined in UIFramework `constants.mjs` — no handler registered |
| `GFORM_CONFIRM` hook | Already emitted by UIFramework on `gform_confirmation_loaded` — ready to consume |
| `ui-modal-show` / `ui-modal-hide` hooks | Already emitted by UIModal — ready to consume |
| `HASH_STATE_CHANGE` hook | Already emitted by UIFramework — ready to consume |
| Video tracking hooks | Not yet emitted — VideoBlock needs to add `video.playing`/`.paused`/`.progress` hooks |
| Structural `data-gtm` labels | Not present in sitchco-starter or roundabout themes — need to add to header/footer |
| `data-gtm` click tracking | No generic click handler exists — tag-manager module must implement |
| Scroll tracking | No implementation — tag-manager module must implement (use IntersectionObserver) |

## Module Architecture for Sitchco-Core

### Module Structure

```
modules/TagManager/
  TagManager.php              # Module class (ACF-driven, no FEATURES)
  TagManagerSettings.php      # extends OptionsBase for ACF option reads
  acf-json/
    group_<key>.json          # ACF options page field group
  assets/
    scripts/
      main.js                 # Vite entry point
```

### Behavioral Gating

TagManager does not use the `FEATURES` constant. Unlike modules with independent, configuration-toggleable behaviors (e.g., Cleanup), TagManager's behavior is entirely driven by ACF field values:

- **GTM container injection, page metadata, interaction tracking**: Active when `gtm_container_ids` has entries
- **Outbound link decoration + UTM persistence**: Active when `gtm_decorate_outbound` is true and `gtm_outbound_domains` has entries

The module being listed in `sitchco.config.php` is the only on/off toggle. `init()` contains all setup logic, branching on `TagManagerSettings` values.

Script injection is handled by a separate ScriptInjection module (CPT-based).

### PHP Side

- `init()`: Register assets via `$this->registerAssets()`. All behavioral setup branches on `TagManagerSettings` values. The ACF options page and field groups are created in the CMS and synced to `acf-json/` — no PHP registration code.
- GTM container injection (M2): Hook `wp_head` (priority 1) and `wp_body_open` (priority 1) for GTM snippets, gated by `$settings->gtm_container_ids` being non-empty. Pass container config to JS via `$assets->inlineScriptData()`.
- Page metadata push (M3): Hook `wp_head` (priority 0) for `dataLayer` initialization and page metadata push (`wp_post_type`, `wp_post_id`, `wp_slug`), gated by `$settings->gtm_container_ids` being non-empty.

### JS Side

- Register handlers for `GTM_INTERACTION` and `GTM_STATE` hooks from UIFramework constants — these are the public API for other modules to push tracking events
- Subscribe to `ui-modal-show`/`ui-modal-hide` for modal tracking
- Subscribe to `GFORM_CONFIRM` for form submission tracking
- Subscribe to `HASH_STATE_CHANGE` for hash navigation tracking
- Bind delegated click handler on `document` for `data-gtm` / interactive elements
- UTM capture from `window.location.search` into `localStorage`
- Outbound link decoration via static DOM pass + MutationObserver with configurable domain list from ACF settings

### Inter-Module Communication

Tag-manager depends on UIFramework (for the hooks system and constants). Other modules do not depend on tag-manager — they fire domain events through `sitchco.hooks.doAction()` using the constants from UIFramework. Tag-manager subscribes. This preserves the hub-and-spoke pattern from the old platform.

On the PHP side, tag-manager exposes its own hooks via `static::hookName(...)`:
- `sitchco/tag-manager/current-state` — filter to modify page metadata before push
- `sitchco/tag-manager/enable-gtm` — boolean filter to disable GTM injection
- `sitchco/tag-manager/outbound-domains` — filter to modify outbound domain list for link decoration

## Resolved Questions

1. **Environment detection strategy**: `wp_get_environment_type()` is the platform standard. However, the GTM staging snippet feature (swapping head snippet per environment) was rarely used on the old platform and is **deferred**. If the need arises, the module can add it using `wp_get_environment_type() !== 'production'`.

2. **VideoBlock integration timeline**: **Deferred.** Build the tag-manager subscriber infrastructure first. VideoBlock will be updated to emit hooks (`video.playing`/`.paused`/`.progress`) into the existing system when ready.

3. **Roundabout-specific modules**: **Deferred.** `DonationForm`, `Performance`, `Production`, and `Membership` follow the same pattern — core subscriber infrastructure ships first, then each consumer module emits hooks into the system when ready.

4. **`data-gtm` attribute helper**: **Resolved.** Discovery complete — see `docs/discovery/data-gtm-helper/problem-assessment.md`. Approach: Twig function `gtm_attr()` with no-op stub in TimberModule, TagManager replaces callable when active. Structural labels (`Header`, `Footer`) placed explicitly in parent theme templates. Interactive container labels (`Slider`, `Tabs`) placed explicitly by theme developers when the context is analytically meaningful. No auto-injection on block root elements. For blocks using the `block_attributes` convention, TagManager can inject `data-gtm` via template context filter as a convenience. Does not block core TagManager work.

## Recommended Focus

1. **Module scaffold and ACF options page first**: TagManager module class, ACF options page with GTM container config and outbound link decoration toggle + domain repeater.

2. **Core PHP infrastructure second**: GTM container injection and page metadata push — self-contained and require no JS module integration.

3. **Generic interaction tracking JS third**: Click handler with `data-gtm` support, delegated on `document`. This gives immediate value for any element with `data-gtm` attributes.

4. **Hook subscribers fourth**: Wire up `ui-modal-show`/`ui-modal-hide`, `GFORM_CONFIRM`, and `HASH_STATE_CHANGE` — these hooks already exist and need only a subscriber.

5. **UTM/outbound link decoration fifth**: ACF-driven domain configuration with static DOM pass + MutationObserver. Add `localStorage` error handling.

6. **ScriptInjection module last**: Separate module with CPT storage, CodeMirror editor, placement selection, and per-page targeting.

7. **Defer**: Video tracking (waiting on VideoBlock hooks), roundabout theme module tracking (consumers emit hooks when ready), scroll tracking Tier 2/3, legacy `data-ga-event` UA path (obsolete).
