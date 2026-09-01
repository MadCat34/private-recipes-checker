<?php

namespace App\Tests\Support;

use App\ErrorReporter\ErrorReporter;

final class RecordingErrorReporter implements ErrorReporter
{
    /** @var list<array{message: string, file: ?string, line: ?int}> */
    public array $errors = [];
    public ?string $flushedCommandName = null;

    public function reportError(string $message, ?string $file = null, ?int $line = null): void
    {
        $this->errors[] = ['message' => $message, 'file' => $file, 'line' => $line];
    }

    public function flush(string $commandName): void
    {
        $this->flushedCommandName = $commandName;
    }
}
