<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use ACF_Post_Type;
use Sitchco\Framework\Core\Module;
use WP_Query;

class AcfPostTypeAdminColumns extends Module
{
    public function init(): void
    {
        if (!class_exists('ACF')) {
            return;
        }

        add_filter( 'acf/post_type/additional_settings_tabs', function ( $tabs ) {
            $tabs['admin-columns'] = 'Admin Columns';
            return $tabs;
        } );
        add_action('acf/post_type/render_settings_tab/admin-columns', [$this, 'adminColumnsTab']);

    }

    public function adminColumnsTab(array $acf_post_type): void
    {
        acf_render_field_wrap(
            [
                'label'        => 'Listing Screen Columns',
                'instructions' => 'Enter a custom field name to automatically display its value in the column content, or use the <code>backstage/admin_col/{name}</code> filter to display custom content.',
                'name'         => 'listing_screen_columns',
                'prefix'       => 'acf_post_type',
                'value'        => $acf_post_type['listing_screen_columns'] ?? [],
                'type'         => 'repeater',
                'ui'           => 1,
                'sub_fields' => [
                    [
                        'key' => 'name',
                        'label' => 'Name',
                        'name' => 'name',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'label',
                        'label' => 'Label',
                        'name' => 'label',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'sortable',
                        'label' => 'Sortable?',
                        'name' => 'sortable',
                        'type' => 'true_false',
                    ],
                ],
                'min' => 1,
                'max' => 10,
                'layout' => 'table',
                'button_label' => 'Add Row',
            ]
        );
    }
}