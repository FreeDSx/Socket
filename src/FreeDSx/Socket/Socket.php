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
use FreeDSx\Socket\Exception\IdleTimeoutException;
use FreeDSx\Socket\Timeout\WriteTimeoutEnforcerInterface;
use FreeDSx\Socket\Tls\Certificate;
use OpenSSLCertificate;

use function fclose;
use function fread;
use function fwrite;
use function is_array;
use function sprintf;
use function stream_context_create;
use function stream_context_get_options;
use function stream_get_meta_data;
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

    protected readonly WriteTimeoutEnforcerInterface $writeTimeoutEnforcer;

    /**
     * @param resource|null $resource
     */
    public function __construct(
        $resource = null,
        protected readonly SocketOptionsInterface $options = new SocketOptions(),
        ?WriteTimeoutEnforcerInterface $writeTimeoutEnforcer = null,
    ) {
        $this->socket = $resource;
        $this->writeTimeoutEnforcer = $writeTimeoutEnforcer ?? $this->options->getWriteTimeoutEnforcer();
        if ($this->socket !== null) {
            $this->setStreamOpts();
        }
    }

    /**
     * @throws IdleTimeoutException when a blocking read makes no progress within the configured read timeout.
     */
    public function read(bool $block = true): string|false
    {
        $stream = $this->getStream();
        stream_set_blocking($stream, $block);

        $data = fread(
            $stream,
            $this->options->getBufferSize(),
        );

        // A blocking read yields no bytes on either a peer disconnect (feof, empty string) or a read timeout
        // (false). Only the timeout case sets the timed_out meta flag, which distinguishes the two.
        if ($block && ($data === '' || $data === false) && $this->hasReadTimedOut($stream)) {
            throw new IdleTimeoutException(sprintf(
                'The connection was idle for longer than the read timeout of %d seconds.',
                $this->options->getTimeoutRead(),
            ));
        }

        if (!$block) {
            stream_set_blocking(
                $stream,
                true,
            );
        }

        return $data === ''
            ? false
            : $data;
    }

    /**
     * @param resource $stream
     */
    private function hasReadTimedOut($stream): bool
    {
        return $this->options->getTimeoutRead() > 0
            && stream_get_meta_data($stream)['timed_out'] === true;
    }

    public function write(string $data): static
    {
        $timeout = $this->options->getTimeoutWrite();
        $stream = $this->getStream();

        if ($timeout <= 0) {
            @fwrite(
                $stream,
                $data,
            );

            return $this;
        }

        $this->writeTimeoutEnforcer->write(
            $stream,
            $data,
            $timeout,
        );

        return $this;
    }

    public function block(bool $block): static
    {
        stream_set_blocking(
            $this->getStream(),
            $block
        );

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

    /**
     * The peer's verified TLS certificate, if one was presented during the handshake.
     */
    public function getPeerCertificate(): ?Certificate
    {
        if ($this->socket === null) {
            return null;
        }

        $ssl = stream_context_get_options($this->socket)['ssl'] ?? null;
        $peer = is_array($ssl)
            ? ($ssl['peer_certificate'] ?? null)
            : null;

        return $peer instanceof OpenSSLCertificate
            ? Certificate::fromX509($peer)
            : null;
    }

    /**
     * @param bool $shutdown False closes only this process's FD copy without affecting shared socket state.
     */
    public function close(bool $shutdown = true): static
    {
        if ($this->socket !== null) {
            $shutdown
                ? stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR)
                : fclose($this->socket);
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
        $stream = $this->getStream();

        stream_set_blocking(
            $stream,
            true,
        );
        $result = stream_socket_enable_crypto(
            $stream,
            $encrypt,
            $this->options->getSslCryptoMethod(),
        );
        stream_set_blocking(
            $stream,
            false,
        );

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
        $this->errorNumber = $errorNumber ?? 0;
        $this->errorMessage = $errorMessage ?? '';

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
        SocketOptions $options = new SocketOptions(),
    ): Socket {
        return (new self(
            null,
            $options,
        ))->connect($host);
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
            $this->getStream(),
            $this->options->getTimeoutRead(),
        );
    }

    /**
     * @return resource
     * @throws ConnectionException
     */
    protected function getStream()
    {
        if ($this->socket === null) {
            throw new ConnectionException('The socket is not connected.');
        }

        return $this->socket;
    }
}
