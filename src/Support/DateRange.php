<?php

namespace Sitchco\Support;

/**
 * Represents a date range with intelligent formatting capabilities.
 *
 * This class is a readonly value object that pairs two DateTime instances
 * and provides smart formatting based on the relationship between the dates.
 */
readonly class DateRange
{
    public function __construct(public DateTime $start_date, public DateTime $end_date) {}

    /**
     * Create a DateRange instance from start and end dates.
     *
     * This is a factory method that provides an alternative to using the constructor,
     * particularly useful in contexts where the `new` keyword is not available (e.g., Twig templates).
     *
     * @param DateTime|string $start_date Start date of the range
     * @param DateTime|string $end_date End date of the range
     * @return self New DateRange instance
     *
     * @example
     * // In PHP with DateTime objects
     * $range = DateRange::create($start, $end);
     *
     * @example
     * // In PHP with strings (will be converted to DateTime)
     * $range = DateRange::create('2025-01-15', '2025-01-20');
     *
     * @example
     * // In Twig (after registering DateRange class as global)
     * {% set range = DateRange::create(event.start_date, event.end_date) %}
     * {{ range.format(DateFormat::long(), true) }}
     */
    public static function create(DateTime|string $start_date, DateTime|string $end_date): self
    {
        if (is_string($start_date)) {
            $start_date = new DateTime($start_date);
        }
        if (is_string($end_date)) {
            $end_date = new DateTime($end_date);
        }

        return new self($start_date, $end_date);
    }

    public function formattedRange(DateFormat $format = null, bool $with_end_date_year = false): array
    {
        $format ??= new DateFormat();

        if ($this->start_date->isSameDay($this->end_date)) {
            return [$this->start_date->format($format->getMonthdayFormat($with_end_date_year))];
        }

        if ($this->start_date->isSameMonth($this->end_date)) {
            return $this->formatRange($format, false, $format->getDayFormat($with_end_date_year));
        }

        if ($this->start_date->isSameYear($this->end_date)) {
            return $this->formatRange($format, false, $with_end_date_year);
        }

        return $this->formatRange();
    }

    public function formatRange(
        DateFormat $format = null,
        bool|string $start_format = true,
        bool|string $end_format = true,
    ): array {
        $format ??= new DateFormat();

        if (is_bool($start_format)) {
            $start_format = $format->getMonthdayFormat($start_format);
        }
        if (is_bool($end_format)) {
            $end_format = $format->getMonthdayFormat($end_format);
        }
        return [$this->start_date->format($start_format), $this->end_date->format($end_format)];
    }

    public function format(
        DateFormat $format = null,
        bool $with_end_date_year = false,
        string $separator = ' - ',
    ): string {
        return implode($separator, $this->formattedRange($format, $with_end_date_year));
    }

    public function __toString()
    {
        return $this->format();
    }
}
