<?php

require_once('vendor/autoload.php');

try {
    unset($argv[0]);

    // Составляем полное имя класса, добавив нэймспейс
    $className = '\\Downloader\\' . array_shift($argv);

    foreach ($argv as $argument) {
        preg_match('/^-(.+)=(.+)$/', $argument, $matches);
        if (!empty($matches)) {
            $paramName = $matches[1];
            $paramValue = $matches[2];

            $params[$paramName] = $paramValue;
        }
    }

    // Создаём экземпляр класса, передав параметры и вызываем метод execute()
    $class = new $className($params);
    $class->downloadPage();
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}