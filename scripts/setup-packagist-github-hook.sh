#!/usr/bin/env sh
set -eu

if command -v gh >/dev/null 2>&1; then
  GH_BIN="gh"
elif [ -x "/usr/local/bin/gh" ]; then
  GH_BIN="/usr/local/bin/gh"
else
  echo "GitHub CLI (gh) was not found. Install gh and authenticate before running this script." >&2
  exit 1
fi

REPOSITORY="${1:-${GITHUB_REPOSITORY:-}}"
USERNAME="${PACKAGIST_USERNAME:-}"
API_TOKEN="${PACKAGIST_API_TOKEN:-}"
FORCE="${FORCE_PACKAGIST_HOOK:-0}"
remote=""

if [ -z "$REPOSITORY" ]; then
  if remote="$(git remote get-url origin 2>/dev/null)"; then
    REPOSITORY="$(printf '%s' "$remote" | sed -E 's#^.*github\.com[:/]([^/]+/[^/.]+)(\.git)?$#\1#')"
  fi
fi

if [ -z "$REPOSITORY" ] || [ "$REPOSITORY" = "$remote" ]; then
  echo "Repository slug is required. Pass owner/repo as the first argument or configure origin." >&2
  exit 1
fi

if [ -z "$USERNAME" ]; then
  USERNAME="$(printf '%s' "$REPOSITORY" | cut -d/ -f1)"
fi

if [ -z "$API_TOKEN" ]; then
  echo "PACKAGIST_API_TOKEN is required." >&2
  exit 1
fi

PAYLOAD_URL="https://packagist.org/api/github?username=${USERNAME}"

HOOK_ID="$("$GH_BIN" api "repos/${REPOSITORY}/hooks" --jq ".[] | select(.config.url == \"${PAYLOAD_URL}\") | .id" | head -n 1 || true)"

BODY="$(printf '{"active":true,"events":["push"],"config":{"url":"%s","content_type":"json","secret":"%s","insecure_ssl":"0"}}' "$PAYLOAD_URL" "$API_TOKEN")"

if [ -n "$HOOK_ID" ] && [ "$FORCE" != "1" ]; then
  echo "Packagist webhook already exists for ${REPOSITORY}"
  echo "Set FORCE_PACKAGIST_HOOK=1 to update the stored Packagist API token."
  exit 0
fi

if [ -n "$HOOK_ID" ]; then
  printf '%s' "$BODY" | "$GH_BIN" api "repos/${REPOSITORY}/hooks/${HOOK_ID}" --method PATCH --input - >/dev/null
  echo "Packagist webhook updated for ${REPOSITORY}"
else
  BODY="$(printf '{"name":"web","active":true,"events":["push"],"config":{"url":"%s","content_type":"json","secret":"%s","insecure_ssl":"0"}}' "$PAYLOAD_URL" "$API_TOKEN")"
  printf '%s' "$BODY" | "$GH_BIN" api "repos/${REPOSITORY}/hooks" --method POST --input - >/dev/null
  echo "Packagist webhook created for ${REPOSITORY}"
fi

echo "Payload URL: ${PAYLOAD_URL}"
