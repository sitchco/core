<?php

namespace Sitchco\BackgroundProcessing;

use Sitchco\Support\HasHooks;
use Sitchco\Support\HookName;

/**
 *
 */
abstract class BackgroundRequestEvent extends \WP_Async_Request
{
    use HasHooks;

    /**
     * List of data keys to pull from $_POST and convert into ordered array of arguments
     * @var array
     */
    protected array $data_keys = [];

    /**
     * @var array Data to be added to called action
     */
    protected array $action_data = [];

    /**
     * @var string
     */
    protected $prefix = HookName::ROOT;

    const HOOK_PREFIX = 'background_event';

    protected array|\WP_Error $dispatch_response = [];

    public function __construct()
    {
        $this->action = static::HOOK_SUFFIX;
        parent::__construct();
    }

    protected function handle(): void
    {
        $this->data($_POST);
        $this->action_data = array_map(fn($key) => $this->data[$key] ?? null, $this->data_keys);
        do_action(static::hookName(), ...$this->action_data);
    }

    public function dispatchIfHooked(): array|\WP_Error
    {
        if (!has_action(static::hookName())) {
            return $this->dispatch_response;
        }
        return $this->dispatch();
    }

    public function dispatch(): array|\WP_Error
    {
        $this->dispatch_response = parent::dispatch();
        return $this->dispatch_response;
    }

    public function getDispatchResponse(): \WP_Error|array
    {
        return $this->dispatch_response;
    }

    abstract public function init(): void;
}
