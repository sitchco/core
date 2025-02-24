<?php

namespace Sitchco\Integration\WPRocket;

use Sitchco\Framework\Core\Module;

/**
 * class WPRocket
 * @package Sitchco\Integration\WPRocket
 */
class WPRocket extends Module
{
    public function init(): void
    {
        add_filter('before_rocket_htaccess_rules', [$this, 'forceTrailingSlash']);
        add_filter('rocket_metabox_options_post_types', '__return_empty_array');

        // Batch size: Reduce the number of URLs per batch for better performance.
        add_filter('rocket_preload_cache_pending_jobs_cron_rows_count', fn(): int => 25);

        // Cron interval: Set a higher delay between batch processing.
        add_filter('rocket_preload_pending_jobs_cron_interval', fn(): int => 120);

        // Delay between requests: Increase delay to reduce CPU usage.
        add_filter('rocket_preload_delay_between_requests', fn(): int => 2_000_000);

        // Remove Unused CSS: Adjust batch processing size.
        add_filter('rocket_rucss_pending_jobs_cron_rows_count', fn(): int => 25);

        // Increase cron interval for unused CSS processing.
        add_filter('rocket_rucss_pending_jobs_cron_interval', fn(): int => 300);
    }

    public function forceTrailingSlash(string $marker): string
    {
        $protocol = is_ssl() ? 'https' : 'http';

        return <<<HTACCESS
        # Force trailing slash
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_METHOD} GET
        RewriteCond %{REQUEST_URI} !(.*)/$
        RewriteCond %{REQUEST_URI} !^/wp-(content|admin|includes)
        RewriteCond %{REQUEST_FILENAME} !\.(gif|jpg|png|jpeg|css|xml|txt|js|php|scss|webp|mp3|avi|wav|mp4|mov)$ [NC]
        RewriteRule ^(.*)$ {$protocol}://%{HTTP_HOST}/$1/ [L,R=301]

        {$marker}
        HTACCESS;
    }
}
