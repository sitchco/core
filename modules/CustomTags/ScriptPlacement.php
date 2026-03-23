<?php

namespace Sitchco\Modules\CustomTags;

enum ScriptPlacement: string
{
    case BeforeGtm = 'before_gtm';
    case AfterGtm = 'after_gtm';
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::BeforeGtm => 'Before GTM',
            self::AfterGtm => 'After GTM',
            self::Footer => 'Footer',
        };
    }

    public static function choices(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn(self $case) => $case->label(), self::cases()),
        );
    }
}
