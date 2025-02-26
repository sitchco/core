<?php

namespace Sitchco\Integration\Wordpress;

use Sitchco\Framework\Core\Module;

/**
 * class Cleanup
 * @package Sitchco\Integration\Wordpress
 */
class Cleanup extends Module
{
    public const FEATURES = [
        'obscurity',
        'cleanHtmlMarkup',
        'disableEmojis',
        'disableGutenbergBlockCss',
        'disableExtraRss',
        'disableRecentCommentsCss',
        'disableGalleryCss',
        'disableXmlRpc',
        'disableFeeds',
        'disableDefaultPosts',
        'disableComments',
        'removeLanguageDropdown',
        'removeWordPressVersion',
        'disableRestEndpoints',
        'removeJpegCompression',
        'updateLoginPage',
        'removeGutenbergStyles',
        'removeScriptVersion',
        'removeDefaultBlockPatterns',
    ];

    /**
     * Obscure and suppress WordPress information.
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
     */
    public function cleanHtmlMarkup(): void
    {
        add_filter('body_class', [$this, 'bodyClass']);
        add_filter('language_attributes', [$this, 'languageAttributes']);

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
     * Add and remove body_class() classes.
     *
     * @param array $classes
     * @param array $disallowedClasses
     *
     * @return array
     */
    public function bodyClass(array $classes, array $disallowedClasses = ['page-template-default']): array
    {
        if (is_single() || (is_page() && ! is_front_page())) {
            $slug = basename(get_permalink());
            if (! in_array($slug, $classes, true)) {
                $classes[] = $slug;
            }
        }

        if (is_front_page()) {
            $disallowedClasses[] = 'page-id-' . get_option('page_on_front');
        }

        return array_values(array_diff($classes, $disallowedClasses));
    }

    /**
     * Clean up language_attributes() used in <html> tag.
     * @return string
     */
    public function languageAttributes(): string
    {
        $attributes = [];

        if (is_rtl()) {
            $attributes[] = 'dir="rtl"';
        }

        $lang = esc_attr(get_bloginfo('language'));

        if ($lang) {
            $attributes[] = "lang=\"{$lang}\"";
        }

        return implode(' ', $attributes);
    }

    /**
     * Remove self-closing tags.
     *
     * @param string|array $html
     *
     * @return string|array
     */
    public function removeSelfClosingTags($html): array|string
    {
        return is_array($html) ? array_map([$this, 'removeSelfClosingTags'], $html) : str_replace(' />', '>', $html);
    }

    /**
     * Disable WordPress emojis.
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
     * Disable Gutenberg block library CSS.
     */
    public function disableGutenbergBlockCss(): void
    {
        add_action('wp_enqueue_scripts', function () {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
        }, 200);
    }

    /**
     * Disable extra RSS feeds.
     */
    public function disableExtraRss(): void
    {
        add_filter('feed_links_show_comments_feed', '__return_false');
        remove_filter('wp_head', 'feed_links_extra', 3);
    }

    /**
     * Disable recent comments CSS.
     */
    public function disableRecentCommentsCss(): void
    {
        add_filter('show_recent_comments_widget_style', '__return_false');
    }

    /**
     * Disable gallery CSS.
     */
    public function disableGalleryCss(): void
    {
        add_filter('use_default_gallery_style', '__return_false');
    }

    /**
     * Disable XML-RPC.
     */
    public function disableXmlRpc(): void
    {
        add_filter('xmlrpc_enabled', '__return_false');
    }

    /**
     * Disable all RSS feeds by redirecting them to the homepage.
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
     * Redirect feed requests to the homepage.
     */
    public function disableFeedsRedirect(): void
    {
        wp_redirect(home_url());
        exit;
    }

    public function disableDefaultPosts()
    {
        add_action('admin_menu', function(){
            remove_menu_page('edit.php');
        });
    }

    /**
     * Disable comments globally.
     */
    public function disableComments(): void
    {
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        remove_action('admin_init', 'wp_comments_require_registration');
        add_action('admin_menu', function () {
            remove_menu_page('edit-comments.php');
        });
    }

    /**
     * Remove language dropdown on login screen.
     */
    public function removeLanguageDropdown(): void
    {
        add_filter('login_display_language_dropdown', '__return_false');
    }

    /**
     * Remove WordPress version from various sources.
     */
    public function removeWordPressVersion(): void
    {
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_false');
    }


    /**
     * Disable REST API endpoints for users when not logged in.
     */
    public function disableRestEndpoints(): void
    {
        add_filter('rest_endpoints', [$this, 'disableRestEndpointsForUsers']);
    }

    /**
     * Disable REST API endpoints for user data if not logged in.
     *
     * @param array $endpoints
     *
     * @return array
     */
    public function disableRestEndpointsForUsers(array $endpoints): array
    {
        if (! is_user_logged_in()) {
            unset($endpoints['/wp/v2/users']);
            unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        }

        return $endpoints;
    }

    /**
     * Remove JPEG compression by setting quality to 100.
     */
    public function removeJpegCompression(): void
    {
        add_filter('jpeg_quality', '__return_true'); // 'true' sets it to 100
    }

    /**
     * Update login page image link URL and title.
     */
    public function updateLoginPage(): void
    {
        add_filter('login_headerurl', [$this, 'loginUrl']);
        add_filter('login_headertext', [$this, 'loginTitle']);
    }

    /**
     * Update the login header URL to the site's home URL.
     * @return string
     */
    public function loginUrl(): string
    {
        return home_url();
    }

    /**
     * Update the login header title to the site's name.
     * @return string
     */
    public function loginTitle(): string
    {
        return get_bloginfo('name');
    }

    /**
     * Remove Gutenberg's core and global styles.
     */
    public function removeGutenbergStyles(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'removeBlockStyles']);
        add_action('wp_enqueue_scripts', [$this, 'removeGlobalStyles']);
        add_action('wp_footer', [$this, 'removeCoreBlockSupports'], 5);
        add_action('wp_enqueue_scripts', [$this, 'removeClassicThemeStyles']);
    }

    /**
     * Deregister Gutenberg block styles.
     */
    public function removeBlockStyles(): void
    {
        wp_deregister_style('wp-block-library');
        wp_deregister_style('wp-block-library-theme');
    }

    /**
     * Deregister core block supports.
     */
    public function removeCoreBlockSupports(): void
    {
        wp_dequeue_style('core-block-supports');
    }

    /**
     * Deregister Gutenberg global styles.
     */
    public function removeGlobalStyles(): void
    {
        //        wp_dequeue_style('global-styles');
    }

    /**
     * Deregister classic theme styles.
     */
    public function removeClassicThemeStyles(): void
    {
        wp_dequeue_style('classic-theme-styles');
    }

    /**
     * Remove ?ver= query from styles and scripts.
     */
    public function removeScriptVersion(): void
    {
        add_filter('script_loader_src', [$this, 'removeVersionArg'], 15, 1);
        add_filter('style_loader_src', [$this, 'removeVersionArg'], 15, 1);
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

    public function removeDefaultBlockPatterns(): void
    {
        add_action('init', function() {
            // will remove 95% of patterns (everything but posts)
            add_filter('should_load_remote_block_patterns', '__return_false');

            // will remove remaining 5% (posts)
            $registry = \WP_Block_Patterns_Registry::get_instance();
            foreach($registry->get_all_registered() as $pattern) {
                unregister_block_pattern($pattern['name']);
            }
        });
    }
}
