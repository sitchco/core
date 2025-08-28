<?php

namespace Sitchco\Modules;

use Illuminate\Support\Collection;
use Sitchco\Framework\Module;
use Sitchco\Support\FilePath;

class SvgSprite extends Module
{
    const HOOK_SUFFIX = 'svg-sprite';

    /**
     * @var array<string,FilePath>
     */
    private array $locations = [];

    private bool $modified = false;

    public function init(): void
    {
        add_action('init', [$this, 'makeLocationModifiedMarkers']);
        add_action('wp_head', [$this, 'buildSvgSprite'], 20);
    }

    public function makeLocationModifiedMarkers(): void
    {
        $locations = apply_filters(static::hookName('locations'), []);
        $validLocations = array_filter($locations, fn($loc) =>
            is_array($loc) && count($loc) === 2 &&
            $loc[0] instanceof FilePath && $loc[1] instanceof FilePath
        );
        foreach ($validLocations as list($path, $buildPath)) {
            $hashFile = $buildPath->append('.svg-sprite-' . md5($path->value()))->value();
            $this->locations[$hashFile] = $path;
        }
        if (wp_get_environment_type() !== 'local') {
            return;
        }
        foreach ($this->locations as $hashFile => $path) {
            $files = $path->glob('*.svg');
            $mtimes = [];
            foreach ($files as $file) {
                $mtimes[$file->name()] = filemtime($file->value());
            }
            $hash = md5(json_encode($mtimes));
            $existingHash = file_get_contents($hashFile);
            if ($existingHash !== $hash) {
                $this->modified = true;
                file_put_contents($hashFile, $hash);
            }
        }
    }

    public function buildSvgSprite()
    {
        $targetDir = $this->assets()->productionAssetsPath();
        if (!$targetDir) {
            return;
        }
        $targetFile = $targetDir->append('sprite.svg');
        if (!$targetFile->exists() || $this->modified) {
            $this->generateSprite($targetFile);
        }
        echo file_get_contents($targetFile->value());
    }

    private function buildFilePathCollection(): Collection
    {
        return collect($this->locations)->flatMap(fn(FilePath $loc) => $loc->glob('*.svg'));
    }

    /**
     * Based on https://github.com/vestaware/svg-sprite-generator/blob/main/src/SvgSpriteGenerator.php
     *
     * @param FilePath $filePath
     * @return void
     */
    private function generateSprite(FilePath $filePath): void
    {
        $paths = $this->buildFilePathCollection();
        $spriteSymbols = $paths->map(fn($f) => $this->processSvgFile($f))->implode('');
        $spriteContent = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">' . $spriteSymbols .'</svg>';
        file_put_contents($filePath->value(), $spriteContent);
        $this->modified = false;
        $filePath->reset();
    }

    /**
     * Process an SVG file for optimization and inclusion in the sprite.
     *
     * @param FilePath $filePath Path to the SVG file.
     * @return string Optimized SVG content for sprite.
     * @throws \Exception
     */
    private function processSvgFile(FilePath $filePath): string
    {
        $content = file_get_contents($filePath->value());

        // Remove <g> tags
        $content = preg_replace('/<g[^>]*>|<\/g>/', '', $content);

        // Extract the content inside the <svg> tag
        preg_match('/<svg[^>]*>(.*?)<\/svg>/s', $content, $matches);
        if (!isset($matches[1])) {
            throw new \Exception("Invalid SVG format in file: {$filePath->value()}");
        }
        $contentInsideSvg = $matches[1];

        // Clean the attributes from the <svg> tag
        $attributes = $this->extractAttributes($content);
        $attributes = $this->cleanAttributes($attributes);

        return '<symbol id="' . $filePath->name() . '" ' . $attributes . '>' . $contentInsideSvg . '</symbol>';
    }

    /**
     * Extract attributes from the <svg> tag.
     *
     * @param string $svgContent Full SVG content.
     * @return string Attributes as a string.
     */
    private function extractAttributes(string $svgContent): string
    {
        preg_match('/<svg(.*?)>/s', $svgContent, $matches);

        return isset($matches[1]) ? trim($matches[1]) : '';
    }

    /**
     * Clean unnecessary attributes from the extracted attributes.
     *
     * @param string $attributes Extracted attributes string.
     * @return string Cleaned attributes string.
     */
    private function cleanAttributes(string $attributes): string
    {
        // Remove everything except viewBox and fill
        $allowedAttributes = ['viewBox', 'fill'];

        // Filter attributes
        $attributesArray = [];
        preg_match_all('/(\w+)=(".*?"|\'.*?\')/s', $attributes, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (in_array($match[1], $allowedAttributes)) {
                $attributesArray[] = $match[0];
            }
        }

        // Ensure xmlns is removed
        $attributesArray = array_filter($attributesArray, fn($attr) => stripos($attr, 'xmlns') === false);

        return implode(' ', $attributesArray);
    }

}
