<?php declare(strict_types=1);

namespace PhpTuic\Tests\Unit\Runtime;

use PhpTuic\Runtime\IpAccessList;
use PHPUnit\Framework\TestCase;

final class IpAccessListTest extends TestCase
{
    public function testEmptyListAllowsAnyAddress(): void
    {
        $list = IpAccessList::fromStrings([]);

        self::assertTrue($list->allows('127.0.0.1'));
        self::assertTrue($list->allows('192.168.1.10'));
    }

    public function testExactAndCidrRulesAreSupported(): void
    {
        $list = IpAccessList::fromStrings(['127.0.0.1', '10.0.0.0/24']);

        self::assertTrue($list->allows('127.0.0.1'));
        self::assertTrue($list->allows('10.0.0.42'));
        self::assertFalse($list->allows('10.0.1.42'));
        self::assertFalse($list->allows('192.168.1.1'));
    }
}
