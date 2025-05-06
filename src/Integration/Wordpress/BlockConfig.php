<?php

namespace Sitchco\Integration\Wordpress;

use Sitchco\Framework\Core\ConfigRegistry;
use Sitchco\Framework\Core\Module;
use WP_Block_Editor_Context;
use WP_Block_Type_Registry;

class BlockConfig extends Module
{
    /**
     * @param ConfigRegistry $configRegistry Used to load block configuration settings
     */
    public function __construct(
        private readonly ConfigRegistry $configRegistry
    ) {
    }

    public function init(): void
    {
        add_filter('gbm_disabled_blocks', [$this, 'filterDisabledBlocks']);
        add_filter('allowed_block_types_all', [$this, 'configureCustomVisibility'], 10, 2);
    }

    public function filterDisabledBlocks(): array
    {
        $disallowedBlockList = $this->configRegistry->load('disallowedBlocks');

        return array_keys(array_filter($disallowedBlockList, fn($block) => $block === true));
    }

    public function configureCustomVisibility(
        array|bool $allowedBlocks,
        WP_Block_Editor_Context $blockEditorContext
    ): array|bool {
        $postType = $blockEditorContext->post->post_type ?? false;
        if (! $postType) {
            return $allowedBlocks;
        }
        $blockSettings = $this->configRegistry->load('disallowedBlocks');
        $customBlocks = array_filter($blockSettings, fn($block) => is_array($block));

        if (empty($customBlocks)) {
            return $allowedBlocks;
        }

        $registeredBlocks = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());

        $filtered = array_filter(
            $registeredBlocks,
            function (string $blockName) use ($customBlocks, $postType) {
                // no custom rule → leave it in
                if (! isset($customBlocks[$blockName])) {
                    return true;
                }

                $cfg = $customBlocks[$blockName];

                // explicit allow list for this post type
                if (isset($cfg['allowPostType'])) {
                    return (bool)($cfg['allowPostType'][$postType] ?? false);
                }

                // no post-type rule → leave it in
                return true;
            }
        );

        // normalize indexing
        $filtered = array_values($filtered);

        // 2. Merge with whatever was already allowed/denied
        if ($allowedBlocks === true) {
            // “everything allowed” becomes just our filtered set
            return $filtered;
        }

        if (is_array($allowedBlocks)) {
            // intersect: only blocks allowed by both
            return array_values(
                array_intersect($allowedBlocks, $filtered)
            );
        }

        // if $allowedBlocks is false (none allowed), keep it as-is
        return $allowedBlocks;
    }
}
