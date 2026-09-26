#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
mark="$repo_root/backend_laravel/public/images/brand/gym-atlas-mark.png"
lockup="$repo_root/backend_laravel/public/images/brand/gym-atlas-lockup.png"
generated="$repo_root/backend_laravel/public/images/brand/generated"

if ! command -v magick >/dev/null 2>&1; then
    echo "ImageMagick is required to generate the brand asset sizes." >&2
    exit 1
fi

mkdir -p "$generated"

mkdir -p "$repo_root/gym_flutter_core/assets/branding"
magick "$mark" -strip -resize 512x512 "$repo_root/gym_flutter_core/assets/branding/gym_atlas_mark.png"
magick "$lockup" -strip -resize 1400x467 "$repo_root/gym_flutter_core/assets/branding/gym_atlas_lockup.png"

magick "$lockup" -channel RGB -fill white -colorize 100% "$generated/gym-atlas-lockup-on-dark.png"
magick -size 1024x1024 xc:'#07152F' \( "$mark" -resize 700x700 \) -gravity center -composite "$generated/app-icon-master.png"
magick -size 1024x1024 xc:none \( "$mark" -resize 680x680 \) -gravity center -composite "$generated/app-icon-foreground.png"
magick "$generated/app-icon-master.png" -resize 512x512 "$generated/app-icon-512.png"
magick "$generated/app-icon-master.png" -resize 180x180 "$repo_root/backend_laravel/public/images/public-site/brand/apple-touch-icon.png"
magick "$generated/app-icon-master.png" -resize 64x64 "$repo_root/backend_laravel/public/images/public-site/brand/atlas-mark-64.png"
magick "$generated/app-icon-master.png" -resize 512x512 "$repo_root/backend_laravel/public/images/public-site/brand/atlas-mark-512.png"
magick "$generated/app-icon-master.png" -define icon:auto-resize=64,48,32,16 "$repo_root/backend_laravel/public/favicon.ico"

for windows_icon in \
    "$repo_root/flutter_member_app/windows/runner/resources/app_icon.ico" \
    "$repo_root/flutter_trainer_app/windows/runner/resources/app_icon.ico" \
    "$repo_root/flutter_admin_app/windows/runner/resources/app_icon.ico"; do
    if [[ -f "$windows_icon" ]]; then
        magick "$generated/app-icon-master.png" -define icon:auto-resize=256,128,64,48,32,16 "$windows_icon"
    fi
done

while IFS= read -r icon; do
    width="$(magick identify -format '%w' "$icon")"
    height="$(magick identify -format '%h' "$icon")"
    source="$generated/app-icon-master.png"
    if [[ "$(basename "$icon")" == *foreground* ]]; then
        source="$generated/app-icon-foreground.png"
    fi
    magick "$source" -resize "${width}x${height}!" "$icon"
done < <(find \
    "$repo_root/flutter_member_app" \
    "$repo_root/flutter_trainer_app" \
    "$repo_root/flutter_admin_app" \
    -type f -name '*.png' \
    \( -path '*/AppIcon.appiconset/*' -o -path '*/mipmap-*/*' -o -path '*/web/icons/*' -o -path '*/web/favicon.png' \) \
    ! -path '*/build/*' \
    ! -path '*/.dart_tool/*')

echo "Gym Atlas brand assets generated."
