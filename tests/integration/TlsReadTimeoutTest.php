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

namespace Tests\Integration\FreeDSx\Socket;

use FreeDSx\Socket\Exception\ReadTimeoutException;
use FreeDSx\Socket\SocketServer;
use FreeDSx\Socket\SocketServerOptions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Unit\FreeDSx\Socket\GeneratesCertificates;
use Tests\Unit\FreeDSx\Socket\RunsInCoroutine;
use Throwable;

final class TlsReadTimeoutTest extends TestCase
{
    use GeneratesCertificates;
    use RunsInCoroutine;

    private int $port = 0;

    private string $certFile = '';

    private string $keyFile = '';

    protected function setUp(): void
    {
        if (!extension_loaded('openssl') || !extension_loaded('sockets')) {
            self::markTestSkipped('The openssl and sockets extensions are required.');
        }
        $this->port = self::freePort();
        [$certificate, $key] = $this->generateCertificate();
        $this->certFile = $this->tempFile();
        $this->keyFile = $this->tempFile();
        openssl_x509_export_to_file(
            $certificate,
            $this->certFile,
        );
        openssl_pkey_export_to_file(
            $key,
            $this->keyFile,
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->certFile, $this->keyFile] as $file) {
            if ($file !== '') {
                @unlink($file);
            }
        }
    }

    public function test_a_partial_tls_record_does_not_hold_a_read_past_its_own_timeout(): void
    {
        [$caught, $elapsed] = $this->readWhileTheClientSendsAPartialRecord();

        self::assertInstanceOf(
            ReadTimeoutException::class,
            $caught,
        );
        self::assertLessThan(
            3.0,
            $elapsed,
        );
    }

    public function test_a_partial_tls_record_does_not_hold_a_read_in_a_coroutine_past_its_own_timeout(): void
    {
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('The swoole extension is required.');
        }
        $result = [null, 0.0];

        $this->runInCoroutine(function () use (&$result): void {
            $result = $this->readWhileTheClientSendsAPartialRecord();
        });
        [$caught, $elapsed] = $result;

        self::assertInstanceOf(
            ReadTimeoutException::class,
            $caught,
        );
        self::assertLessThan(
            3.0,
            $elapsed,
        );
    }

    /**
     * @return array{0: ?Throwable, 1: float}
     */
    private function readWhileTheClientSendsAPartialRecord(): array
    {
        $server = null;
        $client = null;
        $start = microtime(true);

        try {
            // A short idle timeout keeps a read the stream timeout fails to bound from hanging the test.
            $server = SocketServer::bindTcp(
                '127.0.0.1',
                $this->port,
                (new SocketServerOptions())
                    ->setUseSsl(true)
                    ->setSslCert($this->certFile)
                    ->setSslCertKey($this->keyFile)
                    ->setIdleTimeout(5),
            );
            $client = PartialTlsRecordClient::start(
                $this->port,
                0.3,
            );
            $connection = $server->accept(5.0) ?? throw new RuntimeException('The client did not connect.');

            $start = microtime(true);
            $connection->read(
                true,
                1.0,
            );

            return [null, microtime(true) - $start];
        } catch (Throwable $e) {
            return [$e, microtime(true) - $start];
        } finally {
            $client?->stop();
            $server?->close();
        }
    }

    private function tempFile(): string
    {
        $file = tempnam(
            sys_get_temp_dir(),
            'freedsx_socket_',
        );
        if ($file === false) {
            self::fail('Unable to create a temporary file.');
        }

        return $file;
    }

    private static function freePort(): int
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        if ($probe === false) {
            self::fail('Unable to find a free port.');
        }
        $name = stream_socket_get_name(
            $probe,
            false,
        );
        fclose($probe);
        if ($name === false) {
            self::fail('Unable to read the free port.');
        }

        return (int) substr(
            $name,
            (int) strrpos(
                $name,
                ':',
            ) + 1,
        );
    }
}
