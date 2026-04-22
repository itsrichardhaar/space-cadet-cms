<?php
/**
 * Space Cadet CMS — File Upload Handler
 */

class Uploader {

    public static function handle(array $file, ?int $userId): array {
        // Size check
        if ($file['size'] > SC_MAX_UPLOAD_BYTES) {
            Response::error('File exceeds maximum size of 10 MB.', 413);
        }

        // MIME check via finfo (not the browser-supplied type)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, SC_ALLOWED_MIME, true)) {
            Response::error("File type '{$mime}' is not allowed.", 415);
        }

        // Generate a safe, unique filename
        $ext      = self::ext($mime);
        $filename = bin2hex(random_bytes(12)) . '.' . $ext;
        $destPath = SC_UPLOADS . '/' . $filename;

        // SVG sanitization
        if ($mime === 'image/svg+xml') {
            $svgContent = file_get_contents($file['tmp_name']);
            $sanitized  = SvgSanitizer::sanitize($svgContent);
            file_put_contents($destPath, $sanitized);
        } else {
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                Response::error('Failed to move uploaded file.', 500);
            }
        }

        // Image processing
        $width   = null;
        $height  = null;
        $webpFn  = null;
        $thumbFn = null;

        if (self::isImage($mime)) {
            [$width, $height] = getimagesize($destPath);

            // Resize oversized images
            ImageProcessor::resize($destPath, $mime);
            [$width, $height] = getimagesize($destPath);

            // WebP conversion
            $setting = Database::queryOne("SELECT value FROM settings WHERE key='webp_convert'");
            if (($setting['value'] ?? '1') === '1' && $mime !== 'image/svg+xml') {
                $webpFn = ImageProcessor::toWebP($destPath, $mime);
            }

            // Thumbnail
            $thumbFn = ImageProcessor::thumbnail($destPath, $mime, SC_THUMBS);
        }

        // Persist to DB
        $mediaId = Media::create([
            'filename'      => $filename,
            'original_name' => $file['name'],
            'mime_type'     => $mime,
            'size_bytes'    => filesize($destPath),
            'width'         => $width,
            'height'        => $height,
            'webp_path'     => $webpFn,
            'thumb_path'    => $thumbFn,
            'uploaded_by'   => $userId,
        ]);

        return Media::findById($mediaId);
    }

    private static function ext(string $mime): string {
        return match($mime) {
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/gif'        => 'gif',
            'image/webp'       => 'webp',
            'image/avif'       => 'avif',
            'image/svg+xml'    => 'svg',
            'application/pdf'  => 'pdf',
            'video/mp4'        => 'mp4',
            'video/webm'       => 'webm',
            default            => 'bin',
        };
    }

    private static function isImage(string $mime): bool {
        return in_array($mime, ['image/jpeg','image/png','image/gif','image/webp','image/avif'], true);
    }
}
