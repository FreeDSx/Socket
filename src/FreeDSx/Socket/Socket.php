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
    ];

    /**
     * @var bool
     */
    protected $isEncrypted = false;

    /**
     * @var resource|null
     */
    protected $socket;

    /**
     * @var resource|null
     */
    protected $context;

    /**
     * @var string
     */
    protected $errorMessage;

    /**
     * @var int
     */
    protected $errorNumber;

    /**
     * @var array
     */
    protected $sslOptsMap = [
        'ssl_allow_self_signed' => 'allow_self_signed',
        'ssl_ca_cert' => 'cafile',
        'ssl_crypto_type' => 'crypto_type',
        'ssl_peer_name' => 'peer_name',
        'ssl_cert' => 'local_cert',
        'ssl_cert_key' => 'local_pk',
        'ssl_cert_passphrase' => 'passphrase',
    ];

    /**
     * @var array
     */
    protected $sslOpts = [
        'allow_self_signed' => false,
        'verify_peer' => true,
        'verify_peer_name' => true,
        'capture_peer_cert' => true,
        'capture_peer_cert_chain' => true,
    ];

    /**
     * @var array
     */
    protected $options = [
        'transport' => 'tcp',
        'port' => 389,
        'use_ssl' => false,
        'ssl_crypto_type' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT,
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
     * @param array $options
     */
    public function __construct($resource = null, array $options = [])
    {
        $this->socket = $resource;
        $this->options = \array_merge($this->options, $options);
        if (!\in_array($this->options['transport'], self::TRANSPORTS, true)) {
            throw new \RuntimeException(sprintf(
                'The transport "%s" is not valid. It must be one of: %s',
                $this->options['transport'],
                implode(',', self::TRANSPORTS)
            ));
        }
        if ($this->socket !== null) {
            $this->setStreamOpts();
        }
    }

    /**
     * @param bool $block
     * @return string|false
     */
    public function read(bool $block = true)
    {
        $data = false;

        \stream_set_blocking($this->socket, $block);
        while (\strlen((string) ($buffer = \fread($this->socket, $this->options['buffer_size']))) > 0) {
            $data .= $buffer;
            if ($block) {
                $block = false;
                \stream_set_blocking($this->socket, false);
            }
        }
        \stream_set_blocking($this->socket, true);

        return $data;
    }

    /**
     * @param string $data
     * @return $this
     */
    public function write(string $data)
    {
        @\fwrite($this->socket, $data);

        return $this;
    }

    /**
     * @param bool $block
     * @return $this
     */
    public function block(bool $block)
    {
        \stream_set_blocking($this->socket, $block);

        return $this;
    }

    /**
     * @return bool
     */
    public function isConnected() : bool
    {
        return $this->socket !== null && !@\feof($this->socket);
    }

    /**
     * @return bool
     */
    public function isEncrypted() : bool
    {
        return $this->isEncrypted;
    }

    /**
     * @return $this
     */
    public function close()
    {
        if ($this->socket !== null) {
            \stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        }
        $this->isEncrypted = false;
        $this->context = null;

        return $this;
    }

    /**
     * Enable/Disable encryption on the TCP connection stream.
     *
     * @param bool $encrypt
     * @return $this
     * @throws ConnectionException
     */
    public function encrypt(bool $encrypt)
    {
        \stream_set_blocking($this->socket, true);
        $result = \stream_socket_enable_crypto($this->socket, $encrypt, $this->options['ssl_crypto_type']);
        \stream_set_blocking($this->socket, false);

        if ((bool) $result == false) {
            throw new ConnectionException(sprintf(
                'Unable to %s encryption on TCP connection. %s',
                $encrypt ? 'enable' : 'disable',
                $this->errorMessage
            ));
        }
        $this->isEncrypted = $encrypt;

        return $this;
    }

    /**
     * @param string $host
     * @return $this
     * @throws ConnectionException
     */
    public function connect(string $host)
    {
        $transport = $this->options['transport'];
        if ($transport === 'tcp' && (bool) $this->options['use_ssl'] === true) {
            $transport = 'ssl';
        }
        $uri = $transport.'://'.$host.':'.$this->options['port'];

        $socket = @\stream_socket_client(
            $uri,
            $this->errorNumber,
            $this->errorMessage,
            $this->options['timeout_connect'],
            STREAM_CLIENT_CONNECT,
            $this->createSocketContext()
        );
        if ($socket === false) {
            throw new ConnectionException(sprintf(
                'Unable to connect to %s: %s',
                $host,
                $this->errorMessage
            ));
        }
        $this->socket = $socket;
        $this->setStreamOpts();
        $this->isEncrypted = $this->options['use_ssl'];

        return $this;
    }

    /**
     * Get the options set for the socket.
     *
     * @return array
     */
    public function getOptions() : array
    {
        return $this->options;
    }

    /**
     * Create a socket by connecting to a specific host.
     *
     * @param string $host
     * @param array $options
     * @return Socket
     * @throws ConnectionException
     */
    public static function create(string $host, array $options = []) : Socket
    {
        return (new self(null, $options))->connect($host);
    }

    /**
     * Create a TCP based socket.
     *
     * @param string $host
     * @param array $options
     * @return Socket
     * @throws ConnectionException
     */
    public static function tcp(string $host, array $options = []) : Socket
    {
        return self::create($host, \array_merge($options, ['transport' => 'tcp']));
    }

    /**
     * Create a UDP based socket.
     *
     * @param string $host
     * @param array $options
     * @return Socket
     * @throws ConnectionException
     */
    public static function udp(string $host, array $options = []) : Socket
    {
        return self::create($host, \array_merge($options, [
            'transport' => 'udp',
            'buffer_size' => 65507,
        ]));
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
            $sslOpts = \array_merge($sslOpts, [
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
    protected function setStreamOpts() : void
    {
        \stream_set_timeout($this->socket, $this->options['timeout_read']);
    }
}
