<?php

declare(strict_types=1);

namespace Sitchco\Modules\TagManager;

/**
 * ACF "extra_params" field: key, validation, parsing, and filter registration.
 *
 * Owns the param-name regex so the same pattern guards both the ACF validator
 * and the runtime token filter consumed by OutboundDomainsConfig.
 */
final class ExtraParamsField
{
    public const FIELD_KEY = 'field_69b9be20813a0';

    private const PARAM_NAME_PATTERN_HUMAN = '^[A-Za-z0-9_-]+$';

    private const PARAM_NAME_PATTERN = '/' . self::PARAM_NAME_PATTERN_HUMAN . '/D';

    public static function register(): void
    {
        add_filter('acf/validate_value/key=' . self::FIELD_KEY, [self::class, 'validateExtraParams'], 10, 2);
    }

    public static function unregister(): void
    {
        remove_filter('acf/validate_value/key=' . self::FIELD_KEY, [self::class, 'validateExtraParams'], 10);
    }

    public static function validateExtraParams(bool|string $valid, mixed $value): bool|string
    {
        if ($valid !== true) {
            return $valid;
        }
        if (!is_string($value) || $value === '') {
            return $valid;
        }
        foreach (self::parse($value) as $token) {
            if (!preg_match(self::PARAM_NAME_PATTERN, $token)) {
                return sprintf(
                    'Invalid param name "%s". Param names must match the pattern %s.',
                    $token,
                    self::PARAM_NAME_PATTERN_HUMAN,
                );
            }
        }
        return $valid;
    }

    public static function parse(string $csv): array
    {
        $tokens = array_map('trim', explode(',', $csv));
        $tokens = array_filter($tokens, static fn($token) => $token !== '');
        return array_values(array_unique($tokens));
    }

    public static function filterTokens(array $tokens): array
    {
        $valid = [];
        foreach ($tokens as $token) {
            if (is_string($token) && preg_match(self::PARAM_NAME_PATTERN, $token)) {
                $valid[] = $token;
            }
        }
        return array_values(array_unique($valid));
    }
}
