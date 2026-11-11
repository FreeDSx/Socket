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

use Swoole\Coroutine;
use Swoole\Runtime;

trait RunsInCoroutine
{
    private function runInCoroutine(callable $callback): void
    {
        Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

        try {
            Coroutine\run($callback);
        } finally {
            Runtime::enableCoroutine(0);
        }
    }
}
