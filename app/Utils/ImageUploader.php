<?php

namespace App\Utils;

use Nette\Http\FileUpload;

class ImageUploader
{
    public static function uploadImage(FileUpload $file, string $uploadDir, ?string $default = null): ?string
    {
        if ($file->isOk() && $file->isImage() && in_array($file->getContentType(), ['image/jpeg', 'image/png', 'image/gif'])) {
            // Ensure the upload directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uniqueName = uniqid() . '_' . pathinfo($file->getSanitizedName(), PATHINFO_FILENAME);
            $tempPath = rtrim($uploadDir, '/') . '/' . $uniqueName . '.' . pathinfo($file->getSanitizedName(), PATHINFO_EXTENSION);
            $file->move($tempPath);

            $imageInfo = getimagesize($tempPath);
            if ($imageInfo === false) {
                error_log("Invalid image info for file: {$file->getSanitizedName()}");
                unlink($tempPath);
                return $default;
            }
            $type = $imageInfo[2];
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $imageResource = imagecreatefromjpeg($tempPath);
                    break;
                case IMAGETYPE_PNG:
                    $imageResource = imagecreatefrompng($tempPath);
                    break;
                case IMAGETYPE_GIF:
                    $imageResource = imagecreatefromgif($tempPath);
                    break;
                default:
                    error_log("Unsupported image type for file: {$file->getSanitizedName()}");
                    unlink($tempPath);
                    return $default;
            }

            if ($imageResource === false) {
                error_log("Failed to create image resource for file: {$file->getSanitizedName()}");
                unlink($tempPath);
                return $default;
            }

            // Convert palette-based images to truecolor
            if (!imageistruecolor($imageResource)) {
                $truecolor = imagecreatetruecolor(imagesx($imageResource), imagesy($imageResource));
                if ($truecolor === false) {
                    error_log("Failed to create truecolor image for file: {$file->getSanitizedName()}");
                    imagedestroy($imageResource);
                    unlink($tempPath);
                    return $default;
                }
                // Preserve transparency for PNG and GIF
                imagealphablending($truecolor, false);
                imagesavealpha($truecolor, true);
                $transparent = imagecolorallocatealpha($truecolor, 0, 0, 0, 127);
                imagefill($truecolor, 0, 0, $transparent);
                imagecopy($truecolor, $imageResource, 0, 0, 0, 0, imagesx($imageResource), imagesy($imageResource));
                imagedestroy($imageResource);
                $imageResource = $truecolor;
            }

            // Resize if image is too large (optional, adjust max dimension as needed)
            $maxDimension = 1920;
            $width = imagesx($imageResource);
            $height = imagesy($imageResource);
            if ($width > $maxDimension || $height > $maxDimension) {
                $ratio = min($maxDimension / $width, $maxDimension / $height);
                $newWidth = (int) ($width * $ratio);
                $newHeight = (int) ($height * $ratio);
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                if ($resized === false) {
                    error_log("Failed to create resized image for file: {$file->getSanitizedName()}");
                    imagedestroy($imageResource);
                    unlink($tempPath);
                    return $default;
                }
                // Preserve transparency for resized image
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
                imagecopyresampled($resized, $imageResource, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($imageResource);
                $imageResource = $resized;
            }

            $newFileName = $uniqueName . '.webp';
            $newRelativePath = rtrim($uploadDir, '/') . '/' . $newFileName;
            $newDbPath = ltrim($newRelativePath, '/'); // e.g., uploads/repairs/123/unique_id.webp

            // Convert to WebP with compression (quality = 80)
            if (!imagewebp($imageResource, $newRelativePath, 80)) {
                error_log("WebP conversion failed for file: {$file->getSanitizedName()}");
                imagedestroy($imageResource);
                unlink($tempPath);
                return $default;
            }
            imagedestroy($imageResource);
            unlink($tempPath);

            return $newDbPath;
        }
        error_log("Invalid file upload for: {$file->getName()}, isOk: {$file->isOk()}, isImage: {$file->isImage()}, contentType: {$file->getContentType()}");
        return $default;
    }
}