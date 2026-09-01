<?php

namespace App\Tests\Command;

use App\Command\GenerateFlexEndpointCommand;
use App\Tests\Support\FakeVcsProvider;
use App\Tests\Support\RecordingErrorReporter;
use App\Vcs\VcsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class GenerateFlexEndpointCommandTest extends TestCase
{
    private string $recipeDir;
    private string $originalCwd;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->recipeDir = sys_get_temp_dir().'/flex-endpoint-test-'.uniqid();
        $this->originalCwd = getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        if (is_dir($this->recipeDir)) {
            $this->filesystem->remove($this->recipeDir);
        }
    }

    /**
     * Drives the command exactly as `git ls-tree HEAD` piped into `run generate:flex-endpoint ...`
     * does in CI: one "<mode> <type> <sha>\t<package>/<version>" line per recipe, on stdin.
     *
     * @param list<string> $lsTreeLines
     * @return array{exitCode: int, outputDir: string}
     */
    private function runCommand(VcsProvider $vcs, array $lsTreeLines): array
    {
        $outputDir = $this->recipeDir.'/out';
        $this->filesystem->mkdir($outputDir);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, implode('', $lsTreeLines));
        rewind($stream);

        chdir($this->recipeDir);
        $tester = new CommandTester(new GenerateFlexEndpointCommand($vcs, $stream));
        $exitCode = $tester->execute([
            'repository' => 'acme/recipes',
            'source_branch' => 'main',
            'flex_branch' => 'flex/main',
            'output_directory' => $outputDir,
        ]);
        chdir($this->originalCwd);

        return ['exitCode' => $exitCode, 'outputDir' => $outputDir];
    }

    public function testIndexJsonLinksComeFromTheInjectedVcsProvider(): void
    {
        $this->filesystem->mkdir($this->recipeDir.'/acme/private-bundle/1.0');
        file_put_contents($this->recipeDir.'/acme/private-bundle/1.0/manifest.json', json_encode(['container' => ['x' => 1]]));

        $vcs = new FakeVcsProvider(new RecordingErrorReporter());
        $vcs->repositoryUrlResult = 'github.com/acme/recipes';
        $vcs->rawFileUrlResult = 'https://api.github.com/repos/acme/recipes/contents/PLACEHOLDER?ref=flex/main';

        $result = $this->runCommand($vcs, ["040000 tree deadbeefdeadbeefdeadbeefdeadbeefdeadbeef\tacme/private-bundle/1.0\n"]);

        $this->assertSame(0, $result['exitCode']);
        $index = json_decode(file_get_contents($result['outputDir'].'/index.json'), true);
        $this->assertSame('github.com/acme/recipes', $index['_links']['repository']);
        $this->assertSame('{package}:{version}@github.com/acme/recipes:main', $index['_links']['origin_template']);
        $this->assertSame($vcs->rawFileUrlResult, $index['_links']['recipe_template']);
        $this->assertSame('{package_dotted}.{version}.json', $index['_links']['recipe_template_relative']);
        $this->assertSame($vcs->rawFileUrlResult, $index['_links']['archived_recipes_template']);
        $this->assertSame('archived/{package_dotted}/{ref}.json', $index['_links']['archived_recipes_template_relative']);
        $this->assertFalse($index['is_contrib']);
        $this->assertSame(['acme/private-bundle' => ['1.0']], $index['recipes']);
    }

    public function testGeneratedPackageManifestStripsSchemaAndAliases(): void
    {
        $this->filesystem->mkdir($this->recipeDir.'/acme/private-bundle/1.0/config');
        file_put_contents($this->recipeDir.'/acme/private-bundle/1.0/config/acme.yaml', "acme: ~\n");
        file_put_contents($this->recipeDir.'/acme/private-bundle/1.0/manifest.json', json_encode([
            '$schema' => 'https://example.test/manifest.schema.json',
            'aliases' => ['acme'],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/'],
        ]));

        $result = $this->runCommand(new FakeVcsProvider(new RecordingErrorReporter()), [
            "040000 tree deadbeefdeadbeefdeadbeefdeadbeefdeadbeef\tacme/private-bundle/1.0\n",
        ]);

        $this->assertSame(0, $result['exitCode']);
        $written = json_decode(file_get_contents($result['outputDir'].'/acme.private-bundle.1.0.json'), true);
        $writtenManifest = $written['manifests']['acme/private-bundle']['manifest'];
        $this->assertArrayNotHasKey('$schema', $writtenManifest);
        $this->assertArrayNotHasKey('aliases', $writtenManifest);
        $this->assertArrayHasKey('copy-from-recipe', $writtenManifest);
    }

    public function testRecipeWithOnlySchemaAndAliasesIsNotRegistered(): void
    {
        // Once $schema/aliases are stripped, this manifest has nothing left — the recipe is
        // correctly treated as not worth publishing.
        $this->filesystem->mkdir($this->recipeDir.'/acme/private-bundle/1.0');
        file_put_contents($this->recipeDir.'/acme/private-bundle/1.0/manifest.json', json_encode([
            '$schema' => 'https://example.test/manifest.schema.json',
            'aliases' => ['acme'],
        ]));

        $result = $this->runCommand(new FakeVcsProvider(new RecordingErrorReporter()), [
            "040000 tree deadbeefdeadbeefdeadbeefdeadbeefdeadbeef\tacme/private-bundle/1.0\n",
        ]);

        $this->assertSame(0, $result['exitCode']);
        $index = json_decode(file_get_contents($result['outputDir'].'/index.json'), true);
        $this->assertSame([], $index['recipes']);
        $this->assertFileDoesNotExist($result['outputDir'].'/acme.private-bundle.1.0.json');
    }
}
