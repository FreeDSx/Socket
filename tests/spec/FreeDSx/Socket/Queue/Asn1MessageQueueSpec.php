<?php
/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Socket\Queue;

use fixture\FreeDSx\Socket\Pdu;
use FreeDSx\Asn1\Encoder\EncoderInterface;
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Socket\Exception\ConnectionException;
use FreeDSx\Socket\Queue\Asn1MessageQueue;
use FreeDSx\Socket\Socket;
use PhpSpec\ObjectBehavior;

class Asn1MessageQueueSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Asn1MessageQueue::class);
    }

    function let(Socket $socket, EncoderInterface $encoder)
    {
        $this->beConstructedWith($socket, $encoder, Pdu::class);
    }

    function it_should_return_a_single_message_on_tcp_read($socket, $encoder)
    {
        $socket->read()->willReturn('foo');
        $socket->read(false)->willReturn(false);
        $encoder->decode('foo')->shouldBeCalled()->willReturn(new IntegerType(100));
        $encoder->getLastPosition()->willReturn(3);

        $this->getMessage()->shouldBeLike(new Pdu(new IntegerType(100)));
    }

    function it_should_continue_on_during_partial_PDUs($socket, $encoder)
    {
        $socket->read()->willReturn('foo', 'bar');

        $encoder->decode('foo')->shouldBeCalled()->willThrow(PartialPduException::class);
        $encoder->decode('foobar')->shouldBeCalled()->willReturn(new IntegerType(100));
        $encoder->getLastPosition()->willReturn(3);

        $this->getMessage()->shouldBeLike(new Pdu(new IntegerType(100)));
    }

    function it_should_throw_an_exception_on_get_message_when_there_is_none($socket)
    {
        $socket->read()->willReturn(false);

        $this->shouldThrow(ConnectionException::class)->duringGetMessage();
    }

    function it_should_not_peek_the_socket_after_decoding_a_complete_message($socket, $encoder)
    {
        $socket->read()->willReturn('foo');
        $socket->read(false)->shouldNotBeCalled();
        $encoder->decode('foo')->shouldBeCalled()->willReturn(new IntegerType(100));
        $encoder->getLastPosition()->willReturn(3);

        $this->getMessage()->shouldBeLike(new Pdu(new IntegerType(100)));
    }

    function it_should_yield_messages_continuously_from_the_generator($socket, $encoder)
    {
        $socket->read()->willReturn('foobar', 'baz');
        $encoder->decode('foobar')->willReturn(new IntegerType(1));
        $encoder->decode('bar')->willReturn(new IntegerType(2));
        $encoder->decode('baz')->willReturn(new IntegerType(3));
        $encoder->getLastPosition()->willReturn(3);

        $iter = $this->getMessages()->getWrappedObject();

        if (!$iter instanceof \Generator) {
            throw new \RuntimeException('getMessages() must return a Generator.');
        }

        $first = $iter->current();
        $iter->next();
        $second = $iter->current();
        $iter->next();
        $third = $iter->current();

        if ($first != new Pdu(new IntegerType(1))) {
            throw new \RuntimeException('First yielded message did not match.');
        }
        if ($second != new Pdu(new IntegerType(2))) {
            throw new \RuntimeException('Second yielded message did not match.');
        }
        if ($third != new Pdu(new IntegerType(3))) {
            throw new \RuntimeException('Third yielded message did not match.');
        }
    }
}
