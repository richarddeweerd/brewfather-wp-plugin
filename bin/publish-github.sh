#!/usr/bin/env bash
# Publishes this repository to the public GitHub repo, without internal files.
#
# Clones the full history, removes PRIVATE_PATHS from every commit with
# git filter-repo, and pushes main and all tags. The rewrite is deterministic,
# so each run only adds the new commits on GitHub; a push is never forced.
#
# Usage: bin/publish-github.sh <source-repo-url> <github-push-url> [--dry-run]
# Needs git and git-filter-repo. Run by the `publish-github` CI job.
set -euo pipefail

PRIVATE_PATHS=(CLAUDE.md docs/)

source_url=$1
target_url=$2
dry_run=${3:-}

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

git clone --quiet --no-local "$source_url" "$work/repo"
cd "$work/repo"

# Local branches and tags only; remote-tracking refs would leak other branches.
git checkout --quiet -B main origin/main

args=()
for path in "${PRIVATE_PATHS[@]}"; do
	args+=(--path "$path")
done
git filter-repo --quiet --force --invert-paths "${args[@]}" --refs main $(git tag | sed 's|^|refs/tags/|')

for path in "${PRIVATE_PATHS[@]}"; do
	if git log --all --format=%H -- "$path" | grep -q .; then
		echo "error: $path is still in the history" >&2
		exit 1
	fi
done

echo "main: $(git log --oneline -1 main)"
echo "commits: $(git rev-list --count main), tags: $(git tag | tr '\n' ' ')"

if [ "$dry_run" = "--dry-run" ]; then
	git ls-tree -r --name-only main
	exit 0
fi

git push "$target_url" main:refs/heads/main
git push "$target_url" --tags
