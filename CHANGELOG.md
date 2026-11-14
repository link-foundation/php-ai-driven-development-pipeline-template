# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!-- changelog-insert-here -->

## [1.0.0] - 2026-11-14

### Added
- Added a release-preflight job that proves the release preconditions can
- hold before any expensive job runs: Packagist is probed by fetching the
- p2 metadata the wait loop polls anyway, and the workflow token by reading
- the repository's API permissions block. Every failure is reported (not
- just the first), unknown is never a pass, and pull requests run the same
- probes in advisory report mode (closes #11).
- Added a Security workflow auditing the supply chain continuously:
- `composer audit --locked --abandoned=fail` against a freshly resolved
- set, CodeQL over the workflow definitions themselves (CodeQL has no PHP
- analyser), actions/dependency-review on pull requests failing on
- high-severity changes, and a weekly schedule so an advisory published
- into a quiet repository is still caught (closes #7).
- Added `.github/dependabot.yml` so the weekly outdated-action warning becomes a
- grouped GitHub Actions update pull request.

### Changed
- Bumped `shivammathur/setup-php` to 2.40.0, `github/codeql-action` to
- v4.39.1, `zizmorcore/zizmor-action` to v0.8.0 and zizmor to 1.31.1, the
- releases the freshness check now reports.
- Update all PHP, JavaScript, Python, Rust, .NET, workflow and hook dependencies, with committed lockfiles and reproducible checks for the captured templates.
- Require PHP 8.5 and migrate development tools to PHPUnit 13 and PHPStan 2.

### Fixed
- Pinned the actionlint Docker image by digest to 1.7.12, which adds the
- `glob` check for never-matching `paths:` filters; a mutable tag of a
- repository outside this organization is arbitrary code in a job that
- analyses credentials (closes #5).
- Named the zizmor version (1.29.0) instead of trusting the action's frozen
- `latest` table, and added a narrow pedantic pass
- (`--min-severity high --min-confidence high`) so the Pedantic-only audits
- that cover `uses: docker://` image references actually enforce the
- `'*': hash-pin` policy without the stylistic noise (closes #6).
- Made every checkout declare its credential persistence explicitly:
- actions/checkout writes the token into `.git/config` unless told not to,
- and only the two release jobs -- which push the version bump -- keep it
- (closes #8).
- Replaced the grey pipeline-status gap with a real terminal-status job:
- GitHub reports `timeout-minutes` kills as `cancelled`, so every workflow
- now ends in a gate that observes every job and only forgives runs
- superseded by a newer commit (closes #9).
- Gave every long step its own execution budget
- (`scripts/run-with-budget-warning.sh`): GitHub reports a `timeout-minutes`
- kill as `cancelled`, so a hung step now fails red at 70% of its budget
- instead of greying out the whole job, and step budgets always expire
- before the job cap they sit under (closes #10).
- Re-checked the lychee failures where no host ever answered, outside
- lychee: `--max-retries` cannot retry a connection reset during connect
- (lycheeverse/lychee#2297), so a healthy URL behind a RST reddened the run
- without one retry. Failures carrying a status code stay final, and the
- Web Archive fallback and the fail step are gated on the re-check with the
- fail-safe `!= 'true'` form (closes #12).
- The workflow-dependency freshness check no longer turns `main` red when an
- upstream action or tool publishes a release. Outdated pins still fail pull
- requests, but push, scheduled and manual runs report them as warnings plus a
- step summary. Unpinned runner images and failed release lookups still fail on
- every event (closes #20).
- Keep workflow policy tests compatible with dependency updates and verify the release token with the permissions the release actually needs.

### Changed
- Classify cancelled CI jobs against their effective concurrency policy, and
- prevent descendants of budgeted commands from holding workflow steps open.
- Retry transient link-check failures with bounded exponential backoff, throttle
- GitHub requests, and preserve permanent failures when other links recover.
- Pin hosted runners to Ubuntu 24.04, update actions and workflow tools to current
- stable releases, and reject future runner aliases or stale workflow dependencies.


## [0.2.1] - 2026-09-02

### Added
- Validate GitHub Actions workflows with actionlint, ShellCheck, and zizmor.


## [0.2.0] - 2026-06-09

### Added
- Native PHP AI-driven development pipeline: combined CI/CD workflow, changelog-fragment changesets, self-healing idempotent releases to Packagist + GitHub Releases, change detection, file-size guard, and documentation/link-check workflows. All CI/CD logic lives in tested PHP classes under `scripts/`.

