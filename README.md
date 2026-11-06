<img src="assets/icon-128x128.png" width="64" height="64" alt="" align="left">

# Omens Site Audit

A stateless audit sensor for WordPress sites Omens maintains. It runs a fixed set of read-only checks (security and currency, SEO and indexability, server-side accessibility, legal and consent drift), keeps **only the latest run** in one option, and exports it as JSON for the central maintenance audit, which files findings in BugHerd and keeps every baseline off the site. Repo: `github.com/omens-agency/omens-wp-plugin-audit` (public, so sites install and update from it without a token). Plugin folder and slug: `omens-wp-plugin-audit`. WP-CLI command: `wp omens-audit`.

It runs on every kind of site we host: starter-theme builds with a repo, legacy Omens themes, and purchased themes with a child theme and nothing else. What differs is the **route** on each finding: on a build with a repo a template defect is `code` (a PR), on everything else it becomes `human` (someone decides).

## What it is not

- **Not a vulnerability scanner.** Known CVEs come from the host's alerts, confirmed on the filesystem, in the central run. The plugin reports *currency* (updates pending), which is a different measurement.
- **Not a history.** One run, replaced each time. BugHerd owns the lifecycle of a finding; the central run keeps baselines (administrators, mu-plugins) where an attacker with admin access cannot edit them.
- **Not a fixer.** Every check is a pure read. Nothing is deleted, changed or sent anywhere.
- **No outbound HTTP** except loopback requests to the site's own `home_url()`, sequentially, 10 seconds each, with a cache-bust query string. No secrets. No REST routes.

## Commands

| Command | Does |
|---|---|
| `wp omens-audit run [--family=<f>] [--check=<id>[,<id>]] [--dry-run] [--format=table\|json]` | Runs the checks and stores the run. Prints one line per check as it goes. `--check` re-runs exactly one check to verify a fix; `--dry-run` prints without storing. |
| `wp omens-audit export [--part=run\|findings\|sample\|inventory\|meta] [--pretty]` | Prints the latest run as JSON. Exits 1 if the stored run belongs to another host or environment (a database that was copied), with the reason. |
| `wp omens-audit checks [--format=table\|json\|csv]` | Lists the registered checks, their family, default severity, version and whether each applies here. |

Tools > Site Audit in wp-admin shows the same run and has a nonce-checked **Run now**. The screen is visible to Omens accounts by default (see `omens_audit/show_admin`).

## The run

```json
{
  "schema": 1, "plugin_version": "0.1.0", "run_at": "2026-11-06T22:10:00+00:00",
  "host": "example.com", "environment": "production",
  "build_type": "custom", "build_evidence": "OMENS_STARTER_VERSION 1.12.0 defined by theme example",
  "theme": { "stylesheet": "example", "name": "Example", "version": "1.4.0", "author": "Omens", "parent": "", "starter": "1.12.0" },
  "wp_version": "7.1.4", "php_version": "8.4.27", "locale": "en_US",
  "sample": [ "https://example.com/", "..." ], "fetched": [ "..." ],
  "checks": { "security.file-edit-enabled": { "version": "1.0.0", "status": "ran", "ms": 1 } },
  "findings": [ { "...": "see below" } ],
  "counts": { "total": 1, "severity": { "critical": 0, "serious": 1, "moderate": 0, "minor": 0, "info": 0 }, "family": { "security": 1 } },
  "inventory": { "admins": [ { "id": 1, "login": "…", "email_domain": "example.com", "registered": "…", "roles": ["administrator"], "last_login": "" } ], "mu_plugins": [ { "file": "…", "name": "…", "version": "…", "sha1": "…", "size": 0 } ] },
  "fetch_seconds": 0, "duration_ms": 73
}
```

`sample` is the exact list of URLs the rendered-HTML checks were given and `fetched` the ones that answered; the central run uses them to decide whether a finding was **re-checked** before it moves anything to review.

### A finding

```json
{
  "fingerprint": "sha1(host|check|subject)",
  "check": "security.file-edit-enabled", "subject": "DISALLOW_FILE_EDIT",
  "family": "security", "source": "plugin", "severity": "serious",
  "title": "Theme and plugin file editors are reachable in wp-admin",
  "summary": "one sentence", "detail": "what was found, trimmed to 300 characters",
  "url": "", "selector": "", "criterion": "none",
  "fix": { "route": "config", "where": "wp-config.php", "steps": [ "ordered, concrete" ], "confidence": "high" },
  "evidence": [ "what was measured" ],
  "check_version": "1.0.0"
}
```

The fingerprint never includes the run date, so the same problem fingerprints the same way every month. Severities: `critical`, `serious`, `moderate`, `minor`, `info` (`info` is never filed). Routes: `code`, `content`, `config`, `vendor`, `human`.

## Build type

`custom` means a theme scaffolded from `omens-wp-starter-theme`, detected by the `OMENS_STARTER_VERSION` constant its `functions.php` defines. Everything else is `purchased`, including legacy Omens themes that predate the starter (`build_evidence` says which). On `purchased` sites every `code` route becomes `human` with "theme or child theme; no repo" prepended to `where`. A legacy theme that does have a repo can say so:

```php
add_filter( 'omens_audit/build_type', fn( $t ) => array( 'type' => 'custom', 'origin' => 'omens-legacy', 'evidence' => 'repo omens-agency/example-wp-theme' ) );
```

## Configuration

Everything is a filter, in the theme or a small site plugin. Never edit the plugin's own files; every update replaces them.

| Filter | Default | Purpose |
|---|---|---|
| `omens_audit/checks` | all registered checks | Add, replace or remove checks (keyed by ID). |
| `omens_audit/severity` | the check's default | Change a finding's severity on this site: `( $severity, $check_id, $finding )`. |
| `omens_audit/sample_urls` | home, legal pages, one entry per public post type, five most recently modified pages | Add or replace sample URLs (own host only). |
| `omens_audit/sample_cap` | 25 | Cap on the sample. |
| `omens_audit/fetch_args` | 10 s timeout, 3 redirects | `wp_remote_get()` arguments for loopback. |
| `omens_audit/legal_slugs`, `omens_audit/legal_pages` | web-legal-pages slugs; core's privacy page | Where the terms and accessibility statement live. |
| `omens_audit/build_type` | detected | Override the build type (shape above). |
| `omens_audit/schedule` | true | The monthly WP-Cron fallback. The central run is the real scheduler. |
| `omens_audit/capability` | `manage_options` | Capability for the screen and Run now. |
| `omens_audit/admin_domains` | `omens.agency`, `threeomens.com` | Email domains that see Tools > Site Audit. |
| `omens_audit/show_admin` | Omens account, or the site is omens.agency | Final say on who sees the screen: `( $show, $user )`. |
| `omens_audit/last_login_keys` | common plugin meta keys | Where to read a last-login timestamp for the admin inventory. |
| `omens_audit/auto_update` | true | Opt out of unattended updates (they still show in Plugins > Updates). |

## Writing a check

One class per check in `checks/<family>/`, extending `Omens_Audit_Check_Base`: `id()`, `family()`, `title()`, `severity()`, optionally `version()` and `applies( $ctx )`, and `run( $ctx )` returning findings from `$this->finding( array( 'subject' => …, 'title' => …, … ) )`. `$ctx->sample()` is the URL list and `$ctx->fetch( $url )` the loopback fetcher (cached per run; a failure is recorded once and surfaces as `run.fetch-failed`). A check that cannot run returns `array( $this->cannot_run( 'why' ) )`. A check that throws is caught and reported as `run.check-failed`; the rest of the run continues. Every check ships with two fixtures, one that fires and one that does not, under `tests/`.

## Installing and updating

```bash
wp plugin install https://github.com/omens-agency/omens-wp-plugin-audit/releases/latest/download/omens-wp-plugin-audit.zip --activate
```

The plugin updates itself from GitHub releases through Plugin Update Checker (vendored). Only the release asset named `omens-wp-plugin-audit.zip` is accepted, never GitHub's source tarball, and full releases apply themselves on every site within about a day. `tools/release.sh <version>` publishes a pre-release; after a staging install and the smoke test, `VERIFIED=1 tools/promote.sh <version>` makes it the full release.

## Developing

- `tools/smoke.sh ~/Studio/<site>` symlinks the checkout into a local site and runs the M1 assertions (activation, the three commands, schema, dry run, fingerprint stability, foreign-run refusal, cron, uninstall). Local sites only.
- `php -l` on every file and `phpcs` (WordPress-Extra, `phpcs.xml.dist`) before a commit.
- State: one option, `omens_audit_last_run`, not autoloaded, keyed by host and environment. If a change needs more state than that, stop and reread the plan.

## Record

Build decisions are in `docs/decisions.md`. The plan, check catalog and spike measurements live in the Omens Library beside this checkout and are not in the repo.
