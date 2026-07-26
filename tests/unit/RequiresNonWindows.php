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

namespace Tests\Unit\FreeDSx\Socket;

trait RequiresNonWindows
{
    /**
     * Skips a test whose behaviour depends on socket semantics Windows does not share.
     */
    private function requireNonWindows(string $reason): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped($reason);
        }
    }

    /**
     * Windows cannot be made to stall a send by filling the socket buffer.
     */
    private function requireFillableSocket(): void
    {
        $this->requireNonWindows('Cannot fill the socket to force a send stall on Windows.');
    }
}
