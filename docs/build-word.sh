#!/usr/bin/env bash
# Builds Word (.docx) copies of the guides into docs/word/ (PRD 17: user guides exportable to Word).
# Needs pandoc: https://pandoc.org/installing.html
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p word
for f in user-guides/*.md go-live-checklist.md backups-and-monitoring.md deploy-cpanel.md connector-integration.md; do
  [ "$(basename "$f")" = "README.md" ] && continue
  out="word/$(basename "${f%.md}").docx"
  pandoc "$f" -f gfm -t docx -o "$out"
  echo "built $out"
done
