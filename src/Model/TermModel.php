<?php

namespace Sitchco\Model;

use Sitchco\Framework\Core\Module;
use Sitchco\Integration\Timber;

class TermModel extends Module
{
    public const DEPENDENCIES = [
        Timber::class
    ];
}