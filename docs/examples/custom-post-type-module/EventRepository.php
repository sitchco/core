<?php
/**
 * Event Repository
 *
 * Data access layer for Event post type.
 * Provides methods for querying and retrieving events.
 */

namespace Sitchco\App\Modules\Event;

class EventRepository
{
    /**
     * Find all events
     *
     * @param array $args Additional query arguments
     * @return array Array of EventPost objects
     */
    public function findAll(array $args = []): array
    {
        $defaults = [
            'post_type' => 'event',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'start_date',
            'order' => 'ASC',
        ];

        $args = array_merge($defaults, $args);

        return \Timber::get_posts($args);
    }

    /**
     * Find upcoming events
     *
     * @param int $limit Number of events to retrieve
     * @return array Array of EventPost objects
     */
    public function findUpcoming(int $limit = 10): array
    {
        return \Timber::get_posts([
            'post_type' => 'event',
            'posts_per_page' => $limit,
            'meta_key' => 'start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
            ],
        ]);
    }

    /**
     * Find past events
     *
     * @param int $limit Number of events to retrieve
     * @return array Array of EventPost objects
     */
    public function findPast(int $limit = 10): array
    {
        return \Timber::get_posts([
            'post_type' => 'event',
            'posts_per_page' => $limit,
            'meta_key' => 'start_date',
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE',
                ],
            ],
        ]);
    }

    /**
     * Find events by category
     *
     * @param string $category Category slug or ID
     * @param int $limit Number of events to retrieve
     * @return array Array of EventPost objects
     */
    public function findByCategory(string $category, int $limit = -1): array
    {
        return \Timber::get_posts([
            'post_type' => 'event',
            'posts_per_page' => $limit,
            'tax_query' => [
                [
                    'taxonomy' => 'event_category',
                    'field' => is_numeric($category) ? 'term_id' : 'slug',
                    'terms' => $category,
                ],
            ],
            'meta_key' => 'start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ]);
    }

    /**
     * Find events in date range
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Array of EventPost objects
     */
    public function findInDateRange(string $startDate, string $endDate): array
    {
        return \Timber::get_posts([
            'post_type' => 'event',
            'posts_per_page' => -1,
            'meta_key' => 'start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'start_date',
                    'value' => $startDate,
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
                [
                    'key' => 'start_date',
                    'value' => $endDate,
                    'compare' => '<=',
                    'type' => 'DATE',
                ],
            ],
        ]);
    }

    /**
     * Find events by month
     *
     * @param int $month Month (1-12)
     * @param int $year Year (e.g., 2024)
     * @return array Array of EventPost objects
     */
    public function findByMonth(int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month

        return $this->findInDateRange($startDate, $endDate);
    }

    /**
     * Find event by ID
     *
     * @param int $id Post ID
     * @return EventPost|null EventPost object or null if not found
     */
    public function findById(int $id): ?EventPost
    {
        $post = \Timber::get_post($id);

        if (!$post || $post->post_type !== 'event') {
            return null;
        }

        return $post;
    }

    /**
     * Find event by slug
     *
     * @param string $slug Post slug
     * @return EventPost|null EventPost object or null if not found
     */
    public function findBySlug(string $slug): ?EventPost
    {
        $posts = \Timber::get_posts([
            'post_type' => 'event',
            'name' => $slug,
            'posts_per_page' => 1,
        ]);

        return $posts[0] ?? null;
    }

    /**
     * Search events
     *
     * @param string $query Search query
     * @param int $limit Number of events to retrieve
     * @return array Array of EventPost objects
     */
    public function search(string $query, int $limit = 10): array
    {
        return \Timber::get_posts([
            'post_type' => 'event',
            'posts_per_page' => $limit,
            's' => $query,
            'meta_key' => 'start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ]);
    }

    /**
     * Get event count
     *
     * @param array $args Query arguments
     * @return int Number of events
     */
    public function count(array $args = []): int
    {
        $defaults = [
            'post_type' => 'event',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];

        $args = array_merge($defaults, $args);
        $query = new \WP_Query($args);

        return $query->found_posts;
    }

    /**
     * Get upcoming event count
     *
     * @return int Number of upcoming events
     */
    public function countUpcoming(): int
    {
        return $this->count([
            'meta_query' => [
                [
                    'key' => 'start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
            ],
        ]);
    }
}
