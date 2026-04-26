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
 * Read-only contract Socket consumes when given configuration.
 */
interface SocketOptionsInterface
{
    public function getTransport(): Transport;

    public function getPort(): int;

    public function isUseSsl(): bool;

    public function getSslCryptoMethod(): int;

    public function getSslCiphers(): string;

    public function isSslValidateCert(): bool;

    public function getSslAllowSelfSigned(): ?bool;

    public function getSslCaCert(): ?string;

    public function getSslCert(): ?string;

    public function getSslCertKey(): ?string;

    public function getSslCertPassphrase(): ?string;

    public function getSslPeerName(): ?string;

    public function getTimeoutConnect(): int;

    public function getTimeoutRead(): int;

    /**
     * @return positive-int
     */
    public function getBufferSize(): int;

    /**
     * @return array<string, mixed>
     */
    public function toStreamContextSslOptions(): array;
}
