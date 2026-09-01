<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\ErrorReporter;

interface ErrorReporter
{
    public function reportError(string $message, ?string $file = null, ?int $line = null): void;

    /** Writes whatever must be written at the end of a command. No-op for reporters that stream. */
    public function flush(string $commandName): void;
}
