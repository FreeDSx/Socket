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
use function fread;
use function fwrite;
use function stream_context_create;
use function stream_set_blocking;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_enable_crypto;
use function stream_socket_shutdown;

/**
 * Represents a generic socket.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Socket
{
    protected bool $isEncrypted = false;

    /**
     * @var resource|null
     */
    protected $socket;

    /**
     * @var resource|null
     */
    protected $context;

    protected string $errorMessage = '';

    protected int $errorNumber = 0;

    /**
     * @param resource|null $resource
     */
    public function __construct(
        $resource = null,
        protected readonly SocketOptionsInterface $options = new SocketOptions(),
    ) {
        $this->socket = $resource;
        if ($this->socket !== null) {
            $this->setStreamOpts();
        }
    }

    public function read(bool $block = true): string|false
    {
        stream_set_blocking($this->socket, $block);

        $data = fread(
            $this->socket,
            $this->options->getBufferSize(),
        );

        if (!$block) {
            stream_set_blocking(
                $this->socket,
                true,
            );
        }

        return $data === ''
            ? false
            : $data;
    }

    public function write(string $data): static
    {
        @fwrite($this->socket, $data);

        return $this;
    }

    public function block(bool $block): static
    {
        stream_set_blocking($this->socket, $block);

        return $this;
    }

    public function isConnected(): bool
    {
        if ($this->socket === null) {
            return false;
        }

        // Slight optimization. The feof() method should be more accurate and unix socket should be less likely.
        // In PHP 8.2 feof is not accurate for checking a UNIX socket.
        if ($this->options->getTransport() !== Transport::Unix) {
            return !@\feof($this->socket);
        }

        // The is_resource() function will also check if a resource is connected or not.
        return \is_resource($this->socket);
    }

    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }

    public function close(): static
    {
        if ($this->socket !== null) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        }
        $this->socket = null;
        $this->isEncrypted = false;
        $this->context = null;

        return $this;
    }

    /**
     * Enable/Disable encryption on the TCP connection stream.
     *
     * @throws ConnectionException
     */
    public function encrypt(bool $encrypt): static
    {
        stream_set_blocking($this->socket, true);
        $result = stream_socket_enable_crypto(
            $this->socket,
            $encrypt,
            $this->options->getSslCryptoMethod(),
        );
        stream_set_blocking($this->socket, false);

        if ($result !== true) {
            throw new ConnectionException(sprintf(
                'Unable to %s encryption on TCP connection. %s',
                $encrypt ? 'enable' : 'disable',
                $this->errorMessage,
            ));
        }
        $this->isEncrypted = $encrypt;

        return $this;
    }

    /**
     * @throws ConnectionException
     */
    public function connect(string $host): static
    {
        $transport = $this->options->getTransport();
        $scheme = $transport === Transport::Tcp && $this->options->isUseSsl()
            ? 'ssl'
            : $transport->value;

        $uri = $scheme . '://' . $host;
        if ($transport !== Transport::Unix) {
            $uri .= ':' . $this->options->getPort();
        }

        $errorNumber = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            $uri,
            $errorNumber,
            $errorMessage,
            $this->options->getTimeoutConnect(),
            STREAM_CLIENT_CONNECT,
            $this->createSocketContext(),
        );
        $this->errorNumber = $errorNumber;
        $this->errorMessage = $errorMessage;

        if ($socket === false) {
            throw new ConnectionException(sprintf(
                'Unable to connect to %s: %s',
                $host,
                $this->errorMessage,
            ));
        }
        $this->socket = $socket;
        $this->setStreamOpts();
        $this->isEncrypted = $this->options->isUseSsl();

        return $this;
    }

    public function getOptions(): SocketOptionsInterface
    {
        return $this->options;
    }

    /**
     * Create a socket by connecting to a specific host.
     *
     * @throws ConnectionException
     */
    public static function create(
        string $host,
        ?SocketOptions $options = null,
    ): Socket {
        return (new self(null, $options))->connect($host);
    }

    /**
     * Create a UNIX based socket.
     *
     * @param string $file The full path to the unix socket.
     * @throws ConnectionException
     */
    public static function unix(
        string $file,
        ?SocketOptions $options = null,
    ): Socket {
        $options ??= new SocketOptions();
        $options->setTransport(Transport::Unix);

        return self::create($file, $options);
    }

    /**
     * Create a TCP based socket.
     *
     * @throws ConnectionException
     */
    public static function tcp(
        string $host,
        SocketOptions $options = new SocketOptions(),
    ): Socket {
        $options->setTransport(Transport::Tcp);

        return self::create(
            $host,
            $options,
        );
    }

    /**
     * Create a UDP based socket.
     *
     * @throws ConnectionException
     */
    public static function udp(
        string $host,
        SocketOptions $options = new SocketOptions(),
    ): Socket {
        $options
            ->setTransport(Transport::Udp)
            ->setBufferSize(65507);

        return self::create(
            $host,
            $options,
        );
    }

    /**
     * @return resource
     */
    protected function createSocketContext()
    {
        $this->context = stream_context_create([
            'ssl' => $this->options->toStreamContextSslOptions(),
        ]);

        return $this->context;
    }

    /**
     * Sets options on the stream that must be done after it is a resource.
     */
    protected function setStreamOpts(): void
    {
        stream_set_timeout(
            $this->socket,
            $this->options->getTimeoutRead(),
        );
    }
}
