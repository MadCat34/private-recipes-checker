<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\ErrorReporter;

use Symfony\Component\Console\Output\OutputInterface;

final class GitHubActionsErrorReporter implements ErrorReporter
{
    public function __construct(private OutputInterface $output)
    {
    }

    public function reportError(string $message, ?string $file = null, ?int $line = null): void
    {
        if (null === $file) {
            $this->output->writeln(sprintf('::error::%s', $message));

            return;
        }

        if (null === $line) {
            $this->output->writeln(sprintf('::error file=%s::%s', $file, $message));

            return;
        }

        $this->output->writeln(sprintf('::error file=%s,line=%d::%s', $file, $line, $message));
    }

    public function flush(string $commandName): void
    {
        // No-op: GitHub Actions annotations are already emitted on stdout as they happen.
    }
}
