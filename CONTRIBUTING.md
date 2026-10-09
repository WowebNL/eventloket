# Contributing to Eventloket

Thank you for your interest in Eventloket. This document describes how bug reports, pull requests, reviews and releases work in this repository, and which conventions and code standards apply. Everyone taking part is expected to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting a security vulnerability

Do not report a vulnerability in a pull request or any other public channel. Follow [SECURITY.md](SECURITY.md) instead, so the vulnerability can be handled confidentially until a fix is available.

## Reporting a bug or requesting a change

GitHub Issues are not enabled for this repository. Report a bug or request a change through the organisation that commissioned Eventloket (see the [README](README.md)), not through this repository. Bugs and changes that are accepted are resolved through pull requests, as described below.

Do not include personal data, credentials or production data in a report. Use synthetic examples instead.

## Setting up a development environment

The [README](README.md) explains how to run the application with Laravel Sail. To run the complete set of ZGW components locally, see [docs/technisch/lokale-ontwikkelomgeving.md](docs/technisch/lokale-ontwikkelomgeving.md).

Activate the Git hooks of this repository once:

```bash
git config core.hooksPath .githooks
```

The pre-commit hook then runs Pint, PHPStan, Rector (dry run) and the Pest test suite before every commit.

## Branches

`main` is the trunk. All new work starts from `main` on a short-lived branch and returns to `main` through a pull request.

Name your branch after the type of change:

| Prefix | Use for |
| --- | --- |
| `fix/` | Bug fixes |
| `feat/` | New functionality |
| `docs/` | Documentation only |
| `chore/` | Maintenance without functional change |

Branches starting with `fix/` and `feat/` are labelled automatically for the changelog (see [Releases](#releases)).

Supported older versions are maintained on `release/*` branches. Fixes reach those branches as backports: after a pull request has been merged into `main`, adding the label `backport release/x.y` creates a backport pull request to that branch automatically.

## Pull requests

1. Open the pull request against `main`.
2. Describe what changes and why.
3. Make sure the required checks pass. These run on every pull request:
   - `phplint`: Laravel Pint with the `laravel` preset, in test mode;
   - `phpstan`: PHPStan static analysis;
   - `Run Unit Tests`: the Pest test suite against MySQL 8.
4. Give the pull request a changelog label (see [Releases](#releases)), unless it was applied automatically.
5. Wait for a review. A pull request into `main` needs at least one approving review before it can be merged. Pushing new commits dismisses an earlier approval, so the final version is always the one that was reviewed.

Reviews look at correctness, security, quality, performance impact and maintainability. Maintainers may ask for changes, or decline a contribution that does not fit the architecture or the security requirements of the project, and will explain why.

Pull requests are usually squash-merged, so the pull request title becomes the commit message on `main`. Write the title with that in mind.

## Commit conventions

- Write commit messages and pull request titles in English.
- Use the imperative mood and sentence case, and describe the change in behaviour rather than the files that were touched. For example: "Keep the zaak detail screen up when a document cannot be read".
- Keep the subject line short. Use the body to explain why the change is needed when that is not obvious.
- Keep a dependency update in its own pull request, separate from functional changes.

## Code standards

- **Code style**: [Laravel Pint](https://laravel.com/docs/pint) with the `laravel` preset. Run `./vendor/bin/sail pint` before you commit; CI runs Pint in test mode and fails on any difference.
- **Static analysis**: [PHPStan](https://phpstan.org/) with Larastan at level 5 on `app/`, configured in [phpstan.neon](phpstan.neon). New code must not introduce new errors.
- **Refactoring rules**: [Rector](https://getrector.com/), configured in [rector.php](rector.php). The pre-commit hook runs it as a dry run; apply its suggestions before you commit.
- **Tests**: [Pest](https://pestphp.com/). Add or update tests for every change in behaviour, and make sure the full suite passes.
- **Formatting**: follow [.editorconfig](.editorconfig) (UTF-8, LF line endings, four spaces, two for YAML).
- **Conventions**: follow the structure, naming and approach of the surrounding code, and reuse existing components before adding new ones.
- **Interface language**: the user interface is in Dutch.
- **Dependencies**: do not add, remove or upgrade dependencies as part of an unrelated change.

## Releases

Eventloket follows [Semantic Versioning](https://semver.org/). Release Drafter keeps a draft release up to date as pull requests are merged into `main`, and derives the next version number from the changelog labels:

| Label | Version impact |
| --- | --- |
| `changelog: breaking` | Major |
| `changelog: feature` | Minor |
| `changelog: bug`, `changelog: refactor`, `changelog: docs`, `changelog: dependencies`, `changelog: security` | Patch |
| `skip-changelog` | Not included in the release notes |

When a release is published on GitHub, its release notes are added to [CHANGELOG.md](CHANGELOG.md) automatically. The full release process, including hotfixes and backports, is described in [docs/RELEASE_PROCESS.md](docs/RELEASE_PROCESS.md).

## Dependency updates

The maintainers update dependencies and follow up on the security alerts that GitHub raises for them. To report a vulnerability in a dependency that affects Eventloket, follow [SECURITY.md](SECURITY.md).

## License

Eventloket is licensed under the [EUPL 1.2](LICENSE.MD). By contributing, you agree that your contribution is licensed under the same terms.
