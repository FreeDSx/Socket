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
use Swoole\Coroutine;
use Swoole\Timer;

use function error_clear_last;
use function fwrite;
use function sprintf;
use function substr;

/**
 * Bounds a stalled send with a Swoole\Timer watchdog around a coroutine write, with no per-write event-loop yield.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
final readonly class SwooleTimerEnforcer implements WriteTimeoutEnforcerInterface
{
    use ThrowsWriteError;

    public function __construct(
        private WriteTimeoutEnforcerInterface $fallback = new BlockingSelectEnforcer(),
    ) {}

    public function write(
        $stream,
        string $data,
        int $timeout,
    ): void {
        if (Coroutine::getCid() === -1) {
            $this->fallback->write(
                $stream,
                $data,
                $timeout,
            );

            return;
        }

        $this->writeWithinCoroutine(
            $stream,
            $data,
            $timeout,
        );
    }

    /**
     * @param resource $stream
     * @throws WriteTimeoutException when the timer fires before the send makes progress.
     * @throws ConnectionException on a write failure.
     */
    private function writeWithinCoroutine(
        $stream,
        string $data,
        int $timeout,
    ): void {
        $remaining = $data;
        $coroutineId = Coroutine::getCid();
        $timeoutMs = $timeout * 1000;

        while ($remaining !== '') {
            $timedOut = false;
            $timerId = Timer::after(
                $timeoutMs,
                static function () use ($coroutineId, &$timedOut): void {
                    $timedOut = true;
                    Coroutine::cancel($coroutineId);
                },
            );

            error_clear_last();
            $written = @fwrite(
                $stream,
                $remaining,
            );
            if ($timerId !== false) {
                Timer::clear($timerId);
            }

            if ($timedOut) {
                throw new WriteTimeoutException(sprintf(
                    'The write operation timed out after %d seconds.',
                    $timeout,
                ));
            }
            if ($written === false) {
                $this->throwWriteError();
            }

            $remaining = substr(
                $remaining,
                $written,
            );
        }
    }
}
