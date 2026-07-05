<?php

namespace App\Support\Media;

use Illuminate\Support\Facades\Storage;

class StoredImage
{
    public static function publicUrl(?string $path, ?string $fallbackUrl = null): ?string
    {
        if (filled($path)) {
            if (self::isAbsoluteUrl($path) || str_starts_with($path, '/storage/')) {
                return self::toRelativeStorageUrl($path);
            }

            return self::toRelativeStorageUrl(Storage::disk('public')->url($path));
        }

        return filled($fallbackUrl) ? self::toRelativeStorageUrl($fallbackUrl) : null;
    }

    public static function thumbnailUrl(?string $path, ?string $fallbackUrl = null): ?string
    {
        if (filled($path)) {
            $normalizedPath = self::normalizedPath($path);
            $thumbPath = self::thumbnailPath($normalizedPath);

            if ($thumbPath && Storage::disk('public')->exists($thumbPath)) {
                return self::toRelativeStorageUrl(Storage::disk('public')->url($thumbPath));
            }

            return self::publicUrl($path, $fallbackUrl);
        }

        return filled($fallbackUrl) ? $fallbackUrl : null;
    }

    public static function thumbnailPath(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $normalizedPath = self::normalizedPath($path);
        $extension = pathinfo($normalizedPath, PATHINFO_EXTENSION);
        $filename = pathinfo($normalizedPath, PATHINFO_FILENAME);
        $dirname = pathinfo($normalizedPath, PATHINFO_DIRNAME);

        if ($filename === '' || $extension === '') {
            return null;
        }

        $thumbFile = $filename.'_thumb.'.$extension;

        return $dirname === '.' ? $thumbFile : $dirname.'/'.$thumbFile;
    }

    public static function normalizedPath(string $path): string
    {
        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        $storageUrl = Storage::disk('public')->url('');
        if ($storageUrl !== '' && str_starts_with($path, $storageUrl)) {
            return ltrim(substr($path, strlen($storageUrl)), '/');
        }

        return $path;
    }

    public static function isAbsoluteUrl(string $path): bool
    {
        return filter_var($path, FILTER_VALIDATE_URL) !== false;
    }

    public static function toRelativeStorageUrl(string $url): string
    {
        if (str_starts_with($url, '/storage/')) {
            return $url;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? $url;

        return str_starts_with($path, '/storage/') ? $path : $url;
    }
}
