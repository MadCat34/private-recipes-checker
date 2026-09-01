<?php
// tests/Registry/PackageRegistryProviderResolverTest.php

namespace App\Tests\Registry;

use App\Registry\PackagistProvider;
use App\Registry\PackageRegistryProviderResolver;
use PHPUnit\Framework\TestCase;

class PackageRegistryProviderResolverTest extends TestCase
{
    public function testResolveDefaultsToPackagistWhenTypeIsAbsent(): void
    {
        $resolver = new PackageRegistryProviderResolver(['packagist' => PackagistProvider::class]);

        $this->assertInstanceOf(PackagistProvider::class, $resolver->resolve([]));
    }

    public function testResolveThrowsOnUnknownType(): void
    {
        $resolver = new PackageRegistryProviderResolver(['packagist' => PackagistProvider::class]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown registry type "bogus" (known: packagist).');

        $resolver->resolve(['type' => 'bogus']);
    }
}
