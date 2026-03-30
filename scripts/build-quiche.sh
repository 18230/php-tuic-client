#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-0.26.1}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK_DIR="${WORK_DIR:-$ROOT_DIR/runtime/quiche-src}"

case "$(uname -s)" in
  Darwin*)
    PLATFORM="macos"
    LIB_NAME="libquiche.dylib"
    ;;
  *)
    PLATFORM="linux"
    LIB_NAME="libquiche.so"
    ;;
esac

case "$(uname -m)" in
  x86_64|amd64)
    ARCHITECTURE="x64"
    ;;
  arm64|aarch64)
    ARCHITECTURE="arm64"
    ;;
  *)
    ARCHITECTURE="$(uname -m | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g')"
    ;;
esac

TRIPLET="${PLATFORM}-${ARCHITECTURE}"
OUTPUT_DIR="${OUTPUT_DIR:-$ROOT_DIR/resources/native/$TRIPLET}"
ARCHIVE_PATH="${TMPDIR:-/tmp}/quiche-${VERSION}.tar.gz"
EXTRACT_DIR="${TMPDIR:-/tmp}/quiche-${VERSION}"

command -v cargo >/dev/null 2>&1 || { echo "cargo not found in PATH" >&2; exit 1; }
command -v cmake >/dev/null 2>&1 || { echo "cmake not found in PATH" >&2; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "curl not found in PATH" >&2; exit 1; }
command -v tar >/dev/null 2>&1 || { echo "tar not found in PATH" >&2; exit 1; }

rm -rf "$WORK_DIR" "$EXTRACT_DIR"
mkdir -p "$OUTPUT_DIR"

curl -L "https://github.com/cloudflare/quiche/archive/refs/tags/${VERSION}.tar.gz" -o "$ARCHIVE_PATH"
tar -xzf "$ARCHIVE_PATH" -C "${TMPDIR:-/tmp}"
mv "$EXTRACT_DIR" "$WORK_DIR"

pushd "$WORK_DIR" >/dev/null
cargo build -p quiche --release --features ffi
cp "target/release/$LIB_NAME" "$OUTPUT_DIR/$LIB_NAME"
popd >/dev/null

echo "Built $LIB_NAME to $OUTPUT_DIR ($TRIPLET)"
