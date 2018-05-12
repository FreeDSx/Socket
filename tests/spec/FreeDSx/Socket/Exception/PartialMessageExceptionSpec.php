<?php
/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Socket\Exception;

use FreeDSx\Socket\Exception\PartialMessageException;
use PhpSpec\ObjectBehavior;

class PartialMessageExceptionSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(PartialMessageException::class);
    }

    function it_should_extend_exception()
    {
        $this->shouldBeAnInstanceOf(\Exception::class);
    }
}
