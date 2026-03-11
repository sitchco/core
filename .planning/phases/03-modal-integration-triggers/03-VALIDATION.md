---
phase: 3
slug: modal-integration-triggers
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-09
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (via ddev test-phpunit) |
| **Config file** | existing test infrastructure |
| **Quick run command** | `ddev test-phpunit --filter VideoBlockTest` |
| **Full suite command** | `ddev test-phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `ddev test-phpunit --filter VideoBlockTest`
- **After every plan wave:** Run `ddev test-phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 01 | 0 | MODL-07 | unit | `ddev test-phpunit --filter VideoBlockTest::test_modal_only_renders_no_visible_html` | ❌ W0 | ⬜ pending |
| 03-01-02 | 01 | 0 | MODL-08 | unit | `ddev test-phpunit --filter VideoBlockTest::test_modal_only_no_trigger_no_errors` | ❌ W0 | ⬜ pending |
| 03-01-03 | 01 | 0 | TRIG-04 | unit | `ddev test-phpunit --filter VideoBlockTest::test_modal_id_is_slugified` | ❌ W0 | ⬜ pending |
| 03-01-04 | 01 | 0 | ACCS-04 | unit | `ddev test-phpunit --filter VideoBlockTest::test_modal_dialog_aria_labelledby` | ❌ W0 | ⬜ pending |
| 03-01-05 | 01 | 0 | MODL-01, MODL-04 | unit | `ddev test-phpunit --filter VideoBlockTest::test_modal_mode_renders_poster_and_dialog` | ❌ W0 | ⬜ pending |
| 03-XX-XX | XX | X | MODL-01 | manual | Browser test: click poster in modal mode | N/A | ⬜ pending |
| 03-XX-XX | XX | X | MODL-02 | manual | Browser test: check URL after open | N/A | ⬜ pending |
| 03-XX-XX | XX | X | MODL-03 | manual | Browser test: inspect iframe in dialog | N/A | ⬜ pending |
| 03-XX-XX | XX | X | MODL-04 | manual | Browser test: poster visible behind modal | N/A | ⬜ pending |
| 03-XX-XX | XX | X | MODL-05 | manual | Browser test: close modal, check audio stops | N/A | ⬜ pending |
| 03-XX-XX | XX | X | MODL-06 | manual | Browser test: close+reopen, inspect iframes | N/A | ⬜ pending |
| 03-XX-XX | XX | X | TRIG-01 | manual | Browser test: click trigger link | N/A | ⬜ pending |
| 03-XX-XX | XX | X | TRIG-02 | manual | Browser test: test two triggers for same ID | N/A | ⬜ pending |
| 03-XX-XX | XX | X | TRIG-03 | manual | Browser test: navigate to URL with hash | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` — add `test_modal_only_renders_no_visible_html` (MODL-07)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` — add `test_modal_only_no_trigger_no_errors` (MODL-08)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` — add `test_modal_id_is_slugified` (TRIG-04)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` — add `test_modal_dialog_aria_labelledby` (ACCS-04)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` — add `test_modal_mode_renders_poster_and_dialog` (MODL-01/MODL-04)
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` — update `renderBlock()` helper to support modal dialog rendering via `UIModal::unloadModals()` capture

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Modal click opens dialog with video | MODL-01 | JS-driven player lifecycle | Click poster in modal mode, verify dialog opens with player |
| URL hash updates on modal open | MODL-02 | Browser URL bar interaction | Open modal, check URL contains hash |
| SDK loads inside dialog | MODL-03 | Network/iframe requires browser | Open modal, inspect iframe in dialog |
| Page poster stays visible behind modal | MODL-04 | Visual verification | Open modal, verify poster visible behind overlay |
| Player pauses on modal close | MODL-05 | Audio/player state requires browser | Close modal, verify audio stops |
| Reopen resumes without duplicate iframe | MODL-06 | Player lifecycle requires browser | Close+reopen, count iframes |
| href/data-target triggers open modal | TRIG-01 | Link click + dialog interaction | Click trigger link, verify modal opens |
| Multiple triggers work for same modal | TRIG-02 | Multi-element click handling | Test two triggers for same ID |
| Hash deep link opens modal on page load | TRIG-03 | Page load + hash parsing | Navigate directly to URL with hash |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
