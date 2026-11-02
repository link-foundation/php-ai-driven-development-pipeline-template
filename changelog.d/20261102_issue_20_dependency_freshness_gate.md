---
bump: patch
---
### Fixed

- The workflow-dependency freshness check no longer turns `main` red when an
  upstream action or tool publishes a release. Outdated pins still fail pull
  requests, but push, scheduled and manual runs report them as warnings plus a
  step summary. Unpinned runner images and failed release lookups still fail on
  every event (closes #20).

### Added

- Added `.github/dependabot.yml` so the weekly outdated-action warning becomes a
  grouped GitHub Actions update pull request.

### Changed

- Bumped `shivammathur/setup-php` to 2.40.0, `github/codeql-action` to
  v4.39.1, `zizmorcore/zizmor-action` to v0.8.0 and zizmor to 1.31.1, the
  releases the freshness check now reports.
