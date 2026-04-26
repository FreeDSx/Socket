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
 * Represents the decoded result from a message queue.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
readonly class Message
{
    public function __construct(
        protected mixed $message,
        protected ?int  $lastPosition = null,
    ) {
    }

    public function getMessage(): mixed
    {
        return $this->message;
    }

    public function getLastPosition(): ?int
    {
        return $this->lastPosition;
    }
}
