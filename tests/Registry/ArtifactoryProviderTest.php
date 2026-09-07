<?php

namespace App\Tests\Registry;

use App\Registry\ArtifactoryProvider;
use PHPUnit\Framework\TestCase;

class ArtifactoryProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('REGISTRY_TOKEN'); // unset, in case a test set it
    }

    public function testFromConfigRequiresAUrl(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "registry.url" key is required when registry.type is "artifactory".');

        ArtifactoryProvider::fromConfig([]);
    }

    public function testGetMetadataUrlUsesTheDefaultTemplate(): void
    {
        $provider = ArtifactoryProvider::fromConfig([
            'url' => 'https://mycompany.jfrog.io/artifactory/api/composer/my-repo',
            'browse_url_template' => 'https://mycompany.jfrog.io/ui/repos/tree/General/my-repo/%package%',
        ]);

        $this->assertSame(
            'https://mycompany.jfrog.io/artifactory/api/composer/my-repo/p2/acme/private-bundle.json',
            $provider->getMetadataUrl('acme/private-bundle')
        );
    }

    public function testGetMetadataUrlForDevVersions(): void
    {
        $provider = ArtifactoryProvider::fromConfig([
            'url' => 'https://mycompany.jfrog.io/artifactory/api/composer/my-repo',
            'browse_url_template' => 'https://mycompany.jfrog.io/ui/repos/tree/General/my-repo/%package%',
        ]);

        $this->assertSame(
            'https://mycompany.jfrog.io/artifactory/api/composer/my-repo/p2/acme/private-bundle~dev.json',
            $provider->getMetadataUrl('acme/private-bundle', dev: true)
        );
    }

    public function testGetMetadataUrlHonoursACustomTemplate(): void
    {
        $provider = ArtifactoryProvider::fromConfig([
            'url' => 'https://mycompany.jfrog.io/artifactory/api/composer/my-repo',
            'metadata_url_template' => '%url%/composer/p2/%package%.json',
            'browse_url_template' => 'https://mycompany.jfrog.io/ui/repos/tree/General/my-repo/%package%',
        ]);

        $this->assertSame(
            'https://mycompany.jfrog.io/artifactory/api/composer/my-repo/composer/p2/acme/private-bundle.json',
            $provider->getMetadataUrl('acme/private-bundle')
        );
    }

    public function testGetAuthHeadersIsEmptyWithoutRegistryToken(): void
    {
        $provider = ArtifactoryProvider::fromConfig([
            'url' => 'https://example.test',
            'browse_url_template' => 'https://mycompany.jfrog.io/ui/repos/tree/General/my-repo/%package%',
        ]);

        $this->assertSame([], $provider->getAuthHeaders());
    }

    public function testGetAuthHeadersUsesRegistryTokenAsABearerToken(): void
    {
        putenv('REGISTRY_TOKEN=secret-token');

        $provider = ArtifactoryProvider::fromConfig([
            'url' => 'https://example.test',
            'browse_url_template' => 'https://mycompany.jfrog.io/ui/repos/tree/General/my-repo/%package%',
        ]);

        $this->assertSame(['Authorization' => 'Bearer secret-token'], $provider->getAuthHeaders());
    }

    public function testFromConfigRequiresABrowseUrlTemplate(): void
    {
        // Without it every package in the generated RECIPES.md linked to the registry root. The
        // JFrog UI URL shape varies between instances, so there is nothing safe to guess.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "registry.browse_url_template" key is required when registry.type is "artifactory".');

        ArtifactoryProvider::fromConfig(['url' => 'https://mycompany.jfrog.io/artifactory/api/composer/my-repo']);
    }

    public function testGetPackageBrowseUrlHonoursTheTemplate(): void
    {
        $provider = ArtifactoryProvider::fromConfig([
            'url' => 'https://mycompany.jfrog.io/artifactory/api/composer/my-repo',
            'browse_url_template' => 'https://mycompany.jfrog.io/ui/repos/tree/General/my-repo/%package%',
        ]);

        $this->assertSame(
            'https://mycompany.jfrog.io/ui/repos/tree/General/my-repo/acme/private-bundle',
            $provider->getPackageBrowseUrl('acme/private-bundle')
        );
    }
}
