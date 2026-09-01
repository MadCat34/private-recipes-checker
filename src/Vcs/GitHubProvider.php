<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Vcs;

use App\ErrorReporter\ErrorReporter;
use App\ErrorReporter\GitHubActionsErrorReporter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GitHubProvider implements VcsProvider
{
    public function __construct(
        private ?string $eventPath,
        private ?string $token,
        private HttpClientInterface $httpClient = new NativeHttpClient(),
    ) {
    }

    public static function detect(array $env): ?static
    {
        if (empty($env['GITHUB_ACTIONS'])) {
            return null;
        }

        return new static(
            $env['GITHUB_EVENT_PATH'] ?? null,
            $env['VCS_TOKEN'] ?? $env['GITHUB_TOKEN'] ?? null,
        );
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getPullRequestBody(): string
    {
        return $this->eventData()['pull_request']['body'] ?? '';
    }

    public function getPullRequestCommits(): array
    {
        $commitsUrl = $this->eventData()['pull_request']['commits_url'];
        $response = $this->httpClient->request('GET', $commitsUrl, ['auth_bearer' => $this->token]);

        $commits = [];
        foreach ($response->toArray() as $commit) {
            $commits[] = ['sha' => $commit['sha'], 'parent_count' => \count($commit['parents'])];
        }

        return $commits;
    }

    public function getChangedFiles(): array
    {
        $filesUrl = $this->eventData()['pull_request']['url'].'/files';

        $files = [];
        $page = 1;
        do {
            $response = $this->httpClient->request('GET', $filesUrl, [
                'auth_bearer' => $this->token,
                'query' => ['per_page' => 100, 'page' => $page],
            ]);
            $batch = $response->toArray();
            foreach ($batch as $file) {
                $files[] = $file['filename'];
            }
            ++$page;
            // ponytail: hard cap at 1000 files (10 pages) — raise if a PR genuinely needs more.
        } while (100 === \count($batch) && $page <= 10);

        return $files;
    }

    public function getRawFileUrl(string $repository, string $ref, string $path): string
    {
        return sprintf('https://api.github.com/repos/%s/contents/%s?ref=%s', $repository, $path, $ref);
    }

    public function getRepositoryUrl(string $repository): string
    {
        return 'github.com/'.$repository;
    }

    public function createErrorReporter(): ErrorReporter
    {
        return new GitHubActionsErrorReporter(new ConsoleOutput());
    }

    private function eventData(): array
    {
        if (null === $this->eventPath || !is_file($this->eventPath)) {
            throw new \RuntimeException('GITHUB_EVENT_PATH is not set or not readable.');
        }

        return json_decode(file_get_contents($this->eventPath), true, flags: \JSON_THROW_ON_ERROR);
    }
}
