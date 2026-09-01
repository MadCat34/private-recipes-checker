<?php
// tests/Registry/UnavailableRegistryProviderTest.php

namespace App\Tests\Registry;

use App\Registry\UnavailableRegistryProvider;
use PHPUnit\Framework\TestCase;

class UnavailableRegistryProviderTest extends TestCase
{
    /** @dataProvider methodProvider */
    public function testEveryMethodRethrowsTheWrappedError(callable $call): void
    {
        $original = new \RuntimeException('Unknown registry type "bogus" (known: packagist, artifactory).');
        $provider = new UnavailableRegistryProvider($original);

        try {
            $call($provider);
            $this->fail('Expected the wrapped exception to be thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame($original, $e);
        }
    }

    public static function methodProvider(): iterable
    {
        yield 'getMetadataUrl' => [fn (UnavailableRegistryProvider $p) => $p->getMetadataUrl('acme/private-bundle')];
        yield 'getAuthHeaders' => [fn (UnavailableRegistryProvider $p) => $p->getAuthHeaders()];
        yield 'getPackageBrowseUrl' => [fn (UnavailableRegistryProvider $p) => $p->getPackageBrowseUrl('acme/private-bundle')];
    }
}
