# Cross-File Provider Abstraction

These findings span multiple files and relate to how provider-specific logic (YouTube, Vimeo) is organized across the codebase. Addressing these together ensures consistency.

Source review: `modules/VideoBlock/code-review.md`

---

## ~~2. Duplicated thumbnail URL upgrade logic (JS + PHP)~~ RESOLVED

**Severity: Medium** → **Resolved in quick-3**

Both sides now have named functions with matching signatures, documented as intentional mirrors:

- **VideoBlockRenderer.php:44-55** — `VideoBlockRenderer::upgradeThumbnailUrl()`
- **editor.jsx:42-50** — `upgradeThumbnailUrl()` with docblock: "Mirrors VideoBlockRenderer::upgradeThumbnailUrl() in PHP"

No further action needed.

---

## 6. OCP: Provider logic scattered across 8+ locations

**Severity: Medium**

Adding a third provider (e.g., Dailymotion) would require changes in **at least 8 locations** across 3 files:

| File | Locations |
|------|-----------|
| editor.jsx | `detectProvider()`, `getPlayIconSvg()`, `upgradeThumbnailUrl()`, `playIconStyleOptions` |
| VideoBlockRenderer.php | `upgradeThumbnailUrl()`, `extractVideoId()`, `buildPlayButton()` icon name/dimensions |
| view.js | `handlePlay()` provider branch, `extractYouTubeStartTime()`/`extractVimeoStartTime()`, `loadYouTubeAPI()`/`loadVimeoSDK()`, `createYouTubePlayer()`/`createVimeoPlayer()`, `handleModalShow()` resume branch |

The spec explicitly notes "architecture supports [provider expansion], but no implementation." The current structure doesn't support it -- it actively resists it. A provider strategy (even a simple object map per file) would contain each provider's behavior in one place.

---

## 16. Inconsistent play icon rendering (editor vs frontend)

**Severity: Low**

The editor renders inline SVGs (`getPlayIconSvg()` in editor.jsx:58) while the frontend uses SVG sprites via `<use href="#icon-...">` (`VideoBlockRenderer::buildPlayButton()` at VideoBlockRenderer.php:246). This means:
- Two different rendering codepaths to maintain
- Styling mechanisms diverge (editor uses direct attributes, frontend uses sprite inheritance)
- Visual parity between editor and frontend can't be guaranteed structurally

Consider unifying the approach or at minimum ensuring the SVG content is defined in one source of truth.
