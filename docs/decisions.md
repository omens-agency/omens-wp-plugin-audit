# Decisions

Running log of decisions made while building the Omens Site Audit. The two one-way doors (fleet scope, BugHerd as system of record) are in ADR 002 in `Library/Web Development/spec/decisions/` and are not restated here.

## 2026-11-06: Pre-build loop closure (Jake took every recommended default)

Twelve questions were put to Jake after the plan audit. He took all defaults.

| # | Question | Decision |
|---|----------|----------|
| 1 | Transport from Pressable sites | **SSH from Jake's Mac with per-site Pressable credentials is the primary transport.** The Pressable MCP trims succeeded WP-CLI output to 500 characters in the operations log (tighter than the 1 KB in project memory), so chunked export over the MCP (S1 option a) is not viable. The MCP is kept for vulnerability alerts and the fleet install. S1 measures SSH on both pilots. |
| 2 | Custom pilot | **omens.agency** for M1 to M5. A live client starter build joins at M6 if it is on Pressable with a repo. Production theme and repo to be confirmed during S1. (Superseded in part by the M1 note on build-type detection below.) |
| 3 | Purchased-theme pilot | A client law-firm site named in the internal plan: Pressable, Yoast, CookieYes, a Studio copy for M1 and M2, and a known critical CVE from the 2026-09-04 audit. One alternative was named; a third candidate is out because it is not on Pressable. |
| 4 | BugHerd sandbox | Create "Omens Audit Sandbox" **when M4 starts**, not before. |
| 5 | Repo | Create `omens-agency/omens-wp-plugin-audit` **at M1, public, topic `plugin`**, following `omens-wp-plugin-security`. Public skips the private update PAT. |
| 6 | Local tooling | Install PHPCS plus the WordPress Coding Standards with Homebrew. |
| 7 | Working mode | This session builds M0 onward and stops for review after each milestone. |
| 8 | Rollout order (PLAN Q1) | Batches by plan tier, Comply and Boost first; no-retainer sites last or not at all (see 12). |
| 9 | PageSpeed Insights key (PLAN Q2) | Key file at `~/.omens/psi.key`, mode 600, outside every synced folder. |
| 10 | Roll-ups (PLAN Q3) | Short weekly and monthly post to `#web-development-hub`; files only for everything else. |
| 11 | Triage (PLAN Q4) | Lexi triages. Critical within 2 business days, serious within 10, the rest in the monthly batch. |
| 12 | Billing and admin screen (PLAN Q5, Q6) | Audit fixes are inside the retainer by tier; sites with no retainer are not enabled. Admin screen: Omens accounts only on client sites by default, every administrator on omens.agency. |

Where per-site SSH credentials live was asked and not answered separately; S1 records what exists today (one key, `pressable-edh`, in `~/.ssh/config`) and proposes a home for the rest.

## 2026-11-06: M0 outcomes

- **Transport is SSH with the account key, not per-site credentials.** Pressable keys are account-level and sign in as each site's owner SFTP username, so `sites.csv` carries `ssh_user` (from `list_site_ftp_accounts`, `owner: true`) and no secret. Details in `docs/spikes.md`.
- **`export --chunk` options are dropped at M1.** Single-shot export over SSH moved 200 findings in under 10 seconds per site.
- **`run` prints one progress line per check**, so an MCP-invoked run is never killed by the 30-second no-output stall detector.
- **BugHerd filing is two calls** (`create_task`, then `update_task` with `guest_visible: false`), since `create_task` has no visibility parameter.
- **Comment attribution:** audit comments carry no "(via ...)" suffix; Jake's global rule overrides the BugHerd MCP's instruction.
- **Tooling installed:** PHPCS, Composer, WordPress Coding Standards via Homebrew.

## 2026-11-06: M1 decisions

- **Build type is keyed on `OMENS_STARTER_VERSION`, not on `library/php/omens.php`.** The plan's marker misclassifies: legacy Omens themes from the Three Omens era carry a `library/php/omens.php` with none of the starter core. The production omens.agency theme ("Omens (Reskin)" 2.1) is one of them, and it has no GitHub repo, so **omens.agency is `build_type = purchased`** and cannot be the M5 pilot for the task-to-PR workflow. The detector reports `origin` (`starter`, `omens-legacy`, `third-party`) in `build_evidence`, and `omens_audit/build_type` lets a legacy theme with a repo declare itself `custom`. Verified on three Studio copies: a 1.9.3 starter build detects `custom`; the reskin and a legacy law-firm theme detect `purchased` with the `omens-legacy` evidence.
- **The planning record stays out of the public repo.** `PLAN.md`, `CHECKS.md`, `KICKOFF.md` and `docs/spikes.md` name clients, Pressable site IDs and SSH usernames, so they are gitignored and live only in the synced Library folder. `docs/decisions.md` is in the repo and names no client.
- **Interface and base class are separate files** (`includes/check.php`, `includes/check-base.php`) to satisfy the WordPress standard's one-structure-per-file rule.
- **Hook names keep the slash namespace** (`omens_audit/checks`), matching `omens/security/*` and `omens_media/*`; the PHPCS sniff that wants underscores is excluded in `phpcs.xml.dist` with the reason.
- **The monthly fallback self-heals at `init`**: if the filter says on and no event exists, it is scheduled; if the filter says off and one exists, it is cleared. Found by the smoke test, where a re-run on an already-active plugin never fired the activation hook.
- **Admin screen default implements decision 12**: visible to `omens.agency` and `threeomens.com` accounts, or to every administrator when the site host is `omens.agency`; `omens_audit/admin_domains` and `omens_audit/show_admin` change it.
- **`export --chunk` was never built** (M0 decision), and `run` takes a comma-separated `--check` list so one fix can be verified with exactly the checks it touched.
- **Tooling is `studio wp --path <site>`** for the local Studio copies; `tools/smoke.sh` symlinks the checkout into a site and refuses to call WordPress's recursive delete on the symlink.
