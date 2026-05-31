<?php

declare(strict_types=1);

namespace MyInvoice\Tests\Unit\Service\Bank;

use MyInvoice\Infrastructure\Config\Config;
use MyInvoice\Infrastructure\Database\Connection;
use MyInvoice\Service\Bank\StatementMatcher;
use MyInvoice\Service\Invoice\FinalFromProformaCreator;
use PHPUnit\Framework\TestCase;

/**
 * Testuje čistou měnovou logiku párování — expectedMatch() (private, přes reflexi,
 * stejně jako PdfTotalExtractorTest::parseMoney). DB se nedotýká, takže běží i v CI.
 *
 * expectedMatch převede částku faktury do měny transakce + vrátí tolerance:
 *   - stejná měna / neznámá měna tx → přímé porovnání (0.05 / 1.0 Kč),
 *   - CZK platba cizoměnové faktury → částka × kurz faktury, relativní tolerance 4 %,
 *   - cizí účet × jiná měna faktury → null (nepárovat).
 */
final class StatementMatcherTest extends TestCase
{
    private StatementMatcher $matcher;

    protected function setUp(): void
    {
        // expectedMatch nepoužívá DB ani finalCreator → stuby stačí.
        $this->matcher = new StatementMatcher(
            $this->createStub(Connection::class),
            $this->createStub(FinalFromProformaCreator::class),
        );
    }

    /**
     * @return array{expected: float, exact: float, partial: float}|null
     */
    private function expectedMatch(float $amount, string $invCcy, float $rate, ?string $txCcy): ?array
    {
        $ref = new \ReflectionMethod($this->matcher, 'expectedMatch');
        /** @var array{expected: float, exact: float, partial: float}|null $r */
        $r = $ref->invoke($this->matcher, $amount, $invCcy, $rate, $txCcy);
        return $r;
    }

    public function testSameCurrencyComparesDirectly(): void
    {
        $m = $this->expectedMatch(2520.0, 'EUR', 24.36, 'EUR');
        self::assertNotNull($m);
        self::assertSame(2520.0, $m['expected']);
        self::assertSame(0.05, $m['exact']);
        self::assertSame(1.0, $m['partial']);
    }

    public function testNullTxCurrencyFallsBackToDirect(): void
    {
        // Legacy výpisy bez měny — porovnej napřímo (zpětná kompatibilita).
        $m = $this->expectedMatch(1000.0, 'EUR', 25.0, null);
        self::assertNotNull($m);
        self::assertSame(1000.0, $m['expected']);
        self::assertSame(0.05, $m['exact']);
    }

    public function testCzkPaymentOfForeignInvoiceConvertsByRate(): void
    {
        // 2520 EUR × 25 = 63000 CZK; tolerance 4 % = 2520 CZK; cross-currency = jeden tier.
        $m = $this->expectedMatch(2520.0, 'EUR', 25.0, 'CZK');
        self::assertNotNull($m);
        self::assertSame(63000.0, $m['expected']);
        self::assertEqualsWithDelta(2520.0, $m['exact'], 0.001);
        self::assertSame($m['exact'], $m['partial']);
    }

    public function testCzkPaymentOfCzkInvoiceIsDirect(): void
    {
        $m = $this->expectedMatch(3049.2, 'CZK', 1.0, 'CZK');
        self::assertNotNull($m);
        self::assertSame(3049.2, $m['expected']);
        self::assertSame(0.05, $m['exact']);
    }

    public function testForeignAccountWithDifferentInvoiceCurrencyIsNull(): void
    {
        // EUR výpis × USD faktura — bez kurzu transakce nepřevedeme.
        self::assertNull($this->expectedMatch(1000.0, 'USD', 1.0, 'EUR'));
    }

    public function testForeignAccountWithCzkInvoiceIsNull(): void
    {
        // EUR výpis × CZK faktura stejného VS+amount — původní nebezpečný případ zůstává unmatched.
        self::assertNull($this->expectedMatch(1000.0, 'CZK', 1.0, 'EUR'));
    }

    public function testCurrencyComparisonIsCaseInsensitive(): void
    {
        $m = $this->expectedMatch(100.0, 'eur', 25.0, 'EUR');
        self::assertNotNull($m);
        self::assertSame(100.0, $m['expected']); // shoda měny i přes velikost písmen → přímo
    }

    public function testMissingRateFallsBackToOneToOne(): void
    {
        // Chybějící kurz (0) → 1:1 fallback (lepší než dělit nulou / vůbec nepárovat).
        $m = $this->expectedMatch(500.0, 'EUR', 0.0, 'CZK');
        self::assertNotNull($m);
        self::assertSame(500.0, $m['expected']);
    }

    public function testFxToleranceHasAbsoluteFloor(): void
    {
        // Velmi malá částka: 4 % by bylo < 0.05, použije se absolutní floor (EXACT_MATCH_TOLERANCE).
        $m = $this->expectedMatch(0.50, 'EUR', 1.0, 'CZK'); // czk=0.5, 4 % = 0.02 < 0.05
        self::assertNotNull($m);
        self::assertSame(0.5, $m['expected']);
        self::assertSame(0.05, $m['exact']);
    }

    // ── SK profile: tuzemská měna je EUR (country.local_currency) ────────────

    private function matcherWithLocalCurrency(string $localCurrency): StatementMatcher
    {
        return new StatementMatcher(
            $this->createStub(Connection::class),
            $this->createStub(FinalFromProformaCreator::class),
            Config::fromArray(['country' => ['local_currency' => $localCurrency]]),
        );
    }

    /** @return array{expected: float, exact: float, partial: float}|null */
    private function expectedMatchOn(StatementMatcher $matcher, float $amount, string $invCcy, float $rate, ?string $txCcy): ?array
    {
        $ref = new \ReflectionMethod($matcher, 'expectedMatch');
        /** @var array{expected: float, exact: float, partial: float}|null $r */
        $r = $ref->invoke($matcher, $amount, $invCcy, $rate, $txCcy);
        return $r;
    }

    public function testSkProfileTreatsEurAsLocalCurrency(): void
    {
        // SK profil (local_currency='EUR'): EUR platba cizoměnové (CZK) faktury se přepočte
        // kurzem faktury — na CZ profilu by EUR×CZK vrátilo null.
        $matcher = $this->matcherWithLocalCurrency('EUR');
        $m = $this->expectedMatchOn($matcher, 100.0, 'CZK', 0.04, 'EUR'); // 100 CZK × 0.04 = 4 EUR
        self::assertNotNull($m, 'EUR je tuzemská na SK profilu → CZK faktura se přepočte, ne null');
        self::assertEqualsWithDelta(4.0, $m['expected'], 0.001);
        self::assertSame($m['exact'], $m['partial']); // cross-currency = jeden tier
    }

    public function testSkProfileRejectsForeignToForeign(): void
    {
        // CZK je na SK profilu cizí měna → CZK platba USD faktury = null (bez kurzu nepárovat).
        $matcher = $this->matcherWithLocalCurrency('EUR');
        self::assertNull($this->expectedMatchOn($matcher, 100.0, 'USD', 1.0, 'CZK'));
    }
}
