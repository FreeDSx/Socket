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

namespace Tests\Unit\FreeDSx\Socket;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use OpenSSLCertificateSigningRequest;

trait GeneratesCertificates
{
    /**
     * @return array{0: OpenSSLCertificate, 1: OpenSSLAsymmetricKey}
     */
    private function generateCertificate(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        if (!$key instanceof OpenSSLAsymmetricKey) {
            self::fail('Failed to generate a private key.');
        }

        // The key is taken by reference, and both are narrowed again after the call.
        $csr = openssl_csr_new(
            ['commonName' => 'peer.test'],
            $key,
        );
        if (!$csr instanceof OpenSSLCertificateSigningRequest || !$key instanceof OpenSSLAsymmetricKey) {
            self::fail('Failed to generate a certificate signing request.');
        }

        $certificate = openssl_csr_sign(
            $csr,
            null,
            $key,
            1,
        );
        if (!$certificate instanceof OpenSSLCertificate) {
            self::fail('Failed to sign the certificate.');
        }

        return [$certificate, $key];
    }
}
