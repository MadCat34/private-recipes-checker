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

use App\Registry\PackageRegistryProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'generate:recipes-readme', description: 'Generates a "README" containing a list of all recipes.')]
class GenerateRecipesReadmeCommand extends Command
{
    private const DEFAULT_HEADER = "# List of Recipes\n";

    public function __construct(
        private PackageRegistryProvider $registryProvider,
        private ?string $headerOverride = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('index_path', InputArgument::REQUIRED, 'Path to the local index.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexPath = $input->getArgument('index_path');

        if (!file_exists($indexPath)) {
            throw new \InvalidArgumentException(sprintf('Cannot find index JSON file "%s".', $indexPath));
        }

        $data = json_decode(file_get_contents($indexPath), true);

        $aliases = $this->organizeAliases($data['aliases']);
        $hasAliases = count($aliases) > 0;

        $contentLines = explode("\n", rtrim($this->headerOverride ?? self::DEFAULT_HEADER, "\n"));
        $contentLines[] = '';

        // Package | Latest recipe | Aliases
        // [acme/private-bundle](https://packagist) | [1.0](../../../tree/main/.../1.0) | acme
        $contentLines[] = '| Package | Latest Recipe |'.($hasAliases ? ' Aliases |' : '');
        $contentLines[] = '| --- | --- |'.($hasAliases ? ' --- |' : '');
        foreach ($data['recipes'] as $package => $versions) {
            $latestVersion = array_pop($versions);
            $line = sprintf(
                '| [%s](%s) | [%s](../../../tree/main/%s/%s) |',
                $package,
                $this->registryProvider->getPackageBrowseUrl($package),
                $latestVersion,
                $package,
                $latestVersion
            );
            if ($hasAliases) {
                $styledAliases = array_map(function ($alias) {
                    return sprintf('`%s`', $alias);
                }, $aliases[$package] ?? []);
                $line .= sprintf(' %s |', implode(', ', $styledAliases));
            }

            $contentLines[] = $line;
        }

        $output->write(implode("\n", $contentLines));

        return Command::SUCCESS;
    }

    private function organizeAliases(array $aliases): array
    {
        $byPackageAliases = [];
        foreach ($aliases as $alias => $package) {
            if (!isset($byPackageAliases[$package])) {
                $byPackageAliases[$package] = [];
            }

            $byPackageAliases[$package][] = $alias;
        }

        return $byPackageAliases;
    }
}
