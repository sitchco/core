<?php

namespace Sitchco\Modules\SvgSprite;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;
use Sitchco\Support\FilePath;

class SvgSprite extends Module
{
    protected array $iconList;

    public function __construct(protected ConfigRegistry $configRegistry) {}

    public function init(): void
    {
        $configPaths = array_map([FilePath::class, 'create'], $this->configRegistry->basePaths);
        $this->buildSpriteContents($configPaths);
        add_filter('acf/prepare_field/key=field_68f8fa1208258', [$this, 'iconNameFieldChoices']);
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
     * @param FilePath $configPath
     * @param string $filename
     * @return array
     */
    protected function findSvgPaths(FilePath $configPath, string $filename = '*'): array
    {
        return $configPath->glob("modules/*/assets/images/svg-sprite/$filename.svg");
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
                $icons = array_map(fn(FilePath $match) => str_replace('icon-', '', $match->name()), $matches);
                $this->addIcons($icons, $path);
                continue;
            }
            // For production build, read generated icon list and output sprite in body
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

    public function renderIcon(string $name, bool $isEditorPreview = false): string
    {
        if (!isset($this->iconList)) {
            $this->iconList = apply_filters(static::hookName('icon-list'), []);
        }
        /* @var FilePath $configPath */
        $configPath = collect($this->iconList)->where(fn($iconList) => in_array($name, $iconList['icons']))->first()[
            'configPath'
        ];
        if (!($this->assets()->isDevServer || $isEditorPreview)) {
            return '<svg aria-hidden="true"><use fill="currentColor" href="#icon-' . $name . '"></use></svg>';
        }
        /* @var ?FilePath $svgFile */
        $svgFile = collect($this->findSvgPaths($configPath, "icon-$name"))->last();
        if (!$svgFile?->exists()) {
            return "<!-- SVG Symbol $name not found -->";
        }
        return file_get_contents($svgFile->value());
    }
}
