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

use FreeDSx\Socket\Exception\IdleTimeoutException;
use FreeDSx\Socket\Exception\ReadTimeoutException;
use FreeDSx\Socket\Exception\WriteTimeoutException;
use FreeDSx\Socket\Socket;
use FreeDSx\Socket\SocketOptions;
use FreeDSx\Socket\Tls\Certificate;
use FreeDSx\Socket\Transport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SocketTest extends TestCase
{
    use RequiresNonWindows;
    use RequiresUnixTransport;
    use GeneratesCertificates;
    use RunsInCoroutine;

    /**
     * @var resource|null
     */
    private $local;

    /**
     * @var resource|null
     */
    private $remote;

    /**
     * @var resource|null
     */
    private $unixServer;

    private ?string $unixPath = null;

    protected function tearDown(): void
    {
        if (is_resource($this->remote)) {
            fclose($this->remote);
        }
        if (is_resource($this->local)) {
            fclose($this->local);
        }
        if (is_resource($this->unixServer)) {
            fclose($this->unixServer);
        }
        if ($this->unixPath !== null && file_exists($this->unixPath)) {
            @unlink($this->unixPath);
        }
    }

    public function test_it_should_get_the_default_options_for_the_socket(): void
    {
        $subject = new Socket();
        $options = $subject->getOptions();

        self::assertSame(Transport::Tcp, $options->getTransport());
        self::assertSame(389, $options->getPort());
        self::assertFalse($options->isUseSsl());
        self::assertSame(
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
            | STREAM_CRYPTO_METHOD_TLS_CLIENT,
            $options->getSslCryptoMethod(),
        );
        self::assertSame('DEFAULT', $options->getSslCiphers());
        self::assertTrue($options->isSslValidateCert());
        self::assertNull($options->getSslAllowSelfSigned());
        self::assertNull($options->getSslCaCert());
        self::assertNull($options->getSslPeerName());
        self::assertSame(3, $options->getTimeoutConnect());
        self::assertSame(15, $options->getTimeoutRead());
        self::assertSame(0, $options->getTimeoutWrite());
        self::assertSame(8192, $options->getBufferSize());
    }

    public function test_a_write_timeout_of_zero_uses_the_unbounded_path(): void
    {
        [$local, $remote] = $this->createSocketPair();
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutWrite(0),
        );

        $subject->write('0123456789');

        self::assertSame(
            '0123456789',
            fread($remote, 10),
        );
    }

    public function test_a_bounded_write_sends_all_data_when_the_peer_reads(): void
    {
        [$local, $remote] = $this->createSocketPair();
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutWrite(5),
        );

        $subject->write('0123456789');

        self::assertSame(
            '0123456789',
            fread($remote, 10),
        );
        self::assertTrue(stream_get_meta_data($local)['blocked']);
    }

    public function test_a_bounded_write_throws_when_the_peer_stops_reading(): void
    {
        $this->requireFillableSocket();

        [$local] = $this->createSocketPair();
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutWrite(1),
        );

        $this->expectException(WriteTimeoutException::class);

        $subject->write(str_repeat('x', 32 * 1024 * 1024));
    }

    public function test_it_should_create_a_socket(): void
    {
        $subject = Socket::create(
            'www.google.com',
            (new SocketOptions())->setPort(80),
        );

        self::assertTrue($subject->isConnected());
    }

    public function test_it_should_create_a_unix_based_socket(): void
    {
        $path = $this->createUnixServer();

        $subject = Socket::unix($path);

        self::assertSame(Transport::Unix, $subject->getOptions()->getTransport());
    }

    public function test_it_should_create_a_tcp_based_socket(): void
    {
        $subject = Socket::tcp(
            'www.google.com',
            (new SocketOptions())->setPort(80),
        );

        self::assertSame(Transport::Tcp, $subject->getOptions()->getTransport());
    }

    public function test_it_should_create_a_udp_based_socket(): void
    {
        $subject = Socket::udp(
            '8.8.8.8',
            (new SocketOptions())->setPort(53),
        );

        self::assertSame(Transport::Udp, $subject->getOptions()->getTransport());
    }

    public function test_it_should_have_a_default_buffer_size_of_65507_for_UDP(): void
    {
        $subject = Socket::udp(
            '8.8.8.8',
            (new SocketOptions())->setPort(53),
        );

        self::assertSame(65507, $subject->getOptions()->getBufferSize());
    }

    public function test_it_should_tell_whether_or_not_it_is_connected_for_tcp(): void
    {
        $subject = Socket::tcp(
            'www.google.com',
            (new SocketOptions())->setPort(80),
        );

        self::assertTrue($subject->isConnected());
        $subject->close();
        self::assertFalse($subject->isConnected());
    }

    public function test_it_should_tell_whether_or_not_it_is_connected_for_udp(): void
    {
        $subject = Socket::udp(
            'www.google.com',
            (new SocketOptions())->setPort(53),
        );

        self::assertTrue($subject->isConnected());
        $subject->close();
        self::assertFalse($subject->isConnected());
    }

    public function test_it_should_tell_whether_it_is_connected_for_unix(): void
    {
        $path = $this->createUnixServer();
        $subject = Socket::unix($path);

        self::assertTrue($subject->isConnected());
        $subject->close();
        self::assertFalse($subject->isConnected());
    }

    public function test_close_without_shutdown_disconnects_tcp(): void
    {
        $subject = Socket::tcp(
            'www.google.com',
            (new SocketOptions())->setPort(80),
        );

        self::assertTrue($subject->isConnected());
        $subject->close(shutdown: false);
        self::assertFalse($subject->isConnected());
    }

    public function test_close_without_shutdown_disconnects_unix(): void
    {
        $path = $this->createUnixServer();
        $subject = Socket::unix($path);

        self::assertTrue($subject->isConnected());
        $subject->close(shutdown: false);
        self::assertFalse($subject->isConnected());
    }

    public function test_it_should_return_at_most_buffer_size_bytes_per_read(): void
    {
        [$local, $remote] = $this->createSocketPair();
        fwrite($remote, '0123456789');

        $subject = new Socket(
            $local,
            (new SocketOptions())->setBufferSize(4),
        );

        self::assertSame('0123', $subject->read());
        self::assertSame('4567', $subject->read());
        self::assertSame('89', $subject->read());
    }

    public function test_it_should_return_false_on_a_non_blocking_read_when_no_data_is_available(): void
    {
        [$local] = $this->createSocketPair();
        $subject = new Socket($local);

        self::assertFalse($subject->read(false));
    }

    public function test_it_should_return_false_on_a_blocking_read_when_the_peer_has_closed(): void
    {
        [$local, $remote] = $this->createSocketPair();
        fclose($remote);
        $subject = new Socket($local);

        self::assertFalse($subject->read());
    }

    public function test_it_should_throw_an_idle_timeout_when_a_blocking_read_exceeds_the_read_timeout(): void
    {
        [$local] = $this->createSocketPair();
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(1),
        );

        $this->expectException(IdleTimeoutException::class);

        $subject->read();
    }

    public function test_a_blocking_read_dispatches_a_signal_that_arrives_while_it_waits(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            self::markTestSkipped('The pcntl extension is required to deliver a signal mid-read.');
        }

        [$local, $remote] = $this->createSocketPair();
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(5),
        );

        $handled = false;
        pcntl_async_signals(true);
        pcntl_signal(
            SIGALRM,
            function () use (&$handled, $remote): void {
                $handled = true;
                fwrite($remote, 'woke');
            },
        );
        pcntl_alarm(1);

        try {
            $data = $subject->read();
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_DFL);
        }

        self::assertTrue(
            $handled,
            'The signal handler never ran.',
        );
        self::assertSame(
            'woke',
            $data,
        );
    }

    public function test_it_should_not_throw_an_idle_timeout_on_a_non_blocking_read(): void
    {
        [$local] = $this->createSocketPair();
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(1),
        );

        self::assertFalse($subject->read(false));
    }

    public function test_it_should_leave_the_socket_in_blocking_mode_after_a_non_blocking_read(): void
    {
        [$local] = $this->createSocketPair();
        $subject = new Socket($local);

        $subject->read(false);

        self::assertTrue(stream_get_meta_data($local)['blocked']);
    }

    public function test_a_blocking_read_throws_a_read_timeout_when_its_own_timeout_passes_first(): void
    {
        [$local] = $this->createSocketPair();
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(5),
        );

        $this->expectException(ReadTimeoutException::class);

        $subject->read(
            true,
            0.2,
        );
    }

    public function test_a_blocking_read_throws_an_idle_timeout_when_the_read_timeout_passes_first(): void
    {
        [$local] = $this->createSocketPair();
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(1),
        );

        $this->expectException(IdleTimeoutException::class);

        $subject->read(
            true,
            5.0,
        );
    }

    public function test_a_blocking_read_returns_data_that_arrives_within_its_own_timeout(): void
    {
        [$local, $remote] = $this->createSocketPair();
        fwrite(
            $remote,
            'data',
        );
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(5),
        );

        self::assertSame(
            'data',
            $subject->read(
                true,
                1.0,
            ),
        );
    }

    /**
     * @return array<string, array{float}>
     */
    public static function noTimeLeftProvider(): array
    {
        return [
            'zero' => [0.0],
            'negative' => [-1.0],
        ];
    }

    #[DataProvider('noTimeLeftProvider')]
    public function test_a_blocking_read_with_no_time_left_returns_data_already_received(float $timeout): void
    {
        $this->requireNonWindows('A loopback socket pair on Windows may not have the bytes ready at once.');
        [$local, $remote] = $this->createSocketPair();
        fwrite(
            $remote,
            'data',
        );
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(5),
        );

        self::assertSame(
            'data',
            $subject->read(
                true,
                $timeout,
            ),
        );
    }

    #[DataProvider('noTimeLeftProvider')]
    public function test_a_blocking_read_with_no_time_left_throws_a_read_timeout_when_nothing_was_received(
        float $timeout,
    ): void {
        [$local] = $this->createSocketPair();
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(5),
        );

        $this->expectException(ReadTimeoutException::class);

        $subject->read(
            true,
            $timeout,
        );
    }

    public function test_a_blocking_read_with_its_own_timeout_returns_false_when_the_peer_has_closed(): void
    {
        [$local, $remote] = $this->createSocketPair();
        fclose($remote);
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(5),
        );

        self::assertFalse($subject->read(
            true,
            1.0,
        ));
    }

    public function test_a_blocking_read_with_its_own_timeout_puts_back_the_read_timeout_of_the_stream(): void
    {
        [$local, $remote] = $this->createSocketPair();
        fwrite(
            $remote,
            'data',
        );
        $subject = new Socket(
            $local,
            (new SocketOptions())->setTimeoutRead(1),
        );
        $subject->read(
            true,
            0.5,
        );

        $start = microtime(true);
        fread(
            $local,
            1,
        );

        self::assertGreaterThanOrEqual(
            0.9,
            microtime(true) - $start,
        );
    }

    public function test_a_non_blocking_read_ignores_a_timeout_given_for_it(): void
    {
        [$local] = $this->createSocketPair();
        $subject = new Socket($local);

        self::assertFalse($subject->read(
            false,
            0.0,
        ));
    }

    public function test_a_blocking_read_in_a_coroutine_throws_a_read_timeout_when_its_own_timeout_passes_first(): void
    {
        $this->requireSwoole();
        $caught = null;

        $this->runInCoroutine(function () use (&$caught): void {
            [$local, $remote] = $this->createSocketPair();
            $subject = new Socket(
                $local,
                (new SocketOptions())->setTimeoutRead(5),
            );

            try {
                $subject->read(
                    true,
                    0.2,
                );
            } catch (Throwable $e) {
                $caught = $e;
            } finally {
                fclose($local);
                fclose($remote);
            }
        });

        self::assertInstanceOf(
            ReadTimeoutException::class,
            $caught,
        );
    }

    public function test_a_blocking_read_in_a_coroutine_with_its_own_timeout_puts_back_the_read_timeout_of_the_stream(
    ): void {
        $this->requireSwoole();
        $caught = null;
        $elapsed = 0.0;

        $this->runInCoroutine(function () use (&$caught, &$elapsed): void {
            [$local, $remote] = $this->createSocketPair();
            fwrite(
                $remote,
                'data',
            );
            $subject = new Socket(
                $local,
                (new SocketOptions())->setTimeoutRead(1),
            );

            try {
                $subject->read(
                    true,
                    0.5,
                );
                $start = microtime(true);
                fread(
                    $local,
                    1,
                );
                $elapsed = microtime(true) - $start;
            } catch (Throwable $e) {
                $caught = $e;
            } finally {
                fclose($local);
                fclose($remote);
            }
        });

        self::assertNull($caught);
        self::assertGreaterThanOrEqual(
            0.9,
            $elapsed,
        );
    }

    public function test_it_should_return_null_for_the_peer_certificate_when_not_connected(): void
    {
        self::assertNull((new Socket())->getPeerCertificate());
    }

    public function test_it_should_return_null_for_the_peer_certificate_when_none_was_presented(): void
    {
        [$local] = $this->createSocketPair();
        $subject = new Socket($local);

        self::assertNull($subject->getPeerCertificate());
    }

    public function test_it_should_not_know_the_peer_address_of_a_socket_pair(): void
    {
        $this->requireNonWindows('A socket pair on Windows is a loopback TCP connection with an address.');
        [$local] = $this->createSocketPair();

        self::assertNull((new Socket($local))->getPeerAddress());
    }

    public function test_it_should_return_the_peer_certificate_when_one_was_presented(): void
    {
        [$local] = $this->createSocketPair();
        stream_context_set_option($local, 'ssl', 'peer_certificate', $this->generateCertificate()[0]);
        $subject = new Socket($local);

        $certificate = $subject->getPeerCertificate();

        self::assertInstanceOf(
            Certificate::class,
            $certificate,
        );
        self::assertSame(
            'peer.test',
            $certificate->getSubject()['CN'] ?? null,
        );
    }

    public function test_a_socket_adopting_a_resource_under_ssl_options_is_encrypted(): void
    {
        [$local] = $this->createSocketPair();

        $socket = new Socket(
            $local,
            (new SocketOptions())->setUseSsl(true),
        );

        self::assertTrue($socket->isEncrypted());
    }

    public function test_a_socket_adopting_a_resource_without_ssl_options_is_not_encrypted(): void
    {
        [$local] = $this->createSocketPair();

        $socket = new Socket(
            $local,
            new SocketOptions(),
        );

        self::assertFalse($socket->isEncrypted());
    }

    public function test_an_unconnected_socket_is_not_encrypted_even_under_ssl_options(): void
    {
        $socket = new Socket(
            null,
            (new SocketOptions())->setUseSsl(true),
        );

        self::assertFalse($socket->isEncrypted());
    }

    /**
     * @return array{0: resource, 1: resource}
     */
    private function createSocketPair(): array
    {
        $domain = DIRECTORY_SEPARATOR === '\\'
            ? STREAM_PF_INET
            : STREAM_PF_UNIX;

        $pair = stream_socket_pair(
            $domain,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );
        if ($pair === false) {
            self::fail('Failed to create socket pair.');
        }
        [$this->local, $this->remote] = $pair;

        return [$this->local, $this->remote];
    }

    private function createUnixServer(): string
    {
        $this->requireUnixTransport();
        $this->unixPath = sys_get_temp_dir() . '/freedsx_socket_' . uniqid('', true) . '.sock';
        $server = stream_socket_server('unix://' . $this->unixPath);
        if ($server === false) {
            self::fail('Failed to create unix socket server.');
        }
        $this->unixServer = $server;

        return $this->unixPath;
    }

    private function requireSwoole(): void
    {
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('The swoole extension is required.');
        }
    }
}
