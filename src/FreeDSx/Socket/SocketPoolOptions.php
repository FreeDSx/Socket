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

namespace FreeDSx\Socket;

/**
 * Configuration consumed by SocketPool. Composes a SocketOptions used for each
 * Socket the pool creates.
 */
final class SocketPoolOptions
{
    /**
     * @var list<string>
     */
    private array $servers = [];

    private SocketOptions $socket;

    public function __construct(?SocketOptions $socket = null)
    {
        $this->socket = $socket ?? (new SocketOptions())->setTimeoutConnect(1);
    }

    /**
     * @param list<string> $servers
     */
    public function setServers(array $servers): self
    {
        $this->servers = $servers;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    public function setSocket(SocketOptions $socket): self
    {
        $this->socket = $socket;

        return $this;
    }

    public function getSocket(): SocketOptions
    {
        return $this->socket;
    }
}
