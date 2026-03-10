---
phase: 1
slug: block-foundation-editor
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-09
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit via WPTest\Test\TestCase |
| **Config file** | `public/phpunit.xml` |
| **Quick run command** | `ddev test-phpunit` (from sitchco-core/) |
| **Full suite command** | `ddev test-phpunit` (from sitchco-core/) |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `ddev test-phpunit`
- **After every plan wave:** Run `ddev test-phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 01-01-01 | 01 | 1 | PRE-01 | unit | `ddev test-phpunit --filter ModalDataTest` | Partially | ⬜ pending |
| 01-01-02 | 01 | 1 | PRE-02 | integration | `ddev test-phpunit --filter UIModalTest` | ❌ W0 | ⬜ pending |
| 01-02-01 | 02 | 1 | BLK-01 | unit | `ddev test-phpunit --filter VideoBlockTest` | ❌ W0 | ⬜ pending |
| 01-02-02 | 02 | 1 | BLK-03 | unit | `ddev test-phpunit --filter VideoBlockTest` | ❌ W0 | ⬜ pending |
| 01-02-03 | 02 | 1 | NOOP-01 | unit | `ddev test-phpunit --filter VideoBlockTest` | ❌ W0 | ⬜ pending |
| 01-03-01 | 03 | 2 | AUTH-01-11 | manual-only | Manual editor test | N/A | ⬜ pending |
| 01-03-02 | 03 | 2 | BLK-02 | manual-only | Manual editor test | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` — stubs for BLK-01, BLK-03, NOOP-01 (block registration, render output, no-URL behavior)
- [ ] Extend `tests/Modules/UIModal/ModalDataTest.php` — covers PRE-01: verify VIDEO type with raw strings
- [ ] Add `tests/Modules/UIModal/UIModalContentTest.php` — covers PRE-02: content-based modal renders identical dialog

*No JS test infrastructure exists for editor components — AUTH-01 through AUTH-11 are manual-only (editor UI behavior requires Jest/Playwright setup, out of scope for this phase).*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Block inserts empty | BLK-02 | Editor UI behavior | Insert block from inserter, verify no InnerBlocks pre-populated |
| URL input stores attribute | AUTH-01 | Editor inspector panel | Enter YouTube URL in inspector, verify attribute saved |
| Provider auto-detection | AUTH-02 | Editor UI state | Enter YouTube vs Vimeo URL, verify provider detected |
| oEmbed preview fetch | AUTH-03, AUTH-04 | Network + editor rendering | Enter URL, verify thumbnail appears via WP proxy |
| Title auto-populate | AUTH-05 | Editor attribute behavior | Enter URL, verify title populates from oEmbed |
| Display mode switching | AUTH-06, AUTH-08 | Inspector panel UI | Switch modes, verify controls show/hide |
| Modal ID auto-generate | AUTH-07 | Editor attribute behavior | Enter URL in modal mode, verify slugified title |
| Play icon style config | AUTH-09 | Inspector panel UI | Change style for YouTube vs Vimeo URLs |
| Play icon position | AUTH-10 | Inspector panel UI + preview | Adjust X/Y sliders, verify preview updates |
| Click behavior toggle | AUTH-11 | Inspector panel UI | Toggle between poster/icon, verify option persists |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
