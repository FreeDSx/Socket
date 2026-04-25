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
use function array_merge;
use function fread;
use function fwrite;
use function in_array;
use function stream_set_blocking;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_enable_crypto;

/**
 * Represents a generic socket.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Socket
{
    /**
     * Supported transport types.
     */
    public const TRANSPORTS = [
        'tcp',
        'udp',
        'unix',
    ];

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
     * @var array<string, string>
     */
    protected array $sslOptsMap = [
        'ssl_allow_self_signed' => 'allow_self_signed',
        'ssl_ca_cert' => 'cafile',
        'ssl_crypto_method' => 'crypto_method',
        'ssl_ciphers' => 'ciphers',
        'ssl_peer_name' => 'peer_name',
        'ssl_cert' => 'local_cert',
        'ssl_cert_key' => 'local_pk',
        'ssl_cert_passphrase' => 'passphrase',
    ];

    /**
     * @var array<string, bool>
     */
    protected array $sslOpts = [
        'allow_self_signed' => false,
        'verify_peer' => true,
        'verify_peer_name' => true,
        'capture_peer_cert' => true,
        'capture_peer_cert_chain' => true,
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $options = [
        'transport' => 'tcp',
        'port' => 389,
        'use_ssl' => false,
        'ssl_crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT,
        'ssl_ciphers' => 'DEFAULT',
        'ssl_validate_cert' => true,
        'ssl_allow_self_signed' => null,
        'ssl_ca_cert' => null,
        'ssl_peer_name' => null,
        'timeout_connect' => 3,
        'timeout_read' => 15,
        'buffer_size' => 8192,
    ];

    /**
     * @param resource|null $resource
     * @param array<string, mixed> $options
     */
    public function __construct(
        $resource = null,
        array $options = [],
    ) {
        $this->socket = $resource;
        $this->options = array_merge($this->options, $options);
        if (!in_array($this->options['transport'], self::TRANSPORTS, true)) {
            throw new \RuntimeException(sprintf(
                'The transport "%s" is not valid. It must be one of: %s',
                $this->options['transport'],
                implode(',', self::TRANSPORTS),
            ));
        }
        if ($this->socket !== null) {
            $this->setStreamOpts();
        }
    }

    public function read(bool $block = true): string|false
    {
        stream_set_blocking($this->socket, $block);
        $data = fread(
            $this->socket,
            $this->options['buffer_size'],
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
        if ($this->options['transport'] !== 'unix') {
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
            \stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
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
        $result = stream_socket_enable_crypto($this->socket, $encrypt, $this->options['ssl_crypto_method']);
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
        $transport = $this->options['transport'];
        if ($transport === 'tcp' && (bool) $this->options['use_ssl'] === true) {
            $transport = 'ssl';
        }

        $uri = $transport . '://' . $host;

        if ($transport !== 'unix') {
            $uri .= ':' . $this->options['port'];
        }

        $errorNumber = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            $uri,
            $errorNumber,
            $errorMessage,
            $this->options['timeout_connect'],
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
        $this->isEncrypted = (bool) $this->options['use_ssl'];

        return $this;
    }

    /**
     * Get the options set for the socket.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Create a socket by connecting to a specific host.
     *
     * @param array<string, mixed> $options
     * @throws ConnectionException
     */
    public static function create(
        string $host,
        array $options = [],
    ): Socket {
        return (new self(null, $options))->connect($host);
    }

    /**
     * Create a UNIX based socket.
     *
     * @param string $file The full path to the unix socket.
     * @param array<string, mixed> $options Any additional options.
     * @throws ConnectionException
     */
    public static function unix(
        string $file,
        array $options = [],
    ): Socket {
        return self::create(
            $file,
            array_merge(
                $options,
                ['transport' => 'unix'],
            ),
        );
    }

    /**
     * Create a TCP based socket.
     *
     * @param array<string, mixed> $options
     * @throws ConnectionException
     */
    public static function tcp(
        string $host,
        array $options = [],
    ): Socket {
        return self::create(
            $host,
            array_merge(
                $options,
                ['transport' => 'tcp'],
            ),
        );
    }

    /**
     * Create a UDP based socket.
     *
     * @param array<string, mixed> $options
     * @throws ConnectionException
     */
    public static function udp(
        string $host,
        array $options = [],
    ): Socket {
        return self::create(
            $host,
            array_merge(
                $options,
                [
                    'transport' => 'udp',
                    'buffer_size' => 65507,
                ],
            ),
        );
    }

    /**
     * @return resource
     */
    protected function createSocketContext()
    {
        $sslOpts = $this->sslOpts;
        foreach ($this->sslOptsMap as $optName => $sslOptsName) {
            if (isset($this->options[$optName])) {
                $sslOpts[$sslOptsName] = $this->options[$optName];
            }
        }
        if ($this->options['ssl_validate_cert'] === false) {
            $sslOpts = array_merge($sslOpts, [
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]);
        }
        $this->context = \stream_context_create([
            'ssl' => $sslOpts,
        ]);

        return $this->context;
    }

    /**
     * Sets options on the stream that must be done after it is a resource.
     */
    protected function setStreamOpts(): void
    {
        stream_set_timeout($this->socket, $this->options['timeout_read']);
    }
}
