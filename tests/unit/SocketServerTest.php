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

use ErrorException;
use FreeDSx\Socket\Exception\AcceptException;
use FreeDSx\Socket\Exception\ConnectionException;
use FreeDSx\Socket\Socket;
use FreeDSx\Socket\SocketOptions;
use FreeDSx\Socket\SocketServer;
use FreeDSx\Socket\SocketServerOptions;
use FreeDSx\Socket\Transport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SocketServerTest extends TestCase
{
    use GeneratesCertificates;
    use RequiresNonWindows;
    use RequiresUnixTransport;
    use RunsInCoroutine;

    private const SPARE_DESCRIPTORS = 8;

    private string $testSocket = '';

    private ?SocketServer $subject = null;

    private int $port = 0;

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->testSocket = sys_get_temp_dir() . '/phpunit.socket';
        $this->port = self::freePort();
    }

    protected function tearDown(): void
    {
        $this->subject?->close();
        if ($this->testSocket !== '' && file_exists($this->testSocket)) {
            @unlink($this->testSocket);
        }
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    public function test_it_should_throw_a_connection_exception_if_it_cannot_listen_on_the_ip_and_port(): void
    {
        $this->subject = new SocketServer();

        $this->expectException(ConnectionException::class);

        $this->subject->listen('1.2.3.4', 389);
    }

    public function test_it_should_return_null_if_there_is_no_client_on_accept(): void
    {
        $this->subject = SocketServer::bind('0.0.0.0', 33389);

        self::assertNull($this->subject->accept(0));
    }

    public function test_it_should_only_allow_a_second_server_on_the_same_port_when_reusing_it(): void
    {
        $this->requireNonWindows('Windows has no SO_REUSEPORT and already permits rebinding a port without it.');

        $this->subject = (new SocketServer(
            (new SocketServerOptions())->setReusePort(true),
        ))->listen('127.0.0.1', 33390);

        $second = (new SocketServer(
            (new SocketServerOptions())->setReusePort(true),
        ))->listen('127.0.0.1', 33390);
        $second->close();

        $withoutReuse = new SocketServer(new SocketServerOptions());

        $this->expectException(ConnectionException::class);

        $withoutReuse->listen('127.0.0.1', 33390);
    }

    public function test_it_should_construct_a_tcp_based_socket_server(): void
    {
        $this->subject = SocketServer::bindTcp('0.0.0.0', 33389);

        self::assertSame(
            Transport::Tcp,
            $this->subject->getOptions()->getTransport(),
        );
        self::assertTrue($this->subject->isConnected());
        $this->subject->close();
        self::assertFalse($this->subject->isConnected());
    }

    public function test_it_should_construct_a_udp_based_socket_server(): void
    {
        $this->subject = SocketServer::bindUdp('0.0.0.0', 33389);

        self::assertSame(
            Transport::Udp,
            $this->subject->getOptions()->getTransport(),
        );
    }

    public function test_it_should_construct_a_unix_based_socket_server(): void
    {
        $this->requireUnixTransport();

        $this->subject = SocketServer::bindUnix($this->testSocket);

        self::assertSame(
            Transport::Unix,
            $this->subject->getOptions()->getTransport(),
        );
        self::assertTrue($this->subject->isConnected());
        $this->subject->close();
        self::assertFalse($this->subject->isConnected());
    }

    public function test_it_should_still_serve_a_unix_client_when_tcp_no_delay_is_set(): void
    {
        $this->requireUnixTransport();

        $this->subject = SocketServer::bindUnix(
            $this->testSocket,
            (new SocketServerOptions())->setTcpNoDelay(true),
        );
        $client = Socket::unix($this->testSocket);

        try {
            $accepted = $this->subject->accept(1.0);
            self::assertNotNull($accepted);

            $client->write('foo');

            self::assertSame(
                'foo',
                $accepted->read(),
            );
        } finally {
            $client->close();
        }
    }

    public function test_it_should_receive_data(): void
    {
        $this->subject = SocketServer::bindUdp('0.0.0.0', 33389);

        $client = Socket::udp(
            '127.0.0.1',
            (new SocketOptions())->setPort(33389),
        );
        $client->write('foo');

        self::assertSame('foo', $this->subject->receive());
    }

    public function test_it_should_apply_idle_timeout_to_accepted_client_read_timeout(): void
    {
        $this->subject = SocketServer::bindTcp(
            '127.0.0.1',
            33389,
            (new SocketServerOptions())->setIdleTimeout(42),
        );

        $client = Socket::tcp(
            '127.0.0.1',
            (new SocketOptions())->setPort(33389),
        );

        try {
            $accepted = $this->subject->accept(1.0);

            self::assertNotNull($accepted);
            self::assertSame(
                42,
                $accepted->getOptions()->getTimeoutRead()
            );
        } finally {
            $client->close();
        }
    }

    public function test_it_should_wait_out_the_timeout_when_no_client_arrives(): void
    {
        $this->subject = $this->bindTcpServer();

        $start = microtime(true);

        self::assertNull($this->subject->accept(0.2));
        self::assertGreaterThanOrEqual(
            0.15,
            microtime(true) - $start,
        );
    }

    #[DataProvider('nonFiniteTimeouts')]
    public function test_it_should_accept_with_a_non_finite_timeout_without_a_warning(float $timeout): void
    {
        $server = $this->bindTcpServer();
        $this->subject = $server;
        $clients = $this->connectClients(1);
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });

        try {
            self::assertNotNull($server->accept($timeout));
        } finally {
            restore_error_handler();
            $this->closeClients($clients);
        }
    }

    /**
     * @return array<string, array{float}>
     */
    public static function nonFiniteTimeouts(): array
    {
        return [
            'infinite' => [INF],
            'not a number' => [NAN],
        ];
    }

    public function test_it_should_not_accept_on_a_udp_server(): void
    {
        $this->subject = SocketServer::bindUdp(
            '127.0.0.1',
            $this->port,
        );
        $client = Socket::udp(
            '127.0.0.1',
            (new SocketOptions())->setPort($this->port),
        );
        $client->write('foo');

        self::assertNull($this->subject->accept(0.2));
    }

    #[RunInSeparateProcess]
    public function test_it_should_accept_a_client_into_the_last_free_descriptor(): void
    {
        $this->requireDescriptorLimits();
        $server = $this->bindTcpServer();
        $this->subject = $server;
        $clients = $this->connectClients(1);

        try {
            self::assertNotNull($this->withoutFreeDescriptors(
                fn () => $server->accept(1.0),
                1,
            ));
        } finally {
            $this->closeClients($clients);
        }
    }

    #[RunInSeparateProcess]
    public function test_it_should_accept_on_a_listener_past_what_select_can_watch(): void
    {
        $this->requireNonWindows('Windows select has no descriptor number limit.');
        $held = [];
        while (count($held) < 1100) {
            $handle = @fopen(
                '/dev/null',
                'r',
            );
            if ($handle === false) {
                self::markTestSkipped('Not enough descriptors to pass what select can watch.');
            }
            $held[] = $handle;
        }
        $server = $this->bindTcpServer();
        $this->subject = $server;
        $clients = $this->connectClients(1);

        try {
            self::assertNotNull($server->accept(1.0));
        } finally {
            $this->closeClients($clients);
            $this->closeClients($held);
        }
    }

    #[RunInSeparateProcess]
    public function test_it_should_throw_when_a_waiting_client_cannot_be_accepted(): void
    {
        $this->requireDescriptorLimits();
        $server = $this->bindTcpServer();
        $this->subject = $server;
        $clients = $this->connectClients(2);

        try {
            $this->expectException(AcceptException::class);

            $this->withoutFreeDescriptors(fn () => $server->accept(1.0));
        } finally {
            $this->closeClients($clients);
        }
    }

    #[RunInSeparateProcess]
    public function test_it_should_throw_inside_a_coroutine_when_a_waiting_client_cannot_be_accepted(): void
    {
        $this->requireDescriptorLimits();
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('The swoole extension is required.');
        }
        $caught = null;

        $this->runInCoroutine(function () use (&$caught): void {
            $clients = [];
            try {
                $server = $this->bindTcpServer();
                $this->subject = $server;
                $clients = $this->connectClients(2);
                $this->withoutFreeDescriptors(fn () => $server->accept(1.0));
            } catch (Throwable $e) {
                $caught = $e;
            } finally {
                $this->closeClients($clients);
            }
        });

        self::assertInstanceOf(
            AcceptException::class,
            $caught,
        );
    }

    public function test_it_should_not_report_a_failed_tls_handshake_as_an_accept_failure(): void
    {
        if (!extension_loaded('openssl')) {
            self::markTestSkipped('The openssl extension is required.');
        }
        $server = SocketServer::bindTcp(
            '127.0.0.1',
            $this->port,
            $this->tlsServerOptions(),
        );
        $this->subject = $server;
        $clients = $this->connectClients(1);
        fwrite(
            $clients[0],
            'not a tls handshake',
        );
        $this->closeClients($clients);

        self::assertNull($server->accept(1.0));
    }

    public function test_it_should_give_an_accepted_tcp_client_its_peer_address_after_it_closes(): void
    {
        $this->subject = $this->bindTcpServer();
        $clients = $this->connectClients(1);
        $clientName = stream_socket_get_name(
            $clients[0],
            false,
        );
        self::assertIsString($clientName);

        try {
            $accepted = $this->subject->accept(1.0);
            self::assertNotNull($accepted);
            $accepted->close();
            $peer = $accepted->getPeerAddress();

            self::assertNotNull($peer);
            self::assertSame(
                '127.0.0.1',
                $peer->getIp(),
            );
            self::assertSame(
                self::portOf($clientName),
                $peer->getPort(),
            );
        } finally {
            $this->closeClients($clients);
        }
    }

    public function test_it_should_mark_the_peer_of_an_accepted_unix_client_as_unix(): void
    {
        $this->requireUnixTransport();
        $this->subject = SocketServer::bindUnix($this->testSocket);
        $client = Socket::unix($this->testSocket);

        try {
            $accepted = $this->subject->accept(1.0);

            self::assertTrue($accepted?->getPeerAddress()?->isUnix());
        } finally {
            $client->close();
        }
    }

    public function test_it_should_give_a_connected_client_the_address_of_the_server(): void
    {
        $this->subject = $this->bindTcpServer();
        $client = Socket::tcp(
            '127.0.0.1',
            (new SocketOptions())->setPort($this->port),
        );

        try {
            self::assertSame(
                '127.0.0.1:' . $this->port,
                $client->getPeerAddress()?->toString(),
            );
        } finally {
            $client->close();
        }
    }

    private function bindTcpServer(): SocketServer
    {
        return SocketServer::bindTcp(
            '127.0.0.1',
            $this->port,
        );
    }

    /**
     * @return list<resource>
     */
    private function connectClients(int $count): array
    {
        $clients = [];
        for ($i = 0; $i < $count; $i++) {
            $client = stream_socket_client('tcp://127.0.0.1:' . $this->port);
            if ($client === false) {
                self::fail('Unable to connect to the test server.');
            }
            $clients[] = $client;
        }

        return $clients;
    }

    /**
     * @param list<resource> $clients
     */
    private function closeClients(array $clients): void
    {
        foreach ($clients as $client) {
            fclose($client);
        }
    }

    private function tlsServerOptions(): SocketServerOptions
    {
        [$certificate, $key] = $this->generateCertificate();
        $certFile = $this->tempFile();
        $keyFile = $this->tempFile();
        openssl_x509_export_to_file(
            $certificate,
            $certFile,
        );
        openssl_pkey_export_to_file(
            $key,
            $keyFile,
        );

        return (new SocketServerOptions())
            ->setUseSsl(true)
            ->setSslCert($certFile)
            ->setSslCertKey($keyFile);
    }

    private function tempFile(): string
    {
        $file = tempnam(
            sys_get_temp_dir(),
            'freedsx_socket_',
        );
        if ($file === false) {
            self::fail('Unable to create a temporary file.');
        }
        $this->tempFiles[] = $file;

        return $file;
    }

    private function requireDescriptorLimits(): void
    {
        $this->requireNonWindows('Windows has no descriptor limit to exhaust.');
        if (!extension_loaded('posix')) {
            self::markTestSkipped('The posix extension is required to lower the descriptor limit.');
        }
    }

    /**
     * Runs the callback with only the given number of descriptors free, by lowering the limit and holding the rest.
     */
    private function withoutFreeDescriptors(
        callable $callback,
        int $free = 0,
    ): mixed {
        $limits = posix_getrlimit();
        $soft = self::limitOf($limits['soft openfiles'] ?? 'unlimited');
        $hard = self::limitOf($limits['hard openfiles'] ?? 'unlimited');
        $lowered = posix_setrlimit(
            POSIX_RLIMIT_NOFILE,
            count((array) scandir('/dev/fd')) + self::SPARE_DESCRIPTORS,
            $hard,
        );
        if (!$lowered) {
            self::markTestSkipped('The descriptor limit could not be lowered.');
        }

        // The test runner's own handler may need a class file it cannot open while no descriptor is free.
        set_error_handler(static fn (): bool => true);
        $held = [];
        try {
            while (count($held) < self::SPARE_DESCRIPTORS * 4) {
                $handle = @fopen(
                    '/dev/null',
                    'r',
                );
                if ($handle === false) {
                    break;
                }
                $held[] = $handle;
            }
            $this->closeClients(array_splice(
                $held,
                0,
                $free,
            ));

            return $callback();
        } finally {
            $this->closeClients($held);
            restore_error_handler();
            $restored = posix_setrlimit(
                POSIX_RLIMIT_NOFILE,
                $soft,
                $hard,
            );
            if (!$restored) {
                self::fail('The descriptor limit could not be restored.');
            }
        }
    }

    private static function portOf(string $name): int
    {
        $separator = strrpos(
            $name,
            ':',
        );

        return (int) substr(
            $name,
            (int) $separator + 1,
        );
    }

    private static function freePort(): int
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        if ($probe === false) {
            self::fail('Unable to find a free port.');
        }
        $name = stream_socket_get_name(
            $probe,
            false,
        );
        fclose($probe);
        if ($name === false) {
            self::fail('Unable to read the free port.');
        }

        return self::portOf($name);
    }

    private static function limitOf(int|string $limit): int
    {
        return $limit === 'unlimited'
            ? POSIX_RLIMIT_INFINITY
            : (int) $limit;
    }
}
