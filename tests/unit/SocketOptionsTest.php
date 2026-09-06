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

use FreeDSx\Socket\SocketOptions;
use FreeDSx\Socket\SocketPoolOptions;
use FreeDSx\Socket\SocketServerOptions;
use FreeDSx\Socket\Timeout\BlockingSelectEnforcer;
use FreeDSx\Socket\Timeout\SwooleTimerEnforcer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SocketOptionsTest extends TestCase
{
    public function test_it_should_translate_set_ssl_fields_into_stream_context_keys(): void
    {
        $opts = (new SocketOptions())
            ->setSslCaCert('/tmp/ca.pem')
            ->setSslCert('/tmp/cert.pem')
            ->setSslCertKey('/tmp/key.pem')
            ->setSslCertPassphrase('s3cret')
            ->setSslPeerName('foo.example.com');

        $ctx = $opts->toStreamContextSslOptions();

        self::assertSame('/tmp/ca.pem', $ctx['cafile']);
        self::assertSame('/tmp/cert.pem', $ctx['local_cert']);
        self::assertSame('/tmp/key.pem', $ctx['local_pk']);
        self::assertSame('s3cret', $ctx['passphrase']);
        self::assertSame('foo.example.com', $ctx['peer_name']);
    }

    public function test_it_should_omit_unset_ssl_fields_from_stream_context(): void
    {
        $ctx = (new SocketOptions())->toStreamContextSslOptions();

        self::assertArrayNotHasKey('cafile', $ctx);
        self::assertArrayNotHasKey('local_cert', $ctx);
        self::assertArrayNotHasKey('local_pk', $ctx);
        self::assertArrayNotHasKey('passphrase', $ctx);
        self::assertArrayNotHasKey('peer_name', $ctx);
    }

    public function test_it_should_relax_validation_when_validate_cert_is_off(): void
    {
        $ctx = (new SocketOptions())
            ->setSslValidateCert(false)
            ->toStreamContextSslOptions();

        self::assertFalse($ctx['verify_peer']);
        self::assertFalse($ctx['verify_peer_name']);
        self::assertTrue($ctx['allow_self_signed']);
    }

    public function test_it_should_carry_the_validate_cert_flag_when_enabled(): void
    {
        $ctx = (new SocketOptions())->toStreamContextSslOptions();

        self::assertTrue($ctx['verify_peer']);
        self::assertTrue($ctx['verify_peer_name']);
        self::assertFalse($ctx['allow_self_signed']);
    }

    public function test_it_should_capture_the_peer_cert_by_default(): void
    {
        $ctx = (new SocketOptions())->toStreamContextSslOptions();

        self::assertTrue($ctx['capture_peer_cert']);
    }

    public function test_it_should_omit_the_peer_cert_capture_when_suppressed(): void
    {
        $ctx = (new SocketOptions())
            ->setSslCapturePeerCert(false)
            ->toStreamContextSslOptions();

        self::assertArrayNotHasKey('capture_peer_cert', $ctx);
    }

    public function test_it_can_verify_the_peer_without_verifying_its_name(): void
    {
        $ctx = (new SocketOptions())
            ->setSslVerifyPeerName(false)
            ->toStreamContextSslOptions();

        self::assertTrue($ctx['verify_peer']);
        self::assertFalse($ctx['verify_peer_name']);
    }

    public function test_set_buffer_size_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SocketOptions())->setBufferSize(0);
    }

    public function test_server_options_should_disable_certificate_validation_by_default(): void
    {
        self::assertFalse((new SocketServerOptions())->isSslValidateCert());
    }

    public function test_server_options_should_not_verify_the_peer_name_by_default(): void
    {
        self::assertFalse((new SocketServerOptions())->toStreamContextSslOptions()['verify_peer_name']);
    }

    public function test_server_options_should_use_server_side_crypto_method_by_default(): void
    {
        self::assertSame(
            STREAM_CRYPTO_METHOD_TLSv1_2_SERVER
            | STREAM_CRYPTO_METHOD_TLSv1_1_SERVER
            | STREAM_CRYPTO_METHOD_TLS_SERVER,
            (new SocketServerOptions())->getSslCryptoMethod(),
        );
    }

    public function test_server_options_should_default_idle_timeout_to_600(): void
    {
        self::assertSame(600, (new SocketServerOptions())->getIdleTimeout());
    }

    public function test_it_defaults_to_the_blocking_select_write_timeout_enforcer(): void
    {
        self::assertInstanceOf(
            BlockingSelectEnforcer::class,
            (new SocketOptions())->getWriteTimeoutEnforcer(),
        );
    }

    public function test_it_can_set_the_write_timeout_enforcer(): void
    {
        $enforcer = new SwooleTimerEnforcer();
        $options = (new SocketOptions())->setWriteTimeoutEnforcer($enforcer);

        self::assertSame(
            $enforcer,
            $options->getWriteTimeoutEnforcer(),
        );
    }

    public function test_pool_options_default_to_one_second_connect_timeout(): void
    {
        self::assertSame(1, (new SocketPoolOptions())->getSocket()->getTimeoutConnect());
    }

    public function test_pool_options_respect_provided_socket_options(): void
    {
        $custom = (new SocketOptions())->setTimeoutConnect(7);

        self::assertSame(7, (new SocketPoolOptions($custom))->getSocket()->getTimeoutConnect());
    }

    public function test_pool_options_default_to_an_empty_servers_list(): void
    {
        self::assertSame(
            [],
            (new SocketPoolOptions())->getServers()
        );
    }

    public function test_pool_options_round_trip_servers(): void
    {
        $opts = (new SocketPoolOptions())->setServers(['a', 'b', 'c']);

        self::assertSame(
            ['a', 'b', 'c'],
            $opts->getServers(),
        );
    }
}
