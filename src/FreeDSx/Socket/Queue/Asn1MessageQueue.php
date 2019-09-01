<?php
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
use FreeDSx\Socket\Exception\PartialMessageException;
use FreeDSx\Socket\PduInterface;
use FreeDSx\Socket\Socket;

/**
 * Represents an ASN.1 based message queue using the FreeDSx ASN.1 library.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Asn1MessageQueue extends MessageQueue
{
    /**
     * @var null|string
     */
    protected $pduClass;

    /**
     * @var EncoderInterface
     */
    protected $encoder;

    /**
     * @param Socket $socket
     * @param EncoderInterface $encoder
     * @param null|string $pduClass
     */
    public function __construct(Socket $socket, EncoderInterface $encoder, ?string $pduClass = null)
    {
        if ($pduClass !== null && !\is_subclass_of($pduClass, PduInterface::class)) {
            throw new \RuntimeException(sprintf(
                'The class "%s" must implement "%s", but it does not.',
                $pduClass,
                PduInterface::class
            ));
        }
        $this->encoder = $encoder;
        $this->pduClass = $pduClass;
        parent::__construct($socket);
    }

    /**
     * {@inheritdoc}
     */
    protected function decode($bytes): Message
    {
        try {
            $asn1 = $this->encoder->decode($bytes);
            $message = new Message($asn1, $this->encoder->getLastPosition());
        } catch (PartialPduException $exception) {
            throw new PartialMessageException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    protected function constructMessage(Message $message, ?int $id = null)
    {
        if ($this->pduClass === null) {
            throw new \RuntimeException('You must either define a PDU class or override getPdu().');
        }
        $callable = [$this->pduClass, 'fromAsn1'];
        if (!\is_callable($callable)) {
            throw new \RuntimeException(sprintf('The class %s is not callable.', $this->pduClass));
        }

        return \call_user_func($callable, $message->getMessage());
    }
}
