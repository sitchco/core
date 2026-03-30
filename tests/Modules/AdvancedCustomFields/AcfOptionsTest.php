<?php

namespace Sitchco\Tests\Modules\AdvancedCustomFields;

use Sitchco\Tests\TestCase;

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
                [
                    'type' => 'text',
                    'name' => 'test_field',
                    'label' => 'Test Field',
                    'return_format' => '',
                ],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]],
        ];
        $modified = apply_filters('acf/prepare_field_group_for_export', $field_group);
        $this->assertEquals($field_group, $modified);
        $field_group['location'][0][0]['param'] = 'options_page';
        $modified = apply_filters('acf/prepare_field_group_for_export', $field_group);
        $this->assertEquals(static::OPTIONS_CLASS_NAME, $modified['options_class']);
        $class_file = get_stylesheet_directory() . '/src/Options/TestOptions.php';
        $this->assertTrue(file_exists($class_file));
        $this->assertTrue(class_exists(static::OPTIONS_CLASS_NAME));
        $options_file_contents = file_get_contents($class_file);
        $this->assertStringContainsString('@property string $test_field Test Field', $options_file_contents);
        $options = $this->container->get(static::OPTIONS_CLASS_NAME);
        $this->assertEquals('test value', $options->test_field);
        unlink($class_file);
    }

    public function test_excludes_unnamed_fields_from_phpdoc()
    {
        $field_group = [
            'key' => 'group_test_unnamed',
            'title' => 'Test Unnamed Options',
            'fields' => [
                [
                    'type' => 'tab',
                    'name' => '',
                    'label' => 'General Tab',
                ],
                [
                    'type' => 'text',
                    'name' => 'visible_field',
                    'label' => 'Visible Field',
                    'return_format' => '',
                ],
                [
                    'type' => 'message',
                    'name' => '',
                    'label' => 'Instructions',
                ],
            ],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'test']]],
        ];
        $modified = apply_filters('acf/prepare_field_group_for_export', $field_group);
        $class_file = get_stylesheet_directory() . '/src/Options/TestUnnamedOptions.php';
        $this->assertTrue(file_exists($class_file));
        $options_file_contents = file_get_contents($class_file);
        $this->assertStringContainsString('@property string $visible_field Visible Field', $options_file_contents);
        $this->assertStringNotContainsString('@property mixed $', $options_file_contents);
        $this->assertStringNotContainsString('General Tab', $options_file_contents);
        $this->assertStringNotContainsString('Instructions', $options_file_contents);
        unlink($class_file);
    }
}
