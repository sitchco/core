# Editor JSX Cleanup

Readability and maintainability improvements to the block editor component.

Source review: `modules/VideoBlock/code-review.md`

---

## 11. Complex flat conditional JSX

**Severity: Low**

The return JSX (lines 200-397) has 5 conditional blocks that are hard to follow as a flat sequence. Each represents a distinct visual state (no URL, loading, error, preview, placeholder, modal-only vs inline, play icon).

Extracting these into named sub-components or a state-machine approach would clarify the rendering logic.

---

## 14. Nested ternary in JSX (editor.jsx:348-353)

**Severity: Low**

The thumbnail URL computation is a nested ternary embedded directly in the JSX `src` prop:

```jsx
provider === 'youtube'
    ? oembedData.thumbnail_url.replace(/\/hqdefault\.jpg$/, '/maxresdefault.jpg')
    : provider === 'vimeo'
      ? oembedData.thumbnail_url.replace(/_\d+x\d+/, '_1280x720')
      : oembedData.thumbnail_url
```

Hard to read at a glance. Extract to a named function. (Also duplicates render.php logic -- see `05-cross-file-provider-abstraction.md` #2.)

---

## 18. Private attribute naming convention

**Severity: Low**

`_videoTitleEdited` and `_modalIdEdited` use underscore prefixes as a convention for "internal" attributes, but they're stored in the block's serialized JSON alongside public attributes. This is a UI-state concern being persisted as content.

Consider whether these flags could be derived (compare current value to what oEmbed would produce) rather than stored.
