<?php

namespace Sitchco\Modules\TagManager;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\UIFramework\UIFramework;

class TagManager extends Module
{
    public const HOOK_SUFFIX = 'tag-manager';

    public const DEPENDENCIES = [UIFramework::class];

    public function init(): void
    {
        $this->registerAssets(function (ModuleAssets $assets) {
            $assets->registerScript(static::hookName(), 'main.js', [UIFramework::hookName()]);
        });

        // @todo M2: GTM container injection — render head/body snippets when $settings->gtm_container_ids is non-empty
        // @todo M3: Page metadata push — dataLayer init + wp_post_type/wp_post_id/wp_slug in wp_head (priority 0)
        // @todo M4: Interaction tracking — delegated click handler, data-gtm context resolution
        // @todo M5: data-gtm attribute helper — gtm_attr() Twig function, structural labels in parent theme
        // @todo M6: Hook subscribers — modal, form, hash state change
        // @todo M7: UTM persistence + outbound link decoration — driven by $settings->gtm_decorate_outbound and $settings->gtm_outbound_domains
    }
}
