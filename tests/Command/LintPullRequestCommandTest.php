<?php

namespace App\Tests\Command;

use App\Command\LintPullRequestCommand;
use App\Tests\Support\FakeVcsProvider;
use App\Tests\Support\RecordingErrorReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class LintPullRequestCommandTest extends TestCase
{
    private function runCommand(FakeVcsProvider $vcs, array $args = []): array
    {
        $command = new LintPullRequestCommand($vcs, $vcs->createErrorReporter());
        (new CommandTester($command))->execute($args);

        return $vcs->createErrorReporter()->errors;
    }

    public function testMissingRequiredLicenseIsRejected(): void
    {
        $vcs = new FakeVcsProvider(new RecordingErrorReporter());
        $vcs->pullRequestBody = 'Just a description, no license line.';

        $errors = $this->runCommand($vcs, ['--license' => 'MIT']);

        $this->assertNotEmpty(array_filter($errors, fn (array $e) => str_contains($e['message'], 'must be licensed under MIT')));
    }

    public function testMatchingLicenseLineIsAccepted(): void
    {
        $vcs = new FakeVcsProvider(new RecordingErrorReporter());
        $vcs->pullRequestBody = "Some text\n\nLicense MIT\n";

        $errors = $this->runCommand($vcs, ['--license' => 'MIT']);

        $this->assertSame([], $errors);
    }

    public function testNoLicenseOptionSkipsTheCheck(): void
    {
        $vcs = new FakeVcsProvider(new RecordingErrorReporter());
        $vcs->pullRequestBody = 'Anything at all.';

        $errors = $this->runCommand($vcs);

        $this->assertSame([], $errors);
    }

    public function testAMergeCommitIsRejected(): void
    {
        $vcs = new FakeVcsProvider(new RecordingErrorReporter());
        $vcs->pullRequestCommits = [
            ['sha' => 'aaa', 'parent_count' => 1],
            ['sha' => 'bbb', 'parent_count' => 2],
        ];

        $errors = $this->runCommand($vcs);

        $this->assertNotEmpty(array_filter($errors, fn (array $e) => str_contains($e['message'], 'merge commits')));
    }

    public function testNoMergeCommitsProducesNoErrors(): void
    {
        $vcs = new FakeVcsProvider(new RecordingErrorReporter());
        $vcs->pullRequestCommits = [
            ['sha' => 'aaa', 'parent_count' => 1],
            ['sha' => 'bbb', 'parent_count' => 1],
        ];

        $errors = $this->runCommand($vcs);

        $this->assertSame([], $errors);
    }
}
