# Private Recipe Checker

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

## Usage

```bash
composer install
./run list
```

See `.recipes-checker.yaml` (documented in `CLAUDE.md`) for registry configuration, and
`resources/manifest.schema.json` for the recipe manifest schema.
