#!/usr/bin/env bash
# Deploy the committed code to FourSys hosting over SSH.
#
#   bash scripts/deploy.sh            # deploys HEAD
#   bash scripts/deploy.sh master     # deploys a branch, tag or commit
#
# Only committed files are sent (git archive), so uncommitted edits never go live.
# The server keeps its own .env.local; this script never sends or overwrites it.
#
# Needs an SSH host alias "bukidbasket" (see docs/deployment.md, section 6),
# or set DEPLOY_HOST to any ssh destination.
set -euo pipefail

REF="${1:-HEAD}"
HOST="${DEPLOY_HOST:-bukidbasket}"
SSH=(ssh -o BatchMode=yes)
[ -n "${DEPLOY_PORT:-}" ] && SSH+=(-p "$DEPLOY_PORT")

cd "$(git rev-parse --show-toplevel)"

SHA="$(git rev-parse --short "$REF^{commit}")"
if [ "$REF" = "HEAD" ] && [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "Note: you have uncommitted changes. They will NOT be deployed; only commit $SHA is." >&2
fi

echo "Deploying $REF ($SHA) to $HOST ..."

# 1. Upload only the folders the app needs at runtime into a temp folder on the server.
STAGE="$(git archive --format=tar "$REF" public src views database scripts \
  | "${SSH[@]}" "$HOST" 'set -e; d=$(mktemp -d "$HOME/tmp/deploy.XXXXXX"); tar -x -C "$d"; echo "$d"')"

# 2. Run the server-side steps (lint, sync, shim) from this checkout's copy of the script.
"${SSH[@]}" "$HOST" "bash -s -- '$STAGE' '$SHA'" < scripts/server-deploy.sh
