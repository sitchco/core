<?php

declare(strict_types=1);

namespace Sitchco\Modules\TagManager;

/**
 * Immutable value object holding the normalized outbound-domains wire payload.
 *
 * Construction lives in OutboundDomainsResolver, which owns the ACF + filter
 * boundary work; this class is intentionally inert state.
 */
final class OutboundDomainsConfig
{
    /**
     * @param array<string, array{extraParams: string[]}> $entries
     */
    public function __construct(private readonly array $entries) {}

    public function toInlineData(): array
    {
        return ['domains' => $this->entries];
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
