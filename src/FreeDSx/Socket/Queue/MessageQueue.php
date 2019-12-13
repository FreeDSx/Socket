<?php
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

/**
 * Used to retrieve Messages/PDUs from a socket.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
abstract class MessageQueue
{
    /**
     * @var Socket
     */
    protected $socket;

    /**
     * @var false|string
     */
    protected $buffer = false;

    /**
     * @var string|null
     */
    protected $toConsume = null;

    /**
     * @param Socket $socket
     */
    public function __construct(Socket $socket)
    {
        $this->socket = $socket;
    }

    /**
     * @param int|null $id
     * @return \Generator
     * @throws ConnectionException
     */
    public function getMessages(?int $id = null)
    {
        if (!$this->hasBuffer()) {
            $this->addToAvailableBufferOrFail();
        }

        while ($this->hasBuffer()) {
            try {
                if ($this->hasAvailableBuffer()) {
                    $this->addToConsumableBuffer();
                } elseif (!$this->hasConsumableBuffer()) {
                    $this->addToAvailableBufferOrFail();
                }
            } catch (PartialMessageException $exception) {
                $this->addToAvailableBufferOrFail();
            }

            try {
                while ($this->hasConsumableBuffer()) {
                    $message = $this->consume();
                    if ($message !== null) {
                        yield $this->constructMessage($message, $id);
                    }
                }
            } catch (PartialMessageException $e) {
                if ($this->hasAvailableBuffer()) {
                    $this->addToConsumableBuffer();
                } else {
                    $this->addToAvailableBufferOrFail();
                }
            }
        }
    }

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
            $buffer = $this->unwrap((string)$this->buffer);
            $this->buffer = \substr((string)$this->buffer, $buffer->endsAt());
            $this->toConsume .= $buffer->bytes();
        }
    }

    protected function hasBuffer(): bool
    {
        return $this->hasConsumableBuffer() || $this->hasAvailableBuffer();
    }

    protected function hasAvailableBuffer(): bool
    {
        return \strlen((string)$this->buffer) !== 0;
    }

    protected function hasConsumableBuffer(): bool
    {
        return \strlen($this->toConsume) !== 0;
    }

    /**
     * @return Message|null
     * @throws PartialMessageException
     */
    protected function consume(): ?Message
    {
        $message = null;

        try {
            $message = $this->decode($this->toConsume);
            $lastPos = (int)$message->getLastPosition();
            $this->toConsume = \substr($this->toConsume, $lastPos);

            if ($this->toConsume === '' && ($peek = $this->socket->read(false)) !== false) {
                $this->buffer .= $peek;
            }
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

    /**
     * @param string $bytes
     * @return Buffer
     */
    protected function unwrap($bytes) : Buffer
    {
        return new Buffer($bytes, \strlen($bytes));
    }

    /**
     * Decode the bytes to an object. If you have a partial object, throw the PartialMessageException and the queue
     * will attempt to append more data to the buffer.
     *
     * @param string $bytes
     * @return Message
     * @throws PartialMessageException
     */
    protected abstract function decode($bytes) : Message;

    /**
     * @param int|null $id
     * @return mixed
     * @throws ConnectionException
     */
    public function getMessage(?int $id = null)
    {
        return $this->getMessages($id)->current();
    }

    /**
     * Retrieve the message object from the message. Allow for special construction / validation if needed.
     *
     * @param Message $message
     * @param int|null $id
     * @return mixed
     */
    protected function constructMessage(Message $message, ?int $id = null)
    {
        return $message->getMessage();
    }
}
