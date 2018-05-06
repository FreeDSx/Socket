<?php
/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Socket;

use FreeDSx\Asn1\Type\AbstractType;

/**
 * Represents an ASN.1 PDU that can be retrieved from a message queue.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
interface PduInterface
{
    /**
     * @return AbstractType
     */
    public function toAsn1() : AbstractType;

    /**
     * @param AbstractType $asn1
     * @return mixed
     */
    public static function fromAsn1(AbstractType $asn1);
}
