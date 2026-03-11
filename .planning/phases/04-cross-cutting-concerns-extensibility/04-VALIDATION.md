---
phase: 4
slug: cross-cutting-concerns-extensibility
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-10
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (via WPTest) |
| **Config file** | ddev test-phpunit runner (no phpunit.xml needed) |
| **Quick run command** | `ddev test-phpunit tests/Modules/VideoBlock/VideoBlockTest.php` |
| **Full suite command** | `ddev test-phpunit` |
| **Estimated runtime** | ~10 seconds |

---

## Sampling Rate

- **After every task commit:** Run `ddev test-phpunit tests/Modules/VideoBlock/VideoBlockTest.php`
- **After every plan wave:** Run `ddev test-phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 04-01-xx | 01 | 1 | MXCL-01 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-01-xx | 01 | 1 | MXCL-02 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-01-xx | 01 | 1 | ANLT-01 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-01-xx | 01 | 1 | ANLT-02 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-01-xx | 01 | 1 | ANLT-03 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-01-xx | 01 | 1 | EXTN-01 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-01-xx | 01 | 1 | EXTN-02 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-01-xx | 01 | 1 | EXTN-03 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-01-xx | 01 | 1 | EXTN-04 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-01-xx | 01 | 1 | EXTN-05 | Manual browser | N/A — JS behavior | N/A | ⬜ pending |
| 04-02-xx | 02 | 1 | EXTN-06 | PHP unit | `ddev test-phpunit tests/Modules/VideoBlock/VideoBlockTest.php` | ✅ Needs new method | ⬜ pending |
| 04-02-xx | 02 | 1 | NOOP-02 | PHP unit | `ddev test-phpunit tests/Modules/VideoBlock/VideoBlockTest.php` | ✅ Implied | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

No new test files or framework config needed. Existing test file `tests/Modules/VideoBlock/VideoBlockTest.php` needs new test methods:

- [ ] `test_play_icon_svg_filter_is_applied()` — verifies `apply_filters('sitchco/video/play_icon_svg', ...)` is called and result appears in output
- [ ] `test_hook_suffix_produces_correct_filter_name()` — verifies `VideoBlock::hookName('play_icon_svg')` === `'sitchco/video/play_icon_svg'`

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Starting second video pauses first | MXCL-01 | JS player coordination in browser | 1. Place 2 video blocks on page 2. Play first 3. Play second 4. First should pause |
| Opening modal pauses inline | MXCL-02 | JS modal + player coordination | 1. Play inline video 2. Click modal trigger 3. Inline pauses, modal plays |
| video-play hook fires | ANLT-01, EXTN-01 | JS hook system | Console: `sitchco.hooks.addAction('video-play', (p) => console.log('play', p))` then play |
| video-progress milestones | ANLT-02 | JS polling + time-based | Console: subscribe to video-progress, play video past 25% |
| video-pause hook fires | ANLT-03 | JS hook system | Console: subscribe to video-pause, pause a playing video |
| video-request-pause works | EXTN-02 | JS inter-component | Console: `sitchco.hooks.doAction('video-request-pause', 'VIDEO_ID')` while video plays |
| video-ended hook fires | EXTN-03 | JS end-of-video event | Console: subscribe to video-ended, let video finish (or seek near end) |
| YouTube playerVars filter | EXTN-04 | JS filter applied before SDK init | Console: register filter, reload, verify params changed |
| Vimeo playerVars filter | EXTN-05 | JS filter applied before SDK init | Console: register filter, reload, verify params changed |
| No auto-pause on visibility | NOOP-02 | Negative test — verify absence | Play video, switch tabs, return — video still plays |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
