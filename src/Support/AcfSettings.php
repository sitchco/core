<?php

namespace Sitchco\Support;

use Sitchco\Utils\Hooks;

class AcfSettings
{
    public function addSettingsTab(string $name, string $label, callable $callback): void
    {
        add_filter('acf/post_type/additional_settings_tabs', function ($tabs) use ($name, $label) {
            $tabs[$name] = $label;
            return $tabs;
        });
        add_action("acf/post_type/render_settings_tab/$name", $callback);
    }

    public function addSettingsField(string $name, array $settings, array $values): void
    {
        /* Hook: sitchco/acf_settings_field/settings_field/{name} */
        acf_render_field_wrap(
            apply_filters(
                Hooks::name('acf_settings_field', $name),
                array_merge(
                    [
                        'name' => $name,
                        'prefix' => 'acf_post_type',
                        'value' => $values[$name] ?? '',
                    ],
                    $settings,
                ),
                $values,
            ),
        );
    }

    public function extendSettingsField(string $name, callable $callback): void
    {
        add_filter(Hooks::name('acf_settings_field', $name), $callback, 10, 2);
    }
}
