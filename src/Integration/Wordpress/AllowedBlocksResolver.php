<?php

namespace Sitchco\Integration\Wordpress;

use Sitchco\Framework\Core\ConfigRegistry;
use Sitchco\Framework\Core\Module;
use WP_Block_Editor_Context;
use WP_Block_Type_Registry;

/**
 * Manages allowed block types based on configuration settings with opt-in/out logic
 *
 * Handles block permissions using a tiered approach:
 * 1. Core blocks are excluded by default unless explicitly enabled
 * 2. Custom blocks are included by default unless explicitly disabled
 * 3. Merges with existing allow lists from other sources
 */
class AllowedBlocksResolver extends Module
{
    /**
     * @param ConfigRegistry $configRegistry Used to load block configuration settings
     */
    public function __construct(
        private readonly ConfigRegistry $configRegistry
    ) {
    }

    /**
     * Initialize the block filter hook
     */
    public function init(): void
    {
        add_filter('allowed_block_types_all', [$this, 'filterAllowedBlockTypes'], 999, 2);
    }

    /**
     * Filters allowed block types based on configuration settings
     *
     * @param array|bool $allowedBlocks Existing allowed blocks (true=all, false=none, array=filtered list)
     * @param WP_Block_Editor_Context $blockEditorContext The block editor context
     *
     * @return array|bool Modified allow list that combines configuration with existing rules
     *
     * Logic flow:
     * 1. Load block configuration settings
     * 2. Get all registered block names
     * 3. Calculate allowed blocks based on configuration
     * 4. Merge with existing allow list if present
     */
    public function filterAllowedBlockTypes(
        array|bool $allowedBlocks,
        WP_Block_Editor_Context $blockEditorContext
    ): array|bool {
        $blockSettings = $this->configRegistry->load('blocks');

        if (empty($blockSettings)) {
            return $allowedBlocks;
        }

        $registeredBlocks = $this->getAllRegisteredBlockNames();
        $allowedList = $this->calculateAllowedBlocks($registeredBlocks, $blockSettings);

        return $this->handleExistingAllowList($allowedBlocks, $allowedList);
    }

    /**
     * Gets names of all registered block types
     *
     * @return array List of registered block names in 'namespace/name' format
     */
    private function getAllRegisteredBlockNames(): array
    {
        return array_values(array_map(
            static fn ($block) => $block->name,
            WP_Block_Type_Registry::get_instance()->get_all_registered()
        ));
    }

    /**
     * Determines allowed blocks based on configuration rules
     *
     * @param array $allBlocks All available registered blocks
     * @param array $settings Configuration settings from blocks config
     *
     * @return array Filtered list of allowed blocks
     *
     * Configuration rules:
     * - Core blocks (starting with 'core/') are excluded by default
     *   unless explicitly set to true
     * - Custom blocks are included by default unless explicitly set to false
     */
    private function calculateAllowedBlocks(array $allBlocks, array $settings): array
    {
        return array_values(array_filter($allBlocks, function ($blockName) use ($settings) {
            $isCoreBlock = str_starts_with($blockName, 'core/');
            $configValue = $settings[$blockName] ?? null;

            return $isCoreBlock
                ? ($configValue === true)
                : ($configValue !== false);
        }));
    }

    /**
     * Merges calculated allow list with existing filter results
     *
     * @param array|bool $existing Existing allow list from previous filters
     * @param array $calculated Our calculated allow list
     *
     * @return array|bool Combined allow list that respects both existing and new rules
     *
     * Merge strategy:
     * - If existing is true (allow all), use our calculated list
     * - If existing is array, intersect with our calculated list
     * - Otherwise return existing value unchanged
     */
    private function handleExistingAllowList(array|bool $existing, array $calculated): array|bool
    {
        if ($existing === true) {
            return $calculated;
        }

        if (is_array($existing)) {
            return array_intersect($existing, $calculated);
        }

        return $existing;
    }
}
