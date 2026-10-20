<?php

/**
 * =============================================================================
 * IMAGE RESIZE SERVICE
 * =============================================================================
 *
 * @file        app/Services/ImageResizeService.php
 * @description Single GD-backed downscaler for uploaded images (profile photos,
 *              provider avatars, company logos). Overwrites the file in place.
 *
 * Supported: JPEG, PNG (incl. image/x-png), GIF, WebP (when GD has WebP).
 * Unsupported or undecodable files (SVG, corrupt uploads) are left untouched.
 * PNG/GIF transparency is preserved. Never upscales.
 */

namespace App\Services;

class ImageResizeService
{
    private const SVG_MIMES = ['image/svg+xml', 'image/svg'];

    /**
     * Downscale so the width is at most $maxWidth, keeping the aspect ratio.
     */
    public function constrainWidth(string $path, string $mime, int $maxWidth): void
    {
        if (in_array($mime, self::SVG_MIMES, true)) {
            return;
        }

        [$width, $height] = @getimagesize($path) ?: [null, null];
        if (!$width || $width <= $maxWidth) {
            return;
        }

        $ratio = $height ? ($height / $width) : 1;
        $this->resizeInPlace($path, $mime, $maxWidth, max(1, (int) round($maxWidth * $ratio)));
    }

    /**
     * Downscale so neither side exceeds $max, keeping the aspect ratio.
     */
    public function constrainToMaxDimension(string $path, string $mime, int $max): void
    {
        if (in_array($mime, self::SVG_MIMES, true)) {
            return;
        }

        [$width, $height] = @getimagesize($path) ?: [null, null];
        if (!$width || !$height || ($width <= $max && $height <= $max)) {
            return;
        }

        if ($width >= $height) {
            $newWidth = $max;
            $newHeight = (int) round($height * ($max / $width));
        } else {
            $newHeight = $max;
            $newWidth = (int) round($width * ($max / $height));
        }

        $this->resizeInPlace($path, $mime, max(1, $newWidth), max(1, $newHeight));
    }

    /**
     * Resample the image to exactly $newWidth × $newHeight and overwrite it.
     */
    public function resizeInPlace(string $path, string $mime, int $newWidth, int $newHeight): void
    {
        $source = $this->load($path, $mime);
        if (!$source) {
            return;
        }

        $destination = imagecreatetruecolor($newWidth, $newHeight);
        if ($this->hasAlpha($mime)) {
            imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
        }

        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($source), imagesy($source));

        switch ($mime) {
            case 'image/jpeg':
                @imagejpeg($destination, $path, 85);
                break;
            case 'image/png':
            case 'image/x-png':
                @imagepng($destination, $path, 6);
                break;
            case 'image/gif':
                @imagegif($destination, $path);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    @imagewebp($destination, $path, 85);
                }
                break;
        }

        imagedestroy($source);
        imagedestroy($destination);
    }

    /**
     * @return \GdImage|false|null
     */
    private function load(string $path, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                return @imagecreatefromjpeg($path);
            case 'image/png':
            case 'image/x-png':
                return @imagecreatefrompng($path);
            case 'image/gif':
                return @imagecreatefromgif($path);
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
            default:
                return null;
        }
    }

    private function hasAlpha(string $mime): bool
    {
        return in_array($mime, ['image/png', 'image/x-png', 'image/gif'], true);
    }
}
