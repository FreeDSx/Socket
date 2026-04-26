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

use FreeDSx\Socket\Exception\ConnectionException;
use Throwable;
use function implode;
use function sprintf;

/**
 * Given a selection of hosts, connect to one and return the Socket.
 */
class SocketPool
{
    public function __construct(protected SocketPoolOptions $options)
    {
    }

    /**
     * @throws ConnectionException
     */
    public function connect(string $hostname = ''): Socket
    {
        $hosts = $hostname !== ''
            ? [$hostname]
            : $this->options->getServers();

        $lastEx = null;
        $socket = null;
        foreach ($hosts as $host) {
            try {
                $socket = Socket::create(
                    $host,
                    $this->options->getSocket()
                );
                break;
            } catch (Throwable $e) {
                $lastEx = $e;
            }
        }

        if ($socket === null) {
            throw new ConnectionException(sprintf(
                'Unable to connect to server(s): %s',
                implode(',', $hosts),
            ), 0, $lastEx);
        }

        return $socket;
    }
}
