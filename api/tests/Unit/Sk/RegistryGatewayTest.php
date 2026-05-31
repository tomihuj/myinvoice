<?php

declare(strict_types=1);

namespace MyInvoice\Tests\Unit\Sk;

use MyInvoice\Service\Registry\RegistryGateway;
use MyInvoice\Service\Registry\RegistryLookup;
use PHPUnit\Framework\TestCase;

final class RegistryGatewayTest extends TestCase
{
    /** @param array<string,mixed>|null $ret */
    private function fake(?array $ret): RegistryLookup
    {
        return new class($ret) implements RegistryLookup {
            public bool $called = false;
            /** @param array<string,mixed>|null $ret */
            public function __construct(private readonly ?array $ret) {}
            public function lookup(string $ic): ?array
            {
                $this->called = true;
                return $this->ret;
            }
        };
    }

    public function testUsesSkSourceWhenProfileIsSk(): void
    {
        $cz = $this->fake(['company_name' => 'CZ Co']);
        $sk = $this->fake(['company_name' => 'SK Co']);

        $gw = new RegistryGateway('SK', $cz, $sk);
        $r = $gw->lookup('31333532');

        self::assertSame('SK Co', $r['company_name']);
        self::assertTrue($sk->called);
        self::assertFalse($cz->called, 'CZ source must not be queried on a SK profile');
    }

    public function testUsesCzSourceWhenProfileIsCz(): void
    {
        $cz = $this->fake(['company_name' => 'CZ Co']);
        $sk = $this->fake(['company_name' => 'SK Co']);

        $gw = new RegistryGateway('CZ', $cz, $sk);
        $r = $gw->lookup('12345678');

        self::assertSame('CZ Co', $r['company_name']);
        self::assertTrue($cz->called);
        self::assertFalse($sk->called, 'SK source must not be queried on a CZ profile');
    }
}
