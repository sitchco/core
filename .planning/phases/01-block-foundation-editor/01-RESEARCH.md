# Phase 1: Block Foundation & Editor - Research

**Researched:** 2026-03-09
**Domain:** WordPress Gutenberg native block development with React edit component, oEmbed integration, and InnerBlocks persistence
**Confidence:** HIGH

## Summary

Phase 1 creates the first native Gutenberg block in the sitchco-core codebase. All existing blocks (icon, modal) use ACF mode with PHP render callbacks and Twig templates. The video block breaks this pattern by using a React `edit` component registered via `block.json`, with dynamic PHP server-side rendering via the `render` field.

The existing build infrastructure (`@sitchco/module-builder` 2.1.5 with `@kucrut/vite-for-wp` 0.12.0 and Vite 7.3.0) already supports JSX files as entry points and externalizes all `@wordpress/*` packages plus `react` and `react/jsx-runtime` to WordPress globals. The project scanner discovers `*.jsx` files in standard asset directories. No build pipeline changes are needed -- only a `.asset.php` sidecar file is required to declare WordPress script dependencies for the editor script.

The UIModal prerequisite (PRE-01/PRE-02) is already satisfied by commit `4fc37f7` which refactored `ModalData` to accept raw strings. `ModalType::VIDEO` already exists in the enum. Phase 1 only needs to verify this works end-to-end for video content; no new PHP code is needed for the modal infrastructure itself.

**Primary recommendation:** Create `modules/VideoBlock/` following the established module pattern, with `blocks/video/block.json` declaring `editorScript: "file:./editor.jsx"` and `render: "file:./render.php"`. The React edit component uses `@wordpress/block-editor`, `@wordpress/components`, and `@wordpress/api-fetch` (all externalized by the build). Create `editor.asset.php` to declare WordPress dependencies. The `save` function returns `<InnerBlocks.Content/>` only.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- UIModal prerequisite (PRE-01, PRE-02) is already implemented via commit 4fc37f7. ModalData constructor accepts raw strings. loadModalContent() convenience method is unnecessary -- code constructs ModalData directly. Mark as satisfied if verification passes.
- First native Gutenberg block in sitchco-core. React edit component registered via block.json (not ACF block).
- Module directory: modules/VideoBlock/ with subdirectories blocks/video/, assets/scripts/, assets/styles/
- block.json registers sitchco/video -- auto-discovered by BlockManifestRegistry
- Dynamic rendering: PHP render_callback via block.json render field. save function returns InnerBlocks.Content (critical for persistence).
- Block attributes: url, provider, videoTitle, displayMode (inline/modal/modal-only), modalId, playIconStyle, playIconX, playIconY, clickBehavior
- Inspector panel is the primary UI surface. URL input, display mode, play icon controls, click behavior all in inspector.
- oEmbed preview via WordPress proxy endpoint /wp-json/oembed/1.0/proxy using @wordpress/api-fetch. No direct client-side requests.
- Auto-population: videoTitle and modalId from oEmbed response, only if user hasn't manually edited them.
- Conditional inspector UI: Inline hides modal fields; Modal/Modal Only shows title + modal ID; Modal Only hides InnerBlocks area.
- No allowedBlocks restriction on InnerBlocks.
- YouTube branded play icons are a legal requirement (YouTube API ToS).
- Editor must NOT load iframe/embed preview -- only oEmbed thumbnail.

### Claude's Discretion
- Editor component internal state management approach
- Exact inspector panel layout and control ordering
- Loading/error states for oEmbed preview fetching
- How to handle invalid URLs (non-video URLs, malformed URLs)
- Play icon SVG implementation details (inline vs asset)
- How to detect "user-edited" state for auto-population fields

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PRE-01 | UIModal supports content-based modals | Already implemented (commit 4fc37f7). ModalData constructor accepts raw strings. Verify only. |
| PRE-02 | Content-based modals render identical dialog structure | ModalData + UIModal::loadModal() + unloadModals() pipeline unchanged. Verify only. |
| BLK-01 | Native Gutenberg block via block.json with React edit | block.json with editorScript: "file:./editor.jsx". Build system supports JSX. .asset.php sidecar for deps. |
| BLK-02 | Block inserts empty -- no InnerBlocks, no play icon until URL | React edit component renders placeholder when url attribute is empty. InnerBlocks starts empty by default. |
| BLK-03 | Dynamic rendering with save returning InnerBlocks.Content | block.json render: "file:./render.php". save() returns only `<InnerBlocks.Content/>`. |
| AUTH-01 | URL input in inspector panel | TextControl or URLInput in InspectorControls. Stores to url attribute. |
| AUTH-02 | Provider auto-detected from URL | Client-side regex matching in edit component. Sets provider attribute. |
| AUTH-03 | oEmbed via WordPress proxy endpoint | @wordpress/api-fetch GET to /oembed/1.0/proxy?url=. Returns thumbnail_url and title. |
| AUTH-04 | oEmbed thumbnail with play icon in editor | Render img + SVG overlay in edit component. Position via CSS. |
| AUTH-05 | videoTitle auto-populates from oEmbed (editable, not overwritten) | Track user-edited state. Only auto-populate if user hasn't edited. |
| AUTH-06 | Display mode selector: Inline, Modal, Modal Only | SelectControl in InspectorControls. Stores to displayMode attribute. |
| AUTH-07 | Modal modes show title + modal ID fields; modal ID auto-slugified | Conditional InspectorControls. TextControl for both. Slugify via JS. |
| AUTH-08 | Inline hides modal options; Modal Only hides InnerBlocks | Conditional rendering in edit component based on displayMode. |
| AUTH-09 | Play icon style configuration | SelectControl with provider-conditional options. YouTube: dark/light/red. Others: dark/light. |
| AUTH-10 | Play icon X/Y position sliders | RangeControl x2 in InspectorControls. Default 50/50. |
| AUTH-11 | Click behavior toggle (poster vs icon) | ToggleControl or SelectControl. Stores to clickBehavior attribute. |
| NOOP-01 | Block with no URL renders InnerBlocks without play behavior | Edit component renders InnerBlocks area + placeholder. render.php outputs InnerBlocks content only when no URL. |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `@wordpress/blocks` | WP-bundled | `registerBlockType()` | Standard WordPress block registration API |
| `@wordpress/block-editor` | WP-bundled | `InspectorControls`, `InnerBlocks`, `useBlockProps` | Standard editor UI primitives for blocks |
| `@wordpress/components` | WP-bundled | `TextControl`, `SelectControl`, `RangeControl`, `ToggleControl`, `PanelBody`, `PanelRow`, `Placeholder`, `Spinner` | Standard WordPress UI component library |
| `@wordpress/api-fetch` | WP-bundled | oEmbed proxy requests | Standard WordPress REST API client |
| `@wordpress/i18n` | WP-bundled | `__()` and `_x()` for translatable strings | Standard WordPress i18n |
| `@wordpress/element` | WP-bundled | `useState`, `useEffect`, `useCallback`, `useRef` | WordPress wrapper around React |
| `@wordpress/url` | WP-bundled | `addQueryArgs` for building oEmbed proxy URL | Standard WordPress URL utility |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `@wordpress/data` | WP-bundled | Block editor store access | Only if needing editor-level state beyond block attributes |
| `@wordpress/compose` | WP-bundled | `usePrevious`, `useDebounce` | Debouncing oEmbed requests on URL change |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `@wordpress/element` | Direct `react` import | Both work (react externalized to React global). @wordpress/element is the WordPress convention. |
| `@wordpress/api-fetch` | `fetch()` + nonce | api-fetch handles nonce, base URL, and middleware automatically |

**Installation:** No npm install needed. All `@wordpress/*` packages are provided by WordPress and externalized by the build system via `@kucrut/vite-for-wp`. They are imported in source but resolved to `wp.*` globals at runtime.

## Architecture Patterns

### Recommended Project Structure
```
modules/VideoBlock/
  VideoBlock.php              # Module class (extends Module)
  blocks/
    video/
      block.json              # Block registration metadata
      editor.jsx              # React edit component (entry point)
      editor.asset.php        # WordPress dependency declaration
      render.php              # Server-side render template
      style.css               # Block styles (editor + frontend)
      editor.css              # Editor-only styles (optional)
  assets/
    images/
      svg-sprite/             # Play icon SVGs (if using sprite approach)
```

### Pattern 1: Native Block with React Edit + PHP Render
**What:** Block registered via `block.json` with `editorScript` for editor UI and `render` for server-side output. The `save` function returns only `<InnerBlocks.Content/>`.
**When to use:** When block needs complex editor interaction (React) but server-rendered output (PHP).
**Example:**

```json
// block.json
{
  "apiVersion": 3,
  "name": "sitchco/video",
  "title": "Video",
  "category": "sitchco",
  "icon": "video-alt3",
  "description": "Privacy-respecting video embed with poster, play icon, and optional modal display.",
  "keywords": ["video", "youtube", "vimeo", "embed"],
  "attributes": {
    "url": { "type": "string", "default": "" },
    "provider": { "type": "string", "default": "" },
    "videoTitle": { "type": "string", "default": "" },
    "displayMode": { "type": "string", "default": "inline" },
    "modalId": { "type": "string", "default": "" },
    "playIconStyle": { "type": "string", "default": "dark" },
    "playIconX": { "type": "number", "default": 50 },
    "playIconY": { "type": "number", "default": 50 },
    "clickBehavior": { "type": "string", "default": "poster" }
  },
  "supports": {
    "html": false,
    "align": ["wide", "full"]
  },
  "editorScript": "file:./editor.jsx",
  "render": "file:./render.php",
  "style": "file:./style.css"
}
```

```php
// editor.asset.php -- declares WordPress script dependencies
<?php
return [
    'dependencies' => [
        'react',
        'wp-blocks',
        'wp-element',
        'wp-block-editor',
        'wp-components',
        'wp-i18n',
        'wp-api-fetch',
        'wp-url',
    ],
    'version' => '1.0.0',
];
```

```jsx
// editor.jsx (simplified structure)
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps();
    // ... component logic
    return (
        <div { ...blockProps }>
            <InspectorControls>
                <PanelBody title={ __('Video Settings', 'sitchco') }>
                    {/* controls */}
                </PanelBody>
            </InspectorControls>
            {/* block preview */}
            <InnerBlocks />
        </div>
    );
}

function Save() {
    return <InnerBlocks.Content />;
}

registerBlockType(metadata.name, {
    edit: Edit,
    save: Save,
});
```

```php
// render.php -- receives $attributes, $content, $block
<?php
/**
 * @var array    $attributes Block attributes
 * @var string   $content    InnerBlocks content (serialized HTML)
 * @var WP_Block $block      Block instance
 */
// Phase 1: minimal render -- just output InnerBlocks content
// Full render logic (poster, play icon, modal) comes in Phase 2+
echo $content;
```

### Pattern 2: Module Registration
**What:** PHP module class registers the block module in `sitchco.config.php`. Module `init()` handles any server-side setup.
**When to use:** All modules in the framework follow this pattern.
**Example:**

```php
// modules/VideoBlock/VideoBlock.php
<?php
namespace Sitchco\Modules\VideoBlock;

use Sitchco\Framework\Module;
use Sitchco\Modules\UIModal\UIModal;

class VideoBlock extends Module
{
    public const DEPENDENCIES = [UIModal::class];
    public const HOOK_SUFFIX = 'video-block';

    public function init(): void
    {
        // Block is auto-registered by BlockManifestRegistry
        // via sitchco.blocks.json discovery.
        // Module init() is for any server-side setup needed.
    }
}
```

### Pattern 3: oEmbed Proxy Fetch in Editor
**What:** Use `@wordpress/api-fetch` to fetch oEmbed data from the WordPress proxy endpoint. Debounce requests. Cache results.
**When to use:** Any time the editor needs oEmbed metadata (thumbnail, title).
**Example:**

```jsx
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

async function fetchOEmbed(url) {
    return apiFetch({
        path: addQueryArgs('/oembed/1.0/proxy', { url }),
    });
}
// Response: { title, thumbnail_url, provider_name, html, ... }
```

### Pattern 4: User-Edited State Tracking for Auto-Population
**What:** Track whether the user has manually edited auto-populated fields (videoTitle, modalId). Only auto-populate from oEmbed if the field hasn't been manually edited.
**When to use:** Any field that auto-populates but should respect manual edits.
**Recommended approach:** Use additional boolean attributes (`_videoTitleEdited`, `_modalIdEdited`) with underscore prefix convention to indicate internal tracking state. These are persisted in the block serialization but hidden from the inspector UI.

```json
// Additional attributes in block.json
"_videoTitleEdited": { "type": "boolean", "default": false },
"_modalIdEdited": { "type": "boolean", "default": false }
```

**Alternative approach (Claude's discretion):** Use a `useRef` to track whether the field was changed by user interaction vs. programmatic update within the current session. The boolean attribute approach is more robust because it persists across editor sessions.

### Pattern 5: Provider Detection
**What:** Client-side regex to detect YouTube or Vimeo from a URL string.
**When to use:** Immediately on URL change, before the oEmbed request.

```jsx
function detectProvider(url) {
    if (!url) return '';
    if (/(?:youtube\.com|youtu\.be)\//i.test(url)) return 'youtube';
    if (/vimeo\.com\//i.test(url)) return 'vimeo';
    return '';
}
```

### Anti-Patterns to Avoid
- **Never use `import React from 'react'` with `React.createElement` directly.** Use JSX syntax. The build system handles the transform. Import from `@wordpress/element` for hooks (useState, useEffect, etc.).
- **Never embed an iframe in the editor preview.** The spec explicitly requires oEmbed thumbnail only. An iframe would violate privacy principles and cause performance issues.
- **Never put configuration controls in the block toolbar.** Inspector panel is the decided UI surface. Toolbar can have alignment and basic controls, but all settings go in inspector.
- **Never use `wp_oembed_get()` client-side.** That's a PHP function. The editor uses the REST proxy endpoint.
- **Never modify InnerBlocks content from the save function.** `save()` must return exactly `<InnerBlocks.Content/>` -- any deviation causes block validation errors.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Block registration | Custom `registerBlockType` wrapper | `block.json` + `registerBlockType(metadata.name, ...)` | WordPress auto-discovers block.json. BlockManifestRegistry handles the rest. |
| Block inspector panels | Custom sidebar UI | `InspectorControls` + `PanelBody` + standard controls | Matches WordPress editor UX. Accessible. Theme-consistent. |
| oEmbed fetching | Custom fetch with nonce handling | `@wordpress/api-fetch` with `/oembed/1.0/proxy` | Handles nonce, base URL, error states, and caching automatically. |
| URL validation | Custom regex for full URL validation | Simple provider regex + let oEmbed proxy validate | The proxy returns errors for unsupported URLs. Don't over-validate client-side. |
| Slug generation | Custom slugify function | `wp.url.cleanForSlug()` or simple JS regex | WordPress has built-in slugification that handles edge cases. |
| Block asset externalization | Manual webpack externals | `@kucrut/vite-for-wp` wp_scripts() plugin | Already configured in the build. All @wordpress/* packages externalized. |
| InnerBlocks persistence | Custom content serialization | `<InnerBlocks.Content/>` in save | WordPress handles InnerBlocks serialization/deserialization automatically. |
| Script dependency declaration | wp_register_script with manual deps | `.asset.php` sidecar file | ModuleAssets.blockTypeMetadata() reads this file automatically. |

**Key insight:** The WordPress block editor provides almost everything needed via `@wordpress/block-editor` and `@wordpress/components`. The main custom work is the oEmbed thumbnail preview with play icon overlay and the conditional inspector logic.

## Common Pitfalls

### Pitfall 1: InnerBlocks Lost on Save
**What goes wrong:** Block content disappears after saving and reloading. InnerBlocks content is not preserved.
**Why it happens:** The `save` function returns something other than `<InnerBlocks.Content/>`, or returns `null`. WordPress stores InnerBlocks as serialized HTML in the post content. If `save()` doesn't output `<InnerBlocks.Content/>`, the content is stripped.
**How to avoid:** The `save` function must return EXACTLY `<InnerBlocks.Content/>` wrapped in a container div with `useBlockProps.save()`. Nothing else.
**Warning signs:** Block shows "This block contains unexpected or invalid content" after reloading the editor.

### Pitfall 2: Block Validation Errors on Attribute Change
**What goes wrong:** After changing the attribute schema, existing blocks show validation errors in the editor.
**Why it happens:** WordPress compares the saved HTML against what `save()` produces with current attributes. If the schema changes, the comparison fails.
**How to avoid:** Freeze the attribute schema before any production content is created. If changes are needed later, use block deprecations.
**Warning signs:** "This block contains unexpected or invalid content" immediately after updating the plugin.

### Pitfall 3: oEmbed Requests Fire on Every Keystroke
**What goes wrong:** Editor sends dozens of API requests while user types a URL, causing rate limiting and poor UX.
**Why it happens:** The URL input onChange fires on every character, triggering an oEmbed fetch.
**How to avoid:** Debounce the oEmbed fetch (300-500ms). Only fetch when the URL looks complete (has a valid-looking domain at minimum). Use `@wordpress/compose`'s `useDebounce` or a manual debounce.
**Warning signs:** Network tab shows rapid sequential requests to `/oembed/1.0/proxy`.

### Pitfall 4: Missing .asset.php Causes Script to Load Without Dependencies
**What goes wrong:** The editor script loads before WordPress block packages are available, causing `wp.blocks is undefined` or similar errors.
**Why it happens:** Without an `.asset.php` sidecar file, `ModuleAssets::blockTypeMetadata()` registers the script with empty dependencies. WordPress doesn't know to load `wp-blocks`, `wp-element`, etc. first.
**How to avoid:** Create `editor.asset.php` in the same directory as `editor.jsx`, declaring all WordPress script dependencies.
**Warning signs:** Console errors about undefined WordPress globals. Editor crashes or block doesn't appear.

### Pitfall 5: Editor Script Runs on Frontend
**What goes wrong:** The React edit component JavaScript loads on the frontend, adding unnecessary weight and potential errors.
**Why it happens:** Using `script` instead of `editorScript` in block.json, or using `viewScript` when not needed.
**How to avoid:** Use `editorScript` for the React edit component. Do NOT add a `viewScript` in Phase 1 (frontend interactivity is Phase 2+). Use `style` for CSS that applies to both editor and frontend.
**Warning signs:** Editor-related JavaScript appears in frontend page source.

### Pitfall 6: Auto-Population Overwrites Manual Edits
**What goes wrong:** User manually edits the video title, then re-saves. The oEmbed title overwrites their edit.
**Why it happens:** The component auto-populates fields on every URL change or re-render without checking if the user has manually edited the field.
**How to avoid:** Track user-edited state with boolean attributes. Only auto-populate when the edited flag is false. Set the flag when the user types in the field.
**Warning signs:** User reports that their custom title keeps reverting.

### Pitfall 7: JSX Build Fails or Produces Broken Output
**What goes wrong:** Build errors on `.jsx` files, or the built JS doesn't work in the editor.
**Why it happens:** Missing understanding of how the build system handles JSX. The `@sitchco/module-builder` uses esbuild via Vite, which defaults to classic JSX transform (React.createElement). The `@kucrut/vite-for-wp` wp_scripts plugin externalizes `react` to the `React` global.
**How to avoid:** Use `.jsx` file extension (required -- `.js` files don't get JSX transform). Import React explicitly: `import React from 'react'` (even though it maps to a global, the import is needed for esbuild to recognize the JSX factory). OR import `createElement` from `@wordpress/element`.
**Warning signs:** Build errors mentioning "unexpected token <". Runtime errors about React not being defined.

### Pitfall 8: block.json render Field Path Resolution
**What goes wrong:** The `render.php` file isn't found or doesn't execute.
**Why it happens:** The `render` field in block.json uses `file:./render.php` syntax, but WordPress resolves this relative to the block.json directory. If the path is wrong, WordPress silently falls back to the save output.
**How to avoid:** Put `render.php` in the same directory as `block.json` and use `"render": "file:./render.php"`.
**Warning signs:** Block output on frontend shows raw InnerBlocks HTML instead of the expected render.

## Code Examples

Verified patterns from codebase investigation:

### Existing Module Registration Pattern
```php
// Source: sitchco.config.php
// Add to the modules array:
use Sitchco\Modules\VideoBlock\VideoBlock;

// In the return array, add after UIModal::class:
VideoBlock::class,
```

### Existing Block Discovery Pattern
```json
// Source: sitchco.blocks.json (auto-generated)
// After adding modules/VideoBlock/blocks/video/block.json,
// BlockManifestRegistry auto-discovers and adds:
{
    "sitchco/video": "modules/VideoBlock/blocks/video"
}
```

### Existing ModalData Usage for Video
```php
// Source: modules/UIModal/ModalData.php
// Video block render.php can create ModalData directly:
use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\ModalType;
use Sitchco\Modules\UIModal\UIModal;

$modal = new ModalData(
    $attributes['modalId'],
    $attributes['videoTitle'],
    '<div class="sitchco-video-player"><!-- player content --></div>',
    ModalType::VIDEO
);
$container->get(UIModal::class)->loadModal($modal);
```

### Block Asset Resolution Flow
```
block.json: "editorScript": "file:./editor.jsx"
     |
     v
BlockManifestRegistry discovers block in modules/VideoBlock/blocks/video/
     |
     v
BlockRegistrationModuleExtension calls register_block_type($fullPath)
     |
     v
WordPress reads block.json, calls block_type_metadata filter
     |
     v
ModuleAssets::blockTypeMetadata() resolves "file:./editor.jsx"
  -> blockAssetPath() -> modules/VideoBlock/blocks/video/editor.jsx
  -> Checks for editor.asset.php sidecar -> reads dependencies
  -> buildAssetPath() -> looks up in .vite/manifest.json
  -> registers script with wp_register_script(handle, url, deps, version)
```

### oEmbed Proxy Response Structure
```json
// GET /wp-json/oembed/1.0/proxy?url=https://www.youtube.com/watch?v=dQw4w9WgXcQ
{
    "version": "1.0",
    "provider_name": "YouTube",
    "provider_url": "https://www.youtube.com/",
    "title": "Rick Astley - Never Gonna Give You Up (Official Music Video)",
    "type": "video",
    "thumbnail_url": "https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg",
    "thumbnail_width": 480,
    "thumbnail_height": 360,
    "width": 200,
    "height": 150,
    "html": "<iframe ...></iframe>"
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `render_callback` in PHP `register_block_type()` | `"render": "file:./render.php"` in block.json | WordPress 6.1 | Cleaner separation. render.php receives $attributes, $content, $block. |
| Classic JSX (`React.createElement`) | Automatic JSX runtime (`react/jsx-runtime`) | React 17 / WP 6.6 | No need to import React in every file. WordPress provides ReactJSXRuntime global. |
| ACF mode blocks | Native blocks with React edit | Always available | ACF mode is simpler but less flexible. Native blocks required for complex editor UI. |
| `wp_register_block_type` in PHP | `register_block_type` with block.json directory | WordPress 5.8+ | block.json is the canonical source of truth for block metadata. |

**Deprecated/outdated:**
- **ACF block renderCallback for complex editor UI:** Still works but inappropriate for blocks needing React interactivity (drag controls, live previews, conditional UI). The video block correctly uses native registration.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit via WPTest\Test\TestCase |
| Config file | `public/phpunit.xml` |
| Quick run command | `ddev test-phpunit` (from sitchco-core/) |
| Full suite command | `ddev test-phpunit` (from sitchco-core/) |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PRE-01 | ModalData accepts raw strings | unit | `ddev test-phpunit --filter ModalDataTest` | Partially (ModalDataTest exists, needs video-specific test) |
| PRE-02 | Content-based modals render identical dialog | integration | `ddev test-phpunit --filter UIModalTest` | Wave 0 |
| BLK-01 | Block registered via block.json | unit | `ddev test-phpunit --filter VideoBlockTest` | Wave 0 |
| BLK-02 | Block inserts empty | manual-only | Manual editor test | N/A -- editor UI behavior |
| BLK-03 | Dynamic render with InnerBlocks.Content save | unit | `ddev test-phpunit --filter VideoBlockTest` | Wave 0 |
| AUTH-01 through AUTH-11 | Editor authoring features | manual-only | Manual editor test | N/A -- React component behavior |
| NOOP-01 | No-URL block renders InnerBlocks only | unit | `ddev test-phpunit --filter VideoBlockTest` | Wave 0 |

### Sampling Rate
- **Per task commit:** `ddev test-phpunit` (from sitchco-core cwd)
- **Per wave merge:** `ddev test-phpunit` (full suite)
- **Phase gate:** Full suite green before /gsd:verify-work

### Wave 0 Gaps
- [ ] `tests/Modules/VideoBlock/VideoBlockTest.php` -- covers BLK-01, BLK-03, NOOP-01: block registration, render output
- [ ] Extend `tests/Modules/UIModal/ModalDataTest.php` -- covers PRE-01: verify VIDEO type with raw strings works
- [ ] No JS test infrastructure exists for editor components -- AUTH-01 through AUTH-11 are manual-only verification (editor UI behavior is not unit-testable without Jest/Playwright setup, which is out of scope for this phase)

## Open Questions

1. **JSX Transform Mode: Classic vs Automatic**
   - What we know: esbuild defaults to classic transform (React.createElement). The build system externalizes both `react` (React global) and `react/jsx-runtime` (ReactJSXRuntime global). WordPress 6.9 provides both globals.
   - What's unclear: Whether to explicitly configure automatic mode or use classic with React import. Both work.
   - Recommendation: Use classic mode (no build config change needed). Add `import React from 'react'` at the top of JSX files. This is the zero-config approach -- no changes to @sitchco/module-builder required.

2. **Play Icon SVG Strategy**
   - What we know: YouTube requires branded play buttons per API ToS. Generic icons needed for non-YouTube. SvgSprite module exists but uses a build-time sprite system.
   - What's unclear: Whether to use inline SVGs in the React component, import SVG files, or leverage the existing SvgSprite module.
   - Recommendation: Inline SVGs in the React component for the editor preview. This avoids dependencies on the SvgSprite build pipeline and keeps editor assets self-contained. The same SVGs can be output from render.php for frontend (Phase 2).

3. **oEmbed Response Caching**
   - What we know: WordPress caches oEmbed responses when URLs appear in post content (via WP_Embed). The REST proxy endpoint also caches results server-side.
   - What's unclear: Whether the proxy endpoint cache is sufficient or if client-side caching in the React component is also needed.
   - Recommendation: Rely on WordPress server-side caching via the proxy endpoint. Add a simple in-component Map cache (URL -> response) to avoid duplicate requests within a single editor session. No need for localStorage or IndexedDB.

## Sources

### Primary (HIGH confidence)
- Codebase: `src/Framework/ModuleAssets.php` -- blockTypeMetadata() and .asset.php handling
- Codebase: `src/Framework/BlockManifestRegistry.php` + `BlockManifestGenerator.php` -- block discovery
- Codebase: `src/ModuleExtension/BlockRegistrationModuleExtension.php` -- register_block_type flow
- Codebase: `@kucrut/vite-for-wp` 0.12.0 `wp-globals.js` -- confirms react, react/jsx-runtime, @wordpress/* externalization
- Codebase: `@sitchco/project-scanner` 2.1.2 -- confirms `.jsx` in ENTRY_FILE_PATTERN
- Codebase: `@sitchco/module-builder` 2.1.5 `config.js` -- Vite config generation, no custom JSX config
- Codebase: `modules/UIModal/ModalData.php` -- raw string constructor confirmed
- Codebase: `modules/UIModal/ModalType.php` -- VIDEO enum value confirmed

### Secondary (MEDIUM confidence)
- [WordPress Block Metadata Reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/) -- render field, editorScript, attributes
- [WordPress oEmbed Proxy Controller](https://developer.wordpress.org/reference/classes/wp_oembed_controller/) -- proxy endpoint behavior
- [JSX in WordPress 6.6 announcement](https://make.wordpress.org/core/2024/06/06/jsx-in-wordpress-6-6/) -- ReactJSXRuntime global availability
- [vite-for-wp GitHub](https://github.com/kucrut/vite-for-wp) -- Vite integration for WordPress

### Tertiary (LOW confidence)
- esbuild default JSX mode (classic/transform) -- verified through multiple sources but esbuild docs were truncated. Confirmed classic is default through GitHub issues.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all libraries verified in codebase and WordPress core
- Architecture: HIGH -- patterns derived from existing codebase modules (UIModal, SvgSprite)
- Pitfalls: HIGH -- identified from codebase analysis (asset.php pattern, InnerBlocks persistence, build system behavior)
- Build system: HIGH -- directly read @sitchco/module-builder, @kucrut/vite-for-wp, and @sitchco/project-scanner source code

**Research date:** 2026-03-09
**Valid until:** 2026-04-09 (stable -- WordPress block API is mature, build tools are pinned versions)
