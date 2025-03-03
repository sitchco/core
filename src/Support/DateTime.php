<?php

namespace Sitchco\Support;

use Carbon\CarbonImmutable;
use Carbon\Month;
use Carbon\WeekDay;
use DateTimeInterface;
use DateTimeZone;

class DateTime extends CarbonImmutable
{
    public function __construct(
        DateTimeInterface|WeekDay|Month|string|int|float|null $time = 'now',
        DateTimeZone|string|int|null $timezone = null,
    )
    {
        parent::__construct($time, $this->getDefaultTimezone($timezone));
    }

    public static function createFromTimestamp($timestamp, DateTimeZone|string|int $timezone = null): static
    {
        $datetime = new static("@$timestamp", new DateTimeZone('UTC'));
        $datetime->setTimezone($datetime->getDefaultTimezone($timestamp, $timezone));
        return $datetime;
    }

    protected function getDefaultTimezone(
        DateTimeInterface|WeekDay|Month|string|int|float|null $time = 'now',
        DateTimeZone|string|int|null $timezone = null
    ) {
        return is_null($timezone) ? new DateTimeZone($this->getDefaultTimezoneName($time)) : $timezone;
    }

    public function getDefaultTimezoneName(DateTimeInterface|WeekDay|Month|string|int|float|null $time = 'now'): string
    {
        if (!function_exists('get_option')) {
            return date_default_timezone_get();
        }
        if ($timezone_string = get_option('timezone_string')) {
            return $timezone_string;
        }
        // If no named timezone is available, get the GMT offset in hours
        $gmtOffset = get_option('gmt_offset', 0) * HOUR_IN_SECONDS;

        // Check if DST is in effect at this time using the system timezone
        $localtime_assoc = localtime(strtotime($time ?? 'now'), true);
        $isDst = !empty($localtime_assoc['is_dst']) || !empty($localtime_assoc['tm_isdst']);

        return timezone_name_from_abbr('', $gmtOffset, $isDst);
    }

}