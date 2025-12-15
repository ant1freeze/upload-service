<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class TaskRepository
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->migrate();
        $this->pdo->exec('PRAGMA journal_mode=WAL;');
        $this->pdo->exec('PRAGMA foreign_keys=ON;');
    }

    private function migrate(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS tasks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  type TEXT NOT NULL CHECK (type IN ('upload','archive_password')),
  token TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','done','failed')),
  file_path TEXT,
  archive_path TEXT,
  archive_password TEXT,
  attempts INTEGER NOT NULL DEFAULT 0,
  request_number TEXT,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  expires_at DATETIME,
  used_at DATETIME
);
CREATE INDEX IF NOT EXISTS idx_tasks_token ON tasks(token);
CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);
CREATE INDEX IF NOT EXISTS idx_tasks_expires ON tasks(expires_at);
SQL;
        $this->pdo->exec($sql);
        
        // Добавляем колонку request_number если её нет (для существующих БД)
        try {
            $check = $this->pdo->query("PRAGMA table_info(tasks)");
            $columns = $check->fetchAll(PDO::FETCH_ASSOC);
            $hasRequestNumber = false;
            foreach ($columns as $col) {
                if ($col['name'] === 'request_number') {
                    $hasRequestNumber = true;
                    break;
                }
            }
            if (!$hasRequestNumber) {
                $this->pdo->exec('ALTER TABLE tasks ADD COLUMN request_number TEXT');
            }
            // Создаём индекс для request_number (если колонка существует)
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tasks_request_number ON tasks(request_number)');
        } catch (\PDOException $e) {
            // Игнорируем ошибки при добавлении колонки/индекса
        }
    }

    public function create(string $type, ?string $archivePath, ?string $expiresAt, string $token, ?string $requestNumber = null): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (type, token, status, archive_path, request_number, created_at, updated_at, expires_at)
            VALUES (:type, :token, 'pending', :archive_path, :request_number, :created_at, :updated_at, :expires_at)
        ");
        $stmt->execute([
            ':type' => $type,
            ':token' => $token,
            ':archive_path' => $archivePath,
            ':request_number' => $requestNumber,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':expires_at' => $expiresAt,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE token = :token');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE tasks SET status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    public function setFilePath(int $id, string $path): void
    {
        $stmt = $this->pdo->prepare('UPDATE tasks SET file_path = :path, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':path' => $path,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    public function setArchivePassword(int $id, string $password): void
    {
        $stmt = $this->pdo->prepare('UPDATE tasks SET archive_password = :password, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':password' => $password,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    public function incrementAttempts(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE tasks SET attempts = attempts + 1, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':updated_at' => gmdate('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    public function markUsed(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE tasks SET used_at = :used_at, updated_at = :updated_at WHERE id = :id');
        $now = gmdate('Y-m-d H:i:s');
        $stmt->execute([
            ':used_at' => $now,
            ':updated_at' => $now,
            ':id' => $id,
        ]);
    }
}

