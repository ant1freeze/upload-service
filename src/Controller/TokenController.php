<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Service\ArchiveService;
use App\Service\TokenService;
use App\Service\UploadService;
use RuntimeException;

final class TokenController
{
    public function __construct(
        private TaskRepository $tasks,
        private TokenService $tokens,
        private UploadService $uploads,
        private ArchiveService $archives,
        private array $config
    ) {
    }

    public function showForm(array $params): string
    {
        $token = $params['token'] ?? '';
        $task = $this->tasks->findByToken($token);
        if (!$task) {
            return $this->renderMessage('Ссылка недействительна');
        }
        if ($this->tokens->isExpired($task)) {
            $this->tasks->setStatus((int) $task['id'], 'failed');
            return $this->renderMessage('Срок действия ссылки истёк');
        }
        if ($task['status'] !== 'pending') {
            return $this->renderMessage('Задача уже выполнена или недоступна');
        }

        $csrfToken = $this->generateCsrfToken((int) $task['id'], $task['token']);
        
        if ($task['type'] === 'upload') {
            return $this->renderTemplate('templates/upload_form.php', ['task' => $task, 'error' => null, 'csrf_token' => $csrfToken]);
        }
        if ($task['type'] === 'archive_password') {
            $remaining = max(0, ($this->config['max_password_attempts'] ?? 5) - (int) $task['attempts']);
            return $this->renderTemplate('templates/password_form.php', [
                'task' => $task,
                'error' => null,
                'remaining' => $remaining,
                'archive_name' => $this->displayName($task['archive_path'] ?? null),
                'csrf_token' => $csrfToken,
            ]);
        }
        return $this->renderMessage('Неизвестный тип задачи');
    }

    public function submit(array $params): string
    {
        $token = $params['token'] ?? '';
        $task = $this->tasks->findByToken($token);
        if (!$task) {
            return $this->renderMessage('Ссылка недействительна');
        }
        if ($this->tokens->isExpired($task)) {
            $this->tasks->setStatus((int) $task['id'], 'failed');
            return $this->renderMessage('Срок действия ссылки истёк');
        }
        if ($task['status'] !== 'pending') {
            return $this->renderMessage('Задача уже выполнена или недоступна');
        }
        
        // Проверка CSRF токена
        $csrfToken = $_POST['csrf_token'] ?? '';
        $expectedToken = $this->generateCsrfToken((int) $task['id'], $task['token']);
        if (!hash_equals($expectedToken, $csrfToken)) {
            return $this->renderMessage('Ошибка безопасности. Обновите страницу и попробуйте снова.');
        }

        try {
            if ($task['type'] === 'upload') {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMsg = 'Файл не передан';
                    if (isset($_FILES['file']['error'])) {
                        switch ($_FILES['file']['error']) {
                            case UPLOAD_ERR_INI_SIZE:
                            case UPLOAD_ERR_FORM_SIZE:
                                $errorMsg = 'Файл слишком большой';
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $errorMsg = 'Файл загружен частично';
                                break;
                            case UPLOAD_ERR_NO_FILE:
                                $errorMsg = 'Файл не выбран';
                                break;
                            default:
                                $errorMsg = 'Ошибка загрузки файла';
                        }
                    }
                    return $this->renderTemplate('templates/upload_form.php', [
                        'task' => $task,
                        'error' => $errorMsg,
                    ]);
                }
                $relative = $this->uploads->saveUploadedFile((int) $task['id'], $_FILES['file']);
                $this->tasks->setFilePath((int) $task['id'], $relative);
                $this->tasks->setStatus((int) $task['id'], 'done');
                $this->tasks->markUsed((int) $task['id']);
                return $this->renderMessage('Файл загружен, спасибо!');
            }

            if ($task['type'] === 'archive_password') {
                $maxAttempts = (int) ($this->config['max_password_attempts'] ?? 5);
                if ((int) $task['attempts'] >= $maxAttempts) {
                    return $this->renderMessage('Превышено число попыток');
                }
                $password = trim((string) ($_POST['password'] ?? ''));
                if ($password === '') {
                    $this->tasks->incrementAttempts((int) $task['id']);
                    $remaining = max(0, $maxAttempts - (int) $task['attempts'] - 1);
                    return $this->renderTemplate('templates/password_form.php', [
                        'task' => $task,
                        'error' => 'Пароль обязателен',
                        'remaining' => $remaining,
                        'archive_name' => $this->displayName($task['archive_path'] ?? null),
                    ]);
                }
                $ok = $this->archives->verifyPassword((string) $task['archive_path'], $password);
                if (!$ok) {
                    $this->tasks->incrementAttempts((int) $task['id']);
                    $remaining = max(0, $maxAttempts - (int) $task['attempts'] - 1);
                    return $this->renderTemplate('templates/password_form.php', [
                        'task' => $task,
                        'error' => 'Пароль не подходит, повторите попытку',
                        'remaining' => $remaining,
                        'archive_name' => $this->displayName($task['archive_path'] ?? null),
                    ]);
                }
                $stored = $this->archives->encrypt($password);
                $this->tasks->setArchivePassword((int) $task['id'], $stored);
                $this->tasks->setStatus((int) $task['id'], 'done');
                $this->tasks->markUsed((int) $task['id']);
                return $this->renderMessage('Пароль принят, ожидайте завершения заявки.');
            }
        } catch (RuntimeException $e) {
            return $this->renderMessage('Ошибка: ' . $e->getMessage());
        }

        return $this->renderMessage('Неизвестный тип задачи');
    }

    private function renderTemplate(string $file, array $vars): string
    {
        // Путь относительно корня проекта
        $root = dirname(__DIR__, 2);
        $templatePath = $root . '/' . $file;
        if (!is_file($templatePath)) {
            throw new RuntimeException("Template not found: {$templatePath}");
        }
        extract($vars, EXTR_OVERWRITE);
        ob_start();
        include $templatePath;
        return (string) ob_get_clean();
    }

    private function renderMessage(string $message): string
    {
        http_response_code(200);
        return "<html><body><p>{$message}</p></body></html>";
    }

    private function displayName(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        return basename($path);
    }
    
    private function generateCsrfToken(int $taskId, string $taskToken): string
    {
        $secret = $this->config['api_token'] ?? 'default-secret';
        return hash_hmac('sha256', $taskId . ':' . $taskToken, $secret);
    }
}

