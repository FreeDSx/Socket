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

/**
 * Represents a consumable buffer of data in the queue.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
readonly final class Buffer
{
    public function __construct(
        protected string $bytes,
        protected int $endsAt,
    ) {
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function endsAt(): int
    {
        return $this->endsAt;
    }
}
