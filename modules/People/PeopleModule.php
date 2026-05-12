<?php

namespace Sitchco\Modules\People;

use Sitchco\Framework\Module;
use Sitchco\Modules\TimberModule;

class PeopleModule extends Module
{
    const DEPENDENCIES = [TimberModule::class];

    public const HOOK_SUFFIX = 'people';

    public const POST_CLASSES = [PersonPost::class];
}
