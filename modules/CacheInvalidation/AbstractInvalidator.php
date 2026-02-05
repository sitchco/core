<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

/**
 * Base class for cache invalidators with shared flagging logic.
 */
abstract class AbstractInvalidator implements Invalidator
{
    protected bool $flagged = false;

    public function init(): void
    {
        foreach ($this->triggers() as $trigger) {
            foreach ($trigger->hooks() as $hook) {
                add_action($hook, function () {
                    if ($this->delay() === 0) {
                        $this->flush();
                    } else {
                        $this->flag();
                    }
                });
            }
        }
    }

    public function flag(): void
    {
        $shouldFlag = apply_filters(CacheInvalidation::hookName('should_flag'), true);
        if ($shouldFlag) {
            $this->flagged = true;
        }
    }

    public function isFlagged(): bool
    {
        return $this->flagged;
    }

    public function resetFlag(): void
    {
        $this->flagged = false;
    }

    public function shouldRun(): bool
    {
        foreach ($this->conditions() as $condition) {
            if (!$condition->check()) {
                return false;
            }
        }
        return true;
    }
}
