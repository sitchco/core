<?php

namespace Sitchco\Tests\Modules\UIModal;

use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Tests\TestCase;

class UIModalTest extends TestCase
{
    private UIModal $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(UIModal::class);
    }

    public function test_core_types_registered_on_init(): void
    {
        $this->assertTrue($this->module->isRegistered('box'));
        $this->assertTrue($this->module->isRegistered('full'));
    }

    public function test_unregistered_type_returns_false(): void
    {
        $this->assertFalse($this->module->isRegistered('nonexistent'));
    }

    public function test_register_custom_type(): void
    {
        $this->module->registerType('test-slideshow', ['label' => 'Slideshow']);
        $this->assertTrue($this->module->isRegistered('test-slideshow'));
    }

    public function test_type_field_choices_includes_only_labeled_types(): void
    {
        $this->module->registerType('test-internal', []);
        $this->module->registerType('test-visible', ['label' => 'Visible Type']);
        $field = $this->module->typeFieldChoices(['choices' => []]);

        $this->assertArrayHasKey('box', $field['choices']);
        $this->assertArrayHasKey('full', $field['choices']);
        $this->assertArrayHasKey('test-visible', $field['choices']);
        $this->assertArrayNotHasKey('test-internal', $field['choices']);
        $this->assertEquals('Box (default)', $field['choices']['box']);
        $this->assertEquals('Full Screen', $field['choices']['full']);
    }
}
