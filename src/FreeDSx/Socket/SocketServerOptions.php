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

    public function __construct()
    {
        $this->setSslValidateCert(false);
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
}
