# Roadmap: sitchco/video

## Overview

This roadmap delivers a native Gutenberg video block for sitchco-core that provides privacy-respecting, click-to-play video embedding with three display modes (inline, modal, modal-only). The work flows from block registration and editor authoring controls, through server-side poster rendering and inline playback, to modal integration with UIModal, and finally cross-cutting concerns like mutual exclusion, analytics, and extension hooks. Each phase delivers a verifiable, end-to-end capability.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Block Foundation & Editor** - Native block registration with full editor authoring experience (URL input, oEmbed preview, display mode controls, play icon config) (completed 2026-03-09)
- [x] **Phase 2: Poster Rendering & Inline Playback** - Server-side poster with oEmbed/InnerBlocks, click-to-play with provider SDKs, privacy-enhanced URLs, accessibility (completed 2026-03-09)
- [x] **Phase 3: Modal Integration & Triggers** - Modal and modal-only display modes via UIModal composition, decoupled triggers, deep linking (completed 2026-03-09)
- [ ] **Phase 4: Cross-Cutting Concerns & Extensibility** - Mutual exclusion, GTM analytics, JS/PHP extension hooks, no-op behavior

## Phase Details

### Phase 1: Block Foundation & Editor
**Goal**: Authors can insert the video block, configure all settings in the editor, and see an oEmbed-powered preview -- the block saves and loads correctly across editor sessions
**Depends on**: Nothing (first phase)
**Requirements**: PRE-01, PRE-02, BLK-01, BLK-02, BLK-03, AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09, AUTH-10, AUTH-11, NOOP-01
**Success Criteria** (what must be TRUE):
  1. Author can insert the video block, paste a YouTube or Vimeo URL, and see the provider thumbnail with play icon in the editor preview
  2. Author can switch between Inline, Modal, and Modal Only display modes, and the inspector panel shows/hides mode-appropriate controls (modal ID, title field, InnerBlocks)
  3. Video title and modal ID auto-populate from oEmbed metadata and remain editable without being overwritten on subsequent saves
  4. Block saves and reloads without data loss -- InnerBlocks content persists across editor sessions (save function returns InnerBlocks.Content)
  5. Block with no URL set renders InnerBlocks content without play icon or click-to-play behavior
**Plans:** 3/3 plans complete

Plans:
- [x] 01-01-PLAN.md -- Block registration, module class, attribute schema, render.php, editor skeleton, PHPUnit tests, PRE-01/PRE-02 verification
- [x] 01-02-PLAN.md -- URL input, provider detection, oEmbed preview, auto-population, display mode controls, conditional inspector UI
- [x] 01-03-PLAN.md -- Play icon configuration (branded SVGs, position sliders, click behavior), human verification checkpoint

### Phase 2: Poster Rendering & Inline Playback
**Goal**: Visitors see a poster image with accessible play button, click to load the provider SDK and play inline with no layout shift -- zero provider resources load before the click
**Depends on**: Phase 1
**Requirements**: POST-01, POST-02, POST-03, POST-04, POST-05, INLN-01, INLN-02, INLN-03, INLN-04, INLN-05, INLN-06, INLN-07, PRIV-01, PRIV-02, PRIV-03, ACCS-01, ACCS-02, ACCS-03
**Success Criteria** (what must be TRUE):
  1. Page loads with zero network requests to YouTube or Vimeo -- no iframe, SDK, or CDN resource loads before user clicks play (verifiable in browser DevTools Network tab)
  2. User clicks the play button on a YouTube video and playback begins inline with the poster replaced by the player -- the wrapper dimensions remain stable (no layout shift)
  3. When InnerBlocks content is present, it renders as the poster instead of the oEmbed thumbnail; when absent, the oEmbed thumbnail renders automatically
  4. Play button is a focusable, keyboard-activatable button element with aria-label including the video title; entire-poster mode wrapper has role="button" and tabindex="0"
  5. YouTube embeds use youtube-nocookie.com domain and Vimeo embeds include dnt parameter
**Plans:** 3/3 plans complete

Plans:
- [x] 02-01-PLAN.md -- SVG sprite source files, frontend CSS, server-side poster rendering (oEmbed caching, InnerBlocks fallback, accessible play button), PHPUnit tests
- [x] 02-02-PLAN.md -- Click-to-play JavaScript (viewScript, SDK loading, dimension locking, player creation, privacy-enhanced embeds, start time support)
- [x] 02-03-PLAN.md -- Human verification checkpoint for complete poster-to-playback flow

### Phase 3: Modal Integration & Triggers
**Goal**: Videos can play in a modal dialog via UIModal composition, with decoupled triggers and deep linking -- modal-only blocks render no visible page element
**Depends on**: Phase 2
**Requirements**: MODL-01, MODL-02, MODL-03, MODL-04, MODL-05, MODL-06, MODL-07, MODL-08, TRIG-01, TRIG-02, TRIG-03, TRIG-04, ACCS-04
**Success Criteria** (what must be TRUE):
  1. User clicks play on a modal-mode video and a dialog opens with video playback inside -- the page poster remains visible behind the dialog
  2. Closing the modal pauses (not destroys) the video; reopening the same modal resumes the existing player without creating a duplicate iframe
  3. Modal-only block renders no visible element on the page -- only a dialog in wp_footer that can be triggered by any link with matching href="#modal-id" or data-target="#modal-id"
  4. Navigating directly to a URL with #video-modal-id hash opens the corresponding video modal on page load
  5. Modal dialog has aria-labelledby referencing a heading with the video title
**Plans:** 3/3 plans complete

Plans:
- [x] 03-01-PLAN.md -- Server-side render.php display mode branching (modal/modal-only), UIModal::loadModal() composition, video modal CSS, PHPUnit tests
- [x] 03-02-PLAN.md -- Client-side modal playback lifecycle in view.js (open, play, pause, resume, deep link autoplay)
- [x] 03-03-PLAN.md -- Build verification and human browser testing checkpoint

### Phase 4: Cross-Cutting Concerns & Extensibility
**Goal**: Multiple videos coordinate (only one plays at a time), analytics track engagement, and external code can hook into the video lifecycle
**Depends on**: Phase 3
**Requirements**: MXCL-01, MXCL-02, ANLT-01, ANLT-02, ANLT-03, EXTN-01, EXTN-02, EXTN-03, EXTN-04, EXTN-05, EXTN-06, NOOP-02
**Success Criteria** (what must be TRUE):
  1. Starting a second video (inline or modal) automatically pauses the first -- only one video plays at a time across all display modes
  2. GTM dataLayer receives interaction events for video start, pause, and progress milestones (25%, 50%, 75%, 100%) with provider and URL metadata
  3. External JS code can subscribe to video-play, video-pause, and video-ended actions, and can programmatically pause a video by ID via the video-pause action
  4. External JS code can filter YouTube and Vimeo player parameters, and PHP code can filter the play icon SVG markup
**Plans:** 2 plans

Plans:
- [ ] 04-01-PLAN.md -- Player registry, mutual exclusion, JS lifecycle hooks (video-play/pause/ended/progress), milestone polling, JS player parameter filters, video-request-pause subscriber
- [ ] 04-02-PLAN.md -- PHP play icon SVG filter (EXTN-06), HOOK_SUFFIX fix, PHPUnit tests

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Block Foundation & Editor | 3/3 | Complete    | 2026-03-09 |
| 2. Poster Rendering & Inline Playback | 3/3 | Complete | 2026-03-09 |
| 3. Modal Integration & Triggers | 3/3 | Complete | 2026-03-09 |
| 4. Cross-Cutting Concerns & Extensibility | 0/2 | Not started | - |
