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

use App\ErrorReporter\ErrorReporter;
use App\Vcs\VcsProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'lint:pull-request', description: 'Ensures the PR/MR can be accepted')]
class LintPullRequestCommand extends Command
{
    public function __construct(
        private VcsProvider $vcsProvider,
        private ErrorReporter $errorReporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('license', null, InputOption::VALUE_REQUIRED, 'The license to check for in the PR/MR body');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hasErrors = false;

        $license = $input->getOption('license');
        if ($license && !preg_match('/^[ |\t]*License[ |\t]+'.preg_quote($license).'[ |\t]*\r?$/mi', $this->vcsProvider->getPullRequestBody())) {
            $this->errorReporter->reportError('Contributions must be licensed under '.$license.' (add the pull request header in the description)');
            $hasErrors = true;
        }

        foreach ($this->vcsProvider->getPullRequestCommits() as $commit) {
            if (1 < $commit['parent_count']) {
                $this->errorReporter->reportError('Pull requests should not have merge commits (please rebase)');
                $hasErrors = true;
                break;
            }
        }

        $this->errorReporter->flush($this->getName());

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
