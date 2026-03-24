<?php

namespace Sitchco\Tests\Modules\Wordpress;

use Sitchco\Modules\Wordpress\Cleanup;
use Sitchco\Tests\TestCase;

class CleanupTest extends TestCase
{
    private Cleanup $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(Cleanup::class);
    }

    public function test_strips_single_trailing_empty_paragraph(): void
    {
        $content = <<<'HTML'
        <!-- wp:paragraph -->
        <p>Hello world</p>
        <!-- /wp:paragraph -->

        <!-- wp:paragraph -->
        <p></p>
        <!-- /wp:paragraph -->
        HTML;

        $expected = <<<'HTML'
        <!-- wp:paragraph -->
        <p>Hello world</p>
        <!-- /wp:paragraph -->
        HTML;

        $this->assertSame($expected, $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function test_strips_multiple_trailing_empty_paragraphs(): void
    {
        $content = <<<'HTML'
        <!-- wp:paragraph -->
        <p>Hello world</p>
        <!-- /wp:paragraph -->

        <!-- wp:paragraph -->
        <p></p>
        <!-- /wp:paragraph -->

        <!-- wp:paragraph -->
        <p></p>
        <!-- /wp:paragraph -->
        HTML;

        $expected = <<<'HTML'
        <!-- wp:paragraph -->
        <p>Hello world</p>
        <!-- /wp:paragraph -->
        HTML;

        $this->assertSame($expected, $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function test_strips_after_non_paragraph_block(): void
    {
        $content = <<<'HTML'
        <!-- wp:heading -->
        <h2>Title</h2>
        <!-- /wp:heading -->

        <!-- wp:paragraph -->
        <p></p>
        <!-- /wp:paragraph -->
        HTML;

        $expected = <<<'HTML'
        <!-- wp:heading -->
        <h2>Title</h2>
        <!-- /wp:heading -->
        HTML;

        $this->assertSame($expected, $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function test_strips_content_that_is_only_empty_paragraphs(): void
    {
        $content = <<<'HTML'
        <!-- wp:paragraph -->
        <p></p>
        <!-- /wp:paragraph -->

        <!-- wp:paragraph -->
        <p></p>
        <!-- /wp:paragraph -->
        HTML;

        $this->assertSame('', $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function test_handles_empty_content(): void
    {
        $this->assertSame('', $this->module->removeTrailingEmptyParagraphs(''));
    }

    public function test_preserves_non_trailing_empty_paragraphs(): void
    {
        $content = <<<'HTML'
        <!-- wp:paragraph -->
        <p>First</p>
        <!-- /wp:paragraph -->

        <!-- wp:paragraph -->
        <p></p>
        <!-- /wp:paragraph -->

        <!-- wp:paragraph -->
        <p>Last</p>
        <!-- /wp:paragraph -->
        HTML;

        $this->assertSame($content, $this->module->removeTrailingEmptyParagraphs($content));
    }

    /**
     * @dataProvider preserved_trailing_blocks
     */
    public function test_preserves_non_bare_trailing_blocks(string $trailingBlock): void
    {
        $content = "<!-- wp:paragraph -->\n<p>Content</p>\n<!-- /wp:paragraph -->\n\n" . $trailingBlock;

        $this->assertSame($content, $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function preserved_trailing_blocks(): array
    {
        return [
            'paragraph with text' => ["<!-- wp:paragraph -->\n<p>Hello world</p>\n<!-- /wp:paragraph -->"],
            'paragraph with nbsp' => ["<!-- wp:paragraph -->\n<p>&nbsp;</p>\n<!-- /wp:paragraph -->"],
            'paragraph with br' => ["<!-- wp:paragraph -->\n<p><br></p>\n<!-- /wp:paragraph -->"],
            'paragraph with self-closing br' => ["<!-- wp:paragraph -->\n<p><br /></p>\n<!-- /wp:paragraph -->"],
            'paragraph with whitespace' => ["<!-- wp:paragraph -->\n<p>   </p>\n<!-- /wp:paragraph -->"],
            'paragraph with attributes' => [
                "<!-- wp:paragraph {\"align\":\"center\"} -->\n<p class=\"has-text-align-center\"></p>\n<!-- /wp:paragraph -->",
            ],
            'non-paragraph block' => [
                "<!-- wp:spacer -->\n<div style=\"height:100px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->",
            ],
        ];
    }
}
