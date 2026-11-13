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

use RuntimeException;

/**
 * A TLS client in its own process that sends only the start of a record.
 */
final class PartialTlsRecordClient
{
    private const AUTOLOADER = __DIR__ . '/../../vendor/autoload.php';

    /**
     * A record header that announces more bytes than ever follow it.
     */
    private const PARTIAL_RECORD = "\x17\x03\x03\x04\x00partial";

    /**
     * @param resource $process
     * @param resource $input
     */
    private function __construct(
        private $process,
        private $input,
    ) {
    }

    /**
     * Connects to the port and sends the partial record once the delay in seconds has passed.
     */
    public static function start(
        int $port,
        float $delay,
    ): self {
        $pipes = [];
        $process = proc_open(
            [
                PHP_BINARY,
                '-r',
                sprintf(
                    'require %s; %s::run(%d, %F);',
                    var_export(
                        self::AUTOLOADER,
                        true,
                    ),
                    self::class,
                    $port,
                    $delay,
                ),
            ],
            [0 => ['pipe', 'r']],
            $pipes,
        );
        if (!is_resource($process) || !isset($pipes[0])) {
            throw new RuntimeException('Unable to start the client.');
        }

        return new self(
            $process,
            $pipes[0],
        );
    }

    /**
     * Closing the input ends the client, which holds its connection open until then.
     */
    public function stop(): void
    {
        fclose($this->input);
        proc_close($this->process);
    }

    /**
     * Runs in the client's own process.
     */
    public static function run(
        int $port,
        float $delay,
    ): void {
        $client = stream_socket_client(
            'tcp://127.0.0.1:' . $port,
            $errorNumber,
            $errorMessage,
            5.0,
            STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]),
        );
        if ($client === false) {
            throw new RuntimeException('Unable to connect: ' . $errorMessage);
        }

        // Taken before TLS starts, since a stream with TLS on cannot be imported.
        $raw = socket_import_stream($client);
        stream_set_timeout(
            $client,
            5,
        );
        if ($raw === false || stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            throw new RuntimeException('Unable to start TLS.');
        }

        usleep((int) ($delay * 1_000_000));
        socket_write(
            $raw,
            self::PARTIAL_RECORD,
        );
        fgets(STDIN);
    }
}
