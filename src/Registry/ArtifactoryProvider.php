<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Registry;

final class ArtifactoryProvider implements PackageRegistryProvider
{
    public function __construct(
        private string $url,
        private string $metadataUrlTemplate,
        private string $browseUrlTemplate,
        private ?string $token,
    ) {
    }

    public static function fromConfig(array $config): static
    {
        if (!isset($config['url'])) {
            throw new \RuntimeException('The "registry.url" key is required when registry.type is "artifactory".');
        }

        // No sensible default exists: the fallback used to be the registry URL itself, which
        // contains no %package%, so every package in the generated RECIPES.md linked to the same
        // page. The JFrog UI URL shape varies between instances, so guessing is worse than asking.
        if (!isset($config['browse_url_template'])) {
            throw new \RuntimeException('The "registry.browse_url_template" key is required when registry.type is "artifactory".');
        }

        $token = getenv('REGISTRY_TOKEN');

        return new static(
            $config['url'],
            $config['metadata_url_template'] ?? '%url%/p2/%package%.json',
            $config['browse_url_template'],
            false !== $token ? $token : null,
        );
    }

    public function getMetadataUrl(string $package, bool $dev = false): string
    {
        $packagePath = $dev ? $package.'~dev' : $package;

        return str_replace(['%url%', '%package%'], [$this->url, $packagePath], $this->metadataUrlTemplate);
    }

    public function getAuthHeaders(): array
    {
        return null !== $this->token ? ['Authorization' => 'Bearer '.$this->token] : [];
    }

    public function getPackageBrowseUrl(string $package): string
    {
        return str_replace('%package%', $package, $this->browseUrlTemplate);
    }
}
