# Oracle Always Free: Laravel + FastAPI (single server)

## 1. Архитектура

- Laravel (Nginx + PHP-FPM) на одном VM.
- FastAPI слушает только localhost: `127.0.0.1:8000`.
- Laravel отправляет аудио в FastAPI по внутреннему URL: `http://127.0.0.1:8000/process-audio`.

Это безопасно, быстро и без внешних hop'ов.

## 2. Подготовка сервера (Ubuntu 22.04/24.04)

```bash
sudo apt update
sudo apt install -y nginx git curl unzip software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-sqlite3 php8.3-intl php8.3-bcmath
sudo apt install -y python3 python3-venv python3-pip
```

Composer:

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
```

Node.js 20 LTS:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 3. Деплой Laravel

```bash
sudo mkdir -p /var/www/familybudget
sudo chown -R $USER:$USER /var/www/familybudget
cd /var/www/familybudget

# Клонируй свой репозиторий
# git clone ... .

composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Права:

```bash
sudo chown -R www-data:www-data /var/www/familybudget
sudo find /var/www/familybudget/storage -type d -exec chmod 775 {} \;
sudo find /var/www/familybudget/bootstrap/cache -type d -exec chmod 775 {} \;
```

## 4. Настройка .env

Минимум для voice:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain

EXPENSE_VOICE_FASTAPI_URL=http://127.0.0.1:8000/process-audio
EXPENSE_VOICE_FASTAPI_TOKEN=change-this-token
EXPENSE_VOICE_FASTAPI_CONNECT_TIMEOUT=8
EXPENSE_VOICE_FASTAPI_TIMEOUT=120
EXPENSE_VOICE_FASTAPI_RETRIES=2
EXPENSE_VOICE_FASTAPI_RETRY_SLEEP_MS=250
EXPENSE_VOICE_FASTAPI_VERIFY_SSL=false
```

Применить:

```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 5. Запуск FastAPI как systemd-сервис

В проекте уже есть шаблон: `deploy/systemd/expense-voice-fastapi.service`.

Шаги:

```bash
sudo cp /var/www/familybudget/deploy/systemd/expense-voice-fastapi.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable expense-voice-fastapi
sudo systemctl start expense-voice-fastapi
sudo systemctl status expense-voice-fastapi
```

Если путь проекта отличается от `/var/www/familybudget`, поправь `WorkingDirectory` и `ExecStart` в unit-файле.

Проверка health:

```bash
curl http://127.0.0.1:8000/health
```

Ожидается:

```json
{"status":"ok"}
```

## 6. Nginx

Пример server block:

```nginx
server {
    listen 80;
    server_name your-domain;

    root /var/www/familybudget/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Проверка и reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 7. Что ожидает FastAPI от Laravel

`POST /process-audio` (multipart/form-data):

- `audio` (файл)
- `mode` (`expense` или `notes`)
- `context` (JSON строка с `users` и `suppliers`)

Ответ JSON:

```json
{
  "status": "ok",
  "text": "...",
  "notes": "...",
  "supplier": "...",
  "user": "...",
  "date": "YYYY-MM-DD",
  "sum": 123.45
}
```

`category` не передается и не требуется от Python. В Laravel категория ставится автоматически по найденному `supplier`.

## 8. Бесплатная долгосрочная эксплуатация

- Используй UptimeRobot (5-мин ping), чтобы Oracle VM не считалась idle.
- Делай weekly backup БД и `.env`.
- Логи смотреть:
  - Laravel: `storage/logs/laravel.log`
  - FastAPI service: `journalctl -u expense-voice-fastapi -n 200 --no-pager`
- Обновления раз в месяц: security updates, `composer update`/`npm update` по необходимости.

## 9. Минимальный smoke-check после деплоя

1. Открой форму расходов в Filament.
2. Запиши голос и нажми сохранить.
3. Убедись, что:
   - текст распознался,
   - supplier нашелся,
   - category проставилась автоматически от supplier,
   - ошибок 422/500 нет.
