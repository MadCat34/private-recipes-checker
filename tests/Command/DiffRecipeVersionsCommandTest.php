<?php

namespace App\Tests\Command;

use App\Command\DiffRecipeVersionsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class DiffRecipeVersionsCommandTest extends TestCase
{
    private string $fixtureDir;
    private string $originalCwd;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->fixtureDir = sys_get_temp_dir().'/diff-recipe-versions-test-'.uniqid();
        $this->filesystem->mkdir($this->fixtureDir);
        $this->originalCwd = getcwd();
        chdir($this->fixtureDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->filesystem->remove($this->fixtureDir);
    }

    /** @param list<string> $lines package paths, as the command reads them from stdin */
    private function streamOf(array $lines)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, implode("\n", $lines)."\n");
        rewind($stream);

        return $stream;
    }

    public function testSinglePackageVersionProducesNoDiffSection(): void
    {
        // One version means nothing to compare: the command still greets, but emits no "###"
        // package section.
        $this->filesystem->mkdir($this->fixtureDir.'/acme/private-bundle/1.0');

        $tester = new CommandTester(new DiffRecipeVersionsCommand($this->streamOf(['acme/private-bundle'])));
        $exitCode = $tester->execute(['endpoint' => '']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Thanks for the PR', $tester->getDisplay());
        $this->assertStringNotContainsString('### acme/private-bundle', $tester->getDisplay());
    }

    public function testTwoVersionsProduceADiffSection(): void
    {
        $this->filesystem->dumpFile($this->fixtureDir.'/acme/private-bundle/1.0/manifest.json', "{\n    \"a\": 1\n}\n");
        $this->filesystem->dumpFile($this->fixtureDir.'/acme/private-bundle/1.1/manifest.json', "{\n    \"a\": 2\n}\n");

        $tester = new CommandTester(new DiffRecipeVersionsCommand($this->streamOf(['acme/private-bundle'])));
        $exitCode = $tester->execute(['endpoint' => '']);

        $display = $tester->getDisplay();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('### acme/private-bundle', $display);
        $this->assertStringContainsString('1.0 <em>vs</em> 1.1', $display);
        $this->assertStringContainsString('```diff', $display);
    }

    public function testAnEndpointArgumentAddsTheHowToTestSection(): void
    {
        $this->filesystem->mkdir($this->fixtureDir.'/acme/private-bundle/1.0');

        $tester = new CommandTester(new DiffRecipeVersionsCommand($this->streamOf(['acme/private-bundle'])));
        $tester->execute(['endpoint' => 'https://example.test/index.json']);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('How to test these changes in your application', $display);
        $this->assertStringContainsString('SYMFONY_ENDPOINT=https://example.test/index.json', $display);
        // The composer req line quotes each package:^version pair via escapeshellarg().
        $this->assertStringContainsString("'acme/private-bundle:^1.0'", $display);
    }
}
