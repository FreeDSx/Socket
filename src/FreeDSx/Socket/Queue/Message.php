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

/**
 * Represents the decoded result from a message queue.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Message
{
    /**
     * @var mixed
     */
    protected $message;

    /**
     * @var null|int
     */
    protected $lastPosition;

    /**
     * @param mixed $message The message object as the result of the socket data.
     * @param null|int $lastPosition the last position of the byte stream after this message.
     */
    public function __construct($message, ?int $lastPosition = null)
    {
        $this->message = $message;
        $this->lastPosition = $lastPosition;
    }

    /**
     * Get the message object as the result of the socket data.
     *
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get the last position of the byte stream after this message.
     *
     * @return null|int
     */
    public function getLastPosition(): ?int
    {
        return $this->lastPosition;
    }
}
