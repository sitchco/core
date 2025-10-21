<?php
/**
 * Event Post Class
 *
 * Custom Timber post class for Event post type.
 * Provides convenient methods for accessing event-specific data.
 */

namespace Sitchco\App\Modules\Event;

use Timber\Post;

class EventPost extends Post
{
    /**
     * Get the event start date
     *
     * @return string Event start date (Y-m-d format)
     */
    public function startDate(): string
    {
        return get_field('start_date', $this->ID) ?: '';
    }

    /**
     * Get the event end date
     *
     * @return string Event end date (Y-m-d format)
     */
    public function endDate(): string
    {
        return get_field('end_date', $this->ID) ?: '';
    }

    /**
     * Get the event location
     *
     * @return string Event location
     */
    public function location(): string
    {
        return get_field('location', $this->ID) ?: '';
    }

    /**
     * Get formatted start date
     *
     * @param string $format Date format (default: 'F j, Y')
     * @return string Formatted date
     */
    public function formattedStartDate(string $format = 'F j, Y'): string
    {
        $date = $this->startDate();
        return $date ? date($format, strtotime($date)) : '';
    }

    /**
     * Get formatted end date
     *
     * @param string $format Date format (default: 'F j, Y')
     * @return string Formatted date
     */
    public function formattedEndDate(string $format = 'F j, Y'): string
    {
        $date = $this->endDate();
        return $date ? date($format, strtotime($date)) : '';
    }

    /**
     * Get date range string
     *
     * @return string Date range (e.g., "Jan 15 - Jan 17, 2024")
     */
    public function dateRange(): string
    {
        $start = $this->startDate();
        $end = $this->endDate();

        if (!$start) {
            return '';
        }

        if (!$end || $start === $end) {
            return $this->formattedStartDate();
        }

        $startMonth = date('F', strtotime($start));
        $endMonth = date('F', strtotime($end));
        $startDay = date('j', strtotime($start));
        $endDay = date('j', strtotime($end));
        $year = date('Y', strtotime($start));

        if ($startMonth === $endMonth) {
            return "{$startMonth} {$startDay}-{$endDay}, {$year}";
        }

        return $this->formattedStartDate('M j') . ' - ' . $this->formattedEndDate('M j, Y');
    }

    /**
     * Check if event is upcoming
     *
     * @return bool True if event starts in the future
     */
    public function isUpcoming(): bool
    {
        $date = $this->startDate();
        return $date && strtotime($date) > time();
    }

    /**
     * Check if event is past
     *
     * @return bool True if event has ended
     */
    public function isPast(): bool
    {
        $date = $this->endDate() ?: $this->startDate();
        return $date && strtotime($date) < time();
    }

    /**
     * Check if event is currently happening
     *
     * @return bool True if event is happening now
     */
    public function isHappening(): bool
    {
        $start = $this->startDate();
        $end = $this->endDate() ?: $start;

        if (!$start) {
            return false;
        }

        $now = time();
        return strtotime($start) <= $now && strtotime($end) >= $now;
    }

    /**
     * Get event categories
     *
     * @return array Array of category terms
     */
    public function categories(): array
    {
        return get_the_terms($this->ID, 'event_category') ?: [];
    }

    /**
     * Get category names as comma-separated string
     *
     * @return string Category names
     */
    public function categoryNames(): string
    {
        $categories = $this->categories();
        return implode(', ', wp_list_pluck($categories, 'name'));
    }

    /**
     * Get days until event
     *
     * @return int Number of days (negative if past)
     */
    public function daysUntil(): int
    {
        $date = $this->startDate();
        if (!$date) {
            return 0;
        }

        $diff = strtotime($date) - time();
        return (int) floor($diff / (60 * 60 * 24));
    }

    /**
     * Get human-readable time until event
     *
     * @return string e.g., "in 3 days", "tomorrow", "2 weeks ago"
     */
    public function timeUntil(): string
    {
        $days = $this->daysUntil();

        if ($days === 0) {
            return 'today';
        } elseif ($days === 1) {
            return 'tomorrow';
        } elseif ($days === -1) {
            return 'yesterday';
        } elseif ($days > 0) {
            if ($days < 7) {
                return "in {$days} days";
            }
            $weeks = floor($days / 7);
            return "in {$weeks} " . ($weeks === 1 ? 'week' : 'weeks');
        } else {
            $days = abs($days);
            if ($days < 7) {
                return "{$days} days ago";
            }
            $weeks = floor($days / 7);
            return "{$weeks} " . ($weeks === 1 ? 'week' : 'weeks') . ' ago';
        }
    }
}
