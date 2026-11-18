<?php

/*
 * Помощник для start.bat: проверки, которые неудобно писать на cmd.
 *
 *   php scripts/check.php php          — версия PHP >= 8.3 и нужные расширения (код 0 = всё есть)
 *   php scripts/check.php port 6379    — открыт ли порт на 127.0.0.1 (код 0 = открыт)
 *   php scripts/check.php wait 6379 20 — ждать открытия порта до N секунд
 *   php scripts/check.php free 8000    — напечатать первый свободный порт начиная с указанного
 *   php scripts/check.php ini <php.ini> — включить нужные расширения в переносном PHP
 */

const NEED_EXT = ['openssl', 'mbstring', 'pdo_sqlite', 'sqlite3', 'fileinfo', 'curl', 'zip', 'tokenizer', 'ctype'];

$cmd = $argv[1] ?? '';

function portOpen(int $port): bool
{
    $s = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
    if ($s) {
        fclose($s);

        return true;
    }

    return false;
}

switch ($cmd) {
    case 'php':
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            fwrite(STDERR, 'PHP '.PHP_VERSION.' слишком старый, нужен 8.3+'.PHP_EOL);
            exit(1);
        }
        $missing = array_values(array_filter(NEED_EXT, fn ($e) => ! extension_loaded($e)));
        if ($missing) {
            fwrite(STDERR, 'PHP '.PHP_VERSION.': нет расширений '.implode(', ', $missing).PHP_EOL);
            exit(1);
        }
        echo PHP_VERSION;
        exit(0);

    case 'port':
        exit(portOpen((int) ($argv[2] ?? 6379)) ? 0 : 1);

    case 'wait':
        $port = (int) ($argv[2] ?? 6379);
        $deadline = time() + (int) ($argv[3] ?? 20);
        while (time() < $deadline) {
            if (portOpen($port)) {
                exit(0);
            }
            usleep(500_000);
        }
        exit(1);

    case 'free':
        for ($port = (int) ($argv[2] ?? 8000); $port < 8100; $port++) {
            if (! portOpen($port)) {
                echo $port;
                exit(0);
            }
        }
        exit(1);

    case 'ini':
        $file = $argv[2];
        $ini = file_get_contents($file);
        $ini = preg_replace('/^;\s*extension_dir\s*=\s*"ext"/m', 'extension_dir = "ext"', $ini);
        foreach (NEED_EXT as $ext) {
            $ini = preg_replace('/^;\s*extension\s*=\s*'.$ext.'\s*$/m', 'extension='.$ext, $ini);
        }
        file_put_contents($file, $ini);
        exit(0);

    default:
        fwrite(STDERR, "Неизвестная команда: {$cmd}".PHP_EOL);
        exit(2);
}
