#!/usr/bin/env sh
set -eu

PHP_BIN="${PHP_BIN:-php}"
APP_ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

: "${TUIC_SERVER:?TUIC_SERVER is required}"
: "${TUIC_PORT:?TUIC_PORT is required}"
: "${TUIC_UUID:?TUIC_UUID is required}"
: "${TUIC_PASSWORD:?TUIC_PASSWORD is required}"

TUIC_SNI="${TUIC_SNI:-}"
TUIC_ALPN="${TUIC_ALPN:-h3}"
TUIC_UDP_RELAY_MODE="${TUIC_UDP_RELAY_MODE:-native}"
TUIC_CONGESTION_CONTROLLER="${TUIC_CONGESTION_CONTROLLER:-bbr}"
TUIC_ALLOW_INSECURE="${TUIC_ALLOW_INSECURE:-0}"
TUIC_LOCAL="${TUIC_LOCAL:-127.0.0.1:1080}"
TUIC_LOG_LEVEL="${TUIC_LOG_LEVEL:-info}"
TUIC_DRY_RUN="${TUIC_DRY_RUN:-1}"

set -- \
  "$PHP_BIN" "$APP_ROOT/bin/tuic-client" run \
  "--server=$TUIC_SERVER" \
  "--port=$TUIC_PORT" \
  "--uuid=$TUIC_UUID" \
  "--password=$TUIC_PASSWORD" \
  "--alpn=$TUIC_ALPN" \
  "--udp-relay-mode=$TUIC_UDP_RELAY_MODE" \
  "--congestion-controller=$TUIC_CONGESTION_CONTROLLER" \
  "--allow-insecure=$TUIC_ALLOW_INSECURE" \
  "--local=$TUIC_LOCAL" \
  "--log-level=$TUIC_LOG_LEVEL"

if [ -n "$TUIC_SNI" ]; then
  set -- "$@" "--sni=$TUIC_SNI"
fi

if [ "$TUIC_DRY_RUN" = "1" ]; then
  set -- "$@" "--dry-run"
fi

exec "$@"
