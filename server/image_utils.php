<?php
// Image utility functions for processing and managing images

// Load environment variables
$envFile = __DIR__ . '/../.env';
$env = parse_ini_file($envFile);

// Make sure autoloader is included
require_once __DIR__ . '/../vendor/autoload.php';

// Include logger
require_once __DIR__ . '/logger.php';

// Use Intervention Image library
use Intervention\Image\ImageManagerStatic as Image;

// Set default driver (GD or Imagick)
Image::configure(['driver' => 'gd']);

/**
 * Process an image to make it suitable for email attachment
 * - Resize large images
 * - Compress to reduce file size
 * - Convert to JPEG for better compatibility
 *
 * @param string $sourcePath Path to the original image
 * @param string $targetPath Path to save the processed image (if null, overwrites original)
 * @param int $maxWidth Maximum width of the processed image
 * @param int $maxHeight Maximum height of the processed image
 * @param int $quality JPEG compression quality (0-100)
 * @return string Path to the processed image
 */
function processImageForEmail($sourcePath, $targetPath = null, $maxWidth = 1200, $maxHeight = 1200, $quality = 75) {
    // If no target path specified, overwrite the original
    if ($targetPath === null) {
        $targetPath = $sourcePath;
    }

    try {
        // Load the image
        $image = Image::make($sourcePath);

        // Resize if larger than max dimensions while maintaining aspect ratio
        if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
            $image->resize($maxWidth, $maxHeight, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // Save as JPEG with specified quality
        $image->save($targetPath, $quality, 'jpg');

        return $targetPath;
    } catch (Exception $e) {
        log_error('Image processing error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'source_path' => $sourcePath
        ]);
        // Return original path if processing fails
        return $sourcePath;
    }
}

/**
 * Delete image files associated with a submission
 *
 * @param array $images Array of image records from the database
 * @return int Number of files successfully deleted
 */
function deleteSubmissionImages($images) {
    $deletedCount = 0;

    foreach ($images as $image) {
        if (isset($image['file_path']) && file_exists($image['file_path'])) {
            if (unlink($image['file_path'])) {
                $deletedCount++;
            } else {
                log_warning('Failed to delete file', [
                    'file_path' => $image['file_path']
                ]);
            }
        }
    }

    return $deletedCount;
}

/**
 * Clean up old files from the uploads directory
 *
 * @param string $directory Directory to clean
 * @param int $maxAge Maximum age of files in seconds before deletion
 * @return array Statistics about the cleanup operation
 */
function cleanupOldFiles($directory, $maxAge = 86400) {
    $stats = [
        'scanned' => 0,
        'deleted' => 0,
        'failed' => 0,
        'skipped' => 0
    ];

    if (!is_dir($directory)) {
        return $stats;
    }

    $now = time();

    $files = new DirectoryIterator($directory);
    foreach ($files as $file) {
        if ($file->isDot() || $file->isDir()) {
            continue;
        }

        $stats['scanned']++;
        $filePath = $file->getPathname();
        $fileAge = $now - $file->getMTime();

        if ($fileAge > $maxAge) {
            if (unlink($filePath)) {
                $stats['deleted']++;
            } else {
                $stats['failed']++;
                log_warning('Failed to delete old file', [
                    'file_path' => $filePath,
                    'file_age' => $fileAge
                ]);
            }
        } else {
            $stats['skipped']++;
        }
    }

    return $stats;
}
