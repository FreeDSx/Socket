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
use PhpSpec\ObjectBehavior;

/**
 * @todo Need to find a way to spec this properly.
 */
class SocketPoolSpec extends ObjectBehavior
{
    use RequiresUnixTransport;


    /**
     * @var resource|null
     */
    private $unixServer;

    /**
     * @var string|null
     */
    private $unixPath;

    function let()
    {
        $this->beConstructedWith(['servers' => ['foo', 'bar']]);
    }

    function letGo(): void
    {
        if (is_resource($this->unixServer)) {
            fclose($this->unixServer);
        }
        if ($this->unixPath !== null && file_exists($this->unixPath)) {
            @unlink($this->unixPath);
        }
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(SocketPool::class);
    }

    function it_should_respect_the_transport_type_when_connecting()
    {
        $this->requireUnixTransport();
        $this->unixPath = sys_get_temp_dir() . '/freedsx_socket_pool_' . uniqid('', true) . '.sock';
        $this->unixServer = stream_socket_server('unix://' . $this->unixPath);

        $this->beConstructedWith([
            'servers' => [$this->unixPath],
            'transport' => 'unix',
        ]);

        $this->connect()->isConnected()->shouldBeEqualTo(true);
    }
}
