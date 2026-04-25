<?php

/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Socket;

use PhpSpec\Exception\Example\SkippingException;

trait RequiresUnixTransport
{
    private function requireUnixTransport(): void
    {
        if (!in_array('unix', stream_get_transports(), true)) {
            throw new SkippingException('The "unix" stream transport is not available on this platform.');
        }
    }
}
