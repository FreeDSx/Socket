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
        $this->buffer = (\strlen((string) $this->buffer) !== 0) ? $this->buffer : $this->socket->read();

        if ($this->buffer === false) {
            throw new ConnectionException('The connection to the server has been lost.');
        }

        while (\strlen($this->buffer) !== 0) {
            $message = null;
            try {
                $message = $this->decode($this->buffer);
                $lastPos = (int) $message->getLastPosition();
                if (($lastPos + 1) < \strlen($this->buffer)) {
                    $this->buffer = \substr($this->buffer, $lastPos);
                } else {
                    $this->buffer = '';
                }
                if ($this->buffer === '' && ($peek = $this->socket->read(false)) !== false) {
                    $this->buffer .= $peek;
                }
            } catch (PartialMessageException $e) {
                $this->buffer .= (string) $this->socket->read();
            }

            if ($message !== null) {
                yield $this->constructMessage($message, $id);
            }
        }
    }

    /**
     * Decode the bytes to an object. If you have a partial object, throw the PartialMessageException and the queue
     * will attempt to append more data to the buffer.
     *
     * @param string $bytes
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
