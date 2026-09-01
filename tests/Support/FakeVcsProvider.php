<?php
// tests/Support/FakeVcsProvider.php

namespace App\Tests\Support;

use App\ErrorReporter\ErrorReporter;
use App\Vcs\VcsProvider;

final class FakeVcsProvider implements VcsProvider
{
    public ?string $token = null;
    /** @var list<array{sha: string, parent_count: int}> */
    public array $pullRequestCommits = [];
    public string $pullRequestBody = '';
    /** @var list<string> */
    public array $changedFiles = [];
    public string $rawFileUrlResult = '';
    public string $repositoryUrlResult = '';

    public function __construct(private ErrorReporter $errorReporter)
    {
    }

    public static function detect(array $env): ?static
    {
        return null;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getPullRequestCommits(): array
    {
        return $this->pullRequestCommits;
    }

    public function getPullRequestBody(): string
    {
        return $this->pullRequestBody;
    }

    public function getChangedFiles(): array
    {
        return $this->changedFiles;
    }

    public function getRawFileUrl(string $repository, string $ref, string $path): string
    {
        return $this->rawFileUrlResult;
    }

    public function getRepositoryUrl(string $repository): string
    {
        return $this->repositoryUrlResult;
    }

    public function createErrorReporter(): ErrorReporter
    {
        return $this->errorReporter;
    }
}
