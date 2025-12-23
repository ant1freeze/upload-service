<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Service\ArchiveService;
use App\Service\TokenService;
use App\Service\UploadService;

final class TaskApiController
{
    public function __construct(
        private TaskRepository $tasks,
        private TokenService $tokens,
        private UploadService $uploads,
        private ArchiveService $archives,
        private array $config
    ) {
    }

    public function create(): string
    {
        $this->guardAuth();
        $payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $type = $payload['type'] ?? '';
        if (!in_array($type, ['upload', 'archive_password'], true)) {
            return $this->json(['error' => 'Invalid type'], 400);
        }
        $archivePath = $payload['archive_path'] ?? null;
        if ($archivePath !== null && $type === 'archive_password') {
            // Валидация: /tmp/data/<TICKET>/<filename.zip>
            // <TICKET> = буква + буквы/цифры (пример: MA4385764)
            // <filename.zip> = имя файла без дополнительных слэшей
            if (!preg_match('#^/tmp/data/[A-Z][A-Z0-9]+/[A-Za-z0-9._-]+\\.zip$#', $archivePath)) {
                return $this->json(['error' => 'Invalid archive_path format. Must be /tmp/data/<TICKET>/<file>.zip'], 400);
            }
        }
        $expiresAt = $this->tokens->expiresAt($payload['expires_at'] ?? null);
        $requestNumber = $payload['request_number'] ?? null;
        $token = $this->tokens->generateToken();

        $task = $this->tasks->create($type, $archivePath, $expiresAt, $token, $requestNumber);
        $url = rtrim((string) $this->config['base_url'], '/') . '/t/' . $token;

        return $this->json([
            'id' => (int) $task['id'],
            'url' => $url,
            'request_number' => $task['request_number'] ?? null,
        ]);
    }

    public function show(array $params): string
    {
        $this->guardAuth();
        $id = (int) ($params['id'] ?? 0);
        $task = $this->tasks->findById($id);
        if (!$task) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $result = [
            'id' => (int) $task['id'],
            'type' => $task['type'],
            'status' => $task['status'],
            'file' => $this->safeName($task['file_path'] ?? null),
            'archive_path' => $task['archive_path'],
            'request_number' => $task['request_number'] ?? null,
            'updated_at' => $task['updated_at'],
        ];
        
        // Возвращаем пароль только для завершённых задач archive_password
        if ($task['type'] === 'archive_password' && $task['status'] === 'done' && !empty($task['archive_password'])) {
            $result['password'] = $this->archives->decrypt($task['archive_password']);
        }
        
        return $this->json($result);
    }

    public function cancel(array $params): string
    {
        $this->guardAuth();
        $id = (int) ($params['id'] ?? 0);
        $task = $this->tasks->findById($id);
        if (!$task) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if ($task['status'] === 'pending') {
            $this->tasks->setStatus($id, 'failed');
        }
        return $this->json(['status' => $task['status'] === 'pending' ? 'failed' : $task['status']]);
    }

    private function guardAuth(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }
        $token = substr($header, 7);
        if ($token !== ($this->config['api_token'] ?? '')) {
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }
    }

    private function json(array $data, int $code = 200): string
    {
        http_response_code($code);
        header('Content-Type: application/json');
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function safeName(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        return basename($path);
    }
}

