<?php
/**
 * Event Module Example
 *
 * Demonstrates custom post type setup with Timber integration,
 * repository pattern, and admin customizations.
 *
 * Note: Post type and taxonomy registration are handled through ACF Pro UI.
 */

namespace Sitchco\App\Modules\Event;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\Model\PostModel as TimberModule;

class EventModule extends Module
{
    /**
     * Module dependencies
     *
     * This module requires TimberModule for custom post class integration.
     */
    public const DEPENDENCIES = [TimberModule::class];

    /**
     * Optional features
     *
     * Features can be enabled/disabled via sitchco.config.php
     */
    public const FEATURES = ['customAdminColumn', 'emailNotifications'];

    /**
     * Timber post classes
     *
     * Register custom post class with Timber
     */
    public const POST_CLASSES = [EventPost::class];

    /**
     * Constructor - Dependency Injection
     *
     * The DI container will automatically inject the EventRepository
     */
    public function __construct(private EventRepository $repository) {}

    /**
     * Module initialization
     *
     * IMPORTANT: This is called during after_setup_theme at priority 5,
     * NOT during WordPress's 'init' hook. Use this to REGISTER hooks.
     */
    public function init(): void
    {
        // Register for WordPress's 'init' hook (fires later at priority 10)
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerTaxonomy']);

        // Frontend assets
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            // Load only on event pages
            if (is_singular('event') || is_post_type_archive('event')) {
                $assets->style('event.css');
                $assets->script('event.js', dependencies: ['jquery']);
            }
        });

        // Admin assets
        $this->enqueueAdminAssets(function (ModuleAssets $assets) {
            $assets->style('admin.css');
            $assets->script('admin.js', dependencies: ['jquery']);
        });
    }

    /**
     * Register the Event post type
     *
     * Note: Post type registration is handled through ACF Pro UI.
     * This method can be used for additional setup if needed.
     */
    public function registerPostType(): void
    {
        // Post type registration handled through ACF Pro UI
        // No PHP registration needed - ACF Pro handles this
        // This method can be used for additional setup if needed
    }

    /**
     * Register Event Category taxonomy
     *
     * Note: Taxonomy registration is handled through ACF Pro UI.
     * This method can be used for additional setup if needed.
     */
    public function registerTaxonomy(): void
    {
        // Taxonomy registration handled through ACF Pro UI
        // No PHP registration needed - ACF Pro handles this
        // This method can be used for additional setup if needed
    }

    /**
     * Feature: Custom Admin Column
     *
     * Only runs if enabled in config.
     * Adds an "Event Date" column to the admin list.
     */
    protected function customAdminColumn(): void
    {
        // Add column header
        add_filter('manage_event_posts_columns', function ($columns) {
            // Insert after title
            $newColumns = [];
            foreach ($columns as $key => $value) {
                $newColumns[$key] = $value;
                if ($key === 'title') {
                    $newColumns['event_date'] = 'Event Date';
                    $newColumns['event_location'] = 'Location';
                }
            }
            return $newColumns;
        });

        // Render column content
        add_action(
            'manage_event_posts_custom_column',
            function ($column, $postId) {
                if ($column === 'event_date') {
                    $date = get_field('start_date', $postId);
                    echo $date ? date('M j, Y', strtotime($date)) : '—';
                } elseif ($column === 'event_location') {
                    $location = get_field('location', $postId);
                    echo $location ?: '—';
                }
            },
            10,
            2,
        );

        // Make column sortable
        add_filter('manage_edit-event_sortable_columns', function ($columns) {
            $columns['event_date'] = 'event_date';
            return $columns;
        });

        // Handle sorting
        add_action('pre_get_posts', function ($query) {
            if (!is_admin() || !$query->is_main_query()) {
                return;
            }

            if ($query->get('orderby') === 'event_date') {
                $query->set('meta_key', 'start_date');
                $query->set('orderby', 'meta_value');
            }
        });
    }

    /**
     * Feature: Email Notifications
     *
     * Only runs if enabled in config.
     * Sends email when an event is published.
     */
    protected function emailNotifications(): void
    {
        add_action('publish_event', function ($post) {
            // Don't send for auto-drafts or revisions
            if (wp_is_post_revision($post) || $post->post_status !== 'publish') {
                return;
            }

            // Get event details
            $eventPost = new EventPost($post);
            $date = $eventPost->startDate();
            $location = $eventPost->location();

            // Send email
            $to = get_option('admin_email');
            $subject = 'New Event Published: ' . $post->post_title;
            $message = "A new event has been published:\n\n";
            $message .= "Title: {$post->post_title}\n";
            $message .= "Date: {$date}\n";
            $message .= "Location: {$location}\n";
            $message .= 'URL: ' . get_permalink($post) . "\n";

            wp_mail($to, $subject, $message);
        });
    }
}
