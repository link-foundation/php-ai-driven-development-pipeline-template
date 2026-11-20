---
bump: patch
---
### Fixed
- Build and upload API docs independently of optional GitHub Pages deployment.
- Fail change detection when Git history cannot establish a complete comparison.
- Require successful change detection, lint, tests, build, and preflight before automatic releases.
- Fail the release CLI when Git rejects a branch or tag push.
- Keep the Composer lock file current when publishing a release.
