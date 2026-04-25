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

namespace Tests\Unit\FreeDSx\Socket\Queue;

use FreeDSx\Asn1\Encoder\EncoderInterface;
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Socket\Exception\ConnectionException;
use FreeDSx\Socket\Queue\Asn1MessageQueue;
use FreeDSx\Socket\Socket;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Unit\FreeDSx\Socket\Pdu;

final class Asn1MessageQueueTest extends TestCase
{
    private Socket&MockObject $socket;

    private EncoderInterface&MockObject $encoder;

    private Asn1MessageQueue $subject;

    protected function setUp(): void
    {
        $this->socket = $this->createMock(Socket::class);
        $this->encoder = $this->createMock(EncoderInterface::class);
        $this->subject = new Asn1MessageQueue(
            $this->socket,
            $this->encoder,
            Pdu::class,
        );
    }

    public function test_it_should_return_a_single_message_on_tcp_read(): void
    {
        $this->socket->method('read')->willReturn('foo');
        $this->encoder
            ->expects(self::atLeastOnce())
            ->method('decode')
            ->with('foo')
            ->willReturn(new IntegerType(100));
        $this->encoder->method('getLastPosition')->willReturn(3);

        self::assertEquals(
            new Pdu(new IntegerType(100)),
            $this->subject->getMessage(),
        );
    }

    public function test_it_should_continue_on_during_partial_PDUs(): void
    {
        $this->socket
            ->method('read')
            ->willReturnOnConsecutiveCalls('foo', 'bar');
        $this->encoder
            ->expects(self::atLeast(2))
            ->method('decode')
            ->willReturnCallback(
                static fn (string $bytes): IntegerType => match ($bytes) {
                    'foo' => throw new PartialPduException(),
                    'foobar' => new IntegerType(100),
                    default => self::fail("Unexpected decode argument: {$bytes}"),
                },
            );
        $this->encoder->method('getLastPosition')->willReturn(3);

        self::assertEquals(
            new Pdu(new IntegerType(100)),
            $this->subject->getMessage(),
        );
    }

    public function test_it_should_throw_an_exception_on_get_message_when_there_is_none(): void
    {
        $this->socket->method('read')->willReturn(false);

        $this->expectException(ConnectionException::class);

        $this->subject->getMessage();
    }

    public function test_it_should_not_peek_the_socket_after_decoding_a_complete_message(): void
    {
        $this->socket->method('read')->willReturnCallback(
            static function (bool $block = true): string {
                if (!$block) {
                    self::fail('socket->read(false) should not be called after decoding a complete message.');
                }
                return 'foo';
            },
        );
        $this->encoder
            ->expects(self::atLeastOnce())
            ->method('decode')
            ->with('foo')
            ->willReturn(new IntegerType(100));
        $this->encoder->method('getLastPosition')->willReturn(3);

        self::assertEquals(
            new Pdu(new IntegerType(100)),
            $this->subject->getMessage(),
        );
    }

    public function test_it_should_yield_messages_continuously_from_the_generator(): void
    {
        $this->socket
            ->method('read')
            ->willReturnOnConsecutiveCalls('foobar', 'baz');
        $this->encoder
            ->method('decode')
            ->willReturnCallback(
                static fn (string $bytes): IntegerType => match ($bytes) {
                    'foobar' => new IntegerType(1),
                    'bar' => new IntegerType(2),
                    'baz' => new IntegerType(3),
                    default => self::fail("Unexpected decode argument: {$bytes}"),
                },
            );
        $this->encoder->method('getLastPosition')->willReturn(3);

        $iter = $this->subject->getMessages();

        self::assertEquals(new Pdu(new IntegerType(1)), $iter->current());
        $iter->next();
        self::assertEquals(new Pdu(new IntegerType(2)), $iter->current());
        $iter->next();
        self::assertEquals(new Pdu(new IntegerType(3)), $iter->current());
    }
}
