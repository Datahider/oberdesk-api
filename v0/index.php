<?php

use losthost\OberdeskAPI\functions\AbstractFunctionImplementation;
use losthost\OberdeskAPI\functions\getDashboardData;

require '../vendor/autoload.php';
require '../etc/db.php';

$params = [];
foreach ($_GET as $key=>$value) {
    if (is_array($_GET[$key])) {
        $params[$key] = filter_input(INPUT_GET, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    } else {
        $params[$key] = filter_input(INPUT_GET, $key);
    }
}

if (isset($params['function'])) {
    $function = $params['function'];
    unset($params['function']);
} else {
    throw new \Exception('Не передано имя функции');
}

if (is_a('losthost\\OberdeskAPI\\functions\\'. $function, AbstractFunctionImplementation::class, true)) {
    $handler = new ('losthost\\OberdeskAPI\\functions\\'. $function)();
    if (!is_a($handler, AbstractFunctionImplementation::class)) {
        throw new \Exception('Не верная функция '. $function);
    }
    $handler->checkParams($params);
    $result = $handler->run($params);
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
} else {
    throw new \Exception('Не верная функция '. $function);
}