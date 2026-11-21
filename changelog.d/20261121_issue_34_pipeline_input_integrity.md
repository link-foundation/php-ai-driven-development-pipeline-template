---
bump: patch
---
### Fixed
- Verify pull request comparison evidence and reject malformed manifests or manual addition, removal, and editing of the Composer version.
- Keep release notes and truncation notices within their byte limit while preserving complete UTF-8 characters.
- Capture both subprocess output streams without deadlocking, and preserve literal zero output and exit codes.
