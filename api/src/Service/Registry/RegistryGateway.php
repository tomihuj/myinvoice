<?php

declare(strict_types=1);

namespace MyInvoice\Service\Registry;

/**
 * Routes a company-registry lookup to the Czech (ARES) or Slovak (RPO) source
 * based on the configured country profile. Both sources implement RegistryLookup
 * and return the same shape, so callers stay source-agnostic.
 */
final class RegistryGateway implements RegistryLookup
{
    public function __construct(
        private readonly string $profile,        // 'CZ' | 'SK'
        private readonly RegistryLookup $cz,     // AresClient
        private readonly RegistryLookup $sk,     // RpoClient
    ) {}

    /** @return array<string,mixed>|null */
    public function lookup(string $ic): ?array
    {
        return $this->profile === 'SK'
            ? $this->sk->lookup($ic)
            : $this->cz->lookup($ic);
    }
}
