<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\PageOrder;
use Sitchco\Tests\Support\TestCase;

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
}
