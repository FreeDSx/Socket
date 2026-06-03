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

namespace FreeDSx\Socket\Timeout;

use FreeDSx\Socket\Exception\ConnectionException;

use function error_get_last;

/**
 * Shared write-failure throwing for the write timeout enforcers.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
trait ThrowsWriteError
{
    private const WRITE_ERROR_MESSAGE = 'Unable to write to the socket.';

    /**
     * @throws ConnectionException always, enriched with the last PHP error when present.
     */
    private function throwWriteError(): never
    {
        $message = self::WRITE_ERROR_MESSAGE;
        $error = error_get_last();

        if ($error !== null && $error['message'] !== '') {
            $message .= ' ' . $error['message'];
        }

        throw new ConnectionException($message);
    }
}
