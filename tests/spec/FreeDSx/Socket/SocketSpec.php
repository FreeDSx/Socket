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
            'ssl_crypto_type' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT,
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
}
