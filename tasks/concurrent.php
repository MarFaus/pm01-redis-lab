<?php
// Задание 2. Конкурентное выполнение через pcntl_fork().
// Работает только в CLI на Linux/macOS с расширением PCNTL.
// tasks.bat запускает его в Linux-контейнере Docker.

$start = microtime(true);

$tasks = [
    'Задача 1',
    'Задача 2',
    'Задача 3',
];

$processes = [];

foreach ($tasks as $task) {
    $pid = pcntl_fork();

    if ($pid === -1) {
        die("Ошибка создания процесса\n");
    }

    if ($pid === 0) {
        echo "$task: начало\n";
        sleep(2);
        echo "$task: завершена\n";
        exit(0);
    }

    $processes[] = $pid;
}

foreach ($processes as $pid) {
    pcntl_waitpid($pid, $status);
}

$end = microtime(true);

echo "Общее время: "
    . round($end - $start, 2)
    . " секунд\n";
