<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\ErrorReporter;

use Symfony\Component\Console\Output\OutputInterface;

final class GitLabErrorReporter implements ErrorReporter
{
    /** @var list<array{description: string, file: ?string, line: ?int}> */
    private array $errors = [];

    public function __construct(private OutputInterface $output, private string $outputDir)
    {
    }

    public function reportError(string $message, ?string $file = null, ?int $line = null): void
    {
        if (null === $file) {
            $this->output->writeln($message);
        } else {
            $this->output->writeln(sprintf('%s%s: %s', $file, null !== $line ? ':'.$line : '', $message));
        }

        $this->errors[] = ['description' => $message, 'file' => $file, 'line' => $line];
    }

    public function flush(string $commandName): void
    {
        $issues = array_map(function (array $error): array {
            $file = $error['file'] ?? 'manifest';
            $line = $error['line'] ?? 1;

            return [
                'description' => $error['description'],
                'fingerprint' => hash('xxh128', $file.':'.$line.':'.$error['description']),
                'location' => [
                    'path' => $file,
                    'lines' => ['begin' => $line],
                ],
            ];
        }, $this->errors);

        $safeCommandName = str_replace(':', '-', $commandName);
        file_put_contents(
            sprintf('%s/gl-code-quality-report-%s.json', $this->outputDir, $safeCommandName),
            json_encode($issues, \JSON_PRETTY_PRINT)
        );
    }
}
