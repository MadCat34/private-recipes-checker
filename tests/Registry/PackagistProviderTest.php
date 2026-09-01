<?php
// tests/Registry/PackagistProviderTest.php

namespace App\Tests\Registry;

use App\Registry\PackagistProvider;
use PHPUnit\Framework\TestCase;

class PackagistProviderTest extends TestCase
{
    public function testFromConfigIgnoresItsArgument(): void
    {
        $this->assertInstanceOf(PackagistProvider::class, PackagistProvider::fromConfig([]));
    }

    public function testGetMetadataUrl(): void
    {
        $provider = PackagistProvider::fromConfig([]);

        $this->assertSame('https://repo.packagist.org/p2/acme/private-bundle.json', $provider->getMetadataUrl('acme/private-bundle'));
    }

    public function testGetMetadataUrlForDevVersions(): void
    {
        $provider = PackagistProvider::fromConfig([]);

        $this->assertSame('https://repo.packagist.org/p2/acme/private-bundle~dev.json', $provider->getMetadataUrl('acme/private-bundle', dev: true));
    }

    public function testGetAuthHeadersIsEmpty(): void
    {
        $this->assertSame([], PackagistProvider::fromConfig([])->getAuthHeaders());
    }

    public function testGetPackageBrowseUrl(): void
    {
        $provider = PackagistProvider::fromConfig([]);

        $this->assertSame('https://packagist.org/packages/acme/private-bundle', $provider->getPackageBrowseUrl('acme/private-bundle'));
    }
}
