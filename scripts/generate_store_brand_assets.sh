#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
icon="$repo_root/backend_laravel/public/images/brand/generated/app-icon-master.png"
mark="$repo_root/backend_laravel/public/images/brand/gym-atlas-mark.png"
store_root="$repo_root/play_store_assets"

command -v magick >/dev/null || { echo 'ImageMagick is required.' >&2; exit 1; }
[[ -f "$icon" && -f "$mark" ]] || { echo 'Gym Atlas brand source is missing.' >&2; exit 1; }

magick "$icon" -strip -depth 8 "$store_root/branding/atlas-master-icon.png"
magick "$icon" -resize 512x512! -alpha on -strip -depth 8 "PNG32:$store_root/branding/atlas-play-store-icon.png"
mkdir -p "$repo_root/app_store_assets/branding"
magick "$icon" -strip -depth 8 "$repo_root/app_store_assets/branding/gym-atlas-app-icon-1024.png"

render_feature_graphic() {
    local name="$1"
    local subtitle="$2"
    local destination="$3"
    local source="$4"

    magick -size 2048x1000 gradient:'#07152F-#172553' \
        -fill 'none' -stroke '#263B83' -strokewidth 3 \
        -draw 'circle 1660,500 1660,130' \
        -draw 'circle 1660,500 1660,40' \
        -stroke '#22366F' -draw 'circle 1660,500 1660,-70' \
        -fill '#3641F5' -stroke 'none' -draw 'roundrectangle 128,712 224,722 5,5' \
        -fill '#465FFF' -draw 'roundrectangle 240,712 336,722 5,5' \
        -fill '#7C3AED' -draw 'roundrectangle 352,712 448,722 5,5' \
        \( "$mark" -resize 820x820 \) -gravity center -geometry +590+0 -composite \
        -font '/System/Library/Fonts/Supplemental/Arial Bold.ttf' -fill white -pointsize 112 -gravity northwest -annotate +128+273 "$name" \
        -font '/System/Library/Fonts/Supplemental/Arial.ttf' -fill '#C8D5FF' -pointsize 43 -annotate +133+440 "$subtitle" \
        -alpha off -strip -depth 8 "PNG24:$source"
    magick "$source" -resize 1024x500! -alpha off -strip -depth 8 "PNG24:$destination"
}

render_feature_graphic 'Gym Atlas' 'Train with clarity. Track every step.' \
    "$store_root/member/feature-graphic-1024x500.png" \
    "$store_root/member/feature-graphic-source.png"
render_feature_graphic 'Gym Atlas Coach' 'Plan training. Guide progress.' \
    "$store_root/trainer/feature-graphic-1024x500.png" \
    "$store_root/trainer/feature-graphic-source.png"

echo 'Store icon and feature graphics generated from the current Gym Atlas mark.'
