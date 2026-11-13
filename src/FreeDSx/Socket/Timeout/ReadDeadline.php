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

use FreeDSx\Socket\Exception\IdleTimeoutException;
use FreeDSx\Socket\Exception\ReadTimeoutException;

use function is_finite;
use function max;
use function microtime;
use function sprintf;

/**
 * When a blocking read must finish, set by the shorter of the read timeout and a timeout given for that read.
 *
 * @internal
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
readonly class ReadDeadline
{
    private function __construct(
        private ?float $expiresAt,
        private int $readTimeout,
        private ?float $timeout,
        private bool $isSetByTimeout,
    ) {
    }

    /**
     * @param int $readTimeout The read timeout in seconds, or zero or less for none.
     * @param float|null $timeout The seconds given for the read, ignored when it is not finite.
     */
    public static function start(
        int $readTimeout,
        ?float $timeout,
    ): self {
        $timeout = $timeout !== null && is_finite($timeout)
            ? max(
                0.0,
                $timeout,
            )
            : null;
        $isSetByTimeout = self::isSetByTimeout(
            $timeout,
            $readTimeout,
        );
        $seconds = match (true) {
            $isSetByTimeout => $timeout,
            $readTimeout > 0 => (float) $readTimeout,
            default => null,
        };

        return new self(
            $seconds === null
                ? null
                : microtime(true) + $seconds,
            $readTimeout,
            $timeout,
            $isSetByTimeout,
        );
    }

    public function isBounded(): bool
    {
        return $this->expiresAt !== null;
    }

    /**
     * The seconds left, never below zero, or null when the read has no deadline.
     */
    public function remaining(): ?float
    {
        return $this->expiresAt === null
            ? null
            : max(
                0.0,
                $this->expiresAt - microtime(true),
            );
    }

    public function hasPassed(): bool
    {
        return $this->expiresAt !== null && microtime(true) >= $this->expiresAt;
    }

    /**
     * The seconds the stream itself may block for, or null to keep its read timeout when no timeout was given.
     */
    public function streamTimeout(): ?float
    {
        return $this->timeout === null
            ? null
            : $this->remaining();
    }

    public function exceeded(): IdleTimeoutException|ReadTimeoutException
    {
        if ($this->isSetByTimeout) {
            return new ReadTimeoutException(sprintf(
                'The read did not complete within its timeout of %g seconds.',
                $this->timeout,
            ));
        }

        return new IdleTimeoutException(sprintf(
            'The connection was idle for longer than the read timeout of %d seconds.',
            $this->readTimeout,
        ));
    }

    private static function isSetByTimeout(
        ?float $timeout,
        int $readTimeout,
    ): bool {
        return $timeout !== null
            && ($readTimeout <= 0 || $timeout < $readTimeout);
    }
}
