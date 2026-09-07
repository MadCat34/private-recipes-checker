<?php

namespace App\Tests\Command;

use App\Command\GenerateArchivedRecipesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class GenerateArchivedRecipesCommandTest extends TestCase
{
    /** @param list<array{0: list<string>}> $commits each entry: files to add for that commit, as [relativePath => contents] */
    private function initGitRepo(string $repoDir, array $commits): void
    {
        $filesystem = new Filesystem();
        $git = fn (array $args) => (new Process(array_merge(['git'], $args), $repoDir))->mustRun();

        $git(['init', '-q']);
        $git(['checkout', '-q', '-b', 'main']);
        $git(['config', 'user.email', 'test@example.test']);
        $git(['config', 'user.name', 'Test']);

        foreach ($commits as $i => $files) {
            if ([] === $files) {
                $git(['commit', '-q', '--allow-empty', '-m', "Commit $i"]);
                continue;
            }
            foreach ($files as $relativePath => $contents) {
                $filesystem->dumpFile("$repoDir/$relativePath", $contents);
            }
            $git(['add', '.']);
            $git(['commit', '-q', '-m', "Commit $i"]);
        }
    }

    public function testGeneratesArchivedRecipesUsingAnInjectedCheckerRoot(): void
    {
        // Fast, and fully decoupled from generate:flex-endpoint's real behavior (which changes in
        // later tasks): a minimal fake "run" script stands in for the real one. The only thing
        // under test here is that this command shells out to $checkerRoot/run correctly.
        $filesystem = new Filesystem();
        $repoDir = sys_get_temp_dir().'/archived-recipes-test-'.uniqid();
        $checkerRoot = sys_get_temp_dir().'/archived-recipes-checker-'.uniqid();
        $outputDir = sys_get_temp_dir().'/archived-recipes-out-'.uniqid();
        $filesystem->mkdir([$repoDir, $checkerRoot, $outputDir]);

        file_put_contents($checkerRoot.'/run', <<<'PHP'
            <?php
            $outputDir = getenv('OUTPUT_DIR');
            @mkdir($outputDir.'/archived', 0777, true);
            file_put_contents($outputDir.'/archived/marker.json', '{}');
            PHP
        );

        $this->initGitRepo($repoDir, [
            [], // an extra commit, so this exercises the multi-commit walk rather than the single-commit exit
            ['acme/private-bundle/1.0/manifest.json' => json_encode(['container' => ['x' => 1]])],
        ]);

        $tester = new CommandTester(new GenerateArchivedRecipesCommand($checkerRoot));
        $exitCode = $tester->execute(['directory' => $repoDir, 'branch' => 'main', 'output_directory' => $outputDir]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($outputDir.'/marker.json');

        $filesystem->remove([$repoDir, $checkerRoot, $outputDir]);
    }

    public function testTheDefaultCheckerRootPointsAtTheRealProjectRootNotSrc(): void
    {
        // No override: exercises realpath(__DIR__.'/../..') for real, against the project's own
        // run/generate:flex-endpoint. GITHUB_ACTIONS=1 keeps this green across the whole plan —
        // once Tasks 17/19 land, generate:flex-endpoint needs a VcsProvider that doesn't throw on
        // getRepositoryUrl()/getRawFileUrl(); GitHubProvider's are pure string formatting, so
        // faking "we're in GitHub Actions" here is safe and makes no network call.
        putenv('GITHUB_ACTIONS=1');

        $filesystem = new Filesystem();
        $repoDir = sys_get_temp_dir().'/archived-recipes-real-test-'.uniqid();
        $outputDir = sys_get_temp_dir().'/archived-recipes-real-out-'.uniqid();
        $filesystem->mkdir([$repoDir, $outputDir]);

        $this->initGitRepo($repoDir, [
            [],
            ['acme/private-bundle/1.0/manifest.json' => json_encode(['container' => ['x' => 1]])],
        ]);

        $tester = new CommandTester(new GenerateArchivedRecipesCommand());
        $exitCode = $tester->execute(['directory' => $repoDir, 'branch' => 'main', 'output_directory' => $outputDir]);

        putenv('GITHUB_ACTIONS');

        $this->assertSame(0, $exitCode);
        // Archived recipes are mirrored from $checkerRoot's generate:flex-endpoint output, which
        // writes archived/{package_dotted}/{tree_sha}.json — so the mirrored layout under
        // $outputDir is a directory per package, not a flat "package.version.json" file.
        $this->assertNotEmpty(glob($outputDir.'/acme.private-bundle/*.json'));

        $filesystem->remove([$repoDir, $outputDir]);
    }

    public function testRestoresTheStartingBranchOnSuccess(): void
    {
        $filesystem = new Filesystem();
        $repoDir = sys_get_temp_dir().'/archived-restore-test-'.uniqid();
        $checkerRoot = sys_get_temp_dir().'/archived-restore-checker-'.uniqid();
        $outputDir = sys_get_temp_dir().'/archived-restore-out-'.uniqid();
        $filesystem->mkdir([$repoDir, $checkerRoot, $outputDir]);

        file_put_contents($checkerRoot.'/run', <<<'PHP'
            <?php
            $outputDir = getenv('OUTPUT_DIR');
            @mkdir($outputDir.'/archived', 0777, true);
            file_put_contents($outputDir.'/archived/marker.json', '{}');
            PHP
        );

        $this->initGitRepo($repoDir, [
            [],
            ['acme/private-bundle/1.0/manifest.json' => json_encode(['container' => ['x' => 1]])],
        ]);

        $tester = new CommandTester(new GenerateArchivedRecipesCommand($checkerRoot));
        $tester->execute(['directory' => $repoDir, 'branch' => 'main', 'output_directory' => $outputDir]);

        $branch = (new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $repoDir))->mustRun()->getOutput();
        $this->assertSame('main', trim($branch), 'the command left the repository on a detached HEAD');

        $filesystem->remove([$repoDir, $checkerRoot, $outputDir]);
    }

    public function testHandlesARepositoryWithASingleCommit(): void
    {
        $filesystem = new Filesystem();
        $repoDir = sys_get_temp_dir().'/archived-single-test-'.uniqid();
        $checkerRoot = sys_get_temp_dir().'/archived-single-checker-'.uniqid();
        $outputDir = sys_get_temp_dir().'/archived-single-out-'.uniqid();
        $filesystem->mkdir([$repoDir, $checkerRoot, $outputDir]);

        file_put_contents($checkerRoot.'/run', <<<'PHP'
            <?php
            $outputDir = getenv('OUTPUT_DIR');
            @mkdir($outputDir.'/archived', 0777, true);
            file_put_contents($outputDir.'/archived/marker.json', '{}');
            PHP
        );

        // A single commit: the loop must not attempt HEAD^1, which does not exist.
        $this->initGitRepo($repoDir, [
            ['acme/private-bundle/1.0/manifest.json' => json_encode(['container' => ['x' => 1]])],
        ]);

        $tester = new CommandTester(new GenerateArchivedRecipesCommand($checkerRoot));
        $exitCode = $tester->execute(['directory' => $repoDir, 'branch' => 'main', 'output_directory' => $outputDir]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($outputDir.'/marker.json');

        $filesystem->remove([$repoDir, $checkerRoot, $outputDir]);
    }

    public function testRefusesToRunAgainstADirtyWorkingTree(): void
    {
        $filesystem = new Filesystem();
        $repoDir = sys_get_temp_dir().'/archived-dirty-test-'.uniqid();
        $outputDir = sys_get_temp_dir().'/archived-dirty-out-'.uniqid();
        $filesystem->mkdir([$repoDir, $outputDir]);

        $this->initGitRepo($repoDir, [
            [],
            ['acme/private-bundle/1.0/manifest.json' => json_encode(['container' => ['x' => 1]])],
        ]);

        // Uncommitted work the initial `git checkout` would silently discard.
        file_put_contents($repoDir.'/acme/private-bundle/1.0/manifest.json', json_encode(['container' => ['x' => 999]]));

        $tester = new CommandTester(new GenerateArchivedRecipesCommand());

        try {
            $tester->execute(['directory' => $repoDir, 'branch' => 'main', 'output_directory' => $outputDir]);
            $this->fail('the command should refuse to run against a dirty working tree');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('uncommitted changes', $e->getMessage());
        }

        // The user's work is untouched.
        $contents = file_get_contents($repoDir.'/acme/private-bundle/1.0/manifest.json');
        $this->assertStringContainsString('999', $contents);

        $filesystem->remove([$repoDir, $outputDir]);
    }
}
