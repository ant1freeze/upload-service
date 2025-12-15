<?php /** @var array $task */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Загрузка файла</title>
</head>
<body>
    <h1>Загрузка файла для задачи #<?= htmlspecialchars((string)($task['id'] ?? '')) ?></h1>
    <?php if (!empty($error)): ?>
        <p style="color:red;"><?= htmlspecialchars((string) $error) ?></p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="">
        <input type="file" name="file" required>
        <button type="submit">Отправить</button>
    </form>
</body>
</html>

