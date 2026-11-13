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

namespace Tests\Unit\FreeDSx\Socket\Timeout;

use FreeDSx\Socket\Exception\IdleTimeoutException;
use FreeDSx\Socket\Exception\ReadTimeoutException;
use FreeDSx\Socket\Timeout\ReadDeadline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReadDeadlineTest extends TestCase
{
    /**
     * @return array<string, array{int, float}>
     */
    public static function setByTimeoutProvider(): array
    {
        return [
            'shorter than the read timeout' => [
                10,
                0.5,
            ],
            'without a read timeout' => [
                0,
                5.0,
            ],
        ];
    }

    /**
     * @return array<string, array{int, float|null}>
     */
    public static function setByReadTimeoutProvider(): array
    {
        return [
            'a longer timeout given' => [
                1,
                5.0,
            ],
            'an equal timeout given' => [
                5,
                5.0,
            ],
            'no timeout given' => [
                10,
                null,
            ],
            'a timeout given that is not finite' => [
                10,
                INF,
            ],
        ];
    }

    /**
     * @return array<string, array{int, float|null}>
     */
    public static function unboundedProvider(): array
    {
        return [
            'no timeouts' => [
                0,
                null,
            ],
            'a negative read timeout' => [
                -1,
                null,
            ],
            'an infinite timeout given' => [
                0,
                INF,
            ],
            'a timeout given that is not a number' => [
                0,
                NAN,
            ],
        ];
    }

    /**
     * @return array<string, array{float}>
     */
    public static function noTimeLeftProvider(): array
    {
        return [
            'zero' => [0.0],
            'negative' => [-1.0],
        ];
    }

    #[DataProvider('setByTimeoutProvider')]
    public function test_a_deadline_set_by_the_timeout_given_is_exceeded_with_a_read_timeout(
        int $readTimeout,
        float $timeout,
    ): void {
        self::assertInstanceOf(
            ReadTimeoutException::class,
            ReadDeadline::start(
                $readTimeout,
                $timeout,
            )->exceeded(),
        );
    }

    #[DataProvider('setByReadTimeoutProvider')]
    public function test_a_deadline_set_by_the_read_timeout_is_exceeded_with_an_idle_timeout(
        int $readTimeout,
        ?float $timeout,
    ): void {
        self::assertInstanceOf(
            IdleTimeoutException::class,
            ReadDeadline::start(
                $readTimeout,
                $timeout,
            )->exceeded(),
        );
    }

    public function test_a_read_timeout_names_the_timeout_given(): void
    {
        self::assertSame(
            'The read did not complete within its timeout of 0.5 seconds.',
            ReadDeadline::start(
                10,
                0.5,
            )->exceeded()->getMessage(),
        );
    }

    public function test_a_deadline_without_a_timeout_given_leaves_the_stream_timeout_as_it_is(): void
    {
        self::assertNull(ReadDeadline::start(
            10,
            null,
        )->streamTimeout());
    }

    public function test_a_timeout_given_bounds_the_stream_by_the_time_left(): void
    {
        $streamTimeout = ReadDeadline::start(
            10,
            0.5,
        )->streamTimeout();

        self::assertNotNull($streamTimeout);
        self::assertGreaterThan(
            0.0,
            $streamTimeout,
        );
        self::assertLessThanOrEqual(
            0.5,
            $streamTimeout,
        );
    }

    public function test_a_timeout_given_bounds_the_stream_by_the_read_timeout_when_that_is_shorter(): void
    {
        $streamTimeout = ReadDeadline::start(
            1,
            5.0,
        )->streamTimeout();

        self::assertNotNull($streamTimeout);
        self::assertGreaterThan(
            0.5,
            $streamTimeout,
        );
        self::assertLessThanOrEqual(
            1.0,
            $streamTimeout,
        );
    }

    #[DataProvider('unboundedProvider')]
    public function test_a_read_without_a_finite_timeout_has_no_deadline(
        int $readTimeout,
        ?float $timeout,
    ): void {
        $subject = ReadDeadline::start(
            $readTimeout,
            $timeout,
        );

        self::assertFalse($subject->isBounded());
        self::assertNull($subject->remaining());
        self::assertFalse($subject->hasPassed());
        self::assertNull($subject->streamTimeout());
    }

    public function test_a_deadline_has_time_left_before_it_passes(): void
    {
        $subject = ReadDeadline::start(
            10,
            null,
        );

        self::assertTrue($subject->isBounded());
        self::assertFalse($subject->hasPassed());
        self::assertGreaterThan(
            9.0,
            $subject->remaining(),
        );
    }

    #[DataProvider('noTimeLeftProvider')]
    public function test_a_timeout_given_of_zero_or_less_has_already_passed(float $timeout): void
    {
        $subject = ReadDeadline::start(
            10,
            $timeout,
        );

        self::assertTrue($subject->hasPassed());
        self::assertSame(
            0.0,
            $subject->remaining(),
        );
        self::assertInstanceOf(
            ReadTimeoutException::class,
            $subject->exceeded(),
        );
    }
}
