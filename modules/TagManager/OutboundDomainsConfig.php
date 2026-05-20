<?php

declare(strict_types=1);

namespace Sitchco\Modules\TagManager;

/**
 * Normalizes the outbound-domains config consumed by the front-end landing-params bridge.
 *
 * Two ingestion paths converge on the same wire shape:
 *   - ACF storage uses snake_case `extra_params` (CSV string parsed via parseExtraParams).
 *   - The `outbound-domains` developer filter receives the post-parse `extraParams`
 *     (camelCase array) shape and may return a modified config that is re-normalized.
 *
 * Also owns the ACF validator for the Extra Params field so the param-name pattern
 * lives in one place.
 */
class OutboundDomainsConfig
{
    public const EXTRA_PARAMS_FIELD_KEY = 'field_69b9be20813a0';

    private const PARAM_NAME_PATTERN_HUMAN = '^[A-Za-z0-9_-]+$';

    private const PARAM_NAME_PATTERN = '/' . self::PARAM_NAME_PATTERN_HUMAN . '/D';

    public static function fromSettings(TagManagerSettings $settings): array
    {
        if (!$settings->gtm_decorate_outbound) {
            return [];
        }
        $config = [];
        foreach ($settings->gtm_outbound_domains ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rawExtras = $row['extra_params'] ?? '';
            $tokens = is_string($rawExtras) ? self::parseExtraParams($rawExtras) : [];
            if ($built = self::buildEntry($row['domain'] ?? null, $tokens)) {
                [$domain, $value] = $built;
                $config[$domain] = $value;
            }
        }
        $filtered = apply_filters(TagManager::hookName('outbound-domains'), $config);
        return self::normalize($filtered, $config);
    }

    public static function validateExtraParams(bool|string $valid, mixed $value): bool|string
    {
        if ($valid !== true) {
            return $valid;
        }
        if (!is_string($value) || $value === '') {
            return $valid;
        }
        foreach (self::parseExtraParams($value) as $token) {
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

    private static function parseExtraParams(string $csv): array
    {
        $tokens = array_map('trim', explode(',', $csv));
        $tokens = array_filter($tokens, static fn($token) => $token !== '');
        return array_values(array_unique($tokens));
    }

    private static function filterParamTokens(array $tokens): array
    {
        $valid = [];
        foreach ($tokens as $token) {
            if (is_string($token) && preg_match(self::PARAM_NAME_PATTERN, $token)) {
                $valid[] = $token;
            }
        }
        return array_values(array_unique($valid));
    }

    private static function buildEntry(mixed $domain, array $tokens): ?array
    {
        if (!is_string($domain)) {
            return null;
        }
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return null;
        }
        return [$domain, ['extraParams' => self::filterParamTokens($tokens)]];
    }

    private static function normalize(mixed $filtered, array $fallback): array
    {
        if (!is_array($filtered) && !is_object($filtered)) {
            _doing_it_wrong(
                __METHOD__,
                'The outbound-domains filter must return an array of domain entries; falling back to the unfiltered ACF value.',
                \SITCHCO_CORE_VERSION,
            );
            return $fallback;
        }
        $filtered = (array) $filtered;
        $result = [];
        $collisionNoticed = false;
        foreach ($filtered as $domain => $entry) {
            if (!is_array($entry) && !is_object($entry)) {
                continue;
            }
            $entry = (array) $entry;
            $rawExtras = $entry['extraParams'] ?? [];
            $tokens = is_array($rawExtras) ? $rawExtras : [];
            if ($built = self::buildEntry($domain, $tokens)) {
                [$normalizedDomain, $value] = $built;
                if (!$collisionNoticed && array_key_exists($normalizedDomain, $result)) {
                    _doing_it_wrong(
                        __METHOD__,
                        'Filter output contains case-different domain keys that collide after normalization; later entries clobber earlier ones.',
                        \SITCHCO_CORE_VERSION,
                    );
                    $collisionNoticed = true;
                }
                $result[$normalizedDomain] = $value;
            }
        }
        return $result;
    }
}
