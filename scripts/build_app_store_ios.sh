#!/usr/bin/env bash

set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
flutter_bin="${FLUTTER_BIN:-/Users/amanagarwal/Desktop/flutter/bin/flutter}"
api_base_url="${API_BASE_URL:-https://gymatlas.in/api}"
socket_base_url="${SOCKET_BASE_URL:-}"

if [[ -z "$socket_base_url" ]]; then
  echo "SOCKET_BASE_URL is required so production chat receives realtime updates." >&2
  echo "Example: SOCKET_BASE_URL=https://realtime.example.com $0" >&2
  exit 64
fi

build_app() {
  local app_dir="$1"
  local label="$2"

  echo "Building $label for App Store Connect"
  (
    cd "$workspace_dir/$app_dir"
    "$flutter_bin" pub get
    "$flutter_bin" build ipa \
      --release \
      --dart-define="API_BASE_URL=$api_base_url" \
      --dart-define="SOCKET_BASE_URL=$socket_base_url" \
      --export-options-plist=ios/ExportOptions.plist
  )
}

build_app flutter_member_app "Gym Atlas Member"
build_app flutter_trainer_app "Gym Atlas Trainer"

echo "IPAs are available under each app's build/ios/ipa directory."
