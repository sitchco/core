<?php

namespace Sitchco\Tests\Modules\TagManager;

use Sitchco\Modules\TagManager\ExtraParamsField;
use Sitchco\Modules\TagManager\TagManager;
use Sitchco\Tests\Fakes\DataLayerPostTester;
use Sitchco\Tests\Fakes\EventPostTester;
use Sitchco\Tests\TestCase;

class TagManagerTest extends TestCase
{
    private TagManager $module;

    protected function setUp(): void
    {
        parent::setUp();
        acf_get_store('values')->reset();
        // Isolate from any outbound-domains filter an active app module may have registered at
        // bootstrap (e.g. the theme's CriterionLinkDecorator); these tests drive config themselves.
        remove_all_filters(TagManager::hookName('outbound-domains'));
        $this->module = $this->container->get(TagManager::class);
        $this->module->init();
        // Register PostBase fakes so the current-state model merge can resolve real classes:
        // dl_tester → mixed-value builder, event → empty default, plain_post → bare Timber\Post.
        // WP_UnitTestCase restores hooks after each test, so no explicit teardown is needed.
        add_filter('timber/post/classmap', function ($classmap) {
            $classmap['dl_tester'] = DataLayerPostTester::class;
            $classmap['event'] = EventPostTester::class;
            $classmap['plain_post'] = \Timber\Post::class;
            return $classmap;
        });
    }

    protected function tearDown(): void
    {
        remove_all_filters('acf/load_value/name=gtm_container_ids');
        remove_all_filters('acf/load_value/name=gtm_decorate_outbound');
        remove_all_filters('acf/load_value/name=gtm_outbound_domains');
        remove_all_filters(TagManager::hookName('enable-gtm'));
        remove_all_filters(TagManager::hookName('current-state'));
        remove_all_filters(TagManager::hookName('outbound-domains'));
        remove_all_filters('acf/validate_value/key=' . ExtraParamsField::FIELD_KEY);
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

    private function captureOutboundParamsInline(): string
    {
        $handle = TagManager::hookName();
        $existing = wp_scripts()->registered[$handle]->extra['before'] ?? [];
        $beforeCount = is_array($existing) ? count($existing) : 0;
        $this->captureHook('wp_head');
        $after = wp_scripts()->registered[$handle]->extra['before'] ?? [];
        $after = is_array($after) ? $after : [];
        return implode("\n", array_slice($after, $beforeCount));
    }

    private function decodeOutboundDecoratorPayload(?string $inline = null): ?array
    {
        $inline ??= $this->captureOutboundParamsInline();
        if (!preg_match('/window\.sitchco\.tagManager\s*=\s*(\{.+?\});/s', $inline, $m)) {
            return null;
        }
        $decoded = json_decode($m[1], true);
        return is_array($decoded) ? $decoded['outboundDecorator'] ?? null : null;
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
            'post_title' => 'About Us',
        ]);
        $this->setQueriedObject($post, $post->ID);
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('"wp_post_type":"page"', $head);
        $this->assertStringContainsString('"wp_post_id":' . $post->ID, $head);
        $this->assertStringContainsString('"wp_slug":"about-us"', $head);
        $this->assertStringContainsString('"wp_title":"About Us"', $head);
    }

    public function test_datalayer_push_has_no_event_key(): void
    {
        $post = $this->factory()->post->create_and_get();
        $this->setQueriedObject($post, $post->ID);
        $head = $this->captureHook('wp_head');
        // Decode the current-state push via the shared helper and confirm it carries no
        // `event` key. assertIsArray also asserts a push was found at all (the helper
        // returns null when the anchored pattern does not match).
        $data = $this->decodeCurrentStatePush($head);
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('event', $data);
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
        // The filter callback receives (and passes through) the base metadata, including wp_title.
        $this->assertStringContainsString('"wp_title":', $head);
    }

    private function decodeCurrentStatePush(string $head): ?array
    {
        if (!preg_match('/window\.dataLayer\.push\((\{.*?\})\);/s', $head, $m)) {
            return null;
        }
        return json_decode($m[1], true);
    }

    public function test_current_state_push_merges_queried_model_context(): void
    {
        // A queried WP_Post resolves to its PostBase model and contributes dataLayerContext()
        // to the push, before the public current-state filter runs.
        $post = $this->factory()->post->create_and_get(['post_type' => 'dl_tester']);
        $this->setQueriedObject($post, $post->ID);
        $data = $this->decodeCurrentStatePush($this->captureHook('wp_head'));

        $this->assertIsArray($data);
        // Globals still present, and base metadata survives the model merge.
        $this->assertSame('dl_tester', $data['wp_post_type']);
        $this->assertSame($post->ID, $data['wp_post_id']);
        $this->assertSame($post->post_name, $data['wp_slug']);
        $this->assertSame($post->post_title, $data['wp_title']);
        // Model context merged; the final dataLayerContext() kept the meaningful values.
        $this->assertSame('value', $data['kept_string']);
        $this->assertSame(0, $data['kept_zero']);
        $this->assertFalse($data['kept_false']);
        $this->assertSame([], $data['kept_empty_array']);
        // null / '' were stripped before the merge.
        $this->assertArrayNotHasKey('dropped_null', $data);
        $this->assertArrayNotHasKey('dropped_empty_string', $data);
    }

    public function test_current_state_filter_runs_after_model_merge(): void
    {
        // dl_tester's model contributes kept_string='value'; the filter overwrites the same key.
        // The merge must run BEFORE the filter — if it ran after, the model value would clobber
        // the filter's and this assertion would fail. Proves S4 merge-before-filter ordering.
        $post = $this->factory()->post->create_and_get(['post_type' => 'dl_tester']);
        $this->setQueriedObject($post, $post->ID);
        add_filter(TagManager::hookName('current-state'), function (array $data) {
            $data['kept_string'] = 'from_filter';
            return $data;
        });
        $data = $this->decodeCurrentStatePush($this->captureHook('wp_head'));
        $this->assertIsArray($data);
        $this->assertSame('from_filter', $data['kept_string']);
    }

    public function test_current_state_push_adds_nothing_for_model_without_override(): void
    {
        // EventPostTester inherits the empty buildDataLayerContext() default → globals only.
        $post = $this->factory()->post->create_and_get(['post_type' => 'event']);
        $this->setQueriedObject($post, $post->ID);
        $data = $this->decodeCurrentStatePush($this->captureHook('wp_head'));

        // Order-independent: exactly the base keys, no model keys leaked in.
        $this->assertIsArray($data);
        $this->assertCount(4, $data);
        $this->assertArrayHasKey('wp_post_type', $data);
        $this->assertArrayHasKey('wp_post_id', $data);
        $this->assertArrayHasKey('wp_slug', $data);
        $this->assertArrayHasKey('wp_title', $data);
    }

    public function test_current_state_push_skips_non_postbase_object(): void
    {
        // plain_post maps to a bare Timber\Post (not a PostBase) → merge is skipped, no fatal.
        $post = $this->factory()->post->create_and_get(['post_type' => 'plain_post']);
        $this->setQueriedObject($post, $post->ID);
        $data = $this->decodeCurrentStatePush($this->captureHook('wp_head'));

        // Order-independent: exactly the base keys, no model keys leaked in.
        $this->assertIsArray($data);
        $this->assertCount(4, $data);
        $this->assertArrayHasKey('wp_post_type', $data);
        $this->assertArrayHasKey('wp_post_id', $data);
        $this->assertArrayHasKey('wp_slug', $data);
        $this->assertArrayHasKey('wp_title', $data);
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

    public function test_outbound_decorator_payload_uses_nested_wire_shape(): void
    {
        $this->setOutboundDomains(true, [['domain' => 'partner.com', 'extra_params' => 'tess, session_hash']]);
        $inline = $this->captureOutboundParamsInline();
        $this->assertSame(
            ['domains' => ['partner.com' => ['extraParams' => ['tess', 'session_hash']]]],
            $this->decodeOutboundDecoratorPayload($inline),
        );
        $this->assertStringNotContainsString('"outboundDomains"', $inline);
    }

    public function test_outbound_decorator_payload_not_emitted_when_no_domains_configured(): void
    {
        $this->setOutboundDomains(true, []);
        $this->assertNull($this->decodeOutboundDecoratorPayload());
    }

    public function test_datalayer_push_contains_term_metadata(): void
    {
        $term = $this->factory()->term->create_and_get([
            'taxonomy' => 'category',
            'slug' => 'news',
            'name' => 'News',
        ]);
        $this->setQueriedObject($term, $term->term_id);
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('"wp_taxonomy":"category"', $head);
        $this->assertStringContainsString('"wp_term_id":' . $term->term_id, $head);
        $this->assertStringContainsString('"wp_slug":"news"', $head);
        $this->assertStringContainsString('"wp_title":"News"', $head);
    }

    public function test_datalayer_push_contains_post_type_metadata(): void
    {
        $postType = get_post_type_object('page');
        $this->setQueriedObject($postType);
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('"wp_post_type":"page"', $head);
        $this->assertStringNotContainsString('"wp_post_id"', $head);
        $this->assertStringContainsString('"wp_slug":"page"', $head);
        $this->assertStringContainsString('"wp_title":"Pages"', $head);
    }
}
