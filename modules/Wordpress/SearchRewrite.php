<?php

namespace Sitchco\Modules\Wordpress;

use Sitchco\Framework\Module;

class SearchRewrite extends Module
{
    const FEATURES = ['redirect', 'compatibility'];

    /**
     * The search query string.
     */
    protected string $query = '/?s=';

    /**
     * The search slug.
     */
    protected string $slug = '/search/';

    /**
     * Redirect query string search results to the search slug.
     */
    public function redirect(): void
    {
        add_action('template_redirect', function () {
            global $wp_rewrite;

            if (
                !isset($_SERVER['REQUEST_URI']) ||
                !isset($wp_rewrite) ||
                !is_object($wp_rewrite) ||
                !$wp_rewrite->get_search_permastruct()
            ) {
                return;
            }

            $request = wp_unslash(filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL));

            if (
                is_search() &&
                !str_contains($request, '/' . $wp_rewrite->search_base . '/') &&
                !str_contains($request, '&')
            ) {
                wp_safe_redirect(get_search_link());
                exit();
            }
        });
    }

    /**
     * Handle compatibility with third-party plugins.
     */
    public function compatibility(): void
    {
        $this->handleYoastSeo();
    }

    /**
     * Handle Yoast SEO compatibility.
     */
    protected function handleYoastSeo(): void
    {
        add_filter('wpseo_json_ld_search_url', [$this, 'rewriteUrl']);
    }

    /**
     * Rewrite the search query string to a slug.
     *
     * @param string $url
     * @return array|string
     */
    public function rewriteUrl(string $url): array|string
    {
        return str_replace($this->getQuery(), $this->getSlug(), $url);
    }

    /**
     * Get the search query string.
     */
    protected function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Get the search slug.
     */
    protected function getSlug(): string
    {
        return $this->slug;
    }
}
