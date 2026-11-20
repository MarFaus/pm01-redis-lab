# Касса 6379 (ПМ01, ЛР 48–50)

Один проект на все ЛР: репозиторий MarFaus/pm01-redis-lab, ветка `main`. После каждой ЛР
дописывать в README раздел «ЛР N» и строку в таблицу наверху. Локальная папка: `D:\Проекты\PM01\49-queue-lab`
(48-redis-lab — старая копия, в ней не работать).

## Запуск и тесты
- Запуск: `start.bat` → `scripts/start.ps1`, 7 шагов: PHP, composer, .env, SQLite, Redis, RabbitMQ, сайт.
  Открывает окна Worker (`queue:work redis`) и Consumer (`rabbitmq:consume`). Остановка: `stop.bat`.
- ЛР 49 задания 1–2: `tasks.bat`. ЛР 50 практическая 1: `rabbitmq.bat` (скрипты в `rabbitmq-producer/`).
- Тесты: `php artisan test` (55). Redis-тесты пишут в базы 10–12 и пропускаются без Redis.
- Дома: системный `php` без `sockets`; для работы брать `C:\Users\Public\kassa6379-tools\php\php.exe`
  (там sockets включён) и `composer.phar` оттуда же.
- Проверка «как в колледже»: клон в папку с кириллицей, PATH только System32 + Docker,
  `KASSA_NONINTERACTIVE=1` (без браузера, Worker и Consumer), `KASSA_NO_DOCKER=1`.

## Решения
- Базы Redis: 0 сайт, 1 кеш Laravel, 2 лаборатория. Префиксы пустые: ключи как в методичке.
- Predis, SQLite, без Vite/Node: в колледже ничего этого не ставят.
- ЛР 50: таблица events с ЛР 48, поля методички под нашими именами (title=name, event_date=starts_at),
  `available_tickets` добавлен миграцией. Обработчик Consumer вынесен в `OrderTicketProcessor` ради тестов.
- Учебные Producer'ы пишут в `events_queue` и `ticket_routed_queue`, а не в `ticket_queue`:
  её слушает Consumer портала и nack'ает чужие сообщения. `--methodology` — строго по тексту.
- Consumer переподключается каждые 5 с, чтобы start.bat мог открыть его до готовности RabbitMQ.

## Грабли
- В колледже системный composer привязан к PHP 8.2 (XAMPP): composer.phar всегда запускать тем же `$PHP`.
- Новые расширения (sockets для php-amqplib): start.ps1 сам дописывает их в ini переносного PHP.
- cmd теряет метки в UTF-8 батниках: `.bat` только ASCII, русский текст в `.ps1` (UTF-8 с BOM).
  Python-строки для батников: `\t` в `scripts\tasks` превращается в табуляцию.
- `php.exe` из папки с кириллицей ломает `artisan serve`: инструменты в `C:\Users\Public\kassa6379-tools`.
- Переменная окружения `COMPOSER` занята самим Composer.
- Счёт сообщений после publish без `confirm_select()` занижен на одно.
- `.env` закрыт от агента правами; `rm`, отправка форм через curl тоже запрещены — проверять в браузере.

## Не сделано
- Шаблонные `AGENTS.md`, `package.json`, `vite.config.js`, `welcome.blade.php` не удалены (права).
- Не проверены в колледже: ветка Docker в `tasks.bat`, установка Docker через winget.
