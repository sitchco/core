<?php

namespace Sitchco\Utils;

/**
 * Class Template
 * @package Sitchco\Utils
 */
class Template
{
    /**
     * Locate a template and render it with a given scope.
     *
     * @param string|array $templateNames Template name(s) to search for.
     * @param array $scope Variables to be extracted into the template.
     * @param string $basePath Optional base path for locating templates.
     *
     * @return string|null Rendered template content or null if not found.
     *
     * TODO: might not need this method anymore as get_template_part() has been refactored, test in Stream module (stream-activity-report.php)
     */
    public static function getTemplateScoped(
        string|array $templateNames,
        array $scope = [],
        string $basePath = ''
    ): ?string {
        if (!($file = self::locateTemplate($templateNames, $basePath))) {
            return null;
        }

        return self::getFileContents($file, $scope);
    }

    /**
     * Retrieve the file contents and extract the scope inside the template.
     *
     * @param string $file The template file path.
     * @param array $scope Variables to be extracted into the template.
     *
     * @return string|null Rendered template content or null if file is invalid.
     */
    public static function getFileContents(string $file, array $scope = []): ?string
    {
        $realFile = realpath($file);
        if (!$realFile || !is_file($realFile)) {
            error_log('Template file not found: ' . $file);
            return null;
        }

        extract($scope, EXTR_SKIP);

        ob_start();
        require $realFile;
        return ob_get_clean() ?: null;
    }

    /**
     * Locate the first valid template file from an array of paths.
     *
     * @param string|array $templateNames Template name(s) to search for.
     * @param string|array $basePaths Optional base paths for locating templates.
     *
     * @return string|false The located template file path or false if not found.
     */
    public static function locateTemplate(string|array $templateNames, string|array $basePaths = ''): string|false
    {
        $allowedExtensions = ['php', 'html', 'blade'];
        $defaultExtension = 'php';

        $templateNames = array_map(
            fn($name) => in_array(pathinfo($name, PATHINFO_EXTENSION), $allowedExtensions, true)
                ? $name
                : "{$name}.{$defaultExtension}",
            (array) $templateNames
        );

        $basePaths = array_filter((array) $basePaths);

        foreach ($templateNames as $templateName) {
            if (is_file($templateName)) {
                return $templateName;
            }

            if ($themeTemplate = locate_template($templateName)) {
                return $themeTemplate;
            }

            foreach ($basePaths as $basePath) {
                $filePath = rtrim($basePath, '/') . '/' . $templateName;
                if (is_file($filePath)) {
                    return $filePath;
                }
            }
        }

        return false;
    }
}
