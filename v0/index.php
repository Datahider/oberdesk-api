<?php

use losthost\OberdeskAPI\functions\AbstractFunctionImplementation;
use losthost\OberdeskAPI\functions\getDashboardData;
use losthost\OberdeskAPI\input\AbstractInput;

require '../vendor/autoload.php';
require '../etc/db.php';

$method = ($_SERVER['REQUEST_METHOD']);

$method_handler_class = 'losthost\\OberdeskAPI\\input\\Input'. $method;

if (is_a($method_handler_class, AbstractInput::class, true)) {
    $method_handler = new $method_handler_class();
    $result = $method_handler->process();
    echo json_encode($result);
} else {
    throw new \Exception('Не верный метод '. $method);
}

