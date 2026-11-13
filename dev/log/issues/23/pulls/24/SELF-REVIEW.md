# Review and verification record

Issue: https://github.com/link-foundation/php-ai-driven-development-pipeline-template/issues/23
PR: https://github.com/link-foundation/php-ai-driven-development-pipeline-template/pull/24
Branch: issue-23-addf7c5fd0d4. Default baseline: dda0eb244919927efb7e8668d120996d9d82489f.

## Scope and review

Read the complete issue and comments, all three PR feedback types and sub-issue API; final snapshots contain no new feedback or required sub-issues. Reviewed `gh pr diff 24`, including all 83 changed pre-existing code/config files separately from recovered sources and generated locks. Reviewed the changed recovered JS helpers, actual source provenance, package contents, every major migration, workflow conditions and existing test preservation. `git merge-base --is-ancestor origin/main HEAD` passes. Work stays on the requested branch; no merge or default-branch write is performed.

The final review found and repaired leading ! cancellation expressions serialized as YAML tags; a parser regression fails on a6d7de8's workflow and passes on the fix. It also preserves the old comment-spacing lint rule using its maintained Stylistic replacement, proven with a negative CLI fixture. Full template actionlint/shellcheck found unsafe branch interpolation and shell diagnostics, now repaired and covered in root workflow CI. Updated current setup/coverage docs without altering historical changelogs or case-study analysis.

After committing the complete evidence, GitHub's diff endpoint returned HTTP 406 because the PR exceeds its 300-file limit. The earlier complete code review remains valid; final review uses the paginated PR files API and local diff for the remaining changes. Raw external best-practices, Coverlet integration and runner-image sources are stored as .txt so its original relative links are preserved as research data rather than published as broken local documentation links.

Automatic NuGet submission 43525474448 failed at eeb0625: ci-logs/nuget-43525474448.log:292–294 reports NETSDK1045 because the server-generated workflow only discovers a root global.json and otherwise selects SDK 10.0.404. Add the same 11.0.100 SDK pin at the root, preserving the captured template's standalone pin. Captured C# CI now performs its locked solution restore from the repository root before running the template-local build, tests and audit, exercising the failing SDK lookup on every PR. The actual automatic submission workflow provides a second regression check.

## Root regression and local verification

- Baseline original `WorkflowPolicyTest` fails because Dependabot selected zizmor-action v0.8.1 while the policy asserts v0.8.0. See baseline-regression.log and the baseline pipeline logs (43509261699:1533,1695–1700). The repaired interface assertion passes in policy-regression-fixed.log; all 132 PHP tests / 626 assertions, formatter, PHPStan and file-size checks pass in php-check.log. Focused final workflow checks: php-policy-review.log, 23 tests / 323 assertions.
- The release preflight's workflow token lacked push scope (baseline line 411); only its probing job receives the matching release scope. Existing permission failure tests are retained.
- Node and Bun: 72 passing tests each. Deno: 62 passing tests; ten original runtime-specific cases remain selected by their existing runtime guard. No tests were skipped to accommodate a dependency major. See js-*-review.log.
- JavaScript full lint/format/secrets check passes; jscpd reports zero clones with the unchanged zero threshold, minTokens 30 and minLines 5. The reviewed ESLint rule is preserved, not dropped.
- Python: Ruff, format, mypy, 21 tests / 100% coverage, package build, Twine, Sphinx warnings-as-errors, pip dependency checks and audit pass. C#: build has zero warnings/errors; all 21 tests, coverage, format, NuGet package, DocFX warnings-as-errors and 50 Bun script tests pass. Full CI repeats both ecosystems.
- Rust: root 51 tests plus three doctests, fmt and warnings-denied Clippy pass. Locked pipeline targets pass fmt, Clippy and their script tests locally. CI also performs release and real crate packaging. The earlier missing README package failure (43521662995:679–681) is fixed by recovering the capture's exact README and updating its current toolchain instructions.
- Full root and captured workflow actionlint/shellcheck pass in actionlint-templates-final.log. The original root security, dependency freshness, documentation and link jobs remain enabled.
- Audits: Composer, both npm trees, Deno, pip, all NuGet transitive dependencies and both Cargo locks report no known advisories. Original intermediate failures and registry responses are retained alongside the successful final results.
- Actual Playwright Chromium render verified addition (2 + 3 = 5) and multiplication (2 × 3 = 6); js-screenshots.log and the committed screenshot record the result. Browser closed. This is a dependency preservation check, not a requested UI redesign.

## Current CI and limitations

CI snapshots contain timestamps and exact SHAs; obsolete runs may be cancelled by newer commits and are not final verification. The final PR description/checks are updated only after every workflow for the final evidence commit completes successfully. Full builds are delegated to CI to keep local memory below the shared 2.92 GiB budget; local Rust builds use one compiler job and no debug/incremental artifacts.

Direct dependencies, runtimes, actions and tools use registry-verified current stable releases except the explicitly documented Java/Gradle compatibility limit. Every physical lock entry is recorded in TRANSITIVES.md (1396 dependency instances, 816 registry queries, zero registry errors at final generation). Distinct parent API lines, Scriv exact pins and MTP's compiled ApplicationInsights caller are documented rather than forced into incompatible overrides. The latest Linux/Windows installer parents retain existing deprecated glob/inflight/rimraf leaves; they are not newly introduced runtime warnings, their APIs cannot be globally replaced, and their upstream migration/release reports are linked in UPSTREAM.md. Security audits still include them and report no known advisories. No warning suppression or security-audit exclusion is added.

Unfinished acceptance criterion R02: literal default-branch CI recovery requires merge and Pages repository setup. Baseline docs 41539790049:230–231 reports Pages missing. PR documentation builds pass without deployment credentials; main keeps an explicit setup instruction. Branch-only/no-direct-merge instructions prevent claiming the default branch has already recovered. Keep the PR unmerged and the goal unfinished for this criterion.

The original root package version remains managed by its release workflow, as CONTRIBUTING.md requires; the committed major changelog fragment is the release trigger for the PHP 8.5 requirement. No build output/node_modules or dependency caches are committed. The unused backup ref for an unpublished build-output correction is local only; published history is forward-moving.

Link run 43525482784 failed at eeb0625: ci-logs/links-43525482784.log:364–399 identifies copied upstream relative links and four incorrect migration-guide routes. Preserve copied documents unaltered as .txt; correct the authored migration references to the current official PHPStan upgrade guide, .NET compatibility/11, jscpd docs/rust.md and Deno supply-chain pages. The complete external-link check remains enabled with no new exclusions.
