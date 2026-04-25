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

use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Socket\Queue\Message;
use PHPUnit\Framework\TestCase;
use Tests\Unit\FreeDSx\Socket\Pdu;

final class MessageTest extends TestCase
{
    private Message $subject;

    protected function setUp(): void
    {
        $this->subject = new Message(new Pdu(new IntegerType(1)));
    }

    public function test_it_should_get_the_message(): void
    {
        self::assertEquals(
            new Pdu(new IntegerType(1)),
            $this->subject->getMessage(),
        );
    }

    public function test_it_should_have_no_last_position_data_by_default(): void
    {
        self::assertNull($this->subject->getLastPosition());
    }

    public function test_it_should_get_the_last_position(): void
    {
        $this->subject = new Message(new Pdu(new IntegerType(1)), 2);

        self::assertSame(
            2,
            $this->subject->getLastPosition(),
        );
    }
}
