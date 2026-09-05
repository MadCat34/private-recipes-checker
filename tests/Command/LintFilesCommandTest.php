<?php

namespace App\Tests\Command;

use App\Command\LintFilesCommand;
use App\Tests\Support\RecordingErrorReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class LintFilesCommandTest extends TestCase
{
    private string $fixtureDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->fixtureDir = sys_get_temp_dir().'/lint-files-test-'.uniqid();
        $this->filesystem->mkdir($this->fixtureDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->fixtureDir);
    }

    private function writeFixture(string $relativePath, string $contents): void
    {
        $this->filesystem->dumpFile($this->fixtureDir.'/'.$relativePath, $contents);
    }

    public function testCleanRepositoryReportsNoErrors(): void
    {
        $this->writeFixture('acme/private-bundle/1.0/manifest.json', "{\n    \"aliases\": [\"acme\"]\n}\n");

        $reporter = new RecordingErrorReporter();
        $command = new LintFilesCommand($reporter, $this->fixtureDir);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame([], $reporter->errors);
        $this->assertSame(0, $exitCode);
        $this->assertSame('lint:files', $reporter->flushedCommandName);
    }

    public function testRejectsYmlExtension(): void
    {
        $this->writeFixture('acme/private-bundle/1.0/manifest.yml', "a: b\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertStringContainsString('*.yaml', $reporter->errors[0]['message']);
    }

    public function testRejectsSymlinks(): void
    {
        $this->writeFixture('real.txt', "content\n");
        symlink($this->fixtureDir.'/real.txt', $this->fixtureDir.'/link.txt');

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertSame('Symlinks are not allowed', $reporter->errors[0]['message']);
    }

    public function testRejectsGitkeepFiles(): void
    {
        $this->writeFixture('acme/private-bundle/1.0/config/.gitkeep', '');

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertStringContainsString('.gitignore', $reporter->errors[0]['message']);
    }

    public function testRejectsIndentationNotAMultipleOfFourSpaces(): void
    {
        $this->writeFixture('a.yaml', "foo:\n  bar: baz\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertSame(2, $reporter->errors[0]['line']);
    }

    public function testRejectsMissingTrailingNewline(): void
    {
        $this->writeFixture('a.txt', 'no newline at the end');

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertStringContainsString('newline', $reporter->errors[0]['message']);
    }

    public function testRejectsHttpLinksToSymfonyCom(): void
    {
        $this->writeFixture('a.txt', "See http://symfony.com/doc\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertStringContainsString('https', $reporter->errors[0]['message']);
    }

    public function testRejectsNonUnderscoreNotationUnderConfig(): void
    {
        $this->writeFixture('acme/private-bundle/1.0/config/packages/AcmeBundle.yaml', "a: b\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertStringContainsString('Underscore notation', $reporter->errors[0]['message']);
    }

    public function testAllowsUnderscoreNotationUnderConfig(): void
    {
        $this->writeFixture('acme/private-bundle/1.0/manifest.json', "{}\n");
        $this->writeFixture('acme/private-bundle/1.0/config/packages/acme_bundle.yaml', "a: b\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertSame([], $reporter->errors);
    }

    public function testRejectsTildeAsYamlNull(): void
    {
        $this->writeFixture('a.yaml', "foo: ~\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertStringContainsString('null', $reporter->errors[0]['message']);
    }

    public function testRejectsConsoleCommandsInMakefile(): void
    {
        $this->writeFixture('acme/private-bundle/1.0/Makefile', "cc:\n\tbin/console cache:clear\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertStringContainsString('Makefile', $reporter->errors[0]['message']);
    }

    public function testRejectsMissingManifestJson(): void
    {
        $this->writeFixture('acme/private-bundle/1.0/config/packages/acme_bundle.yaml', "a: b\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertTrue($this->hasErrorContaining($reporter, 'manifest.json'));
    }

    public function testRejectsInvalidJson(): void
    {
        $this->writeFixture('a.json', '{ invalid');

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertTrue($this->hasErrorContaining($reporter, 'JSON'));
    }

    public function testRejectsParametersKeyInPackagesConfig(): void
    {
        $this->writeFixture('acme/private-bundle/1.0/manifest.json', "{}\n");
        $this->writeFixture('acme/private-bundle/1.0/config/packages/acme_bundle.yaml', "parameters:\n    foo: bar\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertTrue($this->hasErrorContaining($reporter, 'container'));
    }

    private function hasErrorContaining(RecordingErrorReporter $reporter, string $needle): bool
    {
        foreach ($reporter->errors as $error) {
            if (str_contains($error['message'], $needle)) {
                return true;
            }
        }

        return false;
    }

    public function testDotDirectoriesLikeGithubAreIgnored(): void
    {
        // .github/workflows/*.yml is a real, expected file — GitHub Actions requires that
        // extension there. It must NOT trip the "*.yaml not *.yml" check.
        $this->writeFixture('.github/workflows/qa.yml', "name: QA\n");

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertSame([], $reporter->errors);
    }
}
