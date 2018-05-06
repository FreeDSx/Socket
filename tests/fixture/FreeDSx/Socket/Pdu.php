<?php
/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace fixture\FreeDSx\Socket;

use FreeDSx\Asn1\Type\AbstractType;

/**
 * Throw away PDU class for message queue specs.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Pdu implements \FreeDSx\Socket\PduInterface
{
    /**
     * @var AbstractType
     */
    protected $type;

    /**
     * @param AbstractType $type
     */
    public function __construct(AbstractType $type)
    {
        $this->type = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function toAsn1(): AbstractType
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromAsn1(AbstractType $asn1)
    {
        return new self($asn1);
    }
}
