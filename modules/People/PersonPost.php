<?php

namespace Sitchco\Modules\People;

use Sitchco\Model\PostBase;
use Sitchco\Utils\Str;

class PersonPost extends PostBase
{
    const POST_TYPE = 'person';

    function hasBio(): bool
    {
        return Str::trimHtml($this->post_content) !== '';
    }
}
