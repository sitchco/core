<?php

namespace Sitchco\Modules\AdvancedCustomFields\Options;

use ReflectionClass;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleRegistry;
use Sitchco\Support\FilePath;
use Sitchco\Utils\Acf;

class AcfOptions extends Module
{
    public function __construct(protected ModuleRegistry $moduleRegistry) {}

    public function init(): void
    {
        add_filter('acf/prepare_field_group_for_export', function ($field_group) {
            $is_options_page = $this->isFieldGroupOptionsPage($field_group);
            if (!$is_options_page) {
                return $field_group;
            }
            $this->generateClassFromFieldGroup($field_group);
            return $field_group;
        });
    }

    protected function isFieldGroupOptionsPage(array $field_group): string
    {
        foreach ($field_group['location'] as $rule_group) {
            foreach ($rule_group as $rule) {
                if ($rule['param'] === 'options_page' && $rule['operator'] === '==') {
                    return true;
                }
            }
        }
        return false;
    }

    protected function getTargetModule(string $key): ?Module
    {
        $modules = Acf::findModulesWithJsonPath($this->moduleRegistry->getActiveModules());
        $matchedModule = null;
        foreach ($modules as $module) {
            if (Acf::findJsonFile([$module->path('acf-json')], $key)) {
                $matchedModule = $module;
                break;
            }
        }
        return $matchedModule;
    }

    protected function generateClassFromFieldGroup(array $field_group): void
    {
        $targetModule = $this->getTargetModule($field_group['key']);
        if ($targetModule) {
            // Field group associated with a module
            $namespace = (new ReflectionClass($targetModule))->getNamespaceName();
            $targetPath = $targetModule->path();
        } else {
            // Child theme default location
            $targetPath = FilePath::create(get_stylesheet_directory() . '/src');
            if (!$targetPath->append('Options')->exists()) {
                mkdir($targetPath->value() . '/Options');
            }
            $targetPath = $targetPath->append('Options');
            $namespace = 'Sitchco\App\Options';
        }

        $class_name = preg_replace('/\W/', '', $field_group['title']);
        $target = $targetPath->append("$class_name.php");
        $template = file_exists($target) ?
            file_get_contents($target->value()) :
            file_get_contents(__DIR__ . '/Options.php.tpl');
        $property_lines = array_map(function ($field) {
            $type = $this->acfFieldTypeToPhpType($field['type']);
            return " * @property $type \${$field['name']} {$field['label']}";
        }, $field_group['fields']);
        $template = str_replace(
            ['[namespace]','[class_name]'],
            [$namespace, $class_name],
            $template
        );
        $template = preg_replace(
            '#(\[properties])[\s\S]+(\[/properties])#',
            implode("\n", array_merge(['$1'], $property_lines, ['$2'])),
            $template
        );
        file_put_contents($target->value(), $template);
    }

    /**
     * Map an ACF field type to a PHPDoc type.
     *
     * @param string $acf_type The ACF field type (e.g. 'text', 'image', 'true_false').
     * @return string The corresponding PHP type for annotations.
     */
    protected function acfFieldTypeToPhpType(string $acf_type): string {
        return match ($acf_type) {
            // Simple scalars
            'text', 'textarea', 'email', 'url', 'password', 'wysiwyg', 'select' => 'string',
            'number', 'range' => 'float',
            'true_false', 'checkbox', 'radio' => 'bool',
            'date_picker', 'date_time_picker', 'time_picker' => 'string', // could also be DateTimeInterface

            // Media and attachments
            'image', 'file' => 'array', // or 'array|string|int' depending on return format

            // Post and relationship fields
            'post_object', 'page_link', 'relationship' => 'WP_Post|array',
            'taxonomy' => 'array|string',

            // Complex types
            'group', 'repeater', 'flexible_content' => 'array',

            // Default fallback
            default => 'mixed',
        };
    }
}
