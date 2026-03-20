<?php

namespace Sitchco\Tests\Modules\ContentTargeting;

use Sitchco\Modules\ContentTargeting\ContentTargeting;
use Sitchco\Tests\TestCase;

class ContentTargetingTest extends TestCase
{
    private ContentTargeting $contentTargeting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contentTargeting = $this->container->get(ContentTargeting::class);
    }

    protected function tearDown(): void
    {
        $this->resetQueryFlags();
        parent::tearDown();
    }

    private function setQueriedObject($object, int $id = 0): void
    {
        $GLOBALS['wp_query']->queried_object = $object;
        $GLOBALS['wp_query']->queried_object_id = $id;
    }

    private function setSingular(int $postId): void
    {
        $post = get_post($postId);
        $this->setQueriedObject($post, $postId);
        $GLOBALS['wp_query']->is_singular = true;
        $GLOBALS['wp_query']->is_page = true;
    }

    private function setHome(): void
    {
        $this->resetQueryFlags();
        $GLOBALS['wp_query']->is_home = true;
    }

    private function setSearch(): void
    {
        $this->resetQueryFlags();
        $GLOBALS['wp_query']->is_search = true;
    }

    private function setAuthorArchive(): void
    {
        $this->resetQueryFlags();
        $GLOBALS['wp_query']->is_author = true;
    }

    private function setPostTypeArchive(string $postType): void
    {
        $this->resetQueryFlags();
        $GLOBALS['wp_query']->is_post_type_archive = true;
        $GLOBALS['wp_query']->query_vars['post_type'] = $postType;
        $GLOBALS['wp_query']->set('post_type', $postType);
    }

    private function setCategoryArchive(): void
    {
        $this->resetQueryFlags();
        $GLOBALS['wp_query']->is_category = true;
        $GLOBALS['wp_query']->is_archive = true;
    }

    private function setTagArchive(): void
    {
        $this->resetQueryFlags();
        $GLOBALS['wp_query']->is_tag = true;
        $GLOBALS['wp_query']->is_archive = true;
    }

    private function resetQueryFlags(): void
    {
        $GLOBALS['wp_query']->is_singular = false;
        $GLOBALS['wp_query']->is_page = false;
        $GLOBALS['wp_query']->is_single = false;
        $GLOBALS['wp_query']->is_home = false;
        $GLOBALS['wp_query']->is_search = false;
        $GLOBALS['wp_query']->is_author = false;
        $GLOBALS['wp_query']->is_post_type_archive = false;
        $GLOBALS['wp_query']->is_category = false;
        $GLOBALS['wp_query']->is_tag = false;
        $GLOBALS['wp_query']->is_tax = false;
        $GLOBALS['wp_query']->is_archive = false;
        $GLOBALS['wp_query']->queried_object = null;
        $GLOBALS['wp_query']->queried_object_id = 0;
    }

    public function test_empty_config_matches_everything(): void
    {
        $page = $this->factory()->post->create(['post_type' => 'page']);
        $this->setSingular($page);

        $this->assertTrue($this->contentTargeting->matchesCurrentRequest([]));
        $this->assertTrue(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [],
                'archives' => [],
            ]),
        );
    }

    public function test_include_mode_matches_selected_page(): void
    {
        $page = $this->factory()->post->create(['post_type' => 'page']);
        $this->setSingular($page);

        $this->assertTrue(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [$page],
                'archives' => [],
            ]),
        );
    }

    public function test_include_mode_does_not_match_other_page(): void
    {
        $targetPage = $this->factory()->post->create(['post_type' => 'page']);
        $otherPage = $this->factory()->post->create(['post_type' => 'page']);
        $this->setSingular($otherPage);

        $this->assertFalse(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [$targetPage],
                'archives' => [],
            ]),
        );
    }

    public function test_exclude_mode_does_not_match_excluded_page(): void
    {
        $page = $this->factory()->post->create(['post_type' => 'page']);
        $this->setSingular($page);

        $this->assertFalse(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'exclude',
                'pages' => [$page],
                'archives' => [],
            ]),
        );
    }

    public function test_exclude_mode_matches_non_excluded_page(): void
    {
        $excludedPage = $this->factory()->post->create(['post_type' => 'page']);
        $otherPage = $this->factory()->post->create(['post_type' => 'page']);
        $this->setSingular($otherPage);

        $this->assertTrue(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'exclude',
                'pages' => [$excludedPage],
                'archives' => [],
            ]),
        );
    }

    public function test_include_posts_index_matches_home(): void
    {
        $this->setHome();

        $this->assertTrue(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [],
                'archives' => ['posts_index'],
            ]),
        );
    }

    public function test_include_posts_index_does_not_match_search(): void
    {
        $this->setSearch();

        $this->assertFalse(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [],
                'archives' => ['posts_index'],
            ]),
        );
    }

    public function test_include_search_results_matches_search(): void
    {
        $this->setSearch();

        $this->assertTrue(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [],
                'archives' => ['search_results'],
            ]),
        );
    }

    public function test_include_author_archive_matches_author(): void
    {
        $this->setAuthorArchive();

        $this->assertTrue(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [],
                'archives' => ['author_archive'],
            ]),
        );
    }

    public function test_exclude_archive_does_not_match_excluded_archive(): void
    {
        $this->setHome();

        $this->assertFalse(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'exclude',
                'pages' => [],
                'archives' => ['posts_index'],
            ]),
        );
    }

    public function test_include_category_archive_matches_category(): void
    {
        $this->setCategoryArchive();

        $this->assertTrue(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [],
                'archives' => ['taxonomy_archive:category'],
            ]),
        );
    }

    public function test_include_tag_archive_matches_tag(): void
    {
        $this->setTagArchive();

        $this->assertTrue(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [],
                'archives' => ['taxonomy_archive:post_tag'],
            ]),
        );
    }

    public function test_combined_pages_and_archives_include(): void
    {
        $page = $this->factory()->post->create(['post_type' => 'page']);

        $config = [
            'mode' => 'include',
            'pages' => [$page],
            'archives' => ['posts_index'],
        ];

        $this->setSingular($page);
        $this->assertTrue($this->contentTargeting->matchesCurrentRequest($config));

        $this->setHome();
        $this->assertTrue($this->contentTargeting->matchesCurrentRequest($config));

        $this->setSearch();
        $this->assertFalse($this->contentTargeting->matchesCurrentRequest($config));
    }

    public function test_singular_page_does_not_match_archive_targets(): void
    {
        $page = $this->factory()->post->create(['post_type' => 'page']);
        $this->setSingular($page);

        $this->assertFalse(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [],
                'archives' => ['posts_index', 'search_results'],
            ]),
        );
    }

    public function test_archive_view_does_not_match_page_targets(): void
    {
        $page = $this->factory()->post->create(['post_type' => 'page']);
        $this->setHome();

        $this->assertFalse(
            $this->contentTargeting->matchesCurrentRequest([
                'mode' => 'include',
                'pages' => [$page],
                'archives' => [],
            ]),
        );
    }
}
