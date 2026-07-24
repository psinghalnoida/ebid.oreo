<?php

namespace App\Libraries;

class MediaCompressionService
{
    private const IMAGE_TARGET_BYTES = 300 * 1024;
    private const IMAGE_MAX_DIMENSION = 1920;
    private const IMAGE_QUALITY_FLOOR = 40;

    private const VIDEO_MAX_DURATION_SECONDS = 120;
    private const VIDEO_MAX_RESOLUTION = '1280x720';
    private const VIDEO_MAX_BITRATE = '4M';

    public function compressImage(string $sourcePath, string $destPath, string $sourceMimeType): array
    {
        $originalSize = filesize($sourcePath);
        if ($originalSize === false) {
            throw new \RuntimeException('Could not read source image for compression');
        }

        $image = match ($sourceMimeType) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => throw new \RuntimeException("Unsupported source type for compression: {$sourceMimeType}"),
        };
        if ($image === false) {
            throw new \RuntimeException('GD could not decode the source image — it may be corrupt');
        }

        if ($sourceMimeType === 'image/png') {
            $flattened = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
            imagealphablending($flattened, true);
            imagecopy($flattened, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            $image = $flattened;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > self::IMAGE_MAX_DIMENSION || $height > self::IMAGE_MAX_DIMENSION) {
            $scale = self::IMAGE_MAX_DIMENSION / max($width, $height);
            $newWidth = (int) round($width * $scale);
            $newHeight = (int) round($height * $scale);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        $quality = 80;
        do {
            imagewebp($image, $destPath, $quality);
            clearstatcache(true, $destPath); // PHP caches filesize() per path — without this, repeated checks on the same overwritten file return stale sizes from an earlier iteration
            $currentSize = filesize($destPath);
            if ($currentSize <= self::IMAGE_TARGET_BYTES) {
                break;
            }
            $quality -= 10;
        } while ($quality >= self::IMAGE_QUALITY_FLOOR);

        imagedestroy($image);
        clearstatcache(true, $destPath);

        return [
            'originalSizeBytes' => $originalSize,
            'compressedSizeBytes' => filesize($destPath),
            'width' => $width,
            'height' => $height,
        ];
    }

    public function transcodeVideo(string $sourcePath, string $destPath): array
    {
        if (!self::isFfmpegAvailable()) {
            throw new \RuntimeException('ffmpeg is not installed on this server — video upload cannot be processed. See the deployment guide.');
        }

        $originalSize = filesize($sourcePath);
        if ($originalSize === false) {
            throw new \RuntimeException('Could not read source video for transcoding');
        }

        $sourceDuration = self::getVideoDuration($sourcePath);

        $trimArgs = $sourceDuration > self::VIDEO_MAX_DURATION_SECONDS
            ? '-t ' . self::VIDEO_MAX_DURATION_SECONDS
            : '';

        $escapedSource = escapeshellarg($sourcePath);
        $escapedDest = escapeshellarg($destPath);

        $command = sprintf(
            'ffmpeg -y -i %s %s -vf "scale=%s:force_original_aspect_ratio=decrease" -b:v %s -c:v libx264 -c:a aac -movflags +faststart %s 2>&1',
            $escapedSource,
            $trimArgs,
            str_replace('x', ':', self::VIDEO_MAX_RESOLUTION),
            self::VIDEO_MAX_BITRATE,
            $escapedDest
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($destPath)) {
            throw new \RuntimeException('Video transcoding failed: ' . implode("\n", array_slice($output, -5)));
        }

        return [
            'originalSizeBytes' => $originalSize,
            'compressedSizeBytes' => filesize($destPath),
            'durationSeconds' => min($sourceDuration, self::VIDEO_MAX_DURATION_SECONDS),
        ];
    }

    public static function isFfmpegAvailable(): bool
    {
        exec('which ffmpeg 2>/dev/null', $output, $returnCode);
        return $returnCode === 0 && !empty($output);
    }

    private static function getVideoDuration(string $path): int
    {
        $escaped = escapeshellarg($path);
        exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$escaped} 2>&1", $output);
        return isset($output[0]) ? (int) round((float) $output[0]) : 0;
    }
}
