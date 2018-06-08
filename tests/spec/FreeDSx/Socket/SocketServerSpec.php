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

use FreeDSx\Socket\Exception\ConnectionException;
use FreeDSx\Socket\Socket;
use FreeDSx\Socket\SocketServer;
use PhpSpec\ObjectBehavior;

class SocketServerSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedThrough('bind', ['0.0.0.0', 33389]);
    }

    function letGo()
    {
        @$this->close();
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(SocketServer::class);
    }

    function it_should_throw_a_connection_exception_if_it_cannot_listen_on_the_ip_and_port()
    {
        $this->beConstructedWith([]);

        $this->shouldThrow(ConnectionException::class)->during('Listen',['1.2.3.4', 389]);
    }

    function it_should_return_null_if_there_is_no_client_on_accept()
    {
        $this->accept(0)->shouldBeNull();
    }

    function it_should_construct_a_tcp_based_socket_server()
    {
        $this->beConstructedThrough('bindTcp', ['0.0.0.0', 33389]);

        $this->getOptions()->shouldHaveKeyWithValue('transport', 'tcp');
    }

    function it_should_construct_a_udp_based_socket_server()
    {
        $this->beConstructedThrough('bindUdp', ['0.0.0.0', 33389]);

        $this->getOptions()->shouldHaveKeyWithValue('transport', 'udp');
    }

    function it_should_receive_data()
    {
        $this->beConstructedThrough('bindUdp', ['0.0.0.0', 33389]);
        $this->getOptions();

        $socket = Socket::udp('127.0.0.1', ['port' => 33389]);
        $socket->write('foo');

        $this->receive()->shouldBeEqualTo('foo');
    }
}
