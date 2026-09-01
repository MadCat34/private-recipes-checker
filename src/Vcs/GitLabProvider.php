<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Vcs;

use App\ErrorReporter\ErrorReporter;
use App\ErrorReporter\GitLabErrorReporter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GitLabProvider implements VcsProvider
{
    public function __construct(
        private ?string $apiUrl,
        private ?string $projectId,
        private ?string $mergeRequestIid,
        private ?string $token,
        private string $tokenHeader,
        private ?string $codeQualityOutputDir = null,
        private HttpClientInterface $httpClient = new NativeHttpClient(),
    ) {
    }

    public static function detect(array $env): ?static
    {
        if (empty($env['GITLAB_CI'])) {
            return null;
        }

        if (isset($env['VCS_TOKEN'])) {
            $token = $env['VCS_TOKEN'];
            $tokenHeader = 'PRIVATE-TOKEN';
        } else {
            $token = $env['CI_JOB_TOKEN'] ?? null;
            $tokenHeader = 'JOB-TOKEN';
        }

        return new static(
            $env['CI_API_V4_URL'] ?? null,
            $env['CI_PROJECT_ID'] ?? null,
            $env['CI_MERGE_REQUEST_IID'] ?? null,
            $token,
            $tokenHeader,
            $env['CI_PROJECT_DIR'] ?? null,
        );
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getPullRequestBody(): string
    {
        $response = $this->httpClient->request('GET', $this->mergeRequestUrl(), $this->authOptions());

        return $response->toArray()['description'] ?? '';
    }

    public function getPullRequestCommits(): array
    {
        $response = $this->httpClient->request('GET', $this->mergeRequestUrl().'/commits', $this->authOptions());

        $commits = [];
        foreach ($response->toArray() as $commit) {
            $commits[] = ['sha' => $commit['id'], 'parent_count' => \count($commit['parent_ids'] ?? [])];
        }

        return $commits;
    }

    public function getChangedFiles(): array
    {
        $response = $this->httpClient->request('GET', $this->mergeRequestUrl().'/changes', $this->authOptions());
        $data = $response->toArray();

        return array_map(static fn (array $change): string => $change['new_path'], $data['changes'] ?? []);
    }

    public function getRawFileUrl(string $repository, string $ref, string $path): string
    {
        $encodedPath = str_replace('/', '%2F', $path);

        return sprintf(
            '%s/projects/%s/repository/files/%s/raw?ref=%s',
            $this->apiUrl,
            rawurlencode($repository),
            $encodedPath,
            $ref
        );
    }

    public function getRepositoryUrl(string $repository): string
    {
        $host = parse_url((string) $this->apiUrl, \PHP_URL_HOST) ?? 'gitlab.com';

        return $host.'/'.$repository;
    }

    public function createErrorReporter(): ErrorReporter
    {
        return new GitLabErrorReporter(new ConsoleOutput(), $this->codeQualityOutputDir ?? getcwd());
    }

    private function mergeRequestUrl(): string
    {
        return sprintf('%s/projects/%s/merge_requests/%s', $this->apiUrl, rawurlencode((string) $this->projectId), $this->mergeRequestIid);
    }

    private function authOptions(): array
    {
        return null !== $this->token ? ['headers' => [$this->tokenHeader => $this->token]] : [];
    }
}
