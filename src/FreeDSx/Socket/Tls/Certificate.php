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

namespace FreeDSx\Socket\Tls;

use OpenSSLCertificate;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;
use function openssl_x509_parse;

/**
 * An abstraction over a parsed peer X.509 certificate, so the OpenSSL extension type is not exposed to consumers.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
final readonly class Certificate
{
    /**
     * @param array<array-key, mixed> $parsed
     */
    private function __construct(private array $parsed)
    {
    }

    /**
     * Parse a verified peer certificate, or null when it cannot be parsed.
     */
    public static function fromX509(OpenSSLCertificate $certificate): ?self
    {
        $parsed = openssl_x509_parse($certificate);

        return $parsed === false
            ? null
            : new self($parsed);
    }

    /**
     * The subject relative distinguished name components, e.g. ['CN' => 'foo', 'O' => 'Acme'].
     *
     * @return array<string, string|list<string>>
     */
    public function getSubject(): array
    {
        $subject = $this->parsed['subject'] ?? [];
        if (!is_array($subject)) {
            return [];
        }

        $components = [];
        foreach ($subject as $name => $value) {
            if (!is_string($name)) {
                continue;
            }

            if (is_string($value)) {
                $components[$name] = $value;
            } elseif (is_array($value)) {
                $components[$name] = array_values(array_filter($value, 'is_string'));
            }
        }

        return $components;
    }

    /**
     * The raw OpenSSL-formatted subjectAltName extension (e.g. "DNS:foo.local, email:a@b.local"), or null if absent.
     */
    public function getSubjectAltName(): ?string
    {
        $extensions = $this->parsed['extensions'] ?? [];
        $san = is_array($extensions)
            ? ($extensions['subjectAltName'] ?? null)
            : null;

        return is_string($san)
            ? $san
            : null;
    }
}
