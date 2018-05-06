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

use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Encoder\EncoderInterface;
use FreeDSx\Socket\Exception\ConnectionException;

/**
 * Used to retrieve PDUs from the socket.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class MessageQueue
{
    /**
     * @var Socket
     */
    protected $socket;

    /**
     * @var EncoderInterface
     */
    protected $encoder;

    /**
     * @var false|string
     */
    protected $buffer = false;

    /**
     * @var string|null
     */
    protected $pduClass;

    /**
     * @param Socket $socket
     * @param EncoderInterface $encoder
     * @param null|string $pduClass
     */
    public function __construct(Socket $socket, EncoderInterface $encoder, ?string $pduClass = null)
    {
        $this->socket = $socket;
        $this->encoder = $encoder;

        if ($pduClass !== null && !is_subclass_of($pduClass, PduInterface::class)) {
            throw new \RuntimeException(sprintf(
                'The class "%s" must implement "%s", but it does not.',
                $pduClass,
                PduInterface::class
            ));
        }

        $this->pduClass = $pduClass;
    }

    /**
     * @param int|null $id
     * @return \Generator
     * @throws ConnectionException
     * @throws \FreeDSx\Asn1\Exception\EncoderException
     */
    public function getMessages(?int $id = null)
    {
        $this->buffer = ($this->buffer !== false) ? $this->buffer : $this->socket->read();

        # Likely an unsolicited notification for a remote disconnect. For some reason, this forces it to be caught in
        # that case (but down below). This exception directly below is never thrown in that case. But the remote
        # disconnect message is never caught if this block is not here. Why???
        #
        # @todo PHP bug? Or logic issue with my generator use?
        if ($this->buffer === false) {
            throw new ConnectionException('The connection to the server has been lost.');
        }

        while ($this->buffer !== false) {
            $type = null;
            try {
                $type = $this->encoder->decode($this->buffer);
                $this->buffer = false;

                if ($type->getTrailingData() != '') {
                    $this->buffer = $type->getTrailingData();
                } elseif (($peek = $this->socket->read(false)) !== false) {
                    $this->buffer .= $peek;
                }
            } catch (PartialPduException $e) {
                $this->buffer .= $this->socket->read();
            }

            if ($type !== null) {
                yield $this->getPdu($type, $id);
            }
        }
    }

    /**
     * @param int|null $id
     * @return mixed
     * @throws ConnectionException
     * @throws EncoderException
     */
    public function getMessage(?int $id = null)
    {
        return $this->getMessages($id)->current();
    }

    /**
     * Responsible for validating / constructing the PDU from the ASN.1 type received.
     *
     * @param AbstractType $asn1
     * @param int|null $id
     * @return mixed
     */
    protected function getPdu(AbstractType $asn1, ?int $id = null)
    {
        if ($this->pduClass === null) {
            throw new \RuntimeException('You must either define a PDU class or override getPdu().');
        }

        return call_user_func($this->pduClass.'::'.'fromAsn1', $asn1);
    }
}
