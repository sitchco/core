<?php

namespace Sitchco\Tests\Modules\AdvancedCustomFields;

use Sitchco\Tests\Support\TestCase;

class AcfOptionsTest extends TestCase
{
    const OPTIONS_CLASS_NAME = 'Sitchco\\App\\Options\\TestOptions';

    public function test_generates_option_class_from_field_group()
    {
        update_field('test_field', 'test value', 'options');
        $field_group = [
            'key' => 'group_test',
            'title' => 'Test Options',
            'fields' => [
                ['type' => 'text', 'name' => 'test_field', 'label' => 'Test Field'],
            ],
            'location' => [
                [
                    ['param' => 'post_type', 'operator' =>  '==', 'value' => 'page']
                ]
            ]
        ];
        $modified = apply_filters('acf/prepare_field_group_for_export', $field_group);
        $this->assertEquals($field_group, $modified);
        $field_group['location'][0][0]['param'] = 'options_page';
        $modified = apply_filters('acf/prepare_field_group_for_export', $field_group);
        $this->assertStringEndsWith('src/Options/TestOptions.php', $modified['options_class']);
        $this->assertTrue(file_exists($modified['options_class']));
        $this->assertTrue(class_exists(static::OPTIONS_CLASS_NAME));
        $options_file_contents = file_get_contents($modified['options_class']);
        $this->assertStringContainsString('@property string $test_field Test Field', $options_file_contents);
        $options = $this->container->get(static::OPTIONS_CLASS_NAME);
        $this->assertEquals('test value', $options->test_field);
        unlink($modified['options_class']);
    }
}
