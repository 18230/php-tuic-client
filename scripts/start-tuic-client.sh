#!/usr/bin/env bash
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
APP_ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

TUIC_CONFIG="${TUIC_CONFIG:-}"
TUIC_NODE="${TUIC_NODE:-}"
TUIC_NODE_NAME="${TUIC_NODE_NAME:-}"
TUIC_SOCKS_LISTEN="${TUIC_SOCKS_LISTEN:-127.0.0.1:1080}"
QUICHE_LIB="${QUICHE_LIB:-}"

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

args+=("--listen=$TUIC_SOCKS_LISTEN")

if [[ -n "$QUICHE_LIB" ]]; then
  args+=("--quiche-lib=$QUICHE_LIB")
fi

exec "${args[@]}"
