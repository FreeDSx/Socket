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

namespace FreeDSx\Socket\Queue;

use FreeDSx\Socket\Exception\ConnectionException;
use FreeDSx\Socket\Exception\PartialMessageException;
use FreeDSx\Socket\Socket;
use Generator;
use function strlen;
use function substr;

/**
 * Used to retrieve Messages/PDUs from a socket.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
abstract class MessageQueue
{
    protected string $buffer = '';

    protected string $toConsume = '';

    public function __construct(protected Socket $socket)
    {
    }

    /**
     * @return Generator<int, mixed>
     * @throws ConnectionException
     */
    public function getMessages(?int $id = null): Generator
    {
        while (true) {
            yield $this->getMessage($id);
        }
    }

    /**
     * @throws ConnectionException
     */
    public function getMessage(?int $id = null): mixed
    {
        return $this->constructMessage(
            $this->readOneMessage(),
            $id,
        );
    }

    /**
     * @throws ConnectionException
     * @throws PartialMessageException
     */
    private function readOneMessage(): Message
    {
        while (true) {
            if (!$this->hasConsumableBuffer() && !$this->hasAvailableBuffer()) {
                $this->addToAvailableBufferOrFail();
            }

            if ($this->hasAvailableBuffer()) {
                $this->addToConsumableBuffer();
            }

            try {
                return $this->consume();
            } catch (PartialMessageException) {
                $this->addToAvailableBufferOrFail();
            }
        }
    }

    /**
     * @throws ConnectionException
     */
    protected function addToAvailableBufferOrFail(): void
    {
        $bytes = $this->socket->read();

        if ($bytes === false) {
            throw new ConnectionException('The connection to the server has been lost.');
        }

        $this->buffer .= $bytes;
    }

    protected function addToConsumableBuffer(): void
    {
        if ($this->hasAvailableBuffer()) {
            $buffer = $this->unwrap($this->buffer);
            $this->buffer = substr($this->buffer, $buffer->endsAt());
            $this->toConsume .= $buffer->bytes();
        }
    }

    protected function hasBuffer(): bool
    {
        return $this->hasConsumableBuffer() || $this->hasAvailableBuffer();
    }

    protected function hasAvailableBuffer(): bool
    {
        return strlen($this->buffer) !== 0;
    }

    protected function hasConsumableBuffer(): bool
    {
        return strlen($this->toConsume) !== 0;
    }

    /**
     * @throws PartialMessageException
     */
    protected function consume(): Message
    {
        $message = null;

        try {
            $message = $this->decode($this->toConsume);
            $lastPos = (int) $message->getLastPosition();
            $this->toConsume = substr(
                $this->toConsume,
                $lastPos,
            );
        } catch (PartialMessageException $exception) {
            # If we have available buffer, it might have what we need. Attempt to add it. Otherwise let it bubble...
            if ($this->hasAvailableBuffer()) {
                $this->addToConsumableBuffer();
            } else {
                throw $exception;
            }
        }

        # Adding to the consumed before could cause this to succeed, so retry.
        if ($message === null) {
            return $this->consume();
        }

        return $message;
    }

    protected function unwrap(string $bytes): Buffer
    {
        return new Buffer(
            $bytes,
            strlen($bytes),
        );
    }

    /**
     * Decode the bytes to an object. If you have a partial object, throw the PartialMessageException and the queue
     * will attempt to append more data to the buffer.
     *
     * @throws PartialMessageException
     */
    abstract protected function decode(string $bytes): Message;

    /**
     * Retrieve the message object from the message. Allow for special construction / validation if needed.
     */
    protected function constructMessage(
        Message $message,
        ?int $id = null,
    ): mixed {
        return $message->getMessage();
    }
}
