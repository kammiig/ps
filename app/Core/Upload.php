<?php

declare(strict_types=1);

namespace App\Core;

final class Upload
{
    public static function image(string $field, ?string $existing = null): ?string
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $existing;
        }

        $file = $_FILES[$field];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Image upload failed.');
        }

        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new \RuntimeException('Images must be smaller than 2MB.');
        }

        $info = getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new \RuntimeException('Uploaded file is not a valid image.');
        }

        $allowed = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_ICO => 'ico',
        ];

        $type = $info[2] ?? null;
        if (!isset($allowed[$type])) {
            throw new \RuntimeException('Use JPG, PNG, WebP, GIF, or ICO images only.');
        }

        $folder = 'uploads/' . date('Y/m');
        $targetDir = BASE_PATH . '/' . $folder;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Unable to create upload folder.');
        }

        $name = bin2hex(random_bytes(12)) . '.' . $allowed[$type];
        $target = $targetDir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Unable to save uploaded image.');
        }

        return $folder . '/' . $name;
    }
}
