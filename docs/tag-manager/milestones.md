# Milestones: Tag Manager & Script Injection Modules

## TagManager Module

- [X] **M1: TagManager module scaffold and ACF options page** — Module class with asset registration and ACF options page for GTM settings. All behavioral branching driven by ACF field values — no per-feature toggles in config. ACF field group includes GTM container ID(s) repeater and outbound link decoration toggle + domain repeater. `🤝 Collaborative: ACF field groups created manually in admin UI.`

- [X] **M2: GTM container injection** — GTM head snippet and body `<noscript>` iframe render on the frontend, driven by ACF-configured container ID(s). Multiple containers supported. `enable-gtm` filter can disable injection. No staging snippet logic (deferred).

- [X] **M3: Page metadata push** — `dataLayer` initialized and populated with `wp_post_type`, `wp_post_id`, and `wp_slug` in `wp_head` at priority 4, before the GTM snippet. Non-event variable update (no `event` key).

- [X] **M4: Click interaction tracking** — Delegated click handler on `document` tracks all qualifying elements (`a`, `button`, `input[type=submit]`, `[data-button]`). Labels auto-resolved from aria/text/title. `data-gtm` string values provide context labels via ancestor walk. `data-gtm` JSON values override interaction properties. `data-gtm="0"` opts out the element. Pushes `site_click` event to `dataLayer`.

- [X] **M5: `data-gtm` attribute helper** — Twig function `gtm_attr()` with no-op stub registered in TimberModule (returns `''` when TagManager is disabled). TagManager replaces the callable when active to return `data-gtm="..."` attribute markup. Structural context labels (`Header`, `Footer`, `Main`) added to parent theme layout templates. `🤝 Collaborative: deciding which parent theme templates get structural labels.`

- [X] **M6: Hook subscribers (modal, form, hash)** — TagManager subscribes to existing UIFramework hooks: `ui-modal-show`/`ui-modal-hide` pushes `modal_open`/`modal_close`, `GFORM_CONFIRM` pushes `gform_submit`, `HASH_STATE_CHANGE` pushes `hash_change`. All events fire correctly and appear in `dataLayer`.

- [X] **M7: UTM persistence and outbound link decoration** — URL UTM parameters captured into `localStorage` on page load. When outbound link decoration is enabled via ACF toggle on the TagManager options page, matching outbound links are decorated with stored UTM params via static DOM pass on load + MutationObserver for dynamically inserted links. No click-time modification (avoids navigation race conditions). Domains entered in ACF repeater (placeholder: "telecharge.com"). `sitchco.config.php` and PHP filter available as programmatic overrides. Subdomain matching works. UTM capture functions independently of decoration being enabled. `localStorage` errors handled gracefully.

## ScriptInjection Module

- [ ] **M8: Script CPT and admin editor** — `sitchco_script` CPT created via ACF admin UI (not public, not queryable), synced to `acf-json/`. Admin UI with CodeMirror editor for script content. Placement selector: "Before GTM" / "After GTM" / "Footer". `🤝 Collaborative: CPT, field groups, and options pages created manually in admin UI.`

- [ ] **M9: Script rendering with placement** — Scripts inject at the correct location in the page based on placement selection. "Before GTM" renders in `wp_head` before the GTM snippet. "After GTM" renders after. "Footer" renders in `wp_footer`.

- [ ] **M10: Per-page targeting** — Include/exclude rules using post relationship field. Targeting resolved via `get_queried_object_id()` (not `get_the_ID()`). No rules = fires on all pages. Scripts with targeting rules only render on matching pages.

## Deferred

- GTM staging snippet (environment-specific container swapping)
- Video tracking (waiting on VideoBlock hook emission)
- Roundabout theme module tracking (DonationForm, Performance, Production, Membership)
- Scroll tracking Tier 2/3 (footer observation, `[data-gtm-scroll]`)
