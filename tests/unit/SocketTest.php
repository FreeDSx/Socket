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

use FreeDSx\Socket\Socket;
use FreeDSx\Socket\SocketOptions;
use FreeDSx\Socket\Transport;
use PHPUnit\Framework\TestCase;

final class SocketTest extends TestCase
{
    use RequiresUnixTransport;

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
        self::assertSame(8192, $options->getBufferSize());
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

    public function test_it_should_leave_the_socket_in_blocking_mode_after_a_non_blocking_read(): void
    {
        [$local] = $this->createSocketPair();
        $subject = new Socket($local);

        $subject->read(false);

        self::assertTrue(stream_get_meta_data($local)['blocked']);
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
}
