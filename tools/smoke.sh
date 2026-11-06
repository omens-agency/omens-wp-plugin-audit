#!/usr/bin/env bash
# M1 smoke test against a local WordPress site (a Studio copy, never production).
#
#   tools/smoke.sh ~/Studio/omens
#   tools/smoke.sh ~/Studio/higgs-fletcher-mack --cleanup   # also unlink the plugin at the end
#
# Symlinks this checkout into the site's plugins folder, activates it, runs the
# three WP-CLI commands and asserts: a finding in the schema is stored, export
# returns it, a stored run from another host is ignored, and uninstall removes
# everything. Uses `studio wp` when the site is a Studio site, plain `wp` otherwise.
# Never calls `wp plugin uninstall` or `delete` on the symlink: WordPress's
# recursive delete would follow it into this checkout.
set -euo pipefail
REPO=$(cd "$(dirname "$0")/.." && pwd)
SLUG=$(basename "$REPO")
SITE="${1:?site path, e.g. ~/Studio/omens}"
SITE=$(cd "$SITE" && pwd)
CLEANUP="${2:-}"
PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "  ok   $1"; }
fail() { FAIL=$((FAIL+1)); echo "  FAIL $1"; }

if [[ "$SITE" == "$HOME/Studio/"* ]] && command -v studio >/dev/null; then
	wpcli() { studio wp --path "$SITE" "$@" 2>/dev/null; }
else
	wpcli() { wp --path="$SITE" "$@" 2>/dev/null; }
fi

echo "== $SLUG on $SITE"
LINK="$SITE/wp-content/plugins/$SLUG"
if [ ! -e "$LINK" ]; then ln -s "$REPO" "$LINK"; echo "  linked $LINK"; fi
[ -L "$LINK" ] || { echo "  $LINK exists and is not a symlink; refusing to touch it."; exit 1; }

wpcli plugin activate "$SLUG" >/dev/null && ok "activate" || fail "activate"

echo "-- checks"
CHECKS=$(wpcli omens-audit checks --format=json)
echo "$CHECKS" | python3 -c 'import json,sys; d=json.load(sys.stdin); assert any(c["id"]=="security.file-edit-enabled" for c in d), d' && ok "checks lists security.file-edit-enabled" || fail "checks listing"

echo "-- run"
wpcli omens-audit run | sed 's/^/     /'
RUN=$(wpcli omens-audit export --part=run)
python3 - "$RUN" <<'PY' && ok "run stored, schema fields present, host and environment match the site" || fail "run shape"
import json, sys
r = json.loads(sys.argv[1])
for k in ("schema","plugin_version","run_at","host","environment","build_type","build_evidence","theme","sample","fetched","checks","findings","counts","inventory","duration_ms"):
    assert k in r, "missing " + k
assert "security.file-edit-enabled" in r["checks"], r["checks"]
assert r["host"] and r["environment"], r
assert isinstance(r["inventory"]["admins"], list) and isinstance(r["inventory"]["mu_plugins"], list)
f = r["findings"]
assert isinstance(f, list)
if f:
    first = f[0]
    for k in ("fingerprint","check","family","source","severity","title","summary","detail","url","selector","criterion","fix","evidence","check_version"):
        assert k in first, "finding missing " + k
    for k in ("route","where","steps","confidence"):
        assert k in first["fix"], "fix missing " + k
    assert len(first["fingerprint"]) == 40
print("     build_type=%s (%s); %d findings; sample %d urls; %d ms" % (r["build_type"], r["build_evidence"], len(f), len(r["sample"]), r["duration_ms"]))
PY

echo "-- export parts"
for part in findings sample inventory meta; do
	wpcli omens-audit export --part=$part | python3 -c 'import json,sys; json.load(sys.stdin)' && ok "export --part=$part is JSON" || fail "export --part=$part"
done

echo "-- single check, dry run"
BEFORE=$(wpcli option get omens_audit_last_run --format=json | python3 -c 'import json,sys; print(json.load(sys.stdin)["run_at"])')
wpcli omens-audit run --check=security.file-edit-enabled --dry-run >/dev/null
AFTER=$(wpcli option get omens_audit_last_run --format=json | python3 -c 'import json,sys; print(json.load(sys.stdin)["run_at"])')
[ "$BEFORE" = "$AFTER" ] && ok "dry run did not replace the stored run" || fail "dry run stored something"

echo "-- fingerprint stability"
FP1=$(wpcli omens-audit export --part=findings | python3 -c 'import json,sys; print(sorted(f["fingerprint"] for f in json.load(sys.stdin)))')
wpcli omens-audit run >/dev/null
FP2=$(wpcli omens-audit export --part=findings | python3 -c 'import json,sys; print(sorted(f["fingerprint"] for f in json.load(sys.stdin)))')
[ "$FP1" = "$FP2" ] && ok "fingerprints identical across two runs" || fail "fingerprints changed between runs"

echo "-- foreign run is ignored"
wpcli eval '$r = get_option("omens_audit_last_run"); $r["host"] = "staging.example.invalid"; update_option("omens_audit_last_run", $r, false);'
if wpcli omens-audit export --part=meta >/dev/null 2>&1; then fail "export accepted a run from another host"; else ok "export refused a run from another host"; fi
wpcli omens-audit run >/dev/null && ok "run replaced the foreign run" || fail "re-run"

echo "-- cron fallback"
NEXT=$(wpcli cron event list --hook=omens_audit_monthly_run --field=hook 2>/dev/null | head -1 || true)
[ "$NEXT" = "omens_audit_monthly_run" ] && ok "monthly fallback scheduled" || fail "monthly fallback not scheduled"

echo "-- uninstall (deactivate first, as WordPress does; the active plugin would reschedule its own cron at init)"
wpcli plugin deactivate "$SLUG" >/dev/null && ok "deactivate" || fail "deactivate"
wpcli eval 'define("WP_UNINSTALL_PLUGIN", true); include WP_PLUGIN_DIR . "/'"$SLUG"'/uninstall.php";'
wpcli option get omens_audit_last_run >/dev/null 2>&1 && fail "option survived uninstall" || ok "uninstall removed the option"
NEXT=$(wpcli cron event list --hook=omens_audit_monthly_run --field=hook 2>/dev/null | head -1 || true)
[ -z "$NEXT" ] && ok "uninstall cleared the cron event" || fail "cron event survived uninstall"

if [ "$CLEANUP" = "--cleanup" ]; then
	wpcli plugin deactivate "$SLUG" >/dev/null || true
	rm "$LINK" && echo "  unlinked"
else
	wpcli plugin activate "$SLUG" >/dev/null 2>&1 || true
	echo "  (plugin left active and linked for the next milestone; pass --cleanup to remove)"
fi

echo "== $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
