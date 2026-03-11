# Feature Research

**Domain:** Privacy-first WordPress Gutenberg video embedding block
**Researched:** 2026-03-09
**Confidence:** HIGH

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist. Missing these = product feels incomplete or broken.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Paste URL, get working embed | Core WordPress oEmbed behavior -- users paste YouTube/Vimeo URLs daily. Any video block that requires manual configuration beyond a URL is a non-starter. | LOW | WordPress `_wp_oembed_get_object()` handles provider detection and data fetching server-side. Provider regex matching is well-established. |
| Poster/thumbnail image | Every video embed on the web shows a thumbnail before play. Users expect to see what the video is about before clicking. lite-youtube-embed, WP Video Popup, and every major plugin display poster images. | MEDIUM | oEmbed response includes `thumbnail_url`. Auto-fetch is the zero-config path. The complexity is in the InnerBlocks override system for custom posters. |
| Click-to-play (facade pattern) | This is the product's core promise and also the industry standard for performance-conscious embeds. lite-youtube-embed (Paul Irish) established this pattern. WordPress Performance Team has an open issue (#113) for facade embeds. Every modern video plugin (Lazy Load for Videos, Embed Plus, WP Video Popup) does this. | MEDIUM | Replace iframe with static poster + play button. Load provider SDK only on click. This is the architectural foundation -- everything else builds on it. |
| Play icon overlay | Visual affordance that the poster is playable. Without it, users don't know to click. Universal across all video embed solutions. | LOW | SVG overlay positioned on the poster. Must support YouTube-branded variants (dark/light/red) per YouTube API ToS branding guidelines. Generic icon for Vimeo. |
| Privacy-enhanced embed URLs | youtube-nocookie.com and Vimeo `dnt=1` are baseline for any privacy-first solution. Complianz, Embed Privacy, and GDPR-focused plugins all do this. Without it, the "privacy-first" claim is hollow. | LOW | URL rewriting: `youtube.com` to `youtube-nocookie.com`, append `dnt=1` to Vimeo. Simple string transformation. |
| Responsive/fluid layout | Videos must fill their container and maintain aspect ratio on all screen sizes. This is 2026 -- non-responsive video is unacceptable. | LOW | CSS `aspect-ratio` property on the wrapper. Poster determines dimensions. WordPress Gutenberg issue #52185 documents the CLS problem with dimensionless video elements. |
| Layout shift prevention (CLS) | Core Web Vitals compliance. WordPress has a 71% CLS passing rate on desktop partly due to video embeds. Users (and SEO) expect no content jumping when videos load. | MEDIUM | Lock wrapper dimensions before SDK loads. The poster image establishes the space. Width/height attributes + `aspect-ratio` CSS + `height: auto` pattern. Dimension data comes from oEmbed response. |
| Accessible play button | Screen readers must announce the play action. Keyboard users must be able to activate it. WCAG 2.1 AA is table stakes for any WordPress component. | LOW | `<button>` element with `aria-label` including video title. `Enter`/`Space` activation. In entire-poster click mode, wrapper needs `role="button"` + `tabindex="0"`. |
| YouTube and Vimeo support | These two providers cover 95%+ of embedded video use cases in WordPress. Not supporting both is a dealbreaker. | MEDIUM | Each provider has different SDK APIs, embed URL formats, and branding requirements. Provider abstraction layer needed but scoped to just these two for v1. |
| YouTube branded play button | YouTube API Terms of Service (updated August 2025) require branded play buttons. This is a legal compliance requirement, not a design preference. Custom play buttons that don't use YouTube branding violate ToS. | LOW | YouTube provides dark, light, and red variants. Must be configurable per-block. Official SVG assets from YouTube branding guidelines. |

### Differentiators (Competitive Advantage)

Features that set the product apart from WP Video Popup, Embed Privacy, Lazy Load for Videos, and Presto Player.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Three display modes (inline, modal, modal-only) | No existing plugin offers all three in one block. WP Video Popup is modal-only. Core embed is inline-only. This block gives authors full control over how video integrates with page layout. | HIGH | Inline: replace poster with iframe in-place. Modal: compose with UIModal, open `<dialog>`. Modal-only: no visible poster on page, `<dialog>` rendered in `wp_footer`, triggered externally. Each mode has different DOM structure and lifecycle. |
| UIModal composition (not a custom lightbox) | Reuses the existing battle-tested UIModal system with `<dialog>`, focus trapping, scroll locking, hash syncing, and ARIA. Competitors build throwaway lightbox implementations. This composability means the video block benefits from every UIModal improvement automatically. | MEDIUM | UIModal already handles show/hide/hash/ARIA/scroll-lock/focus. Video block adds pause-on-close behavior. Requires UIModal content-based modal refactor (prerequisite). |
| InnerBlocks custom poster composition | Authors can use ANY block type (Image, Cover, Group with text overlay, custom hero layouts) as the poster. No other video plugin offers this level of poster customization. Presto Player has poster image upload. This block has poster *composition*. | MEDIUM | InnerBlocks renders when present, oEmbed thumbnail used when absent. Block checks only *whether* InnerBlocks exist, never inspects content. This is the key design constraint. |
| Decoupled trigger system | Modal-only mode enables any element on the page to trigger video playback via `href="#id"` or `data-target="#id"`. A button in a hero, a link in body text, a CTA card -- any of them can open the video modal. WP Video Popup requires a specific CSS class on trigger elements. This is more flexible and declarative. | LOW | Already implemented in UIModal. Video block just needs to register its dialog with a predictable ID (auto-generated from oEmbed metadata or manually set). |
| Deep linking via URL hash | Visitors can share direct links to video modals. Bookmarkable video states. No competitor does this for video modals. UIModal already supports hash sync. | LOW | Essentially free -- UIModal's `syncModalWithHash()` already handles this. Video block just needs to auto-play when modal opens from hash navigation. |
| Mutual exclusion (one video at a time) | When a user plays Video B, Video A automatically pauses. Professional behavior that prevents audio overlap chaos. Only the "Lazy load videos and sticky control" plugin does this, and only for YouTube in free tier. | MEDIUM | Requires provider SDK integration (YouTube IFrame API `onStateChange`, Vimeo Player SDK events). `sitchco.hooks` system provides the coordination bus -- `video-play` action triggers pause on all other instances. |
| GTM analytics events | Push `video_play`, `video_pause`, `video_progress` (10% milestones) to dataLayer. Presto Player has its own analytics dashboard. This approach integrates with existing GTM/GA4 infrastructure that sites already have. No plugin dependency for analytics. | MEDIUM | Provider SDKs expose progress events. Map to dataLayer pushes. gtm4wp pattern: `gtm4wp.mediaPlayerStateChange` with play/pause/ended states. Progress tracking at 10% intervals via SDK time APIs. |
| Extension hooks (JS actions/filters) | `video-play`, `video-pause`, `video-ended` actions and `playerVars` filter. External code can react to video events (carousel auto-pause, custom analytics, AB testing). No other video block plugin exposes a WordPress-style hook system for JavaScript. | LOW | `sitchco.hooks` infrastructure already exists. Video module registers its actions/filters. Third-party code subscribes. Pattern is proven by UIModal's hook usage. |
| oEmbed auto-populated metadata | Video title and modal ID auto-populate from oEmbed response. Zero-config: paste URL, get accessible video with correct ARIA label and predictable hash. Competitors require manual title entry. | LOW | Server-side: fetch oEmbed on URL save, extract `title` and `thumbnail_url`. Store as block attributes. Editor shows preview. |
| Click behavior mode (entire poster vs play icon only) | Some designs need the entire poster clickable (hero video). Others need only the play icon clickable (poster with other interactive elements). This granularity doesn't exist in competitors. | LOW | Attribute toggle. Entire-poster mode: wrapper gets `role="button"`, `tabindex="0"`, click handler on wrapper. Icon-only mode: `<button>` element is the sole click target. |
| Start time support from URL | Authors paste `?t=120` or `&start=120` URLs and it Just Works. YouTube and Vimeo URL parameters are parsed and forwarded to SDK. Minor feature but reduces author frustration. | LOW | Parse URL query params on save. Map to provider-specific SDK parameters (`start` for YouTube, `#t=` for Vimeo). |
| Play icon X/Y positioning | Configurable play icon position (not always dead center). Useful when poster has a face or key content at center that shouldn't be obscured. Not offered by any competitor. | LOW | CSS custom properties for position. Inspector controls with percentage-based X/Y. |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem good but create complexity, maintenance burden, or user experience problems.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Autoplay on scroll/viewport entry | Marketing teams want "engaging" video that plays when users scroll to it. | Violates the privacy-first premise -- loading SDK before user intent. Also, browsers aggressively block autoplay with sound. Creates jarring UX. Increases page weight. Conflicts with mutual exclusion. | Click-to-play is the intentional design choice. If autoplay is needed, it's a different block type entirely (background video). |
| Self-hosted/HTML5 video support | Some users want to upload MP4 files directly. | Completely different technical requirements (no SDK, no oEmbed, no provider detection, different streaming concerns, storage costs). WordPress core already has a Video block for self-hosted. Mixing concerns dilutes the block's purpose. | Use WordPress core Video block for self-hosted. This block is specifically for third-party embeds with privacy controls. |
| Built-in analytics dashboard | Presto Player has one. Seems like a competitive feature. | Massive scope increase. Requires database tables, admin UI, data retention policy, GDPR considerations for the analytics data itself. The analytics need is already served by GTM/GA4 which sites already have. | Push events to dataLayer. Let existing analytics infrastructure (GTM, GA4, Matomo) handle collection and reporting. |
| Consent management / CMP integration | GDPR compliance seems like it should be built-in. | Consent management is a site-wide concern, not a per-block concern. Every CMP (Complianz, CookieYes, etc.) has different APIs. Building CMP integration couples the block to specific consent tools. | Click-to-load architecture IS the privacy boundary. No provider resources load until user clicks. A future consent layer can intercept the click handler via the hook system if needed. |
| Provider expansion beyond YouTube/Vimeo (Dailymotion, TikTok, Wistia, etc.) | Completeness appeal. | Each provider has unique SDK, embed format, branding rules, and API quirks. The provider abstraction layer supports future additions, but v1 scope creep kills shipping. YouTube + Vimeo covers 95%+ of use cases. | Architecture supports provider plugins. Document the provider interface. Add providers in future versions based on actual user demand. |
| Custom end-screen / end-state behavior | Marketers want CTAs when video ends. | Provider-specific. YouTube has end screens built in. Vimeo has end-screen settings. Custom end states require overlaying on provider iframes, which is fragile and potentially ToS-violating. | Let providers handle their own end states. Expose `video-ended` hook so external code can trigger CTAs (show a modal, reveal a form, etc.) |
| Background video mode | Design teams want ambient looping video in hero sections. | Fundamentally different UX: no poster, no play button, autoplay+muted+loop, usually self-hosted for performance. Mixing this into a click-to-play block makes the block's purpose ambiguous. | Separate block type. Background video has different constraints (performance budget, mobile considerations, accessibility of motion). |
| Aspect ratio controls for the player iframe | Seems useful for different video formats (vertical, square, cinematic). | The iframe fills 100% of its wrapper. The poster (either oEmbed thumbnail or InnerBlocks) determines the visible dimensions. Adding aspect ratio controls creates a second source of truth for dimensions and risks mismatches between poster and player. | Poster determines dimensions. oEmbed thumbnails have correct aspect ratio. InnerBlocks poster gives authors full dimensional control. |
| Video gallery / playlist features | Multiple videos in a grid or carousel. | This is a collection/layout concern, not a single-block concern. WordPress has Group, Columns, and Query Loop blocks. A gallery is composed FROM video blocks, not built INTO a video block. | Use WordPress layout blocks (Group, Columns) to arrange multiple video blocks. Mutual exclusion handles the "only one plays" requirement across any layout. |
| Sticky/floating video on scroll | Video follows user as they scroll past. | Complex intersection observer logic, z-index management, mobile considerations, and potential conflict with other sticky elements. Scope creep that delays the core block. | Could be added as an optional enhancement in a future phase via a separate behavior module that composes with the video block. Not v1. |

## Feature Dependencies

```
[URL input + provider detection]
    |
    +---> [oEmbed auto-fetch] ---> [Poster thumbnail (default)]
    |                          +---> [Video title auto-populate]
    |                          +---> [Modal ID auto-populate]
    |
    +---> [Privacy-enhanced URLs]
    |
    +---> [Start time parsing]

[Click-to-play facade]
    |
    +--requires---> [Poster image (oEmbed or InnerBlocks)]
    +--requires---> [Play icon overlay]
    +--requires---> [Provider SDK lazy loading]
    |
    +--enables---> [Mutual exclusion]
    +--enables---> [GTM analytics events]

[Provider SDK lazy loading]
    +--requires---> [Provider detection from URL]
    +--enables---> [Programmatic pause/play]
    +--enables---> [Progress tracking]
    +--enables---> [Mutual exclusion]

[UIModal content-based refactor] *** PREREQUISITE ***
    |
    +--enables---> [Modal display mode]
    +--enables---> [Modal-only display mode]
    +--enables---> [Deep linking]
    +--enables---> [Decoupled triggers]
    +--enables---> [Pause on modal close]

[InnerBlocks custom poster]
    +--enhances---> [Poster image]
    +--conflicts---> [oEmbed thumbnail] (mutually exclusive: one or the other)

[Mutual exclusion]
    +--requires---> [Provider SDK lazy loading]
    +--requires---> [sitchco.hooks system]

[GTM analytics]
    +--requires---> [Provider SDK lazy loading]
    +--requires---> [sitchco.hooks system]

[Extension hooks]
    +--requires---> [sitchco.hooks system] (already exists)
    +--enhances---> [All video lifecycle events]
```

### Dependency Notes

- **UIModal content-based refactor is the critical prerequisite:** Currently, UIModal requires a `Post` object via `ModalData::fromPost()`. The video block needs `ModalData` to accept arbitrary HTML content (the iframe player) without a post. This refactor unblocks modal and modal-only display modes and also enables future non-post modals elsewhere.
- **Provider SDK loading is the foundation for advanced features:** Mutual exclusion, analytics, and programmatic pause all require the YouTube IFrame API and Vimeo Player SDK. Without SDKs, these features are impossible -- raw iframes don't expose playback state.
- **InnerBlocks and oEmbed thumbnail are mutually exclusive:** When InnerBlocks are present, they ARE the poster. When absent, oEmbed thumbnail is fetched and displayed. The block checks only whether InnerBlocks exist, never inspects their content.
- **Click-to-play facade requires poster + play icon:** The facade pattern needs something to show before the SDK loads. Poster provides the visual, play icon provides the affordance. Both must exist before the click handler can work.
- **Extension hooks enhance everything else:** The `video-play`, `video-pause`, `video-ended` actions and `playerVars` filter provide the seam where external code integrates. Carousel auto-pause, custom analytics, A/B testing -- all compose via hooks.

## MVP Definition

### Launch With (v1)

Minimum viable product -- what's needed to deliver the core value proposition of "paste URL, get privacy-respecting video."

- [ ] URL input with YouTube/Vimeo provider detection -- the entry point for everything
- [ ] oEmbed auto-fetch poster thumbnail -- zero-config visual representation
- [ ] oEmbed auto-populated video title -- accessibility and modal ID without author effort
- [ ] Click-to-play with provider SDK lazy loading -- the core privacy/performance behavior
- [ ] Play icon overlay with YouTube-branded variants -- legal compliance + visual affordance
- [ ] Privacy-enhanced embed URLs (youtube-nocookie.com, Vimeo dnt=1) -- the privacy promise
- [ ] Inline display mode with CLS prevention -- the simplest working mode
- [ ] Accessible play button with ARIA -- WCAG 2.1 AA compliance
- [ ] Native Gutenberg block with React edit component -- the block editor experience
- [ ] UIModal content-based refactor -- prerequisite for modal modes

### Add After Validation (v1.x)

Features to add once inline playback is working and the block editor experience is validated.

- [ ] Modal display mode -- when inline mode is proven, add modal composition
- [ ] Modal-only display mode -- when modal mode works, add invisible-on-page variant
- [ ] Decoupled triggers (href/data-target) -- when modal-only exists, enable external triggering
- [ ] Deep linking via URL hash -- when modal modes work, enable shareable links
- [ ] InnerBlocks custom poster override -- when oEmbed poster works, add composition
- [ ] Mutual exclusion -- when multiple videos exist on pages, prevent audio overlap
- [ ] Pause on modal close -- when modal modes exist, handle lifecycle
- [ ] Click behavior mode (entire poster vs icon only) -- when poster composition exists, add granularity
- [ ] Start time support -- when basic playback works, add URL parameter parsing
- [ ] Play icon X/Y positioning -- when play icon works, add position customization

### Future Consideration (v2+)

Features to defer until the block is battle-tested on production sites.

- [ ] GTM analytics events -- requires defining the dataLayer contract and milestone intervals
- [ ] Extension hooks (video-play, video-pause, video-ended, playerVars filter) -- requires stabilized internal API before exposing public hooks
- [ ] Generic play icon variants (dark/light) for non-YouTube -- low urgency, Vimeo doesn't require branded buttons
- [ ] Additional provider support -- only when actual user demand for Dailymotion/Wistia/etc. emerges

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| URL input + provider detection | HIGH | LOW | P1 |
| oEmbed auto-fetch poster | HIGH | MEDIUM | P1 |
| Click-to-play facade | HIGH | MEDIUM | P1 |
| Play icon overlay (YouTube-branded) | HIGH | LOW | P1 |
| Privacy-enhanced URLs | HIGH | LOW | P1 |
| CLS prevention | HIGH | MEDIUM | P1 |
| Accessible play button | HIGH | LOW | P1 |
| Inline display mode | HIGH | MEDIUM | P1 |
| Native Gutenberg block (edit component) | HIGH | HIGH | P1 |
| UIModal content-based refactor | HIGH | MEDIUM | P1 |
| Modal display mode | HIGH | HIGH | P2 |
| Modal-only display mode | MEDIUM | MEDIUM | P2 |
| InnerBlocks custom poster | MEDIUM | MEDIUM | P2 |
| Decoupled triggers | MEDIUM | LOW | P2 |
| Deep linking | MEDIUM | LOW | P2 |
| Mutual exclusion | MEDIUM | MEDIUM | P2 |
| Pause on modal close | MEDIUM | LOW | P2 |
| Click behavior mode | LOW | LOW | P2 |
| Start time support | LOW | LOW | P2 |
| Play icon X/Y positioning | LOW | LOW | P2 |
| GTM analytics events | MEDIUM | MEDIUM | P3 |
| Extension hooks | MEDIUM | LOW | P3 |
| Generic play icon (dark/light) | LOW | LOW | P3 |

**Priority key:**
- P1: Must have for launch -- core privacy-first value proposition
- P2: Should have, add when possible -- modal modes, composition, polish
- P3: Nice to have, future consideration -- analytics, extensibility

## Competitor Feature Analysis

| Feature | WP Video Popup | Embed Privacy | Lazy Load for Videos | Presto Player | lite-youtube-embed | Our Approach |
|---------|----------------|---------------|----------------------|---------------|-------------------|--------------|
| Click-to-play | Yes (modal only) | Yes (consent placeholder) | Yes (facade) | No (direct embed) | Yes (facade) | Yes -- facade pattern with SDK lazy load |
| Privacy-enhanced URLs | No | N/A (blocks all embeds) | Some | No | No | Yes -- youtube-nocookie.com + Vimeo dnt=1 |
| Poster image | Auto-fetched | Generic placeholder | Auto-fetched | Auto-fetched | Auto-fetched | Auto-fetched (oEmbed) + InnerBlocks custom composition |
| Modal/lightbox playback | Yes (core feature) | No | No | No | No | Yes -- three modes including modal-only |
| Inline playback | No | Yes (after consent) | Yes | Yes | Yes | Yes -- with CLS prevention |
| YouTube branded play button | No | N/A | No | No | No (uses YouTube's own) | Yes -- dark/light/red per ToS |
| Mutual exclusion | No | No | Yes (YouTube only, free) | No | No | Yes -- all providers via SDK |
| Analytics | Via GTM integration | No | No | Built-in dashboard | No | GTM dataLayer events |
| Deep linking | Via prettyPhoto | No | No | No | No | Yes -- UIModal hash sync |
| Accessibility (WCAG) | Basic | Basic | Basic | Basic | Basic | Full -- ARIA labels, keyboard, focus management |
| Custom poster content | No | No | No | Image upload | No | Any block type via InnerBlocks |
| Decoupled triggers | CSS class required | N/A | No | No | No | href/data-target (UIModal pattern) |
| Extension API | No | Filters (PHP) | No | Limited | No | Full JS hooks system (actions + filters) |
| Gutenberg native block | No (shortcode) | Filters core embeds | No (replaces oEmbed) | Yes | No (web component) | Yes -- block.json + React edit |
| Start time support | Yes | N/A | No | No | No | Yes -- URL param parsing |

## Sources

- [YouTube API Terms of Service - Branding Guidelines](https://developers.google.com/youtube/terms/branding-guidelines) -- YouTube play button branding requirements
- [YouTube Embedded Players and Player Parameters](https://developers.google.com/youtube/player_parameters) -- modestbranding deprecation, player parameters
- [YouTube Required Minimum Functionality](https://developers.google.com/youtube/terms/required-minimum-functionality) -- minimum 200x200px player, overlay restrictions
- [Vimeo Do-Not-Track (DNT)](https://ignite.video/en/articles/tutorials/vimeo-do-not-track) -- dnt=1 parameter behavior and limitations
- [Vimeo Player Cookies](https://help.vimeo.com/hc/en-us/articles/26080940921361-Vimeo-Player-Cookies) -- cookies set even with DNT
- [WordPress Performance Team - Facade Embeds Issue #113](https://github.com/WordPress/performance/issues/113) -- facade pattern for WordPress core
- [Gutenberg Issue #52185 - Video CLS](https://github.com/WordPress/gutenberg/issues/52185) -- CLS problems with video block
- [lite-youtube-embed](https://github.com/paulirish/lite-youtube-embed) -- reference facade implementation (224x faster than standard embed)
- [Embed Privacy Plugin](https://wordpress.org/plugins/embed-privacy/) -- privacy-first embed replacement approach
- [WP Video Popup](https://wordpress.org/plugins/responsive-youtube-vimeo-popup/) -- modal-only video playback reference
- [Lazy Load for Videos](https://wordpress.org/plugins/lazy-load-for-videos/) -- facade + mutual exclusion reference
- [Presto Player](https://prestoplayer.com/) -- full-featured WordPress video player comparison
- [web.dev - Embed Best Practices](https://web.dev/articles/embed-best-practices) -- facade pattern performance guidance
- [GTM4WP - Media Player Tracking](https://gtm4wp.com/setup-gtm4wp-features/how-to-track-embedded-media-players-youtube-vimeo-soundcloud) -- dataLayer event patterns
- [Complianz - YouTube and GDPR](https://complianz.io/youtube-and-the-gdpr-how-to-embed-youtube-on-your-site/) -- GDPR compliance requirements for YouTube embeds

---
*Feature research for: Privacy-first WordPress Gutenberg video embedding block*
*Researched: 2026-03-09*
