<?php

namespace Sitchco\Modules\SvgSprite;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;
use Sitchco\Support\FilePath;
use Sitchco\Utils\Block;
use Sitchco\Utils\Str;

class SvgSprite extends Module
{
    public const HOOK_SUFFIX = 'svg-sprite';

    /**
     * Filename prefix that marks a sprite symbol as an icon rather than a plain shape.
     */
    public const ICON_PREFIX = 'icon-';

    protected array $iconList;

    public function __construct(protected ConfigRegistry $configRegistry) {}

    /**
     * Reduce a list of sprite symbol names to the icons among them, with the prefix stripped.
     *
     * A module's svg-sprite directory is not an icon directory. Everything in it goes into the
     * sprite, and this prefix is what separates the icons from shapes that are only ever
     * referenced directly with `<use href="#{id}">`. Only prefixed symbols can render through
     * renderIcon(), which asks the sprite for `#icon-{name}` and resolves the source file by
     * globbing `icon-{name}.svg` — so an unprefixed name in this list is an icon picker choice
     * that points at nothing. Leaving it out is what lets a module ship a shape without
     * polluting the picker: don't prefix it.
     *
     * Mirrors iconNames() in @sitchco/module-builder's svgstore-sprite plugin, which applies
     * the same rule when it writes the sprite-icons.json that production builds read.
     */
    public static function iconNames(array $symbolNames): array
    {
        $icons = array_filter($symbolNames, fn(string $name) => str_starts_with($name, static::ICON_PREFIX));
        return array_values(array_map(fn(string $name) => substr($name, strlen(static::ICON_PREFIX)), $icons));
    }

    public function init(): void
    {
        $configPaths = array_map([FilePath::class, 'create'], $this->configRegistry->getBasePaths());
        $this->buildSpriteContents($configPaths);
        add_filter('acf/prepare_field/key=field_68f8fa1208258', [$this, 'iconNameFieldChoices']);
        add_filter('timber/twig/functions', function ($functions) {
            $functions['icon'] = [
                'callable' => [$this, 'renderIcon'],
                'is_safe' => ['html'],
            ];
            return $functions;
        });
    }

    public function iconNameFieldChoices($field)
    {
        $iconList = apply_filters(static::hookName('icon-list'), []);
        $field['choices'] = collect($iconList)
            ->flatMap(fn($icons) => $icons['icons'])
            ->sort()
            ->mapWithKeys(fn($name) => [$name => ucfirst(str_replace('-', ' ', $name))])
            ->all();
        return $field;
    }

    protected function addIcons(array $icons, FilePath $configPath): void
    {
        add_filter(static::hookName('icon-list'), function ($iconList) use ($icons, $configPath) {
            sort($icons);
            $iconList[] = compact('icons', 'configPath');
            return $iconList;
        });
    }

    protected function getSpritePath(FilePath $configPath): FilePath
    {
        return $configPath->append('dist/assets/images/sprite.svg');
    }

    /**
     * @param ?FilePath $configPath
     * @param string $filename
     * @return array
     */
    protected function findSvgPaths(?FilePath $configPath, string $filename = '*'): array
    {
        return $configPath?->glob("modules/*/assets/images/svg-sprite/$filename.svg") ?? [];
    }

    /**
     * @var FilePath[] $configPaths
     */
    protected function buildSpriteContents(array $configPaths): void
    {
        foreach ($configPaths as $path) {
            // For dev server, glob list of svg sprite icon filenames
            if ($this->assets()->isDevServer) {
                $matches = $this->findSvgPaths($path);
                $icons = static::iconNames(array_map(fn(FilePath $match) => $match->name(), $matches));
                $this->addIcons($icons, $path);
                $sprite = $this->getSpritePath($path);
                if ($sprite->exists()) {
                    $contents = file_get_contents($sprite->value());
                    add_action('wp_body_open', fn() => print $contents, 20);
                }
                continue;
            }
            // For production build, read generated icon list and output sprite in body.
            // The build applies the icon-prefix rule when it writes that list, so it arrives
            // filtered and stripped — see iconNames() above.
            $sprite = $this->getSpritePath($path);
            $spriteIcons = $path->append('dist/assets/images/sprite-icons.json');
            if (!($sprite->exists() && $spriteIcons->exists())) {
                continue;
            }
            $contents = file_get_contents($sprite->value());
            $icons = json_decode(file_get_contents($spriteIcons->value()));
            $this->addIcons($icons, $path);
            add_action('wp_body_open', fn() => print $contents, 20);
        }
    }

    /**
     * @param string $name - Icon key / filename
     * @param Rotation|null $rotation - Icon rotation
     * @param array $cssClasses - Additional css classes on icon wrapper
     * @param array $style - Additional style properties on icon wrapper
     * @return string
     */
    public function renderIcon(string $name, ?Rotation $rotation, array $cssClasses = [], array $style = []): string
    {
        $transform = $rotation && $rotation !== Rotation::NONE ? "rotate({$rotation->value}deg)" : null;
        $svg = $this->renderIconSvg($name);
        $classes = array_filter(array_merge(['sitchco-icon', "sitchco-icon--{$name}"], $cssClasses));
        return Str::wrapElement($svg, 'span', [
            'class' => $classes,
            'style' => array_merge(['--sitchco-icon-transform' => $transform], $style),
        ]);
    }

    protected function renderIconSvg(string $name): string
    {
        // The sprite ids the symbol by its source filename, so this doubles as the file's basename.
        $symbolId = static::ICON_PREFIX . $name;
        if (!($this->assets()->isDevServer || Block::isPreview())) {
            return '<svg aria-hidden="true"><use fill="currentColor" href="#' . $symbolId . '"></use></svg>';
        }
        if (!isset($this->iconList)) {
            $this->iconList = apply_filters(static::hookName('icon-list'), []);
        }
        /* @var FilePath $configPath */
        $foundIconList = collect($this->iconList)->where(fn($iconList) => in_array($name, $iconList['icons']))->first();
        $configPath = $foundIconList['configPath'] ?? null;

        /* @var ?FilePath $svgFile */
        $svgFile = collect($this->findSvgPaths($configPath, $symbolId))->last();
        if (!$svgFile?->exists()) {
            return "<!-- SVG Symbol $name not found -->";
        }
        return file_get_contents($svgFile->value());
    }
}
