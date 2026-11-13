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
use FreeDSx\Socket\Exception\ReadTimeoutException;
use FreeDSx\Socket\Timeout\ReadDeadline;
use FreeDSx\Socket\Timeout\WriteTimeoutEnforcerInterface;
use FreeDSx\Socket\Tls\Certificate;
use OpenSSLCertificate;

use function error_get_last;
use function fclose;
use function fmod;
use function fread;
use function fwrite;
use function is_array;
use function is_resource;
use function is_string;
use function max;
use function sprintf;
use function stream_context_create;
use function stream_context_get_options;
use function stream_get_meta_data;
use function stream_select;
use function stream_set_blocking;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_enable_crypto;
use function stream_socket_get_name;
use function stream_socket_shutdown;

/**
 * Represents a generic socket.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Socket
{
    private const MICROSECONDS_PER_SECOND = 1_000_000;

    private const MINIMUM_STREAM_TIMEOUT = 0.001;

    protected bool $isEncrypted = false;

    protected ?SocketAddress $peerAddress = null;

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
            $this->isEncrypted = $this->options->isUseSsl();
            $this->peerAddress = $this->peerAddressOf($this->socket);
            $this->setStreamOpts();
        }
    }

    /**
     * @param float|null $timeout The most seconds a blocking read may take when shorter than the read timeout.
     * @throws IdleTimeoutException when a blocking read makes no progress within the configured read timeout.
     * @throws ReadTimeoutException when a blocking read does not complete within the timeout given for it.
     */
    public function read(
        bool $block = true,
        ?float $timeout = null,
    ): string|false {
        $stream = $this->getStream();
        if (!$block) {
            $data = $this->readWithoutBlocking($stream);

            return $data === ''
                ? false
                : $data;
        }

        // Bytes decrypted into the TLS buffer are off the wire already.
        $buffered = $this->isEncrypted
            ? $this->readWithoutBlocking($stream)
            : '';

        return $buffered !== ''
            ? $buffered
            : $this->readBlocking(
                $stream,
                ReadDeadline::start(
                    $this->options->getTimeoutRead(),
                    $timeout,
                ),
            );
    }

    /**
     * A timeout given for the read also bounds the stream, which a partial TLS record would otherwise hold.
     *
     * @param resource $stream
     * @throws IdleTimeoutException|ReadTimeoutException
     */
    private function readBlocking(
        $stream,
        ReadDeadline $deadline,
    ): string|false {
        $this->awaitReadable(
            $stream,
            $deadline,
        );
        $streamTimeout = $deadline->streamTimeout();
        if ($streamTimeout === null) {
            return $this->fetch(
                $stream,
                $deadline,
            );
        }

        $this->setReadTimeout(
            $stream,
            $streamTimeout,
        );
        try {
            return $this->fetch(
                $stream,
                $deadline,
            );
        } finally {
            $this->setStreamOpts();
        }
    }

    /**
     * @param resource $stream
     * @throws IdleTimeoutException|ReadTimeoutException
     */
    private function fetch(
        $stream,
        ReadDeadline $deadline,
    ): string|false {
        stream_set_blocking(
            $stream,
            true,
        );
        $data = fread(
            $stream,
            $this->options->getBufferSize(),
        );

        // A blocking read yields no bytes on either a peer disconnect (feof, empty string) or a read timeout
        // (false). Only the timeout case sets the timed_out meta flag, which distinguishes the two.
        if (($data === '' || $data === false) && $this->hasReadTimedOut($stream, $deadline)) {
            throw $deadline->exceeded();
        }

        return $data === ''
            ? false
            : $data;
    }

    /**
     * Take whatever is already buffered without waiting.
     *
     * @param resource $stream
     * @return string Whatever is in the buffer. Empty string when nothing is.
     */
    private function readWithoutBlocking($stream): string
    {
        stream_set_blocking($stream, false);

        $data = fread(
            $stream,
            $this->options->getBufferSize(),
        );

        stream_set_blocking($stream, true);

        return $data === false
            ? ''
            : $data;
    }

    /**
     * Wait for the socket to become readable. This enforces the deadline that the blocking read would.
     *
     * Waiting here returns on EINTR and lets the caller run its signal handlers.
     *
     * @param resource $stream
     * @throws IdleTimeoutException|ReadTimeoutException
     */
    private function awaitReadable(
        $stream,
        ReadDeadline $deadline,
    ): void {
        while (true) {
            $ready = self::selectReadable(
                $stream,
                $deadline->remaining(),
            );

            if ($ready > 0) {
                return;
            }
            if (!is_resource($stream)) {
                return;
            }
            if ($deadline->hasPassed()) {
                throw $deadline->exceeded();
            }
        }
    }

    /**
     * @param resource $stream
     */
    private function hasReadTimedOut(
        $stream,
        ReadDeadline $deadline,
    ): bool {
        return $deadline->isBounded()
            && stream_get_meta_data($stream)['timed_out'] === true;
    }

    /**
     * Never below a millisecond, since a stream timeout of zero waits without limit on TLS.
     *
     * @param resource $stream
     */
    private function setReadTimeout(
        $stream,
        float $seconds,
    ): void {
        $seconds = max(
            self::MINIMUM_STREAM_TIMEOUT,
            $seconds,
        );

        stream_set_timeout(
            $stream,
            (int) $seconds,
            (int) (fmod(
                $seconds,
                1.0,
            ) * self::MICROSECONDS_PER_SECOND),
        );
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
     * The address of the other end, or null when it is not known (such as for a socket pair).
     */
    public function getPeerAddress(): ?SocketAddress
    {
        return $this->peerAddress;
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
        // Bounded on its own, since the read timeout belongs to an idle session and can be far longer.
        stream_set_timeout(
            $stream,
            $this->options->getTimeoutHandshake(),
        );

        // Suppressed so a peer that stalls the handshake cannot make the process write to stderr.
        $result = @stream_socket_enable_crypto(
            $stream,
            $encrypt,
            $this->options->getSslCryptoMethod(),
        );
        $lastError = error_get_last();

        $this->setStreamOpts();
        stream_set_blocking(
            $stream,
            false,
        );

        if ($result !== true) {
            throw new ConnectionException(sprintf(
                'Unable to %s encryption on TCP connection. %s',
                $encrypt ? 'enable' : 'disable',
                $lastError['message'] ?? $this->errorMessage,
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
        $this->peerAddress = $this->peerAddressOf($socket);
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

    /**
     * Waits up to the timeout in seconds, or without limit when null, for the stream to become readable.
     *
     * @param resource $stream
     */
    protected static function selectReadable(
        $stream,
        ?float $timeout,
    ): int|false {
        $read = [$stream];
        $write = [];
        $except = [];
        $fraction = $timeout === null
            ? 0.0
            : fmod(
                $timeout,
                1.0,
            );

        return @stream_select(
            $read,
            $write,
            $except,
            $timeout === null
                ? null
                : (int) $timeout,
            (int) ($fraction * self::MICROSECONDS_PER_SECOND),
        );
    }

    /**
     * Read once when the socket connects and kept after it closes.
     *
     * @param resource $stream
     */
    private function peerAddressOf($stream): ?SocketAddress
    {
        if ($this->options->getTransport() === Transport::Unix) {
            return SocketAddress::unix();
        }

        $name = @stream_socket_get_name(
            $stream,
            true,
        );

        return is_string($name)
            ? SocketAddress::tryFromName($name)
            : null;
    }
}
