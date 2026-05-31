<?php

declare(strict_types=1);

namespace MyInvoice\Tests\Unit\Sk;

use MyInvoice\Service\Sk\SkInvoiceLabels;
use PHPUnit\Framework\TestCase;

final class SkInvoiceLabelsTest extends TestCase
{
    public function testVatPayerInvoiceTitle(): void
    {
        self::assertSame('Faktúra – daňový doklad', SkInvoiceLabels::docTypeLabel('invoice', true));
    }

    public function testNonPayerInvoiceTitle(): void
    {
        self::assertSame('Faktúra', SkInvoiceLabels::docTypeLabel('invoice', false));
    }

    public function testProformaTitleIsSameForBoth(): void
    {
        self::assertSame('Zálohová faktúra', SkInvoiceLabels::docTypeLabel('proforma', true));
        self::assertSame('Zálohová faktúra', SkInvoiceLabels::docTypeLabel('proforma', false));
    }

    public function testCreditNoteTitleDependsOnVatPayer(): void
    {
        self::assertSame('Opravný daňový doklad', SkInvoiceLabels::docTypeLabel('credit_note', true));
        self::assertSame('Opravná faktúra', SkInvoiceLabels::docTypeLabel('credit_note', false));
    }

    public function testReverseChargeNoteCitesSlovakVatAct(): void
    {
        $note = SkInvoiceLabels::reverseChargeNote();
        self::assertStringContainsString('Prenesenie daňovej povinnosti', $note);
        self::assertStringContainsString('§ 69', $note);
        self::assertStringContainsString('222/2004', $note);
    }

    public function testNonPayerNote(): void
    {
        self::assertSame('Neplatiteľ DPH', SkInvoiceLabels::nonPayerNote());
    }

    public function testUnknownTypeFallsBackToFaktura(): void
    {
        self::assertSame('Faktúra', SkInvoiceLabels::docTypeLabel('mystery', true));
    }
}
