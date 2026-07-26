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

/**
 * Server-side configuration consumed by SocketServer.
 */
final class SocketServerOptions implements SocketOptionsInterface
{
    use HasSocketOptions;

    private int $idleTimeout = 600;

    private int $writeTimeout = 0;

    private bool $reusePort = false;

    private bool $reuseAddress = false;

    private ?int $backlog = null;

    public function __construct()
    {
        $this->setSslValidateCert(false);
        // A server verifies the client certificate chains to a trusted CA, never that it matches a hostname.
        $this->setSslVerifyPeerName(false);
        $this->setSslCryptoMethod(
            STREAM_CRYPTO_METHOD_TLSv1_2_SERVER
            | STREAM_CRYPTO_METHOD_TLSv1_1_SERVER
            | STREAM_CRYPTO_METHOD_TLS_SERVER,
        );
    }

    public function setIdleTimeout(int $seconds): self
    {
        $this->idleTimeout = $seconds;

        return $this;
    }

    public function getIdleTimeout(): int
    {
        return $this->idleTimeout;
    }

    public function setWriteTimeout(int $seconds): self
    {
        $this->writeTimeout = $seconds;

        return $this;
    }

    public function getWriteTimeout(): int
    {
        return $this->writeTimeout;
    }

    /**
     * Allow several processes to bind their own socket to the same address, letting the kernel distribute incoming
     * connections between them (Linux 3.9+; semantics differ on other platforms).
     */
    public function setReusePort(bool $reusePort): self
    {
        $this->reusePort = $reusePort;

        return $this;
    }

    public function isReusePort(): bool
    {
        return $this->reusePort;
    }

    /**
     * Bind even while the address is in the kernel's TIME_WAIT state, so a restart does not have to wait it out.
     */
    public function setReuseAddress(bool $reuseAddress): self
    {
        $this->reuseAddress = $reuseAddress;

        return $this;
    }

    public function isReuseAddress(): bool
    {
        return $this->reuseAddress;
    }

    /**
     * Pending connections the kernel queues before refusing them, or null for the system default.
     */
    public function setBacklog(?int $backlog): self
    {
        $this->backlog = $backlog;

        return $this;
    }

    public function getBacklog(): ?int
    {
        return $this->backlog;
    }

    /**
     * The socket-level stream context options, omitting anything left at its default so the kernel decides.
     *
     * @return array<string, bool|int>
     */
    public function toStreamContextSocketOptions(): array
    {
        $opts = [];

        if ($this->reusePort) {
            $opts['so_reuseport'] = true;
        }
        if ($this->reuseAddress) {
            $opts['so_reuseaddr'] = true;
        }
        if ($this->backlog !== null) {
            $opts['backlog'] = $this->backlog;
        }

        return $opts;
    }
}
