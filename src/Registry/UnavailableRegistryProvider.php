<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Registry;

/**
 * Null object used when .recipes-checker.yaml or its "registry" section is broken. Constructed
 * directly by `run` (never through the resolver's fromConfig), it rethrows the original error the
 * moment any real method is called — so a bad config doesn't prevent `./run --help` from answering.
 */
final class UnavailableRegistryProvider implements PackageRegistryProvider
{
    public function __construct(private \Throwable $error)
    {
    }

    public static function fromConfig(array $config): static
    {
        throw new \LogicException('UnavailableRegistryProvider must be constructed directly with the configuration error it wraps.');
    }

    public function getMetadataUrl(string $package, bool $dev = false): string
    {
        throw $this->error;
    }

    public function getAuthHeaders(): array
    {
        throw $this->error;
    }

    public function getPackageBrowseUrl(string $package): string
    {
        throw $this->error;
    }
}
