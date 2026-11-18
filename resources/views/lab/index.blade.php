@extends('layouts.app')
@section('title', 'Лаборатория')
@section('body-class', 'is-lab')

@section('content')
<div class="lab-head">
    <h1 class="display">Лаборатория</h1>
    <p class="lab-head__lead">
        Консоль работает с отдельной базой Redis №&nbsp;{{ config('database.redis.lab.database') }}.
        Здесь можно выполнить любые команды из заданий, даже <code>FLUSHDB</code>: афиша и её просмотры живут в базе №&nbsp;{{ config('database.redis.default.database') }} и не пострадают.
    </p>
    <p @class(['lab-head__task1', 'is-done' => $health['online']])>
        <span class="mark" aria-hidden="true"></span>
        @if ($health['online'])
            Задание 1 выполнено: Redis {{ $health['version'] }} запущен ({{ $health['mode'] }}).
        @else
            Задание 1: Redis не запущен. Откройте <code>start.bat</code>, он запустит Redis в Docker.
        @endif
    </p>
</div>

<div class="lab" data-lab
     data-run="{{ route('lab.run') }}"
     data-keys="{{ route('lab.keys') }}"
     data-check="{{ route('lab.check') }}">

    <section class="lab__tasks" aria-labelledby="tasks-title">
        <h2 id="tasks-title" class="side-title">Задания</h2>
        <p class="muted small">Нажмите на команду, и она окажется в консоли.</p>

        @foreach ($tasks as $task)
            <details class="task" data-task="{{ $task['id'] }}" @if ($loop->first) open @endif>
                <summary class="task__head">
                    <span class="task__num">{{ $task['number'] }}</span>
                    <span class="task__title">{{ $task['title'] }}</span>
                    <span class="mark" data-mark aria-hidden="true"></span>
                </summary>
                <div class="task__body">
                    <p>{{ $task['goal'] }}</p>
                    <ul class="task__cmds">
                        @foreach ($task['commands'] as $cmd)
                            <li><button type="button" class="cmd" data-cmd="{{ $cmd }}">{{ $cmd }}</button></li>
                        @endforeach
                    </ul>
                    <p class="task__todo-title">Сделайте сами</p>
                    <ul class="task__todo">
                        @foreach ($task['todo'] as $todo)
                            <li>{{ $todo }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn btn--line btn--small" data-check-btn>Проверить задание</button>
                    <p class="task__result" data-result aria-live="polite"></p>
                </div>
            </details>
        @endforeach

        <div class="task task--static">
            <div class="task__head">
                <span class="task__num">7</span>
                <span class="task__title">Redis в Laravel</span>
            </div>
            <div class="task__body">
                <p>Маршруты из методички уже есть в <code>routes/web.php</code>:</p>
                <ul class="task__links">
                    <li><a href="{{ route('task.redis-test') }}" target="_blank">/redis-test</a>: SET и GET через фасад Redis</li>
                    <li><a href="{{ route('task.views', 1) }}" target="_blank">/events/1/views</a>: INCR, ответ в JSON</li>
                    <li><a href="{{ route('task.events-cache') }}" target="_blank">/events-cache</a>: Cache::remember из доп. задания</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="lab__console" aria-labelledby="console-title">
        <h2 id="console-title" class="visually-hidden">Консоль Redis</h2>
        <div class="term">
            <div class="term__out" data-out role="log" aria-live="polite">
                <p class="term__hint">Это redis-cli в браузере. Наберите команду и нажмите Enter, клавиша ↑ вернёт предыдущую.</p>
            </div>
            <form class="term__in" data-form autocomplete="off">
                <label class="term__prompt" for="cmd-input">127.0.0.1:6379[{{ config('database.redis.lab.database') }}]&gt;</label>
                <input id="cmd-input" class="term__input" data-input type="text" spellcheck="false"
                       placeholder="SET student:name &quot;Александр&quot;" maxlength="1000" autocapitalize="off">
                <button class="term__run" type="submit">Выполнить</button>
            </form>
        </div>
        <div class="term__tools">
            <button type="button" class="linkish" data-clear>Очистить экран</button>
            <span class="muted small" data-left></span>
        </div>
    </section>

    <section class="lab__keys" aria-labelledby="keys-title">
        <div class="keys__head">
            <h2 id="keys-title" class="side-title">Ключи</h2>
            <div class="seg" role="tablist" aria-label="База Redis">
                <button type="button" role="tab" class="seg__item is-active" aria-selected="true" data-scope="lab">Лаборатория</button>
                <button type="button" role="tab" class="seg__item" aria-selected="false" data-scope="app">Сайт</button>
            </div>
        </div>
        <p class="muted small" data-keys-note>Перебор через <code>SCAN</code>: в отличие от <code>KEYS *</code>, он не блокирует сервер.</p>
        <ul class="keys" data-keys aria-live="polite"></ul>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/lab.js') }}?v={{ filemtime(public_path('js/lab.js')) }}" defer></script>
@endpush
