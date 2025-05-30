<?php

namespace Sitchco\Modules\CoreBlocks;

use Sitchco\Framework\Module;

class CoreBlockModifier extends Module
{
    const FEATURES = [
        'youtubeNoCookie'
    ];
    public function youtubeNoCookie(): void
    {
        add_filter('embed_oembed_html',fn(string $html) => str_contains($html, 'youtube.com') ? str_replace('youtube.com', 'youtube-nocookie.com', $html) : $html);
    }
}
