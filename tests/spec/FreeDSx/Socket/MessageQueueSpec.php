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

use fixture\FreeDSx\Socket\Pdu;
use FreeDSx\Asn1\Encoder\EncoderInterface;
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Socket\Exception\ConnectionException;
use FreeDSx\Socket\MessageQueue;
use FreeDSx\Socket\Socket;
use PhpSpec\ObjectBehavior;

class MessageQueueSpec extends ObjectBehavior
{
    function let(Socket $socket, EncoderInterface $encoder)
    {
        $this->beConstructedWith($socket, $encoder, Pdu::class);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(MessageQueue::class);
    }

    function it_should_return_a_single_message_on_tcp_read($socket, $encoder)
    {
        $socket->read()->willReturn('foo');
        $socket->read(false)->shouldBeCalled()->willReturn(false);
        $encoder->decode('foo')->shouldBeCalled()->willReturn(new IntegerType(100));
        $encoder->getLastPosition()->willReturn(2);

        $this->getMessage()->shouldBeLike(new Pdu(new IntegerType(100)));
    }

    function it_should_continue_on_during_partial_PDUs($socket, $encoder)
    {
        $socket->read()->willReturn('foo', 'bar');

        $encoder->decode('foo')->shouldBeCalled()->willThrow(PartialPduException::class);
        $encoder->decode('foobar')->shouldBeCalled()->willReturn(new IntegerType(100));
        $encoder->getLastPosition()->willReturn(2);

        $this->getMessage()->shouldBeLike(new Pdu(new IntegerType(100)));
    }

    function it_should_throw_an_exception_on_get_message_when_there_is_none($socket)
    {
        $socket->read()->willReturn(false);

        $this->shouldThrow(ConnectionException::class)->duringGetMessage();
    }
}
