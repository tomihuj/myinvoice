<?php

declare(strict_types=1);

namespace MyInvoice\Tests\Unit\Sk;

use MyInvoice\Service\Registry\RpoClient;
use PHPUnit\Framework\TestCase;

final class RpoClientTest extends TestCase
{
    private function client(): RpoClient
    {
        $json = (string) file_get_contents(__DIR__ . '/../../Fixtures/rpo_response.json');
        // Inject a fake fetcher returning the fixture, so no network is used.
        return new RpoClient('https://api.statistics.sk/rpo/v1', static fn (string $url): ?string => $json);
    }

    public function testRejectsNonNumericOrWrongLengthIco(): void
    {
        self::assertNull($this->client()->lookup('abc'));
        self::assertNull($this->client()->lookup('123'));
    }

    public function testMapsRpoResponseToAresShape(): void
    {
        $r = $this->client()->lookup('31333532');
        self::assertIsArray($r);
        self::assertSame('ESET, spol. s r.o.', $r['company_name']);
        self::assertSame('31333532', $r['ic']);
        self::assertSame('2020317068', $r['dic']);
        self::assertSame('Einsteinova 24', $r['street']);
        self::assertSame('Bratislava', $r['city']);
        self::assertSame('851 01', $r['zip']);
        self::assertSame('SK', $r['country_iso2']);
        self::assertTrue($r['is_vat_payer']);          // vatNumbers present => payer
    }

    public function testReturnsNullWhenFetchFails(): void
    {
        $c = new RpoClient('https://api.statistics.sk/rpo/v1', static fn (string $url): ?string => null);
        self::assertNull($c->lookup('31333532'));
    }
}
