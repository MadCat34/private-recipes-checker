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

    public function testGeneratedEndpointDirectoriesAreNotMistakenForRecipes(): void
    {
        // flex-endpoint/archived/<package_dotted>/ is generated by the pipeline itself and sits at
        // depth 2, but it is not a recipe and has no manifest.json.
        $this->filesystem->dumpFile($this->fixtureDir.'/flex-endpoint/archived/acme.private-bundle/abc123.json', "{}\n");

        // A real recipe missing its manifest must still be reported.
        $this->filesystem->mkdir($this->fixtureDir.'/acme/other-bundle/1.0');

        $reporter = new RecordingErrorReporter();
        (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $manifestErrors = array_values(array_filter(
            $reporter->errors,
            static fn (array $error): bool => str_contains($error['message'], 'must define a "manifest.json"')
        ));

        $this->assertCount(1, $manifestErrors);
        $this->assertSame('acme/other-bundle/1.0', $manifestErrors[0]['file']);
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

    public function testDependencyDirectoriesAreNotLinted(): void
    {
        // Two-space indentation, which checkIndentation() rejects — but these are dependencies,
        // not recipe content. The shipped pipelines only escaped this because they clone the
        // checker into a dot-directory, which Finder skips by default.
        $this->filesystem->dumpFile($this->fixtureDir.'/vendor/some/package/composer.json', "{\n  \"a\": 1\n}\n");
        $this->filesystem->dumpFile($this->fixtureDir.'/node_modules/some-package/package.json', "{\n  \"b\": 2\n}\n");

        // A real violation at a normal path, with the same two-space indentation as the dependency
        // fixtures above. Without this, the assertion that dependency directories produce no errors
        // would pass just as well if the Finder matched nothing at all — this proves the linter is
        // actually running and would catch the same mistake outside vendor/ and node_modules/.
        $this->writeFixture('a.json', "{\n  \"c\": 3\n}\n");

        $reporter = new RecordingErrorReporter();
        $exitCode = (new CommandTester(new LintFilesCommand($reporter, $this->fixtureDir)))->execute([]);

        $this->assertTrue($this->hasErrorContaining($reporter, 'Indentation must be a multiple of 4 spaces'));
        $this->assertSame(['a.json'], array_values(array_unique(array_column($reporter->errors, 'file'))));
        $this->assertSame(1, $exitCode);
    }
}
