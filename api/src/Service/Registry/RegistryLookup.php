<?php

declare(strict_types=1);

namespace MyInvoice\Service\Registry;

/**
 * A company-registry source that resolves a national company id to a normalized
 * record. Implemented by AresClient (CZ) and RpoClient (SK); both return the same
 * array shape so callers are source-agnostic.
 */
interface RegistryLookup
{
    /** @return array<string,mixed>|null */
    public function lookup(string $ic): ?array;
}
