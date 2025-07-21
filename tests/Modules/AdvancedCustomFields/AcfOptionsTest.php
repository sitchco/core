<?php

namespace Sitchco\Tests\Modules\AdvancedCustomFields;

use Sitchco\Tests\Support\TestCase;

class AcfOptionsTest extends TestCase
{
    public function test_generates_option_class_from_field_group()
    {
        $field_group = [
            'location' => [
                [
                    'param' => 'post_type',
                    'operator' =>  '==',
                    'value' => 'page'
                ]
            ]
        ];
        $modified = apply_filters('acf/prepare_field_group_for_export', $field_group);
        $this->assertEquals($field_group, $modified);
    }
}
