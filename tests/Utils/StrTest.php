<?php

namespace Sitchco\Tests\Utils;

use ReflectionMethod;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Str;

class StrTest extends TestCase
{
    private function callFallback(float $amount, string $currency, ?int $decimals = null): string
    {
        try {
            $m = new ReflectionMethod(Str::class, 'formatCurrencyFallback');
            return $m->invoke(null, $amount, $currency, $decimals);
        } catch (\ReflectionException $e) {
            $this->fail('Reflection failed: ' . $e->getMessage());
        }
    }

    // --- formatCurrency: intl path ---

    public function test_format_currency_default_usd_en_us(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $result = Str::formatCurrency(1234.56, ['locale' => 'en_US']);
        $this->assertStringContainsString('1,234.56', $result);
        $this->assertStringContainsString('$', $result);
    }

    public function test_format_currency_with_explicit_locale_eur(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $result = Str::formatCurrency(1234.56, ['currency' => 'EUR', 'locale' => 'de_DE']);
        $this->assertStringContainsString('1.234,56', $result);
        $this->assertStringContainsString('€', $result);
    }

    public function test_format_currency_zero(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $result = Str::formatCurrency(0, ['locale' => 'en_US']);
        $this->assertStringContainsString('0.00', $result);
        $this->assertStringContainsString('$', $result);
    }

    public function test_format_currency_negative(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $result = Str::formatCurrency(-1234.56, ['locale' => 'en_US']);
        $this->assertStringContainsString('1,234.56', $result);
        // ICU may use "-$..." or "($...)" depending on version/locale
        $this->assertMatchesRegularExpression('/[-(]/', $result);
    }

    public function test_format_currency_intl_with_decimals_zero(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $result = Str::formatCurrency(1234.56, ['locale' => 'en_US', 'decimals' => 0]);
        $this->assertStringContainsString('1,235', $result);
        $this->assertStringContainsString('$', $result);
        $this->assertStringNotContainsString('.', $result);
    }

    // --- formatCurrency: fallback path (via reflection) ---

    public function test_format_currency_fallback_usd(): void
    {
        $this->assertSame('$1,234.56', $this->callFallback(1234.56, 'USD'));
    }

    public function test_format_currency_fallback_eur(): void
    {
        $this->assertSame('€1,234.56', $this->callFallback(1234.56, 'EUR'));
    }

    public function test_format_currency_unknown_code_uses_code_prefix(): void
    {
        $this->assertSame('XYZ 1,234.56', $this->callFallback(1234.56, 'XYZ'));
    }

    public function test_format_currency_lowercase_code_normalized(): void
    {
        $this->assertSame('$1,234.56', $this->callFallback(1234.56, 'usd'));
    }

    public function test_format_currency_fallback_with_decimals_zero(): void
    {
        $this->assertSame('$1,235', $this->callFallback(1234.56, 'USD', 0));
    }

    // --- formatCurrencyRange ---

    public function test_format_currency_range_same_low_high_returns_single_amount(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $result = Str::formatCurrencyRange(50, 50, ['locale' => 'en_US', 'decimals' => 0]);
        $this->assertSame('$50', $result);
        $this->assertSame(1, substr_count($result, '$'));
    }

    public function test_format_currency_range_different_low_high(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $this->assertSame('$25-75', Str::formatCurrencyRange(25, 75, ['locale' => 'en_US', 'decimals' => 0]));
    }

    public function test_format_currency_range_custom_separator_with_spaces(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $this->assertSame(
            '$25 – 75',
            Str::formatCurrencyRange(25, 75, ['locale' => 'en_US', 'decimals' => 0, 'separator' => ' – ']),
        );
    }

    public function test_format_currency_range_zero(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $this->assertSame('$0', Str::formatCurrencyRange(0, 0, ['locale' => 'en_US', 'decimals' => 0]));
    }

    public function test_format_currency_range_with_decimals(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $this->assertSame(
            '$25.50-75.25',
            Str::formatCurrencyRange(25.5, 75.25, ['locale' => 'en_US', 'decimals' => 2]),
        );
    }

    public function test_format_currency_range_eur_locale(): void
    {
        if (!class_exists('NumberFormatter')) {
            $this->markTestSkipped('ext-intl not installed.');
        }
        $result = Str::formatCurrencyRange(25, 75, [
            'currency' => 'EUR',
            'locale' => 'de_DE',
            'decimals' => 0,
        ]);
        $this->assertSame(1, substr_count($result, '€'));
        $this->assertStringContainsString('25', $result);
        $this->assertStringContainsString('75', $result);
        $this->assertStringContainsString('-', $result);
    }

    // --- plural ---

    public function test_plural_basic_word(): void
    {
        $this->assertSame('cats', Str::plural('cat'));
    }

    public function test_plural_irregular_word(): void
    {
        $this->assertSame('children', Str::plural('child'));
    }

    // --- singular ---

    public function test_singular_basic_word(): void
    {
        $this->assertSame('cat', Str::singular('cats'));
    }

    public function test_singular_irregular_word(): void
    {
        $this->assertSame('mouse', Str::singular('mice'));
    }

    // --- toCamelCase ---

    public function test_to_camel_case_from_snake_case(): void
    {
        $this->assertSame('helloWorld', Str::toCamelCase('hello_world'));
    }

    public function test_to_camel_case_single_word(): void
    {
        $this->assertSame('hello', Str::toCamelCase('hello'));
    }

    public function test_to_camel_case_multiple_segments(): void
    {
        $this->assertSame('fooBarBaz', Str::toCamelCase('foo_bar_baz'));
    }

    // --- toPascalCase ---

    public function test_to_pascal_case_from_snake_case(): void
    {
        $this->assertSame('HelloWorld', Str::toPascalCase('hello_world'));
    }

    public function test_to_pascal_case_single_word(): void
    {
        $this->assertSame('Hello', Str::toPascalCase('hello'));
    }

    // --- toSnakeCase ---

    public function test_to_snake_case_from_pascal_case(): void
    {
        $this->assertSame('hello_world', Str::toSnakeCase('HelloWorld'));
    }

    public function test_to_snake_case_from_camel_case(): void
    {
        $this->assertSame('hello_world', Str::toSnakeCase('helloWorld'));
    }

    public function test_to_snake_case_already_lowercase(): void
    {
        $this->assertSame('hello', Str::toSnakeCase('hello'));
    }

    // --- truncate ---

    public function test_truncate_short_text_unchanged(): void
    {
        $this->assertSame('Short text', Str::truncate('Short text', 48));
    }

    public function test_truncate_long_text_appends_ellipsis(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog and then keeps running';
        $result = Str::truncate($text, 20);
        $this->assertStringEndsWith('...', $result);
        $this->assertLessThanOrEqual(23, strlen($result));
    }

    public function test_truncate_custom_append(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog';
        $result = Str::truncate($text, 15, '…');
        $this->assertStringEndsWith('…', $result);
    }

    // --- cutUsingLast ---

    public function test_cut_using_last_left_keeps_character(): void
    {
        $this->assertSame('foo.bar.', Str::cutUsingLast('.', 'foo.bar.baz', 'left', true));
    }

    public function test_cut_using_last_left_drops_character(): void
    {
        $this->assertSame('foo.bar', Str::cutUsingLast('.', 'foo.bar.baz', 'left', false));
    }

    public function test_cut_using_last_right_keeps_character(): void
    {
        $this->assertSame('.baz', Str::cutUsingLast('.', 'foo.bar.baz', 'right', true));
    }

    public function test_cut_using_last_right_drops_character(): void
    {
        $this->assertSame('baz', Str::cutUsingLast('.', 'foo.bar.baz', 'right', false));
    }

    public function test_cut_using_last_invalid_side_returns_false(): void
    {
        $this->assertFalse(Str::cutUsingLast('.', 'foo.bar', 'invalid'));
    }

    // --- sanitizeKey ---

    public function test_sanitize_key_lowercases_and_replaces_spaces(): void
    {
        $this->assertSame('hello_world', Str::sanitizeKey('Hello World'));
    }

    public function test_sanitize_key_converts_hyphens_to_underscores(): void
    {
        $this->assertSame('foo_bar_baz', Str::sanitizeKey('foo-bar-baz'));
    }

    // --- getFirstParagraph ---

    public function test_get_first_paragraph_extracts_first_p_tag(): void
    {
        $html = '<p>First paragraph.</p><p>Second paragraph.</p>';
        $this->assertSame('<p>First paragraph.</p>', Str::getFirstParagraph($html));
    }

    public function test_get_first_paragraph_with_leading_content(): void
    {
        $html = '<h1>Title</h1><p>Hello there.</p><p>Goodbye.</p>';
        $this->assertSame('<p>Hello there.</p>', Str::getFirstParagraph($html));
    }

    // --- getLastWords ---

    public function test_get_last_words_returns_last_n_words(): void
    {
        $this->assertSame('Lazy Dog', Str::getLastWords('the quick brown fox jumps over the lazy dog', 2));
    }

    public function test_get_last_words_count_exceeds_word_count(): void
    {
        $this->assertSame('Hello World', Str::getLastWords('hello world', 5));
    }

    // --- wrapLink ---

    public function test_wrap_link_with_url(): void
    {
        $result = Str::wrapLink('Click here', 'https://example.com');
        $this->assertSame('<a href="https://example.com">Click here</a>', $result);
    }

    public function test_wrap_link_without_url(): void
    {
        $result = Str::wrapLink('No link', null);
        $this->assertSame('<a>No link</a>', $result);
    }

    public function test_wrap_link_with_extra_attributes(): void
    {
        $result = Str::wrapLink('Click', 'https://example.com', ['class' => 'btn', 'target' => '_blank']);
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('class="btn"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
        $this->assertStringContainsString('>Click</a>', $result);
    }

    // --- wrapElement ---

    public function test_wrap_element_with_no_attributes(): void
    {
        $this->assertSame('<span>Hello</span>', Str::wrapElement('Hello', 'span'));
    }

    public function test_wrap_element_with_array_attributes(): void
    {
        $result = Str::wrapElement('Hello', 'div', ['id' => 'main', 'class' => 'foo']);
        $this->assertSame('<div id="main" class="foo">Hello</div>', $result);
    }

    public function test_wrap_element_with_string_attributes(): void
    {
        $result = Str::wrapElement('Hello', 'div', 'data-test="x"');
        $this->assertSame('<div data-test="x">Hello</div>', $result);
    }

    // --- trimHtml ---

    /**
     * @dataProvider trimHtmlProvider
     */
    public function test_trim_html(string $input, string $expected): void
    {
        $this->assertSame($expected, Str::trimHtml($input));
    }

    public static function trimHtmlProvider(): array
    {
        return [
            'plain text unchanged' => ['Hello world', 'Hello world'],
            'surrounding whitespace trimmed' => ["  Hello world \n", 'Hello world'],
            'tags stripped' => ['<p>Hello <strong>world</strong></p>', 'Hello world'],
            'html comments stripped' => ['<!-- a comment -->Hello', 'Hello'],
            'entities decoded' => ['Tom &amp; Jerry', 'Tom & Jerry'],
            'empty string' => ['', ''],
            'whitespace only' => ["  \n\t ", ''],
            'tags only' => ['<p></p><div></div>', ''],
            'nbsp entity only' => ['&nbsp;', ''],
            'nbsp character only' => ["\u{A0}", ''],
            'empty paragraph block markup' => ["<!-- wp:paragraph -->\n<p>&nbsp;</p>\n<!-- /wp:paragraph -->", ''],
            'block delimiters with no inner content' => ["<!-- wp:paragraph -->\n<!-- /wp:paragraph -->", ''],
            'paragraph block with content' => [
                "<!-- wp:paragraph -->\n<p>A short bio.</p>\n<!-- /wp:paragraph -->",
                'A short bio.',
            ],
            'interior nbsp preserved' => ['Hello&nbsp;world', "Hello\u{A0}world"],
        ];
    }

    // --- hexToRGB ---

    public function test_hex_to_rgb_six_digit(): void
    {
        $this->assertSame('255, 0, 0', Str::hexToRGB('#ff0000'));
    }

    public function test_hex_to_rgb_three_digit(): void
    {
        $this->assertSame('255, 0, 0', Str::hexToRGB('#f00'));
    }

    public function test_hex_to_rgb_passes_through_rgb_string(): void
    {
        $this->assertSame('255, 0, 0', Str::hexToRGB('rgb(255, 0, 0)'));
    }

    public function test_hex_to_rgb_six_digit_mixed_case(): void
    {
        $this->assertSame('17, 34, 51', Str::hexToRGB('#112233'));
    }

    // --- hexToHSL / hslToHex ---

    /**
     * @dataProvider hslRoundTripProvider
     */
    public function test_hsl_round_trip_within_one_per_channel(string $hex): void
    {
        $roundTripped = Str::hslToHex(...Str::hexToHSL($hex));
        $this->assertChannelsWithinOne($hex, $roundTripped);
    }

    public static function hslRoundTripProvider(): array
    {
        return [
            'red' => ['#ff0000'],
            'green' => ['#00ff00'],
            'blue' => ['#0000ff'],
            'mixed' => ['#112233'],
            'white' => ['#ffffff'],
            'black' => ['#000000'],
            'gray' => ['#808080'],
            'brand magenta' => ['#e60073'],
            'muted blue' => ['#486090'],
        ];
    }

    public function test_hex_to_hsl_short_hex_matches_full_hex(): void
    {
        $this->assertSame(Str::hexToHSL('#ff0000'), Str::hexToHSL('#f00'));
        $this->assertSame(Str::hexToHSL('#112233'), Str::hexToHSL('#123'));
    }

    public function test_hex_to_hsl_gray_is_achromatic(): void
    {
        [$h, $s, $l] = Str::hexToHSL('#808080');
        $this->assertSame(0.0, $h);
        $this->assertSame(0.0, $s);
        $this->assertEqualsWithDelta(50.2, $l, 0.5);
    }

    public function test_hex_to_hsl_invalid_input_degrades_to_black(): void
    {
        $this->assertSame([0.0, 0.0, 0.0], Str::hexToHSL(''));
        $this->assertSame([0.0, 0.0, 0.0], Str::hexToHSL('not-a-color'));
    }

    public function test_hsl_to_hex_clamps_lightness_high(): void
    {
        // Lightness over 100 must clamp to white, not wrap or overshoot.
        $this->assertSame('#ffffff', Str::hslToHex(0, 0, 130));
    }

    public function test_hsl_to_hex_clamps_lightness_low(): void
    {
        // Negative lightness clamps to black.
        $this->assertSame('#000000', Str::hslToHex(200, 100, -20));
    }

    public function test_hsl_to_hex_clamps_lightness_after_plus_thirty(): void
    {
        // A light base + 30 lightness overshoots 100 and must clamp to white.
        [$h, $s, $l] = Str::hexToHSL('#cccccc'); // L ~80%
        $this->assertSame('#ffffff', Str::hslToHex($h, $s, $l + 30));
    }

    /**
     * The Criterion feed derives each "light" accent from its "base" by
     * adding 30 percentage points of lightness. Deriving light from base
     * must reproduce the feed's light within +/- 1 per channel.
     *
     * @dataProvider feedSampleProvider
     */
    public function test_light_derivation_matches_feed_samples(string $base, string $light): void
    {
        [$h, $s, $l] = Str::hexToHSL($base);
        $derived = Str::hslToHex($h, $s, $l + 30);
        $this->assertChannelsWithinOne($light, $derived);
    }

    public static function feedSampleProvider(): array
    {
        return [
            '& Juliet' => ['#e60073', '#ff80bf'],
            'Purpose' => ['#486090', '#a1b1d0'],
            'Oedipus' => ['#137cb9', '#74c3f1'],
        ];
    }

    private function assertChannelsWithinOne(string $expectedHex, string $actualHex): void
    {
        $expected = array_map('intval', explode(',', Str::hexToRGB($expectedHex)));
        $actual = array_map('intval', explode(',', Str::hexToRGB($actualHex)));
        foreach (['R', 'G', 'B'] as $i => $channel) {
            $this->assertLessThanOrEqual(
                1,
                abs($expected[$i] - $actual[$i]),
                "Channel {$channel} of {$actualHex} differs from {$expectedHex} by more than 1",
            );
        }
    }
}
