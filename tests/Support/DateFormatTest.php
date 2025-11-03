<?php

namespace Sitchco\Tests\Support;

use Sitchco\Support\DateFormat;
use Sitchco\Tests\TestCase;

class DateFormatTest extends TestCase
{
    public function test_constructor_with_custom_options()
    {
        $format = new DateFormat([
            'day_format' => 'd',
            'month_format' => 'F',
            'year_format' => 'y',
        ]);

        $this->assertEquals('F d', $format->getMonthdayFormat(false));
        $this->assertEquals('F d, y', $format->getMonthdayFormat());
    }

    public function test_constructor_with_custom_separators()
    {
        $format = new DateFormat([
            'monthday_separator' => '/',
            'year_separator' => ' ',
        ]);

        $this->assertEquals('M/j Y', $format->getMonthdayFormat());
    }

    public function test_constructor_ignores_invalid_properties()
    {
        $format = new DateFormat([
            'invalid_property' => 'value',
            'month_format' => 'F',
        ]);

        // Should only apply valid properties and not throw error
        $this->assertEquals('F j', $format->getMonthdayFormat(false));
    }

    public function test_get_monthday_format_with_include_year_false()
    {
        $format = new DateFormat(['include_year' => false]);

        $this->assertEquals('M j', $format->getMonthdayFormat());
        $this->assertEquals('M j', $format->getMonthdayFormat(false));
    }

    public function test_get_monthday_format_with_always_show_year_true()
    {
        $format = new DateFormat(['always_show_year' => true]);

        $this->assertEquals('M j, Y', $format->getMonthdayFormat(false));
        $this->assertEquals('M j, Y', $format->getMonthdayFormat());
    }

    public function test_get_monthday_format_with_always_show_year_but_include_year_false()
    {
        $format = new DateFormat([
            'always_show_year' => true,
            'include_year' => false,
        ]);

        // include_year takes precedence
        $this->assertEquals('M j', $format->getMonthdayFormat(false));
        $this->assertEquals('M j', $format->getMonthdayFormat());
    }

    public function test_get_day_format_with_always_show_month_true()
    {
        $format = new DateFormat(['always_show_month' => true]);

        $this->assertEquals('M j', $format->getDayFormat(false));
        $this->assertEquals('M j, Y', $format->getDayFormat());
    }

    public function test_get_day_format_with_include_year_false()
    {
        $format = new DateFormat(['include_year' => false]);

        $this->assertEquals('j', $format->getDayFormat());
        $this->assertEquals('j', $format->getDayFormat(false));
    }

    public function test_custom_format_combination()
    {
        $format = new DateFormat([
            'day_format' => 'd',
            'month_format' => 'F',
            'year_format' => 'Y',
            'monthday_separator' => ' ',
            'year_separator' => ' ',
        ]);

        $this->assertEquals('F d', $format->getMonthdayFormat(false));
        $this->assertEquals('F d Y', $format->getMonthdayFormat());
        $this->assertEquals('d', $format->getDayFormat(false));
        $this->assertEquals('d Y', $format->getDayFormat());
    }

    public function test_european_date_format_style()
    {
        $format = new DateFormat([
            'day_format' => 'd',
            'month_format' => 'm',
            'year_format' => 'Y',
            'monthday_separator' => '/',
            'year_separator' => '/',
        ]);

        $this->assertEquals('m/d', $format->getMonthdayFormat(false));
        $this->assertEquals('m/d/Y', $format->getMonthdayFormat());
    }

    public function test_iso_date_format_style()
    {
        $format = new DateFormat([
            'year_format' => 'Y',
            'month_format' => 'm',
            'day_format' => 'd',
            'monthday_separator' => '-',
            'year_separator' => '-',
        ]);

        $this->assertEquals('m-d', $format->getMonthdayFormat(false));
        $this->assertEquals('m-d-Y', $format->getMonthdayFormat());
    }

    public function test_complex_flag_interactions()
    {
        // Test with always_show_month and always_show_year both true
        $format = new DateFormat([
            'always_show_month' => true,
            'always_show_year' => true,
        ]);

        $this->assertEquals('M j, Y', $format->getDayFormat(false));
        $this->assertEquals('M j, Y', $format->getDayFormat());
        $this->assertEquals('M j, Y', $format->getMonthdayFormat(false));
        $this->assertEquals('M j, Y', $format->getMonthdayFormat());
    }

    public function test_empty_separators()
    {
        $format = new DateFormat([
            'monthday_separator' => '',
            'year_separator' => '',
        ]);

        $this->assertEquals('MjY', $format->getMonthdayFormat());
        $this->assertEquals('Mj', $format->getMonthdayFormat(false));
    }

    public function test_create_factory_method_with_no_options()
    {
        $format = DateFormat::create();

        // Should produce same result as default constructor
        $this->assertEquals('M j, Y', $format->getMonthdayFormat());
        $this->assertEquals('j', $format->day_format);
    }

    public function test_create_factory_method_with_options()
    {
        $format = DateFormat::create([
            'month_format' => 'F',
            'day_format' => 'd',
        ]);

        $this->assertEquals('F d, Y', $format->getMonthdayFormat());
        $this->assertEquals('d', $format->day_format);
        $this->assertEquals('F', $format->month_format);
    }

    public function test_us_factory_method_and_default_values()
    {
        $format = DateFormat::us();

        // Test format output
        $this->assertEquals('M j', $format->getMonthdayFormat(false));
        $this->assertEquals('M j, Y', $format->getMonthdayFormat());
        $this->assertEquals('j', $format->getDayFormat(false));
        $this->assertEquals('j, Y', $format->getDayFormat());

        // Test default property values
        $this->assertEquals('j', $format->day_format);
        $this->assertEquals('M', $format->month_format);
        $this->assertEquals('Y', $format->year_format);
        $this->assertEquals(' ', $format->monthday_separator);
        $this->assertEquals(', ', $format->year_separator);
        $this->assertTrue($format->include_year);
        $this->assertFalse($format->always_show_month);
        $this->assertFalse($format->always_show_year);
    }

    public function test_iso_factory_method()
    {
        $format = DateFormat::iso();

        $this->assertEquals('m-d', $format->getMonthdayFormat(false));
        $this->assertEquals('m-d-Y', $format->getMonthdayFormat());
        $this->assertEquals('d', $format->getDayFormat(false));
        $this->assertEquals('d-Y', $format->getDayFormat());
    }

    public function test_european_factory_method()
    {
        $format = DateFormat::european();

        $this->assertEquals('m/d', $format->getMonthdayFormat(false));
        $this->assertEquals('m/d/Y', $format->getMonthdayFormat());
        $this->assertEquals('d', $format->getDayFormat(false));
        $this->assertEquals('d/Y', $format->getDayFormat());
    }

    public function test_compact_factory_method()
    {
        $format = DateFormat::compact();

        $this->assertEquals('n/j', $format->getMonthdayFormat(false));
        $this->assertEquals('n/j/y', $format->getMonthdayFormat());
        $this->assertEquals('j', $format->getDayFormat(false));
        $this->assertEquals('j/y', $format->getDayFormat());
    }

    public function test_long_factory_method()
    {
        $format = DateFormat::long();

        $this->assertEquals('F j', $format->getMonthdayFormat(false));
        $this->assertEquals('F j, Y', $format->getMonthdayFormat());
        $this->assertEquals('j', $format->getDayFormat(false));
        $this->assertEquals('j, Y', $format->getDayFormat());
    }

    public function test_properties_are_publicly_accessible()
    {
        $format = new DateFormat([
            'day_format' => 'd',
            'month_format' => 'F',
            'year_format' => 'y',
            'monthday_separator' => '/',
            'year_separator' => ' - ',
            'include_year' => false,
            'always_show_month' => true,
            'always_show_year' => true,
        ]);

        $this->assertEquals('d', $format->day_format);
        $this->assertEquals('F', $format->month_format);
        $this->assertEquals('y', $format->year_format);
        $this->assertEquals('/', $format->monthday_separator);
        $this->assertEquals(' - ', $format->year_separator);
        $this->assertFalse($format->include_year);
        $this->assertTrue($format->always_show_month);
        $this->assertTrue($format->always_show_year);
    }

    public function test_with_method_immutability_and_single_property()
    {
        $original = DateFormat::us();
        $modified = $original->with(['month_format' => 'F']);

        // Should be different instances (immutability)
        $this->assertNotSame($original, $modified);

        // Original should be unchanged
        $this->assertEquals('M', $original->month_format);
        $this->assertEquals('M j, Y', $original->getMonthdayFormat());

        // Modified should have new value
        $this->assertEquals('F', $modified->month_format);
        $this->assertEquals('F j, Y', $modified->getMonthdayFormat());

        // Other properties should be preserved
        $this->assertEquals('j', $modified->day_format);
        $this->assertEquals('Y', $modified->year_format);
        $this->assertEquals(' ', $modified->monthday_separator);
        $this->assertEquals(', ', $modified->year_separator);
        $this->assertTrue($modified->include_year);
    }

    public function test_with_method_multiple_properties_and_separators()
    {
        $format = DateFormat::us()->with([
            'month_format' => 'F',
            'monthday_separator' => '/',
            'year_separator' => '-',
        ]);

        $this->assertEquals('F/j-Y', $format->getMonthdayFormat());
    }

    public function test_with_method_chaining()
    {
        $format = DateFormat::compact()
            ->with(['month_format' => 'M'])
            ->with(['year_format' => 'Y'])
            ->with(['year_separator' => ', ']);

        $this->assertEquals('M/j, Y', $format->getMonthdayFormat());
        $this->assertEquals('M', $format->month_format);
        $this->assertEquals('Y', $format->year_format);
        $this->assertEquals(', ', $format->year_separator);
    }
}
