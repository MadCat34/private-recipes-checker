<?php

namespace App\Tests\Vcs;

use App\ErrorReporter\GitHubActionsErrorReporter;
use App\Vcs\GitHubProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GitHubProviderTest extends TestCase
{
    public function testDetectReturnsNullWhenNotGitHubActions(): void
    {
        $this->assertNull(GitHubProvider::detect([]));
        $this->assertNull(GitHubProvider::detect(['GITHUB_ACTIONS' => '']));
    }

    public function testDetectReturnsAnInstanceWhenGitHubActionsIsSet(): void
    {
        $provider = GitHubProvider::detect([
            'GITHUB_ACTIONS' => 'true',
            'GITHUB_EVENT_PATH' => '/tmp/event.json',
            'GITHUB_TOKEN' => 'gh-token-from-env',
        ]);

        $this->assertInstanceOf(GitHubProvider::class, $provider);
        $this->assertSame('gh-token-from-env', $provider->getToken());
    }

    public function testVcsTokenTakesPrecedenceOverGithubToken(): void
    {
        $provider = GitHubProvider::detect([
            'GITHUB_ACTIONS' => 'true',
            'VCS_TOKEN' => 'explicit-token',
            'GITHUB_TOKEN' => 'gh-token-from-env',
        ]);

        $this->assertSame('explicit-token', $provider->getToken());
    }

    public function testGetRawFileUrlUsesTheContentsApiAndLeavesPlaceholdersUntouched(): void
    {
        $provider = new GitHubProvider(null, null);

        $url = $provider->getRawFileUrl('acme/recipes', 'flex/main', 'archived/{package_dotted}/{ref}.json');

        $this->assertSame(
            'https://api.github.com/repos/acme/recipes/contents/archived/{package_dotted}/{ref}.json?ref=flex/main',
            $url
        );
    }

    public function testGetRepositoryUrl(): void
    {
        $provider = new GitHubProvider(null, null);

        $this->assertSame('github.com/acme/recipes', $provider->getRepositoryUrl('acme/recipes'));
    }

    public function testCreateErrorReporterReturnsGitHubActionsErrorReporter(): void
    {
        $this->assertInstanceOf(GitHubActionsErrorReporter::class, (new GitHubProvider(null, null))->createErrorReporter());
    }

    public function testGetPullRequestBodyReadsTheEventFile(): void
    {
        $eventPath = tempnam(sys_get_temp_dir(), 'gh-event-');
        file_put_contents($eventPath, json_encode(['pull_request' => ['body' => 'License MIT']]));

        $provider = new GitHubProvider($eventPath, null);

        $this->assertSame('License MIT', $provider->getPullRequestBody());
        unlink($eventPath);
    }

    public function testGetPullRequestCommitsCallsTheCommitsUrlAndCountsParents(): void
    {
        $eventPath = tempnam(sys_get_temp_dir(), 'gh-event-');
        file_put_contents($eventPath, json_encode([
            'pull_request' => ['commits_url' => 'https://api.github.com/repos/acme/recipes/pulls/1/commits'],
        ]));

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                ['sha' => 'aaa', 'parents' => [['sha' => 'zzz']]],
                ['sha' => 'bbb', 'parents' => [['sha' => 'aaa'], ['sha' => 'ccc']]],
            ])),
        ]);

        $provider = new GitHubProvider($eventPath, 'a-token', $httpClient);

        $this->assertSame(
            [
                ['sha' => 'aaa', 'parent_count' => 1],
                ['sha' => 'bbb', 'parent_count' => 2],
            ],
            $provider->getPullRequestCommits()
        );
        unlink($eventPath);
    }

    public function testGetChangedFilesReturnsFilenamesFromTheFilesApi(): void
    {
        $eventPath = tempnam(sys_get_temp_dir(), 'gh-event-');
        file_put_contents($eventPath, json_encode([
            'pull_request' => ['url' => 'https://api.github.com/repos/acme/recipes/pulls/1'],
        ]));

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                ['filename' => 'acme/private-bundle/1.0/manifest.json'],
                ['filename' => 'acme/private-bundle/1.0/config/packages/acme.yaml'],
            ])),
        ]);

        $provider = new GitHubProvider($eventPath, 'a-token', $httpClient);

        $this->assertSame(
            ['acme/private-bundle/1.0/manifest.json', 'acme/private-bundle/1.0/config/packages/acme.yaml'],
            $provider->getChangedFiles()
        );
        unlink($eventPath);
    }
}
