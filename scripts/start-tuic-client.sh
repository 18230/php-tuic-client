#!/usr/bin/env bash
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
APP_ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

TUIC_CONFIG="${TUIC_CONFIG:-}"
TUIC_NODE="${TUIC_NODE:-}"
TUIC_NODE_NAME="${TUIC_NODE_NAME:-}"
TUIC_SOCKS_LISTEN="${TUIC_SOCKS_LISTEN:-127.0.0.1:1080}"
TUIC_ALLOW_IP="${TUIC_ALLOW_IP:-}"
TUIC_MAX_CONNECTIONS="${TUIC_MAX_CONNECTIONS:-}"
TUIC_CONNECT_TIMEOUT="${TUIC_CONNECT_TIMEOUT:-}"
TUIC_IDLE_TIMEOUT="${TUIC_IDLE_TIMEOUT:-}"
TUIC_HANDSHAKE_TIMEOUT="${TUIC_HANDSHAKE_TIMEOUT:-}"
TUIC_STATUS_FILE="${TUIC_STATUS_FILE:-}"
TUIC_STATUS_INTERVAL="${TUIC_STATUS_INTERVAL:-}"
TUIC_LOG_FILE="${TUIC_LOG_FILE:-}"
TUIC_PID_FILE="${TUIC_PID_FILE:-}"
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

if [[ -n "$TUIC_ALLOW_IP" ]]; then
  args+=("--allow-ip=$TUIC_ALLOW_IP")
fi

if [[ -n "$TUIC_MAX_CONNECTIONS" ]]; then
  args+=("--max-connections=$TUIC_MAX_CONNECTIONS")
fi

if [[ -n "$TUIC_CONNECT_TIMEOUT" ]]; then
  args+=("--connect-timeout=$TUIC_CONNECT_TIMEOUT")
fi

if [[ -n "$TUIC_IDLE_TIMEOUT" ]]; then
  args+=("--idle-timeout=$TUIC_IDLE_TIMEOUT")
fi

if [[ -n "$TUIC_HANDSHAKE_TIMEOUT" ]]; then
  args+=("--handshake-timeout=$TUIC_HANDSHAKE_TIMEOUT")
fi

if [[ -n "$TUIC_STATUS_FILE" ]]; then
  args+=("--status-file=$TUIC_STATUS_FILE")
fi

if [[ -n "$TUIC_STATUS_INTERVAL" ]]; then
  args+=("--status-interval=$TUIC_STATUS_INTERVAL")
fi

if [[ -n "$TUIC_LOG_FILE" ]]; then
  args+=("--log-file=$TUIC_LOG_FILE")
fi

if [[ -n "$TUIC_PID_FILE" ]]; then
  args+=("--pid-file=$TUIC_PID_FILE")
fi

if [[ -n "$QUICHE_LIB" ]]; then
  args+=("--quiche-lib=$QUICHE_LIB")
fi

exec "${args[@]}"
