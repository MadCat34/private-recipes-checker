<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class RunTest extends TestCase
{
    public function testRunListShowsAllTenCommands(): void
    {
        $process = new Process(['php', __DIR__.'/../run', 'list', '--format=txt']);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        foreach ([
            'lint:files', 'lint:manifests', 'lint:packages', 'lint:pull-request', 'lint:yaml',
            'list-unpatched-packages', 'generate:flex-endpoint', 'generate:recipes-readme',
            'generate:archived-recipes', 'diff-recipe-versions',
        ] as $commandName) {
            $this->assertStringContainsString($commandName, $process->getOutput());
        }
    }

    public function testRunWorksOutsideAnyCiEnvironment(): void
    {
        // No GITHUB_ACTIONS/GITLAB_CI reaching the subprocess: exercises NullVcsProvider and the
        // default Packagist registry (no .recipes-checker.yaml in the checker's own repo root).
        // The command finds no recipes here (this is the checker's own repo, not a recipes repo)
        // and simply exits 0 — the point of this test is that bootstrap doesn't crash without CI.
        $process = new Process(['php', __DIR__.'/../run', 'lint:manifests'], null, ['GITHUB_ACTIONS' => null, 'GITLAB_CI' => null]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function testRunFailsClearlyOnAnUnknownRegistryTypeButStillAnswersHelp(): void
    {
        $configDir = sys_get_temp_dir().'/run-test-'.uniqid();
        mkdir($configDir);
        file_put_contents($configDir.'/.recipes-checker.yaml', "registry:\n    type: bogus\n");

        // --help must still work: UnavailableRegistryProvider defers the error until a real call.
        $helpProcess = new Process(['php', __DIR__.'/../run', 'lint:packages', '--help'], $configDir);
        $helpProcess->run();
        $this->assertTrue($helpProcess->isSuccessful(), $helpProcess->getErrorOutput());

        // Actually running the command must fail with the configuration error.
        $runProcess = new Process(['php', __DIR__.'/../run', 'lint:packages'], $configDir);
        $runProcess->run();
        $this->assertFalse($runProcess->isSuccessful());
        $this->assertStringContainsString('Unknown registry type "bogus"', $runProcess->getErrorOutput().$runProcess->getOutput());

        (new \Symfony\Component\Filesystem\Filesystem())->remove($configDir);
    }
}
