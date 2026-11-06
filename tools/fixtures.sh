#!/usr/bin/env bash
# Run every check's fixtures (one PHP process per case, WordPress stubbed).
#   tools/fixtures.sh            # all
#   tools/fixtures.sh a11y       # fixtures whose name contains "a11y"
set -euo pipefail
cd "$(dirname "$0")/.."
exec php tests/run.php "${1:-}"
