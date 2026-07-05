<?php

namespace App\Services\Media;

use App\Models\Gym;
use App\Models\GymPhoto;
use App\Support\Media\StoredImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GymImageService
{
    /**
     * @param  array<string, int|string>  $options
     * @return array{path: string|null, url: string|null, thumbnail_path: string|null, thumbnail_url: string|null}
     */
    public function storeSingle(?UploadedFile $file, string $directory, ?string $currentPath = null, ?string $currentUrl = null, array $options = []): array
    {
        if (! $file) {
            return [
                'path' => $currentPath,
                'url' => $currentUrl,
                'thumbnail_path' => StoredImage::thumbnailPath($currentPath),
                'thumbnail_url' => StoredImage::thumbnailUrl($currentPath, $currentUrl),
            ];
        }

        $this->deleteManagedImage($currentPath);

        return $this->storeOptimizedImage($file, $directory, $options);
    }

    /**
     * @param  iterable<mixed>  $files
     * @param  array<string, int|string>  $options
     * @return \Illuminate\Support\Collection<int, array{path: string, url: string, thumbnail_path: string|null, thumbnail_url: string}>
     */
    public function storeGallery(iterable $files, string $directory, array $options = []): Collection
    {
        return collect($files)
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file): array => $this->storeOptimizedImage($file, $directory, $options));
    }

    public function syncGymMediaRecords(Gym $gym): void
    {
        $this->syncSingleRecord($gym, 'logo', $gym->logo_url);
        $this->syncSingleRecord($gym, 'cover', $gym->cover_image_url);
        $this->syncGalleryRecords($gym);
    }

    public function deleteManagedImage(?string $path): void
    {
        if (! filled($path) || StoredImage::isAbsoluteUrl($path)) {
            return;
        }

        $normalizedPath = StoredImage::normalizedPath($path);
        $thumbnailPath = StoredImage::thumbnailPath($normalizedPath);

        Storage::disk('public')->delete(array_filter([$normalizedPath, $thumbnailPath]));
    }

    /**
     * @param  array<string, int|string>  $options
     * @return array{path: string, url: string, thumbnail_path: string|null, thumbnail_url: string}
     */
    private function storeOptimizedImage(UploadedFile $file, string $directory, array $options = []): array
    {
        $normalizedDirectory = trim($directory, '/');
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || ! function_exists('imagecreatefromstring')) {
            $storedPath = $file->store($normalizedDirectory, 'public');

            return [
                'path' => $storedPath,
                'url' => Storage::disk('public')->url($storedPath),
                'thumbnail_path' => null,
                'thumbnail_url' => Storage::disk('public')->url($storedPath),
            ];
        }

        $source = @imagecreatefromstring($contents);
        $dimensions = @getimagesizefromstring($contents);

        if (! $source || ! is_array($dimensions)) {
            $storedPath = $file->store($normalizedDirectory, 'public');

            return [
                'path' => $storedPath,
                'url' => Storage::disk('public')->url($storedPath),
                'thumbnail_path' => null,
                'thumbnail_url' => Storage::disk('public')->url($storedPath),
            ];
        }

        $sourceWidth = (int) $dimensions[0];
        $sourceHeight = (int) $dimensions[1];
        $mime = (string) ($dimensions['mime'] ?? $file->getMimeType() ?? 'image/jpeg');
        $extension = function_exists('imagewebp') ? 'webp' : $this->extensionFromMime($mime);
        $fileName = (string) Str::uuid();
        $storedPath = $normalizedDirectory.'/'.$fileName.'.'.$extension;
        $thumbnailPath = $normalizedDirectory.'/'.$fileName.'_thumb.'.$extension;

        $maxWidth = (int) ($options['max_width'] ?? 1600);
        $maxHeight = (int) ($options['max_height'] ?? 1600);
        $thumbWidth = (int) ($options['thumb_width'] ?? 640);
        $thumbHeight = (int) ($options['thumb_height'] ?? 480);
        $thumbMode = (string) ($options['thumb_mode'] ?? 'crop');

        $optimized = $this->resizeToFit($source, $sourceWidth, $sourceHeight, $maxWidth, $maxHeight, $mime);
        $thumbnail = $thumbMode === 'fit'
            ? $this->resizeToFit($source, $sourceWidth, $sourceHeight, $thumbWidth, $thumbHeight, $mime)
            : $this->cropToFill($source, $sourceWidth, $sourceHeight, $thumbWidth, $thumbHeight, $mime);

        Storage::disk('public')->put($storedPath, $this->encodeImage($optimized, $mime));
        Storage::disk('public')->put($thumbnailPath, $this->encodeImage($thumbnail, $mime));

        imagedestroy($source);
        imagedestroy($optimized);
        imagedestroy($thumbnail);

        return [
            'path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
            'thumbnail_path' => $thumbnailPath,
            'thumbnail_url' => Storage::disk('public')->url($thumbnailPath),
        ];
    }

    private function syncSingleRecord(Gym $gym, string $type, ?string $imageUrl): void
    {
        $query = $gym->gymPhotos()->whereNull('branch_id')->where('type', $type);

        if (! filled($imageUrl)) {
            $query->delete();

            return;
        }

        $photo = $query->first();

        if ($photo) {
            $photo->forceFill([
                'image_path' => $imageUrl,
                'sort_order' => 0,
            ])->save();

            return;
        }

        $gym->gymPhotos()->create([
            'branch_id' => null,
            'image_path' => $imageUrl,
            'type' => $type,
            'sort_order' => 0,
        ]);
    }

    private function syncGalleryRecords(Gym $gym): void
    {
        $galleryUrls = collect($gym->photo_urls ?? [])
            ->filter(fn (mixed $url): bool => filled($url))
            ->map(fn (mixed $url): string => trim((string) $url))
            ->values();

        $galleryQuery = $gym->gymPhotos()->whereNull('branch_id')->where('type', 'gallery');
        $existing = $galleryQuery->get()->keyBy('image_path');

        foreach ($galleryUrls as $index => $imageUrl) {
            $photo = $existing->pull($imageUrl);

            if ($photo) {
                $photo->forceFill(['sort_order' => $index + 1])->save();

                continue;
            }

            $gym->gymPhotos()->create([
                'branch_id' => null,
                'image_path' => $imageUrl,
                'type' => 'gallery',
                'sort_order' => $index + 1,
            ]);
        }

        $existing->each(function (GymPhoto $photo): void {
            $this->deleteManagedImage($photo->image_path);
            $photo->delete();
        });
    }

    private function resizeToFit(\GdImage $source, int $sourceWidth, int $sourceHeight, int $maxWidth, int $maxHeight, string $mime): \GdImage
    {
        $scale = min($maxWidth / max($sourceWidth, 1), $maxHeight / max($sourceHeight, 1), 1);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $canvas = $this->createCanvas($targetWidth, $targetHeight, $mime);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        return $canvas;
    }

    private function cropToFill(\GdImage $source, int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight, string $mime): \GdImage
    {
        $sourceRatio = $sourceWidth / max($sourceHeight, 1);
        $targetRatio = $targetWidth / max($targetHeight, 1);

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $srcX = (int) round(($sourceWidth - $cropWidth) / 2);
            $srcY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $srcX = 0;
            $srcY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        $canvas = $this->createCanvas($targetWidth, $targetHeight, $mime);
        imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);

        return $canvas;
    }

    private function createCanvas(int $width, int $height, string $mime): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        }

        return $canvas;
    }

    private function encodeImage(\GdImage $image, string $mime): string
    {
        ob_start();

        if (function_exists('imagewebp')) {
            imagewebp($image, null, 82);
        } elseif ($mime === 'image/png') {
            imagepng($image, null, 6);
        } else {
            imagejpeg($image, null, 84);
        }

        return (string) ob_get_clean();
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
