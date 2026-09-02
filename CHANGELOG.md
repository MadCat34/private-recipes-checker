# Changelog

High-level summary of what changed in this fork relative to
[symfony-tools/recipes-checker](https://github.com/symfony-tools/recipes-checker). File-level
detail is in the git history; per-file modification notices are in each touched file's header, per
AGPLv3 §5(a).

## Unreleased

### Added

- Multi-VCS support: `VcsProvider` (GitHub Actions, GitLab CI), auto-detected from the
  environment, resolved once in `run`.
- Multi-registry support: `PackageRegistryProvider` (Packagist, JFrog Artifactory), selected via
  `.recipes-checker.yaml`.
- `lint:files` command: file-level checks (indentation, extensions, trailing newlines, symlinks,
  YAML style, Makefile content) that were previously only planned as a shared CI shell script —
  now a testable PHP command shared by both CI templates.
- `resources/manifest.schema.json`: formal JSON Schema (draft 2020-12) for `manifest.json`,
  validated via `opis/json-schema`. Recipe authors can reference it from their own `manifest.json`
  via `$schema` for live editor validation.
- `ErrorReporter` abstraction: GitHub Actions `::error::` annotations or GitLab Code Quality
  reports, chosen automatically alongside the VCS provider.
- Test suite (PHPUnit) — the upstream tool had none.

### Changed

- `lint:manifests`: structural validation (allowed keys, `add-lines` shape) now comes entirely
  from the JSON Schema instead of a hand-written `ALLOWED_KEYS` array — which fixes a real
  upstream bug: `composer-commands` is documented and Flex-supported but was missing from that
  array, so upstream rejects it.
- `generate:flex-endpoint`: `_links` URLs are built from the resolved `VcsProvider` instead of
  hardcoded GitHub URLs, and now point at authenticatable API endpoints
  (`api.github.com/repos/.../contents/...`, GitLab's repository files API) rather than
  `raw.githubusercontent.com`, which cannot serve private repositories.
- `lint:packages`: package metadata URL and auth headers come from the resolved
  `PackageRegistryProvider` instead of a hardcoded Packagist URL.
- `generate:recipes-readme`: package links come from `PackageRegistryProvider::getPackageBrowseUrl()`;
  the header is now configurable via `readme.header` in `.recipes-checker.yaml`.
- `composer.json`: `symfony/console` and friends bumped from `^5.4` to `^8.1`; `php` from `>=8` to
  `>=8.4` (required by `symfony/console` 8.1); `"license"` corrected from the incorrect `"MIT"` to
  `"AGPL-3.0-or-later"`, matching the `LICENSE` file that was always there; package renamed to
  `madcat34/private-recipe-checker`.

### Removed

- The "official vs. contrib" distinction (`--contrib` option, everywhere it appeared) — this fork
  manages one kind of repository: a company's private recipes.
- Automatic `symfony/*` package aliasing in `generate:flex-endpoint` — specific to the Symfony
  monorepo, meaningless for private bundles.
- `versions_json`/`.github/versions.json` support in `generate:flex-endpoint` — Symfony's own
  LTS/stable/next version tracking, out of scope for private bundles.
- The `.ci/checks.sh` shared shell script that was originally planned — replaced by `lint:files`
  before it was ever written.

### Fixed

- `LintManifestsCommand`: a special-alias check compared the wrong variable
  (`in_array($aliases, ...)`, the accumulator array, instead of `in_array($alias, ...)`, the
  current alias) and could never actually reject `lock`/`nothing`/`mirrors`/an empty alias.
- `GenerateArchivedRecipesCommand`: its own re-invocation of `generate:flex-endpoint` computed the
  checker's root directory as `realpath(__DIR__.'/../')`, which broke once commands moved into
  `src/Command/` (one directory deeper).
- `LintPackagesCommand`: a broken `.recipes-checker.yaml` registry config (unknown `registry.type`)
  silently succeeded when the working directory had no package folders to check, instead of
  surfacing the configuration error.
