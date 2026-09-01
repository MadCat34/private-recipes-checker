<?php

/*
 * (c) 2026 madcat34
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Vcs;

use App\ErrorReporter\ErrorReporter;

interface VcsProvider
{
    /**
     * Returns an instance if $env matches this CI environment, null otherwise.
     * $env is injected (never read via getenv() inside) so detection stays testable.
     */
    public static function detect(array $env): ?static;

    public function getToken(): ?string;

    /** @return list<array{sha: string, parent_count: int}> */
    public function getPullRequestCommits(): array;

    public function getPullRequestBody(): string;

    /** @return list<string> paths of files changed by the PR/MR, relative to the repo root */
    public function getChangedFiles(): array;

    /**
     * An authenticatable URL to a file's content (see spec §4 "URLs privées"). $path is inserted
     * verbatim — it may carry unresolved Flex placeholders ({package_dotted}, {version}, {ref})
     * that must never be URL-encoded.
     */
    public function getRawFileUrl(string $repository, string $ref, string $path): string;

    /** Schema-less "host/path" form (e.g. `github.com/acme/recipes`), used in `_links`. */
    public function getRepositoryUrl(string $repository): string;

    public function createErrorReporter(): ErrorReporter;
}
