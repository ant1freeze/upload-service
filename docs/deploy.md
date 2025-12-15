# Развёртывание upload-service на Astra 1.7.8 (nginx 1.28, PHP 8.1)

## Требования
- ОС: Astra Linux 1.7.8 (SE)
- nginx 1.28.x
- PHP 8.1.x с модулями: `pdo_sqlite`, `sqlite3`, `zip`, `mbstring`, `curl`, `xml`, `openssl`, `opcache`
- Утилиты: `git`, `zip`, `unzip`, `sqlite3`

Проверенные версии на текущем сервере:
- nginx 1.28.0
- PHP 8.1.12-1ubuntu4.3.astra2
- sqlite3 3.34.1 (CLI и libsqlite)
- zip 3.0-11astra3+b3, unzip 6.0-23+deb10u3astra1+b3

## Установка пакетов
```bash
apt update
apt install -y nginx git zip unzip sqlite3 \
  php8.1-fpm php8.1-cli php8.1-sqlite3 php8.1-zip php8.1-mbstring php8.1-xml php8.1-curl
```

## Клонирование проекта
```bash
cd /var/www
git clone git@github.com:ant1freeze/upload-service.git
cd upload-service
```

## Конфигурация (`config.php`)
Создайте файл `config.php` (не в git):
```php
<?php
return [
    "db_path" => "/var/www/upload-service/var/db/tasks.sqlite",
    "upload_dir" => "/var/www/upload-service/var/uploads",
    "max_upload_mb" => 100,
    "token_ttl_minutes" => 60,
    "base_url" => "http://<домен_или_IP>",
    "encryption_key" => null, // или строка для AES-256-GCM
    "rate_limit_per_minute" => 30,
    "max_password_attempts" => 5,
    "api_token" => "<openssl rand -hex 32>",
];
```

Права:
```bash
mkdir -p var/db var/uploads var/log
chown -R www-data:www-data var
chmod 700 var var/db var/uploads var/log
chmod 600 config.php
```

## PHP лимиты
В `/etc/php/8.1/fpm/php.ini`:
```
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 256M
```
Перезапуск: `systemctl restart php8.1-fpm`

## Конфиг nginx (пример HTTP)
`/etc/nginx/sites-available/upload-service.conf`:
```
server {
    listen 127.0.0.1:80;
    server_name localhost;
    root /var/www/upload-service/public;
    index index.php;
    client_max_body_size 100M;

    location ~ /\.(git|env) { deny all; }
    location /       { try_files $uri /index.php$is_args$args; }
    location /api/   { try_files $uri /index.php$is_args$args; }
    location /t/     { try_files $uri /index.php$is_args$args; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }
}

server {
    listen 80;
    server_name <внешний_домен_или_IP>;
    root /var/www/upload-service/public;
    index index.php;
    client_max_body_size 100M;

    location ~ /\.(git|env) { deny all; }
    location /t/ {
        try_files $uri /index.php$is_args$args;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }
    location /api/ { deny all; }
    location / { return 403; }
}
```
Активировать:
```bash
ln -s /etc/nginx/sites-available/upload-service.conf /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```
Для HTTPS добавьте `listen 443 ssl;` и сертификаты.

## Проверка
Создать задачу upload:
```bash
curl -s -X POST http://127.0.0.1/api/tasks \
  -H "Authorization: Bearer <API_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"type":"upload"}'
```
Открыть форму: `http://<домен>/t/<token>` из ответа.

Для archive_password (проверка пароля ZIP):
```bash
curl -s -X POST http://127.0.0.1/api/tasks \
  -H "Authorization: Bearer <API_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"type":"archive_password","archive_path":"/tmp/test-verify.zip","request_number":"TICKET-XYZ"}'
```
В форме ввести пароль; если подходит — статус `done`, пароль доступен в `GET /api/tasks/{id}` (поле `password`).

Статус задачи:
```bash
curl -s http://127.0.0.1/api/tasks/<id> \
  -H "Authorization: Bearer <API_TOKEN>"
```

## Заметки безопасности
- API доступен только с 127.0.0.1; наружу — только `/t/`.
- `config.php` и var/* не в репозитории; права 600/700.
- При включении `encryption_key` пароли шифруются (AES-256-GCM). При `null` — хранятся в plaintext.

## Полезные версии/команды на референсном сервере
- Astra 1.7.8 SE (`/etc/issue`)
- nginx: `nginx -v` → 1.28.0
- PHP: `php -v` → 8.1.12-1ubuntu4.3.astra2
- PHP модули: `php -m` (pdo_sqlite, sqlite3, zip, mbstring, curl, xml, openssl, opcache)
- sqlite3: `sqlite3 --version` → 3.34.1


