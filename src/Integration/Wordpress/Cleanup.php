<?php

namespace Sitchco\Integration\Wordpress;

use Sitchco\Framework\Core\Module;

/**
 * Class Cleanup
 *
 * This class provides methods to clean up and optimize WordPress functionality.
 *
 * @package Sitchco\Integration\Wordpress
 */
class Cleanup extends Module
{
    /** @var array<string> List of features supported by this class. */
    public const FEATURES = [
        'obscurity',
        'cleanHtmlMarkup',
        'cleanLoginPage',
        'disableEmojis',
        'disableExtraRss',
        'disableGalleryCss',
        'disableXmlRpc',
        'disableFeeds',
        'disableDefaultPosts',
        'disableComments',
        'disableLanguageDropdown',
        'disableWordPressVersion',
        'disableRestEndpoints',
        'disableJpegCompression',
        'disableGutenbergStyles',
        'disableScriptVersion',
        'disableDefaultBlockPatterns',
    ];

    /**
     * Obscure and suppress WordPress information.
     *
     * @return void
     */
    public function obscurity(): void
    {
        foreach (
            [
                'adjacent_posts_rel_link_wp_head',
                'rest_output_link_wp_head',
                'rsd_link',
                'wlwmanifest_link',
                'wp_generator',
                'wp_oembed_add_discovery_links',
                'wp_oembed_add_host_js',
                'wp_shortlink_wp_head',
            ] as $hook
        ) {
            remove_filter('wp_head', $hook);
        }

        add_filter('get_bloginfo_rss', fn($value) => $value !== __('Just another WordPress site') ? $value : '');
        add_filter('the_generator', '__return_false');
    }

    /**
     * Clean HTML5 markup.
     *
     * @return void
     */
    public function cleanHtmlMarkup(): void
    {
        add_filter('body_class', [$this, 'cleanBodyClass']);
        add_filter('language_attributes', function (): string {
            $attributes = [];

            if (is_rtl()) {
                $attributes[] = 'dir="rtl"';
            }

            $lang = esc_attr(get_bloginfo('language'));

            if ($lang) {
                $attributes[] = "lang=\"{$lang}\"";
            }

            return implode(' ', $attributes);
        });

        add_filter('wp_targeted_link_rel', function ($values) {
            return preg_replace('/noreferrer\s*/i', '', $values);
        });

        foreach (
            [
                'get_avatar',
                'comment_id_fields',
                'post_thumbnail_html',
            ] as $hook
        ) {
            add_filter($hook, [$this, 'removeSelfClosingTags']);
        }

        add_filter('site_icon_meta_tags', fn($tags) => array_map([$this, 'removeSelfClosingTags'], $tags), 20);
    }

    /**
     * Update login page image link URL and title.
     *
     * @return void
     */
    public function cleanLoginPage(): void
    {
        add_filter('login_headerurl', fn() => home_url());
        add_filter('login_headertext', fn() => get_bloginfo('name'));
    }

    /**
     * Disable WordPress emojis.
     *
     * @return void
     */
    public function disableEmojis(): void
    {
        add_filter('emoji_svg_url', '__return_false');
        remove_filter('wp_head', 'print_emoji_detection_script', 7);

        foreach (
            [
                'admin_print_scripts' => 'print_emoji_detection_script',
                'wp_print_styles' => 'print_emoji_styles',
                'admin_print_styles' => 'print_emoji_styles',
                'the_content_feed' => 'wp_staticize_emoji',
                'comment_text_rss' => 'wp_staticize_emoji',
                'wp_mail' => 'wp_staticize_emoji_for_email',
            ] as $hook => $function
        ) {
            remove_filter($hook, $function);
        }
    }

    /**
     * Disable extra RSS feeds.
     *
     * @return void
     */
    public function disableExtraRss(): void
    {
        add_filter('feed_links_show_comments_feed', '__return_false');
        remove_filter('wp_head', 'feed_links_extra', 3);
    }

    /**
     * Disable gallery CSS.
     *
     * @return void
     */
    public function disableGalleryCss(): void
    {
        add_filter('use_default_gallery_style', '__return_false');
    }

    /**
     * Disable XML-RPC.
     *
     * @return void
     */
    public function disableXmlRpc(): void
    {
        add_filter('xmlrpc_enabled', '__return_false');
    }

    /**
     * Disable all RSS feeds by redirecting them to the homepage.
     *
     * @return void
     */
    public function disableFeeds(): void
    {
        add_action('do_feed', [$this, 'disableFeedsRedirect'], 1);
        add_action('do_feed_rdf', [$this, 'disableFeedsRedirect'], 1);
        add_action('do_feed_rss', [$this, 'disableFeedsRedirect'], 1);
        add_action('do_feed_rss2', [$this, 'disableFeedsRedirect'], 1);
        add_action('do_feed_atom', [$this, 'disableFeedsRedirect'], 1);
        add_action('do_feed_rss2_comments', [$this, 'disableFeedsRedirect'], 1);
        add_action('do_feed_atom_comments', [$this, 'disableFeedsRedirect'], 1);
    }

    /**
     * Disable default posts.
     *
     * @return void
     */
    public function disableDefaultPosts(): void
    {
        add_action('admin_menu', function () {
            remove_menu_page('edit.php');
        });
    }

    /**
     * Disable comments globally.
     *
     * @return void
     */
    public function disableComments(): void
    {
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        add_filter('show_recent_comments_widget_style', '__return_false');
        remove_action('admin_init', 'wp_comments_require_registration');
        add_action('admin_menu', function () {
            remove_menu_page('edit-comments.php');
        });
    }

    /**
     * Remove language dropdown on login screen.
     *
     * @return void
     */
    public function disableLanguageDropdown(): void
    {
        add_filter('login_display_language_dropdown', '__return_false');
    }

    /**
     * Remove WordPress version from various sources.
     *
     * @return void
     */
    public function disableWordPressVersion(): void
    {
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_false');
    }

    /**
     * Disable REST API endpoints for users when not logged in.
     *
     * @return void
     */
    public function disableRestEndpoints(): void
    {
        add_filter('rest_endpoints', function (array $endpoints): array {
            if (!is_user_logged_in()) {
                unset($endpoints['/wp/v2/users']);
                unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
            }

            return $endpoints;
        });
    }

    /**
     * Remove JPEG compression by setting quality to 100.
     *
     * @return void
     */
    public function disableJpegCompression(): void
    {
        add_filter('jpeg_quality', '__return_true');
    }

    /**
     * Remove Gutenberg's core and global styles.
     *
     * @return void
     */
    public function disableGutenbergStyles(): void
    {
        add_action('wp_enqueue_scripts', function (): void {
            wp_deregister_style('wp-block-library');
            wp_deregister_style('wp-block-library-theme');
        }, 200);
//        add_action('wp_enqueue_scripts', function(): void {
//            wp_dequeue_style('global-styles');
//        });
        add_action('wp_footer', fn() => wp_dequeue_style('core-block-supports'), 5);
        add_action('wp_enqueue_scripts', fn() => wp_dequeue_style('classic-theme-styles'));
    }

    /**
     * Disable default block patterns.
     *
     * @return void
     */
    public function disableDefaultBlockPatterns(): void
    {
        add_action('init', function () {
            // Remove 95% of patterns (everything but posts)
            add_filter('should_load_remote_block_patterns', '__return_false');

            // Remove remaining 5% (posts)
            $registry = \WP_Block_Patterns_Registry::get_instance();
            foreach ($registry->get_all_registered() as $pattern) {
                unregister_block_pattern($pattern['name']);
            }
        });
    }

    /**
     * Remove ?ver= query from styles and scripts.
     *
     * @return void
     */
    public function disableScriptVersion(): void
    {
        add_filter('script_loader_src', [$this, 'removeVersionArg'], 15, 1);
        add_filter('style_loader_src', [$this, 'removeVersionArg'], 15, 1);
    }

    // FEATURE UTILITY METHODS

    /**
     * Add and remove body_class() classes.
     *
     * @param array $classes
     * @param array $disallowedClasses
     *
     * @return array
     */
    public function cleanBodyClass(array $classes, array $disallowedClasses = ['page-template-default']): array
    {
        if (is_single() || (is_page() && !is_front_page())) {
            $slug = basename(get_permalink());
            if (!in_array($slug, $classes, true)) {
                $classes[] = $slug;
            }
        }

        if (is_front_page()) {
            $disallowedClasses[] = 'page-id-' . get_option('page_on_front');
        }

        return array_values(array_diff($classes, $disallowedClasses));
    }

    /**
     * Remove the 'ver' query parameter from enqueued scripts and styles.
     *
     * @param string $url
     *
     * @return string
     */
    public function removeVersionArg(string $url): string
    {
        if (is_admin()) {
            return $url;
        }

        if ($url) {
            return esc_url(remove_query_arg('ver', $url));
        }

        return $url;
    }

    /**
     * Redirect feed requests to the homepage.
     *
     * @return void
     */
    public function disableFeedsRedirect(): void
    {
        wp_redirect(home_url());
        exit;
    }

    /**
     * Remove self-closing tags.
     *
     * @param array|string $html
     *
     * @return array|string
     */
    public function removeSelfClosingTags(array|string $html): array|string
    {
        return is_array($html) ? array_map([$this, 'removeSelfClosingTags'], $html) : str_replace(' />', '>', $html);
    }
}