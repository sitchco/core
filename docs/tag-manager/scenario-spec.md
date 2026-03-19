# Scenario Spec: Tag Manager Module

## Axioms

1. **Tag-manager is a subscriber, not a publisher.** Consuming modules emit domain-level events via UIFramework's hook system; tag-manager translates them into `dataLayer` pushes. Other modules do not depend on tag-manager.

2. **The sitchco-core Module architecture is the target structure.** Module class, Vite-built assets, ACF settings, `registerAssets()`, `inlineScriptData()`. TagManager does not use `FEATURES` — ACF field values drive all behavioral branching. The module being enabled/disabled in `sitchco.config.php` is the only toggle.

3. **UIFramework's hook system is the inter-module communication channel.** `sitchco.hooks.doAction()` with constants like `GTM_INTERACTION`, `GTM_STATE`, `GFORM_CONFIRM`.

4. **Several hook integration points already exist and emit events.** `ui-modal-show`/`ui-modal-hide`, `GFORM_CONFIRM`, `HASH_STATE_CHANGE` are already firing and need only a subscriber.

5. **ACF is the registration mechanism for field groups, post types, and taxonomies.** These are created in the ACF admin UI and synced to `acf-json/`. The TagManager options page is registered via `acf_add_options_page()` in PHP to control menu placement (top-level "Tag Manager" menu at position 61, between Appearance and Plugins, with "Settings" and "Custom Tags" submenus). Container IDs and other admin-configurable values use ACF field groups on options pages.

6. **The `data-gtm` attribute convention is dual-purpose.** String values = context labels for DOM ancestry walk. JSON object values = interaction overrides (bare keys mapped internally). `"0"` or `"false"` = opt-out for the element.

7. **Interaction tracking is opt-out.** Every `a`, `button`, `input[type=submit]`, and `[data-button]` is tracked by default. `data-gtm="0"` or `data-gtm="false"` excludes the specific element (not descendants).

8. **This is greenfield — no migration.** No backward compatibility with old platform's GTM containers, event schemas, or configurations.

9. **No reset push needed.** GA4 doesn't need it. With multiple named events (Option A), each event type is self-contained — no stale DL variable cross-contamination between event types.

10. **Pre-GTM `dataLayer.push()` is safe and deterministic.** PHP outputs page metadata in `wp_head` at priority 4, before GTM snippet at priority 5. WordPress full page reloads make load-order deterministic.

## Chosen Approaches

### Outbound Link Configuration: ACF Primary + PHP Filter

ACF toggle ("Decorate outbound links with UTM parameters") and domain repeater on the TagManager options page is the primary configuration path. Placing this alongside GTM container setup creates a natural decision point during site setup. PHP filter `sitchco/tag-manager/outbound-domains` receives ACF values for programmatic override. Decoration uses static DOM pass + MutationObserver (not click-time, to avoid navigation race conditions).

### dataLayer Event Schema: Multiple Named Events (Option A) + Context Walk

Each interaction type fires its own named event: `site_click`, `modal_open`, `modal_close`, `gform_submit`, `hash_change`. Context walk preserved — ancestor `data-gtm` string values collected and joined as `' > '` string for section-level attribution. No reset push.

### Scroll Tracking: Tier 1 — GA4 Native Only

No custom scroll tracking JS at launch. GA4 Enhanced Measurement provides baseline `scroll` event at 90% depth. GTM Scroll Depth triggers available for additional thresholds. Footer auto-observation (Tier 2) can be added when analytics teams confirm the signal's value.

### Custom Tags: Separate CustomTags Module (CPT-Based)

A separate module from TagManager. CPT storage (`sitchco_script` post type) with CodeMirror editor. Semantic placement labels ("Before GTM" / "After GTM" / "Footer"). Per-page targeting via include/exclude rules using `get_queried_object_id()`. Build sequencing deferred to planning.

### Key Convention: Bare Keys in `data-gtm` JSON

`data-gtm='{"label":"Donate"}'` uses bare keys. JS maps to schema-specific parameter names internally. Matches old convention. More ergonomic for content editors.

---

## Scenarios

### Outbound Link Configuration

#### S1. Admin Configures Outbound Domains via ACF

**Trigger:** Admin setting up GTM on a site enables outbound link decoration for a ticketing provider.

**Expected:**
1. On the TagManager ACF options page, admin checks "Decorate outbound links with UTM parameters."
2. Admin enters domain(s) in the repeater field (placeholder: "telecharge.com").
3. PHP applies `apply_filters('sitchco/tag-manager/outbound-domains', $acfDomains)`, passes result to JS via `inlineScriptData`.
4. JS reads `Object.keys(window.sitchco.tagManager.outboundDomains)`.
5. On DOM ready, static pass decorates all `<a>` elements matching configured domains by appending stored UTM parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`) from localStorage to the href.
6. MutationObserver watches for dynamically inserted `<a>` elements and decorates matching ones immediately.
7. Hostname matching: `hostname === domain || hostname.endsWith('.' + domain)` (handles subdomains).

**Must NOT:** Decorate internal links (skip when `link.host === window.location.host`). Use click-time href modification (race condition with browser navigation).

#### S2. No Outbound Domains Configured

**Trigger:** TagManager module enabled but outbound link decoration toggle is off (or on with no domains entered).

**Expected:**
1. JS receives empty object, skips outbound link decoration logic.
2. UTM persistence (localStorage read/write from URL params) still functions independently.

**Must NOT:** Throw errors. Attempt decoration with an empty domain list.

#### S3. Programmatic Domain Override via PHP Filter

**Trigger:** A mu-plugin or plugin needs to add/remove domains without touching ACF.

**Expected:**
1. Code adds `add_filter('sitchco/tag-manager/outbound-domains', function($domains) { ... })`.
2. Filter receives ACF values as the base, returns modified array.
3. Final array passes to JS as normal.

**Must NOT:** Override all sources entirely — filter receives ACF values as the base.

---

### Page Metadata

#### S4. Page Metadata Push

**Trigger:** Every page load, before GTM snippet executes.

**Expected:**
1. PHP outputs `window.dataLayer = window.dataLayer || [];` followed by a push containing `wp_post_type`, `wp_post_id`, and `wp_slug`.
2. Push occurs in `wp_head` at priority 4. GTM snippet loads at priority 5.
3. Push is a non-event Data Layer variable update (no `event` key) — values available to all subsequent triggers.
4. On bfcache restore, metadata persists in the dataLayer from the original page load — no re-push needed.

**Must NOT:** Use `page_title` redundantly (GTM has a built-in Page Title variable). Include an `event` field unless there's a specific trigger need.

---

### Click Interaction Tracking

#### S5. Click on Qualifying Element

**Trigger:** User clicks any `a`, `button`, `input[type=submit]`, or `[data-button]` element not excluded by `data-gtm="0"`.

**Expected:**
1. Delegated click listener on `document` catches the click.
2. `e.target.closest('a, button, input[type=submit], [data-button]')` resolves the qualifying element.
3. Ancestor walk checks for `data-gtm="0"` or `data-gtm="false"` on the resolved element itself — if found, no event fires.
4. Label resolved via priority chain: `data-gtm` JSON `label` key → `aria-label` → `aria-labelledby` text → `title` → `value` → `textContent` (trimmed).
5. Context resolved: walk ancestors collecting `data-gtm` string values, joined as `' > '` string. Truncate from the leaf inward if approaching GA4's 100-character parameter value limit.
6. For anchor elements: `direction` derived from hostname comparison. URL captured from `href`.
7. For non-anchor elements: `direction` and `url` omitted entirely.
8. `data-gtm` JSON object on the element overrides any computed values via bare key mapping.
9. Push `site_click` event to `dataLayer`.

**Must NOT:** Fire on elements with `data-gtm="0"` or `data-gtm="false"`. Require `data-gtm` markup to function — tracking works on all qualifying elements by default. Cause errors when `window.dataLayer` is undefined (guard with `window.dataLayer = window.dataLayer || []`). Cascade opt-out to descendant elements — `data-gtm="0"` only excludes the element itself.

#### S6. Click on Nested Element Inside Qualifying Element

**Trigger:** User clicks an `<img>`, `<span>`, or `<svg>` inside a qualifying `<a>` or `<button>`.

**Expected:**
1. `e.target.closest(...)` walks up from the actual click target to find the nearest qualifying ancestor.
2. Label, context, and all properties resolve from the qualifying ancestor, not the inner element.
3. Event fires as normal.

**Must NOT:** Track the inner element. Miss the click because `e.target` was not itself a qualifying element.

---

### Modal Tracking

#### S7. Modal Open

**Trigger:** UIModal fires `ui-modal-show` hook.

**Expected:**
1. TagManager subscribes to `ui-modal-show` via `sitchco.hooks.doAction()` listener.
2. Modal label resolved from `aria-labelledby` → `modal.id` (UIModal passes native `<dialog>` element).
3. Push `modal_open` event with modal label.

**Must NOT:** Depend on UIModal importing anything from TagManager. Fire if UIModal is not enabled.

#### S8. Modal Close

**Trigger:** UIModal fires `ui-modal-hide` hook.

**Expected:**
1. Same subscription pattern as modal open.
2. Push `modal_close` event with modal label.

**Must NOT:** Fire if the modal was never opened (guard against stale state).

---

### Form Tracking

#### S9. Gravity Forms Confirmation

**Trigger:** UIFramework fires `GFORM_CONFIRM` hook with `formId` (number).

**Expected:**
1. TagManager subscribes, pushes `gform_submit` event with form ID.
2. Event name `gform_submit` avoids GA4's reserved `form_submit`.

**Must NOT:** Fire duplicate events if GA4 Enhanced Measurement "Form interactions" is enabled. (GTM configuration concern — document recommendation to disable GA4 form auto-collection.)

---

### Hash State Tracking

#### S10. Hash State Change

**Trigger:** UIFramework fires `HASH_STATE_CHANGE` hook.

**Expected:**
1. TagManager subscribes, pushes `hash_change` event with hash value.

**Must NOT:** Fire on initial page load hash — only on navigation-triggered changes.

---

### Custom Tags (Separate Module)

#### S11. Pre-GTM Custom Tag Injection (Consent Management Platform)

**Trigger:** Admin needs to add a consent management platform that must load before GTM.

**Expected:**
1. Admin creates a new entry in the `sitchco_script` CPT ("Custom Tags").
2. Enters tag content in CodeMirror editor.
3. Selects placement: "Before GTM."
4. Targets: "All Pages."
5. Module injects the tag in `wp_head` at a priority before the GTM snippet.

**Must NOT:** Depend on GTM being enabled. Fire after GTM loads.

#### S12. Per-Page Custom Tag Targeting

**Trigger:** Admin adds an event-specific tag (ticket widget embed) to a single page.

**Expected:**
1. Admin creates a custom tag entry with include rule targeting specific post(s).
2. On `template_redirect`, module resolves `get_queried_object_id()` and matches against targeting rules.
3. Custom tag injected only on matching pages.

**Must NOT:** Use `get_the_ID()` — returns 0 on archives/search. Must use `get_queried_object_id()`.

#### S13. Custom Tag with No Targeting Rules

**Trigger:** Admin creates a custom tag entry without specifying any include/exclude rules.

**Expected:**
1. Custom tag fires on all pages (default behavior).

**Must NOT:** Require targeting rules to be set — no rules means global.

#### S14. Marketing/Analytics Script via GTM (Not Custom Tags)

**Trigger:** Admin needs to add a tracking pixel (Facebook Pixel, etc.) to the site.

**Expected:**
1. Admin creates a Custom HTML tag in GTM with the pixel snippet.
2. Configures consent via Consent Mode v2.
3. No platform-side action needed.

**Must NOT:** Be handled by the Custom Tags module — tracking scripts with consent requirements belong in GTM.

---

### No-Op Scenarios

#### N1. Click on Non-Qualifying Element

**Trigger:** User clicks a `<div>`, `<p>`, `<span>`, or other non-qualifying element that is not `[data-button]`.

**Expected:** No `site_click` event fires. The element is not in the qualifying selector set.

#### N2. Opt-Out on Parent Does Not Cascade

**Trigger:** `data-gtm="0"` is placed on a `<form>` element. User clicks a child `<a>` inside the form.

**Expected:** The `<a>` click fires normally. `data-gtm="0"` on the parent does not exclude descendant qualifying elements — only the element it's placed on.

#### N3. Initial Page Load with Hash

**Trigger:** User navigates directly to `/page#section` (initial load, not in-page navigation).

**Expected:** No `hash_change` event fires. Hash tracking only fires on navigation-triggered changes, not the initial hash in the URL.

#### N4. GTM Container Disabled

**Trigger:** No GTM container ID configured, or `enable-gtm` filter returns false.

**Expected:** No GTM snippet injected. `window.dataLayer` still initialized. UTM persistence and outbound link decoration still function. dataLayer pushes queue harmlessly. No JS errors.

#### N5. Module Enabled, No Interactions Configured

**Trigger:** TagManager module enabled with default configuration. No `data-gtm` attributes anywhere in markup.

**Expected:** All qualifying elements tracked with auto-resolved labels (textContent, aria-label, etc.). No context strings (no `data-gtm` ancestors to walk). Module functions fully without any markup.

---

## Constraints

1. **No dependencies from other modules to tag-manager.** Tag-manager subscribes; other modules fire events through UIFramework hooks without knowing tag-manager exists.

2. **No JavaScript errors when GTM is disabled.** The module's JS must degrade gracefully if no GTM container is configured.

3. **No performance regressions.** No polling, no heavy DOM traversal on hot paths. Click delegation on `document` is acceptable. No scroll event listeners.

4. **No `data-gtm` markup required to function.** Opt-out model: the system works without markup; `data-gtm` enriches, not enables.

5. **GA4 reserved event names must be avoided.** Custom events must not use: `page_view`, `scroll`, `click`, `form_start`, `form_submit`, `file_download`, `first_visit`, `session_start`, `video_start`, `video_progress`, `video_complete`.

6. **Context string truncation.** If the ancestor context string approaches GA4's 100-character parameter value limit, truncate from the leaf inward to preserve outermost context (more meaningful for section-level attribution).

7. **Custom Tags CPT not publicly accessible.** Configure in ACF with public and publicly queryable disabled.

8. **Custom Tags module does not handle consent.** Tags fire unconditionally. Consent-requiring scripts belong in GTM behind Consent Mode v2 triggers. This is a governance/documentation concern, not enforced by code.

9. **Page caching limitation (Custom Tags).** Full-page caches serve static HTML — PHP-side injection on `template_redirect` won't run on cache hits. Per-page targeted custom tags require cache exclusion rules for affected pages. This is a known operational requirement to document.

## Future Considerations

- **Kadence Blocks integration (tabs/accordions):** Kadence blocks fire their own events. Parent theme would subscribe and emit via UIFramework hooks. TagManager subscribes to those hooks. Out of scope for this module — belongs in the parent theme.

- **Scroll tracking Tier 2 (footer auto-observation):** Single IntersectionObserver on `<footer>` at 50% threshold. ~10 lines of JS. Add when analytics teams confirm the signal's value.

- **Scroll tracking Tier 3 (opt-in section observation):** `[data-gtm-scroll]` attribute support using the same IntersectionObserver. Add when section-level scroll events drive analytics decisions.
