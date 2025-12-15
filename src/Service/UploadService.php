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
        
        // Валидация и нормализация имени файла
        $originalName = (string) ($file['name'] ?? 'upload.bin');
        $name = $this->sanitizeFileName($originalName);
        
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new RuntimeException('Cannot move upload');
        }
        return $taskId . '/' . $name; // относительный путь
    }
    
    private function sanitizeFileName(string $name): string
    {
        // Убираем путь, оставляем только имя
        $name = basename($name);
        // Убираем опасные символы, оставляем буквы, цифры, точки, дефисы, подчеркивания
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        // Ограничиваем длину
        if (strlen($name) > 255) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = substr(pathinfo($name, PATHINFO_FILENAME), 0, 255 - strlen($ext) - 1);
            $name = $base . '.' . $ext;
        }
        // Если имя пустое или только точки - даем дефолтное
        if (empty($name) || trim($name, '.') === '') {
            $name = 'upload_' . time() . '.bin';
        }
        return $name;
    }
}

