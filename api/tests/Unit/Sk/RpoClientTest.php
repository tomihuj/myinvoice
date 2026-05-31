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
        // Inject a fake fetcher returning the ORSF fixture, so no network is used.
        return new RpoClient('https://api.orsf.sk/v1', static fn (string $url): ?string => $json);
    }

    public function testRejectsNonNumericOrWrongLengthIco(): void
    {
        self::assertNull($this->client()->lookup('abc'));
        self::assertNull($this->client()->lookup('123'));
    }

    public function testMapsOrsfResponseToAresShape(): void
    {
        $r = $this->client()->lookup('31333532');
        self::assertIsArray($r);
        self::assertTrue($r['found']);                 // AresClient-style envelope
        $d = $r['data'];
        self::assertSame('ESET, spol. s r.o.', $d['company_name']);
        self::assertSame('31333532', $d['ic']);
        self::assertSame('2020317068', $d['dic']);
        self::assertSame('Einsteinova 24', $d['street']);
        self::assertStringContainsString('Bratislava', $d['city']);
        self::assertSame('85101', $d['zip']);
        self::assertSame('SK', $d['country_iso2']);
        self::assertTrue($d['is_vat_payer']);          // icdph present => VAT payer
        self::assertSame('SK2020317068', $d['vat_id']);
        self::assertSame('po', $d['taxpayer_type']);   // Obchodný register => právnická osoba
        self::assertStringContainsString('Obchodný register', $d['commercial_register']);
    }

    public function testReturnsNullWhenFetchFails(): void
    {
        // Network/unavailable => null => AresLookupAction returns 503.
        $c = new RpoClient('https://api.orsf.sk/v1', static fn (string $url): ?string => null);
        self::assertNull($c->lookup('31333532'));
    }

    public function testReturnsNotFoundOnErrorPayload(): void
    {
        // ORSF 404 / error payload has no `ico` field => {found:false}.
        $c = new RpoClient('https://api.orsf.sk/v1', static fn (string $url): ?string => '{"statusCode":404,"message":"Not Found"}');
        self::assertSame(['found' => false], $c->lookup('99999999'));
    }
}
