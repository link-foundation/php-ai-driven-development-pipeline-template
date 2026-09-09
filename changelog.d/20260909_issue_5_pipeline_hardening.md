---
bump: minor
---
### Fixed

- Pinned the actionlint Docker image by digest to 1.7.12, which adds the
  `glob` check for never-matching `paths:` filters; a mutable tag of a
  repository outside this organization is arbitrary code in a job that
  analyses credentials (closes #5).
- Named the zizmor version (1.29.0) instead of trusting the action's frozen
  `latest` table, and added a narrow pedantic pass
  (`--min-severity high --min-confidence high`) so the Pedantic-only audits
  that cover `uses: docker://` image references actually enforce the
  `'*': hash-pin` policy without the stylistic noise (closes #6).
- Made every checkout declare its credential persistence explicitly:
  actions/checkout writes the token into `.git/config` unless told not to,
  and only the two release jobs -- which push the version bump -- keep it
  (closes #8).
- Replaced the grey pipeline-status gap with a real terminal-status job:
  GitHub reports `timeout-minutes` kills as `cancelled`, so every workflow
  now ends in a gate that observes every job and only forgives runs
  superseded by a newer commit (closes #9).
- Gave every long step its own execution budget
  (`scripts/run-with-budget-warning.sh`): GitHub reports a `timeout-minutes`
  kill as `cancelled`, so a hung step now fails red at 70% of its budget
  instead of greying out the whole job, and step budgets always expire
  before the job cap they sit under (closes #10).
- Re-checked the lychee failures where no host ever answered, outside
  lychee: `--max-retries` cannot retry a connection reset during connect
  (lycheeverse/lychee#2297), so a healthy URL behind a RST reddened the run
  without one retry. Failures carrying a status code stay final, and the
  Web Archive fallback and the fail step are gated on the re-check with the
  fail-safe `!= 'true'` form (closes #12).

### Added

- Added a release-preflight job that proves the release preconditions can
  hold before any expensive job runs: Packagist is probed by fetching the
  p2 metadata the wait loop polls anyway, and the workflow token by reading
  the repository's API permissions block. Every failure is reported (not
  just the first), unknown is never a pass, and pull requests run the same
  probes in advisory report mode (closes #11).
- Added a Security workflow auditing the supply chain continuously:
  `composer audit --locked --abandoned=fail` against a freshly resolved
  set, CodeQL over the workflow definitions themselves (CodeQL has no PHP
  analyser), actions/dependency-review on pull requests failing on
  high-severity changes, and a weekly schedule so an advisory published
  into a quiet repository is still caught (closes #7).
