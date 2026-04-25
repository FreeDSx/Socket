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

use FreeDSx\Socket\Exception\ConnectionException;
use FreeDSx\Socket\Socket;
use FreeDSx\Socket\SocketServer;
use PHPUnit\Framework\TestCase;

final class SocketServerTest extends TestCase
{
    use RequiresUnixTransport;

    private string $testSocket = '';

    private ?SocketServer $subject = null;

    protected function setUp(): void
    {
        $this->testSocket = sys_get_temp_dir() . '/phpunit.socket';
    }

    protected function tearDown(): void
    {
        $this->subject?->close();
        if ($this->testSocket !== '' && file_exists($this->testSocket)) {
            @unlink($this->testSocket);
        }
    }

    public function test_it_should_throw_a_connection_exception_if_it_cannot_listen_on_the_ip_and_port(): void
    {
        $this->subject = new SocketServer([]);

        $this->expectException(ConnectionException::class);

        $this->subject->listen('1.2.3.4', 389);
    }

    public function test_it_should_return_null_if_there_is_no_client_on_accept(): void
    {
        $this->subject = SocketServer::bind('0.0.0.0', 33389);

        self::assertNull($this->subject->accept(0));
    }

    public function test_it_should_construct_a_tcp_based_socket_server(): void
    {
        $this->subject = SocketServer::bindTcp('0.0.0.0', 33389);

        self::assertSame(
            'tcp',
            $this->subject->getOptions()['transport']
        );
        self::assertTrue($this->subject->isConnected());
        $this->subject->close();
        self::assertFalse($this->subject->isConnected());
    }

    public function test_it_should_construct_a_udp_based_socket_server(): void
    {
        $this->subject = SocketServer::bindUdp('0.0.0.0', 33389);

        self::assertSame('udp', $this->subject->getOptions()['transport']);
    }

    public function test_it_should_construct_a_unix_based_socket_server(): void
    {
        $this->requireUnixTransport();

        $this->subject = SocketServer::bindUnix($this->testSocket);

        self::assertSame(
            'unix',
            $this->subject->getOptions()['transport']
        );
        self::assertTrue($this->subject->isConnected());
        $this->subject->close();
        self::assertFalse($this->subject->isConnected());
    }

    public function test_it_should_receive_data(): void
    {
        $this->subject = SocketServer::bindUdp('0.0.0.0', 33389);

        $client = Socket::udp('127.0.0.1', ['port' => 33389]);
        $client->write('foo');

        self::assertSame(
            'foo',
            $this->subject->receive()
        );
    }
}
