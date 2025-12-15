<?php /** @var array $task */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Пароль для архива</title>
</head>
<body>
    <h1>Введите пароль для задачи #<?= htmlspecialchars((string)($task['id'] ?? '')) ?></h1>
    <?php if (!empty($task['request_number'])): ?>
        <p><strong>Номер заявки:</strong> <?= htmlspecialchars((string) $task['request_number']) ?></p>
    <?php endif; ?>
    <?php if (!empty($archive_name)): ?>
        <p><strong>Архив:</strong> <?= htmlspecialchars((string) $archive_name) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p style="color:red;"><?= htmlspecialchars((string) $error) ?></p>
    <?php endif; ?>
    <?php if (isset($remaining)): ?>
        <p>Осталось попыток: <?= (int) $remaining ?></p>
    <?php endif; ?>
    <form method="post">
        <input type="password" name="password" required>
        <button type="submit">Отправить</button>
    </form>
</body>
</html>

