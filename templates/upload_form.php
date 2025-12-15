<?php /** @var array $task */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Загрузка файла</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f5; font-family:Arial, sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh;">
    <div style="background:#fff; padding:24px 28px; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.12); max-width:420px; width:90%; text-align:center;">
        <h1 style="margin-top:0; font-size:20px; color:#222;">
            Загрузка файла — <?= htmlspecialchars((string)($task['request_number'] ?? ('#' . ($task['id'] ?? '')))) ?>
        </h1>
        <?php if (!empty($error)): ?>
            <p style="color:#d11; margin:12px 0; font-weight:600;"><?= htmlspecialchars((string) $error) ?></p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="" style="margin-top:16px;">
            <div style="margin-bottom:12px;">
                <input type="file" name="file" required style="width:100%;"/>
            </div>
            <button type="submit" style="background:#2563eb; color:#fff; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600;">
                Отправить
            </button>
        </form>
    </div>
</body>
</html>

