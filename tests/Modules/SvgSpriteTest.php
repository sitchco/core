<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Tests\TestCase;

class SvgSpriteTest extends TestCase
{
    /**
     * The icon list drives the picker's choices, so anything left in it has to be renderable:
     * renderIcon() only ever asks the sprite for `#icon-{name}`.
     *
     * @dataProvider symbolNameProvider
     */
    public function test_icon_names_keeps_only_prefixed_symbols(array $symbolNames, array $expected): void
    {
        $this->assertSame($expected, SvgSprite::iconNames($symbolNames));
    }

    public static function symbolNameProvider(): array
    {
        return [
            'strips the prefix' => [['icon-arrow', 'icon-search'], ['arrow', 'search']],
            // The shape stays in the sprite either way; it just stops being offered as an icon.
            'drops unprefixed shapes' => [['icon-arrow', 'half-circle'], ['arrow']],
            'strips only the leading prefix' => [['icon-icon-play'], ['icon-play']],
            'ignores the prefix mid-name' => [['brand-icon-mark'], []],
            // ACF reads the list as an ordered array, so the keys have to close up.
            'reindexes after filtering' => [['half-circle', 'icon-arrow'], ['arrow']],
        ];
    }
}
