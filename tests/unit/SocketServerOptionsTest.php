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

use FreeDSx\Socket\SocketServerOptions;
use PHPUnit\Framework\TestCase;

final class SocketServerOptionsTest extends TestCase
{
    private SocketServerOptions $subject;

    protected function setUp(): void
    {
        $this->subject = new SocketServerOptions();
    }

    public function test_it_sets_no_socket_options_by_default(): void
    {
        self::assertSame(
            [],
            $this->subject->toStreamContextSocketOptions(),
        );
    }

    public function test_it_defaults_the_socket_options_to_off(): void
    {
        self::assertFalse($this->subject->isReusePort());
        self::assertFalse($this->subject->isReuseAddress());
        self::assertNull($this->subject->getBacklog());
    }

    public function test_it_emits_the_port_reuse_option(): void
    {
        $this->subject->setReusePort(true);

        self::assertSame(
            ['so_reuseport' => true],
            $this->subject->toStreamContextSocketOptions(),
        );
    }

    public function test_it_emits_the_address_reuse_option(): void
    {
        $this->subject->setReuseAddress(true);

        self::assertSame(
            ['so_reuseaddr' => true],
            $this->subject->toStreamContextSocketOptions(),
        );
    }

    public function test_it_emits_the_backlog_option(): void
    {
        $this->subject->setBacklog(256);

        self::assertSame(
            ['backlog' => 256],
            $this->subject->toStreamContextSocketOptions(),
        );
    }

    public function test_it_emits_every_option_that_was_set(): void
    {
        $this->subject
            ->setReusePort(true)
            ->setReuseAddress(true)
            ->setBacklog(128);

        self::assertSame(
            [
                'so_reuseport' => true,
                'so_reuseaddr' => true,
                'backlog' => 128,
            ],
            $this->subject->toStreamContextSocketOptions(),
        );
    }

    public function test_it_omits_an_option_that_was_switched_back_off(): void
    {
        $this->subject
            ->setReusePort(true)
            ->setReusePort(false)
            ->setBacklog(64)
            ->setBacklog(null);

        self::assertSame(
            [],
            $this->subject->toStreamContextSocketOptions(),
        );
    }
}
