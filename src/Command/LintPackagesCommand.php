<?php

/*
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Modified by madcat34 for the Private Recipe Checker fork, 2026.
 * See CHANGELOG.md and the git history for details.
 */

namespace App\Command;

use App\ErrorReporter\ErrorReporter;
use App\Registry\PackageRegistryProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'lint:packages', description: 'Ensures directories map to valid packages in the configured registry')]
class LintPackagesCommand extends Command
{
    public function __construct(
        private PackageRegistryProvider $registryProvider,
        private ErrorReporter $errorReporter,
        private HttpClientInterface $httpClient = new NativeHttpClient(),
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packages = [];

        foreach (glob('*/*') as $package) {
            $packages[] = [false, $package, $this->requestMetadata($package, dev: false)];
        }

        $hasErrors = false;

        for ($i = 0; isset($packages[$i]); ++$i) {
            [$dev, $package, $response] = $packages[$i];
            unset($packages[$i]);

            if (200 !== $response->getStatusCode()) {
                $this->errorReporter->reportError(sprintf('Package "%s" does not exist in the registry', $package));
                $hasErrors = true;
                unset($packages[$package]);

                continue;
            }

            $data = $response->toArray();

            foreach (glob("$package/*") as $version) {
                $version = substr($version, 1 + \strlen($package));

                if (!preg_match('/^\d+\.\d+$/D', $version)) {
                    $this->errorReporter->reportError(sprintf('Version "%s" is not valid, format is "x.y" where x and y are numbers for "%s"', $version, $package));
                    $hasErrors = true;
                    continue;
                }

                $found = false;
                foreach ($data['packages'][$package] as $versionData) {
                    $v = str_ends_with($version, '.0') ? substr($version, 0, -2) : $version;

                    if (str_starts_with($versionData['version_normalized'], $v.'.')) {
                        $found = true;
                        break;
                    }

                    if ($dev && str_starts_with($versionData['extra']['branch-alias'][$versionData['version'] ?? ''] ?? '', $v.'.')) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    if ($dev) {
                        $this->errorReporter->reportError(sprintf('Version "%s" of "%s" does not exist in the registry', $version, $package));
                        $hasErrors = true;
                    } else {
                        if (!$packages) {
                            $i = -1;
                        }
                        $packages[] = [true, $package, $this->requestMetadata($package, dev: true)];
                    }

                    continue;
                }

                if (isset($versionData['require']['symfony/symfony'])) {
                    $this->errorReporter->reportError(sprintf('Package "%s/%s" must not depend on symfony/symfony; depend on explicit symfony/* packages instead', $package, $version));
                    $hasErrors = true;
                }

                if (isset($versionData['require']['symfony/security']) && !\in_array($package.'/'.$version, ['nelmio/security-bundle/2.4', 'symfony/security-bundle/3.3'], true)) {
                    $this->errorReporter->reportError(sprintf('Package "%s/%s" must not depend on symfony/security; depend on explicit symfony/security-* packages instead', $package, $version));
                    $hasErrors = true;
                }

                if (!is_file("$package/$version/manifest.json")) {
                    continue;
                }

                $manifest = json_decode(file_get_contents("$package/$version/manifest.json"), true);
                if (empty($manifest['bundles']) && !empty($versionData['type'])) {
                    if ('symfony-bundle' === $versionData['type']) {
                        $this->errorReporter->reportError(sprintf('You should register the bundle in the manifest as "%s/%s" is a Symfony bundle', $package, $version));
                        $hasErrors = true;
                    } elseif ('sylius-plugin' === $versionData['type']) {
                        $this->errorReporter->reportError(sprintf('You should register the bundle in the manifest as "%s/%s" is a Sylius plugin', $package, $version));
                        $hasErrors = true;
                    } elseif ('sulu-plugin' === $versionData['type']) {
                        $this->errorReporter->reportError(sprintf('You should register the bundle in the manifest as "%s/%s" is a Sulu bundle', $package, $version));
                        $hasErrors = true;
                    }
                }
            }
        }

        $this->errorReporter->flush($this->getName());

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    private function requestMetadata(string $package, bool $dev): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->httpClient->request('GET', $this->registryProvider->getMetadataUrl($package, $dev), [
            'headers' => $this->registryProvider->getAuthHeaders(),
        ]);
    }
}
