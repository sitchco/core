<?php

namespace Sitchco\Support;

use Sitchco\Utils\Hooks;

trait HasAsyncAction
{
    use HasHooks;

    public function getName(): string
    {
        return $this->action;
    }



    protected function handle(): void
    {
        do_action(static::hookName());
    }
}