# Changelog

## 1.0.5 (2026-11-08)

Two false positives found while working the moderate findings of the fleet dry run. No new checks.

- `a11y.link-empty` skips WordPress core's image lightbox trigger button, whose `aria-label` is bound at runtime by the Interactivity API (`data-wp-bind--aria-label`), the same way `a11y.img-alt-missing` already skips the lightbox image.
- The URL sample skips page-builder template post types (Elementor library, ThemeREX layouts, Oxygen, ElementsKit and the like). Their permalinks redirect to the front page and the redirected request carries no cache-buster, so on three sites the audit kept reading an hour-old cached copy of `/` under another name after a fix had landed. Filter: `omens_audit/template_post_types`.
- `currency.house-plugins` no longer asks a custom build for `omens-wp-plugin-seo` when Yoast, Rank Math or All in One SEO is active. Yoast is the house SEO pick (plugin baseline, 2026-08-31), so a starter build on Yoast is the normal case, not a gap.

## 1.0.4 (2026-11-08)

Fixes from the serious-findings review of the first fleet dry run. No new checks.

- **`security.admins-without-2fa` files nothing while a 2FA plugin is active.** Jake's call (November 8): enrolment is the plugin's job once it is enforced. The check now records one info line per site (how many administrators are enrolled, whether WP 2FA enforces the Administrator role, and the names not yet enrolled, kept in the evidence for the monthly report) and stays serious only when no provider is active. 151 would-be tasks across 34 sites become 34 info lines.
- **`seo.sitemap` finds the sitemap the way a crawler does.** It reads robots.txt `Sitemap:` lines first, then the detected plugin's default, then `/sitemap_index.xml`, `/sitemap.xml` and `/wp-sitemap.xml`, and accepts the first body that is a sitemap. `<loc>` values wrapped in CDATA (AIOSEO) are parsed. Four of the five fleet findings were the check's own: two AIOSEO indexes read as empty, Slim SEO's `/sitemap.xml` never tried, and a Yoast site mis-detected as Omens SEO.
- SEO-plugin detection prefers an active Yoast, Rank Math or All in One SEO over the Omens SEO fallback, which used to win whenever a theme defined `omens_seo_get_description()`.

## 1.0.3 (2026-11-08)

Fixes from the first fleet dry run (35 sites, November 7). No new checks.

- **`security.php-in-uploads` now has three tiers and a signature allowlist.** Of its 36 criticals on the first fleet run, 28 were files plugins put under uploads themselves (Redux Customizer class stubs, Ajax Load More templates, Calendarize It help, Ultimate Addons icon maps, LearnDash and WPForms guards, the Mailchimp log), 6 were zero-byte remnants of a cleaned infection, and 2 were a live cookie-triggered backdoor that read exactly like the guards around it. The allowlist entries now carry a content signature (`contains` / `starts`), so a dropper named like a plugin file does not ride them; any `index.php` that only exits, comments or sends a header is a guard whatever its spelling; a file with no PHP in it is filed as **inert** at moderate; and a file whose code reads request input, decodes or evaluates strings, or runs commands is filed as a **suspected web shell** with the incident steps first. Filters: `omens_audit/uploads_allow` (entries may now be arrays), `omens_audit/uploads_shell_markers`.
- `a11y.img-alt-missing` skips WordPress core markup that is correct as printed: the image-lightbox template (`data-wp-bind--alt`) and comment avatars from `get_avatar()`.

## 1.0.2 (2026-11-07)

- **Platform counters are moderate and point at the template.** Jetpack Stats and the Pressable visit counter (Fathom Analytics, formerly Gauges; the platform injects it on every Pressable site) are now the `hosting analytics` tracker category in `data/trackers.json`, so `legal.tracker-drift` files them at moderate instead of serious and its fix names the Legal Pages privacy template's `HOSTING_ANALYTICS` block (or the Pressable Insights switch, for the counter) instead of a cookie-table row. Jake's call, 2026-11-07, after both pilots produced the same two serious findings and the fleet would have produced about 120.
- A check that returns its one `cannot-run` finding (for example `currency.plugins` when the `update_plugins` transient is empty, which `wp plugin install` leaves behind) is recorded with status `cannot-run` instead of `ran`, so the central run does not count its open tasks as re-checked. The central run guards against this on its side as well.

## 1.0.1 (2026-11-07)

Fixes from the post-launch audit (Dev Team review, 2026-11-07). No new checks.

- **The host name decides the environment in both directions.** 1.0.0 trusted it one way only, so a production domain whose `WP_ENVIRONMENT_TYPE` still said `staging` (a sandbox that became production by DNS) was told at serious severity to discourage search engines. `seo.indexing-blocked`, `seo.sitemap` and `security.debug-display` now key on one `effective_environment` (production or staging by host, local by the constant); the constant mismatch stays an info finding.
- **A partial WP-CLI run is never stored.** `wp omens-audit run --check=` or `--family=` without `--dry-run` used to overwrite the latest run, so the central run read a one-check record and marked every other task "not checked". Partial runs now imply `--dry-run`; `--store` overrides.
- `security.debug-display` ignores PHP's `display_errors` ini value under the CLI SAPI, which PHP forces on, so a host without a CLI ini override no longer files a false serious finding.
- Plugin icon (the yellow calendar-check tile) replaces the skeleton placeholder and is served to the Plugins and Updates screens through the update checker.

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
