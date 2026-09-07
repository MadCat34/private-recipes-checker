<?php

/*
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Modified by madcat34 for the Private Recipe Checker fork, 2026.
 * See CHANGELOG.md and the git history for details.
 */

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'generate:archived-recipes', description: 'Generates an "archived" directory containing the history of every recipe.')]
class GenerateArchivedRecipesCommand extends Command
{
    public function __construct(private ?string $checkerRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::REQUIRED, 'Path to the local recipes repository')
            ->addArgument('branch', InputArgument::REQUIRED, 'Branch on the recipes repository to use')
            ->addArgument('output_directory', InputArgument::REQUIRED, 'The directory where generated files should be stored')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $recipesDirectory = $input->getArgument('directory');
        $branch = $input->getArgument('branch');
        $outputDir = $input->getArgument('output_directory');
        $checkerRoot = $this->checkerRoot ?? realpath(__DIR__.'/../..');
        $filesystem = new Filesystem();

        if (!file_exists($recipesDirectory)) {
            throw new \InvalidArgumentException(sprintf('Cannot find directory "%s"', $recipesDirectory));
        }

        // This command checks out every commit of $recipesDirectory in turn, so uncommitted work
        // there would be silently discarded by the very first checkout. Refuse rather than destroy.
        $status = (new Process(['git', 'status', '--porcelain'], $recipesDirectory))->mustRun();
        if ('' !== trim($status->getOutput())) {
            throw new \RuntimeException(sprintf('The repository at "%s" has uncommitted changes. Commit or stash them first: this command checks out every commit in turn and would discard them.', $recipesDirectory));
        }

        // Remembered so the finally below can put the repository back where the user left it —
        // the loop walks history with detaching checkouts and would otherwise strand it there.
        $startingRef = trim((new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $recipesDirectory))->mustRun()->getOutput());

        $process = new Process(['git', 'checkout', $branch], $recipesDirectory);
        $process->mustRun();

        $tmpDir = sys_get_temp_dir().'/_flex_archive/';
        if (file_exists($tmpDir)) {
            $filesystem->remove($tmpDir);
        }
        $filesystem->mkdir($tmpDir);

        $process = (new Process(['git', 'rev-list', '--count', $branch, '--no-merges'], $recipesDirectory))->mustRun();
        // an imperfect estimate of the total commits
        $totalCommits = (int) trim($process->getOutput());
        $progress = new ProgressBar($output, $totalCommits);

        try {
            while (true) {
                // most arguments to the command do not matter for us and so are hardcoded
                $process = Process::fromShellCommandline(
                    sprintf('git ls-tree HEAD */*/* | php %s generate:flex-endpoint symfony/recipes master flex/main $OUTPUT_DIR', escapeshellarg($checkerRoot.'/run')),
                    $recipesDirectory
                );
                // this WILL occasionally fail: some legacy recipes were invalid and pointed to non-existent files
                $process->run(null, ['OUTPUT_DIR' => $tmpDir]);

                // Checked before descending: on the root commit HEAD^1 does not exist, and the
                // old order tried the checkout first, surfacing a raw git pathspec error.
                $process = (new Process(['git', 'rev-list', '--count', 'HEAD', '--no-merges'], $recipesDirectory))->mustRun();
                $remainingCommits = (int) trim($process->getOutput());
                if ($remainingCommits <= 1) {
                    break;
                }

                $process = new Process(['git', 'checkout', 'HEAD^1'], $recipesDirectory);
                $process->mustRun();

                $progress->setProgress($totalCommits - $remainingCommits);
            }
        } finally {
            // Covers the exception path too: a failed checkout mid-walk must not leave the user's
            // repository detached on some arbitrary commit.
            (new Process(['git', 'checkout', $startingRef], $recipesDirectory))->run();
        }

        $filesystem->mirror($tmpDir.'/archived', $outputDir);
        $progress->finish();
        $filesystem->remove($tmpDir);

        return 0;
    }
}
