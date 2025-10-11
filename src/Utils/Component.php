<?php

declare(strict_types=1);

namespace Sitchco\Utils;

/**
 * Class Component
 * @package Sitchco\Utils
 */
class Component
{
    /**
     * Generates a list of CSS classes for a component.
     *
     * @param string   $base           Component container name.
     * @param string[] $classModifiers Optional list of class modifiers.
     *
     * @return string The formatted class string.
     */
    public static function componentClasses(string $base, array $classModifiers = []): string
    {
        if ($classModifiers === []) {
            return $base;
        }

        return sprintf('%s %s', $base, implode(' ', preg_filter('/^/', "{$base}--", array_filter($classModifiers))));
    }

    /**
     * Generates an HTML attribute string for a component.
     *
     * @param string   $base              Component container name.
     * @param string[] $classModifiers    Optional list of class modifiers.
     * @param array    $elementAttributes Additional element attributes.
     *
     * @return string The formatted attribute string.
     */
    public static function componentAttributes(
        string $base,
        array $classModifiers = [],
        array $elementAttributes = [],
    ): string {
        return ArrayUtil::toAttributes(self::componentAttributesArray($base, $classModifiers, $elementAttributes));
    }

    /**
     * Builds an array of attributes for a component.
     *
     * @param string   $base              Component container name.
     * @param string[] $classModifiers    Optional list of class modifiers.
     * @param array    $elementAttributes Additional element attributes.
     *
     * @return array<string, string> The attribute array.
     */
    public static function componentAttributesArray(
        string $base,
        array $classModifiers = [],
        array $elementAttributes = [],
    ): array {
        $core = ['data-gtm' => $base];

        if ($classes = self::componentClasses($base, $classModifiers)) {
            $core['class'] = $classes;
        }

        if (isset($elementAttributes['class'])) {
            $core['class'] .= " {$elementAttributes['class']}";
            unset($elementAttributes['class']);
        }

        if ($elementAttributes !== []) {
            $core = array_merge($core, $elementAttributes);
        }

        return $core;
    }
}
