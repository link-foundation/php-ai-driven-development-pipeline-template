# Issue 23 / PR 24 work plan

Objective: implement and verify every requirement in issue 23 in this PR, without merging or changing main directly.

1. [in progress] Preserve complete issue, comments, PR conversation/reviews/inline comments, required sub-issues, merged-PR context, baseline CI logs, and registry evidence.
2. [pending] Produce per-ecosystem before/latest tables (including script metadata, toolchains, actions, images and transitive locks), research major migrations and existing components.
3. [pending] PHP first: upgrade constraints, runtime, PHPUnit/PHPStan/formatter APIs, commit Composer lock, reproduce/fix baseline failures with regression coverage.
4. [pending] Update every nested template ecosystem and every repeated version; restore missing source only if needed to make captured projects verifiable; preserve provenance and existing features.
5. [pending] Cover all manifests in Dependabot; add full cross-ecosystem build/test/lint/audit verification using bounded CI jobs and small sequential local checks.
6. [pending] Search upstream reports for dependency workarounds, report reproducible general issues, retain necessary local workarounds and link reports.
7. [pending] Commit atomic steps, push only issue-23-addf7c5fd0d4, update PR title/body (Fixes #23), fetch fresh CI logs for current SHA, repair all failures, review full diff, verify default-branch ancestry and clean tree, mark PR 24 ready.

No required sub-issues or PR feedback were present in the first complete API snapshots. The default branch cannot receive these fixes until the PR is merged; validate fixes on the PR and keep any main-only deployment setup failures explicit.

## CI investigation protocol

- List latest five branch runs with IDs, creation timestamps, conclusions and head SHAs.
- Match runs to latest pushed SHA before using a result as verification.
- Download each failing run into ci-logs; inspect files in chunks no larger than 1500 lines and record exact error lines.
- Baseline: main dda0eb2, pipeline 43509261699 failed; docs 41539790049 failed at dad99fe. Other latest baseline security/workflow/link checks succeeded.
- Confirmed pipeline regression: WorkflowPolicyTest.php:150 expects zizmor-action v0.8.0 after Dependabot upgraded to v0.8.1 (baseline log 1533, 1695–1700; repeated across PHP matrix).
- Separate baseline configuration failures: preflight job checks a read-only workflow token for push permission (log 411); Pages site absent (docs log 230–231). Do not hide release/deployment precondition errors on main.
