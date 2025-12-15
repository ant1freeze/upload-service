<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class UploadService
{
    public function __construct(
        private string $uploadDir,
        private int $maxUploadMb
    ) {
    }

    public function ensureDirs(int $taskId): string
    {
        $dir = rtrim($this->uploadDir, '/') . '/' . $taskId;
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create upload dir');
        }
        return $dir;
    }

    public function validateSize(int $bytes): void
    {
        $limit = $this->maxUploadMb * 1024 * 1024;
        if ($bytes > $limit) {
            throw new RuntimeException('File too large');
        }
    }

    public function saveUploadedFile(int $taskId, array $file): string
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error');
        }
        $this->validateSize((int) ($file['size'] ?? 0));
        $dir = $this->ensureDirs($taskId);
        $name = basename((string) ($file['name'] ?? 'upload.bin'));
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new RuntimeException('Cannot move upload');
        }
        return $taskId . '/' . $name; // относительный путь
    }
}

