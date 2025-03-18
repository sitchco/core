<?php

namespace Sitchco\Events;

use Sitchco\Support\HasAsyncAction;
use Sitchco\Support\HasHooks;
use Sitchco\Utils\Hooks;

/**
 *
 */
abstract class BackgroundEvent extends \WP_Async_Request
{
    use HasAsyncAction;

    /**
     * List of data keys to pull from $_POST and convert into ordered array of arguments
     * @var array
     */
    protected array $data_keys = [];

    /**
     * @var string
     */
    protected $prefix = Hooks::ROOT;

    const HOOK_PREFIX = 'background_event';

    public function maybe_handle(): void
    {
        $this->data($_POST);
        $this->action_data = array_map(fn($key) => $this->data[$key] ?? null, $this->data_keys);
        parent::maybe_handle();
    }
}