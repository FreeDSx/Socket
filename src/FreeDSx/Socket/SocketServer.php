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
use function array_search;
use function array_values;
use function is_resource;
use function stream_socket_accept;
use function stream_socket_recvfrom;
use function stream_socket_server;

/**
 * TCP/UDP/UNIX socket server to accept client connections.
 */
class SocketServer extends Socket
{
    /**
     * @var list<Socket>
     */
    protected array $clients = [];

    public function __construct(?SocketServerOptions $options = null)
    {
        parent::__construct(null, $options ?? new SocketServerOptions());
    }

    public function getOptions(): SocketServerOptions
    {
        \assert($this->options instanceof SocketServerOptions);

        return $this->options;
    }

    /**
     * Create the socket server and bind to a specific port to listen for clients.
     *
     * @throws ConnectionException
     */
    public function listen(
        string $ip,
        ?int $port,
    ): static {
        $transport = $this->options->getTransport();

        $flags = STREAM_SERVER_BIND;
        if ($transport !== Transport::Udp) {
            $flags |= STREAM_SERVER_LISTEN;
        }

        $scheme = $transport === Transport::Tcp && $this->options->isUseSsl()
            ? 'ssl'
            : $transport->value;

        if ($transport !== Transport::Unix && $port === null) {
            throw new ConnectionException('The port must be set if not using a unix based socket.');
        }

        $uri = $scheme . '://' . $ip;
        if ($port !== null && $transport !== Transport::Unix) {
            $uri .= ':' . $port;
        }

        $errorNumber = 0;
        $errorMessage = '';
        $socket = @stream_socket_server(
            $uri,
            $errorNumber,
            $errorMessage,
            $flags,
            $this->createSocketContext(),
        );
        $this->errorNumber = $errorNumber;
        $this->errorMessage = $errorMessage;

        if ($socket === false) {
            throw new ConnectionException(sprintf(
                'Unable to open %s socket (%s): %s',
                \strtoupper($this->options->getTransport()->value),
                $this->errorNumber,
                $this->errorMessage,
            ));
        }
        $this->socket = $socket;

        return $this;
    }

    public function accept(float $timeout = -1.0): ?Socket
    {
        if ($this->socket === null) {
            return null;
        }

        $accepted = @stream_socket_accept($this->socket, $timeout);
        if (!is_resource($accepted)) {
            return null;
        }

        $client = new Socket(
            $accepted,
            self::optionsForAcceptedClient($this->getOptions()),
        );
        $this->clients[] = $client;

        return $client;
    }

    private static function optionsForAcceptedClient(SocketServerOptions $server): SocketOptionsInterface
    {
        $clone = clone $server;
        $clone->setTimeoutRead($server->getIdleTimeout());

        return $clone;
    }

    /**
     * Receive data from a UDP based socket. Optionally get the IP address the data was received from.
     *
     * @todo Buffer size should be adjustable. Max UDP packet size is 65507. Currently this avoids possible truncation.
     */
    public function receive(?string &$ipAddress = null): ?string
    {
        $this->block(true);

        $data = stream_socket_recvfrom(
            $this->socket,
            65507,
            0,
            $ipAddress,
        );

        return $data === false ? null : $data;
    }

    /**
     * @return list<Socket>
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    public function removeClient(Socket $socket): void
    {
        $index = array_search($socket, $this->clients, true);
        if ($index !== false) {
            unset($this->clients[$index]);
            $this->clients = array_values($this->clients);
        }
    }

    /**
     * Create the socket server. Binds and listens on a specific port.
     *
     * @throws ConnectionException
     */
    public static function bind(
        string $ip,
        ?int $port,
        ?SocketServerOptions $options = null,
    ): SocketServer {
        return (new self($options))->listen(
            $ip,
            $port
        );
    }

    /**
     * Create a TCP based socket server.
     *
     * @throws ConnectionException
     */
    public static function bindTcp(
        string $ip,
        int $port,
        SocketServerOptions $options = new SocketServerOptions(),
    ): SocketServer {
        $options->setTransport(Transport::Tcp);

        return self::bind(
            $ip,
            $port,
            $options,
        );
    }

    /**
     * Create a UDP based socket server.
     *
     * @throws ConnectionException
     */
    public static function bindUdp(
        string $ip,
        int $port,
        SocketServerOptions $options = new SocketServerOptions(),
    ): SocketServer {
        $options->setTransport(Transport::Udp);

        return self::bind(
            $ip,
            $port,
            $options,
        );
    }

    /**
     * Create a UNIX based socket server.
     *
     * @throws ConnectionException
     */
    public static function bindUnix(
        string $socketFile,
        SocketServerOptions $options = new SocketServerOptions(),
    ): SocketServer {
        $options->setTransport(Transport::Unix);

        return self::bind(
            $socketFile,
            null,
            $options,
        );
    }
}
