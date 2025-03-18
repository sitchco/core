<?php

namespace Sitchco\Support;

use Sitchco\Utils\Hooks;

trait HasAsyncAction
{
    use HasHooks;

    protected array $action_data = [];

    public function getName(): string
    {
        return $this->action;
    }



    protected function handle(): void
    {
        do_action(static::hookName(), ...$this->action_data);
    }
}