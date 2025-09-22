<?php

namespace Sitchco\Utils;

use Timber\Timber;

class TimberUtil
{
    static function includeWithContext(string $template, array $additional_context = []): string
    {
        $context = Timber::context();
        $context = array_merge($context, $additional_context);
        return static::compileWithContext($template, $context);
    }

    static function compileWithContext(string $template, array $context, $filter_key = null): bool|string
    {
        if (!$filter_key) {
            $filter_key = str_replace('.twig', '', $template);
        }
        $hookName = Hooks::name('template-context', $filter_key);
        $context = apply_filters($hookName, $context, $filter_key);
        return Timber::compile($template, $context);
    }

    static function addContext(string $template, array|callable $additionalContext, int $priority = 10): void
    {
        $callback = is_callable($additionalContext) ? $additionalContext : fn($context) => array_merge($context, $additionalContext);
        add_filter(
            Hooks::name('template-context', $template),
            $callback,
            $priority
        );
    }
}
