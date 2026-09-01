<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Registry;

interface PackageRegistryProvider
{
    /** @param array<string, mixed> $config the "registry" section of .recipes-checker.yaml */
    public static function fromConfig(array $config): static;

    public function getMetadataUrl(string $package, bool $dev = false): string;

    /** @return array<string, string> HTTP headers to add to the request */
    public function getAuthHeaders(): array;

    /** Human-facing URL (package presentation page), used by generate:recipes-readme */
    public function getPackageBrowseUrl(string $package): string;
}
