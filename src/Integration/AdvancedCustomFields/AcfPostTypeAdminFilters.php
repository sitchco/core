<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Acf;

class AcfPostTypeAdminFilters extends Module
{
    protected AcfSettings $settings;

    const DEPENDENCIES = [
        AcfPostTypeAdminColumns::class,
    ];

    public function __construct(AcfSettings $settings)
    {
        $this->settings = $settings;
    }

    public function init(): void
    {
        if (!class_exists('ACF')) {
            return;
        }
        $this->settings->extendSettingsField('listing_screen_columns', function($field) {
            $field['sub_fields'] = array_merge(
                $field['sub_fields'],
                [
                    [
                        'key' => 'filterable',
                        'label' => 'Filterable?',
                        'name' => 'filterable',
                        'type' => 'true_false',
                    ],
                ]
            );
            return $field;
        });
    }
}