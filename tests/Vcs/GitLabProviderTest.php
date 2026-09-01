<?php

namespace App\Tests\Vcs;

use App\ErrorReporter\GitLabErrorReporter;
use App\Vcs\GitLabProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GitLabProviderTest extends TestCase
{
    public function testDetectReturnsNullWhenNotGitlabCi(): void
    {
        $this->assertNull(GitLabProvider::detect([]));
        $this->assertNull(GitLabProvider::detect(['GITLAB_CI' => '']));
    }

    public function testDetectUsesPrivateTokenHeaderWhenVcsTokenIsSet(): void
    {
        $provider = GitLabProvider::detect([
            'GITLAB_CI' => 'true',
            'VCS_TOKEN' => 'a-pat',
            'CI_JOB_TOKEN' => 'a-job-token',
        ]);

        $this->assertInstanceOf(GitLabProvider::class, $provider);
        $this->assertSame('a-pat', $provider->getToken());
    }

    public function testDetectFallsBackToJobTokenHeaderWhenNoVcsToken(): void
    {
        $provider = GitLabProvider::detect([
            'GITLAB_CI' => 'true',
            'CI_JOB_TOKEN' => 'a-job-token',
        ]);

        $this->assertSame('a-job-token', $provider->getToken());
    }

    public function testGetRawFileUrlEncodesSlashesInThePathButNotPlaceholders(): void
    {
        $provider = new GitLabProvider('https://gitlab.com/api/v4', null, null, null, 'PRIVATE-TOKEN');

        $url = $provider->getRawFileUrl('acme/recipes', 'flex/main', 'archived/{package_dotted}/{ref}.json');

        $this->assertSame(
            'https://gitlab.com/api/v4/projects/acme%2Frecipes/repository/files/archived%2F{package_dotted}%2F{ref}.json/raw?ref=flex/main',
            $url
        );
    }

    public function testGetRepositoryUrlUsesTheApiUrlHost(): void
    {
        $provider = new GitLabProvider('https://gitlab.mycompany.com/api/v4', null, null, null, 'PRIVATE-TOKEN');

        $this->assertSame('gitlab.mycompany.com/acme/recipes', $provider->getRepositoryUrl('acme/recipes'));
    }

    public function testCreateErrorReporterReturnsGitLabErrorReporter(): void
    {
        $provider = new GitLabProvider('https://gitlab.com/api/v4', null, null, null, 'PRIVATE-TOKEN', sys_get_temp_dir());

        $this->assertInstanceOf(GitLabErrorReporter::class, $provider->createErrorReporter());
    }

    public function testGetPullRequestBodyReturnsTheMergeRequestDescription(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('https://gitlab.com/api/v4/projects/42/merge_requests/7', $url);
            $this->assertSame('JOB-TOKEN: a-job-token', $options['normalized_headers']['job-token'][0]);

            return new MockResponse(json_encode(['description' => 'License MIT']));
        });

        $provider = new GitLabProvider('https://gitlab.com/api/v4', '42', '7', 'a-job-token', 'JOB-TOKEN', null, $httpClient);

        $this->assertSame('License MIT', $provider->getPullRequestBody());
    }

    public function testGetPullRequestCommitsCountsParentIds(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                ['id' => 'aaa', 'parent_ids' => ['zzz']],
                ['id' => 'bbb', 'parent_ids' => ['aaa', 'ccc']],
            ])),
        ]);

        $provider = new GitLabProvider('https://gitlab.com/api/v4', '42', '7', 't', 'PRIVATE-TOKEN', null, $httpClient);

        $this->assertSame(
            [
                ['sha' => 'aaa', 'parent_count' => 1],
                ['sha' => 'bbb', 'parent_count' => 2],
            ],
            $provider->getPullRequestCommits()
        );
    }

    public function testGetChangedFilesReturnsNewPathsFromChanges(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'changes' => [
                    ['new_path' => 'acme/private-bundle/1.0/manifest.json'],
                    ['new_path' => 'acme/private-bundle/1.0/config/packages/acme.yaml'],
                ],
            ])),
        ]);

        $provider = new GitLabProvider('https://gitlab.com/api/v4', '42', '7', 't', 'PRIVATE-TOKEN', null, $httpClient);

        $this->assertSame(
            ['acme/private-bundle/1.0/manifest.json', 'acme/private-bundle/1.0/config/packages/acme.yaml'],
            $provider->getChangedFiles()
        );
    }
}
