<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use ZipArchive;

final class ArchiveService
{
    public function __construct(private ?string $encryptionKey)
    {
    }

    public function encrypt(string $plain): string
    {
        if ($this->encryptionKey === null) {
            return $plain;
        }
        $iv = random_bytes(12);
        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $this->encryptionKey,
            0,
            $iv,
            $tag
        );
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $stored): string
    {
        if ($this->encryptionKey === null) {
            return $stored;
        }
        $data = base64_decode($stored, true);
        if ($data === false || strlen($data) < 28) {
            throw new RuntimeException('Bad data');
        }
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $cipher = substr($data, 28);
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $this->encryptionKey,
            0,
            $iv,
            $tag
        );
        if ($plain === false) {
            throw new RuntimeException('Decryption failed');
        }
        return $plain;
    }

    /**
     * Проверяем, подходит ли пароль к ZIP-архиву.
     */
    public function verifyPassword(string $archivePath, string $password): bool
    {
        if (!is_file($archivePath)) {
            throw new RuntimeException('Архив не найден');
        }
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Не удалось открыть архив');
        }
        if ($zip->numFiles < 1) {
            $zip->close();
            throw new RuntimeException('Архив пуст');
        }

        $zip->setPassword($password);
        $name = $zip->getNameIndex(0);
        if ($name === false) {
            $zip->close();
            throw new RuntimeException('Не удалось прочитать архив');
        }
        $stream = $zip->getStream($name);
        if ($stream === false) {
            $zip->close();
            return false; // пароль не подошёл
        }
        // Читаем немного данных, чтобы убедиться, что пароль верен.
        fread($stream, 4);
        fclose($stream);
        $zip->close();
        return true;
    }
}

