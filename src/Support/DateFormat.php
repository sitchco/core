<?php

namespace Sitchco\Support;

/**
 * Date format configuration for flexible date string generation.
 *
 * This class builds date format strings based on configurable patterns and separators.
 * All properties are readonly after construction, ensuring immutable format configurations.
 *
 * @see https://www.php.net/manual/en/datetime.format.php For format character reference
 */
readonly class DateFormat
{
    /**
     * PHP date format for the day component.
     *
     * Common values:
     * - 'j' = Day without leading zeros (1-31)
     * - 'd' = Day with leading zeros (01-31)
     * - 'D' = Textual day, 3 letters (Mon, Tue, etc.)
     * - 'l' = Full textual day (Monday, Tuesday, etc.)
     */
    public string $day_format;

    /**
     * PHP date format for the month component.
     *
     * Common values:
     * - 'M' = Short month name (Jan, Feb, etc.)
     * - 'F' = Full month name (January, February, etc.)
     * - 'm' = Numeric month with leading zeros (01-12)
     * - 'n' = Numeric month without leading zeros (1-12)
     */
    public string $month_format;

    /**
     * PHP date format for the year component.
     *
     * Common values:
     * - 'Y' = 4-digit year (2025)
     * - 'y' = 2-digit year (25)
     */
    public string $year_format;

    /**
     * Separator between month and day.
     *
     * Examples:
     * - ' ' = "Jan 15" (default)
     * - '/' = "Jan/15"
     * - '-' = "Jan-15"
     * - '' = "Jan15"
     */
    public string $monthday_separator;

    /**
     * Separator between date and year.
     *
     * Examples:
     * - ', ' = "Jan 15, 2025" (default)
     * - '/' = "Jan 15/2025"
     * - '-' = "Jan 15-2025"
     * - ' ' = "Jan 15 2025"
     */
    public string $year_separator;

    /**
     * Whether to include the year in date formats.
     *
     * When false, year will never be included regardless of method parameters.
     * Default: true
     */
    public bool $include_year;

    /**
     * Whether to always show the month in getDayFormat().
     *
     * When true, getDayFormat() returns the full month+day format.
     * When false, getDayFormat() returns only the day.
     * Default: false
     */
    public bool $always_show_month;

    /**
     * Whether to always show the year in all formats.
     *
     * When true, year is included even when $with_year parameter is false.
     * Default: false
     */
    public bool $always_show_year;

    /**
     * Create a new DateFormat instance.
     *
     * @param array{
     *     day_format?: string,
     *     month_format?: string,
     *     year_format?: string,
     *     monthday_separator?: string,
     *     year_separator?: string,
     *     include_year?: bool,
     *     always_show_month?: bool,
     *     always_show_year?: bool
     * } $options Configuration options
     *
     * @example
     * // US-style format (default)
     * new DateFormat();
     * // Produces: "Jan 15, 2025"
     *
     * @example
     * // European format
     * new DateFormat([
     *     'day_format' => 'd',
     *     'month_format' => 'm',
     *     'year_format' => 'Y',
     *     'monthday_separator' => '/',
     *     'year_separator' => '/',
     * ]);
     * // Produces: "15/01/2025"
     *
     * @example
     * // Long readable format
     * new DateFormat([
     *     'month_format' => 'F',
     *     'day_format' => 'j',
     * ]);
     * // Produces: "January 15, 2025"
     */
    public function __construct(array $options = [])
    {
        $this->day_format = $options['day_format'] ?? 'j';
        $this->month_format = $options['month_format'] ?? 'M';
        $this->year_format = $options['year_format'] ?? 'Y';
        $this->monthday_separator = $options['monthday_separator'] ?? ' ';
        $this->year_separator = $options['year_separator'] ?? ', ';
        $this->include_year = $options['include_year'] ?? true;
        $this->always_show_month = $options['always_show_month'] ?? false;
        $this->always_show_year = $options['always_show_year'] ?? false;
    }

    /**
     * Create a custom DateFormat instance with specified options.
     *
     * This is a factory method that provides an alternative to using the constructor,
     * particularly useful in contexts where the `new` keyword is not available (e.g., Twig templates).
     *
     * @param array{
     *     day_format?: string,
     *     month_format?: string,
     *     year_format?: string,
     *     monthday_separator?: string,
     *     year_separator?: string,
     *     include_year?: bool,
     *     always_show_month?: bool,
     *     always_show_year?: bool
     * } $options Configuration options
     * @return self New DateFormat instance
     *
     * @example
     * // In PHP
     * $format = DateFormat::create(['month_format' => 'F', 'day_format' => 'j']);
     *
     * @example
     * // In Twig (after registering DateFormat class as global)
     * {% set format = DateFormat::create({'month_format': 'F', 'day_format': 'j'}) %}
     * {{ range.format(format) }}
     */
    public static function create(array $options = []): self
    {
        return new self($options);
    }

    /**
     * Create a US-style date format (e.g., "Jan 15, 2025")
     */
    public static function us(): self
    {
        return new self();
    }

    /**
     * Create an ISO 8601 date format (e.g., "2025-01-15")
     */
    public static function iso(): self
    {
        return new self([
            'year_format' => 'Y',
            'month_format' => 'm',
            'day_format' => 'd',
            'monthday_separator' => '-',
            'year_separator' => '-',
        ]);
    }

    /**
     * Create a European date format (e.g., "15/01/2025")
     */
    public static function european(): self
    {
        return new self([
            'day_format' => 'd',
            'month_format' => 'm',
            'year_format' => 'Y',
            'monthday_separator' => '/',
            'year_separator' => '/',
        ]);
    }

    /**
     * Create a compact date format (e.g., "1/15/25")
     */
    public static function compact(): self
    {
        return new self([
            'day_format' => 'j',
            'month_format' => 'n',
            'year_format' => 'y',
            'monthday_separator' => '/',
            'year_separator' => '/',
        ]);
    }

    /**
     * Create a long date format (e.g., "January 15, 2025")
     */
    public static function long(): self
    {
        return new self([
            'month_format' => 'F',
            'day_format' => 'j',
            'year_format' => 'Y',
        ]);
    }

    /**
     * Create a new instance with one or more modified properties.
     *
     * Returns a new DateFormat instance with the specified properties changed
     * while preserving all other properties from the current instance.
     *
     * @param array{
     *     day_format?: string,
     *     month_format?: string,
     *     year_format?: string,
     *     monthday_separator?: string,
     *     year_separator?: string,
     *     include_year?: bool,
     *     always_show_month?: bool,
     *     always_show_year?: bool
     * } $options Properties to modify
     * @return self New DateFormat instance with merged properties
     *
     * @example
     * // Change single property
     * $format = DateFormat::us()->with(['month_format' => 'F']);
     * // "M j, Y" → "F j, Y" (Jan → January)
     *
     * @example
     * // Change multiple properties
     * $format = DateFormat::us()->with([
     *     'month_format' => 'F',
     *     'year_separator' => ' ',
     * ]);
     * // "M j, Y" → "F j Y" (January 15 2025)
     *
     * @example
     * // Chain multiple modifications
     * $format = DateFormat::iso()
     *     ->with(['day_format' => 'D'])
     *     ->with(['include_year' => false]);
     */
    public function with(array $options): self
    {
        return new self([
            'day_format' => $options['day_format'] ?? $this->day_format,
            'month_format' => $options['month_format'] ?? $this->month_format,
            'year_format' => $options['year_format'] ?? $this->year_format,
            'monthday_separator' => $options['monthday_separator'] ?? $this->monthday_separator,
            'year_separator' => $options['year_separator'] ?? $this->year_separator,
            'include_year' => $options['include_year'] ?? $this->include_year,
            'always_show_month' => $options['always_show_month'] ?? $this->always_show_month,
            'always_show_year' => $options['always_show_year'] ?? $this->always_show_year,
        ]);
    }

    /**
     * Get the format string for month and day, optionally with year.
     *
     * Returns a format string combining month, day, and optionally year.
     * The year is included based on the $with_year parameter and class flags.
     *
     * @param bool $with_year Whether to include the year in the format (default: true)
     * @return string The format string (e.g., "M j, Y" or "M j")
     *
     * @example
     * $format = new DateFormat();
     * $format->getMonthdayFormat(true);  // "M j, Y" → "Jan 15, 2025"
     * $format->getMonthdayFormat(false); // "M j"    → "Jan 15"
     */
    public function getMonthdayFormat(bool $with_year = true): string
    {
        $format = $this->month_format . $this->monthday_separator . $this->day_format;
        return $this->maybeAppendYear($format, $with_year);
    }

    /**
     * Get the format string for day only, optionally with year.
     *
     * Returns a format string for the day component. If $always_show_month is true,
     * this method returns the same as getMonthdayFormat().
     *
     * @param bool $with_year Whether to include the year in the format (default: true)
     * @return string The format string (e.g., "j, Y" or "j")
     *
     * @example
     * $format = new DateFormat();
     * $format->getDayFormat(true);  // "j, Y" → "15, 2025"
     * $format->getDayFormat(false); // "j"    → "15"
     *
     * @example
     * // With always_show_month enabled
     * $format = new DateFormat(['always_show_month' => true]);
     * $format->getDayFormat(false); // "M j" → "Jan 15"
     */
    public function getDayFormat(bool $with_year = true): string
    {
        return $this->always_show_month
            ? $this->getMonthdayFormat($with_year)
            : $this->maybeAppendYear($this->day_format, $with_year);
    }

    protected function maybeAppendYear(string $format, bool $with_year = true): string
    {
        if (($with_year || $this->always_show_year) && $this->include_year) {
            $format .= $this->year_separator . $this->year_format;
        }
        return $format;
    }
}
