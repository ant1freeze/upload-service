# Скрипты для Jenkins Pipeline

## Генерация ссылки для загрузки файла

### Вариант 1: Использование bash-скрипта

```bash
# Установите переменные окружения
export UPLOAD_API_URL="http://127.0.0.1/api/tasks"
export UPLOAD_API_TOKEN="ваш_api_token"

# Запустите скрипт
./scripts/generate_upload_link.sh
```

Скрипт выведет ссылку в консоль и сохранит её в `upload_link.txt` и `task_id.txt`.

### Вариант 2: Прямой вызов из Jenkins Pipeline

```groovy
stage('Генерация ссылки') {
    steps {
        script {
            def response = sh(
                script: """
                    curl -s -X POST http://127.0.0.1/api/tasks \\
                        -H "Authorization: Bearer \${UPLOAD_API_TOKEN}" \\
                        -H "Content-Type: application/json" \\
                        -d '{"type":"upload"}'
                """,
                returnStdout: true
            )
            
            def url = sh(
                script: "echo '${response}' | grep -o '\"url\":\"[^\"]*\"' | cut -d'\"' -f4",
                returnStdout: true
            ).trim()
            
            echo "Ссылка для загрузки: ${url}"
            env.UPLOAD_URL = url
        }
    }
}
```

### Вариант 3: Использование Python (если доступен)

```python
#!/usr/bin/env python3
import os
import json
import requests

API_URL = os.getenv('UPLOAD_API_URL', 'http://127.0.0.1/api/tasks')
API_TOKEN = os.getenv('UPLOAD_API_TOKEN')

if not API_TOKEN:
    print("ERROR: UPLOAD_API_TOKEN не задан", file=sys.stderr)
    exit(1)

response = requests.post(
    API_URL,
    headers={
        'Authorization': f'Bearer {API_TOKEN}',
        'Content-Type': 'application/json'
    },
    json={'type': 'upload'}
)

if response.status_code != 200:
    print(f"ERROR: API вернул код {response.status_code}", file=sys.stderr)
    exit(1)

data = response.json()
print("=" * 50)
print(f"Ссылка для загрузки файла:")
print(data['url'])
print("=" * 50)
print(data['url'])  # Для использования в pipeline
```

## Проверка статуса загрузки

```bash
# Получить статус задачи
curl -s http://127.0.0.1/api/tasks/<id> \
    -H "Authorization: Bearer $UPLOAD_API_TOKEN"

# Пример ответа:
# {"id":1,"type":"upload","status":"pending","file":null,...}
# {"id":1,"type":"upload","status":"done","file":"filename.zip",...}
```

## Полный пример Jenkins Pipeline

См. `Jenkinsfile.example` для полного примера с ожиданием загрузки.

## Настройка Jenkins

1. Создайте credentials в Jenkins:
   - Тип: Secret text
   - ID: `upload-service-api-token`
   - Secret: ваш API токен из `config.php`

2. Добавьте в Jenkinsfile:
   ```groovy
   environment {
       UPLOAD_API_TOKEN = credentials('upload-service-api-token')
   }
   ```

3. Используйте скрипт или прямой вызов API в нужном этапе пайплайна.

