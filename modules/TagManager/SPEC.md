# TagManager Module Spec

## Axioms

1. **WordPress is the integration host.** PHP owns editor-facing settings, render-time HTML emission, and the public filter surface. JS owns runtime behavior and DOM mutation.
2. **PHP → JS handoff is one-way via `inlineScriptData` at `'before'` position.** All module settings cross as a single serialized blob under the `tagManager` namespace on `window.sitchco`.
3. **ACF is the editor configuration surface; PHP filters are the developer-override surface.** Both must be honored. Filter output is a trust boundary and is re-validated on every read.
4. **Container snippets and dataLayer init are deterministic in order.** dataLayer initialization renders before the head snippet; the body snippet renders at `wp_body_open` priority 1. The order is fixed and observable by GTM.
5. **Param-name validation is strict at every trust boundary.** Write-time (ACF validator), read-time (PHP normalization), and emission-time (filter-output normalization) all enforce `^[A-Za-z0-9_-]+$`. Domain-value normalization is permissive (trim + lowercase only) — bad input silently fails to match.
6. **Outbound decoration is opted-in per configured domain.** Unconfigured domains receive no decoration, including the site's own hostname.
7. **The module gates its inline-data emission.** No `outboundDecorator` payload is shipped when the decorate-outbound toggle is off or no domain rows exist.

---

## Chosen Approach

### Module surface

**Class registration:**

| Class | Role |
|---|---|
| `TagManager` | Module entry. Boots at `init`; owns asset registration, hook attachment, snippet emission, dataLayer init, and inline-data emission. Declares `DEPENDENCIES = [UIFramework::class]`. |
| `TagManagerSettings` | ACF options facade. Properties: `gtm_container_ids`, `gtm_decorate_outbound`, `gtm_outbound_domains`. Backed by `OptionsBase`. |
| `OutboundDomainsConfig` | Immutable value object for the outbound decoration config. Named constructors `fromSettings()` and `fromFilterReturn()`; instance methods `toInlineData()` and `isEmpty()`. |
| `ExtraParamsField` | Utility owning the `extra_params` ACF field key, parse/filter helpers, and the ACF validator. Self-registers via `ExtraParamsField::register()`. |

**`TagManager::renderGtmAttribute(mixed $value): string`** — public static helper. Returns `''` for `null`/`''`, ` data-gtm="0"` for `false`/`0`, JSON-encoded HTML-escaped string for arrays/objects, and HTML-escaped string for scalars. Used by the `gtm_attr` Twig function.

**`TagManager::registerOptionsPage(): void`** — registers the ACF options page on `acf/init` at priority 5; menu slot position 61.

### WordPress hooks exposed

All hooks are prefixed via `TagManager::hookName($suffix)`:

| Hook | Type | Default | Purpose |
|---|---|---|---|
| `tag-manager::enable-gtm` | filter | `true` | Master gate. When `false`, `getContainerIds()` returns `[]` and no container snippets render (dataLayer init still renders). |
| `tag-manager::current-state` | filter | output of `getPageMetadata()` | Lets plugins customize the dataLayer.push() payload. Receives the metadata array; must return an array. An empty return suppresses the push entirely. |
| `tag-manager::outbound-domains` | filter | normalized ACF config: `array<string, array{extraParams: string[]}>` | Developer override for the outbound decorator config. Filter output is re-validated (`fromFilterReturn`): non-array/non-object returns fall back with `_doing_it_wrong`; invalid tokens are stripped; case-different duplicate domains trigger `_doing_it_wrong` and last-wins. |
| `acf/validate_value/key=field_69b9be20813a0` | filter | — | The ACF validator attached by `ExtraParamsField::register()`. Receives `(bool\|string $valid, mixed $value)`; returns `true` or an error string identifying the offending token. |
| `timber/twig/functions` | filter (priority 20) | — | Registers the `gtm_attr` Twig function. |
| `wp_head` priority 4 | action | — | `renderDataLayerInit()` |
| `wp_head` priority 5 | action | — | Renders one GTM head snippet per container ID. |
| `wp_body_open` priority 1 | action | — | Renders one GTM noscript snippet per container ID. |

### ACF settings model

ACF group `group_69b9bd38ce5fb`, scoped to the Tag Manager options page.

| Field | Key | Type | Notes |
|---|---|---|---|
| `gtm_container_ids` | `field_69b9bd38812fc` | Repeater (table) | One sub-field `container_id` (text). Rows produce one GTM container script each, deduped and trimmed on read. |
| `gtm_decorate_outbound` | `field_69b9bdbd812fe` | True/False | Master toggle for outbound decoration. Conditionally reveals `gtm_outbound_domains`. |
| `gtm_outbound_domains` | `field_69b9bde4812ff` | Repeater (table) | Conditionally shown when decorate-outbound is on. Sub-fields below. |
| └─ `domain` | `field_69b9be0381300` | Text | Hostname only. Editor instruction: *"Hostname only — no `https://`, no path, no port. Subdomains are matched automatically (e.g. `partner.com` also matches `www.partner.com` and `shop.partner.com`)."* |
| └─ `extra_params` | `field_69b9be20813a0` | Text | Comma-separated param names. Validator rejects tokens that don't match `^[A-Za-z0-9_-]+$`. Editor instruction: *"Comma-separated param names to forward to this domain in addition to the 5 UTM params. Names must match `^[A-Za-z0-9_-]+$`. Leave blank to forward UTM defaults only."* |

### Wire shape (PHP → JS)

The module emits a single inline-data blob via `ModuleAssets::inlineScriptData()`. The blob is gated: it is only emitted when `OutboundDomainsConfig::fromSettings()->isEmpty()` is false.

```js
window.sitchco = window.sitchco || {};
window.sitchco.tagManager = {
    outboundDecorator: {
        domains: {
            'partner.com': { extraParams: ['tess', 'session_hash'] },
            'shop.partner.com': { extraParams: ['shop_id'] },
            'other.com': { extraParams: [] }
        }
    }
};
```

- The outer key (`outboundDecorator`) is fixed.
- Domain keys are normalized to **lowercased, trimmed** form. Duplicates collapse last-wins.
- Each entry is `{ extraParams: string[] }`. The list is deduped, regex-validated, and may be empty (the domain is still opted into UTM-default decoration).

### JS bridge public API

`assets/scripts/main.js` registers on the framework's `REGISTER` action with hook namespace `'tag-manager'`. After registration, the following surfaces are attached to `window.sitchco.tagManager`:

| Surface | Type | Contract |
|---|---|---|
| `outboundDecorator` | object (read-only) | Inline-data payload (present only when emission gate passes). Shape per the table above. |
| `updateOutboundDecorator(values)` | function | Imperative runtime updater. Same contract as the underlying `handle.update()` from `@sitchco/datalayer`: filters keys through the union allowlist, writes to storage, schedules debounced re-decoration. |
| `clearOutboundDecorator(keys?)` | function | Imperative runtime clear. Same contract as the underlying `handle.clear()`: with `keys`, removes those keys; without args, wipes all library-written params (author params untouched). |

The bridge boots the decorator and trackers in this order on `REGISTER`:

1. Bind `pushEvent` to the framework's `GTM_INTERACTION` and `GTM_STATE` action hooks.
2. Register `click`, `modal`, `form`, `hash`, and `video` trackers; each receives `pushEvent`.
3. Read `window.sitchco.tagManager.outboundDecorator.domains` (or `{}`), transform the record into an `OutboundDecoratorConfig` array, and call `registerOutboundDecorator(config)`.
4. Call `captureUrlParams()` (no-op if no decorator is registered).
5. Attach `updateOutboundDecorator` and `clearOutboundDecorator` to `window.sitchco.tagManager`.

The bridge does **not** pass a `beforeResolve` to `registerClickTracker`; it relies on the package default (yields one `requestAnimationFrame`), which is sufficient for the common case of framework ARIA flips.

### Twig functions

| Function | Signature | Behavior |
|---|---|---|
| `gtm_attr(value)` | `(mixed) => string` | Delegates to `TagManager::renderGtmAttribute()`. Renders a `data-gtm="…"` attribute (with leading space) suitable for inline placement on an element. Returns `''` when the attribute should be omitted entirely. |

### DataLayer init

`renderDataLayerInit()` runs at `wp_head` priority 4. It emits:

```html
<script>
window.dataLayer=window.dataLayer||[];
window.dataLayer.push({…page metadata…});
</script>
```

The `push(...)` is **omitted** when filtered page metadata is empty. The init script (`window.dataLayer=window.dataLayer||[]`) is always emitted (provides the buffer GTM expects).

Page-metadata defaults from `getPageMetadata()`:

| Queried object | Pushed keys |
|---|---|
| `WP_Post` | `wp_post_type`, `wp_post_id`, `wp_slug` |
| `WP_Term` | `wp_taxonomy`, `wp_term_id`, `wp_slug` |
| `WP_Post_Type` | `wp_post_type`, `wp_slug` |
| Other / 404 | `[]` (push suppressed) |

**Critical:** The pushed object has **no `event` key**. It is metadata-only and does not fire a GTM tag by itself.

### Container snippets

Each container ID emits one head snippet (the official GTM loader) and one body snippet (the noscript iframe fallback). Container IDs are read from `gtm_container_ids`, trimmed, falsy-filtered, and deduped. The head snippet's ID passes through `esc_js()`; the body snippet's ID passes through `esc_attr()`.

### Outbound decoration semantics

The PHP side normalizes domains as **trim + lowercase**. Param tokens must match `/^[A-Za-z0-9_-]+$/D`. Matching, allowlist construction, link mutation, and storage all live in `@sitchco/datalayer`; this module's responsibility ends at producing the wire shape and wiring the JS handle.

Implicit subdomain matching applies (handled by the package): configuring `partner.com` always matches `partner.com` and every subdomain. There is no exclusion syntax.

---

## Constraints (Must-NOT)

1. **Must not emit the `outboundDecorator` payload when no domains are configured.** The inline-data gate (`isEmpty()`) is the sole authority. A payload with `{ domains: {} }` is never emitted.
2. **Must not trust ACF storage without re-validation.** `OutboundDomainsConfig::fromSettings()` re-strips invalid tokens via `ExtraParamsField::filterTokens()` even after ACF write-time validation.
3. **Must not trust filter output without re-validation.** `fromFilterReturn()` is the choke point: malformed root → fall back + `_doing_it_wrong`; non-array entries skipped; non-string `extraParams` coerced to `[]`; invalid tokens silently stripped; case-different duplicates trigger `_doing_it_wrong`.
4. **Must not silently accept an invalid token name on ACF save.** `ExtraParamsField::validateExtraParams()` returns an error string identifying the offending token; the save is rejected.
5. **Must not render GTM snippets when `tag-manager::enable-gtm` returns false.** Even with container IDs configured, the gate suppresses all snippet output.
6. **Must not render the dataLayer push when filtered metadata is empty.** The init buffer is still emitted (`dataLayer=dataLayer||[]`), but no `push()` call is made.
7. **Must not allow `data-gtm` attribute output to inject unescaped HTML.** Scalar values pass through `esc_attr`; collections are JSON-encoded then `esc_attr`.
8. **Must not include an `event` key in the page-metadata dataLayer push.** The initial push is reserved for context, not for tag triggering.
9. **Must not collide the head/body snippet escaping contexts.** Head uses `esc_js()` (JavaScript string context); body uses `esc_attr()` (HTML attribute context).

---

## Scenarios

### Configuration

#### S1. Editor saves an `extra_params` value via ACF

**Trigger:** Admin types into the `extra_params` text sub-field on a domain row (e.g. `tess, session_hash`).

**Expected:**
1. ACF runs `ExtraParamsField::validateExtraParams()` on the value.
2. The validator splits on commas, trims each token, and tests each against `^[A-Za-z0-9_-]+$`.
3. If every token is valid (or the field is empty), the save proceeds.
4. On next page render, `OutboundDomainsConfig::fromSettings()` re-parses the stored CSV and re-strips invalid tokens (belt-and-suspenders).
5. The validated config is emitted to JS via `inlineScriptData`.

**Must NOT:**
- Accept a token containing whitespace, `=`, `.`, `<`, `>`, or any character outside the regex.
- Trust the stored value without re-validating on read.

#### S2. Editor saves an invalid `extra_params` token

**Trigger:** Admin enters `tess, bad token, session_hash`.

**Expected:** ACF rejects the save and surfaces an error string naming the first invalid token. The stored value is unchanged.

**Must NOT:** Persist the partially valid CSV.

#### S3. Editor leaves `extra_params` blank for a configured domain

**Trigger:** Domain row with `domain=partner.com`, `extra_params=""`.

**Expected:** The wire-shape entry is `'partner.com' => { extraParams: [] }`. The decorator forwards UTM defaults only to that domain.

**Must NOT:** Treat the empty field as "exclude this domain from decoration."

#### S4. Editor disables the master toggle

**Trigger:** `gtm_decorate_outbound` is false.

**Expected:** `OutboundDomainsConfig::fromSettings()` returns an empty config. No inline-data payload is emitted. The `tag-manager::outbound-domains` filter is **not** invoked.

**Must NOT:** Emit `window.sitchco.tagManager.outboundDecorator` in any form.

#### S5. Developer overrides the outbound config via filter

**Trigger:** `add_filter('tag-manager::outbound-domains', fn($entries) => [...])` returns a nested `Record<string, { extraParams: string[] }>`.

**Expected:**
1. The filter receives the post-ACF normalized config.
2. `fromFilterReturn()` re-validates: domain keys are trimmed + lowercased; non-array entries are skipped; `extraParams` is coerced to `[]` if non-array; tokens are stripped through the regex; case-different duplicates trigger `_doing_it_wrong` and last-wins.
3. The validated value object's `toInlineData()` is shipped to JS.

**Must NOT:**
- Pass filter output through to JS unvalidated.
- Crash on wrong-shape filter output (see S6).

#### S6. Developer returns wrong-shape data from the filter

**Trigger:** Filter returns a non-array, non-object scalar (e.g. a string).

**Expected:** `_doing_it_wrong()` is emitted; the unfiltered (ACF-derived) config is used as the fallback.

**Must NOT:** Pass the malformed value through; crash; or silently accept it.

#### S7. Editor enters a domain with mixed case or surrounding whitespace

**Trigger:** `domain = "  Partner.COM  "`.

**Expected:** Normalized to `partner.com` in the wire payload. No validation error.

**Must NOT:** Persist or emit the unnormalized form.

#### S8. Editor enters two domain rows that normalize to the same key

**Trigger:** Row 1: `partner.com` / row 2: `PARTNER.COM`.

**Expected:** The wire payload contains a single `partner.com` entry; the last row's `extra_params` wins. From ACF path the collision is silent (the repeater UI shows both rows visually). From the filter path a `_doing_it_wrong` notice fires.

**Must NOT:** Emit two separate entries for the same normalized domain.

### Rendering

#### S9. Single configured container ID

**Trigger:** `gtm_container_ids = [{ container_id: 'GTM-XXXX' }]`.

**Expected:** One head snippet (official GTM loader with `GTM-XXXX` via `esc_js`) renders at `wp_head` priority 5. One body noscript snippet renders at `wp_body_open` priority 1 (with `GTM-XXXX` via `esc_attr`).

**Must NOT:** Emit a snippet without escaping the container ID per its context.

#### S10. Multiple configured container IDs

**Trigger:** Two distinct container IDs configured.

**Expected:** Two head snippets and two body snippets, one each per container, emitted in config order.

#### S11. Duplicate container IDs

**Trigger:** Same `GTM-XXXX` appears in two repeater rows.

**Expected:** Deduped on read; rendered exactly once in head and once in body.

#### S12. No container IDs configured

**Trigger:** `gtm_container_ids = []`.

**Expected:** No head or body snippets render. `renderDataLayerInit()` still emits the init buffer (and the metadata push if non-empty).

#### S13. `tag-manager::enable-gtm` returns false

**Trigger:** Developer attaches `add_filter('tag-manager::enable-gtm', '__return_false')`.

**Expected:** `getContainerIds()` returns `[]`; no head/body snippets render despite configured containers. `renderDataLayerInit()` still runs.

#### S14. Queried object is a published `WP_Post`

**Trigger:** Visitor lands on a single-post page.

**Expected:** `renderDataLayerInit()` emits `dataLayer=dataLayer||[]` and pushes `{ wp_post_type, wp_post_id, wp_slug }`.

**Must NOT:** Include an `event` key in the push.

#### S15. Queried object is a `WP_Term`

**Trigger:** Visitor lands on a taxonomy archive.

**Expected:** Push contains `{ wp_taxonomy, wp_term_id, wp_slug }`.

#### S16. Queried object is a `WP_Post_Type`

**Trigger:** Visitor lands on a post-type archive.

**Expected:** Push contains `{ wp_post_type, wp_slug }`. No `wp_post_id`.

#### S17. 404 / no queried object

**Trigger:** 404 page, or query resolves to no queried object.

**Expected:** `renderDataLayerInit()` emits the init buffer only. No `dataLayer.push()` call.

#### S18. `tag-manager::current-state` filter modifies the metadata

**Trigger:** Plugin adds `add_filter('tag-manager::current-state', fn($d) => array_merge($d, ['custom' => 'value']))`.

**Expected:** Push contains the merged custom field alongside the defaults.

**Must NOT:** Filter the value when no metadata exists (the push is suppressed regardless of filter intent if the final array is empty).

### Inline-data emission

#### S19. Outbound config is non-empty

**Trigger:** Decorate-outbound on; at least one valid domain row.

**Expected:** `window.sitchco.tagManager.outboundDecorator = { domains: {...} }` is serialized into a script tag attached before `main.js`.

#### S20. Outbound config is empty

**Trigger:** Decorate-outbound on but no rows; or decorate-outbound off; or all rows malformed (empty after normalization).

**Expected:** No `outboundDecorator` payload is emitted. `window.sitchco.tagManager` may still exist (the bridge creates it), but `outboundDecorator` is `undefined`. The bridge's `domainsRecord` falls back to `{}`, and `registerOutboundDecorator` is called with an empty config (no-op handle).

### Twig

#### S21. `gtm_attr` with a string

**Trigger:** Template renders `{{ gtm_attr('Header CTA') }}`.

**Expected:** Output is ` data-gtm="Header CTA"` (leading space, value HTML-escaped).

#### S22. `gtm_attr` with an array

**Trigger:** `{{ gtm_attr({label: 'Donate', role: 'cta'}) }}`.

**Expected:** Output is ` data-gtm="{&quot;label&quot;:&quot;Donate&quot;,&quot;role&quot;:&quot;cta&quot;}"`.

#### S23. `gtm_attr` with `null` or empty string

**Trigger:** `{{ gtm_attr(null) }}` or `{{ gtm_attr('') }}`.

**Expected:** Empty string (no attribute emitted).

#### S24. `gtm_attr` with `0` or `false`

**Trigger:** `{{ gtm_attr(0) }}` or `{{ gtm_attr(false) }}`.

**Expected:** ` data-gtm="0"` — the opt-out marker that downstream JS recognizes.

### Bridge / runtime API

#### S25. Page boot wires the decorator and handle

**Trigger:** `main.js` runs after the framework's `REGISTER` action fires.

**Expected:**
1. `pushEvent` is bound to `GTM_INTERACTION` and `GTM_STATE` hooks.
2. Click / modal / form / hash / video trackers are registered.
3. `registerOutboundDecorator(config)` is called with the transformed config (record → array).
4. `captureUrlParams()` runs after registration.
5. `window.sitchco.tagManager.updateOutboundDecorator` and `.clearOutboundDecorator` are attached.

**Must NOT:** Call `captureUrlParams()` before `registerOutboundDecorator()` (the package would no-op).

#### S26. Consumer calls `updateOutboundDecorator({...})` at runtime

**Trigger:** After identity resolution, consumer code calls `window.sitchco.tagManager.updateOutboundDecorator({ vid: 'abc' })`.

**Expected:** Delegates to `handle.update`. Keys outside the union allowlist are silently dropped. Allowed keys are merged into the in-memory params, written to `localStorage` under the package's storage key, and trigger a debounced full-document re-decoration.

#### S27. Consumer calls `clearOutboundDecorator()`

**Trigger:** Consumer calls `window.sitchco.tagManager.clearOutboundDecorator()` (no args).

**Expected:** All library-written params are removed from storage; on next debounced pass all decorated links are stripped of those params. Author-placed URL params remain.

---

## Edge Cases

| Edge case | Behavior |
|---|---|
| Empty `extra_params` CSV | Parsed to `[]`; domain still emitted (UTM-default decoration). |
| Whitespace-only `extra_params` (e.g. `,  ,`) | Parsed to `[]` after trim + empty-filter. |
| Duplicate tokens in CSV (`tess, tess`) | `array_unique` dedupes silently. |
| Invalid token at ACF save | `validateExtraParams` returns the error string naming the offending token; save rejected. |
| Invalid token from PHP filter return | `filterTokens` strips it silently on read. |
| Non-array filter root | `_doing_it_wrong` + fallback to unfiltered ACF config. |
| Non-array filter entry | Skipped. |
| Non-array `extraParams` in filter entry | Coerced to `[]`. |
| Integer or empty-string domain key in filter return | Skipped by `buildEntry`. |
| Domain entered with `https://`, path, port, or leading dot | Passed through unchanged; silently fails to match at runtime. No format validation. |
| Domain entered with mixed case / surrounding whitespace | Trimmed and lowercased on read. |
| Case-different duplicate domains from ACF | Repeater shows both rows; wire payload last-wins. No editor warning. |
| Case-different duplicate domains from filter return | `_doing_it_wrong` + last-wins. |
| `gtm_container_ids` row with whitespace or empty `container_id` | Trim + falsy-filter removes it before render. |
| Duplicate `container_id` values | `array_unique` dedupes; one head + one body snippet per unique ID. |
| `tag-manager::current-state` filter returns `[]` | Init buffer still emits; `dataLayer.push()` is suppressed. |
| `tag-manager::current-state` filter receives `[]` (404 case) | Filter still runs; if it returns non-empty, the push is emitted. |
| `gtm_attr(0)` vs `gtm_attr(null)` | `0` → `data-gtm="0"` (opt-out marker); `null` → no attribute at all. |
| `gtm_attr(array)` with deeply nested values | `wp_json_encode` then `esc_attr` — safe for HTML attribute context. |
| Inline-data emission when bridge JS has not yet loaded | `inlineScriptData` is attached before `main.js`; the bridge reads it on `REGISTER`. |
| `window.sitchco.tagManager` is mutated by other code before the bridge runs | The bridge uses `?? {}` and additive assignment, so it composes rather than overwrites. |

---

## Out of Scope

- **Subdomain exclusion syntax.** Configuring `partner.com` always matches all subdomains. No syntax to scope to an exact hostname only.
- **Domain-value format validation.** No rejection of protocol, path, port, leading dot, or other non-hostname syntax. Bad input silently no-matches; editors self-correct on testing.
- **Per-param metadata in ACF** (capture-only-if-set, expiry, labels). The CSV sub-field is intentionally flat.
- **Wildcard or regex domain matching.** Exact + parent-domain match is the only matching mode.
- **Multi-consumer coordination on the JS side.** The bridge assumes single consumer per page; storage clobber across consumers is documented as unsupported by the package.
- **Editor warning UI for case-different duplicate domain rows.** Surfaced via `_doing_it_wrong` only; no admin notice.
- **Limits on token count or token name length** (beyond the character regex).
- **Token order normalization.** Tokens preserve CSV order after dedup.
- **Runtime control over container IDs.** No JS API to add/remove containers after page load.
- **An `event` key in the page-metadata dataLayer push.** The push is metadata-only by contract.
