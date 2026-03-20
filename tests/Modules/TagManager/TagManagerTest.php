<?php

namespace Sitchco\Tests\Modules\TagManager;

use Sitchco\Modules\TagManager\TagManager;
use Sitchco\Tests\TestCase;

class TagManagerTest extends TestCase
{
    private TagManager $module;

    protected function setUp(): void
    {
        parent::setUp();
        acf_get_store('values')->reset();
        $this->module = $this->container->get(TagManager::class);
        $this->module->init();
    }

    protected function tearDown(): void
    {
        remove_all_filters('acf/load_value/name=gtm_container_ids');
        remove_all_filters('acf/load_value/name=gtm_decorate_outbound');
        remove_all_filters('acf/load_value/name=gtm_outbound_domains');
        remove_all_filters(TagManager::hookName('enable-gtm'));
        remove_all_filters(TagManager::hookName('current-state'));
        remove_all_filters(TagManager::hookName('outbound-domains'));
        parent::tearDown();
    }

    private function setContainerIds(array $ids): void
    {
        add_filter('acf/load_value/name=gtm_container_ids', fn() => $ids, 10, 0);
    }

    private function setQueriedObject($object, int $id = 0): void
    {
        $GLOBALS['wp_query']->queried_object = $object;
        $GLOBALS['wp_query']->queried_object_id = $id;
    }

    private function setOutboundDomains(bool $enabled, array $domains = []): void
    {
        add_filter('acf/load_value/name=gtm_decorate_outbound', fn() => $enabled, 10, 0);
        add_filter('acf/load_value/name=gtm_outbound_domains', fn() => $domains, 10, 0);
    }

    private function captureHook(string $hook): string
    {
        ob_start();
        do_action($hook);
        return ob_get_clean();
    }

    public function test_renders_gtm_snippets_for_configured_containers(): void
    {
        $this->setContainerIds([['container_id' => 'GTM-FIRST'], ['container_id' => 'GTM-SECOND']]);
        $head = $this->captureHook('wp_head');
        $body = $this->captureHook('wp_body_open');
        $this->assertStringContainsString("'GTM-FIRST'", $head);
        $this->assertStringContainsString("'GTM-SECOND'", $head);
        $this->assertStringContainsString('ns.html?id=GTM-FIRST', $body);
        $this->assertStringContainsString('ns.html?id=GTM-SECOND', $body);
    }

    public function test_no_output_when_no_containers_configured(): void
    {
        $this->setContainerIds([]);
        $head = $this->captureHook('wp_head');
        $body = $this->captureHook('wp_body_open');
        $this->assertStringNotContainsString('googletagmanager', $head);
        $this->assertStringNotContainsString('googletagmanager', $body);
    }

    public function test_enable_gtm_filter_suppresses_output(): void
    {
        $this->setContainerIds([['container_id' => 'GTM-TEST123']]);
        add_filter(TagManager::hookName('enable-gtm'), '__return_false');
        $head = $this->captureHook('wp_head');
        $body = $this->captureHook('wp_body_open');
        $this->assertStringNotContainsString('googletagmanager', $head);
        $this->assertStringNotContainsString('googletagmanager', $body);
    }

    public function test_datalayer_init_renders_before_gtm_snippet(): void
    {
        $this->setContainerIds([['container_id' => 'GTM-ORDER']]);
        $post = $this->factory()->post->create_and_get();
        $this->setQueriedObject($post, $post->ID);
        $head = $this->captureHook('wp_head');
        $dlPos = strpos($head, 'window.dataLayer=window.dataLayer||[]');
        $gtmPos = strpos($head, 'googletagmanager');
        $this->assertNotFalse($dlPos);
        $this->assertNotFalse($gtmPos);
        $this->assertLessThan($gtmPos, $dlPos);
    }

    public function test_datalayer_push_contains_post_metadata(): void
    {
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'page',
            'post_name' => 'about-us',
        ]);
        $this->setQueriedObject($post, $post->ID);
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('"wp_post_type":"page"', $head);
        $this->assertStringContainsString('"wp_post_id":' . $post->ID, $head);
        $this->assertStringContainsString('"wp_slug":"about-us"', $head);
    }

    public function test_datalayer_push_has_no_event_key(): void
    {
        $post = $this->factory()->post->create_and_get();
        $this->setQueriedObject($post, $post->ID);
        $head = $this->captureHook('wp_head');
        $this->assertMatchesRegularExpression('/dataLayer\.push\(\{[^}]+\}\)/', $head);
        $this->assertDoesNotMatchRegularExpression('/dataLayer\.push\(\{[^}]*"event"/', $head);
    }

    public function test_datalayer_init_renders_without_container_ids(): void
    {
        $this->setContainerIds([]);
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('window.dataLayer=window.dataLayer||[]', $head);
        $this->assertStringNotContainsString('googletagmanager', $head);
    }

    public function test_datalayer_no_push_on_404(): void
    {
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('window.dataLayer=window.dataLayer||[]', $head);
        $this->assertStringNotContainsString('dataLayer.push', $head);
    }

    public function test_current_state_filter_modifies_metadata(): void
    {
        $post = $this->factory()->post->create_and_get();
        $this->setQueriedObject($post, $post->ID);
        add_filter(TagManager::hookName('current-state'), function (array $data) {
            $data['custom_key'] = 'custom_value';
            return $data;
        });
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('"custom_key":"custom_value"', $head);
    }

    public function test_gtm_attr_renders_string_value(): void
    {
        $result = TagManager::renderGtmAttribute('Header');
        $this->assertSame(' data-gtm="Header"', $result);
    }

    public function test_gtm_attr_renders_array_as_escaped_json(): void
    {
        $result = TagManager::renderGtmAttribute(['label' => 'Donate', 'role' => 'cta']);
        $this->assertStringContainsString('data-gtm="', $result);
        $decoded = html_entity_decode($result);
        $this->assertStringContainsString('{"label":"Donate","role":"cta"}', $decoded);
    }

    public function test_outbound_domains_filter_receives_configured_domains(): void
    {
        $this->setOutboundDomains(true, [['domain' => 'example.com'], ['domain' => 'other.com']]);
        $captured = null;
        add_filter(TagManager::hookName('outbound-domains'), function (array $domains) use (&$captured) {
            $captured = $domains;
            return $domains;
        });
        $this->captureHook('wp_head');
        $this->assertSame(['example.com', 'other.com'], $captured);
    }

    public function test_outbound_domains_filter_not_called_when_toggle_disabled(): void
    {
        $this->setOutboundDomains(false, [['domain' => 'example.com']]);
        $called = false;
        add_filter(TagManager::hookName('outbound-domains'), function (array $domains) use (&$called) {
            $called = true;
            return $domains;
        });
        $this->captureHook('wp_head');
        $this->assertFalse($called);
    }

    public function test_datalayer_push_contains_term_metadata(): void
    {
        $term = $this->factory()->term->create_and_get(['taxonomy' => 'category', 'slug' => 'news']);
        $this->setQueriedObject($term, $term->term_id);
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('"wp_taxonomy":"category"', $head);
        $this->assertStringContainsString('"wp_term_id":' . $term->term_id, $head);
        $this->assertStringContainsString('"wp_slug":"news"', $head);
    }

    public function test_datalayer_push_contains_post_type_metadata(): void
    {
        $postType = get_post_type_object('page');
        $this->setQueriedObject($postType);
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('"wp_post_type":"page"', $head);
        $this->assertStringNotContainsString('"wp_post_id"', $head);
        $this->assertStringContainsString('"wp_slug":"page"', $head);
    }
}
