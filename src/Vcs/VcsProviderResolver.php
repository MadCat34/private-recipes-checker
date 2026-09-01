<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Vcs;

final class VcsProviderResolver
{
    /** @var list<class-string<VcsProvider>> */
    public const DEFAULT_PROVIDERS = [
        GitHubProvider::class,
        GitLabProvider::class,
    ];

    /** @param list<class-string<VcsProvider>> $providers injectable for tests */
    public function __construct(private array $providers = self::DEFAULT_PROVIDERS)
    {
    }

    public function resolve(array $env): VcsProvider
    {
        foreach ($this->providers as $providerClass) {
            if (null !== $provider = $providerClass::detect($env)) {
                return $provider;
            }
        }

        return new NullVcsProvider();
    }
}
