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
 * Represents a consumable buffer of data in the queue.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Buffer
{
    /**
     * @var string
     */
    protected $bytes;

    /**
     * @var int
     */
    protected $endsAt;

    /**
     * @param string $bytes
     * @param int $endsAt
     */
    public function __construct($bytes, int $endsAt)
    {
        $this->bytes = $bytes;
        $this->endsAt = $endsAt;
    }

    /**
     * @return string
     */
    public function bytes()
    {
        return $this->bytes;
    }

    /**
     * @return int
     */
    public function endsAt(): int
    {
        return $this->endsAt;
    }
}
