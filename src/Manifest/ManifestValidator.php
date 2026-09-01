<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Manifest;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

final class ManifestValidator
{
    private Validator $validator;
    private object $schema;

    public function __construct(string $schemaPath)
    {
        $this->validator = new Validator();
        $this->validator->setMaxErrors(50);
        $this->schema = json_decode(file_get_contents($schemaPath), flags: \JSON_THROW_ON_ERROR);
    }

    /** @return list<string> human-readable error messages; empty when the manifest is valid */
    public function validate(string $manifestJson): array
    {
        $data = json_decode($manifestJson, flags: \JSON_THROW_ON_ERROR);
        $result = $this->validator->validate($data, $this->schema);

        if ($result->isValid()) {
            return [];
        }

        $formatted = (new ErrorFormatter())->format($result->error());

        $messages = [];
        foreach ($formatted as $path => $errorsForPath) {
            foreach ($errorsForPath as $message) {
                $messages[] = '' === $path ? $message : sprintf('%s: %s', $path, $message);
            }
        }

        return $messages;
    }
}
