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
        $this->buffer = ($this->buffer !== false) ? $this->buffer : $this->socket->read();

        # Likely an unsolicited notification for a remote disconnect. For some reason, this forces it to be caught in
        # that case (but down below). This exception directly below is never thrown in that case. But the remote
        # disconnect message is never caught if this block is not here. Why???
        #
        # @todo PHP bug? Or logic issue with my generator use?
        if ($this->buffer === false) {
            throw new ConnectionException('The connection to the server has been lost.');
        }

        while ($this->buffer !== false) {
            $message = null;
            try {
                $message = $this->decode($this->buffer);
                $this->buffer = false;

                if ($message->getTrailingData() != '') {
                    $this->buffer = $message->getTrailingData();
                } elseif (($peek = $this->socket->read(false)) !== false) {
                    $this->buffer .= $peek;
                }
            } catch (PartialMessageException $e) {
                $this->buffer .= $this->socket->read();
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
     * @param $bytes
     * @return mixed
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
