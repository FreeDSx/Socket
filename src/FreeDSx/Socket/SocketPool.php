<?php
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

/**
 * Given a selection of hosts, connect to one and return the Socket.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class SocketPool
{
    /**
     * @var array
     */
    protected $options = [
        'servers' => [],
        'port' => 389,
        'timeout_connect' => 1,
    ];

    /**
     * @var array
     */
    protected $socketOpts = [
        'use_ssl',
        'ssl_validate_cert',
        'ssl_allow_self_signed',
        'ssl_ca_cert',
        'ssl_cert',
        'ssl_peer_name',
        'timeout_connect',
        'timeout_read',
        'port',
        'transport',
    ];

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = \array_merge($this->options, $options);
    }

    /**
     * @throws ConnectionException
     */
    public function connect(string $hostname = '') : Socket
    {
        $hosts = ($hostname !== '') ? [$hostname] : (array) $this->options['servers'];

        $lastEx = null;
        $socket = null;
        foreach ($hosts as $host) {
            try {
                $socket = Socket::create(
                    $host,
                    $this->getSocketOptions()
                );
                break;
            } catch (\Exception $e) {
                $lastEx = $e;
            }
        }

        if ($socket === null) {
            throw new ConnectionException(sprintf(
                'Unable to connect to server(s): %s',
                implode(',', $hosts)
            ), 0, $lastEx);
        }

        return $socket;
    }

    /**
     * @return array
     */
    protected function getSocketOptions() : array
    {
        $opts = [];

        foreach ($this->socketOpts as $name) {
            if (isset($this->options[$name])) {
                $opts[$name] = $this->options[$name];
            }
        }

        return $opts;
    }
}
