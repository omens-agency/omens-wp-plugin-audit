# Changelog

## 0.1.0 (unreleased, M1 skeleton)

- Plugin skeleton: guarded constants, check interface and registry (`omens_audit/checks`), run engine with per-check error isolation, the shared finding schema with fingerprints, build-type detection keyed on the starter's `OMENS_STARTER_VERSION`, the rendered-HTML sample and loopback fetcher (sequential, 10 second timeout, cache-bust query string, own host only), admin and mu-plugin inventories, the latest-run option keyed by environment and host, a monthly WP-Cron fallback, Tools > Site Audit with a nonce-checked Run now, WP-CLI `run`, `export` and `checks`, self-updating from GitHub releases, uninstall.
- First check: `security.file-edit-enabled`.
