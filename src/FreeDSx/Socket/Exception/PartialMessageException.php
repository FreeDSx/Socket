<?php
/**
 * This file is part of the FreeDSx Socket package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Socket\Exception;

/**
 * Thrown in the MessageQueue if the data received is not complete.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class PartialMessageException extends \Exception
{
}
