#!/usr/bin/env bash
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
APP_ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

TUIC_CONFIG="${TUIC_CONFIG:-}"
TUIC_NODE="${TUIC_NODE:-}"
TUIC_NODE_NAME="${TUIC_NODE_NAME:-}"
TUIC_HTTP_LISTEN="${TUIC_HTTP_LISTEN:-127.0.0.1:8080}"
TUIC_SOCKS_LISTEN="${TUIC_SOCKS_LISTEN:-127.0.0.1:1080}"
TUIC_NO_HTTP="${TUIC_NO_HTTP:-0}"
TUIC_NO_SOCKS="${TUIC_NO_SOCKS:-0}"

if [[ -z "$TUIC_CONFIG" && -z "$TUIC_NODE" ]]; then
  echo "TUIC_CONFIG or TUIC_NODE is required." >&2
  exit 1
fi

args=("$PHP_BIN" "$APP_ROOT/bin/tuic-client")

if [[ -n "$TUIC_CONFIG" ]]; then
  args+=("--config=$TUIC_CONFIG")
fi

if [[ -n "$TUIC_NODE" ]]; then
  args+=("--node=$TUIC_NODE")
fi

if [[ -n "$TUIC_NODE_NAME" ]]; then
  args+=("--node-name=$TUIC_NODE_NAME")
fi

args+=("--http-listen=$TUIC_HTTP_LISTEN" "--socks-listen=$TUIC_SOCKS_LISTEN")

if [[ "$TUIC_NO_HTTP" == "1" ]]; then
  args+=("--no-http")
fi

if [[ "$TUIC_NO_SOCKS" == "1" ]]; then
  args+=("--no-socks")
fi

exec "${args[@]}"
