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
     * @var null|string
     */
    protected $trailingData;

    /**
     * @param mixed $message The message object as the result of the socket data.
     * @param null|string $trailingData Any trailing data after this message object.
     */
    public function __construct($message, $trailingData = null)
    {
        $this->message = $message;
        $this->trailingData = $trailingData;
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
     * Get any trailing data after this message object.
     *
     * @return null|string
     */
    public function getTrailingData()
    {
        return $this->trailingData;
    }
}
