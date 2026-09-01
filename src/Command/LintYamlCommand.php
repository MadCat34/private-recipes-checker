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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'lint:yaml', description: 'Validates the content of yaml files')]
class LintYamlCommand extends Command
{
    /** @param resource $inputStream defaults to real stdin; overridable so tests can inject a fake stream */
    public function __construct(
        private ErrorReporter $errorReporter,
        private $inputStream = \STDIN,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hasErrors = false;

        while (false !== $file = fgets($this->inputStream)) {
            $file = substr($file, 0, -1);
            $hasErrors = $this->validate($file) || $hasErrors;
        }

        $this->errorReporter->flush($this->getName());

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    private function validate(string $file): bool
    {
        $parser = new Parser();
        $content = file_get_contents($file);

        $prevErrorHandler = set_error_handler(function ($level, $message, $file, $line) use (&$prevErrorHandler, $parser) {
            if (\E_USER_DEPRECATED === $level) {
                throw new ParseException($message, $parser->getRealCurrentLineNb() + 1);
            }

            return $prevErrorHandler ? $prevErrorHandler($level, $message, $file, $line) : false;
        });

        try {
            $data = $parser->parse($content, Yaml::PARSE_CONSTANT | Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException $e) {
            $this->errorReporter->reportError($e->getMessage(), $file, $e->getParsedLine());

            return true;
        } finally {
            restore_error_handler();
        }

        if (null === $data || !preg_match('{^[^/]+/[^/]+/[^/]+/config/packages/}', $file)) {
            return false;
        }

        if (!\is_array($data)) {
            $this->errorReporter->reportError('A configuration array is expected', $file);

            return true;
        }

        $hasErrors = false;
        foreach ($data as $k => $v) {
            if (!\in_array($v, ['', null, []], true)) {
                continue;
            }

            $quotedKey = preg_quote($k);
            $line = null;
            foreach (file($file) as $i => $fileLine) {
                if (preg_match("{^$quotedKey\s*:}", $fileLine)) {
                    $line = 1 + $i;
                    break;
                }
            }

            $this->errorReporter->reportError(sprintf('"%s" entry should be removed as it is empty', $k), $file, $line);
            $hasErrors = true;
        }

        return $hasErrors;
    }
}
