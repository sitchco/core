<?php

namespace Sitchco\Tests\Modules\UIModal;

use Sitchco\Modules\UIModal\ModalData;
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

    public function test_isLoaded_returns_false_when_no_modals_loaded(): void
    {
        $this->assertFalse($this->module->isLoaded('nonexistent'));
    }

    public function test_isLoaded_returns_true_after_loadModal(): void
    {
        $modal = new ModalData('test-modal', 'Test', '<p>Content</p>', 'box');
        $this->module->loadModal($modal);
        $this->assertTrue($this->module->isLoaded('test-modal'));
        $this->assertFalse($this->module->isLoaded('other-modal'));
    }

    public function test_default_type_is_registered(): void
    {
        $this->assertTrue($this->module->isRegistered(UIModal::DEFAULT_TYPE));
    }

    public function test_resolveType_returns_registered_type(): void
    {
        $this->assertEquals('box', $this->module->resolveType('box'));
        $this->assertEquals('full', $this->module->resolveType('full'));
    }

    public function test_resolveType_falls_back_to_default_for_unregistered(): void
    {
        $this->assertEquals(UIModal::DEFAULT_TYPE, $this->module->resolveType('nonexistent'));
    }

    public function test_resolveType_falls_back_to_default_for_empty_string(): void
    {
        $this->assertEquals(UIModal::DEFAULT_TYPE, $this->module->resolveType(''));
    }

    public function test_loadModal_resolves_unregistered_type_to_default(): void
    {
        $modal = new ModalData('test-fallback', 'Test', '<p>Content</p>', 'nonexistent');
        $loaded = $this->module->loadModal($modal);
        $this->assertEquals(UIModal::DEFAULT_TYPE, $loaded->type);
    }

    public function test_loadModal_preserves_registered_type(): void
    {
        $modal = new ModalData('test-preserve', 'Test', '<p>Content</p>', 'full');
        $loaded = $this->module->loadModal($modal);
        $this->assertEquals('full', $loaded->type);
    }
}
