<?php

namespace App\Tests\Command;

use App\Command\LintManifestsCommand;
use App\Manifest\ManifestValidator;
use App\Tests\Support\RecordingErrorReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class LintManifestsCommandTest extends TestCase
{
    private string $fixtureDir;
    private string $originalCwd;
    private Filesystem $filesystem;
    private ManifestValidator $manifestValidator;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->fixtureDir = sys_get_temp_dir().'/lint-manifests-test-'.uniqid();
        $this->filesystem->mkdir($this->fixtureDir);
        $this->originalCwd = getcwd();
        chdir($this->fixtureDir);
        $this->manifestValidator = new ManifestValidator(__DIR__.'/../../resources/manifest.schema.json');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->filesystem->remove($this->fixtureDir);
    }

    private function writeRecipe(string $package, string $version, array $manifest, array $extraFiles = []): void
    {
        $dir = $this->fixtureDir."/$package/$version";
        $this->filesystem->mkdir($dir);
        file_put_contents("$dir/manifest.json", json_encode($manifest, \JSON_PRETTY_PRINT));
        foreach ($extraFiles as $relativePath => $contents) {
            $this->filesystem->dumpFile("$dir/$relativePath", $contents);
        }
    }

    private function runCommand(): array
    {
        $reporter = new RecordingErrorReporter();
        $command = new LintManifestsCommand($reporter, $this->manifestValidator);
        (new CommandTester($command))->execute([]);

        return $reporter->errors;
    }

    public function testValidRecipeProducesNoErrors(): void
    {
        $this->writeRecipe('acme/private-bundle', '1.0', [
            'bundles' => ['Acme\\PrivateBundle\\AcmeBundle' => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/'],
        ], ['config/packages/acme.yaml' => "acme: ~\n"]);

        $this->assertSame([], $this->runCommand());
    }

    public function testUnknownKeyIsRejectedByTheSchema(): void
    {
        $this->writeRecipe('acme/private-bundle', '1.0', ['not-a-real-key' => true]);

        $errors = $this->runCommand();

        $this->assertNotEmpty($errors);
    }

    public function testComposerCommandsIsAccepted(): void
    {
        // Regression: the upstream ALLOWED_KEYS array is missing "composer-commands" even though
        // it's a documented, Flex-supported key — the schema (Task 9) fixes that.
        $this->writeRecipe('acme/private-bundle', '1.0', ['composer-commands' => ['test' => 'bin/phpunit']]);

        $this->assertSame([], $this->runCommand());
    }

    public function testASchemaKeyDoesNotTriggerAnUnsupportedKeyError(): void
    {
        $this->writeRecipe('acme/private-bundle', '1.0', [
            '$schema' => 'https://example.test/manifest.schema.json',
            'bundles' => ['Acme\\PrivateBundle\\AcmeBundle' => ['dev', 'test']],
        ]);

        $this->assertSame([], $this->runCommand());
    }

    public function testDuplicateAliasAcrossTwoPackagesIsRejected(): void
    {
        $this->writeRecipe('acme/private-bundle', '1.0', ['aliases' => ['acme'], 'container' => ['x' => 1]]);
        $this->writeRecipe('acme/other-bundle', '1.0', ['aliases' => ['acme'], 'container' => ['x' => 1]]);

        $errors = $this->runCommand();

        $this->assertNotEmpty(array_filter($errors, fn (array $e) => str_contains($e['message'], 'also defined for')));
    }

    public function testSpecialComposerAliasNamesAreRejected(): void
    {
        // Regression for the $aliases-vs-$alias typo: without the fix, this case is silently
        // accepted because `in_array($aliases, [...])` compares an array to a string and can
        // never be true.
        $this->writeRecipe('acme/private-bundle', '1.0', ['aliases' => ['lock'], 'container' => ['x' => 1]]);

        $errors = $this->runCommand();

        $this->assertNotEmpty(array_filter($errors, fn (array $e) => str_contains($e['message'], 'special alias')));
    }

    public function testFileNotListedInCopyFromRecipeIsRejected(): void
    {
        $this->writeRecipe('acme/private-bundle', '1.0', ['container' => ['x' => 1]], ['config/packages/acme.yaml' => "acme: ~\n"]);

        $errors = $this->runCommand();

        $this->assertNotEmpty(array_filter($errors, fn (array $e) => str_contains($e['message'], 'must be listed')));
    }

    public function testRecipeOnlyRegisteringOneBundleForAllEnvironmentsIsFlaggedAsUnneeded(): void
    {
        $this->writeRecipe('acme/private-bundle', '1.0', ['bundles' => ['Acme\\PrivateBundle\\AcmeBundle' => ['all']]]);

        $errors = $this->runCommand();

        $this->assertNotEmpty(array_filter($errors, fn (array $e) => str_contains($e['message'], 'is not needed')));
    }

    public function testRecipeWithPostInstallTextIsNotFlaggedAsUnneeded(): void
    {
        $this->writeRecipe(
            'acme/private-bundle',
            '1.0',
            ['bundles' => ['Acme\\PrivateBundle\\AcmeBundle' => ['all']]],
            ['post-install.txt' => "Thanks for installing!\n"]
        );

        $errors = $this->runCommand();

        $this->assertEmpty(array_filter($errors, fn (array $e) => str_contains($e['message'], 'is not needed')));
    }
}
