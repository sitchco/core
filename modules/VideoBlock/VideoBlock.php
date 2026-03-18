<?php

namespace Sitchco\Modules\VideoBlock;

use Sitchco\Framework\Module;
use Sitchco\Modules\UIModal\UIModal;

class VideoBlock extends Module
{
    public const DEPENDENCIES = [UIModal::class];

    const HOOK_SUFFIX = 'video';

    public function __construct(private readonly UIModal $uiModal) {}

    public function init(): void
    {
        $this->uiModal->registerType('video');
    }
}
