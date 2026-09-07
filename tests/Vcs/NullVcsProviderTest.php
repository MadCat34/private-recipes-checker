<?php

namespace App\Tests\Vcs;

use App\ErrorReporter\PlainTextErrorReporter;
use App\Vcs\NullVcsProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class NullVcsProviderTest extends TestCase
{
    public function testDetectAlwaysReturnsNull(): void
    {
        $this->assertNull(NullVcsProvider::detect(['GITHUB_ACTIONS' => 'true']));
    }

    public function testGetTokenReturnsNull(): void
    {
        $this->assertNull((new NullVcsProvider())->getToken());
    }

    public function testCreateErrorReporterReturnsPlainTextErrorReporter(): void
    {
        $provider = new NullVcsProvider(new BufferedOutput());

        $this->assertInstanceOf(PlainTextErrorReporter::class, $provider->createErrorReporter());
    }

    #[DataProvider('unavailableMethodProvider')]
    public function testMethodsRequiringACiEnvironmentThrow(callable $call): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This command requires running inside GitHub Actions or GitLab CI.');

        $call(new NullVcsProvider());
    }

    public static function unavailableMethodProvider(): iterable
    {
        yield 'getPullRequestCommits' => [fn (NullVcsProvider $p) => $p->getPullRequestCommits()];
        yield 'getPullRequestBody' => [fn (NullVcsProvider $p) => $p->getPullRequestBody()];
        yield 'getChangedFiles' => [fn (NullVcsProvider $p) => $p->getChangedFiles()];
        yield 'getRawFileUrl' => [fn (NullVcsProvider $p) => $p->getRawFileUrl('acme/recipes', 'main', 'a.json')];
        yield 'getRepositoryUrl' => [fn (NullVcsProvider $p) => $p->getRepositoryUrl('acme/recipes')];
    }
}
