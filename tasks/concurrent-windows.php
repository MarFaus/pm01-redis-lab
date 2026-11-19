<?php
// Задание 2 для Windows, где нет pcntl_fork().
// Идея та же: родительский процесс запускает три дочерних процесса PHP
// и ждёт, пока все они завершатся. Процессов тоже 4.

$start = microtime(true);

$tasks = [
    'Задача 1',
    'Задача 2',
    'Задача 3',
];

$child = 'echo $argv[1] . ": начало\n"; sleep(2); echo $argv[1] . ": завершена\n";';
$processes = [];

foreach ($tasks as $task) {
    $process = proc_open([PHP_BINARY, '-r', $child, $task], [1 => STDOUT, 2 => STDERR], $pipes);

    if (! is_resource($process)) {
        die("Ошибка создания процесса\n");
    }

    $processes[] = $process;
}

foreach ($processes as $process) {
    proc_close($process);
}

$end = microtime(true);

echo "Общее время: "
    . round($end - $start, 2)
    . " секунд\n";
