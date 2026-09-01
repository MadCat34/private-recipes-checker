<?php

namespace App\Tests\Manifest;

use App\Manifest\ManifestValidator;
use PHPUnit\Framework\TestCase;

class ManifestValidatorTest extends TestCase
{
    private ManifestValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ManifestValidator(__DIR__.'/../../resources/manifest.schema.json');
    }

    public function testEmptyManifestIsValid(): void
    {
        $this->assertSame([], $this->validator->validate('{}'));
    }

    public function testUnknownTopLevelKeyIsRejected(): void
    {
        $errors = $this->validator->validate('{"not-a-real-key": true}');

        $this->assertNotEmpty($errors);
    }

    public function testSchemaKeyIsAcceptedAndDoesNotCountAsAnUnknownKey(): void
    {
        // Regression: with additionalProperties:false and no explicit "$schema" declaration,
        // a manifest.json using the IDE-support differentiator would fail its own validation.
        $this->assertSame([], $this->validator->validate('{"$schema": "https://example.test/manifest.schema.json"}'));
    }

    public function testComposerCommandsIsAccepted(): void
    {
        // Regression: the upstream tool rejects this key even though Flex documents and supports
        // it (missing from its hand-written ALLOWED_KEYS list).
        $errors = $this->validator->validate('{"composer-commands": {"test": "bin/phpunit"}}');

        $this->assertSame([], $errors);
    }

    public function testBundlesRejectsAnUnknownEnvironment(): void
    {
        $errors = $this->validator->validate('{"bundles": {"Acme\\\\Bundle\\\\AcmeBundle": ["staging"]}}');

        $this->assertNotEmpty($errors);
    }

    public function testBundlesAcceptsKnownEnvironments(): void
    {
        $errors = $this->validator->validate('{"bundles": {"Acme\\\\Bundle\\\\AcmeBundle": ["dev", "test"]}}');

        $this->assertSame([], $errors);
    }

    public function testAddLinesRequiresTargetWhenPositionIsAfterTarget(): void
    {
        $manifest = '{"add-lines": [{"file": "a.js", "content": "x", "position": "after_target"}]}';

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
    }

    public function testAddLinesIsValidWithTargetWhenPositionIsAfterTarget(): void
    {
        $manifest = '{"add-lines": [{"file": "a.js", "content": "x", "position": "after_target", "target": ".foo()"}]}';

        $this->assertSame([], $this->validator->validate($manifest));
    }

    public function testAddLinesDoesNotRequireTargetForTopOrBottom(): void
    {
        $manifest = '{"add-lines": [{"file": "a.js", "content": "x", "position": "top"}]}';

        $this->assertSame([], $this->validator->validate($manifest));
    }

    public function testFullExampleFromTheReadmeIsValid(): void
    {
        $manifest = json_encode([
            'bundles' => ['Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle' => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/', 'public/' => '%PUBLIC_DIR%/', 'src/' => '%SRC_DIR%/'],
            'composer-scripts' => ['cache:clear' => 'symfony-cmd', 'assets:install --symlink --relative %PUBLIC_DIR%' => 'symfony-cmd'],
            'env' => ['APP_ENV' => 'dev', 'APP_SECRET' => '%generate(secret)%'],
            'gitignore' => ['.env', '/public/bundles/', '/var/', '/vendor/'],
        ]);

        $this->assertSame([], $this->validator->validate($manifest));
    }
}
