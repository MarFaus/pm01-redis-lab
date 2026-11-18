<?php

use App\Models\Event;
use Illuminate\Support\Facades\Artisan;

// Для start.bat: заполнить афишу, только если она пустая (повторный запуск не плодит дубли).
Artisan::command('kassa:seed-if-empty', function () {
    if (Event::query()->exists()) {
        $this->line('Афиша уже заполнена: '.Event::count().' мероприятий.');

        return;
    }

    $this->call('db:seed', ['--force' => true]);
})->purpose('Заполнить базу демо-мероприятиями, если она пустая');
