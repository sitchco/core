<?php

namespace Sitchco\Modules\CustomTags;

use Sitchco\Model\PostBase;

/**
 * @property string $script_content Script Content
 * @property string $script_placement Script Placement (before_gtm, after_gtm, footer)
 * @property array $script_assignment Assignment group (type: 0=exclude, 1=include; selection: post IDs)
 */
class CustomTag extends PostBase
{
    const POST_TYPE = 'custom_tag';
}
