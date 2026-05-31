<?php

declare(strict_types=1);

namespace MyInvoice\Service\Sk;

/**
 * Single source of truth for Slovak invoice legal wording (Stage 1).
 * Mirrors the doc-type set used by InvoicePdfRenderer::docTypeLabel().
 */
final class SkInvoiceLabels
{
    public static function docTypeLabel(string $invoiceType, bool $isVatPayer): string
    {
        return match ($invoiceType) {
            'invoice'      => $isVatPayer ? 'Faktúra – daňový doklad' : 'Faktúra',
            'proforma'     => 'Zálohová faktúra',
            'credit_note'  => $isVatPayer ? 'Opravný daňový doklad' : 'Opravná faktúra',
            'cancellation' => 'Storno (interné)',
            default        => 'Faktúra',
        };
    }

    /** Title used on the PDF header, e.g. "Faktúra 2026-001". */
    public static function docTitle(string $invoiceType): string
    {
        return match ($invoiceType) {
            'proforma'     => 'Zálohová faktúra',
            'credit_note'  => 'Dobropis',
            'cancellation' => 'Storno',
            default        => 'Faktúra',
        };
    }

    public static function reverseChargeNote(): string
    {
        return 'Prenesenie daňovej povinnosti podľa § 69 zákona č. 222/2004 Z. z. o DPH.';
    }

    public static function nonPayerNote(): string
    {
        return 'Neplatiteľ DPH';
    }
}
