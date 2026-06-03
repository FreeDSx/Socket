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

use FreeDSx\Socket\Exception\WriteTimeoutException;
use FreeDSx\Socket\Timeout\SwooleTimerEnforcer;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Runtime;
use Throwable;

final class SwooleTimerEnforcerTest extends TestCase
{
    private SwooleTimerEnforcer $subject;

    protected function setUp(): void
    {
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('The swoole extension is required.');
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Cannot fill the socket to force a send stall on Windows.');
        }

        $this->subject = new SwooleTimerEnforcer();
    }

    public function test_it_falls_back_to_the_blocking_select_loop_outside_a_coroutine(): void
    {
        [$local, $remote] = $this->createSocketPair();

        try {
            $this->expectException(WriteTimeoutException::class);

            $this->subject->write(
                $local,
                str_repeat('x', 32 * 1024 * 1024),
                1,
            );
        } finally {
            fclose($local);
            fclose($remote);
        }
    }

    public function test_it_sends_all_data_inside_a_coroutine(): void
    {
        $received = null;
        $this->runInCoroutine(function () use (&$received): void {
            [$local, $remote] = $this->createSocketPair();

            try {
                $this->subject->write(
                    $local,
                    '0123456789',
                    5,
                );
                $received = fread($remote, 10);
            } finally {
                fclose($local);
                fclose($remote);
            }
        });

        self::assertSame(
            '0123456789',
            $received,
        );
    }

    public function test_it_throws_a_write_timeout_inside_a_coroutine_when_the_peer_stalls(): void
    {
        $caught = null;
        $this->runInCoroutine(function () use (&$caught): void {
            [$local, $remote] = $this->createSocketPair();

            try {
                $this->subject->write(
                    $local,
                    str_repeat('x', 32 * 1024 * 1024),
                    1,
                );
            } catch (Throwable $e) {
                $caught = $e;
            } finally {
                fclose($local);
                fclose($remote);
            }
        });

        self::assertInstanceOf(
            WriteTimeoutException::class,
            $caught,
        );
    }

    private function runInCoroutine(callable $callback): void
    {
        Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

        try {
            Coroutine\run($callback);
        } finally {
            Runtime::enableCoroutine(0);
        }
    }

    /**
     * @return array{0: resource, 1: resource}
     */
    private function createSocketPair(): array
    {
        $pair = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );
        if ($pair === false) {
            self::fail('Failed to create socket pair.');
        }

        return [$pair[0], $pair[1]];
    }
}
