<?php

namespace App\Tests\ErrorReporter;

use App\ErrorReporter\GitHubActionsErrorReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class GitHubActionsErrorReporterTest extends TestCase
{
    public function testReportErrorWithFileAndLine(): void
    {
        $output = new BufferedOutput();
        $reporter = new GitHubActionsErrorReporter($output);

        $reporter->reportError('Bad key', 'manifest.json', 12);

        $this->assertSame("::error file=manifest.json,line=12::Bad key\n", $output->fetch());
    }

    public function testReportErrorWithFileOnly(): void
    {
        $output = new BufferedOutput();
        $reporter = new GitHubActionsErrorReporter($output);

        $reporter->reportError('Unsupported key "foo"', 'manifest.json');

        $this->assertSame("::error file=manifest.json::Unsupported key \"foo\"\n", $output->fetch());
    }

    public function testReportErrorWithoutFile(): void
    {
        $output = new BufferedOutput();
        $reporter = new GitHubActionsErrorReporter($output);

        $reporter->reportError('Global problem');

        $this->assertSame("::error::Global problem\n", $output->fetch());
    }

    public function testFlushIsANoop(): void
    {
        $output = new BufferedOutput();
        $reporter = new GitHubActionsErrorReporter($output);

        $reporter->flush('lint:manifests');

        $this->assertSame('', $output->fetch());
    }
}
