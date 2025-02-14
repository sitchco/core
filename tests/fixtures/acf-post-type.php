<?php

return [
    'post_type' => 'performance',
    'advanced_configuration' => true,
    'import_source' => '',
    'import_date' => '',
    'labels' =>
        [
            'name' => 'Performances',
            'singular_name' => 'Performance',
            'menu_name' => 'Performances',
            'all_items' => 'All Performances',
            'edit_item' => 'Edit Performance',
            'view_item' => 'View Performance',
            'view_items' => 'View Performances',
            'add_new_item' => 'Add New Performance',
            'add_new' => 'Add New Performance',
            'new_item' => 'New Performance',
            'parent_item_colon' => 'Parent Performance:',
            'search_items' => 'Search Performances',
            'not_found' => 'No performances found',
            'not_found_in_trash' => 'No performances found in Trash',
            'archives' => 'Performance Archives',
            'attributes' => 'Performance Attributes',
            'featured_image' => '',
            'set_featured_image' => '',
            'remove_featured_image' => '',
            'use_featured_image' => '',
            'insert_into_item' => 'Insert into performance',
            'uploaded_to_this_item' => 'Uploaded to this performance',
            'filter_items_list' => 'Filter performances list',
            'filter_by_date' => 'Filter performances by date',
            'items_list_navigation' => 'Performances list navigation',
            'items_list' => 'Performances list',
            'item_published' => 'Performance published.',
            'item_published_privately' => 'Performance published privately.',
            'item_reverted_to_draft' => 'Performance reverted to draft.',
            'item_scheduled' => 'Performance scheduled.',
            'item_updated' => 'Performance updated.',
            'item_link' => 'Performance Link',
            'item_link_description' => 'A link to a performance.',
        ],
    'description' => '',
    'public' => true,
    'hierarchical' => false,
    'exclude_from_search' => false,
    'publicly_queryable' => true,
    'show_ui' => true,
    'show_in_menu' => true,
    'admin_menu_parent' => '',
    'show_in_admin_bar' => true,
    'show_in_nav_menus' => true,
    'show_in_rest' => true,
    'rest_base' => '',
    'rest_namespace' => 'wp/v2',
    'rest_controller_class' => 'WP_REST_Posts_Controller',
    'menu_position' => '',
    'menu_icon' =>
        [
            'type' => 'dashicons',
            'value' => 'dashicons-admin-post',
        ],
    'rename_capabilities' => false,
    'singular_capability_name' => 'post',
    'plural_capability_name' => 'posts',
    'supports' =>
        [
            0 => 'title',
            1 => 'editor',
            2 => 'thumbnail',
            3 => 'custom-fields',
        ],
    'taxonomies' => 'category',
    'has_archive' => false,
    'has_archive_slug' => '',
    'rewrite' =>
        [
            'permalink_rewrite' => 'post_type_key',
            'with_front' => '1',
            'feeds' => '0',
            'pages' => '1',
        ],
    'query_var' => 'post_type_key',
    'query_var_name' => '',
    'can_export' => true,
    'delete_with_user' => false,
    'register_meta_box_cb' => '',
    'enter_title_here' => '',
    'default_query_parameters' =>
        [
            'row-row-0' =>
                [
                    'key' => 'orderby',
                    'value' => 'title',
                    'location' => '',
                ],
            'row-row-1' =>
                [
                    'key' => 'order',
                    'value' => 'DESC',
                    'location' => '',
                ],
            'row-row-2' =>
                [
                    'key' => 'order',
                    'value' => 'ASC',
                    'location' => 'admin',
                ],
            'row-row-3' =>
                [
                    'key' => 'meta_key',
                    'value' => 'active',
                    'location' => 'public',
                ],
            'row-row-4' =>
                [
                    'key' => 'meta_value',
                    'value' => '1',
                    'location' => 'public',
                ],
        ],
    'listing_screen_columns' =>
        [
            'row-row-0' =>
                [
                    'name' => 'active',
                    'label' => 'Active',
                    'sortable' => '0',
                ],
            'row-row-1' =>
                [
                    'name' => 'excerpt',
                    'label' => 'Summary',
                    'sortable' => '0',
                ],
            'row-row-2' =>
                [
                    'name' => '',
                    'label' => '',
                    'sortable' => '0',
                ],
        ],
];
