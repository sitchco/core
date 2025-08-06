<?php

namespace Sitchco\Modules\Wordpress;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class BlockConfig extends Module
{
    const FEATURES = ['enableBlockFilter', 'postTypeBlockVisibility', 'registerBlockCategory'];

    /**
     * @param ConfigRegistry $configRegistry Used to load block configuration settings
     */
    public function __construct(private readonly ConfigRegistry $configRegistry) {}

    public function enableBlockFilter(): void
    {
        add_filter('gbm_disabled_blocks', [$this, 'filterDisabledBlocks']);
    }

    public function postTypeBlockVisibility(): void
    {
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $blockSettings = $this->configRegistry->load('disallowedBlocks');
            $customBlocks = array_filter($blockSettings, fn($block) => is_array($block));
            if (empty($customBlocks)) {
                return;
            }
            $assets->enqueueScript('custom-block-visibility', 'block-visibility.js', [
                'wp-blocks',
                'wp-dom-ready',
                'wp-edit-post',
            ]);
            $assets->inlineScriptData(
               'custom-block-visibility',
                'sitchcoBlockVisibility',
                $customBlocks
            );
        });

    }

    public function registerBlockCategory(): void
    {
        add_filter('block_categories_all', [$this, 'blockCategories']);
    }

    public function filterDisabledBlocks(): array
    {
        $disallowedBlockList = $this->configRegistry->load('disallowedBlocks');

        return array_keys(array_filter($disallowedBlockList, fn($block) => $block === true));
    }

    public function blockCategories($categories)
    {
        $sitchco_category = [
            'slug' => 'sitchco',
            'title' => 'Situation',
        ];

        $new_categories = [];
        $inserted = false;

        foreach ($categories as $category) {
            $new_categories[] = $category;
            if ($category['slug'] === 'text') {
                $new_categories[] = $sitchco_category;
                $inserted = true;
            }
        }

        if (!$inserted) {
            array_unshift($new_categories, $sitchco_category);
        }

        return $new_categories;
    }
}
