# Issue 23 / PR 24 work plan

Objective: implement and verify every requirement in issue 23 in this PR, without merging or changing main directly.

1. [complete] Read and preserve the complete issue, comments, all three PR feedback types, required sub-issues, recent merged work, baseline CI and official registry evidence. Fresh snapshots confirm no additional required sub-issues or PR feedback.
2. [complete] Produce direct/runtime/action and every physical resolved dependency inventory with before/current/latest versions and explicit parent/upstream compatibility reasons. Document major migrations and existing component choices.
3. [complete] PHP first: migrate runtime, PHPUnit, PHPStan, formatter and locked dependencies. Reproduce the original policy failure and verify its regression fix; repair release preflight scope and the false collaborator-permissions probe using native Git authorization. Replace both deprecated PHP response-header variable uses with the native API.
4. [complete] Update all captured C#, pre-commit, Python, JavaScript/app, Rust, shell tools, actions, images and repeated pins. Restore missing source from recorded capture revisions with provenance; preserve the original tests and build interfaces.
5. [complete] Expand Dependabot to all manifests. Add full cross-ecosystem CI, locked installation, audits and bounded test/job execution. Extend actionlint/shellcheck to captured workflows and add release-expression regression coverage.
6. [complete] Search upstream work first; post four concrete reproductions/proposals to existing reports and link already-complete reports for the remaining known upstream constraints. Keep the narrow Windows installer backport and all audit coverage.
7. [in progress] Finish evidence commit and full PR diff review; verify every current-SHA CI run, repair failures, update PR title/body with Fixes #23 and final screenshot, confirm main ancestry and clean tree, mark PR 24 ready.
8. [externally dependent / unfinished] Literal default-branch recovery requires normal merge and GitHub Pages repository setup. Branch-only instructions prohibit directly modifying or merging main. The PR keeps a clear main-only setup failure and remains unmerged.

## CI investigation protocol

- List recent branch runs with IDs, creation timestamps, conclusions and head SHAs; use only the latest pushed SHA as final verification.
- Download every failing run to ci-logs, read in chunks at most 1500 lines, and record exact failure lines and corrective commits in SELF-REVIEW.md.
- Baseline main dda0eb2 pipeline 43509261699: WorkflowPolicyTest expected zizmor-action v0.8.0 after Dependabot selected v0.8.1 (baseline log 1533 and 1695–1700). baseline-regression.log reproduces it; policy-regression-fixed.log and php-check.log pass after adaptation.
- The same baseline preflight probed a read-only workflow token for release push permission (log 411). Give only that probing job the release scope.
- Final log review of the green 43526953740 run found Contents: write at 732–733 but a false permission refusal at 907–909. The new transport regression fails before the fix and passes afterward. Verify the real Actions token receives a Git push advertisement on final-SHA CI.
- Baseline Docs 41539790049 at dad99fe: Pages absent (log 230–231). PRs build docs without requiring deployment setup; main reports an actionable configuration error.
- Draft CI revealed formatter, trusted publisher and missing Rust package README issues; each is repaired in forward-moving commits, with logs preserved. Final workflow review also caught invalid leading ! YAML and shell diagnostics before final CI.
