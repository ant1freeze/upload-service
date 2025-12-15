<?php /** @var array $task */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Пароль для архива</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f5; font-family:Arial, sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh;">
    <div style="background:#fff; padding:24px 28px; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.12); max-width:420px; width:90%; text-align:center;">
        <h1 style="margin-top:0; font-size:20px; color:#222;">Введите пароль для задачи #<?= htmlspecialchars((string)($task['id'] ?? '')) ?></h1>
        <?php if (!empty($task['request_number'])): ?>
            <p style="margin:6px 0; color:#333;"><strong>Номер заявки:</strong> <?= htmlspecialchars((string) $task['request_number']) ?></p>
        <?php endif; ?>
        <?php if (!empty($archive_name)): ?>
            <p style="margin:6px 0; color:#333;"><strong>Архив:</strong> <?= htmlspecialchars((string) $archive_name) ?></p>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <p style="color:#d11; margin:12px 0; font-weight:600;"><?= htmlspecialchars((string) $error) ?></p>
        <?php endif; ?>
        <?php if (isset($remaining)): ?>
            <p style="margin:6px 0; color:#444;">Осталось попыток: <?= (int) $remaining ?></p>
        <?php endif; ?>
        <form method="post" style="margin-top:16px;">
            <div style="margin-bottom:12px;">
                <input type="password" name="password" required style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:8px; font-size:14px; box-sizing:border-box;">
            </div>
            <button type="submit" style="background:#2563eb; color:#fff; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600;">
                Отправить
            </button>
        </form>
    </div>
</body>
</html>

