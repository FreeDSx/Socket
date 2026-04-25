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

namespace Tests\Unit\FreeDSx\Socket\Queue;

use FreeDSx\Socket\Queue\Buffer;
use PHPUnit\Framework\TestCase;

final class BufferTest extends TestCase
{
    private Buffer $subject;

    protected function setUp(): void
    {
        $this->subject = new Buffer('foo', 4);
    }

    public function test_it_should_get_the_bytes(): void
    {
        self::assertSame(
            'foo',
            $this->subject->bytes(),
        );
    }

    public function test_it_should_get_where_the_buffer_ends(): void
    {
        self::assertSame(
            4,
            $this->subject->endsAt(),
        );
    }
}
