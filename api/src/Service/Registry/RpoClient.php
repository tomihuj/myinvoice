<?php

declare(strict_types=1);

namespace MyInvoice\Service\Registry;

/**
 * Slovak company-registry lookup via ORSF (https://api.orsf.sk) — a free,
 * key-less REST aggregator over the official RPO / ORSR / RUZ registers.
 *
 * We use ORSF rather than the official statistics.sk RPO API because the latter
 * regularly times out (>50 s, 0 bytes) on synchronous IČO lookups from a web form,
 * which made the "load company by IČO" button dead on the SK profile.
 *
 * Returns the SAME normalized array shape as AresClient::lookup() so the existing
 * "load from registry" endpoints/frontend (Setup, Settings, Codebooks) work unchanged.
 *
 * The HTTP fetch is injected (callable(string $url): ?string) so the JSON→fields
 * mapping is unit-testable without network access.
 */
final class RpoClient implements RegistryLookup
{
    /** @var callable(string):?string */
    private $fetch;

    /** @param (callable(string):?string)|null $fetch */
    public function __construct(
        private readonly string $apiBase,   // e.g. https://api.orsf.sk/v1
        ?callable $fetch = null,
    ) {
        $this->fetch = $fetch ?? self::defaultFetch();
    }

    /**
     * @return array<string,mixed>|null  normalized company record, or null if not found / unavailable
     */
    public function lookup(string $ico): ?array
    {
        $ico = preg_replace('/\D/', '', $ico) ?? '';
        if (strlen($ico) !== 8) {
            return null;
        }

        $url = rtrim($this->apiBase, '/') . '/companies/' . $ico;
        $raw = ($this->fetch)($url);
        if ($raw === null || $raw === '') {
            return null;   // network/unavailable → AresLookupAction returns 503
        }

        $e = json_decode($raw, true);
        // ORSF returns the company object directly; a 404/error payload has no `ico`.
        if (!is_array($e) || empty($e['ico'])) {
            return ['found' => false];
        }

        // Mirror AresClient::lookup() envelope so the same actions/frontend work:
        // {found: true, data: {...normalized fields...}}.
        return ['found' => true, 'data' => $this->normalize($ico, $e)];
    }

    /**
     * @param array<string,mixed> $e
     * @return array<string,mixed>
     */
    private function normalize(string $ico, array $e): array
    {
        $addr = is_array($e['address'] ?? null) ? $e['address'] : [];
        $register = trim(((string) ($e['register'] ?? '')) . ' ' . ((string) ($e['registerNumber'] ?? '')));
        // Živnostenský register → fyzická osoba (FO); inak právnická osoba (PO).
        $isTrade = stripos((string) ($e['register'] ?? ''), 'ivnosten') !== false;

        return [
            'company_name'        => (string) ($e['name'] ?? ''),
            'ic'                  => (string) ($e['ico'] ?? $ico),
            'dic'                 => (string) ($e['dic'] ?? ''),
            'street'              => (string) ($e['street'] ?? ($addr['street'] ?? '')),
            'city'                => (string) ($e['city'] ?? ($addr['city'] ?? '')),
            'zip'                 => (string) ($e['psc'] ?? ($e['postalCode'] ?? ($addr['postalCode'] ?? ''))),
            'country_iso2'        => strtoupper((string) ($e['countryCode'] ?? 'SK')),
            'is_vat_payer'        => !empty($e['icdph']),
            'vat_id'              => (string) ($e['icdph'] ?? ''),
            'legal_form'          => (string) ($e['legalForm'] ?? ''),
            'commercial_register' => $register,
            'taxpayer_type'       => $isTrade ? 'fo' : 'po',
            'date_active'         => (string) ($e['establishedOn'] ?? ''),
        ];
    }

    /** @return callable(string):?string */
    private static function defaultFetch(): callable
    {
        return static function (string $url): ?string {
            $ctx = stream_context_create(['http' => [
                'timeout'       => 10,
                'ignore_errors' => true,
                'header'        => "Accept: application/json\r\nUser-Agent: MyInvoice-SK/1.0\r\n",
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            return $body === false ? null : $body;
        };
    }
}
