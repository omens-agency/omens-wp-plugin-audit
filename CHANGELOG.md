# Changelog

## 0.2.0 (unreleased, M2: the version 1 checks)

- The 18 on-site checks of version 1 (security 4, currency 2, SEO 3, accessibility 4, legal 5), each with fixtures that fire and fixtures that stay clean; `tools/fixtures.sh` runs them against WordPress stubs. The content-versus-code routing rule (`includes/routing.php`) with a fixture per branch. Shared HTML helpers (DOMDocument, accessible name, selector, robots, scripts). Tracker signatures copied from web-legal-pages into `data/trackers.json`; meaningless-filename patterns from omens-wp-plugin-media into `data/meaningless-filenames.json`.

## 0.1.0 (unreleased, M1 skeleton)

- Plugin skeleton: guarded constants, check interface and registry (`omens_audit/checks`), run engine with per-check error isolation, the shared finding schema with fingerprints, build-type detection keyed on the starter's `OMENS_STARTER_VERSION`, the rendered-HTML sample and loopback fetcher (sequential, 10 second timeout, cache-bust query string, own host only), admin and mu-plugin inventories, the latest-run option keyed by environment and host, a monthly WP-Cron fallback, Tools > Site Audit with a nonce-checked Run now, WP-CLI `run`, `export` and `checks`, self-updating from GitHub releases, uninstall.
- First check: `security.file-edit-enabled`.
