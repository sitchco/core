<?php

declare(strict_types=1);

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

class AcfLifecycle extends Module
{
    public const HOOK_SUFFIX = 'acf';

    public function init(): void
    {
        add_action('acf/save_post', [$this, 'onSavePost']);
    }

    public function onSavePost($post_id): void
    {
        if (is_numeric($post_id)) {
            return;
        }

        $entityType = $this->resolveEntityType((string) $post_id);

        if ($entityType === null) {
            return;
        }

        do_action(self::hookName('fields_saved'), $entityType, $post_id);
    }

    private function resolveEntityType(string $post_id): ?string
    {
        if ($post_id === 'options' || str_starts_with($post_id, 'options_')) {
            return 'options';
        }

        if (str_starts_with($post_id, 'user_')) {
            return 'user';
        }

        if (str_starts_with($post_id, 'term_')) {
            return 'term';
        }

        return null;
    }
}
