#!/usr/bin/env bash
# Prints the changelog entries of one version from readme.txt as Markdown.
#
# Usage: bin/changelog.sh <version> [readme.txt]
set -euo pipefail

version=${1#v}
readme=${2:-readme.txt}

notes=$(awk -v v="$version" '
	$0 == "= " v " =" { found = 1; next }
	found && /^= .* =$/ { exit }
	found && /^== / { exit }
	found { print }
' "$readme" | sed '/^[[:space:]]*$/d')

if [ -z "$notes" ]; then
	echo "error: no changelog for $version in $readme" >&2
	exit 1
fi

printf '%s\n\n' "$notes"
echo "Install it by uploading \`brew-log-for-brewfather-$version.zip\` under Plugins → Add New → Upload Plugin. Requires WordPress 6.4+ and PHP 7.4+."
