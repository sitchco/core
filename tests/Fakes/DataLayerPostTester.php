<?php

namespace Sitchco\Tests\Fakes;

use Sitchco\Model\PostBase;

/**
 * class DataLayerPostTester
 *
 * PostBase subclass whose builder mixes present, null, '', and
 * falsy-but-meaningful values, to exercise the final dataLayerContext()
 * filtering contract (S2/N4).
 *
 * @package Sitchco\Tests\Fakes
 */
class DataLayerPostTester extends PostBase
{
    const POST_TYPE = 'dl_tester';

    protected function buildDataLayerContext(): array
    {
        return [
            'kept_string' => 'value',
            'kept_zero' => 0,
            'kept_false' => false,
            'kept_empty_array' => [],
            'dropped_null' => null,
            'dropped_empty_string' => '',
        ];
    }
}
