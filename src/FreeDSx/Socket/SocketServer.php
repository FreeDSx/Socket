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

use FreeDSx\Socket\Exception\AcceptException;
use FreeDSx\Socket\Exception\ConnectionException;
use function array_filter;
use function array_values;
use function class_exists;
use function error_clear_last;
use function error_get_last;
use function is_finite;
use function is_resource;
use function is_string;
use function min;
use function sprintf;
use function stream_socket_accept;
use function stream_socket_recvfrom;
use function stream_socket_server;

/**
 * TCP/UDP/UNIX socket server to accept client connections.
 */
class SocketServer extends Socket
{
    /**
     * The longest wait select accepts on every platform, in seconds.
     */
    private const MAX_WAIT_SECONDS = 86_400.0;

    /**
     * Bounds the accept call itself in seconds, since a coroutine-hooked accept without a timeout waits for a client.
     */
    private const ACCEPT_WAIT_SECONDS = 0.01;

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
     * Adds the socket-level options, which only a listening socket has any use for.
     *
     * @return resource
     */
    protected function createSocketContext()
    {
        $this->context = stream_context_create([
            'ssl' => $this->options->toStreamContextSslOptions(),
            'socket' => $this->getOptions()->toStreamContextSocketOptions(),
        ]);

        return $this->context;
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
        $this->errorNumber = $errorNumber ?? 0;
        $this->errorMessage = $errorMessage ?? '';

        if ($socket === false) {
            throw new ConnectionException(sprintf(
                'Unable to open %s socket (%s): %s',
                \strtoupper($this->options->getTransport()->value),
                $this->errorNumber,
                $this->errorMessage,
            ));
        }
        $this->socket = $socket;
        self::loadAcceptClasses();

        return $this;
    }

    /**
     * Waits up to the timeout in seconds for a client, without limit when negative, or returns null when none arrives.
     *
     * @throws AcceptException when a waiting client cannot be accepted, which a caller should back off from.
     */
    public function accept(float $timeout = -1.0): ?Socket
    {
        if ($this->socket === null || $this->options->getTransport() === Transport::Udp) {
            return null;
        }

        $accepted = $this->acceptWithin(
            $this->socket,
            self::waitOf($timeout),
        );
        if ($accepted === null) {
            return null;
        }

        $client = new Socket(
            $accepted,
            self::optionsForAcceptedClient($this->getOptions()),
        );
        $this->clients[] = $client;

        return $client;
    }

    /**
     * Waiting before accepting is what tells a failure from a timeout.
     *
     * @param resource $socket
     * @return resource|null
     * @throws AcceptException
     */
    private function acceptWithin(
        $socket,
        ?float $wait,
    ) {
        $ready = self::selectReadable(
            $socket,
            $wait,
        );

        // A wait that fails, such as one a signal interrupts, falls back to waiting in the accept call.
        if ($ready === false) {
            $accepted = @stream_socket_accept(
                $socket,
                $wait ?? -1.0,
            );

            return is_resource($accepted)
                ? $accepted
                : null;
        }

        return $ready > 0
            ? $this->acceptWaitingClient($socket)
            : null;
    }

    /**
     * @param resource $socket
     * @return resource|null
     * @throws AcceptException
     */
    private function acceptWaitingClient($socket)
    {
        error_clear_last();
        $accepted = @stream_socket_accept(
            $socket,
            self::ACCEPT_WAIT_SECONDS,
        );
        if (is_resource($accepted)) {
            return $accepted;
        }
        $reason = error_get_last()['message'] ?? 'unknown error';

        // Nothing left waiting means the client went elsewhere, such as to another process or a failed TLS handshake.
        $stillWaiting = self::selectReadable(
            $socket,
            0.0,
        );
        if ($stillWaiting !== 1) {
            return null;
        }

        throw new AcceptException(sprintf(
            'Unable to accept a waiting client: %s',
            $reason,
        ));
    }

    /**
     * A negative or infinite timeout waits without limit, and a finite one is capped at what select accepts.
     */
    private static function waitOf(float $timeout): ?float
    {
        if ($timeout < 0 || !is_finite($timeout)) {
            return null;
        }

        return min(
            $timeout,
            self::MAX_WAIT_SECONDS,
        );
    }

    /**
     * Loads what we need up front. This guards against being out of descriptors when we need to throw.
     */
    private static function loadAcceptClasses(): void
    {
        class_exists(AcceptException::class);
        class_exists(SocketAddress::class);
    }

    private static function optionsForAcceptedClient(SocketServerOptions $server): SocketOptionsInterface
    {
        $clone = clone $server;
        $clone->setTimeoutRead($server->getIdleTimeout());
        $clone->setTimeoutWrite($server->getWriteTimeout());

        return $clone;
    }

    /**
     * Receive data from a UDP based socket. Optionally get the IP address the data was received from.
     *
     * @param-out string|null $ipAddress
     * @todo Buffer size should be adjustable. Max UDP packet size is 65507. Currently this avoids possible truncation.
     */
    public function receive(?string &$ipAddress = null): ?string
    {
        $this->block(true);

        $received = null;
        $data = stream_socket_recvfrom(
            $this->getStream(),
            65507,
            0,
            $received,
        );
        $ipAddress = is_string($received)
            ? $received
            : null;

        return $data === false
            ? null
            : $data;
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
        $this->clients = array_values(array_filter(
            $this->clients,
            fn (Socket $client): bool => $client !== $socket,
        ));
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
