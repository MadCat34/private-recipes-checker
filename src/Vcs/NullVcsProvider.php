<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Vcs;

use App\ErrorReporter\ErrorReporter;
use App\ErrorReporter\PlainTextErrorReporter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fallback used when no CI environment is detected (a developer running commands locally).
 * Deliberately absent from VcsProviderResolver::DEFAULT_PROVIDERS: it is the resolver's default
 * return value, not a chain candidate, so its own detect() always returns null and is never called.
 */
final class NullVcsProvider implements VcsProvider
{
    public function __construct(private OutputInterface $output = new ConsoleOutput())
    {
    }

    public static function detect(array $env): ?static
    {
        return null;
    }

    public function getToken(): ?string
    {
        return null;
    }

    public function getPullRequestCommits(): array
    {
        throw $this->unavailable();
    }

    public function getPullRequestBody(): string
    {
        throw $this->unavailable();
    }

    public function getChangedFiles(): array
    {
        throw $this->unavailable();
    }

    public function getRawFileUrl(string $repository, string $ref, string $path): string
    {
        throw $this->unavailable();
    }

    public function getRepositoryUrl(string $repository): string
    {
        throw $this->unavailable();
    }

    public function createErrorReporter(): ErrorReporter
    {
        return new PlainTextErrorReporter($this->output);
    }

    private function unavailable(): \LogicException
    {
        return new \LogicException('This command requires running inside GitHub Actions or GitLab CI.');
    }
}
