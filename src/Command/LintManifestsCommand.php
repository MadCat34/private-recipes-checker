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
use App\Manifest\ManifestValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'lint:manifests', description: 'Checks manifest.json files')]
class LintManifestsCommand extends Command
{
    /**
     * Used only to decide whether a recipe is "empty" (not worth keeping) — not for structural
     * validation, which is delegated entirely to ManifestValidator/resources/manifest.schema.json.
     * "bundles" needs more than one entry to count as meaningful content on its own; every other
     * key just needs to be non-empty.
     */
    private const EMPTY_CHECK_KEYS = [
        'bundles' => 1,
        'copy-from-recipe' => 0,
        'copy-from-package' => 0,
        'composer-scripts' => 0,
        'composer-commands' => 0,
        'dotenv' => 0,
        'env' => 0,
        'makefile' => 0,
        'gitignore' => 0,
        'post-install-output' => 0,
        'aliases' => 0,
        'container' => 0,
        'conflict' => 0,
        'dockerfile' => 0,
        'docker-compose' => 0,
        'add-lines' => 0,
    ];

    private const SPECIAL_FILES = ['.', '..', 'manifest.json', 'post-install.txt', 'Makefile'];

    public function __construct(
        private ErrorReporter $errorReporter,
        private ManifestValidator $manifestValidator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hasErrors = false;
        $aliases = [];

        foreach (glob('*/*/*/manifest.json') as $manifest) {
            [$vendor, $package, $version] = explode('/', $manifest);
            $package = "$vendor/$package";
            $manifestJson = file_get_contents($manifest);
            $data = json_decode($manifestJson, true);

            foreach ($this->manifestValidator->validate($manifestJson) as $schemaError) {
                $this->errorReporter->reportError($schemaError, $manifest);
                $hasErrors = true;
            }

            $empty = true;
            foreach (self::EMPTY_CHECK_KEYS as $key => $count) {
                if ($count ? \count($data[$key] ?? []) > $count : !empty($data[$key])) {
                    $empty = false;
                    break;
                }
            }
            if ($empty && !is_file("$package/$version/post-install.txt") && ['all'] === current($data['bundles'] ?? [])) {
                $this->errorReporter->reportError('Recipe is not needed as it only registers a bundle for all environments', $manifest);
                continue;
            }

            foreach ($data['aliases'] ?? [] as $alias) {
                if (\in_array($alias, ['lock', 'nothing', 'mirrors', ''], true)) {
                    $this->errorReporter->reportError(sprintf('Alias "%s" cannot be used as it\'s a special alias used by Composer', $alias), $manifest);
                    $hasErrors = true;
                }
                if (isset($aliases[$alias]) && $package !== $aliases[$alias]) {
                    $this->errorReporter->reportError(sprintf('Alias "%s" also defined for "%s"', $alias, $aliases[$alias]), $manifest);
                    $hasErrors = true;
                } else {
                    $aliases[$alias] = $package;
                }
            }

            foreach (scandir("$package/$version") as $file) {
                $path = "$package/$version/$file";

                if (\in_array($file, self::SPECIAL_FILES, true)) {
                    if (is_file($path) && !preg_match('//u', file_get_contents($path))) {
                        $this->errorReporter->reportError(sprintf('File "%s" must be UTF-8 encoded', $file), $path);
                        $hasErrors = true;
                    }
                    continue;
                }

                if (is_dir($path)) {
                    if (isset($data['copy-from-recipe'][$file.'/'])) {
                        // no-op
                    } elseif (isset($data['copy-from-recipe'][$file])) {
                        $this->errorReporter->reportError(sprintf('Directory must be listed under "%s/" in the "copy-from-recipe" section', $file), $manifest);
                        $hasErrors = true;
                    } else {
                        $this->errorReporter->reportError(sprintf('Directory must be listed under "%s/" in the "copy-from-recipe" section of "manifest.json"', $file), $path);
                        $hasErrors = true;
                    }
                } elseif (!isset($data['copy-from-recipe'][$file])) {
                    $this->errorReporter->reportError('File must be listed in the "copy-from-recipe" section of "manifest.json"', $path);
                    $hasErrors = true;
                }
            }
        }

        $this->errorReporter->flush($this->getName());

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
