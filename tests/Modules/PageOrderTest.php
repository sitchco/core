<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\PageOrder;
use Sitchco\Tests\TestCase;

class PageOrderTest extends TestCase
{
    public function test_sort_order_transient()
    {
        // save_post_{post_type}
        $this->factory()->post->create();
        $this->assertFalse(get_transient(PageOrder::CHECK_SORT_TRANSIENT));
        $this->factory()->post->create(['post_type' => 'page']);
        $this->assertEquals(1, get_transient(PageOrder::CHECK_SORT_TRANSIENT));
        delete_transient(PageOrder::CHECK_SORT_TRANSIENT);
        $this->factory()->post->create(['post_type' => 'nav_menu_item']);
        $this->assertEquals(1, get_transient(PageOrder::CHECK_SORT_TRANSIENT));
        delete_transient(PageOrder::CHECK_SORT_TRANSIENT);

        // current_screen
        set_current_screen('pages');
        $this->assertFalse(get_transient(PageOrder::CHECK_SORT_TRANSIENT));
        set_current_screen('pages_page_order-page');
        $this->assertEquals(1, get_transient(PageOrder::CHECK_SORT_TRANSIENT));
        delete_transient(PageOrder::CHECK_SORT_TRANSIENT);
    }

    protected function createNavMenuItem(int $nav_menu_id, int $page_id): \WP_Term|\WP_Error|bool|int
    {
        return wp_update_nav_menu_item($nav_menu_id, 0, [
            'menu-item-title' => "Page $page_id",
            'menu-item-object' => 'page',
            'menu-item-object-id' => $page_id,
            'menu-item-type' => 'post_type',
            'menu-item-status' => 'publish',
        ]);
    }

    public function test_reorder_pages_from_menu_priorities()
    {
        $pages = $this->factory()->post->create_many(5, ['post_type' => 'page']);

        // Create two menus and assign them to theme locations
        $primary_menu_id = wp_create_nav_menu('Primary Menu');
        $footer_menu_id  = wp_create_nav_menu('Footer Menu');

        set_theme_mod('nav_menu_locations', [
            PageOrder::MENU_LOCATION_PRIMARY => $primary_menu_id,
            PageOrder::MENU_LOCATION_FOOTER  => $footer_menu_id,
        ]);

        // Add pages to the menus, omitting last page
        $this->createNavMenuItem($footer_menu_id, $pages[0]);
        $this->createNavMenuItem($footer_menu_id, $pages[2]);
        $this->createNavMenuItem($primary_menu_id, $pages[1]);
        $this->createNavMenuItem($primary_menu_id, $pages[3]);

        foreach ($pages as $page_id) {
            $menu_order = (int) get_post_field('menu_order', $page_id);
            $this->assertSame(0, $menu_order);
        }

        $expected_page_id_order = [
            $pages[1],
            $pages[3],
            $pages[4],
            $pages[0],
            $pages[2],
        ];

        $menu_orders = $this->container->get(PageOrder::class)->sortPagesByMenuOrder();
        $this->assertEquals($expected_page_id_order, $menu_orders);

        foreach ($expected_page_id_order as $index => $page_id) {
            $menu_order = (int) get_post_field('menu_order', $page_id);
            $this->assertSame($index, $menu_order);
        }
    }

}
