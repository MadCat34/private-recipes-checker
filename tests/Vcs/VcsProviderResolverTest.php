<?php

namespace App\Tests\Vcs;

use App\ErrorReporter\ErrorReporter;
use App\ErrorReporter\PlainTextErrorReporter;
use App\Vcs\NullVcsProvider;
use App\Vcs\VcsProvider;
use App\Vcs\VcsProviderResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class FakeEnvMarkerProvider implements VcsProvider
{
    public static function detect(array $env): ?static
    {
        return isset($env['FAKE_CI']) ? new static() : null;
    }

    public function getToken(): ?string
    {
        return 'fake-token';
    }

    public function getPullRequestCommits(): array
    {
        return [];
    }

    public function getPullRequestBody(): string
    {
        return '';
    }

    public function getChangedFiles(): array
    {
        return [];
    }

    public function getRawFileUrl(string $repository, string $ref, string $path): string
    {
        return "fake://$repository/$ref/$path";
    }

    public function getRepositoryUrl(string $repository): string
    {
        return "fake.example/$repository";
    }

    public function createErrorReporter(): ErrorReporter
    {
        return new PlainTextErrorReporter(new BufferedOutput());
    }
}

final class FakeNeverMatchesProvider implements VcsProvider
{
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
        return [];
    }

    public function getPullRequestBody(): string
    {
        return '';
    }

    public function getChangedFiles(): array
    {
        return [];
    }

    public function getRawFileUrl(string $repository, string $ref, string $path): string
    {
        return '';
    }

    public function getRepositoryUrl(string $repository): string
    {
        return '';
    }

    public function createErrorReporter(): ErrorReporter
    {
        return new PlainTextErrorReporter(new BufferedOutput());
    }
}

class VcsProviderResolverTest extends TestCase
{
    public function testResolvePicksTheFirstMatchingProviderInOrder(): void
    {
        $resolver = new VcsProviderResolver([FakeNeverMatchesProvider::class, FakeEnvMarkerProvider::class]);

        $provider = $resolver->resolve(['FAKE_CI' => '1']);

        $this->assertInstanceOf(FakeEnvMarkerProvider::class, $provider);
    }

    public function testResolveFallsBackToNullVcsProviderWhenNoneMatch(): void
    {
        $resolver = new VcsProviderResolver([FakeNeverMatchesProvider::class]);

        $provider = $resolver->resolve([]);

        $this->assertInstanceOf(NullVcsProvider::class, $provider);
    }

    public function testResolveNeverCallsGetenv(): void
    {
        // Regression guard: providers must only look at the $env array passed in, never getenv().
        // Setting an env var that WOULD match FakeEnvMarkerProvider's real getenv() usage (it has
        // none) and asserting the array-only lookup is what decides the outcome.
        putenv('FAKE_CI=1');
        $resolver = new VcsProviderResolver([FakeEnvMarkerProvider::class]);

        $provider = $resolver->resolve([]); // empty array: no FAKE_CI key

        putenv('FAKE_CI'); // cleanup
        $this->assertInstanceOf(NullVcsProvider::class, $provider);
    }
}
