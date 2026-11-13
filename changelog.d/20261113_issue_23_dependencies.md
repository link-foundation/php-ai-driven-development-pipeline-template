---
bump: major
---

### Changed
- Update all PHP, JavaScript, Python, Rust, .NET, workflow and hook dependencies, with committed lockfiles and reproducible checks for the captured templates.
- Require PHP 8.5 and migrate development tools to PHPUnit 13 and PHPStan 2.

### Fixed
- Keep workflow policy tests compatible with dependency updates and verify the release token with the permissions the release actually needs.
