<?php
/**
 * Event Module Example
 *
 * Demonstrates custom post type registration with Timber integration,
 * repository pattern, and admin customizations.
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
     */
    public function registerPostType(): void
    {
        register_post_type('event', [
            'labels' => [
                'name' => 'Events',
                'singular_name' => 'Event',
                'add_new' => 'Add New Event',
                'add_new_item' => 'Add New Event',
                'edit_item' => 'Edit Event',
                'new_item' => 'New Event',
                'view_item' => 'View Event',
                'search_items' => 'Search Events',
                'not_found' => 'No events found',
                'not_found_in_trash' => 'No events found in trash',
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon' => 'dashicons-calendar-alt',
            'rewrite' => ['slug' => 'events'],
            'show_in_rest' => true, // Enable Gutenberg editor
        ]);
    }

    /**
     * Register Event Category taxonomy
     */
    public function registerTaxonomy(): void
    {
        register_taxonomy('event_category', 'event', [
            'labels' => [
                'name' => 'Event Categories',
                'singular_name' => 'Event Category',
                'search_items' => 'Search Categories',
                'all_items' => 'All Categories',
                'edit_item' => 'Edit Category',
                'update_item' => 'Update Category',
                'add_new_item' => 'Add New Category',
                'new_item_name' => 'New Category Name',
            ],
            'hierarchical' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'event-category'],
        ]);
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
