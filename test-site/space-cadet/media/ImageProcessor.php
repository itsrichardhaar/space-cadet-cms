<?php
/**
 * Space Cadet CMS — GD-based Image Processor
 * No Composer dependencies — uses only the bundled GD extension.
 */

class ImageProcessor {

    /**
     * Resize an image if either dimension exceeds SC_MAX_IMAGE_DIM.
     * Returns the path (may be same file if no resize needed).
     */
    public static function resize(string $path, string $mime): void {
        if (!self::isRaster($mime)) return;

        [$w, $h] = getimagesize($path);
        $max = SC_MAX_IMAGE_DIM;

        if ($w <= $max && $h <= $max) return;

        $ratio = min($max / $w, $max / $h);
        $nw    = (int) round($w * $ratio);
        $nh    = (int) round($h * $ratio);

        $src  = self::load($path, $mime);
        $dst  = imagecreatetruecolor($nw, $nh);
        self::preserveTransparency($dst, $mime);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        self::save($dst, $path, $mime);
        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * Convert a raster image to WebP and save alongside original.
     * Returns the relative filename of the WebP file.
     */
    public static function toWebP(string $path, string $mime): ?string {
        if (!self::isRaster($mime) || $mime === 'image/webp') return null;
        if (!function_exists('imagewebp')) return null;

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $path);
        $src      = self::load($path, $mime);
        imagewebp($src, $webpPath, SC_WEBP_QUALITY);
        imagedestroy($src);

        return basename($webpPath);
    }

    /**
     * Create a center-cropped thumbnail (SC_THUMB_WIDTH × SC_THUMB_HEIGHT).
     * Returns the relative filename of the thumbnail.
     */
    public static function thumbnail(string $path, string $mime, string $destDir): ?string {
        if (!self::isRaster($mime)) return null;

        $tw = SC_THUMB_WIDTH;
        $th = SC_THUMB_HEIGHT;

        [$w, $h] = getimagesize($path);

        // Compute crop box
        $srcRatio  = $w / $h;
        $dstRatio  = $tw / $th;

        if ($srcRatio > $dstRatio) {
            $cropH = $h;
            $cropW = (int) round($h * $dstRatio);
            $cropX = (int) round(($w - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $w;
            $cropH = (int) round($w / $dstRatio);
            $cropX = 0;
            $cropY = (int) round(($h - $cropH) / 2);
        }

        $src  = self::load($path, $mime);
        $dst  = imagecreatetruecolor($tw, $th);
        self::preserveTransparency($dst, 'image/webp');
        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $tw, $th, $cropW, $cropH);

        $thumbName = 'thumb_' . basename($path) . '.webp';
        $thumbPath = rtrim($destDir, '/') . '/' . $thumbName;
        imagewebp($dst, $thumbPath, SC_WEBP_QUALITY);

        imagedestroy($src);
        imagedestroy($dst);

        return $thumbName;
    }

    // ── Private helpers ───────────────────────────────────────

    private static function isRaster(string $mime): bool {
        return in_array($mime, ['image/jpeg','image/png','image/gif','image/webp','image/avif'], true);
    }

    private static function load(string $path, string $mime): GdImage {
        return match($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/gif'  => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => imagecreatefromjpeg($path),
        };
    }

    private static function save(GdImage $img, string $path, string $mime): void {
        match($mime) {
            'image/jpeg' => imagejpeg($img, $path, 92),
            'image/png'  => imagepng($img, $path, 6),
            'image/gif'  => imagegif($img, $path),
            'image/webp' => imagewebp($img, $path, SC_WEBP_QUALITY),
            default      => imagejpeg($img, $path, 92),
        };
    }

    private static function preserveTransparency(GdImage $img, string $mime): void {
        if (in_array($mime, ['image/png','image/gif','image/webp'], true)) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
            imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $transparent);
        }
    }
}
