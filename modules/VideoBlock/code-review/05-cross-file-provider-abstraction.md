# Cross-File Provider Abstraction

These findings span multiple files and relate to how provider-specific logic (YouTube, Vimeo) is organized across the codebase. Addressing these together ensures consistency.

Source review: `modules/VideoBlock/code-review.md`

---

## 2. Duplicated thumbnail URL upgrade logic (JS + PHP)

**Severity: Medium**

The same regex replacements exist in two places with no shared abstraction:

- **render.php:48-65** `sitchco_video_upgrade_thumbnail_url()` as a named function
- **editor.jsx:348-353** inline in JSX as a nested ternary

```jsx
// editor.jsx - inlined
provider === 'youtube'
    ? oembedData.thumbnail_url.replace(/\/hqdefault\.jpg$/, '/maxresdefault.jpg')
    : provider === 'vimeo'
      ? oembedData.thumbnail_url.replace(/_\d+x\d+/, '_1280x720')
      : oembedData.thumbnail_url
```

This logic will drift. Since these run in different environments (PHP vs JS), true sharing isn't possible, but they should at least be named functions with matching signatures and documented as intentional mirrors.

---

## 6. OCP: Provider logic scattered across 8+ locations

**Severity: Medium**

Adding a third provider (e.g., Dailymotion) would require changes in **at least 8 locations** across 3 files:

| File | Locations |
|------|-----------|
| editor.jsx | `detectProvider()`, `getPlayIconSvg()`, thumbnail ternary, `playIconStyleOptions` |
| render.php | `sitchco_video_upgrade_thumbnail_url()`, `sitchco_video_extract_id()`, icon name selection |
| view.js | `handlePlay()` provider branch, start time extraction, SDK loader, player creator |

The spec explicitly notes "architecture supports [provider expansion], but no implementation." The current structure doesn't support it -- it actively resists it. A provider strategy (even a simple object map per file) would contain each provider's behavior in one place.

---

## 16. Inconsistent play icon rendering (editor vs frontend)

**Severity: Low**

The editor renders inline SVGs (`getPlayIconSvg()`) while the frontend uses SVG sprites via `<use href="#icon-...">`. This means:
- Two different rendering codepaths to maintain
- Styling mechanisms diverge (editor uses direct attributes, frontend uses sprite inheritance)
- Visual parity between editor and frontend can't be guaranteed structurally

Consider unifying the approach or at minimum ensuring the SVG content is defined in one source of truth.
