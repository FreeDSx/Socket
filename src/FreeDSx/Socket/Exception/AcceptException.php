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

namespace FreeDSx\Socket\Exception;

/**
 * Thrown when a waiting client cannot be accepted (such as with no descriptor free or a failed TLS handshake).
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
final class AcceptException extends ConnectionException
{
}
