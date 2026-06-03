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
use FreeDSx\Socket\Timeout\BlockingSelectEnforcer;
use PHPUnit\Framework\TestCase;

final class BlockingSelectEnforcerTest extends TestCase
{
    private BlockingSelectEnforcer $subject;

    /**
     * @var resource|null
     */
    private $local;

    /**
     * @var resource|null
     */
    private $remote;

    protected function setUp(): void
    {
        $this->subject = new BlockingSelectEnforcer();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->remote)) {
            fclose($this->remote);
        }
        if (is_resource($this->local)) {
            fclose($this->local);
        }
    }

    public function test_it_sends_all_data_when_the_peer_reads(): void
    {
        [$local, $remote] = $this->createSocketPair();

        $this->subject->write(
            $local,
            '0123456789',
            5,
        );

        self::assertSame(
            '0123456789',
            fread($remote, 10),
        );
    }

    public function test_it_restores_blocking_mode_after_a_successful_write(): void
    {
        [$local] = $this->createSocketPair();

        $this->subject->write(
            $local,
            '0123456789',
            5,
        );

        self::assertTrue(stream_get_meta_data($local)['blocked']);
    }

    public function test_it_throws_when_the_peer_stops_reading(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Cannot fill the socket to force a send stall on Windows.');
        }

        [$local] = $this->createSocketPair();

        $this->expectException(WriteTimeoutException::class);

        $this->subject->write(
            $local,
            str_repeat('x', 32 * 1024 * 1024),
            1,
        );
    }

    /**
     * @return array{0: resource, 1: resource}
     */
    private function createSocketPair(): array
    {
        $pair = stream_socket_pair(
            DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );
        if ($pair === false) {
            self::fail('Failed to create socket pair.');
        }
        [$this->local, $this->remote] = $pair;

        return [
            $pair[0],
            $pair[1],
        ];
    }
}
