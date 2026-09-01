<?php

namespace App\Tests\Command;

use App\Command\LintYamlCommand;
use App\Tests\Support\RecordingErrorReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class LintYamlCommandTest extends TestCase
{
    private string $originalCwd;
    private string $tempBase;

    protected function setUp(): void
    {
        // The command's config/packages/ detection regex is anchored and expects a path
        // relative to cwd (that's how CI feeds it: relative to the recipes/ checkout).
        // Run each test inside a temp cwd so writeTempFile() can hand the command paths
        // shaped exactly like production ones, instead of absolute sys_get_temp_dir() paths.
        $this->originalCwd = getcwd();
        $this->tempBase = sys_get_temp_dir().'/lint-yaml-test-'.uniqid();
        (new \Symfony\Component\Filesystem\Filesystem())->mkdir($this->tempBase);
        chdir($this->tempBase);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        (new \Symfony\Component\Filesystem\Filesystem())->remove($this->tempBase);
    }

    private function writeTempFile(string $relativeDir, string $filename, string $contents): string
    {
        (new \Symfony\Component\Filesystem\Filesystem())->mkdir($relativeDir);
        $path = $relativeDir.'/'.$filename;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Drives the command exactly as CI does: a list of file paths piped in on stdin.
     *
     * @param list<string> $files
     */
    private function runCommand(RecordingErrorReporter $reporter, array $files): CommandTester
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, implode("\n", $files)."\n");
        rewind($stream);

        $tester = new CommandTester(new LintYamlCommand($reporter, $stream));
        $tester->execute([]);

        return $tester;
    }

    public function testValidYamlProducesNoErrors(): void
    {
        $file = $this->writeTempFile('valid', 'a.yaml', "foo: bar\n");
        $reporter = new RecordingErrorReporter();

        $tester = $this->runCommand($reporter, [$file]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([], $reporter->errors);
    }

    public function testInvalidYamlSyntaxReportsFileAndLine(): void
    {
        $file = $this->writeTempFile('invalid', 'a.yaml', "foo: [unclosed\n");
        $reporter = new RecordingErrorReporter();

        $tester = $this->runCommand($reporter, [$file]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertCount(1, $reporter->errors);
        $this->assertSame($file, $reporter->errors[0]['file']);
    }

    public function testEmptyEntryUnderConfigPackagesIsRejected(): void
    {
        // Empty-entry detection only inspects top-level keys (unchanged, pre-existing behavior),
        // so "foo" must be top-level here for the command to flag it.
        $file = $this->writeTempFile('acme/private-bundle/1.0/config/packages', 'acme.yaml', "foo: ~\n");
        $reporter = new RecordingErrorReporter();

        $tester = $this->runCommand($reporter, [$file]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('"foo" entry should be removed as it is empty', $reporter->errors[0]['message']);
    }

    public function testEmptyEntryOutsideConfigPackagesIsIgnored(): void
    {
        $file = $this->writeTempFile('acme/private-bundle/1.0', 'a.yaml', "foo: ~\n");
        $reporter = new RecordingErrorReporter();

        $tester = $this->runCommand($reporter, [$file]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([], $reporter->errors);
    }

    public function testNonArrayConfigPackagesFileIsRejected(): void
    {
        $file = $this->writeTempFile('acme/private-bundle/1.0/config/packages', 'acme.yaml', "just a string\n");
        $reporter = new RecordingErrorReporter();

        $tester = $this->runCommand($reporter, [$file]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame('A configuration array is expected', $reporter->errors[0]['message']);
    }

    public function testMultipleFilesAreAllProcessedAndFlushIsCalledOnce(): void
    {
        // A behavior reflection into validate() could never have caught: that execute() actually
        // loops over every stdin line and calls flush() exactly once at the end.
        $valid = $this->writeTempFile('multi', 'valid.yaml', "a: b\n");
        $invalid = $this->writeTempFile('multi', 'invalid.yaml', "a: [unclosed\n");
        $reporter = new RecordingErrorReporter();

        $tester = $this->runCommand($reporter, [$valid, $invalid]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertCount(1, $reporter->errors);
        $this->assertSame('lint:yaml', $reporter->flushedCommandName);
    }
}
