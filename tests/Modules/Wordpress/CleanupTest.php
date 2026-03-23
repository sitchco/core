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

    public function test_strips_nbsp_paragraph(): void
    {
        $content = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>&nbsp;</p>
<!-- /wp:paragraph -->
HTML;

        $expected = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->
HTML;

        $this->assertSame($expected, $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function test_strips_br_paragraph(): void
    {
        $content = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><br></p>
<!-- /wp:paragraph -->
HTML;

        $expected = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->
HTML;

        $this->assertSame($expected, $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function test_strips_self_closing_br_paragraph(): void
    {
        $content = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><br /></p>
<!-- /wp:paragraph -->
HTML;

        $expected = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->
HTML;

        $this->assertSame($expected, $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function test_strips_empty_paragraph_with_block_attributes(): void
    {
        $content = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"></p>
<!-- /wp:paragraph -->
HTML;

        $expected = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->
HTML;

        $this->assertSame($expected, $this->module->removeTrailingEmptyParagraphs($content));
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

    public function test_preserves_non_empty_trailing_paragraph(): void
    {
        $content = <<<'HTML'
<!-- wp:paragraph -->
<p>Hello world</p>
<!-- /wp:paragraph -->
HTML;

        $this->assertSame($content, $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function test_does_not_strip_other_empty_block_types(): void
    {
        $content = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->

<!-- wp:spacer -->
<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
HTML;

        $this->assertSame($content, $this->module->removeTrailingEmptyParagraphs($content));
    }

    public function test_strips_trailing_empty_paragraphs_after_non_paragraph_block(): void
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

    public function test_handles_empty_content(): void
    {
        $this->assertSame('', $this->module->removeTrailingEmptyParagraphs(''));
    }

    public function test_handles_content_that_is_only_empty_paragraphs(): void
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

    public function test_strips_whitespace_only_paragraph(): void
    {
        $content = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>   </p>
<!-- /wp:paragraph -->
HTML;

        $expected = <<<'HTML'
<!-- wp:paragraph -->
<p>Content</p>
<!-- /wp:paragraph -->
HTML;

        $this->assertSame($expected, $this->module->removeTrailingEmptyParagraphs($content));
    }
}
