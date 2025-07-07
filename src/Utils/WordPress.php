<?php

declare(strict_types=1);

namespace Sitchco\Utils;

use WP_Post;

/**
 * Class WordPress
 * @package Sitchco\Utils
 */
class WordPress
{
    /**
     * Generate random posts with specified arguments.
     *
     * @param array|null $args Optional arguments to customize the post generation.
     * @return void
     */
    public function generateRandomPosts(array $args = null): void
    {
        $defaults = [
            'number' => 20,
            'min_title' => 4,
            'max_title' => 15,
            'min_body' => 0,
            'max_body' => 3,
            'body_paragraphs' => 5,
            'headers' => false,
            'lists' => false,
            'plaintext' => false,
            'post_type' => 'post',
            'rand_cats' => true,
        ];

        $args = wp_parse_args($args, $defaults);
        extract($args, EXTR_SKIP);

        $params = $plaintext ? 'plaintext' : 'decorate link';
        $params .= $headers ? ' headers' : '';
        $params .= $lists ? ' ul ol bq' : '';
        $params .= ' prude';

        $cat_list = [];
        if ($post_type === 'post' && $rand_cats) {
            $categories = get_categories();
            foreach ($categories as $category) {
                $cat_list[] = $category->term_id;
            }
        }

        $my_posts = [];
        $length = ['short', 'medium', 'long', 'longer'];
        for ($i = 1; $i <= $number; $i++) {
            $my_posts[] = [
                'post_title' => Str::getLastWords(
                    Str::placeholderText('1 long plaintext prude'),
                    rand($min_title, $max_title)
                ),
                'post_content' => trim(
                    Str::placeholderText(
                        rand(2, $body_paragraphs) . ' ' . $length[rand($min_body, $max_body)] . ' ' . $params
                    )
                ),
                'post_status' => 'publish',
                'post_type' => $post_type,
                'post_category' => !empty($cat_list) ? [$cat_list[rand(0, count($cat_list) - 1)]] : null,
            ];
        }

        foreach ($my_posts as $my_post) {
            wp_insert_post($my_post);
        }
    }

    /**
     * Get an excerpt of the post content.
     *
     * @param WP_Post $post The post object.
     * @param int $num_words The number of words to limit the excerpt to.
     * @param bool $raw Whether to return the raw excerpt or a filtered version.
     * @return string The post excerpt.
     */
    public static function excerpt(WP_Post $post, int $num_words = 0, bool $raw = false): string
    {
        $excerpt_length = $num_words ?: (int) apply_filters('excerpt_length', 20);
        $raw_excerpt = $post->post_excerpt ?: $post->post_content;
        $text = strip_shortcodes($raw_excerpt);

        if (empty($text) && !empty($raw_excerpt)) {
            $text = strip_tags(do_shortcode($raw_excerpt));
        }

        if (empty($GLOBALS['pages'])) {
            $GLOBALS['pages'] = [''];
        }
        if (empty($GLOBALS['page'])) {
            $GLOBALS['page'] = 1;
        }

        $global_post = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = (object) ['post_content' => ''];

        if (!$raw) {
            $text = apply_filters('the_content', $text);
        }

        $text = str_replace(']]>', ']]&gt;', $text);
        $excerpt_more = $raw ? '' : apply_filters('excerpt_more', '');
        $text = wp_trim_words($text, $excerpt_length, $excerpt_more);

        if (!$raw) {
            $text = apply_filters('get_the_excerpt', $text, $post);
        }

        $GLOBALS['post'] = $global_post;

        return $text;
    }

    /**
     * Filter content by applying WordPress text filters.
     *
     * @param string $str The content to filter.
     * @return string The filtered content.
     */
    public static function filterContent(string $str): string
    {
        return wpautop(wptexturize($str));
    }

    /**
     * Retrieve all post types that are likely to have a single/detail view on the front end.
     *
     * This function filters and returns an array of post types that are public, queryable,
     * and have rewrite rules defined. It includes both built-in and custom post types.
     *
     * @return string[] An array of post type names that are publicly queryable and likely to have a detail view.
     */
    public static function getVisibleSinglePostTypes(): array
    {
        $built_in = array_diff(get_post_types(['public' => true, '_builtin' => true]), ['attachment']);
        $custom_types = array_filter(
            get_post_types(['public' => true, 'publicly_queryable' => true, '_builtin' => false], 'objects') ?: [],
            fn($post_type) => !empty($post_type->rewrite)
        );

        $all_types = array_values(
            array_unique(array_merge($built_in, array_map(fn($post_type) => $post_type->name, $custom_types)))
        );

        return array_filter($all_types, function ($post_type) {
            $count = array_filter((array) wp_count_posts($post_type));
            $count = array_intersect_key($count, array_flip(['publish', 'future', 'draft', 'pending', 'private']));

            return !empty($count);
        });
    }
}
