<?php

namespace Sitchco\Support;

class DateFormat
{
    protected string $day_format = 'j';
    protected string $month_format = 'M';
    protected string $year_format = 'Y';
    protected string $monthday_separator = ' ';
    protected string $year_separator = ', ';
    protected bool $include_year = true;
    protected bool $always_show_month = false;
    protected bool $always_show_year = false;

    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function getMonthdayFormat($with_year = true)
    {
        $format = $this->month_format . $this->monthday_separator . $this->day_format;
        return $this->maybeAppendYear($format, $with_year);
    }

    public function getDayFormat($with_year = true)
    {
        return $this->always_show_month ?
            $this->getMonthdayFormat($with_year) :
            $this->maybeAppendYear($this->day_format, $with_year);
    }

    protected function maybeAppendYear($format, $with_year = true)
    {
        if (($with_year || $this->always_show_year) && $this->include_year) {
            $format .= $this->year_separator . $this->year_format;
        }
        return $format;
    }

}