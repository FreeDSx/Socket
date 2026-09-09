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

use FreeDSx\Socket\Exception\WriteTimeoutException;

use function error_clear_last;
use function fwrite;
use function sprintf;
use function stream_select;
use function stream_set_blocking;
use function substr;

/**
 * Bounds a stalled send with a blocking stream_select loop.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
final class BlockingSelectEnforcer implements WriteTimeoutEnforcerInterface
{
    use ThrowsWriteError;

    public function write(
        $stream,
        string $data,
        int $timeout,
    ): void {
        $remaining = $data;

        stream_set_blocking(
            $stream,
            false,
        );

        try {
            while ($remaining !== '') {
                $write = [$stream];
                $read = [];
                $except = [];
                $ready = @stream_select(
                    $read,
                    $write,
                    $except,
                    $timeout,
                );

                // The write itself decides whether the socket is still usable.
                if ($ready === 0) {
                    throw new WriteTimeoutException(sprintf(
                        'The write operation timed out after %d seconds.',
                        $timeout,
                    ));
                }

                $remaining = $this->writeRemaining(
                    $stream,
                    $remaining,
                );
            }
        } finally {
            @stream_set_blocking(
                $stream,
                true,
            );
        }
    }

    /**
     * Writes what is left and returning what still has to go out.
     *
     * @param resource $stream
     */
    private function writeRemaining(
        $stream,
        string $remaining,
    ): string {
        error_clear_last();
        $written = @fwrite(
            $stream,
            $remaining,
        );

        if ($written === false) {
            $this->throwWriteError();
        }

        return substr(
            $remaining,
            $written,
        );
    }
}
