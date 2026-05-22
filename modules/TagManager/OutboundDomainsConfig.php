<?php

declare(strict_types=1);

namespace Sitchco\Modules\TagManager;

/**
 * Value object for the outbound-domains config consumed by the front-end outbound-decorator bridge.
 *
 * Two ingestion paths converge on the same wire shape:
 *   - ACF storage uses snake_case `extra_params` (CSV string parsed via ExtraParamsField::parse).
 *   - The `outbound-domains` developer filter receives the post-parse `extraParams`
 *     (camelCase array) shape and may return a modified config that is re-normalized.
 */
final class OutboundDomainsConfig
{
    /**
     * @param array<string, array{extraParams: string[]}> $entries
     */
    private function __construct(private readonly array $entries) {}

    public static function fromSettings(TagManagerSettings $settings): self
    {
        if (!$settings->gtm_decorate_outbound) {
            return new self([]);
        }
        $entries = [];
        foreach ($settings->gtm_outbound_domains ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rawExtras = $row['extra_params'] ?? '';
            $tokens = is_string($rawExtras) ? ExtraParamsField::parse($rawExtras) : [];
            $built = self::buildEntry($row['domain'] ?? null, $tokens);
            if ($built === null) {
                continue;
            }
            [$domain, $value] = $built;
            $entries[$domain] = $value;
        }
        $fallback = new self($entries);
        $filtered = apply_filters(TagManager::hookName('outbound-domains'), $entries);
        return self::fromFilterReturn($filtered, $fallback);
    }

    public static function fromFilterReturn(mixed $filtered, self $fallback): self
    {
        if (!is_array($filtered) && !is_object($filtered)) {
            _doing_it_wrong(
                __METHOD__,
                'The outbound-domains filter must return an array of domain entries; falling back to the unfiltered ACF value.',
                \SITCHCO_CORE_VERSION,
            );
            return $fallback;
        }
        $result = [];
        $collisionNoticed = false;
        foreach ((array) $filtered as $domain => $entry) {
            if (!is_array($entry) && !is_object($entry)) {
                continue;
            }
            $rawExtras = ((array) $entry)['extraParams'] ?? [];
            $tokens = is_array($rawExtras) ? $rawExtras : [];
            $built = self::buildEntry($domain, $tokens);
            if ($built === null) {
                continue;
            }
            [$normalizedDomain, $value] = $built;
            if (!$collisionNoticed && array_key_exists($normalizedDomain, $result)) {
                self::warnCollision();
                $collisionNoticed = true;
            }
            $result[$normalizedDomain] = $value;
        }
        return new self($result);
    }

    public function toInlineData(): array
    {
        return ['domains' => $this->entries];
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * @return array{0: string, 1: array{extraParams: string[]}}|null
     */
    private static function buildEntry(mixed $domain, array $tokens): ?array
    {
        if (!is_string($domain)) {
            return null;
        }
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return null;
        }
        return [$domain, ['extraParams' => ExtraParamsField::filterTokens($tokens)]];
    }

    private static function warnCollision(): void
    {
        _doing_it_wrong(
            __CLASS__ . '::fromFilterReturn',
            'Filter output contains case-different domain keys that collide after normalization; later entries clobber earlier ones.',
            \SITCHCO_CORE_VERSION,
        );
    }
}
