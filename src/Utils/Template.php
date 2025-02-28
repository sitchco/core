<?php

declare(strict_types=1);

namespace Sitchco\Utils;

/**
 * class Template
 * @package Sitchco\Utils
 */
class Template
{
    /**
     * Locate a template and render it with scope.
     */
    public static function getTemplateScoped(string|array $template_names, array $scope = [], string $base_path = ''): ?string
    {
        $file = self::locateTemplate($template_names, $base_path);
        return $file ? self::getFileContents($file, $scope) : null;
    }

    /**
     * Retrieve the file contents and extract the scope inside the template.
     */
    public static function getFileContents(string $file, array $scope = []): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        extract($scope, EXTR_SKIP);

        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Locate the first valid template file from an array of paths.
     */
    public static function locateTemplate(string|array $template_names, string|array $base_paths = ''): string|false
    {
        $default_extension = 'php';
        $extension_whitelist = ['php', 'html', 'blade'];

        $template_names = array_map(
            fn($name) => in_array(pathinfo($name, PATHINFO_EXTENSION), $extension_whitelist, true)
                ? $name
                : "{$name}.{$default_extension}",
            (array) $template_names
        );

        $base_paths = array_filter((array) $base_paths);

        foreach ($template_names as $template_name) {
            if (is_file($template_name)) {
                return $template_name;
            }

            if ($theme_template = locate_template($template_name)) {
                return $theme_template;
            }

            foreach ($base_paths as $base_path) {
                $filename = rtrim($base_path, '/') . '/' . $template_name;
                if (is_file($filename)) {
                    return $filename;
                }
            }
        }

        return false;
    }
}
