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

use InvalidArgumentException;

/**
 * Shared socket option functionality.
 */
trait HasSocketOptions
{
    private Transport $transport = Transport::Tcp;

    private int $port = 389;

    private bool $useSsl = false;

    private int $sslCryptoMethod =
        STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
        | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
        | STREAM_CRYPTO_METHOD_TLS_CLIENT;

    private string $sslCiphers = 'DEFAULT';

    private bool $sslValidateCert = true;

    private ?bool $sslAllowSelfSigned = null;

    private ?string $sslCaCert = null;

    private ?string $sslCert = null;

    private ?string $sslCertKey = null;

    private ?string $sslCertPassphrase = null;

    private ?string $sslPeerName = null;

    private int $timeoutConnect = 3;

    private int $timeoutRead = 15;

    private int $bufferSize = 8192;

    public function setTransport(Transport $transport): self
    {
        $this->transport = $transport;

        return $this;
    }

    public function getTransport(): Transport
    {
        return $this->transport;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setUseSsl(bool $useSsl): self
    {
        $this->useSsl = $useSsl;

        return $this;
    }

    public function isUseSsl(): bool
    {
        return $this->useSsl;
    }

    public function setSslCryptoMethod(int $sslCryptoMethod): self
    {
        $this->sslCryptoMethod = $sslCryptoMethod;

        return $this;
    }

    public function getSslCryptoMethod(): int
    {
        return $this->sslCryptoMethod;
    }

    public function setSslCiphers(string $sslCiphers): self
    {
        $this->sslCiphers = $sslCiphers;

        return $this;
    }

    public function getSslCiphers(): string
    {
        return $this->sslCiphers;
    }

    public function setSslValidateCert(bool $sslValidateCert): self
    {
        $this->sslValidateCert = $sslValidateCert;

        return $this;
    }

    public function isSslValidateCert(): bool
    {
        return $this->sslValidateCert;
    }

    public function setSslAllowSelfSigned(?bool $sslAllowSelfSigned): self
    {
        $this->sslAllowSelfSigned = $sslAllowSelfSigned;

        return $this;
    }

    public function getSslAllowSelfSigned(): ?bool
    {
        return $this->sslAllowSelfSigned;
    }

    public function setSslCaCert(?string $sslCaCert): self
    {
        $this->sslCaCert = $sslCaCert;

        return $this;
    }

    public function getSslCaCert(): ?string
    {
        return $this->sslCaCert;
    }

    public function setSslCert(?string $sslCert): self
    {
        $this->sslCert = $sslCert;

        return $this;
    }

    public function getSslCert(): ?string
    {
        return $this->sslCert;
    }

    public function setSslCertKey(?string $sslCertKey): self
    {
        $this->sslCertKey = $sslCertKey;

        return $this;
    }

    public function getSslCertKey(): ?string
    {
        return $this->sslCertKey;
    }

    public function setSslCertPassphrase(?string $sslCertPassphrase): self
    {
        $this->sslCertPassphrase = $sslCertPassphrase;

        return $this;
    }

    public function getSslCertPassphrase(): ?string
    {
        return $this->sslCertPassphrase;
    }

    public function setSslPeerName(?string $sslPeerName): self
    {
        $this->sslPeerName = $sslPeerName;

        return $this;
    }

    public function getSslPeerName(): ?string
    {
        return $this->sslPeerName;
    }

    public function setTimeoutConnect(int $seconds): self
    {
        $this->timeoutConnect = $seconds;

        return $this;
    }

    public function getTimeoutConnect(): int
    {
        return $this->timeoutConnect;
    }

    public function setTimeoutRead(int $seconds): self
    {
        $this->timeoutRead = $seconds;

        return $this;
    }

    public function getTimeoutRead(): int
    {
        return $this->timeoutRead;
    }

    public function setBufferSize(int $bufferSize): self
    {
        if ($bufferSize < 1) {
            throw new InvalidArgumentException('Buffer size must be a positive integer.');
        }
        $this->bufferSize = $bufferSize;

        return $this;
    }

    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    /**
     * @return array<string, mixed>
     */
    public function toStreamContextSslOptions(): array
    {
        $opts = [
            'allow_self_signed' => $this->sslAllowSelfSigned ?? false,
            'verify_peer' => $this->sslValidateCert,
            'verify_peer_name' => $this->sslValidateCert,
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'crypto_method' => $this->sslCryptoMethod,
            'ciphers' => $this->sslCiphers,
        ];

        if ($this->sslCaCert !== null) {
            $opts['cafile'] = $this->sslCaCert;
        }
        if ($this->sslCert !== null) {
            $opts['local_cert'] = $this->sslCert;
        }
        if ($this->sslCertKey !== null) {
            $opts['local_pk'] = $this->sslCertKey;
        }
        if ($this->sslCertPassphrase !== null) {
            $opts['passphrase'] = $this->sslCertPassphrase;
        }
        if ($this->sslPeerName !== null) {
            $opts['peer_name'] = $this->sslPeerName;
        }
        if (!$this->sslValidateCert) {
            $opts['allow_self_signed'] = true;
        }

        return $opts;
    }
}
