<?php
/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Socket;

use FreeDSx\Socket\Socket;
use PhpSpec\ObjectBehavior;

class SocketSpec extends ObjectBehavior
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

    /**
     * @var string|null
     */
    private $unixPath;

    function letGo(): void
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

    private function createSocketPair(): void
    {
        $domain = DIRECTORY_SEPARATOR === '\\'
            ? STREAM_PF_INET
            : STREAM_PF_UNIX;

        [$this->local, $this->remote] = stream_socket_pair(
            $domain,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        );
    }

    private function createUnixServer(): string
    {
        $this->requireUnixTransport();
        $this->unixPath = sys_get_temp_dir() . '/freedsx_socket_' . uniqid('', true) . '.sock';
        $this->unixServer = stream_socket_server('unix://' . $this->unixPath);

        return $this->unixPath;
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(Socket::class);
    }

    function it_should_get_the_options_for_the_socket()
    {
        $this->getOptions()->shouldBeEqualTo([
            'transport' => 'tcp',
            'port' => 389,
            'use_ssl' => false,
            'ssl_crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT,
            'ssl_ciphers' => 'DEFAULT',
            'ssl_validate_cert' => true,
            'ssl_allow_self_signed' => null,
            'ssl_ca_cert' => null,
            'ssl_peer_name' => null,
            'timeout_connect' => 3,
            'timeout_read' => 15,
            'buffer_size' => 8192,
        ]);
    }

    function it_should_create_a_socket()
    {
        $this::create('www.google.com', ['port' => 80])->shouldBeAnInstanceOf(Socket::class);
    }

    function it_should_create_a_unix_based_socket()
    {
        $path = $this->createUnixServer();

        $this::unix($path)->shouldBeAnInstanceOf(Socket::class);
    }

    function it_should_create_a_tcp_based_socket()
    {
        $this::tcp('www.google.com', ['port' => 80])->getOptions()->shouldHaveKeyWithValue('transport', 'tcp');
    }

    function it_should_create_a_udp_based_socket()
    {
        $this::udp('8.8.8.8', ['port' => 53])->getOptions()->shouldHaveKeyWithValue('transport', 'udp');
    }

    function it_should_have_a_default_buffer_size_of_65507_for_UDP()
    {
        $this::udp('8.8.8.8', ['port' => 53])->getOptions()->shouldHaveKeyWithValue('buffer_size', 65507);
    }

    function it_should_tell_whether_or_not_it_is_connected_for_tcp()
    {
        $this->beConstructedThrough('tcp', ['www.google.com', ['port' => 80]]);

        $this->isConnected()->shouldBeEqualTo(true);
        $this->close();
        $this->isConnected()->shouldBeEqualTo(false);
    }

    function it_should_tell_whether_or_not_it_is_connected_for_udp()
    {
        $this->beConstructedThrough('udp', ['www.google.com', ['port' => 53]]);

        $this->isConnected()->shouldBeEqualTo(true);
        $this->close();
        $this->isConnected()->shouldBeEqualTo(false);
    }

    function it_should_tell_whether_it_is_connected_for_unix()
    {
        $path = $this->createUnixServer();
        $this->beConstructedThrough('unix', [$path]);

        $this->isConnected()->shouldBeEqualTo(true);
        $this->close();
        $this->isConnected()->shouldBeEqualTo(false);
    }

    function it_should_return_at_most_buffer_size_bytes_per_read()
    {
        $this->createSocketPair();
        fwrite($this->remote, '0123456789');

        $this->beConstructedWith($this->local, ['buffer_size' => 4]);

        $this->read()->shouldBe('0123');
        $this->read()->shouldBe('4567');
        $this->read()->shouldBe('89');
    }

    function it_should_return_false_on_a_non_blocking_read_when_no_data_is_available()
    {
        $this->createSocketPair();

        $this->beConstructedWith($this->local);

        $this->read(false)->shouldBe(false);
    }

    function it_should_return_false_on_a_blocking_read_when_the_peer_has_closed()
    {
        $this->createSocketPair();
        fclose($this->remote);

        $this->beConstructedWith($this->local);

        $this->read()->shouldBe(false);
    }

    function it_should_leave_the_socket_in_blocking_mode_after_a_non_blocking_read()
    {
        $this->createSocketPair();

        $this->beConstructedWith($this->local);

        $this->read(false);

        if (stream_get_meta_data($this->local)['blocked'] !== true) {
            throw new \RuntimeException('Socket should be in blocking mode after a non-blocking read.');
        }
    }
}
