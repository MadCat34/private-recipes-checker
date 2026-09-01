<?php

namespace App\Tests\Command;

use App\Command\GenerateRecipesReadmeCommand;
use App\Registry\PackagistProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateRecipesReadmeCommandTest extends TestCase
{
    private string $indexPath;

    protected function setUp(): void
    {
        $this->indexPath = tempnam(sys_get_temp_dir(), 'index-');
        file_put_contents($this->indexPath, json_encode([
            'aliases' => [],
            'recipes' => ['acme/private-bundle' => ['1.0', '2.0']],
        ]));
    }

    protected function tearDown(): void
    {
        unlink($this->indexPath);
    }

    private function runCommand(?string $headerOverride = null): string
    {
        $command = new GenerateRecipesReadmeCommand(PackagistProvider::fromConfig([]), $headerOverride);
        $tester = new CommandTester($command);
        $tester->execute(['index_path' => $this->indexPath]);

        return $tester->getDisplay();
    }

    public function testDefaultHeaderIsUsedWhenNoOverrideIsGiven(): void
    {
        $this->assertStringStartsWith("# List of Recipes\n\n", $this->runCommand());
    }

    public function testCustomHeaderOverridesTheDefault(): void
    {
        $output = $this->runCommand("# Our internal recipes\n\nFor MyCompany bundles.\n");

        $this->assertStringStartsWith("# Our internal recipes\n\nFor MyCompany bundles.\n\n", $output);
    }

    public function testPackageLinkUsesTheRegistryProviderBrowseUrl(): void
    {
        $output = $this->runCommand();

        $this->assertStringContainsString('[acme/private-bundle](https://packagist.org/packages/acme/private-bundle)', $output);
    }

    public function testLatestVersionIsTheLastOneListed(): void
    {
        $output = $this->runCommand();

        $this->assertStringContainsString('[2.0](../../../tree/main/acme/private-bundle/2.0)', $output);
        $this->assertStringNotContainsString('[1.0](../../../tree/main/acme/private-bundle/1.0)', $output);
    }

    public function testNoAliasesColumnWhenThereAreNoAliases(): void
    {
        $output = $this->runCommand();

        $this->assertStringNotContainsString('Aliases', $output);
    }
}
