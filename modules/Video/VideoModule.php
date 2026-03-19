<?php

namespace Sitchco\Modules\Video;

use Sitchco\Framework\Module;
use Sitchco\Modules\UIModal\UIModal;

class VideoModule extends Module
{
    public const DEPENDENCIES = [UIModal::class];

    const HOOK_SUFFIX = 'video';

    public function __construct(private readonly UIModal $uiModal, private readonly VideoBlockRenderer $renderer) {}

    public function init(): void
    {
        $this->uiModal->registerType('video');
    }

    public function blockRegistrationArgs(): array
    {
        return [
            'sitchco/video' => [
                'render_callback' => [$this->renderer, 'render'],
            ],
        ];
    }
}
