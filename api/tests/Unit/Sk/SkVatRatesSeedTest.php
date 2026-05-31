<?php

declare(strict_types=1);

namespace MyInvoice\Tests\Unit\Sk;

use PHPUnit\Framework\TestCase;

final class SkVatRatesSeedTest extends TestCase
{
    public function testMigrationSeedsAllSlovakRates(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../../../db/migrations/0083_vat_rates_slovak.sql');
        self::assertIsString($sql);

        foreach (['SK-23', 'SK-19', 'SK-5', 'SK-0', 'SK-RC'] as $code) {
            self::assertStringContainsString("'$code'", $sql, "missing rate $code");
        }
        // Reverse charge row must set the reverse-charge flag.
        self::assertMatchesRegularExpression(
            "/'SK-RC',\s*0\.00,\s*'SK'.*?,\s*0,\s*1,/s",
            $sql,
            'SK-RC must be country SK, rate 0, is_default 0, is_reverse_charge 1'
        );
        // Standard rate must be the SK default.
        self::assertMatchesRegularExpression(
            "/'SK-23',\s*23\.00,\s*'SK'.*?,\s*1,\s*0,/s",
            $sql,
            'SK-23 must be the SK default (is_default 1)'
        );
        self::assertStringContainsString('INSERT IGNORE', $sql, 'use INSERT IGNORE to stay idempotent');
    }
}
