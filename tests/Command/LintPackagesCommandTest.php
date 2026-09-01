<?php

namespace App\Tests\Command;

use App\Command\LintPackagesCommand;
use App\Registry\PackageRegistryProvider;
use App\Registry\PackagistProvider;
use App\Tests\Support\RecordingErrorReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FakeRegistryProvider implements PackageRegistryProvider
{
    public static function fromConfig(array $config): static
    {
        return new static();
    }

    public function getMetadataUrl(string $package, bool $dev = false): string
    {
        return "https://fake-registry.example/p2/$package".($dev ? '~dev' : '').'.json';
    }

    public function getAuthHeaders(): array
    {
        return ['Authorization' => 'Bearer fake-token'];
    }

    public function getPackageBrowseUrl(string $package): string
    {
        return "https://fake-registry.example/packages/$package";
    }
}

class LintPackagesCommandTest extends TestCase
{
    private string $fixtureDir;
    private string $originalCwd;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->fixtureDir = sys_get_temp_dir().'/lint-packages-test-'.uniqid();
        $this->filesystem->mkdir($this->fixtureDir);
        $this->originalCwd = getcwd();
        chdir($this->fixtureDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->filesystem->remove($this->fixtureDir);
    }

    private function packagistPackageResponse(array $versions): string
    {
        return json_encode(['packages' => ['acme/private-bundle' => $versions]]);
    }

    public function testPackageMissingFromTheRegistryIsRejected(): void
    {
        $this->filesystem->mkdir($this->fixtureDir.'/acme/private-bundle/1.0');

        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 404]));
        $reporter = new RecordingErrorReporter();
        $command = new LintPackagesCommand(PackagistProvider::fromConfig([]), $reporter, $httpClient);
        (new CommandTester($command))->execute([]);

        $this->assertNotEmpty($reporter->errors);
        $this->assertStringContainsString('does not exist', $reporter->errors[0]['message']);
    }

    public function testValidPackageAndVersionProduceNoErrors(): void
    {
        $this->filesystem->mkdir($this->fixtureDir.'/acme/private-bundle/1.0');
        file_put_contents(
            $this->fixtureDir.'/acme/private-bundle/1.0/manifest.json',
            json_encode(['bundles' => ['Acme\\PrivateBundle\\AcmeBundle' => ['all']]])
        );

        $httpClient = new MockHttpClient(new MockResponse($this->packagistPackageResponse([
            ['version_normalized' => '1.0.0.0', 'require' => [], 'type' => 'symfony-bundle'],
        ])));
        $reporter = new RecordingErrorReporter();
        $command = new LintPackagesCommand(PackagistProvider::fromConfig([]), $reporter, $httpClient);
        (new CommandTester($command))->execute([]);

        $this->assertSame([], $reporter->errors);
    }

    public function testDependingOnSymfonySymfonyIsRejected(): void
    {
        $this->filesystem->mkdir($this->fixtureDir.'/acme/private-bundle/1.0');

        $httpClient = new MockHttpClient(new MockResponse($this->packagistPackageResponse([
            ['version_normalized' => '1.0.0.0', 'require' => ['symfony/symfony' => '*'], 'type' => 'library'],
        ])));
        $reporter = new RecordingErrorReporter();
        $command = new LintPackagesCommand(PackagistProvider::fromConfig([]), $reporter, $httpClient);
        (new CommandTester($command))->execute([]);

        $this->assertNotEmpty(array_filter($reporter->errors, fn (array $e) => str_contains($e['message'], 'must not depend on symfony/symfony')));
    }

    public function testSymfonyBundleTypeWithoutARegisteredBundleIsRejected(): void
    {
        $this->filesystem->mkdir($this->fixtureDir.'/acme/private-bundle/1.0');
        file_put_contents($this->fixtureDir.'/acme/private-bundle/1.0/manifest.json', json_encode(['container' => ['x' => 1]]));

        $httpClient = new MockHttpClient(new MockResponse($this->packagistPackageResponse([
            ['version_normalized' => '1.0.0.0', 'require' => [], 'type' => 'symfony-bundle'],
        ])));
        $reporter = new RecordingErrorReporter();
        $command = new LintPackagesCommand(PackagistProvider::fromConfig([]), $reporter, $httpClient);
        (new CommandTester($command))->execute([]);

        $this->assertNotEmpty(array_filter($reporter->errors, fn (array $e) => str_contains($e['message'], 'register the bundle')));
    }

    public function testTheMetadataUrlComesFromTheInjectedRegistryProviderNotAHardcodedOne(): void
    {
        $this->filesystem->mkdir($this->fixtureDir.'/acme/private-bundle/1.0');

        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl) {
            $requestedUrl = $url;

            return new MockResponse('', ['http_code' => 404]);
        });

        $command = new LintPackagesCommand(new FakeRegistryProvider(), new RecordingErrorReporter(), $httpClient);
        (new CommandTester($command))->execute([]);

        $this->assertSame('https://fake-registry.example/p2/acme/private-bundle.json', $requestedUrl);
    }
}
