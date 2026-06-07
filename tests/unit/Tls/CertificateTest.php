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

namespace Tests\Unit\FreeDSx\Socket\Tls;

use FreeDSx\Socket\Tls\Certificate;
use OpenSSLCertificate;
use PHPUnit\Framework\TestCase;

final class CertificateTest extends TestCase
{
    /**
     * Throwaway self-signed cert generated for this test.
     *
     *  - subject "/CN=test.local/O=FreeDSx/C=US",
     *  - SAN "DNS:test.local, email:test@freedsx.local"
     *
     * openssl req -x509 -newkey rsa:2048 -nodes -days 36500
     */
    private const FIXTURE_PEM = <<<'PEM'
        -----BEGIN CERTIFICATE-----
        MIIDdjCCAl6gAwIBAgIUdHEhFVr/VYCaS+SDgabMMlhPVUQwDQYJKoZIhvcNAQEL
        BQAwNDETMBEGA1UEAwwKdGVzdC5sb2NhbDEQMA4GA1UECgwHRnJlZURTeDELMAkG
        A1UEBhMCVVMwIBcNMjYwNjA3MjAyNzQzWhgPMjEyNjA1MTQyMDI3NDNaMDQxEzAR
        BgNVBAMMCnRlc3QubG9jYWwxEDAOBgNVBAoMB0ZyZWVEU3gxCzAJBgNVBAYTAlVT
        MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAmU5G97cRIK/1RXPQoQSc
        Ce8tyXSJa3pFln/P/MR++isQTN3GENvFRLqpVZqjDZY81HbpH6/zmXNLiFFkp3HC
        Nk9VOCO5q22/ZN3m7jBPOWhUTmqgppmtqEeRzBq4tMOqtqvyAtMISKPYzrdCj6bs
        ZG/D5zWoWDDRSBQ4mjaMMFe68pf85WqUDi9qfLSQoHx5iLbbyGc2Z5/uzUysOlJN
        qR7BqNbpdpnrDt4BbnBj5rUjvLZZXRIqfBtQgLV7si2g2zyOiEdQFblkwiJZi83v
        246akDrwxAjYaVnpJ+eCmgTBUflzSbUjR80DBLYPpCA3D+nqnfDbEdP6aotZb4Mn
        IQIDAQABo34wfDAdBgNVHQ4EFgQUhD5XlhQ+ZvreIt5jarm8yzlRSMcwHwYDVR0j
        BBgwFoAUhD5XlhQ+ZvreIt5jarm8yzlRSMcwDwYDVR0TAQH/BAUwAwEB/zApBgNV
        HREEIjAgggp0ZXN0LmxvY2FsgRJ0ZXN0QGZyZWVkc3gubG9jYWwwDQYJKoZIhvcN
        AQELBQADggEBAHdo7kq5ddOPwW3VGCgXLdPe0zZ22G6NzJdnv53+QN/h1Z0ugHRY
        t+iTy2HX4ssRSph/lNqp1BfkU0T7+MPy0A/VJlVVcISUc/1lfr5NTYs6MUNvVIzQ
        vy/Ehi/0C9L5pg06ECeLGMYKnwe77JzgW+LrhPz2Wp+I4fDMHp++AsoN6Yn4NLot
        kutr6oGylPInZL8CQMf7y89C0QtJxa6U8mjC7Mx4YwcAjIiKNJeKJHLQG6lopJKz
        HWERWoTCuR1106b2fyedXz7ZwRtZFmalQiZeg8TIt3WKzElWSLHl72SBtLFHafSB
        WiIEba05kUnSLRk97yfqFO53Mv2KsUz6HUc=
        -----END CERTIFICATE-----
        PEM;

    public function testItExposesTheSubject(): void
    {
        $certificate = Certificate::fromX509($this->fixture());

        self::assertSame(
            [
                'CN' => 'test.local',
                'O' => 'FreeDSx',
                'C' => 'US',
            ],
            $certificate?->getSubject(),
        );
    }

    public function testItExposesTheSubjectAltName(): void
    {
        $certificate = Certificate::fromX509($this->fixture());

        self::assertSame(
            'DNS:test.local, email:test@freedsx.local',
            $certificate?->getSubjectAltName(),
        );
    }

    private function fixture(): OpenSSLCertificate
    {
        $certificate = openssl_x509_read(self::FIXTURE_PEM);
        self::assertInstanceOf(
            OpenSSLCertificate::class,
            $certificate,
        );

        return $certificate;
    }
}
