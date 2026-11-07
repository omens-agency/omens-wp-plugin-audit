# Changelog

## 1.0.0 (2026-11-07)

First release of the Omens Site Audit, the on-site half of the Omens Site Audit system (central run: `web-maintenance-audit`; fix workflow: `web-audit-fix`).

- A stateless audit sensor: 18 read-only checks across security and currency, SEO and indexability, server-side accessibility, and legal and consent drift, each shipped with fixtures that fire and fixtures that stay clean (`tools/fixtures.sh`, 88 cases). Keeps only the latest run in one option keyed by host and environment, holds no secrets, makes no outbound request except loopback to its own `home_url()`.
- The content-versus-code routing rule: a defect in rendered HTML is traced to `post_content`, ACF fields, ACF options pages, the Media Library or the theme source, so a task names the field or the file. Purchased-theme sites route code to a person.
- Build-type detection by the starter's `OMENS_STARTER_VERSION` constant, overridable with `omens_audit/build_type`. Admin and mu-plugin inventories and an installed-plugin list for the central run's baselines and alert confirmation.
- The loopback fetcher refuses to fetch while WordPress is in maintenance mode and recognises a password gate or holding page served with 200, so a page that is not the site never counts as the site.
- WP-CLI `run` (one progress line per check), `export`, `checks`; Tools > Site Audit for Omens accounts; a monthly WP-Cron fallback; self-updating from GitHub release assets; `tools/smoke.sh` for a local site.

Internal milestones 0.1.0 to 0.2.1 were never released; their notes follow for the record.

## 0.2.1 (unreleased, M3)

- The run's inventory gains `plugins` (slug, file, name, version, status), so the central run can confirm a host vulnerability alert against the version the site really runs before filing it.

## 0.2.0 (unreleased, M2: the version 1 checks)

- The 18 on-site checks of version 1 (security 4, currency 2, SEO 3, accessibility 4, legal 5), each with fixtures that fire and fixtures that stay clean; `tools/fixtures.sh` runs them against WordPress stubs. The content-versus-code routing rule (`includes/routing.php`) with a fixture per branch. Shared HTML helpers (DOMDocument, accessible name, selector, robots, scripts). Tracker signatures copied from web-legal-pages into `data/trackers.json`; meaningless-filename patterns from omens-wp-plugin-media into `data/meaningless-filenames.json`.

## 0.1.0 (unreleased, M1 skeleton)

- Plugin skeleton: guarded constants, check interface and registry (`omens_audit/checks`), run engine with per-check error isolation, the shared finding schema with fingerprints, build-type detection keyed on the starter's `OMENS_STARTER_VERSION`, the rendered-HTML sample and loopback fetcher (sequential, 10 second timeout, cache-bust query string, own host only), admin and mu-plugin inventories, the latest-run option keyed by environment and host, a monthly WP-Cron fallback, Tools > Site Audit with a nonce-checked Run now, WP-CLI `run`, `export` and `checks`, self-updating from GitHub releases, uninstall.
- First check: `security.file-edit-enabled`.
