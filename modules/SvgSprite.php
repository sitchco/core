<?php

namespace Sitchco\Modules;

use Illuminate\Support\Collection;
use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleRegistry;
use Sitchco\Support\FilePath;

class SvgSprite extends Module
{
    /**
     * @var FilePath[]
     */
    protected readonly array $configPaths;

    public function __construct(protected ConfigRegistry $configRegistry) {}

    public function init(): void
    {
        $this->configPaths = array_map([FilePath::class, 'create'], $this->configRegistry->basePaths);
        add_action('wp_body_open', [$this, 'outputSvgSprites'], 20);
    }

    public function outputSvgSprites(): void
    {
        foreach ($this->configPaths as $path) {
            $sprite = $path->append('dist/assets/images/sprite.svg');
            if (!$sprite->exists()) {
                continue;
            }
            $contents = file_get_contents($sprite->value());
            $contents = str_replace(
                '<svg',
                '<svg width="0" height="0" style="position:absolute" aria-hidden="true"',
                $contents
            );
            echo $contents;
        }
    }
}
