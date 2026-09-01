<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Registry;

final class PackagistProvider implements PackageRegistryProvider
{
    public static function fromConfig(array $config): static
    {
        return new static();
    }

    public function getMetadataUrl(string $package, bool $dev = false): string
    {
        return sprintf('https://repo.packagist.org/p2/%s%s.json', $package, $dev ? '~dev' : '');
    }

    public function getAuthHeaders(): array
    {
        return [];
    }

    public function getPackageBrowseUrl(string $package): string
    {
        return 'https://packagist.org/packages/'.$package;
    }
}
