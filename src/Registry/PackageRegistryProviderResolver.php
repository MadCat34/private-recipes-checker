<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Registry;

final class PackageRegistryProviderResolver
{
    /** @var array<string, class-string<PackageRegistryProvider>> */
    public const DEFAULT_PROVIDERS = [
        'packagist' => PackagistProvider::class,
    ];

    /** @param array<string, class-string<PackageRegistryProvider>> $providers injectable for tests */
    public function __construct(private array $providers = self::DEFAULT_PROVIDERS)
    {
    }

    public function resolve(array $registryConfig): PackageRegistryProvider
    {
        $type = $registryConfig['type'] ?? 'packagist';

        if (!isset($this->providers[$type])) {
            throw new \RuntimeException(sprintf(
                'Unknown registry type "%s" (known: %s).',
                $type,
                implode(', ', array_keys($this->providers))
            ));
        }

        return ($this->providers[$type])::fromConfig($registryConfig);
    }
}
