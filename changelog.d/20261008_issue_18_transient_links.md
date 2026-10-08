---
bump: patch
---

Retry transient link-check failures with bounded exponential backoff, throttle
GitHub requests, and preserve permanent failures when other links recover.

Pin hosted runners to Ubuntu 24.04, update actions and workflow tools to current
stable releases, and reject future runner aliases or stale workflow dependencies.
