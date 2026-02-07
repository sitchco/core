<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

use Sitchco\Utils\Logger;

/**
 * Base class for cache invalidators with shared flagging logic.
 */
abstract class AbstractInvalidator implements Invalidator
{
    protected bool $flagged = false;
    private bool $suppressionLogged = false;
    private bool $shutdownRegistered = false;

    public function init(): void
    {
        foreach ($this->triggers() as $trigger) {
            foreach ($trigger->hooks() as $hook) {
                add_action($hook, function () use ($hook) {
                    if ($this->delay() === 0) {
                        $this->flag($hook);
                        if (!$this->shutdownRegistered) {
                            $this->shutdownRegistered = true;
                            add_action('shutdown', function () {
                                if ($this->flagged) {
                                    Logger::debug("[Cache] {$this->name()} flushing at shutdown");
                                    $this->flush();
                                }
                            });
                        }
                    } else {
                        $this->flag($hook);
                    }
                });
            }
        }
    }

    public function flag(?string $hook = null): void
    {
        $shouldFlag = apply_filters(CacheInvalidation::hookName('should_flag'), true);
        if ($shouldFlag) {
            if (!$this->flagged) {
                Logger::debug("[Cache] {$this->name()} flagged by {$hook}");
            }
            $this->flagged = true;
        } elseif (!$this->suppressionLogged) {
            Logger::debug("[Cache] {$this->name()} flag suppressed (should_flag=false)");
            $this->suppressionLogged = true;
        }
    }

    protected function name(): string
    {
        return substr(strrchr(static::class, '\\'), 1);
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
