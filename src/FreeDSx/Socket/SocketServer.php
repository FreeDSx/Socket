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
 * TCP socket server to accept client connections.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class SocketServer extends Socket
{
    /**
     * @var array
     */
    protected $serverOpts = [
        'ssl_cert' => null,
        'ssl_cert_key' => null,
        'ssl_cert_passphrase' => null,
        'ssl_crypto_type' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_1_SERVER | STREAM_CRYPTO_METHOD_TLS_SERVER,
        'ssl_validate_cert' => false,
        'idle_timeout' => 600,
    ];

    /**
     * @var Socket[]
     */
    protected $clients = [];

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        parent::__construct(null, \array_merge($this->serverOpts, $options));
    }

    /**
     * Create the socket server and bind to a specific port to listen for clients.
     *
     * @param string $ip
     * @param int $port
     * @return $this
     * @throws ConnectionException
     * @internal param string $ip
     */
    public function listen(string $ip, int $port)
    {
        $flags = STREAM_SERVER_BIND;
        if ($this->options['transport'] !== 'udp') {
            $flags |= STREAM_SERVER_LISTEN;
        }
        $socket = @\stream_socket_server(
            $this->options['transport'].'://'.$ip.':'.$port,
            $this->errorNumber,
            $this->errorMessage,
            $flags,
            $this->createSocketContext()
        );
        if ($socket === false) {
            throw new ConnectionException(sprintf(
                'Unable to open %s socket (%s): %s',
                \strtoupper($this->options['transport']),
                $this->errorNumber,
                $this->errorMessage
            ));
        }
        $this->socket = $socket;

        return $this;
    }

    /**
     * @param int $timeout
     * @return null|Socket
     */
    public function accept($timeout = -1) : ?Socket
    {
        $socket = @\stream_socket_accept($this->socket, $timeout);
        if (\is_resource($socket)) {
            $socket = new Socket($socket, \array_merge($this->options, [
                'timeout_read' => $this->options['idle_timeout']
            ]));
            $this->clients[] = $socket;
        }

        return $socket instanceof Socket ? $socket : null;
    }

    /**
     * Receive data from a UDP based socket. Optionally get the IP address the data was received from.
     *
     * @todo Buffer size should be adjustable. Max UDP packet size is 65507. Currently this avoids possible truncation.
     * @param null $ipAddress
     * @return null|string
     */
    public function receive(&$ipAddress = null)
    {
        $this->block(true);

        return \stream_socket_recvfrom($this->socket, 65507, 0, $ipAddress);
    }

    /**
     * @return Socket[]
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     * @param Socket $socket
     */
    public function removeClient(Socket $socket) : void
    {
        if (($index = \array_search($socket, $this->clients, true)) !== false) {
            unset($this->clients[$index]);
        }
    }

    /**
     * Create the socket server. Binds and listens on a specific port
     *
     * @param string $ip
     * @param int $port
     * @param array $options
     * @return SocketServer
     * @throws ConnectionException
     */
    public static function bind(string $ip, int $port, array $options = []) : SocketServer
    {
        return (new self($options))->listen($ip, $port);
    }

    /**
     * Create a TCP based socket server.
     *
     * @param string $ip
     * @param int $port
     * @param array $options
     * @return SocketServer
     * @throws ConnectionException
     */
    public static function bindTcp(string $ip, int $port, array $options = []) : SocketServer
    {
        return static::bind($ip, $port, \array_merge($options, ['transport' => 'tcp']));
    }

    /**
     * Created a UDP based socket server.
     *
     * @param string $ip
     * @param int $port
     * @param array $options
     * @return SocketServer
     * @throws ConnectionException
     */
    public static function bindUdp(string $ip, int $port, array $options = []) : SocketServer
    {
        return static::bind($ip, $port, \array_merge($options, ['transport' => 'udp']));
    }
}
