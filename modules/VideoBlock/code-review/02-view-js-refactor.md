# view.js Refactor

DRY consolidation and modernization of the frontend video player script.

Source review: `modules/VideoBlock/code-review.md`

---

## 1. Consolidate duplicated player creation functions

**Severity: High**

`createYouTubePlayer` and `createModalYouTubePlayer` share ~90% of their code. Same for the Vimeo pair. The only differences: modal variants create a wrapper div, store a reference in `modalPlayers`, and add a `--ready` class.

```
createYouTubePlayer        (lines 63-87)
createModalYouTubePlayer   (lines 94-129)
createVimeoPlayer          (lines 144-161)
createModalVimeoPlayer     (lines 168-198)
```

Each pair should be a single function with a `modalId` parameter that optionally enables the modal behavior.

---

## 3. Consolidate duplicated event binding pattern

**Severity: Medium**

`initVideoBlock()` repeats the same click + keyboard handler attachment pattern for both modal and inline modes (lines 332-405). The keyboard handler logic (Enter/Space check, `preventDefault`, dispatch action) appears twice with only the callback differing.

Extract a shared `bindPlayTrigger(element, callback)` helper or similar.

---

## 10. Replace global mutable state + var usage

**Severity: Medium**

`ytAPIPromise` and `modalPlayers` are module-level mutable variables (`var`). The file uses `var` exclusively instead of `const`/`let`, losing block-scoping guarantees.

The `modalPlayers` Map mixes three concerns into one plain object: loading state, player reference, and provider type.

Actions:
- Replace all `var` with `const`/`let` as appropriate
- Consider restructuring `modalPlayers` into a cleaner data structure
- Minimize module-level mutable state where possible
