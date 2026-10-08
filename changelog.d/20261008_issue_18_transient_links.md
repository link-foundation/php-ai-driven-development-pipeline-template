---
bump: patch
---

Retry transient link-check failures with bounded exponential backoff, throttle
GitHub requests, and preserve permanent failures when other links recover.
