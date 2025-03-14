<?php

namespace Sitchco\Integration;

use Sitchco\Framework\Core\Module;

/**
 * Class AmazonCloudfront
 * @package Sitchco\Integration
 *
 * TODO: make this an env thing?
 */
class AmazonCloudfront extends Module
{
    public function init(): void
    {
        add_filter('user_can_richedit', [$this, 'enableAmazonCloudfrontUserAgent']);
    }

    public function enableAmazonCloudfrontUserAgent($wp_rich_edit): bool
    {
        if (! $wp_rich_edit
            && (get_user_option('rich_editing') == 'true' || ! is_user_logged_in())
            && isset($_SERVER['HTTP_USER_AGENT'])
            && stripos($_SERVER['HTTP_USER_AGENT'], 'amazon cloudfront') !== false
        ) {
            return true;
        }

        return $wp_rich_edit;
    }
}