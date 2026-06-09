#!/usr/bin/env bash
set -euo pipefail

REMOTE="pierre@live.uuu.ee"
DEST="/home/pierre/html/emic"

SCP="scp -q"

echo "Deploying to ${REMOTE}:${DEST} ..."

# Top-level files
$SCP search/index.php search/style.css "${REMOTE}:${DEST}/"

# Subdirectories (recursive)
$SCP -r search/api search/js "${REMOTE}:${DEST}/"

# Input folder
$SCP -r input "${REMOTE}:${DEST}/"

echo "Done."
