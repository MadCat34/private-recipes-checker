<?php

namespace App\Tests\ErrorReporter;

use App\ErrorReporter\PlainTextErrorReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class PlainTextErrorReporterTest extends TestCase
{
    public function testReportErrorWritesPlainMessage(): void
    {
        $output = new BufferedOutput();
        $reporter = new PlainTextErrorReporter($output);

        $reporter->reportError('Something is wrong', 'manifest.json', 12);

        $this->assertSame("manifest.json:12: Something is wrong\n", $output->fetch());
    }

    public function testReportErrorWithoutFileOrLine(): void
    {
        $output = new BufferedOutput();
        $reporter = new PlainTextErrorReporter($output);

        $reporter->reportError('Global problem');

        $this->assertSame("Global problem\n", $output->fetch());
    }

    public function testFlushIsANoop(): void
    {
        $output = new BufferedOutput();
        $reporter = new PlainTextErrorReporter($output);

        $reporter->flush('lint:manifests');

        $this->assertSame('', $output->fetch());
    }
}
