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

use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Socket\PduInterface;

/**
 * Throw away PDU class for message queue tests.
 */
class Pdu implements PduInterface
{
    public function __construct(private readonly AbstractType $type)
    {
    }

    public function toAsn1(): AbstractType
    {
        return $this->type;
    }

    public static function fromAsn1(AbstractType $asn1): self
    {
        return new self($asn1);
    }
}
