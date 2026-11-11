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

use function inet_ntop;
use function inet_pton;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * One end of a socket: an IP address and port, or a unix socket.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
final readonly class SocketAddress
{
    private const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    private const MAX_PORT = 65535;

    private function __construct(
        private ?string $ip = null,
        private ?int $port = null,
    ) {
    }

    /**
     * Parses a name in the form PHP reports one, such as 127.0.0.1:389 or [::1]:389, or returns null for anything else.
     */
    public static function tryFromName(string $name): ?self
    {
        $separator = strrpos(
            $name,
            ':',
        );
        if ($separator === false) {
            return null;
        }

        $host = substr(
            $name,
            0,
            $separator,
        );
        $port = substr(
            $name,
            $separator + 1,
        );
        $isBracketed = ($host[0] ?? '') === '['
            && ($host[-1] ?? '') === ']';
        if ($isBracketed) {
            $host = substr(
                $host,
                1,
                -1,
            );
        }

        // Only an IPv6 host is bracketed, which is what keeps it apart from the port.
        if ($isBracketed !== str_contains($host, ':')) {
            return null;
        }

        $packed = @inet_pton($host);
        if ($packed === false || !self::isPort($port)) {
            return null;
        }

        return new self(
            self::canonicalIp($packed),
            (int) $port,
        );
    }

    public static function unix(): self
    {
        return new self();
    }

    /**
     * The IP address without brackets, with an IPv4-mapped IPv6 address given as plain IPv4, or null for a unix socket.
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * The port, or null for a unix socket.
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    public function isUnix(): bool
    {
        return $this->ip === null;
    }

    /**
     * The address as host:port in its canonical form, with an IPv6 host bracketed, or "unix" for a unix socket.
     */
    public function toString(): string
    {
        if ($this->ip === null) {
            return 'unix';
        }

        return str_contains($this->ip, ':')
            ? "[{$this->ip}]:{$this->port}"
            : "{$this->ip}:{$this->port}";
    }

    /**
     * The same client reads as ::ffff:127.0.0.1 on a dual-stack listener and as 127.0.0.1 on an IPv4 one.
     */
    private static function canonicalIp(string $packed): string
    {
        if (strlen($packed) === 16 && str_starts_with($packed, self::IPV4_MAPPED_PREFIX)) {
            $packed = substr(
                $packed,
                strlen(self::IPV4_MAPPED_PREFIX),
            );
        }

        return (string) inet_ntop($packed);
    }

    private static function isPort(string $port): bool
    {
        $matched = preg_match(
            '/^(0|[1-9]\d{0,4})$/',
            $port,
        );

        return $matched === 1
            && (int) $port <= self::MAX_PORT;
    }
}
