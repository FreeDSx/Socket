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

use FreeDSx\Socket\SocketPool;
use PHPUnit\Framework\TestCase;

final class SocketPoolTest extends TestCase
{
    use RequiresUnixTransport;

    /**
     * @var resource|null
     */
    private $unixServer;

    private ?string $unixPath = null;

    protected function tearDown(): void
    {
        if (is_resource($this->unixServer)) {
            fclose($this->unixServer);
        }
        if ($this->unixPath !== null && file_exists($this->unixPath)) {
            @unlink($this->unixPath);
        }
    }

    public function test_it_should_respect_the_transport_type_when_connecting(): void
    {
        $this->requireUnixTransport();
        $this->unixPath = sys_get_temp_dir() . '/freedsx_socket_pool_' . uniqid('', true) . '.sock';
        $server = stream_socket_server('unix://' . $this->unixPath);
        if ($server === false) {
            self::fail('Failed to create unix socket server.');
        }
        $this->unixServer = $server;

        $subject = new SocketPool([
            'servers' => [$this->unixPath],
            'transport' => 'unix',
        ]);

        self::assertTrue($subject->connect()->isConnected());
    }
}
