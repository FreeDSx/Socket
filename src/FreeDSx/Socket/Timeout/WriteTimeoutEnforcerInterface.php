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
use FreeDSx\Socket\Exception\WriteTimeoutException;

/**
 * Strategy for writing all bytes to a stream while bounding a stalled send.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
interface WriteTimeoutEnforcerInterface
{
    /**
     * Write all the data, requiring progress within the timeout (seconds).
     *
     * @param resource $stream
     * @throws WriteTimeoutException when the send makes no progress within the timeout.
     * @throws ConnectionException on a write failure.
     */
    public function write(
        $stream,
        string $data,
        int $timeout,
    ): void;
}
