<?php

namespace Sitchco\Modules\VideoBlock;

use Sitchco\Framework\Module;
use Sitchco\Modules\UIModal\UIModal;

class VideoBlock extends Module
{
    public const DEPENDENCIES = [UIModal::class];

    const HOOK_SUFFIX = 'video-block';

    public function init(): void
    {
        // Block is auto-registered by BlockRegistrationModuleExtension
        // via block.json discovery in blocks/video/.
    }
}
