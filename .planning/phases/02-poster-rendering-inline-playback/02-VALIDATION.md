---
phase: 2
slug: poster-rendering-inline-playback
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-09
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (WordPress integration via wp-phpunit) |
| **Config file** | `public/phpunit.xml` |
| **Quick run command** | `ddev test-phpunit --filter=VideoBlockTest` |
| **Full suite command** | `ddev test-phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `ddev test-phpunit --filter=VideoBlockTest`
- **After every plan wave:** Run `ddev test-phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | POST-01 | unit | `ddev test-phpunit --filter=test_render_with_oembed_thumbnail` | ❌ W0 | ⬜ pending |
| 02-01-02 | 01 | 1 | POST-02 | unit | `ddev test-phpunit --filter=test_render_innerblocks_as_poster` | ❌ W0 | ⬜ pending |
| 02-01-03 | 01 | 1 | POST-04 | unit | `ddev test-phpunit --filter=test_innerblocks_presence_check` | ❌ W0 | ⬜ pending |
| 02-01-04 | 01 | 1 | POST-05 | unit | `ddev test-phpunit --filter=test_render_generic_placeholder` | ❌ W0 | ⬜ pending |
| 02-01-05 | 01 | 1 | ACCS-01 | unit | `ddev test-phpunit --filter=test_play_button_aria_label` | ❌ W0 | ⬜ pending |
| 02-01-06 | 01 | 1 | ACCS-03 | unit | `ddev test-phpunit --filter=test_poster_click_mode_accessibility` | ❌ W0 | ⬜ pending |
| 02-01-07 | 01 | 1 | PRIV-02 | unit | `ddev test-phpunit --filter=test_youtube_nocookie` | ❌ W0 | ⬜ pending |
| 02-02-01 | 02 | 1 | INLN-01 | manual-only | Browser DevTools | N/A | ⬜ pending |
| 02-02-02 | 02 | 1 | INLN-02 | manual-only | Browser DevTools | N/A | ⬜ pending |
| 02-02-03 | 02 | 1 | INLN-03 | manual-only | Browser DevTools | N/A | ⬜ pending |
| 02-02-04 | 02 | 1 | INLN-04 | manual-only | Browser DevTools | N/A | ⬜ pending |
| 02-02-05 | 02 | 1 | INLN-05 | manual-only | Browser DevTools | N/A | ⬜ pending |
| 02-02-06 | 02 | 1 | INLN-06 | manual-only | Browser DevTools | N/A | ⬜ pending |
| 02-02-07 | 02 | 1 | INLN-07 | manual-only | Browser DevTools Network tab | N/A | ⬜ pending |
| 02-02-08 | 02 | 1 | PRIV-01 | manual-only | Browser DevTools Network tab | N/A | ⬜ pending |
| 02-02-09 | 02 | 1 | PRIV-03 | manual-only | Browser DevTools iframe src | N/A | ⬜ pending |
| 02-02-10 | 02 | 1 | ACCS-02 | manual-only | Tab + Enter/Space | N/A | ⬜ pending |
| 02-01-08 | 01 | 1 | POST-03 | unit | `ddev test-phpunit --filter=test_innerblocks_any_block_type` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` — extend existing file with POST-01, POST-02, POST-03, POST-04, POST-05, ACCS-01, ACCS-03, PRIV-02 test methods
- [ ] HTTP mocking via `$this->fakeHttp()` for oEmbed responses — needed to test render.php without real network calls

*Existing test infrastructure covers framework requirements. Only new test methods needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Click-to-play loads SDK | INLN-01 | JS runtime behavior | Click play button, verify iframe appears in DOM |
| Dimension locking on click | INLN-02 | Visual layout verification | Click play, confirm no layout shift via DevTools |
| Poster hidden after click | INLN-03 | Visual state change | Click play, confirm poster is hidden |
| Iframe at 100% w/h | INLN-04 | Visual rendering | Inspect iframe dimensions match wrapper |
| Auto-play on ready | INLN-05 | Runtime JS behavior | Click play, confirm video starts automatically |
| Start time from URL | INLN-06 | Runtime JS behavior | Use URL with ?t=60, verify playback starts at 60s |
| No pre-click loading | INLN-07, PRIV-01 | Network behavior | Load page, check DevTools Network for zero provider requests |
| Vimeo dnt parameter | PRIV-03 | iframe src inspection | Click play on Vimeo video, inspect iframe src for dnt=1 |
| Keyboard activation | ACCS-02 | Keyboard interaction | Tab to play button, press Enter/Space, verify play starts |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
