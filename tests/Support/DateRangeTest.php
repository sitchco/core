<?php

namespace Sitchco\Tests\Support;

use Sitchco\Support\DateFormat;
use Sitchco\Support\DateRange;
use Sitchco\Support\DateTime;
use Sitchco\Tests\TestCase;

class DateRangeTest extends TestCase
{
    public function test_create_factory_method_with_datetime_objects()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $range = DateRange::create($start, $end);

        $this->assertInstanceOf(DateRange::class, $range);
        $this->assertEquals(['Jan 15', '20'], $range->formattedRange());
    }

    public function test_create_factory_method_with_strings()
    {
        $range = DateRange::create('2025-01-15', '2025-01-20');

        $this->assertInstanceOf(DateRange::class, $range);
        $this->assertEquals(['Jan 15', '20'], $range->formattedRange());
    }

    public function test_create_factory_method_with_mixed_types()
    {
        $start = new DateTime('2025-01-15');
        $range = DateRange::create($start, '2025-01-20');

        $this->assertInstanceOf(DateRange::class, $range);
        $this->assertEquals(['Jan 15', '20'], $range->formattedRange());
    }

    public function test_formatted_range_with_default_format()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        // Same month: smart formatting shows "Jan 15 - 20" not "Jan 15 - Jan 20"
        $this->assertEquals(['Jan 15', '20'], $range->formattedRange());
        $this->assertEquals('Jan 15 - 20', $range->format());
    }

    public function test_formatted_range_with_custom_format()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $format = DateFormat::iso();
        $range = new DateRange($start, $end);

        // Same month: smart formatting shows "01-15 - 20" using getDayFormat for end date
        $this->assertEquals(['01-15', '20'], $range->formattedRange($format));
    }

    public function test_format_with_custom_separator()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        // Smart formatting applies: same month shows "Jan 15 to 20" not "Jan 15 to Jan 20"
        $this->assertEquals('Jan 15 to 20', $range->format(separator: ' to '));
    }

    public function test_same_day_returns_single_date()
    {
        $date = new DateTime('2025-01-15');
        $range = new DateRange($date, $date);

        $this->assertEquals(['Jan 15'], $range->formattedRange());
        $this->assertEquals('Jan 15', $range->format());
    }

    public function test_same_day_with_year()
    {
        $date = new DateTime('2025-01-15');
        $range = new DateRange($date, $date);

        $this->assertEquals(['Jan 15, 2025'], $range->formattedRange(with_end_date_year: true));
        $this->assertEquals('Jan 15, 2025', $range->format(with_end_date_year: true));
    }

    public function test_same_month_different_days()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        // Same month: "Jan 15 - 20"
        $this->assertEquals(['Jan 15', '20'], $range->formattedRange());
        $this->assertEquals('Jan 15 - 20', $range->format());
    }

    public function test_same_month_with_year_on_end_date()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        // Same month with year on end: "Jan 15 - 20, 2025"
        $this->assertEquals(['Jan 15', '20, 2025'], $range->formattedRange(with_end_date_year: true));
        $this->assertEquals('Jan 15 - 20, 2025', $range->format(with_end_date_year: true));
    }

    public function test_same_year_different_months()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-03-20');
        $range = new DateRange($start, $end);

        // Same year: "Jan 15 - Mar 20"
        $this->assertEquals(['Jan 15', 'Mar 20'], $range->formattedRange());
        $this->assertEquals('Jan 15 - Mar 20', $range->format());
    }

    public function test_same_year_with_year_on_end_date()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-03-20');
        $range = new DateRange($start, $end);

        // Same year with year on end: "Jan 15 - Mar 20, 2025"
        $this->assertEquals(['Jan 15', 'Mar 20, 2025'], $range->formattedRange(with_end_date_year: true));
        $this->assertEquals('Jan 15 - Mar 20, 2025', $range->format(with_end_date_year: true));
    }

    public function test_different_years()
    {
        $start = new DateTime('2024-12-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        // Different years: "Dec 15, 2024 - Jan 20, 2025"
        $this->assertEquals(['Dec 15, 2024', 'Jan 20, 2025'], $range->formattedRange());
        $this->assertEquals('Dec 15, 2024 - Jan 20, 2025', $range->format());
    }

    public function test_different_years_with_year_flag()
    {
        $start = new DateTime('2024-12-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        // Year flag doesn't affect different year ranges
        $this->assertEquals(['Dec 15, 2024', 'Jan 20, 2025'], $range->formattedRange(with_end_date_year: true));
        $this->assertEquals(['Dec 15, 2024', 'Jan 20, 2025'], $range->formattedRange(with_end_date_year: false));
    }

    public function test_format_range_with_boolean_parameters()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        // Both booleans
        $this->assertEquals(
            ['Jan 15, 2025', 'Jan 20, 2025'],
            $range->formatRange(start_format: true, end_format: true),
        );
        $this->assertEquals(['Jan 15', 'Jan 20'], $range->formatRange(start_format: false, end_format: false));
        $this->assertEquals(['Jan 15', 'Jan 20, 2025'], $range->formatRange(start_format: false, end_format: true));
    }

    public function test_format_range_with_custom_format_strings()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        // Custom format strings
        $this->assertEquals(
            ['January 15, 2025', 'Jan 20'],
            $range->formatRange(start_format: 'F j, Y', end_format: 'M j'),
        );
    }

    public function test_format_range_with_mixed_parameters()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        // Mixed: boolean and string
        $this->assertEquals(['Jan 15, 2025', 'January 20'], $range->formatRange(start_format: true, end_format: 'F j'));
    }

    public function test_to_string_magic_method()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $range = new DateRange($start, $end);

        $this->assertEquals('Jan 15 - 20', (string) $range);
    }

    public function test_with_custom_format_long_style()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-03-20');
        $format = DateFormat::long();
        $range = new DateRange($start, $end);

        $this->assertEquals(
            ['January 15', 'March 20, 2025'],
            $range->formattedRange($format, with_end_date_year: true),
        );
        $this->assertEquals('January 15 - March 20, 2025', $range->format($format, with_end_date_year: true));
    }

    public function test_with_iso_format()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-03-20');
        $format = DateFormat::iso();
        $range = new DateRange($start, $end);

        $this->assertEquals(['01-15', '03-20-2025'], $range->formattedRange($format, with_end_date_year: true));
    }

    public function test_with_custom_separator_and_format()
    {
        $start = new DateTime('2025-01-15');
        $end = new DateTime('2025-01-20');
        $format = DateFormat::long();
        $range = new DateRange($start, $end);

        $this->assertEquals(
            'January 15 through 20, 2025',
            $range->format($format, with_end_date_year: true, separator: ' through '),
        );
    }

    public function test_edge_case_year_boundary()
    {
        $start = new DateTime('2024-12-31');
        $end = new DateTime('2025-01-01');
        $range = new DateRange($start, $end);

        $this->assertEquals(['Dec 31, 2024', 'Jan 1, 2025'], $range->formattedRange());
    }

    public function test_edge_case_month_boundary_same_year()
    {
        $start = new DateTime('2025-01-31');
        $end = new DateTime('2025-02-01');
        $range = new DateRange($start, $end);

        $this->assertEquals(['Jan 31', 'Feb 1'], $range->formattedRange());
    }
}
