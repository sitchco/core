# Bug Fixes & Quick Wins

Small, independent fixes that can be done in any order. Prioritize #12 and #13 as they affect correctness.

Source review: `modules/VideoBlock/code-review.md`

---

## 12. Remove debug logging in production code (render.php:114)

**Severity: High**

```php
error_log('$oembed: ' . stripcslashes(json_encode($oembed, JSON_PRETTY_PRINT)));
```

This debug output fires on every page load for every video block without InnerBlocks. Remove it.

---

## 13. Fix stale closure in oEmbed effect (editor.jsx)

**Severity: High**

The `useEffect` dependency array is `[url]` but the `.then()` callback reads `_videoTitleEdited` and `_modalIdEdited`. These values are captured at effect creation time and won't reflect changes between the URL change and the async response:

```jsx
useEffect(() => {
    // ...
    .then((response) => {
        if (response.title && !_videoTitleEdited) {  // stale
            setAttributes({ videoTitle: response.title });
        }
        if (response.title && !_modalIdEdited) {      // stale
            setAttributes({ modalId: slugify(response.title) });
        }
    });
}, [url]);  // missing _videoTitleEdited, _modalIdEdited
```

If the author edits the title while an oEmbed request is in flight, the flag change won't be seen. Fix by adding the missing dependencies or using refs for the flags.

---

## 17. Add missing `wp-data` dependency (editor.asset.php)

**Severity: Medium**

`editor.jsx` imports `useSelect` from `@wordpress/data` but `editor.asset.php` doesn't list `wp-data` in its dependencies array. This works by accident (another loaded script likely pulls it in) but is incorrect and could break in isolation.

---

## 4. Remove redundant provider detection in oEmbed effect (editor.jsx)

**Severity: Low**

Line 144 re-calls `detectProvider(url)` inside the `useEffect`, but `provider` is already set as an attribute by `onUrlChange()` and available in scope. Use the existing attribute value instead.
