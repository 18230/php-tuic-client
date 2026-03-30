# Production Guide

`php-tuic-client` is not production-ready yet. The current project state is intentionally limited to:

- configuration parsing
- doctor checks
- dry-run validation
- release and CI scaffolding

Recommended use right now:

1. finalize the TUIC config shape
2. validate it in CI with `doctor` and `run --dry-run`
3. implement the transport runtime before exposing it as a long-running local proxy
