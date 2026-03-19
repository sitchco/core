# Browser Test Procedure: Tag Manager Module

## Environment

- **Site URL:** `https://roundabout.test/`
- **DDEV project root:** `public/` (not `roundabout/`)
- **Plugin CWD:** `public/wp-content/mu-plugins/sitchco-core/`
- **Scenario spec:** `docs/tag-manager/scenario-spec.md`
- **Build command:** `make build` from `public/`
- **Cache flush:** `ddev wp cache flush` from `public/`

## Lessons Learned

These tips prevent re-discovery of issues encountered during initial testing.

### playwright-cli

- **Console logs from `<head>` scripts are not captured.** Scripts that execute synchronously in `<head>` (custom tags, dataLayer init) run before the playwright-cli console listener attaches. Check `document.head.innerHTML` instead of console logs to verify these.
- **Clicking links causes navigation.** To test `site_click` dataLayer events on anchor elements, prevent default navigation first:
  ```js
  playwright-cli run-code "async page => { await page.evaluate(() => { document.querySelector('a[href*=\"target\"]').addEventListener('click', e => e.preventDefault(), { once: true }); }); }"
  ```
- **Use `run-code` for complex evaluations.** `playwright-cli eval` fails on arrow functions and complex expressions. Use `playwright-cli run-code "async page => { ... }"` instead.
- **Hash links open new tabs.** Social links with `href="#"` may open new tabs. Use `playwright-cli tab-close 1` to clean up.

### DDEV / WordPress

- **Flush cache after CMS changes.** Custom tags use `Cache::remember()`. After creating/editing custom tags or changing Tag Manager settings: `ddev wp cache flush` from `public/`.
- **Also flush transients if cache alone doesn't work:** `ddev wp transient delete --all` from `public/`.
- **Use `ddev wp` for WP-CLI.** All WP-CLI commands go through DDEV. Run from `public/`.

### dataLayer Inspection Patterns

Check page metadata (always index 0):
```bash
playwright-cli run-code "async page => { const meta = await page.evaluate(() => window.dataLayer[0]); return JSON.stringify(meta, null, 2); }"
```

Filter by event type:
```bash
playwright-cli run-code "async page => { const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'site_click')); return JSON.stringify(events, null, 2); }"
```

Check all event names:
```bash
playwright-cli run-code "async page => { const all = await page.evaluate(() => window.dataLayer.map(e => e.event).filter(Boolean)); return JSON.stringify(all); }"
```

Check outbound domain config:
```bash
playwright-cli run-code "async page => { const config = await page.evaluate(() => window.sitchco && window.sitchco.tagManager); return JSON.stringify(config, null, 2); }"
```

Check if script exists in `<head>`:
```bash
playwright-cli run-code "async page => { const html = await page.evaluate(() => document.head.innerHTML); return JSON.stringify({ hasScript: html.includes('YOUR_MARKER') }); }"
```

---

## Test Setup

### CMS State Required

These items must be configured in WordPress before testing. Verify with `ddev wp` commands.

**Tag Manager Settings** (Tag Manager > Settings in admin):
- GTM Container ID: any valid ID (e.g. `GTM-KRWFBSTC`)
- "Decorate outbound links with UTM parameters": **enabled** (for S1 tests, disabled for S2)
- Outbound Domains: `youtube.com` (or any domain that appears in links on test pages)

**Custom Tags** (Tag Manager > Custom Tags in admin):

| ID | Title | Content | Placement | Targeting |
|----|-------|---------|-----------|-----------|
| 2356 | Test Script | `<script>console.log("Hello World");</script>` | After GTM | None (global) |
| 2364 | Before GTM Test | `<script>console.log("BEFORE-GTM-TAG");</script>` | Before GTM | None (global) |
| 2365 | Per-Page Test | `<script>console.log("PER-PAGE-ABOUT-ONLY");</script>` | After GTM | Only Include On: About (ID 916) |

Verify custom tags exist:
```bash
ddev wp post list --post_type=sitchco_script --fields=ID,post_title,post_status --format=table
```

### Test Pages

| Page | URL | Used For |
|------|-----|----------|
| Home | `https://roundabout.test/` | S4, S5, S9, S13, N1, N5 |
| About | `https://roundabout.test/about/` | S11, S12, N3 |
| Modal Tests | `https://roundabout.test/modal-tests/` | S7, S8 |
| Video Test | `https://roundabout.test/video-test/` | S1, S7, S8 (video modal), S15–S18 |
| Fallen Angels | `https://roundabout.test/production/fallen-angels/` | S4 (custom post type), S10 |
| GTM Test - Opt Out | `https://roundabout.test/gtm-test-opt-out/` | N2 |
| Privacy Policy | `https://roundabout.test/privacy-policy/` | (navigation target) |

---

## Scenarios

### S1. Outbound Link Decoration — PASS

**Prereq:** Outbound decoration enabled with `youtube.com` in Tag Manager settings. UTM params in localStorage.

**Setup:**
```bash
playwright-cli goto https://roundabout.test/video-test/
```

Verify config is present:
```bash
playwright-cli run-code "async page => { const config = await page.evaluate(() => window.sitchco && window.sitchco.tagManager); return JSON.stringify(config, null, 2); }"
```
Expected: `{ "outboundDomains": { "youtube.com": true } }`

Seed UTM params if needed (then reload):
```bash
playwright-cli run-code "async page => { await page.evaluate(() => { localStorage.setItem('utm_params', JSON.stringify({ utm_source: 'roundabout', utm_medium: 'website', utm_campaign: 'test' })); }); }"
playwright-cli reload
```

**Test:** Inject a matching outbound link and verify decoration:
```bash
playwright-cli run-code "async page => { const result = await page.evaluate(() => { const a = document.createElement('a'); a.href = 'https://www.youtube.com/watch?v=abc123'; a.textContent = 'Watch on YouTube'; a.id = 'test-outbound'; document.body.appendChild(a); return new Promise(resolve => { setTimeout(() => { const el = document.getElementById('test-outbound'); resolve({ href: el.href, decorated: el.href.includes('utm_') }); }, 500); }); }); return JSON.stringify(result, null, 2); }"
```
Expected: `decorated: true`, href contains `utm_source`, `utm_medium`, `utm_campaign`.

**Verify internal links NOT decorated:**
```bash
playwright-cli run-code "async page => { const result = await page.evaluate(() => { const a = document.createElement('a'); a.href = '/about/'; a.id = 'test-internal'; document.body.appendChild(a); return new Promise(resolve => { setTimeout(() => { resolve({ href: document.getElementById('test-internal').href, decorated: document.getElementById('test-internal').href.includes('utm_') }); }, 500); }); }); return JSON.stringify(result); }"
```
Expected: `decorated: false`

**Verify non-matching external links NOT decorated:**
```bash
playwright-cli run-code "async page => { const result = await page.evaluate(() => { const a = document.createElement('a'); a.href = 'https://www.google.com/search?q=test'; a.id = 'test-nomatch'; document.body.appendChild(a); return new Promise(resolve => { setTimeout(() => { resolve({ decorated: document.getElementById('test-nomatch').href.includes('utm_') }); }, 500); }); }); return JSON.stringify(result); }"
```
Expected: `decorated: false`

---

### S2. No Outbound Domains Configured — PASS

**Prereq:** Outbound decoration toggle OFF in Tag Manager settings. Flush cache.

```bash
playwright-cli goto https://roundabout.test/
playwright-cli eval "typeof window.sitchco.tagManager"
```
Expected: `"undefined"` — no inline data emitted, no JS errors.

---

### S4. Page Metadata Push — PASS

**Test on a page:**
```bash
playwright-cli goto https://roundabout.test/
playwright-cli run-code "async page => { const meta = await page.evaluate(() => window.dataLayer[0]); return JSON.stringify(meta, null, 2); }"
```
Expected: `{ "wp_post_type": "page", "wp_post_id": 2, "wp_slug": "home" }`

**Test on a custom post type:**
```bash
playwright-cli goto https://roundabout.test/production/fallen-angels/
playwright-cli run-code "async page => { const meta = await page.evaluate(() => window.dataLayer[0]); return JSON.stringify(meta, null, 2); }"
```
Expected: `{ "wp_post_type": "production", "wp_post_id": 586, "wp_slug": "fallen-angels" }`

**Verify metadata appears before GTM (index 0 in dataLayer, GTM events start at index 1+).**

---

### S5. Click on Qualifying Element — PASS

**Test link click:**
```bash
playwright-cli goto https://roundabout.test/
```
Prevent navigation, then click:
```bash
playwright-cli run-code "async page => { await page.evaluate(() => { document.querySelector('a[href*=\"privacy-policy\"]').addEventListener('click', e => e.preventDefault(), { once: true }); }); }"
playwright-cli click e111
playwright-cli run-code "async page => { const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'site_click')); return JSON.stringify(events, null, 2); }"
```
Expected: `{ event: "site_click", click_label: "Privacy Policy", click_direction: "internal", click_url: "/privacy-policy/" }`

Note: Element refs (e111, etc.) change between page loads. Use the snapshot to find current refs.

**Test button click (non-anchor):**
Click the Submit button on the homepage form.
Expected: `{ event: "site_click", click_label: "Submit" }` — no `click_direction` or `click_url` (correct for non-anchor elements).

**Test outbound click direction:**
After S1 setup, click the injected YouTube link.
Expected: `click_direction: "outbound"`

---

### S6. Nested Click Inside Qualifying Element — PASS

Click the `<img>` (e73) inside the "RTC on Facebook" `<a>` (e71) in the footer:
```bash
playwright-cli click e73
```
Expected: Event resolves from the `<a>` ancestor: `click_label: "RTC on Facebook"` (from aria-label).

Note: Ref numbers are from an earlier session. Get current refs from snapshot.

---

### S7. Modal Open — PASS

```bash
playwright-cli goto https://roundabout.test/modal-tests/
playwright-cli click e60
```
(Click the "Newsletter" link — `href="#newsletter-modal"`)

```bash
playwright-cli run-code "async page => { const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'modal_open')); return JSON.stringify(events, null, 2); }"
```
Expected: `{ event: "modal_open", modal_label: "Sign up for our Newsletter" }`

**Video modal variant:**
```bash
playwright-cli goto https://roundabout.test/video-test/
playwright-cli click e48
```
(Click "Play video: Education at Roundabout 2026")
Expected: `{ event: "modal_open", modal_label: "Education at Roundabout 2026" }`

---

### S8. Modal Close — PASS

After opening a modal (S7), click the close button:
```bash
playwright-cli snapshot
```
Find the "Close modal" button ref in the snapshot, then:
```bash
playwright-cli click <close-button-ref>
playwright-cli run-code "async page => { const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'modal_close')); return JSON.stringify(events, null, 2); }"
```
Expected: `{ event: "modal_close", modal_label: "<same label as modal_open>" }`

---

### S9. Gravity Forms Confirmation — PASS

```bash
playwright-cli goto https://roundabout.test/
playwright-cli fill e57 "Test User"
playwright-cli click e60
```
Wait for AJAX confirmation:
```bash
playwright-cli run-code "async page => { await page.waitForTimeout(3000); const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'gform_submit')); return JSON.stringify(events, null, 2); }"
```
Expected: `{ event: "gform_submit", form_id: 5 }`

Note: Only works with AJAX-enabled Gravity Forms. The `gform_confirmation_loaded` jQuery event does not fire for non-AJAX forms.

---

### S10. Hash State Change — PASS

```bash
playwright-cli goto https://roundabout.test/production/fallen-angels/
playwright-cli run-code "async page => { await page.evaluate(() => { window.location.hash = '#test-section'; }); }"
playwright-cli run-code "async page => { const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'hash_change')); return JSON.stringify(events, null, 2); }"
```
Expected: `{ event: "hash_change", hash_value: "test-section" }`

Note: The accordions on `/about/` and Kadence tabs on `/production/fallen-angels/` do NOT trigger hash changes. Use `window.location.hash = '...'` to test, or find pages with UIFramework hash-state navigation.

---

### S15. Video Play — PASS

```bash
playwright-cli goto https://roundabout.test/video-test/
playwright-cli click <play-button-ref>
```
(Click "Play video: Education at Roundabout 2026" or any video play button)

```bash
playwright-cli run-code "async page => { await page.waitForTimeout(3000); const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'video_play')); return JSON.stringify(events, null, 2); }"
```
Expected: `{ event: "video_play", video_id: "gsZ24DskSRM", video_provider: "youtube", video_url: "https://www.youtube.com/watch?v=gsZ24DskSRM" }`

---

### S16. Video Pause — PASS

After starting a video (S15), close the modal to trigger pause:
```bash
playwright-cli click <close-modal-ref>
playwright-cli run-code "async page => { const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'video_pause')); return JSON.stringify(events, null, 2); }"
```
Expected: `{ event: "video_pause", video_id: "gsZ24DskSRM", video_provider: "youtube", video_url: "https://www.youtube.com/watch?v=gsZ24DskSRM" }`

---

### S17. Video Progress Milestone — PASS

Play the Vimeo video (short enough to reach milestones in headless Chrome):
```bash
playwright-cli click <vimeo-play-button-ref>
playwright-cli run-code "async page => { await page.waitForTimeout(10000); const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'video_milestone')); return JSON.stringify(events, null, 2); }"
```
Expected: Milestones at 25, 50, 75, 100:
```json
{ "event": "video_milestone", "video_id": "613729649", "video_provider": "vimeo", "video_url": "https://vimeo.com/613729649", "video_milestone": 25 }
```

Note: YouTube iframes don't advance playback in headless Chrome. Use Vimeo videos for milestone testing.

---

### S18. Video Ended — PASS

Let the Vimeo video play to completion:
```bash
playwright-cli run-code "async page => { await page.waitForTimeout(20000); const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'video_ended')); return JSON.stringify(events, null, 2); }"
```
Expected: `{ event: "video_ended", video_id: "613729649", video_provider: "vimeo", video_url: "https://vimeo.com/613729649" }`

---

### S11. Pre-GTM Custom Tag Injection — PASS

**Prereq:** "Before GTM Test" custom tag exists with placement "Before GTM".

```bash
playwright-cli goto https://roundabout.test/about/
playwright-cli run-code "async page => { const html = await page.evaluate(() => document.head.innerHTML); const beforeIdx = html.indexOf('BEFORE-GTM-TAG'); const gtmComment = html.indexOf('Google Tag Manager -->'); return JSON.stringify({ beforeGtmPos: beforeIdx, gtmCommentPos: gtmComment, orderCorrect: beforeIdx !== -1 && gtmComment !== -1 && beforeIdx < gtmComment }); }"
```
Expected: `orderCorrect: true` — the "Before GTM" tag appears before the GTM snippet in `<head>`.

---

### S12. Per-Page Custom Tag Targeting — PASS

**Prereq:** "Per-Page Test" custom tag exists with "Only Include On: About (ID 916)".

**Verify present on target page:**
```bash
playwright-cli goto https://roundabout.test/about/
playwright-cli run-code "async page => { const html = await page.evaluate(() => document.head.innerHTML); return JSON.stringify({ hasPerPage: html.includes('PER-PAGE-ABOUT-ONLY') }); }"
```
Expected: `hasPerPage: true`

**Verify absent on non-target page:**
```bash
playwright-cli goto https://roundabout.test/
playwright-cli run-code "async page => { const html = await page.evaluate(() => document.head.innerHTML); return JSON.stringify({ hasPerPage: html.includes('PER-PAGE-ABOUT-ONLY') }); }"
```
Expected: `hasPerPage: false`

---

### S13. Custom Tag with No Targeting Rules — PASS

**Prereq:** "Test Script" custom tag exists with no targeting rules.

```bash
playwright-cli goto https://roundabout.test/
playwright-cli run-code "async page => { const html = await page.evaluate(() => document.head.innerHTML); return JSON.stringify({ hasGlobal: html.includes('Hello World') }); }"
```
Expected: `hasGlobal: true`

Repeat on a different page (e.g. `/about/`, `/video-test/`) — should be true on all pages.

---

### N1. Click on Non-Qualifying Element — PASS

```bash
playwright-cli goto https://roundabout.test/
playwright-cli eval "window.dataLayer.length"
```
Record baseline. Click a `<p>` element:
```bash
playwright-cli click e155
playwright-cli eval "window.dataLayer.length"
```
Expected: dataLayer length unchanged. No `site_click` event.

Note: Use snapshot to find a paragraph ref — e155 was from an earlier session.

---

### N2. Opt-Out on Parent Does Not Cascade — PASS

**Prereq:** Test page "GTM Test - Opt Out" (ID 2366) with `<form data-gtm="0">` containing a child `<a href="/privacy-policy/">`.

```bash
playwright-cli goto https://roundabout.test/gtm-test-opt-out/
```
Prevent navigation, then click the link inside the opted-out form:
```bash
playwright-cli run-code "async page => { await page.evaluate(() => { document.querySelector('a[href*=\"privacy-policy\"]').addEventListener('click', e => e.preventDefault(), { once: true }); }); }"
playwright-cli click <link-ref>
playwright-cli run-code "async page => { const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'site_click')); return JSON.stringify(events, null, 2); }"
```
Expected: `{ event: "site_click", click_label: "Link inside opted-out form", click_direction: "internal", click_url: "/privacy-policy/" }` — `data-gtm="0"` on the parent does not exclude descendant qualifying elements.

---

### N3. Initial Page Load with Hash — PASS

```bash
playwright-cli goto https://roundabout.test/about/#some-hash
playwright-cli run-code "async page => { const events = await page.evaluate(() => window.dataLayer.filter(e => e.event === 'hash_change')); return JSON.stringify(events, null, 2); }"
```
Expected: `[]` — no `hash_change` event on initial load.

---

### N4. GTM Container Disabled — PASS

**Prereq:** Remove GTM container ID from Tag Manager > Settings. Flush cache.

```bash
playwright-cli goto https://roundabout.test/
playwright-cli run-code "async page => { const result = await page.evaluate(() => { const html = document.head.innerHTML; return { hasGtmSnippet: html.includes('googletagmanager.com'), dataLayerExists: Array.isArray(window.dataLayer), dataLayerContent: window.dataLayer ? window.dataLayer[0] : null }; }); return JSON.stringify(result, null, 2); }"
```
Expected: `hasGtmSnippet: false`, `dataLayerExists: true`, `dataLayerContent` contains page metadata. No JS errors in console. Custom tags still render independently.

**Restore:** Re-add GTM container ID after testing. Flush cache.

---

### N5. Module Enabled, No Interactions Configured — PASS

All click tracking tests above used pages without any `data-gtm` attributes. Labels were auto-resolved from `textContent`, `aria-label`, etc. No errors.

---

## Scenarios Not Requiring Browser Testing

| Scenario | Reason |
|----------|--------|
| S3 (PHP filter override) | Unit/integration test — `add_filter('sitchco/tag-manager/outbound-domains', ...)` |
| S14 (Marketing script via GTM) | GTM configuration only, no platform code involved |

---

## Bugs Found During Testing

### 1. TagManager JS never enqueued

**File:** `modules/TagManager/TagManager.php`

**Issue:** `registerAssets()` called `$assets->registerScript()` (wp_register_script) but `enqueueFrontendAssets()` never called `$assets->enqueueScript()`. The JS was registered but not loaded on the frontend.

**Fix:** Added `$assets->enqueueScript(static::hookName())` inside the `enqueueFrontendAssets` callback.

### 2. Gravity Forms confirmation event never bound

**File:** `modules/UIFramework/assets/scripts/main.js`

**Issue:** UIFramework script loads before jQuery (script position 6 vs jQuery at position 10). The `if (window.jQuery)` check at module scope failed silently, so `gform_confirmation_loaded` was never bound.

**Fix:** Moved the jQuery binding inside `ready()` callback, which fires on `DOMContentLoaded` when jQuery is guaranteed to be loaded. Also switched from deprecated `.bind()` to `.on()`.
