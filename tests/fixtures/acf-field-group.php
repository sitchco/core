<?php
/**
 * ACF Field Group: Event Attributes
 *
 * This file contains the field group definition for Event Attributes.
 * Can be used to dynamically import ACF field groups in unit tests.
 *
 * Usage in tests:
 *   $field_group = require '/path/to/group_68fa4ca929b7c.php';
 *   acf_add_local_field_group($field_group);
 */

return [
    'key' => 'group_68fa4ca929b7c',
    'title' => 'Event Attributes',
    'fields' => [
        [
            'key' => 'field_68fa4ca929da0',
            'label' => 'Start Time',
            'name' => 'start_time',
            'aria-label' => '',
            'type' => 'date_time_picker',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'display_format' => 'F j, Y g:i a',
            'return_format' => 'Y-m-d H:i:s',
            'first_day' => 1,
            'default_to_current_date' => 0,
            'allow_in_bindings' => 0,
        ],
        [
            'key' => 'field_68fa4d4129da1',
            'label' => 'End Time',
            'name' => 'end_time',
            'aria-label' => '',
            'type' => 'date_time_picker',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'display_format' => 'F j, Y g:i a',
            'return_format' => 'Y-m-d H:i:s',
            'first_day' => 1,
            'default_to_current_date' => 0,
            'allow_in_bindings' => 0,
        ],
    ],
    'location' => [
        [
            [
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'post',
            ],
        ],
    ],
    'menu_order' => 0,
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'instruction_placement' => 'label',
    'hide_on_screen' => '',
    'active' => true,
    'description' => '',
    'show_in_rest' => 0,
    'modified' => 1761234312,
];
