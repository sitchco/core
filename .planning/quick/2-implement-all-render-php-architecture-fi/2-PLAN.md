---
phase: quick
plan: 2
type: execute
wave: 1
depends_on: []
files_modified:
  - modules/VideoBlock/VideoBlockRenderer.php
  - modules/VideoBlock/VideoBlock.php
  - modules/VideoBlock/blocks/video/render.php
  - tests/Modules/VideoBlock/VideoBlockTest.php
autonomous: true
requirements: [CR-05, CR-07, CR-08, CR-09, CR-15]

must_haves:
  truths:
    - "All 3 global function_exists() definitions are removed from render.php"
    - "render.php no longer accesses $GLOBALS['SitchcoContainer'] directly"
    - "Data preparation is separated from HTML rendering in a dedicated class"
    - "All existing tests pass without modification to assertions"
  artifacts:
    - path: "modules/VideoBlock/VideoBlockRenderer.php"
      provides: "Dedicated renderer class with static utility methods and render orchestration"
      contains: "class VideoBlockRenderer"
    - path: "modules/VideoBlock/VideoBlock.php"
      provides: "Module class that exposes UIModal dependency for render context"
      contains: "uiModal"
    - path: "modules/VideoBlock/blocks/video/render.php"
      provides: "Thin wrapper delegating to VideoBlockRenderer"
      min_lines: 5
  key_links:
    - from: "modules/VideoBlock/blocks/video/render.php"
      to: "modules/VideoBlock/VideoBlockRenderer.php"
      via: "static method call"
      pattern: "VideoBlockRenderer::render"
    - from: "modules/VideoBlock/VideoBlockRenderer.php"
      to: "modules/UIModal/UIModal.php"
      via: "UIModal parameter passed in from VideoBlock"
      pattern: "UIModal \\$uiModal"
    - from: "modules/VideoBlock/VideoBlock.php"
      to: "modules/VideoBlock/VideoBlockRenderer.php"
      via: "provides UIModal dependency to renderer"
      pattern: "uiModal"
---

<objective>
Refactor render.php to address all 5 architecture findings from code review (CR-05 SRP, CR-07 DIP, CR-08 function_exists guards, CR-09 imperative style, CR-15 sprintf chains).

Purpose: Extract the 233-line procedural render.php into a clean class-based architecture with separated data preparation and rendering, proper dependency injection, and no global namespace pollution.

Output: VideoBlockRenderer.php class, updated VideoBlock.php, thin render.php wrapper. All existing tests continue to pass.
</objective>

<execution_context>
@/Users/jstrom/.claude/get-shit-done/workflows/execute-plan.md
@/Users/jstrom/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@modules/VideoBlock/blocks/video/render.php
@modules/VideoBlock/VideoBlock.php
@modules/UIModal/UIModal.php
@modules/UIModal/ModalData.php
@src/Framework/Module.php
@src/Utils/Cache.php
@tests/Modules/VideoBlock/VideoBlockTest.php
@modules/VideoBlock/code-review/03-render-php-architecture.md

<interfaces>
<!-- Key types and contracts the executor needs. -->

From modules/UIModal/UIModal.php:
```php
namespace Sitchco\Modules\UIModal;
class UIModal extends Module {
    public function loadModal(ModalData $modal): ?ModalData;
}
```

From modules/UIModal/ModalData.php:
```php
namespace Sitchco\Modules\UIModal;
readonly class ModalData {
    public function __construct(string $id, string $heading, string $content, public ModalType $type);
}
```

From src/Utils/Cache.php:
```php
namespace Sitchco\Utils;
class Cache {
    public static function rememberTransient(string $key, callable $callback, int $expiration = DAY_IN_SECONDS): mixed;
}
```

From src/Framework/Module.php:
```php
namespace Sitchco\Framework;
abstract class Module {
    use HasHooks;
    public const DEPENDENCIES = [];
    public function init() {}
    public function path(string $relative = ''): FilePath;
    public function blocksPath(): FilePath;
}
```

From modules/VideoBlock/VideoBlock.php (current):
```php
namespace Sitchco\Modules\VideoBlock;
class VideoBlock extends Module {
    public const DEPENDENCIES = [UIModal::class];
    const HOOK_SUFFIX = 'video-block';
    public function init(): void {}
}
```

IMPORTANT: This is a native WordPress block (block.json with "render": "file:./render.php").
Native blocks receive only `$attributes`, `$content`, `$block` in render.php -- there is NO automatic
`$container` injection like ACF blocks have. The DIP fix must work within this constraint.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create VideoBlockRenderer class and update VideoBlock to expose UIModal</name>
  <files>modules/VideoBlock/VideoBlockRenderer.php, modules/VideoBlock/VideoBlock.php</files>
  <action>
**Create `modules/VideoBlock/VideoBlockRenderer.php`** in namespace `Sitchco\Modules\VideoBlock`.

This class replaces all logic currently in render.php. It has:

**Static utility methods** (CR-08: replaces function_exists globals):

1. `public static function getCachedOembedData(string $url): ?object` -- identical logic to current `sitchco_video_get_cached_oembed_data()`. Uses `Cache::rememberTransient()` with `sitchco_voembed_` prefix and 30-day TTL.

2. `public static function upgradeThumbnailUrl(string $url, string $provider): string` -- identical logic to current `sitchco_video_upgrade_thumbnail_url()`. YouTube: replace hqdefault.jpg with maxresdefault.jpg. Vimeo: rewrite CDN dimensions to 1280x720.

3. `public static function extractVideoId(string $url, string $provider): string` -- identical logic to current `sitchco_video_extract_id()`. YouTube: 11-char ID regex. Vimeo: numeric ID regex.

**Main render method** (CR-05 SRP, CR-09 data/rendering separation):

4. `public static function render(array $attributes, string $content, object $block, ?UIModal $uiModal = null): string` -- orchestrates the full render. Structure:

```
Phase 1 - Early return:
  if empty url, return $content

Phase 2 - Extract attributes into local vars (same as current lines 90-97)

Phase 3 - Build view data (pure computation, no output):
  - $video_id via self::extractVideoId()
  - $poster_html, $poster_style via poster resolution chain (same logic as current lines 103-126)
    Use self::getCachedOembedData() and self::upgradeThumbnailUrl()
  - $play_button_html via buildPlayButton() private static helper
  - $wrapper_attrs array (same as current lines 152-163)

Phase 4 - Modal side effects (if display_mode is modal/modal-only):
  - Same logic as current lines 166-216
  - Uses $uiModal parameter instead of $GLOBALS['SitchcoContainer']->get(UIModal::class) (CR-07)
  - If $uiModal is null and modal mode requested, fall back to $GLOBALS['SitchcoContainer']->get(UIModal::class) for backward compatibility, but this path should not be needed in production

Phase 5 - Accessibility attributes (same as current lines 219-223)

Phase 6 - Return HTML string (do NOT echo):
  - Use get_block_wrapper_attributes() and return the assembled HTML
  - For modal-only, return '' (empty string)
```

**Replace sprintf chains with heredoc** (CR-15):

For the play button HTML (current lines 132-149), poster img (current lines 113-117), modal player content (current lines 194-203), and modal thumbnail img (current lines 185-192), use heredoc syntax with embedded variable interpolation instead of long sprintf chains. Pre-escape all variables before the heredoc so the template reads cleanly.

Example pattern:
```php
$escaped_url = esc_url($thumb_url);
$escaped_w = esc_attr($aspect_w);
$escaped_h = esc_attr($aspect_h);
$thumb_img = <<<HTML
<img src="{$escaped_url}" alt="" class="sitchco-video__modal-poster-img" width="{$escaped_w}" height="{$escaped_h}">
HTML;
```

Keep simple 2-3 placeholder sprintf calls (like the play button aria-label) as-is -- heredoc is only needed for the 5+ placeholder cases.

**Private static helper:**

5. `private static function buildPlayButton(string $provider, string $play_icon_style, int|float $play_icon_x, int|float $play_icon_y, string $video_title): string` -- extracts play icon SVG and button HTML generation. Returns the full button HTML string.

**Update `modules/VideoBlock/VideoBlock.php`** (CR-07 DIP):

Add a property and getter so render.php can access UIModal without globals:

```php
private ?UIModal $uiModal = null;

public function init(): void
{
    $this->uiModal = $GLOBALS['SitchcoContainer']->get(UIModal::class);
}

public function uiModal(): ?UIModal
{
    return $this->uiModal;
}
```

This keeps the $GLOBALS access in exactly ONE place (the module init) rather than scattered in render templates. The container access happens once at init time, not at render time.
  </action>
  <verify>
    <automated>cd /Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core && php -l modules/VideoBlock/VideoBlockRenderer.php && php -l modules/VideoBlock/VideoBlock.php</automated>
  </verify>
  <done>VideoBlockRenderer.php exists with all 5 public static methods and 1 private helper. VideoBlock.php exposes uiModal() getter. Both files pass PHP lint.</done>
</task>

<task type="auto">
  <name>Task 2: Replace render.php with thin wrapper and update tests</name>
  <files>modules/VideoBlock/blocks/video/render.php, tests/Modules/VideoBlock/VideoBlockTest.php</files>
  <action>
**Replace `modules/VideoBlock/blocks/video/render.php`** with a thin wrapper (target: ~15 lines):

```php
<?php
/**
 * Server-side render template for sitchco/video block.
 *
 * @var array    $attributes Block attributes
 * @var string   $content    InnerBlocks content (serialized HTML)
 * @var WP_Block $block      Block instance
 */

use Sitchco\Modules\VideoBlock\VideoBlock;
use Sitchco\Modules\VideoBlock\VideoBlockRenderer;

$videoBlock = $GLOBALS['SitchcoContainer']->get(VideoBlock::class);
$output = VideoBlockRenderer::render($attributes, $content, $block, $videoBlock->uiModal());

if ($output !== '') {
    echo $output;
}
```

This addresses:
- CR-05: render.php is now a thin wrapper, all logic in VideoBlockRenderer
- CR-07: UIModal comes from VideoBlock->uiModal(), not direct container access in render logic
- CR-08: No function_exists() guards -- utility functions are class methods
- CR-09: render.php only wires; data prep and rendering are in the class

**Update `tests/Modules/VideoBlock/VideoBlockTest.php`:**

The `renderBlock()` private helper currently uses a raw `include` which worked for the procedural script. Since render.php now delegates to VideoBlockRenderer::render(), the include-based approach should still work because render.php still expects the same `$attributes`, `$content`, `$block` variables.

However, the test's `$block` is currently `new \stdClass()` which has no `inner_blocks` property. The current render.php accesses `$block->inner_blocks` (line 103). This has been working because the inner_blocks check is based on `count($block->inner_blocks) > 0` and stdClass with no property would trigger a warning but still work.

Update the `renderBlock()` helper to provide a proper mock:

```php
$block = new \stdClass();
$block->inner_blocks = [];
```

And for tests that pass InnerBlocks content (non-empty $content), also set:
```php
$block->inner_blocks = $content ? [true] : [];
```

Wait -- actually review the current behavior more carefully. The test `test_render_innerblocks_as_poster` passes `'<p>Custom poster</p>'` as content and expects InnerBlocks to be used as poster. But `$block->inner_blocks` on a stdClass would be undefined, so `count()` would be 0, meaning it would NOT use InnerBlocks.

Looking at the actual test assertion: it checks that the content string appears in output AND no `<img>` tag appears. The content appears because the poster resolution chain falls to the oEmbed path, but since no oEmbed is faked for that test, it falls to the placeholder poster. But the test passes... Let me re-read: the content IS in the output because the wrapper includes InnerBlocks content in the poster div when `$has_inner_blocks` is true. But with stdClass, inner_blocks would be missing.

Actually, the test must be working because render.php line 106 does `$poster_html = $content` when `$has_inner_blocks` is true, but it would NOT be true with stdClass. Yet the test passes. This means either:
1. stdClass magically has inner_blocks, OR
2. The content appears through a different path

Review: without fakeOembedResponse, the oEmbed call returns null, so poster_html becomes the placeholder div. The assertStringNotContainsString('<img') passes because there's no img. The assertStringContainsString('<p>Custom poster</p>') passes because... content appears in the final printf line 228 as part of `$poster_html`? No -- if inner_blocks count is 0, poster_html is the placeholder div, not $content.

Actually wait -- the `$content` variable in render.php IS the InnerBlocks serialized HTML from WordPress. In the final output `printf('<div %s>...<div class="sitchco-video__poster"%s>%s</div>%s</div>', ...)`, the `$poster_html` is the third `%s`. If inner_blocks is empty, poster_html is the placeholder. But the test asserts $content appears somewhere...

Let me re-check: no, the final printf only has `$poster_html` and `$play_button` -- `$content` is NOT directly in the output unless it was assigned to `$poster_html`. So either:
- The test is actually failing (unlikely since CI passed), OR
- The stdClass somehow triggers a truthy inner_blocks check

PHP `count()` on an undefined property of stdClass would throw a Warning and return 0 in PHP 8.x, or return 1 for the object in older PHP. In PHP 8+, accessing undefined property on stdClass issues a deprecation notice and returns null, and `count(null)` returns 0 with a warning.

So the test may actually be relying on some quirk or may have been auto-approved. For safety, update the test helper to properly mock inner_blocks based on content.

**Updated `renderBlock()` helper:**

```php
private function renderBlock(array $attributes, string $content): string
{
    $module = $this->container->get(VideoBlock::class);
    $renderFile = $module->blocksPath()->append('video/render.php')->value();

    $block = new \stdClass();
    $block->inner_blocks = $content !== '' ? [true] : [];

    ob_start();
    (function (string $_file, array $attributes, string $content, object $block) {
        include $_file;
    })($renderFile, $attributes, $content, $block);
    return ob_get_clean();
}
```

This properly simulates that non-empty content means InnerBlocks are present.

Run the full test suite to confirm all tests pass.
  </action>
  <verify>
    <automated>cd /Users/jstrom/Projects/web/roundabout/public/wp-content/mu-plugins/sitchco-core && ddev test-phpunit -- --filter=VideoBlockTest</automated>
  </verify>
  <done>render.php is under 20 lines. All 20 VideoBlockTest tests pass. No function_exists() guards remain. No direct $GLOBALS container access in VideoBlockRenderer. sprintf chains with 5+ placeholders replaced with heredoc.</done>
</task>

</tasks>

<verification>
1. `php -l modules/VideoBlock/VideoBlockRenderer.php` -- no syntax errors
2. `php -l modules/VideoBlock/VideoBlock.php` -- no syntax errors
3. `php -l modules/VideoBlock/blocks/video/render.php` -- no syntax errors
4. `ddev test-phpunit -- --filter=VideoBlockTest` -- all tests pass
5. `grep -c 'function_exists' modules/VideoBlock/blocks/video/render.php` -- returns 0
6. `grep -c '\$GLOBALS' modules/VideoBlock/VideoBlockRenderer.php` -- returns 0 (no direct globals in renderer)
7. `wc -l modules/VideoBlock/blocks/video/render.php` -- under 20 lines
</verification>

<success_criteria>
- VideoBlockRenderer.php contains all extracted logic as static methods
- render.php is a thin wrapper under 20 lines with no business logic
- All 20 existing VideoBlockTest tests pass unchanged (assertion-wise)
- No function_exists() guards in any VideoBlock file
- No $GLOBALS access in VideoBlockRenderer (only in render.php for getting VideoBlock, and in VideoBlock::init for getting UIModal)
- sprintf chains with 5+ placeholders replaced with heredoc
- Data preparation and HTML rendering are clearly separated in VideoBlockRenderer::render()
</success_criteria>

<output>
After completion, create `.planning/quick/2-implement-all-render-php-architecture-fi/2-SUMMARY.md`
</output>
