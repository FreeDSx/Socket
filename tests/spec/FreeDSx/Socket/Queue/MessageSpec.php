<?php
/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Socket\Queue;

use fixture\FreeDSx\Socket\Pdu;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Socket\Queue\Message;
use PhpSpec\ObjectBehavior;

class MessageSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(new Pdu(new IntegerType(1)));
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(Message::class);
    }

    function it_should_get_the_message()
    {
        $this->getMessage()->shouldBeLike(new Pdu(new IntegerType(1)));
    }

    function it_should_have_no_trailing_data_by_default()
    {
        $this->getTrailingData()->shouldBeNull();
    }

    function it_should_get_trailing_data_if_it_exists()
    {
        $this->beConstructedWith(new Pdu(new IntegerType(1)), 'foo');

        $this->getTrailingData()->shouldBeEqualTo('foo');
    }
}
