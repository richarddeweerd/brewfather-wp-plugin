#!/usr/bin/env bash
# Builds the installable plugin zip for a git ref (tag, branch or commit).
#
# The zip holds one folder, brew-log-for-brewfather/, with the files of that
# ref minus everything listed in that ref's .distignore.
#
# Usage: bin/build-zip.sh <ref> <output-dir>
# Prints the path of the zip.
set -euo pipefail

slug=brew-log-for-brewfather
ref=$1
out=$(cd "$2" && pwd)
version=${ref#v}

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

git archive --prefix="$slug/" "$ref" | tar -x -C "$work"

while IFS= read -r path || [ -n "$path" ]; do
	path=${path%/}
	[ -z "$path" ] && continue
	rm -rf "${work:?}/$slug/$path"
done < <(git show "$ref:.distignore")
rm -f "$work/$slug/.distignore"

zip_path="$out/$slug-$version.zip"
rm -f "$zip_path"
(cd "$work" && zip -qr "$zip_path" "$slug")

echo "$zip_path"
