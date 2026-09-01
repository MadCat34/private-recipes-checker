<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\ErrorReporter;

use Symfony\Component\Console\Output\OutputInterface;

final class PlainTextErrorReporter implements ErrorReporter
{
    public function __construct(private OutputInterface $output)
    {
    }

    public function reportError(string $message, ?string $file = null, ?int $line = null): void
    {
        if (null === $file) {
            $this->output->writeln($message);

            return;
        }

        $this->output->writeln(sprintf('%s%s: %s', $file, null !== $line ? ':'.$line : '', $message));
    }

    public function flush(string $commandName): void
    {
        // No-op: PlainTextErrorReporter streams directly, nothing to flush.
    }
}
