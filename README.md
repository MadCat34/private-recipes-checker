# Private Recipe Checker

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.4-777bb4.svg)](composer.json)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000.svg)](composer.json)

A fork of [symfony-tools/recipes-checker](https://github.com/symfony-tools/recipes-checker) —
credit to its original authors, **Fabien Potencier** and **Nicolas Grekas** — adapted to manage
**private** Symfony Flex recipes for a company's internal bundles, instead of the official
`symfony/recipes`/`symfony/recipes-contrib` repositories.

## Why this fork exists

The upstream tool is built specifically for Symfony's own official recipe repositories: it is
tied to GitHub, to Packagist, and to the "official vs. contrib" distinction those two repositories
need. None of that fits a company that wants to:

- host its private recipes repository on **GitHub or GitLab**,
- publish its private packages on **Packagist or a JFrog Artifactory** Composer registry,
- validate `manifest.json` against a **formal JSON Schema** — including live validation in an
  editor via `$schema`, not just in CI.

Three concrete differentiators from the upstream tool:

- **Multi-VCS**: `VcsProvider` auto-detects GitHub Actions or GitLab CI from the environment —
  see `src/Vcs/`.
- **Multi-registry**: `PackageRegistryProvider`, selected via `.recipes-checker.yaml`, supports
  Packagist and JFrog Artifactory — see `src/Registry/`.
- **JSON Schema validation**: `resources/manifest.schema.json`, validated with `opis/json-schema`,
  replaces the upstream tool's hand-written key allowlist — and, along the way, accepts
  `composer-commands`, a documented Flex key the upstream allowlist rejects by mistake.

## Why it is not expected to be upstreamed

Two concrete, technical reasons — not a policy statement:

1. **The upstream distribution model can't absorb a breaking change.** Its own CI
   (`callable-qa.yml`) downloads the checker straight from the `main` branch, unversioned, on
   every run — for `symfony/recipes`, `symfony/recipes-contrib`, and any third party following the
   same pattern. This fork changes several command signatures (drops `--contrib`, drops the
   `event_path`/`github_token` arguments, changes `generate:flex-endpoint`'s arguments). Merging
   that to `main` upstream would break the very next CI run of every existing consumer, with no
   migration window possible under that distribution model.
2. **The scope divergence runs in both directions.** This fork *removes* functionality
   `symfony/recipes` structurally depends on (the official/contrib distinction, automatic
   `symfony/*` aliasing, `versions.json` splits) and *adds* a dependency and code (GitLab support,
   Artifactory support, `opis/json-schema`) that serve a use case the Symfony Core Team doesn't
   have. A change that removes what upstream uses and adds a dependency for what it will never use
   isn't a reasonable contribution to propose.

As a secondary, unrelated note: `composer.json` upstream declares `"license": "MIT"` while its
`LICENSE` file is the full AGPLv3 text — flagged, unanswered, in
[symfony-tools/recipes-checker#20](https://github.com/symfony-tools/recipes-checker/issues/20).
This fork corrects the metadata to match the license that was always actually there.

## License

AGPL-3.0-or-later — see [LICENSE](LICENSE).

## Requirements

- PHP >= 8.4, with the `ctype`, `intl`, and `mbstring` extensions
- Composer 2

## Installation

```bash
composer install
./run list
```

## Commands

```
 generate
  generate:archived-recipes  Generates an "archived" directory containing the history of every recipe.
  generate:flex-endpoint     Generates the json files required by Flex
  generate:recipes-readme    Generates a "README" containing a list of all recipes.
 lint
  lint:files                 Validates file-level conventions (indentation, extensions, newlines, ...)
  lint:manifests             Checks manifest.json files
  lint:packages              Ensures directories map to valid packages in the configured registry
  lint:pull-request          Ensures the PR/MR can be accepted
  lint:yaml                  Validates the content of yaml files
 misc
  diff-recipe-versions       Displays the diff between versions of a recipe
  list-unpatched-packages    Lists packages that are *not* patched by the PR/MR
```

Run `./run <command> --help` for a command's arguments and options.

## Configuration

Create a `.recipes-checker.yaml` at the root of your recipes repository:

```yaml
registry:
  type: packagist # or "artifactory"
  # url: https://artifactory.example.com/artifactory/api/composer/my-repo # artifactory only
  # token: "%env(ARTIFACTORY_TOKEN)%"                                     # artifactory only

readme:
  header: "# My Company's Private Recipes" # optional, used by generate:recipes-readme
```

The VCS (GitHub Actions or GitLab CI) is auto-detected from the CI environment — no configuration
needed there.

The recipe manifest schema lives at `resources/manifest.schema.json` (JSON Schema draft 2020-12,
validated with `opis/json-schema`). Reference it from a recipe's own `manifest.json` via `$schema`
for live validation in editors that support it.

## CI templates

Ready-to-copy CI pipelines for a private recipes repository live under
`templates/recipes-repository/`:

- **`.gitlab-ci.yml`** — lints merge requests, smoke-tests the resulting Flex endpoint against a
  throwaway `symfony/skeleton` project, auto-merges on success, and keeps the `flex/main` branch
  (the actual Flex endpoint served to consumers) and its `archived/` history up to date on every
  push to the default branch. See the comments at the top of the file for the GitLab project
  settings (protected branches, merge checks) and CI/CD variables it assumes.
- **`.github/workflows/qa.yml`** and **`callable-qa.yml`** — the GitHub Actions equivalent.

Both templates pin the checker via a `CHECKER_REF` variable/input — set it to a tagged release
once you've cut one, rather than tracking `main` unversioned (see "why it is not expected to be
upstreamed" below for why that matters).

## Development

```bash
composer install
vendor/bin/phpunit
```

The test suite (PHPUnit) covers `src/`; the upstream tool this was forked from had none.
