<?php

namespace Sitchco\Modules\CustomTags;

use Sitchco\Model\PostBase;

/**
 * @property string $script_content Script Content
 * @property string $script_placement Script Placement (before_gtm, after_gtm, footer)
 */
class CustomTag extends PostBase
{
    const POST_TYPE = 'sitchco_script';
}
