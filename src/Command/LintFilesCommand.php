<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\ErrorReporter\ErrorReporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'lint:files', description: 'Validates file-level conventions (indentation, extensions, newlines, ...)')]
final class LintFilesCommand extends Command
{
    public function __construct(private ErrorReporter $errorReporter, private ?string $baseDir = null)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseDir = $this->baseDir ?? getcwd();

        $hasErrors = false;
        $hasErrors = $this->checkNoSymlinks($baseDir) || $hasErrors;
        $hasErrors = $this->checkNoYmlExtension($baseDir) || $hasErrors;
        $hasErrors = $this->checkNoGitkeep($baseDir) || $hasErrors;
        $hasErrors = $this->checkIndentation($baseDir) || $hasErrors;
        $hasErrors = $this->checkTrailingNewline($baseDir) || $hasErrors;
        $hasErrors = $this->checkHttpsForSymfonyCom($baseDir) || $hasErrors;
        $hasErrors = $this->checkUnderscoreNotationUnderConfig($baseDir) || $hasErrors;
        $hasErrors = $this->checkNoTildeNulls($baseDir) || $hasErrors;
        $hasErrors = $this->checkNoConsoleInMakefile($baseDir) || $hasErrors;
        $hasErrors = $this->checkManifestJsonExists($baseDir) || $hasErrors;
        $hasErrors = $this->checkJsonFilesAreValid($baseDir) || $hasErrors;
        $hasErrors = $this->checkNoParametersKeyInPackagesConfig($baseDir) || $hasErrors;

        $this->errorReporter->flush($this->getName());

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Every check goes through here so an exclusion is added once, not twelve times. Dependency
     * directories are not recipe content; linting them produced hundreds of false positives.
     *
     * Deliberately not ignoreVCSIgnored(): a recipes repository's .gitignore may legitimately
     * cover files we still want linted, and the lint's behavior should not depend on it.
     */
    private function finder(string $baseDir): Finder
    {
        return (new Finder())
            ->in($baseDir)
            ->exclude(['vendor', 'node_modules'])
        ;
    }

    private function checkNoSymlinks(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir) as $fileInfo) {
            if (is_link($fileInfo->getPathname())) {
                $this->errorReporter->reportError('Symlinks are not allowed', $fileInfo->getRelativePathname());
                $hasErrors = true;
            }
        }

        return $hasErrors;
    }

    private function checkNoYmlExtension(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->files()->name('*.yml') as $file) {
            $this->errorReporter->reportError('*.yaml files should be used instead of *.yml', $file->getRelativePathname());
            $hasErrors = true;
        }

        return $hasErrors;
    }

    private function checkNoGitkeep(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->ignoreDotFiles(false)->files()->name('.gitkeep') as $file) {
            $this->errorReporter->reportError('.gitkeep files should be renamed to .gitignore', $file->getRelativePathname());
            $hasErrors = true;
        }

        return $hasErrors;
    }

    private function checkIndentation(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->files()->name(['*.yaml', '*.json']) as $file) {
            foreach (explode("\n", $file->getContents()) as $i => $line) {
                if (!preg_match('/^((    )*[^ \t]|$)/', $line)) {
                    $this->errorReporter->reportError('Indentation must be a multiple of 4 spaces', $file->getRelativePathname(), $i + 1);
                    $hasErrors = true;
                }
            }
        }

        return $hasErrors;
    }

    private function checkTrailingNewline(string $baseDir): bool
    {
        $hasErrors = false;
        $extensions = ['*.yaml', '*.yml', '*.txt', '*.md', '*.markdown', '*.json', '*.rst', '*.php', '*.js', '*.css', '*.twig'];
        foreach ($this->finder($baseDir)->files()->name($extensions) as $file) {
            $contents = $file->getContents();
            if ('' !== $contents && "\n" !== substr($contents, -1)) {
                $line = substr_count($contents, "\n") + 1;
                $this->errorReporter->reportError('Should end with a newline', $file->getRelativePathname(), $line);
                $hasErrors = true;
            }
        }

        return $hasErrors;
    }

    private function checkHttpsForSymfonyCom(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->files() as $file) {
            foreach (explode("\n", $file->getContents()) as $i => $line) {
                if (preg_match('{http://.*symfony\.com}', $line)) {
                    $this->errorReporter->reportError('Use https when referencing symfony.com', $file->getRelativePathname(), $i + 1);
                    $hasErrors = true;
                }
            }
        }

        return $hasErrors;
    }

    private function checkUnderscoreNotationUnderConfig(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->files() as $file) {
            $relative = $file->getRelativePathname();
            if (!preg_match('{^[^/]+/[^/]+/[^/]+/config/}', $relative)) {
                continue;
            }
            if (!preg_match('{^[^/]+/[^/]+/[^/]+/config/[0-9a-z_./]+$}', $relative)) {
                $this->errorReporter->reportError('Underscore notation is required for file and directory names under config/', $relative);
                $hasErrors = true;
            }
        }

        return $hasErrors;
    }

    private function checkNoTildeNulls(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->files()->name(['*.yaml', '*.yml']) as $file) {
            foreach (explode("\n", $file->getContents()) as $i => $line) {
                if (str_contains($line, ': ~')) {
                    $this->errorReporter->reportError('"~" should be replaced with "null"', $file->getRelativePathname(), $i + 1);
                    $hasErrors = true;
                }
            }
        }

        return $hasErrors;
    }

    private function checkNoConsoleInMakefile(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->files()->name('Makefile') as $file) {
            foreach (explode("\n", $file->getContents()) as $i => $line) {
                if (preg_match('{bin/console|\$\(CONSOLE\)}', $line)) {
                    $this->errorReporter->reportError('Symfony commands should not be wrapped in a Makefile', $file->getRelativePathname(), $i + 1);
                    $hasErrors = true;
                }
            }
        }

        return $hasErrors;
    }

    private function checkManifestJsonExists(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->directories()->depth('== 2') as $dir) {
            // Depth alone is not enough: generated trees such as flex-endpoint/archived/<pkg>/
            // also sit at depth 2. A recipe's third segment is a version directory ("1.0"), which
            // is what tells one apart — the same "x.y" shape lint:packages enforces.
            if (!preg_match('{^[^/]+/[^/]+/\d+\.\d+$}', $dir->getRelativePathname())) {
                continue;
            }

            if (!is_file($dir->getPathname().'/manifest.json')) {
                $this->errorReporter->reportError('Recipes must define a "manifest.json" file', $dir->getRelativePathname());
                $hasErrors = true;
            }
        }

        return $hasErrors;
    }

    private function checkJsonFilesAreValid(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->files()->name('*.json') as $file) {
            json_decode($file->getContents());
            if (\JSON_ERROR_NONE !== json_last_error()) {
                $this->errorReporter->reportError(sprintf('File is not valid JSON: %s', json_last_error_msg()), $file->getRelativePathname());
                $hasErrors = true;
            }
        }

        return $hasErrors;
    }

    private function checkNoParametersKeyInPackagesConfig(string $baseDir): bool
    {
        $hasErrors = false;
        foreach ($this->finder($baseDir)->files()->name(['*.yaml', '*.yml']) as $file) {
            if (!preg_match('{^[^/]+/[^/]+/[^/]+/config/packages/}', $file->getRelativePathname())) {
                continue;
            }
            foreach (explode("\n", $file->getContents()) as $i => $line) {
                if (preg_match('{^parameters:}', $line)) {
                    $this->errorReporter->reportError('"parameters" should be defined via the "container" configurator instead', $file->getRelativePathname(), $i + 1);
                    $hasErrors = true;
                }
            }
        }

        return $hasErrors;
    }
}
