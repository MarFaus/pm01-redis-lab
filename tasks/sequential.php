<?php
// Задание 1. Последовательное выполнение.

function taskOne()
{
    echo "Задача 1: начало\n";
    sleep(2);
    echo "Задача 1: завершена\n";
}

function taskTwo()
{
    echo "Задача 2: начало\n";
    sleep(2);
    echo "Задача 2: завершена\n";
}

function taskThree()
{
    echo "Задача 3: начало\n";
    sleep(2);
    echo "Задача 3: завершена\n";
}

$start = microtime(true);

taskOne();
taskTwo();
taskThree();

$end = microtime(true);

echo "Общее время: "
    . round($end - $start, 2)
    . " секунд\n";
