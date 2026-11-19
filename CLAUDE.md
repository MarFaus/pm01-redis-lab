# Касса 6379: очереди (ПМ01, работа 49)

Форк проекта 48-redis-lab: добавлены очереди (`/queue`, Jobs, `tasks/`).

## Запуск и тесты
- Запуск: `start.bat` (логика в `scripts/start.ps1`, он же открывает окно Worker
  `queue:work redis` и ставит в `.env` `QUEUE_CONNECTION=redis`), остановка Redis: `stop.bat`.
- Задания 1–2: `tasks.bat`. `pcntl_fork` только в Docker `php:8.3-cli`, без Docker идёт `concurrent-windows.php`.
- Не проверено вживую: Worker + Redis целиком и ветка Docker в `tasks.bat` (Docker был выключен).
- `artisan serve` однопоточный: пока идёт `/sync-order`, опрос статуса на `/queue` ждёт.
- Тесты: `php artisan test` — нужен Redis на 6379, тесты пишут в базы Redis 10–12.
- Redis дома: Docker-контейнер `redis-learning` (`redis:7`), `docker start redis-learning`.
- Проверка батника «как в колледже»: клон в папку с кириллицей, PATH только System32,
  `KASSA_NONINTERACTIVE=1` (без браузера и Read-Host), `KASSA_NO_DOCKER=1` (ветка переносного Redis).

## Решения
- Базы Redis: 0 сайт, 1 кеш Laravel, 2 лаборатория (FLUSHDB в консоли не трогает сайт).
- Префиксы Redis и кеша пустые: ключи как в методичке (`events:list`, `event:100:views`).
- Predis, а не phpredis: в колледже не будет расширения redis.
- SQLite вместо PostgreSQL: запуск без установки СУБД.
- В кеш кладутся массивы, не модели: в Laravel 13 `cache.serializable_classes = false`.
- Без Vite и Node: CSS и JS лежат в `public/`, Node в колледже не нужен.
- Ошибка команды в консоли отдаётся с HTTP 200 и `ok:false`, как обычный ответ redis-cli.

## Грабли, на которые уже наступили
- cmd теряет метки (`call :label`) в UTF-8 батнике с `chcp 65001`. Поэтому `start.bat`
  только ASCII, а весь русский текст лежит в `.ps1` (UTF-8 с BOM для PowerShell 5.1).
- Переменная `COMPOSER` в окружении — это путь к composer.json для самого Composer. Не называть так свои переменные.
- `php.exe` из папки с кириллицей ломает `artisan serve`. Инструменты в таком случае
  кладутся в `C:\Users\Public\kassa6379-tools`.
- Сидер с событиями модели при выключенном Redis ждал таймаут на каждой записи (12 с).
  `DatabaseSeeder` использует `WithoutModelEvents`.
- Замер «Чека запроса» включал открытие соединений: оба соединения прогреваются до замера.
- `postJson` в тестах шлёт cookie только с `withCredentials()`.
- Laravel отключает CSRF в тестах: проверка 419 делалась вручную через curl.
- `.env` правами закрыт от агента: менять его просит пользователя или делает start.bat.

## Не сделано
- `AGENTS.md`, `package.json`, `vite.config.js`, `resources/css`, `resources/js`, `welcome.blade.php`
  остались от шаблона Laravel и не используются: удаление не разрешили правами агента.
