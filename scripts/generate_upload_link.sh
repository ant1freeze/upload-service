#!/bin/bash
# Скрипт для Jenkins pipeline: создаёт задачу upload и выдаёт ссылку

set -euo pipefail

# Конфигурация (можно вынести в переменные окружения Jenkins)
API_URL="${UPLOAD_API_URL:-http://127.0.0.1/api/tasks}"
API_TOKEN="${UPLOAD_API_TOKEN:-}"

if [ -z "$API_TOKEN" ]; then
    echo "ERROR: UPLOAD_API_TOKEN не задан" >&2
    exit 1
fi

# Создаём задачу upload
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"type":"upload"}')

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | head -n-1)

if [ "$HTTP_CODE" != "200" ]; then
    echo "ERROR: API вернул код $HTTP_CODE" >&2
    echo "$BODY" >&2
    exit 1
fi

# Парсим JSON и извлекаем url
URL=$(echo "$BODY" | grep -o '"url":"[^"]*"' | cut -d'"' -f4)

if [ -z "$URL" ]; then
    echo "ERROR: Не удалось извлечь URL из ответа" >&2
    echo "$BODY" >&2
    exit 1
fi

# Выводим ссылку
echo "=========================================="
echo "Ссылка для загрузки файла:"
echo "$URL"
echo "=========================================="

# Сохраняем в файл для последующего использования
echo "$URL" > "${WORKSPACE:-.}/upload_link.txt"
echo "ID задачи: $(echo "$BODY" | grep -o '"id":[0-9]*' | cut -d':' -f2)" > "${WORKSPACE:-.}/task_id.txt"

# Возвращаем URL для использования в pipeline
echo "$URL"

