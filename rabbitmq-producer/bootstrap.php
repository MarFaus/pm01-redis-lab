<?php

// Своей папки vendor у этого учебного проекта может не быть: тогда берём
// библиотеку php-amqplib из основного Laravel-проекта уровнем выше.
// (composer install здесь тоже работает, тогда используется своя vendor.)
foreach ([__DIR__.'/vendor/autoload.php', __DIR__.'/../vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;

        return;
    }
}

fwrite(STDERR, "Не найден vendor/autoload.php. Выполните composer install или запустите start.bat.\n");
exit(1);
