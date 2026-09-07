# Changelog

High-level summary of what changed in this fork relative to
[symfony-tools/recipes-checker](https://github.com/symfony-tools/recipes-checker). File-level
detail is in the git history; per-file modification notices are in each touched file's header, per
AGPLv3 §5(a).

## Unreleased

### Added

- `templates/recipes-repository/.gitlab-ci.yml`: a GitLab CI template mirroring the GitHub Actions
  one — lints merge requests, smoke-tests the generated Flex endpoint, keeps the published
  endpoint and its archives up to date on pushes to the default branch, and cleans up stale
  branches.
- Multi-VCS support: `VcsProvider` (GitHub Actions, GitLab CI), auto-detected from the environment.
- Multi-registry support: `PackageRegistryProvider` (Packagist, JFrog Artifactory), selected via
  `.recipes-checker.yaml`.
- `lint:files` command: file-level checks (indentation, extensions, trailing newlines, symlinks,
  YAML style, Makefile content).
- `resources/manifest.schema.json`: formal JSON Schema for `manifest.json`, usable for live
  editor validation via `$schema`.
- `ErrorReporter` abstraction: GitHub Actions annotations or GitLab Code Quality reports, chosen
  automatically alongside the VCS provider.
- Test suite (PHPUnit) — the upstream tool had none.

### Changed

- `lint:manifests`: structural validation now comes entirely from the JSON Schema instead of a
  hand-written allowlist, fixing a real upstream bug (`composer-commands` was wrongly rejected).
- `generate:flex-endpoint`: links point at the resolved VCS's API instead of hardcoded GitHub URLs,
  so they also work against private repositories.
- `lint:packages`: package lookups and auth come from the configured registry instead of a
  hardcoded Packagist URL.
- `generate:recipes-readme`: header is now configurable via `readme.header`.
- `composer.json`: dependencies bumped to current major versions, `"license"` corrected from the
  incorrect `"MIT"` to `"AGPL-3.0-or-later"`, package renamed to `madcat34/private-recipe-checker`.

### Removed

- The "official vs. contrib" distinction (`--contrib` option) — this fork manages one kind of
  repository: a company's private recipes.
- Symfony-monorepo-specific behavior in `generate:flex-endpoint` (automatic `symfony/*` aliasing,
  `versions.json` tracking) that doesn't apply to private bundles.

### Fixed

- `LintManifestsCommand`, `GenerateArchivedRecipesCommand`, `LintPackagesCommand`: correctness bugs
  inherited from upstream around alias validation, path resolution, and silent config errors.
- GitLab CI template: authentication, branch-handling, and directory-scanning issues found while
  hardening the pipeline (see git history for specifics).
