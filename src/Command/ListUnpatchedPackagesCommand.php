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

use App\Vcs\VcsProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'list-unpatched-packages', description: 'Lists packages that are *not* patched by the PR/MR')]
class ListUnpatchedPackagesCommand extends Command
{
    public function __construct(private VcsProvider $vcsProvider)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $patchedPackages = [];
        foreach ($this->vcsProvider->getChangedFiles() as $file) {
            $parts = explode('/', $file, 3);
            if (\count($parts) >= 2) {
                $patchedPackages[$parts[0].'/'.$parts[1]] = true;
            }
        }

        foreach (glob('*/*') as $package) {
            if (!isset($patchedPackages[$package])) {
                $output->writeln($package);
            }
        }

        return Command::SUCCESS;
    }
}
