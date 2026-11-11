<?php

declare(strict_types=1);

/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\FreeDSx\Socket;

use FreeDSx\Socket\SocketAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SocketAddressTest extends TestCase
{
    public function test_it_should_parse_an_ipv4_name(): void
    {
        $address = SocketAddress::tryFromName('127.0.0.1:389');

        self::assertNotNull($address);
        self::assertSame(
            '127.0.0.1',
            $address->getIp(),
        );
        self::assertSame(
            389,
            $address->getPort(),
        );
        self::assertFalse($address->isUnix());
        self::assertSame(
            '127.0.0.1:389',
            $address->toString(),
        );
    }

    public function test_it_should_parse_a_bracketed_ipv6_name(): void
    {
        $address = SocketAddress::tryFromName('[::1]:389');

        self::assertNotNull($address);
        self::assertSame(
            '::1',
            $address->getIp(),
        );
        self::assertSame(
            389,
            $address->getPort(),
        );
        self::assertSame(
            '[::1]:389',
            $address->toString(),
        );
    }

    public function test_it_should_give_an_ipv4_mapped_ipv6_address_as_plain_ipv4(): void
    {
        $address = SocketAddress::tryFromName('[::ffff:127.0.0.1]:389');

        self::assertNotNull($address);
        self::assertSame(
            '127.0.0.1',
            $address->getIp(),
        );
        self::assertSame(
            '127.0.0.1:389',
            $address->toString(),
        );
    }

    public function test_it_should_give_an_ipv6_address_in_its_compressed_form(): void
    {
        self::assertSame(
            '[2001:db8::1]:389',
            SocketAddress::tryFromName('[2001:0db8:0:0:0:0:0:1]:389')?->toString(),
        );
    }

    public function test_it_should_equal_another_name_for_the_same_client(): void
    {
        self::assertEquals(
            SocketAddress::tryFromName('127.0.0.1:389'),
            SocketAddress::tryFromName('[::ffff:127.0.0.1]:389'),
        );
    }

    #[DataProvider('malformedNames')]
    public function test_it_should_not_parse_a_malformed_name(string $name): void
    {
        self::assertNull(SocketAddress::tryFromName($name));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedNames(): array
    {
        return [
            'no port' => ['127.0.0.1'],
            'a bracketed host without a port' => ['[::1]'],
            'an empty port' => ['127.0.0.1:'],
            'a port that is not a number' => ['127.0.0.1:ldap'],
            'a port with a leading zero' => ['127.0.0.1:0389'],
            'a port out of range' => ['127.0.0.1:65536'],
            'a host that is not an ip' => ['localhost:389'],
            'an unbracketed ipv6 host' => ['1::2:3'],
            'a bracketed ipv4 host' => ['[127.0.0.1]:389'],
            'an empty bracketed host' => ['[]:389'],
            'an empty name' => [''],
        ];
    }

    public function test_it_should_describe_a_unix_socket(): void
    {
        $address = SocketAddress::unix();

        self::assertTrue($address->isUnix());
        self::assertNull($address->getIp());
        self::assertNull($address->getPort());
        self::assertSame(
            'unix',
            $address->toString(),
        );
    }
}
