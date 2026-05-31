<?php

declare(strict_types=1);

namespace MyInvoice\Service\Registry;

/**
 * Slovak business-registry (RPO — Register právnických osôb, Štatistický úrad SR) lookup.
 * Returns the SAME normalized array shape as AresClient::lookup() so existing
 * "load from registry" endpoints/frontend work unchanged.
 *
 * The HTTP fetch is injected (callable(string $url): ?string) so the JSON->fields
 * mapping is unit-testable without network access.
 */
final class RpoClient implements RegistryLookup
{
    /** @var callable(string):?string */
    private $fetch;

    /** @param (callable(string):?string)|null $fetch */
    public function __construct(
        private readonly string $apiBase,
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

        $url = rtrim($this->apiBase, '/') . '/entities?identifier=' . $ico;
        $raw = ($this->fetch)($url);
        if ($raw === null || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $entity = $data['results'][0] ?? null;
        if (!is_array($entity)) {
            return null;
        }

        return $this->normalize($ico, $entity);
    }

    /**
     * @param array<string,mixed> $e
     * @return array<string,mixed>
     */
    private function normalize(string $ico, array $e): array
    {
        $addr   = $e['addresses'][0] ?? [];
        $street = trim(((string) ($addr['street'] ?? '')) . ' ' . ((string) ($addr['buildingNumber'] ?? '')));
        $vatNos = $e['vatNumbers'] ?? [];

        return [
            'company_name' => (string) ($e['fullNames'][0]['value'] ?? ''),
            'ic'           => $ico,
            'dic'          => (string) ($e['taxNumbers'][0] ?? ''),
            'street'       => $street,
            'city'         => (string) ($addr['municipality']['value'] ?? ''),
            'zip'          => (string) ($addr['postalCodes'][0] ?? ''),
            'country_iso2' => (string) ($addr['country']['code'] ?? 'SK'),
            'is_vat_payer' => !empty($vatNos),
            'date_active'  => (string) ($e['establishment'] ?? ''),
            'legal_form'   => (string) ($e['legalForms'][0]['value'] ?? ''),
            'vat_id'       => (string) ($vatNos[0] ?? ''),
        ];
    }

    /** @return callable(string):?string */
    private static function defaultFetch(): callable
    {
        return static function (string $url): ?string {
            $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
            $body = @file_get_contents($url, false, $ctx);
            return $body === false ? null : $body;
        };
    }
}
