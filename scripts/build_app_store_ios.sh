#!/usr/bin/env bash

set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
flutter_bin="${FLUTTER_BIN:-/Users/amanagarwal/Desktop/flutter/bin/flutter}"
api_base_url="${API_BASE_URL:-https://gymatlas.in/api}"
socket_base_url="${SOCKET_BASE_URL:-https://socket.gymatlas.in}"
socket_resolve_ip="${SOCKET_RESOLVE_IP:-}"
build_name="${BUILD_NAME:-}"
build_number="${BUILD_NUMBER:-}"
system_first_path="/usr/bin:/bin:/usr/sbin:/sbin:${PATH}"

api_base_url="${api_base_url%/}"
socket_base_url="${socket_base_url%/}"

verify_realtime_endpoint() {
  local -a curl_args=(
    --fail
    --silent
    --show-error
    --max-time
    15
  )

  if [[ -n "$socket_resolve_ip" ]]; then
    local socket_host="${socket_base_url#https://}"
    socket_host="${socket_host%%/*}"
    curl_args+=(--resolve "$socket_host:443:$socket_resolve_ip")
  fi

  echo "Verifying production realtime endpoint: $socket_base_url"
  curl "${curl_args[@]}" "$socket_base_url/health" >/dev/null
  curl "${curl_args[@]}" "$socket_base_url/ready" >/dev/null
}

build_app() {
  local app_dir="$1"
  local label="$2"
  local -a build_args=(
    build
    ipa
    --release
    "--dart-define=API_BASE_URL=$api_base_url"
    "--dart-define=SOCKET_BASE_URL=$socket_base_url"
    --export-options-plist=ios/ExportOptions.plist
  )

  if [[ -n "$build_name" ]]; then
    build_args+=("--build-name=$build_name")
  fi

  if [[ -n "$build_number" ]]; then
    build_args+=("--build-number=$build_number")
  fi

  echo "Building $label for App Store Connect"
  (
    cd "$workspace_dir/$app_dir"
    env PATH="$system_first_path" "$flutter_bin" pub get
    env PATH="$system_first_path" "$flutter_bin" "${build_args[@]}"
  )
}

verify_realtime_endpoint
build_app flutter_member_app "Gym Atlas Member"
build_app flutter_trainer_app "Gym Atlas Trainer"

echo "IPAs are available under each app's build/ios/ipa directory."
