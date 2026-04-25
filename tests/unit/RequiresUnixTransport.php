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

trait RequiresUnixTransport
{
    private function requireUnixTransport(): void
    {
        if (!in_array('unix', stream_get_transports(), true)) {
            self::markTestSkipped('The "unix" stream transport is not available on this platform.');
        }
    }
}
