<?php

namespace Sitchco\Tests\Modules\TagManager;

use Sitchco\Modules\TagManager\OutboundDomainsConfig;
use Sitchco\Modules\TagManager\TagManager;
use Sitchco\Modules\TagManager\TagManagerSettings;
use Sitchco\Tests\TestCase;

class OutboundDomainsConfigTest extends TestCase
{
    private TagManagerSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();
        acf_get_store('values')->reset();
        $this->settings = $this->container->get(TagManagerSettings::class);
    }

    protected function tearDown(): void
    {
        remove_all_filters('acf/load_value/name=gtm_decorate_outbound');
        remove_all_filters('acf/load_value/name=gtm_outbound_domains');
        remove_all_filters(TagManager::hookName('outbound-domains'));
        parent::tearDown();
    }

    private function setOutboundDomains(bool $enabled, array $domains = []): void
    {
        add_filter('acf/load_value/name=gtm_decorate_outbound', fn() => $enabled, 10, 0);
        add_filter('acf/load_value/name=gtm_outbound_domains', fn() => $domains, 10, 0);
    }

    public function test_normalizes_domain_case_and_whitespace(): void
    {
        $this->setOutboundDomains(true, [['domain' => '  Partner.COM ', 'extra_params' => 'tess']]);
        $this->assertSame(
            ['partner.com' => ['extraParams' => ['tess']]],
            OutboundDomainsConfig::fromSettings($this->settings),
        );
    }

    public function test_strips_invalid_tokens_at_read_time(): void
    {
        $this->setOutboundDomains(true, [
            ['domain' => 'partner.com', 'extra_params' => 'tess, bad token, session_hash'],
        ]);
        $this->assertSame(
            ['partner.com' => ['extraParams' => ['tess', 'session_hash']]],
            OutboundDomainsConfig::fromSettings($this->settings),
        );
    }

    public function test_handles_missing_extra_params_key(): void
    {
        $this->setOutboundDomains(true, [['domain' => 'partner.com']]);
        $this->assertSame(
            ['partner.com' => ['extraParams' => []]],
            OutboundDomainsConfig::fromSettings($this->settings),
        );
    }

    public function test_drops_malformed_rows(): void
    {
        $this->setOutboundDomains(true, [
            ['domain' => '', 'extra_params' => 'tess'],
            ['domain' => 'partner.com', 'extra_params' => 'tess'],
            'not-an-array',
        ]);
        $this->assertSame(
            ['partner.com' => ['extraParams' => ['tess']]],
            OutboundDomainsConfig::fromSettings($this->settings),
        );
    }

    public function test_collapses_duplicate_rows_last_wins(): void
    {
        $this->setOutboundDomains(true, [
            ['domain' => 'partner.com', 'extra_params' => 'tess'],
            ['domain' => 'partner.com', 'extra_params' => 'session_hash'],
        ]);
        $this->assertSame(
            ['partner.com' => ['extraParams' => ['session_hash']]],
            OutboundDomainsConfig::fromSettings($this->settings),
        );
    }

    public function test_preserves_config_row_order(): void
    {
        $this->setOutboundDomains(true, [
            ['domain' => 'partner.com', 'extra_params' => 'tess'],
            ['domain' => 'shop.partner.com', 'extra_params' => 'session_hash'],
        ]);
        $this->assertSame(
            ['partner.com', 'shop.partner.com'],
            array_keys(OutboundDomainsConfig::fromSettings($this->settings)),
        );
    }

    public function test_isolates_tokens_per_domain(): void
    {
        $this->setOutboundDomains(true, [
            ['domain' => 'a.com', 'extra_params' => 'tess'],
            ['domain' => 'b.com', 'extra_params' => 'session_hash'],
        ]);
        $this->assertSame(
            [
                'a.com' => ['extraParams' => ['tess']],
                'b.com' => ['extraParams' => ['session_hash']],
            ],
            OutboundDomainsConfig::fromSettings($this->settings),
        );
    }

    public function test_dedupes_duplicate_tokens(): void
    {
        $this->setOutboundDomains(true, [
            ['domain' => 'partner.com', 'extra_params' => 'tess, tess, session_hash, tess'],
        ]);
        $this->assertSame(
            ['partner.com' => ['extraParams' => ['tess', 'session_hash']]],
            OutboundDomainsConfig::fromSettings($this->settings),
        );
    }

    public function test_returns_empty_when_toggle_disabled(): void
    {
        $this->setOutboundDomains(false, [['domain' => 'partner.com', 'extra_params' => 'tess']]);
        $this->assertSame([], OutboundDomainsConfig::fromSettings($this->settings));
    }

    public function test_filter_receives_nested_config_shape(): void
    {
        $this->setOutboundDomains(true, [
            ['domain' => 'example.com', 'extra_params' => 'tess'],
            ['domain' => 'other.com', 'extra_params' => ''],
        ]);
        $captured = null;
        add_filter(TagManager::hookName('outbound-domains'), function (array $domains) use (&$captured) {
            $captured = $domains;
            return $domains;
        });
        OutboundDomainsConfig::fromSettings($this->settings);
        $this->assertSame(
            [
                'example.com' => ['extraParams' => ['tess']],
                'other.com' => ['extraParams' => []],
            ],
            $captured,
        );
    }

    public function test_filter_not_called_when_toggle_disabled(): void
    {
        $this->setOutboundDomains(false, [['domain' => 'example.com']]);
        $called = false;
        add_filter(TagManager::hookName('outbound-domains'), function (array $domains) use (&$called) {
            $called = true;
            return $domains;
        });
        OutboundDomainsConfig::fromSettings($this->settings);
        $this->assertFalse($called);
    }

    public function test_filter_output_is_renormalized(): void
    {
        $this->setOutboundDomains(true, [['domain' => 'partner.com', 'extra_params' => 'tess']]);
        add_filter(TagManager::hookName('outbound-domains'), function () {
            return [
                'Example.com ' => ['extraParams' => ['tess', 'bad token', 'tess', 'session_hash']],
                'partner.com' => ['extraParams' => 'not-an-array'],
                42 => ['extraParams' => ['x']],
                '' => ['extraParams' => ['y']],
                'other.com' => 'not-an-array',
            ];
        });
        $result = OutboundDomainsConfig::fromSettings($this->settings);
        $this->assertSame(['tess', 'session_hash'], $result['example.com']['extraParams'] ?? null);
        $this->assertSame([], $result['partner.com']['extraParams'] ?? null);
        $this->assertArrayNotHasKey('other.com', $result);
    }

    public function test_filter_bad_shape_falls_back_and_emits_doing_it_wrong(): void
    {
        $this->setOutboundDomains(true, [['domain' => 'partner.com', 'extra_params' => 'tess']]);
        add_filter(TagManager::hookName('outbound-domains'), fn() => 'not-an-array');
        $this->setExpectedIncorrectUsage('Sitchco\\Modules\\TagManager\\OutboundDomainsConfig::normalize');
        $result = OutboundDomainsConfig::fromSettings($this->settings);
        $this->assertSame(['tess'], $result['partner.com']['extraParams'] ?? null);
    }

    public function test_filter_accepts_object_root_return(): void
    {
        $this->setOutboundDomains(true, [['domain' => 'partner.com', 'extra_params' => 'tess']]);
        add_filter(TagManager::hookName('outbound-domains'), function () {
            return new \ArrayObject(['partner.com' => ['extraParams' => ['tess']]]);
        });
        $result = OutboundDomainsConfig::fromSettings($this->settings);
        $this->assertSame(['tess'], $result['partner.com']['extraParams'] ?? null);
    }

    public function test_filter_accepts_object_shaped_entry(): void
    {
        $this->setOutboundDomains(true, [['domain' => 'partner.com', 'extra_params' => 'tess']]);
        add_filter(TagManager::hookName('outbound-domains'), function () {
            return ['partner.com' => (object) ['extraParams' => ['session_hash']]];
        });
        $result = OutboundDomainsConfig::fromSettings($this->settings);
        $this->assertSame(['session_hash'], $result['partner.com']['extraParams'] ?? null);
    }

    public function test_filter_rejects_trailing_newline_token(): void
    {
        $this->setOutboundDomains(true, [['domain' => 'partner.com', 'extra_params' => 'tess']]);
        add_filter(TagManager::hookName('outbound-domains'), function () {
            return ['partner.com' => ['extraParams' => ["foo\n", 'session_hash']]];
        });
        $result = OutboundDomainsConfig::fromSettings($this->settings);
        $this->assertSame(['session_hash'], $result['partner.com']['extraParams'] ?? null);
    }

    public function test_filter_emits_doing_it_wrong_on_case_collision(): void
    {
        $this->setOutboundDomains(true, [['domain' => 'partner.com', 'extra_params' => 'tess']]);
        add_filter(TagManager::hookName('outbound-domains'), function () {
            return [
                'PARTNER.COM' => ['extraParams' => ['tess']],
                'partner.com' => ['extraParams' => ['session_hash']],
            ];
        });
        $this->setExpectedIncorrectUsage('Sitchco\\Modules\\TagManager\\OutboundDomainsConfig::normalize');
        OutboundDomainsConfig::fromSettings($this->settings);
    }

    public function test_validator_accepts_single_token(): void
    {
        $this->assertTrue(OutboundDomainsConfig::validateExtraParams(true, 'tess'));
    }

    public function test_validator_accepts_csv(): void
    {
        $this->assertTrue(OutboundDomainsConfig::validateExtraParams(true, 'tess,session_hash'));
    }

    public function test_validator_accepts_whitespace_around_tokens(): void
    {
        $this->assertTrue(OutboundDomainsConfig::validateExtraParams(true, ' tess , session_hash '));
    }

    public function test_validator_accepts_empty_string(): void
    {
        $this->assertTrue(OutboundDomainsConfig::validateExtraParams(true, ''));
    }

    public function test_validator_accepts_duplicate_tokens(): void
    {
        $this->assertTrue(OutboundDomainsConfig::validateExtraParams(true, 'tess, tess'));
    }

    /**
     * @dataProvider invalidCharacterTokenProvider
     */
    public function test_validator_rejects_token_with_invalid_character(string $token): void
    {
        $result = OutboundDomainsConfig::validateExtraParams(true, $token);
        $this->assertIsString($result);
        $this->assertStringContainsString($token, $result);
    }

    public static function invalidCharacterTokenProvider(): array
    {
        return [
            'space' => ['utm source'],
            'equals' => ['tess=hash'],
            'period' => ['tess.hash'],
        ];
    }

    public function test_validator_rejects_script_tag(): void
    {
        $result = OutboundDomainsConfig::validateExtraParams(true, '<script>');
        $this->assertIsString($result);
        $this->assertStringContainsString('<script>', $result);
    }

    public function test_validator_rejects_when_any_token_invalid(): void
    {
        $result = OutboundDomainsConfig::validateExtraParams(true, 'tess, bad token, session_hash');
        $this->assertIsString($result);
        $this->assertStringContainsString('bad token', $result);
    }

    public function test_validator_passes_through_prior_invalid(): void
    {
        $this->assertSame('prior error', OutboundDomainsConfig::validateExtraParams('prior error', 'tess'));
    }
}
