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
use function in_array;
use function is_resource;
use function stream_socket_accept;
use function stream_socket_recvfrom;
use function stream_socket_server;

/**
 * TCP socket server to accept client connections.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class SocketServer extends Socket
{
    /**
     * Supported transport types.
     */
    public const TRANSPORTS = [
        'tcp',
        'udp',
        'unix',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $serverOpts = [
        'use_ssl' => false,
        'ssl_cert' => null,
        'ssl_cert_key' => null,
        'ssl_cert_passphrase' => null,
        'ssl_ciphers' => 'DEFAULT',
        'ssl_crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_1_SERVER | STREAM_CRYPTO_METHOD_TLS_SERVER,
        'ssl_validate_cert' => false,
        'idle_timeout' => 600,
    ];

    /**
     * @var list<Socket>
     */
    protected array $clients = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        parent::__construct(
            null,
            \array_merge(
                $this->serverOpts,
                $options,
            ),
        );
        if (!in_array($this->options['transport'], self::TRANSPORTS, true)) {
            throw new \RuntimeException(sprintf(
                'The transport "%s" is not valid. It must be one of: %s',
                $this->options['transport'],
                implode(',', self::TRANSPORTS),
            ));
        }
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
        $flags = STREAM_SERVER_BIND;
        if ($this->options['transport'] !== 'udp') {
            $flags |= STREAM_SERVER_LISTEN;
        }

        $transport = $this->options['transport'];
        if ($transport === 'tcp' && $this->options['use_ssl'] === true) {
            $transport = 'ssl';
        }

        if ($transport !== 'unix' && $port === null) {
            throw new ConnectionException('The port must be set if not using a unix based socket.');
        }

        $uri = $transport . '://' . $ip;
        if ($port !== null && $transport !== 'unix') {
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
                \strtoupper((string) $this->options['transport']),
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

        $client = new Socket($accepted, \array_merge($this->options, [
            'timeout_read' => $this->options['idle_timeout'],
        ]));
        $this->clients[] = $client;

        return $client;
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
     * @param array<string, mixed> $options
     * @throws ConnectionException
     */
    public static function bind(
        string $ip,
        ?int $port,
        array $options = [],
    ): SocketServer {
        return (new self($options))->listen(
            $ip,
            $port,
        );
    }

    /**
     * Create a TCP based socket server.
     *
     * @param array<string, mixed> $options
     * @throws ConnectionException
     */
    public static function bindTcp(
        string $ip,
        int $port,
        array $options = [],
    ): SocketServer {
        return self::bind(
            $ip,
            $port,
            \array_merge(
                $options,
                ['transport' => 'tcp'],
            ),
        );
    }

    /**
     * Create a UDP based socket server.
     *
     * @param array<string, mixed> $options
     * @throws ConnectionException
     */
    public static function bindUdp(
        string $ip,
        int $port,
        array $options = [],
    ): SocketServer {
        return self::bind(
            $ip,
            $port,
            \array_merge(
                $options,
                ['transport' => 'udp'],
            ),
        );
    }

    /**
     * Create a UNIX based socket server.
     *
     * @param array<string, mixed> $options
     * @throws ConnectionException
     */
    public static function bindUnix(
        string $socketFile,
        array $options = [],
    ): SocketServer {
        return self::bind(
            $socketFile,
            null,
            \array_merge(
                $options,
                ['transport' => 'unix'],
            ),
        );
    }
}
