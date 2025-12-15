<?php

declare(strict_types=1);

// Простой фронт-контроллер с самописным роутером.

$root = dirname(__DIR__);
require_once $root . '/src/Router.php';

// Автозагрузка файлов из src/
spl_autoload_register(function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $class = substr($class, strlen($prefix));
    }
    $path = $root . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$config = require $root . '/config.php';

// Инициализация репозитория и сервисов
$repo = new App\Repository\TaskRepository($config['db_path']);
$tokenService = new App\Service\TokenService($config['token_ttl_minutes']);
$uploadService = new App\Service\UploadService($config['upload_dir'], $config['max_upload_mb']);
$archiveService = new App\Service\ArchiveService($config['encryption_key']);

$apiController = new App\Controller\TaskApiController(
    $repo,
    $tokenService,
    $uploadService,
    $archiveService,
    $config
);
$tokenController = new App\Controller\TokenController(
    $repo,
    $tokenService,
    $uploadService,
    $archiveService,
    $config
);

$router = new App\Router();

// REST API (только локально и с Bearer-токеном)
$router->add('POST', '/api/tasks', [$apiController, 'create']);
$router->add('GET', '/api/tasks/{id}', [$apiController, 'show']);
$router->add('POST', '/api/tasks/{id}/cancel', [$apiController, 'cancel']);

// UI по токену
$router->add('GET', '/t/{token}', [$tokenController, 'showForm']);
$router->add('POST', '/t/{token}', [$tokenController, 'submit']);

// Обработка запроса
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

