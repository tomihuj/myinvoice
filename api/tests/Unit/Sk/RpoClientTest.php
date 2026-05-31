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
        self::assertSame('ESET, spol. s r.o.', $r['company_name']);
        self::assertSame('31333532', $r['ic']);
        self::assertSame('2020317068', $r['dic']);
        self::assertSame('Einsteinova 24', $r['street']);
        self::assertStringContainsString('Bratislava', $r['city']);
        self::assertSame('85101', $r['zip']);
        self::assertSame('SK', $r['country_iso2']);
        self::assertTrue($r['is_vat_payer']);          // icdph present => VAT payer
        self::assertSame('SK2020317068', $r['vat_id']);
        self::assertSame('po', $r['taxpayer_type']);   // Obchodný register => právnická osoba
        self::assertStringContainsString('Obchodný register', $r['commercial_register']);
    }

    public function testReturnsNullWhenFetchFails(): void
    {
        $c = new RpoClient('https://api.orsf.sk/v1', static fn (string $url): ?string => null);
        self::assertNull($c->lookup('31333532'));
    }

    public function testReturnsNullOnErrorPayload(): void
    {
        // ORSF 404 / error payload has no `ico` field.
        $c = new RpoClient('https://api.orsf.sk/v1', static fn (string $url): ?string => '{"statusCode":404,"message":"Not Found"}');
        self::assertNull($c->lookup('99999999'));
    }
}
