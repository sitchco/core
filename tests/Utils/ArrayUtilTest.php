<?php

namespace Sitchco\Tests\Utils;

use ArrayIterator;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\ArrayUtil;

class ArrayUtilTest extends TestCase
{
    // --- mergeRecursiveDistinct ---

    public function test_merge_recursive_distinct_overwrites_scalar_values(): void
    {
        $result = ArrayUtil::mergeRecursiveDistinct(['a' => 1, 'b' => 2], ['b' => 3, 'c' => 4]);
        $this->assertSame(['a' => 1, 'b' => 3, 'c' => 4], $result);
    }

    public function test_merge_recursive_distinct_merges_nested_arrays(): void
    {
        $result = ArrayUtil::mergeRecursiveDistinct(
            ['nested' => ['a' => 1, 'b' => 2]],
            ['nested' => ['b' => 3, 'c' => 4]],
        );
        $this->assertSame(['nested' => ['a' => 1, 'b' => 3, 'c' => 4]], $result);
    }

    public function test_merge_recursive_distinct_with_multiple_arrays(): void
    {
        $result = ArrayUtil::mergeRecursiveDistinct(['a' => 1], ['b' => 2], ['a' => 3, 'c' => 4]);
        $this->assertSame(['a' => 3, 'b' => 2, 'c' => 4], $result);
    }

    public function test_merge_recursive_distinct_scalar_overwrites_array(): void
    {
        $result = ArrayUtil::mergeRecursiveDistinct(['a' => ['nested' => 1]], ['a' => 'scalar']);
        $this->assertSame(['a' => 'scalar'], $result);
    }

    public function test_merge_recursive_distinct_empty_arrays(): void
    {
        $this->assertSame([], ArrayUtil::mergeRecursiveDistinct([], []));
    }

    // --- arrayMapAssoc ---

    public function test_array_map_assoc_passes_key_and_value(): void
    {
        $result = ArrayUtil::arrayMapAssoc(fn($key, $value) => "$key=$value", ['a' => 1, 'b' => 2]);
        $this->assertSame(['a=1', 'b=2'], $result);
    }

    public function test_array_map_assoc_empty_array(): void
    {
        $this->assertSame([], ArrayUtil::arrayMapAssoc(fn($k, $v) => $v, []));
    }

    // --- arrayMapFlat ---

    public function test_array_map_flat_flattens_results(): void
    {
        $result = ArrayUtil::arrayMapFlat(fn($item) => [$item, $item * 2], [1, 2, 3]);
        $this->assertSame([1, 2, 2, 4, 3, 6], $result);
    }

    // --- arrayToAssocByColumn ---

    public function test_array_to_assoc_by_column(): void
    {
        $input = [['id' => 'a', 'name' => 'Alice'], ['id' => 'b', 'name' => 'Bob']];
        $result = ArrayUtil::arrayToAssocByColumn($input, 'id');
        $this->assertSame(
            [
                'a' => ['id' => 'a', 'name' => 'Alice'],
                'b' => ['id' => 'b', 'name' => 'Bob'],
            ],
            $result,
        );
    }

    // --- convertToList ---

    public function test_convert_to_list_default_ul(): void
    {
        $result = ArrayUtil::convertToList(['Item 1', 'Item 2']);
        $this->assertStringContainsString('<ul', $result);
        $this->assertStringContainsString('<li class="">Item 1</li>', $result);
        $this->assertStringContainsString('<li class="">Item 2</li>', $result);
        $this->assertStringContainsString('</ul>', $result);
    }

    public function test_convert_to_list_ordered_with_classes(): void
    {
        $result = ArrayUtil::convertToList(
            ['A'],
            [
                'list_type' => 'ol',
                'list_class' => 'my-list',
                'item_class' => 'my-item',
            ],
        );
        $this->assertStringContainsString('<ol class="my-list"', $result);
        $this->assertStringContainsString('<li class="my-item">A</li>', $result);
        $this->assertStringContainsString('</ol>', $result);
    }

    // --- toAttributes ---

    public function test_to_attributes_string_value(): void
    {
        $result = ArrayUtil::toAttributes(['id' => 'main', 'role' => 'button']);
        $this->assertSame('id="main" role="button"', $result);
    }

    public function test_to_attributes_boolean_true(): void
    {
        $result = ArrayUtil::toAttributes(['disabled' => true]);
        $this->assertSame('disabled', $result);
    }

    public function test_to_attributes_false_and_null_omitted(): void
    {
        $result = ArrayUtil::toAttributes(['hidden' => false, 'data-x' => null, 'id' => 'keep']);
        $this->assertSame('id="keep"', $result);
    }

    public function test_to_attributes_array_value_joined(): void
    {
        $result = ArrayUtil::toAttributes(['class' => ['foo', 'bar']]);
        $this->assertSame('class="foo bar"', $result);
    }

    public function test_to_attributes_style_array_converted_to_css(): void
    {
        $result = ArrayUtil::toAttributes(['style' => ['color' => 'red', 'margin' => '0']]);
        $this->assertSame('style="color: red;margin: 0;"', $result);
    }

    public function test_to_attributes_empty_string_omitted(): void
    {
        $result = ArrayUtil::toAttributes(['title' => '', 'id' => 'x']);
        $this->assertSame('id="x"', $result);
    }

    // --- parseStyleString ---

    public function test_parse_style_string_basic(): void
    {
        $result = ArrayUtil::parseStyleString('color: red; font-size: 14px;');
        $this->assertSame(['color' => 'red', 'font-size' => '14px'], $result);
    }

    public function test_parse_style_string_without_trailing_semicolon(): void
    {
        $result = ArrayUtil::parseStyleString('color: red; font-size: 14px');
        $this->assertSame(['color' => 'red', 'font-size' => '14px'], $result);
    }

    public function test_parse_style_string_with_colon_in_value(): void
    {
        $result = ArrayUtil::parseStyleString('background: url(http://example.com)');
        $this->assertSame(['background' => 'url(http://example.com)'], $result);
    }

    public function test_parse_style_string_empty(): void
    {
        $this->assertSame([], ArrayUtil::parseStyleString(''));
    }

    // --- toCSSProperties ---

    public function test_to_css_properties_basic(): void
    {
        $result = ArrayUtil::toCSSProperties(['color' => 'red', 'margin' => '0']);
        $this->assertSame('color: red;margin: 0;', $result);
    }

    public function test_to_css_properties_filters_empty_values(): void
    {
        $result = ArrayUtil::toCSSProperties(['color' => 'red', 'margin' => '', 'padding' => null]);
        $this->assertSame('color: red;', $result);
    }

    public function test_to_css_properties_array_value_joined(): void
    {
        $result = ArrayUtil::toCSSProperties(['margin' => ['10px', '20px']]);
        $this->assertSame('margin: 10px 20px;', $result);
    }

    // --- diff ---

    public function test_diff_returns_empty_string_when_equal(): void
    {
        $this->assertSame('', ArrayUtil::diff(['a' => 1], ['a' => 1]));
    }

    public function test_diff_returns_changed_values(): void
    {
        $result = ArrayUtil::diff(['a' => 1, 'b' => 3], ['a' => 1, 'b' => 2]);
        $this->assertSame(['b' => 3], $result);
    }

    public function test_diff_returns_added_keys(): void
    {
        $result = ArrayUtil::diff(['a' => 1, 'b' => 2], ['a' => 1]);
        $this->assertSame(['b' => 2], $result);
    }

    // --- stringify ---

    public function test_stringify_converts_values_to_strings(): void
    {
        $result = ArrayUtil::stringify(['a' => 1, 'b' => 2.5, 'c' => true]);
        $this->assertSame(['a' => '1', 'b' => '2.5', 'c' => '1'], $result);
    }

    public function test_stringify_recursive(): void
    {
        $result = ArrayUtil::stringify(['nested' => ['a' => 1]]);
        $this->assertSame(['nested' => ['a' => '1']], $result);
    }

    // --- numerify ---

    public function test_numerify_converts_numeric_strings(): void
    {
        $result = ArrayUtil::numerify(['a' => '1', 'b' => '2.5', 'c' => 'text']);
        $this->assertSame(1, $result['a']);
        $this->assertSame(2.5, $result['b']);
        $this->assertSame('text', $result['c']);
    }

    public function test_numerify_recursive(): void
    {
        $result = ArrayUtil::numerify(['nested' => ['a' => '42']]);
        $this->assertSame(['nested' => ['a' => 42]], $result);
    }

    // --- normalizeIterable ---

    public function test_normalize_iterable_returns_null_for_null(): void
    {
        $this->assertNull(ArrayUtil::normalizeIterable(null));
    }

    public function test_normalize_iterable_returns_array_as_is(): void
    {
        $this->assertSame(['a' => 1], ArrayUtil::normalizeIterable(['a' => 1]));
    }

    public function test_normalize_iterable_strips_keys_when_requested(): void
    {
        $this->assertSame([1, 2], ArrayUtil::normalizeIterable(['a' => 1, 'b' => 2], false));
    }

    public function test_normalize_iterable_converts_traversable(): void
    {
        $iterator = new ArrayIterator(['x' => 10, 'y' => 20]);
        $this->assertSame(['x' => 10, 'y' => 20], ArrayUtil::normalizeIterable($iterator));
    }

    public function test_normalize_iterable_returns_null_for_scalar(): void
    {
        $this->assertNull(ArrayUtil::normalizeIterable('string'));
        $this->assertNull(ArrayUtil::normalizeIterable(42));
    }

    // --- mergeAttributes ---

    public function test_merge_attributes_simple_overwrite(): void
    {
        $result = ArrayUtil::mergeAttributes(['id' => 'a', 'role' => 'button'], ['id' => 'b']);
        $this->assertSame('b', $result['id']);
        $this->assertSame('button', $result['role']);
    }

    public function test_merge_attributes_class_strings_combined(): void
    {
        $result = ArrayUtil::mergeAttributes(['class' => 'foo bar'], ['class' => 'baz foo']);
        $this->assertSame('foo bar baz', $result['class']);
    }

    public function test_merge_attributes_class_arrays_combined(): void
    {
        $result = ArrayUtil::mergeAttributes(['class' => ['foo', 'bar']], ['class' => ['baz']]);
        $this->assertSame('foo bar baz', $result['class']);
    }

    public function test_merge_attributes_class_mixed_string_and_array(): void
    {
        $result = ArrayUtil::mergeAttributes(['class' => 'foo bar'], ['class' => ['baz']]);
        $this->assertSame('foo bar baz', $result['class']);
    }

    public function test_merge_attributes_style_arrays_merged(): void
    {
        $result = ArrayUtil::mergeAttributes(
            ['style' => ['color' => 'red', 'margin' => '0']],
            ['style' => ['color' => 'blue', 'padding' => '10px']],
        );
        $this->assertSame('color: blue;margin: 0;padding: 10px;', $result['style']);
    }

    public function test_merge_attributes_style_strings_merged(): void
    {
        $result = ArrayUtil::mergeAttributes(
            ['style' => 'color: red; margin: 0;'],
            ['style' => 'color: blue; padding: 10px;'],
        );
        $this->assertSame('color: blue;margin: 0;padding: 10px;', $result['style']);
    }

    public function test_merge_attributes_style_string_and_array_merged(): void
    {
        $result = ArrayUtil::mergeAttributes(
            ['style' => 'color: red; margin: 0;'],
            ['style' => ['color' => 'blue', 'padding' => '10px']],
        );
        $this->assertSame('color: blue;margin: 0;padding: 10px;', $result['style']);
    }

    public function test_merge_attributes_style_array_and_string_merged(): void
    {
        $result = ArrayUtil::mergeAttributes(
            ['style' => ['color' => 'red', 'margin' => '0']],
            ['style' => 'color: blue; padding: 10px;'],
        );
        $this->assertSame('color: blue;margin: 0;padding: 10px;', $result['style']);
    }

    public function test_merge_attributes_multiple_sets(): void
    {
        $result = ArrayUtil::mergeAttributes(
            ['class' => 'a', 'id' => 'first'],
            ['class' => 'b', 'role' => 'button'],
            ['class' => 'c', 'id' => 'last'],
        );
        $this->assertSame('a b c', $result['class']);
        $this->assertSame('last', $result['id']);
        $this->assertSame('button', $result['role']);
    }

    public function test_merge_attributes_empty_sets(): void
    {
        $this->assertSame([], ArrayUtil::mergeAttributes([], []));
    }

    public function test_merge_attributes_class_deduplicates(): void
    {
        $result = ArrayUtil::mergeAttributes(['class' => 'foo bar'], ['class' => 'bar baz']);
        $this->assertSame('foo bar baz', $result['class']);
    }

    public function test_merge_attributes_class_filters_empty_strings(): void
    {
        $result = ArrayUtil::mergeAttributes(['class' => ['foo', '', 'bar']]);
        $this->assertSame('foo bar', $result['class']);
    }

    public function test_merge_attributes_style_scalar_existing_does_not_crash(): void
    {
        $result = ArrayUtil::mergeAttributes(['style' => false], ['style' => ['color' => 'red']]);
        $this->assertSame('color: red;', $result['style']);

        $result = ArrayUtil::mergeAttributes(['style' => true], ['style' => ['color' => 'red']]);
        $this->assertSame('color: red;', $result['style']);
    }

    // --- pick ---

    public function test_pick_selects_specified_keys(): void
    {
        $result = ArrayUtil::pick(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'c']);
        $this->assertSame(['a' => 1, 'c' => 3], $result);
    }

    public function test_pick_ignores_missing_keys(): void
    {
        $result = ArrayUtil::pick(['a' => 1], ['a', 'b']);
        $this->assertSame(['a' => 1], $result);
    }

    public function test_pick_returns_empty_for_no_matches(): void
    {
        $this->assertSame([], ArrayUtil::pick(['a' => 1], ['b']));
    }
}
