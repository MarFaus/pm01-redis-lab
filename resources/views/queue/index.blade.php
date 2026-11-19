@extends('layouts.app')
@section('title', 'Очереди')

@section('content')
<style>
    .q-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 28px; margin-top: 24px; }
    .q-runs { display: grid; gap: 12px; }
    .q-run { display: grid; grid-template-columns: 1fr auto; gap: 6px 16px; align-items: center; padding: 16px 18px; background: #fff; border-radius: 6px; border: 1px solid #c9ced6; }
    .q-run__title { font-weight: 700; }
    .q-run__route { font-family: 'JetBrains Mono', monospace; font-size: 14px; color: #4a5568; }
    .q-run__out { grid-column: 1 / -1; font-family: 'JetBrains Mono', monospace; font-size: 14px; min-height: 1.4em; }
    .q-run__out b { font-size: 20px; }
    .q-run.is-busy .q-run__out::after { content: 'жду ответ сервера…'; color: #6b7280; }
    .q-log { list-style: none; margin: 0; padding: 0; font-family: 'JetBrains Mono', monospace; font-size: 13px; }
    .q-log li { padding: 8px 0; border-bottom: 1px dashed #c9ced6; overflow-wrap: anywhere; }
    .q-log .lvl-warning { color: #9a3412; }
    .q-pending { font-size: 40px; font-weight: 800; line-height: 1; }
    .q-table { width: 100%; border-collapse: collapse; margin-top: 12px; background: #fff; }
    .q-table th, .q-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #dde1e7; vertical-align: top; }
    .q-section { margin-top: 44px; max-width: 900px; }
    .q-section p { max-width: 72ch; }
    @media (max-width: 860px) { .q-grid { grid-template-columns: 1fr; } }
</style>

<h1 class="display">Очереди</h1>
<p class="muted" style="max-width:72ch">
    Нажмите кнопки и сравните время ответа. Синхронный заказ держит браузер 3 секунды,
    асинхронный отвечает сразу, а работу делает Worker в отдельном окне.
    Сейчас очередь работает через <code>{{ $connection }}</code>.
</p>

<div class="q-grid" data-queue data-status="{{ route('queue.status') }}">
    <section class="q-runs" aria-label="Маршруты">
        <div class="q-run" data-url="{{ route('task.sync-order') }}">
            <div><div class="q-run__title">Синхронный заказ</div><div class="q-run__route">/sync-order · задание 4</div></div>
            <button class="btn btn--small" type="button">Оформить</button>
            <div class="q-run__out" data-out></div>
        </div>
        <div class="q-run" data-url="{{ route('task.async-order') }}">
            <div><div class="q-run__title">Асинхронный заказ</div><div class="q-run__route">/async-order · задания 3 и 5</div></div>
            <button class="btn btn--small" type="button">Оформить</button>
            <div class="q-run__out" data-out></div>
        </div>
        <div class="q-run" data-url="{{ route('task.ticket-document') }}">
            <div><div class="q-run__title">Сформировать билет</div><div class="q-run__route">/ticket-document · GenerateTicketDocument, 5 с</div></div>
            <button class="btn btn--small" type="button">Поставить в очередь</button>
            <div class="q-run__out" data-out></div>
        </div>
        <div class="q-run" data-url="{{ route('task.double-order') }}">
            <div><div class="q-run__title">Двойной клик</div><div class="q-run__route">/double-order · одна задача дважды</div></div>
            <button class="btn btn--small" type="button">Отправить дважды</button>
            <div class="q-run__out" data-out></div>
        </div>
    </section>

    <section aria-labelledby="worker-title">
        <h2 id="worker-title" class="side-title">Worker</h2>
        <p class="muted small">Задач ждёт в очереди</p>
        <p class="q-pending" data-pending>—</p>
        <p class="muted small" style="margin-top:16px">Последние записи <code>storage/logs/laravel.log</code>, обновляются раз в секунду:</p>
        <ul class="q-log" data-log aria-live="polite"><li class="muted">Пока пусто</li></ul>
    </section>
</div>

<section class="q-section">
    <h2 class="side-title">Задания 1 и 2: последовательно и конкурентно</h2>
    <p>Скрипты лежат в папке <code>tasks/</code>. Запуск: <code>tasks.bat</code>. Он выполнит <code>sequential.php</code>
       (около 6 с), затем <code>concurrent.php</code> с <code>pcntl_fork()</code> в Linux-контейнере Docker (около 2 с).
       Если Docker нет, запускается <code>concurrent-windows.php</code>: те же три задачи в трёх отдельных процессах PHP.</p>
    <table class="q-table">
        <tr><th>Параметр</th><th>Последовательно</th><th>Конкурентно</th></tr>
        <tr><td>Количество задач</td><td>3</td><td>3</td></tr>
        <tr><td>Время одной задачи</td><td>2 с</td><td>2 с</td></tr>
        <tr><td>Общее время</td><td>≈ 6 с</td><td>≈ 2 с</td></tr>
        <tr><td>Количество процессов</td><td>1</td><td>4 (родительский и три дочерних)</td></tr>
    </table>
</section>

<section class="q-section">
    <h2 class="side-title">Кто что делает</h2>
    <p><b>Laravel</b> принимает HTTP-запрос, сохраняет заказ и через <code>dispatch()</code> кладёт задачу в очередь, после чего сразу отвечает пользователю.
       <b>Redis</b> хранит очередь: задача лежит в списке <code>queues:default</code> (команда <code>RPUSH</code>), пока её не заберут.
       <b>Worker</b> (<code>php artisan queue:work redis</code>) это отдельный процесс, который в цикле забирает задачи из Redis и выполняет <code>handle()</code>.
       Если задача упала, Worker повторит её (<code>$tries = 3</code>), а после этого запишет в <code>failed_jobs</code>.</p>
</section>

<section class="q-section">
    <h2 class="side-title">Доп. задание: какие операции в фон</h2>
    <table class="q-table">
        <tr><th>Операция</th><th>Обработка</th><th>Почему</th></tr>
        <tr><td>Проверить доступность билета</td><td>Синхронно</td><td>Нельзя подтвердить заказ, не зная, есть ли билет</td></tr>
        <tr><td>Создать заказ</td><td>Синхронно</td><td>Пользователь должен получить номер заказа</td></tr>
        <tr><td>Зафиксировать оплату</td><td>Синхронно</td><td>Финансовый результат должен быть записан до ответа</td></tr>
        <tr><td>Сформировать электронный билет</td><td>Фон</td><td>Занимает секунды, билет можно прислать чуть позже</td></tr>
        <tr><td>Отправить письмо</td><td>Фон</td><td>Почтовый сервер может отвечать медленно</td></tr>
        <tr><td>Обновить статистику</td><td>Фон</td><td>Не влияет на подтверждение заказа</td></tr>
    </table>
    <p style="margin-top:16px"><b>Двойной клик.</b> Без защиты пользователь получит два письма и два билета.
       Решение в три слоя: у заказа есть статус, и задача ничего не делает, если он уже «уведомлён»;
       у операции есть уникальный ключ (номер заказа), и форма отправляет его вместе с запросом;
       обработка идемпотентна: <code>SendOrderNotification</code> сначала атомарно ставит отметку
       <code>order:{id}:notified</code> через <code>Cache::add</code> (в Redis это <code>SET NX</code>), и вторая копия задачи видит отметку и выходит.
       Кнопка «Двойной клик» показывает это: в журнале одно «Уведомление отправлено» и одно «Повтор задачи пропущен».</p>
</section>

<script>
(() => {
    const root = document.querySelector('[data-queue]');
    root.querySelectorAll('.q-run').forEach((card) => {
        const btn = card.querySelector('button');
        const out = card.querySelector('[data-out]');
        btn.addEventListener('click', async () => {
            card.classList.add('is-busy'); btn.disabled = true; out.textContent = '';
            const t0 = performance.now();
            try {
                const res = await fetch(card.dataset.url, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                const ms = Math.round(performance.now() - t0);
                out.innerHTML = '';
                const b = document.createElement('b'); b.textContent = (ms / 1000).toFixed(2) + ' с';
                out.append('Ответ через ', b, ' · ' + data.message + (data.order_id ? ', заказ ' + data.order_id : ''));
            } catch (e) {
                out.textContent = 'Ошибка: ' + e.message;
            } finally {
                card.classList.remove('is-busy'); btn.disabled = false; poll();
            }
        });
    });

    const pending = root.querySelector('[data-pending]');
    const log = root.querySelector('[data-log]');
    async function poll() {
        try {
            const data = await (await fetch(root.dataset.status, { headers: { Accept: 'application/json' } })).json();
            pending.textContent = data.pending ?? '—';
            log.innerHTML = '';
            if (!data.log.length) { log.innerHTML = '<li class="muted">Пока пусто</li>'; return; }
            for (const row of data.log) {
                const li = document.createElement('li');
                li.className = 'lvl-' + row.level;
                li.textContent = row.time + '  ' + row.text;
                log.append(li);
            }
        } catch (e) { /* сервер перезапускается, попробуем через секунду */ }
    }
    poll();
    setInterval(poll, 1000);
})();
</script>
@endsection
