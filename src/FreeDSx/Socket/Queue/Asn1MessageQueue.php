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

namespace FreeDSx\Socket\Queue;

use FreeDSx\Asn1\Encoder\EncoderInterface;
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Socket\Exception\PartialMessageException;
use FreeDSx\Socket\PduInterface;
use FreeDSx\Socket\Socket;
use RuntimeException;
use UnexpectedValueException;
use function get_debug_type;

/**
 * Represents an ASN.1 based message queue using the FreeDSx ASN.1 library.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Asn1MessageQueue extends MessageQueue
{
    /**
     * @param class-string<PduInterface>|null $pduClass
     */
    public function __construct(
        Socket $socket,
        protected EncoderInterface $encoder,
        protected ?string $pduClass = null,
    ) {
        if ($pduClass !== null && !\is_subclass_of($pduClass, PduInterface::class)) {
            throw new RuntimeException(sprintf(
                'The class "%s" must implement "%s", but it does not.',
                $pduClass,
                PduInterface::class,
            ));
        }
        parent::__construct($socket);
    }

    protected function decode(string $bytes): Message
    {
        try {
            $asn1 = $this->encoder->decode($bytes);
            $message = new Message($asn1, $this->encoder->getLastPosition());
        } catch (PartialPduException $exception) {
            throw new PartialMessageException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $message;
    }

    protected function constructMessage(
        Message $message,
        ?int $id = null,
    ): mixed {
        if ($this->pduClass === null) {
            throw new RuntimeException('You must either define a PDU class or override getPdu().');
        }
        $asn1 = $message->getMessage();
        if (!$asn1 instanceof AbstractType) {
            throw new UnexpectedValueException(sprintf(
                'Expected an instance of %s, got %s.',
                AbstractType::class,
                get_debug_type($asn1),
            ));
        }

        return ($this->pduClass)::fromAsn1($asn1);
    }
}
