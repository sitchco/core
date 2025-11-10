<?php

namespace Sitchco\Modules\SvgSprite;

enum Rotation: int
{
    case NONE = 0;
    case RIGHT_QUARTER = 90;
    case LEFT_QUARTER = -90;
    case HALF = 180;
}
