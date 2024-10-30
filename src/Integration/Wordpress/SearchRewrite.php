<?php

namespace Sitchco\Integration\Wordpress;

use Sitchco\Framework\Core\AbstractModule;

class SearchRewrite extends AbstractModule
{
    //    public const NAME = 'search-rewrite';
    //    public const ENABLED = true;

    /**
     * The search query string.
     */
    protected $query = '/?s=';

    /**
     * The search slug.
     */
    protected $slug = '/search/';

    /**
     * Constructor to initialize hooks.
     */
    public function __construct()
    {
        $this->handleRedirect();
        $this->handleCompatibility();
    }

    /**
     * Redirect query string search results to the search slug.
     */
    protected function handleRedirect()
    {
        add_action('template_redirect', function () {
            global $wp_rewrite;

            if (
                ! isset($_SERVER['REQUEST_URI']) ||
                ! isset($wp_rewrite) ||
                ! is_object($wp_rewrite) ||
                ! $wp_rewrite->get_search_permastruct()
            ) {
                return;
            }

            $request = wp_unslash(filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL));

            if (
                is_search() &&
                strpos($request, '/' . $wp_rewrite->search_base . '/') === false &&
                strpos($request, '&') === false
            ) {
                wp_safe_redirect(get_search_link());
                exit;
            }
        });
    }

    /**
     * Handle compatibility with third-party plugins.
     */
    protected function handleCompatibility()
    {
        $this->handleYoastSeo();
    }

    /**
     * Handle Yoast SEO compatibility.
     */
    protected function handleYoastSeo()
    {
        add_filter('wpseo_json_ld_search_url', [$this, 'rewriteUrl']);
    }

    /**
     * Rewrite the search query string to a slug.
     */
    public function rewriteUrl($url)
    {
        return str_replace($this->getQuery(), $this->getSlug(), $url);
    }

    /**
     * Get the search query string.
     */
    protected function getQuery()
    {
        return $this->query;
    }

    /**
     * Get the search slug.
     */
    protected function getSlug()
    {
        return $this->slug;
    }
}