<?php
/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Socket;

use FreeDSx\Socket\SocketPool;
use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\ObjectBehavior;

/**
 * @todo Need to find a way to spec this properly.
 */
class SocketPoolSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(['servers' => ['foo', 'bar']]);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(SocketPool::class);
    }

    function it_should_respect_the_transport_type_when_connecting()
    {
        if (!file_exists('/var/run/docker.sock')) {
            throw new SkippingException('The /var/run/docker.sock file must exist to test unix sockets.');
        }
        $this->beConstructedWith([
            'servers' => ['/var/run/docker.sock'],
             'transport' => 'unix',
        ]);

        $this->connect()->isConnected()->shouldBeEqualTo(true);
    }
}
