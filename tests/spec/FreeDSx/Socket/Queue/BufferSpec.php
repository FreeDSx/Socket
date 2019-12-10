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

use FreeDSx\Socket\Queue\Buffer;
use PhpSpec\ObjectBehavior;

class BufferSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('foo', 4);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(Buffer::class);
    }

    function it_should_get_the_bytes()
    {
        $this->bytes()->shouldBeEqualTo('foo');
    }

    function it_should_get_where_the_buffer_ends()
    {
        $this->endsAt()->shouldBeEqualTo(4);
    }
}
