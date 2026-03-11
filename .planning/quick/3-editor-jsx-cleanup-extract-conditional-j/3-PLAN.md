---
phase: quick-3
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - modules/VideoBlock/blocks/video/editor.jsx
  - modules/VideoBlock/blocks/video/block.json
  - modules/VideoBlock/blocks/video/render.php
  - modules/VideoBlock/VideoBlockRenderer.php
  - tests/Modules/VideoBlock/VideoBlockTest.php
autonomous: true
requirements: [review-11, review-14, review-18]

must_haves:
  truths:
    - "Editor JSX return block uses named sub-components instead of flat conditional blocks"
    - "Thumbnail URL upgrade logic is a named function, not an inline nested ternary"
    - "_videoTitleEdited and _modalIdEdited attributes are removed from block.json"
    - "Auto-populate still skips overwriting user-edited videoTitle and modalId values"
    - "Build compiles without errors"
    - "PHP tests still pass"
  artifacts:
    - path: "modules/VideoBlock/blocks/video/editor.jsx"
      provides: "Cleaned-up editor with extracted sub-components and derived edit flags"
    - path: "modules/VideoBlock/blocks/video/block.json"
      provides: "Block attributes without _videoTitleEdited and _modalIdEdited"
    - path: "modules/VideoBlock/blocks/video/render.php"
      provides: "Render template without _*Edited attribute references"
    - path: "modules/VideoBlock/VideoBlockRenderer.php"
      provides: "Renderer class unchanged (no _*Edited references)"
    - path: "tests/Modules/VideoBlock/VideoBlockTest.php"
      provides: "Tests updated to remove _*Edited from attribute fixtures"
  key_links:
    - from: "editor.jsx upgradeThumbnailUrl()"
      to: "VideoBlockRenderer::upgradeThumbnailUrl()"
      via: "Same logic mirrored in JS and PHP"
      pattern: "maxresdefault|_1280x720"
---

<objective>
Clean up the editor.jsx component by addressing three code review items: extract conditional JSX sections into named sub-components, replace the nested ternary thumbnail logic with a named function, and derive the "edited" flags from state comparison instead of persisting them as block attributes.

Purpose: Improve editor.jsx readability and eliminate unnecessary persisted state from block serialization.
Output: Cleaner editor.jsx, simplified block.json, updated test fixtures.
</objective>

<execution_context>
@/Users/jstrom/.claude/get-shit-done/workflows/execute-plan.md
@/Users/jstrom/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@modules/VideoBlock/code-review/04-editor-jsx-cleanup.md
@modules/VideoBlock/blocks/video/editor.jsx
@modules/VideoBlock/blocks/video/block.json
@modules/VideoBlock/blocks/video/render.php
@modules/VideoBlock/VideoBlockRenderer.php
@tests/Modules/VideoBlock/VideoBlockTest.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Extract upgradeThumbnailUrl function and derive edit flags instead of persisting them</name>
  <files>modules/VideoBlock/blocks/video/editor.jsx, modules/VideoBlock/blocks/video/block.json</files>
  <action>
In editor.jsx:

1. **Extract `upgradeThumbnailUrl(url, provider)` function** (place near `detectProvider` and `slugify` at top of file):
   ```js
   function upgradeThumbnailUrl(url, provider) {
       if (provider === 'youtube') {
           return url.replace(/\/hqdefault\.jpg$/, '/maxresdefault.jpg');
       }
       if (provider === 'vimeo') {
           return url.replace(/_\d+x\d+/, '_1280x720');
       }
       return url;
   }
   ```
   This mirrors `VideoBlockRenderer::upgradeThumbnailUrl()` in PHP.

2. **Remove `_videoTitleEdited` and `_modalIdEdited` from attribute destructuring** (line 84-85). Remove the refs `videoTitleEditedRef` and `modalIdEditedRef` (lines 95-96) and the useEffect that syncs them (lines 98-101).

3. **Derive "edited" state from oEmbed comparison** in the oEmbed fetch `.then()` callback. Replace:
   ```js
   if (response.title && !videoTitleEditedRef.current) {
       setAttributes({ videoTitle: response.title });
   }
   if (response.title && !modalIdEditedRef.current) {
       setAttributes({ modalId: slugify(response.title) });
   }
   ```
   With derivation logic that compares current attribute values to what oEmbed would auto-generate:
   ```js
   if (response.title) {
       const updates = {};
       // Auto-populate only if current value is empty or matches what
       // oEmbed would have auto-generated (user hasn't manually edited)
       if (!videoTitle || videoTitle === oembedData?.title) {
           updates.videoTitle = response.title;
       }
       if (!modalId || modalId === slugify(oembedData?.title || '')) {
           updates.modalId = slugify(response.title);
       }
       if (Object.keys(updates).length > 0) {
           setAttributes(updates);
       }
   }
   ```
   Note: `oembedData` here is the PREVIOUS oEmbed data (before this fetch resolved), accessed via the state variable. On initial load, `oembedData` is null, so `videoTitle === oembedData?.title` is `videoTitle === undefined` which is false -- meaning if the user has any saved title, it won't be overwritten. When `videoTitle` is empty (fresh block), it auto-populates. When `videoTitle` matches the previous oEmbed title (user never edited it), it updates to the new oEmbed title if the URL changed.

   However, we need the PREVIOUS oEmbed title to compare against. Use a ref to store the last oEmbed title:
   ```js
   const prevOembedTitleRef = useRef(null);
   ```
   In the .then() callback, before updating state:
   ```js
   if (response.title) {
       const updates = {};
       const prevTitle = prevOembedTitleRef.current;
       if (!videoTitle || videoTitle === prevTitle) {
           updates.videoTitle = response.title;
       }
       if (!modalId || modalId === slugify(prevTitle || '')) {
           updates.modalId = slugify(response.title);
       }
       if (Object.keys(updates).length > 0) {
           setAttributes(updates);
       }
       prevOembedTitleRef.current = response.title;
   }
   ```
   This handles all cases:
   - Fresh block (no title yet): `!videoTitle` is true, auto-populates
   - URL changed (title matches previous oEmbed): auto-updates to new oEmbed title
   - User manually edited title: doesn't match previous oEmbed, preserved

4. **Remove `_videoTitleEdited: true` and `_modalIdEdited: true`** from the onChange handlers of the Video Title and Modal ID TextControls (lines 243 and 258). The onChange handlers become simply:
   - Video Title: `onChange={(value) => setAttributes({ videoTitle: value })}`
   - Modal ID: `onChange={(value) => setAttributes({ modalId: slugify(value) })}`

5. **Replace the nested ternary in the `<img>` src prop** (lines 351-355) with a call to `upgradeThumbnailUrl(oembedData.thumbnail_url, provider)`.

6. **In block.json**, remove the two attribute entries:
   - `"_videoTitleEdited": { "type": "boolean", "default": false }`
   - `"_modalIdEdited": { "type": "boolean", "default": false }`
  </action>
  <verify>
    <automated>cd /Users/jstrom/Projects/web/roundabout/public && make build 2>&1 | tail -20</automated>
  </verify>
  <done>
  - `upgradeThumbnailUrl()` is a named function at module top level
  - No nested ternary in JSX `src` prop
  - `_videoTitleEdited` and `_modalIdEdited` fully removed from editor.jsx and block.json
  - Auto-populate logic uses derivation (value comparison) instead of persisted boolean flags
  - Build compiles without errors
  </done>
</task>

<task type="auto">
  <name>Task 2: Extract conditional JSX into named sub-components</name>
  <files>modules/VideoBlock/blocks/video/editor.jsx</files>
  <action>
Extract the 5 conditional JSX blocks in the return statement (lines ~319-399) into clearly named helper components or render functions defined inside `Edit`. This turns the flat conditional mess into a readable sequence of named sections.

Define these as arrow-function components inside `Edit` (they close over the component's local variables, so they don't need props):

1. **`renderPlaceholder`** -- the "no URL" Placeholder (current lines 319-325):
   ```jsx
   const renderPlaceholder = () => {
       if (url) return null;
       return (
           <Placeholder icon="video-alt3" label={__('Video', 'sitchco')}
               instructions={__('Enter a video URL in the block settings.', 'sitchco')} />
       );
   };
   ```

2. **`renderLoading`** -- the loading spinner (current lines 327-331):
   ```jsx
   const renderLoading = () => {
       if (!url || !isLoading) return null;
       return <div className="sitchco-video__loading"><Spinner /></div>;
   };
   ```

3. **`renderError`** -- the error state (current lines 333-337):
   ```jsx
   const renderError = () => {
       if (!url || !error) return null;
       return <div className="sitchco-video__error"><p>{error}</p></div>;
   };
   ```

4. **`renderPreview`** -- the thumbnail preview (current lines 339-360):
   ```jsx
   const renderPreview = () => {
       if (!url || isModalOnly || hasInnerBlocks || !oembedData?.thumbnail_url) return null;
       return (
           <div className="sitchco-video__preview"
               style={oembedData.width && oembedData.height
                   ? { aspectRatio: `${oembedData.width} / ${oembedData.height}` }
                   : undefined}>
               <img className="sitchco-video__thumbnail"
                   src={upgradeThumbnailUrl(oembedData.thumbnail_url, provider)}
                   alt={oembedData.title || ''} />
           </div>
       );
   };
   ```

5. **`renderEmptyState`** -- the "enter a URL" prompt when oEmbed hasn't returned (current lines 362-366):
   ```jsx
   const renderEmptyState = () => {
       if (!url || isLoading || error || oembedData) return null;
       return (
           <div className="sitchco-video__placeholder">
               <p>{__('Enter a YouTube or Vimeo URL to see a preview.', 'sitchco')}</p>
           </div>
       );
   };
   ```

Then the return JSX body (inside `<div {...blockProps}>`, after `</InspectorControls>`) becomes:

```jsx
{renderPlaceholder()}
{renderLoading()}
{renderError()}
{renderPreview()}
{renderEmptyState()}

{isModalOnly ? (
    <Placeholder icon="video-alt3" label={__('Modal Only', 'sitchco')}>
        {url && (
            <>
                <p><strong>{__('Modal ID:', 'sitchco')}</strong>{' '}
                    {modalId || __('(auto-generated from title)', 'sitchco')}</p>
                <p><strong>{__('URL:', 'sitchco')}</strong> {url}</p>
            </>
        )}
    </Placeholder>
) : (
    <InnerBlocks />
)}

{url && !isModalOnly && (
    <div className={`sitchco-video__play-icon sitchco-video__play-button--${playIconStyle}`}
        aria-hidden="true"
        style={{ position: 'absolute', left: `${playIconX}%`, top: `${playIconY}%`, transform: 'translate(-50%, -50%)' }}>
        {getPlayIconSvg(provider)}
    </div>
)}
```

The modal-only placeholder and play icon blocks are small enough to keep inline. The key improvement is the 5 conditional visual states are now named.
  </action>
  <verify>
    <automated>cd /Users/jstrom/Projects/web/roundabout/public && make build 2>&1 | tail -20</automated>
  </verify>
  <done>
  - Five named render functions replace the flat conditional JSX blocks
  - Return statement is a clear, readable sequence of named sections
  - Build compiles without errors
  - No behavioral changes -- same conditions, same output
  </done>
</task>

<task type="auto">
  <name>Task 3: Update render.php and PHP tests to remove _*Edited attribute references</name>
  <files>modules/VideoBlock/blocks/video/render.php, modules/VideoBlock/VideoBlockRenderer.php, tests/Modules/VideoBlock/VideoBlockTest.php</files>
  <action>
1. **Check render.php** for any references to `_videoTitleEdited` or `_modalIdEdited`. The current render.php delegates to `VideoBlockRenderer::render()`, which receives `$attributes`. Check if the renderer reads these flags. Based on grep results, the renderer does NOT use these flags -- they're editor-only. However, WordPress will still pass them in the `$attributes` array from saved block content. Since we removed them from block.json, WordPress will no longer include them in `$attributes` for new saves. Old saved content may still have them serialized in the block comment delimiter, but WordPress strips attributes not in block.json on re-save. No PHP code changes needed for render.php or VideoBlockRenderer.php unless they reference these attributes.

2. **Update `VideoBlockTest.php`**: Remove `_videoTitleEdited` and `_modalIdEdited` from the `makeAttributes()` helper method's default array (lines 662-663). Also remove them from the explicit attribute arrays in `test_render_without_url_outputs_innerblocks_content` (lines 52-53) and `test_render_with_url_outputs_wrapper_with_data_attributes` (lines 72-73).

3. **Run the test suite** to verify nothing breaks. The renderer doesn't use these attributes, so removing them from test fixtures should be seamless.
  </action>
  <verify>
    <automated>cd /Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core && ddev test-phpunit -- --filter=VideoBlockTest 2>&1 | tail -30</automated>
  </verify>
  <done>
  - `_videoTitleEdited` and `_modalIdEdited` removed from all test attribute fixtures
  - All VideoBlockTest tests pass
  - No PHP files reference the removed attributes
  </done>
</task>

</tasks>

<verification>
1. `make build` succeeds (editor.jsx compiles)
2. `ddev test-phpunit -- --filter=VideoBlockTest` passes all tests
3. `grep -r '_videoTitleEdited\|_modalIdEdited' modules/VideoBlock/blocks/` returns no matches
4. `grep 'upgradeThumbnailUrl' modules/VideoBlock/blocks/video/editor.jsx` shows the named function definition and usage
</verification>

<success_criteria>
- editor.jsx has a named `upgradeThumbnailUrl()` function, no nested ternary in JSX
- editor.jsx has 5 named render functions replacing flat conditional blocks
- `_videoTitleEdited` and `_modalIdEdited` removed from block.json, editor.jsx, and test fixtures
- Auto-populate logic derives "edited" state from value comparison instead of persisted flags
- Build compiles, PHP tests pass
</success_criteria>

<output>
After completion, create `.planning/quick/3-editor-jsx-cleanup-extract-conditional-j/3-SUMMARY.md`
</output>
