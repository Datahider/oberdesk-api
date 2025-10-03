<?php

namespace losthost\OberdeskAPI\input;

use losthost\OberdeskAPI\functions\AbstractFunctionImplementation;

class InputPUT extends AbstractInput{
    
    public function process() {

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

        $input_data = file_get_contents('php://input');
        error_log($input_data);
        $put_data = json_decode($input_data, true);
        
        if (is_a('losthost\\OberdeskAPI\\functions\\put\\'. $function, AbstractFunctionImplementation::class, true)) {
            $handler = new ('losthost\\OberdeskAPI\\functions\\put\\'. $function)();
            if (!is_a($handler, AbstractFunctionImplementation::class)) {
                throw new \Exception('Не верная функция '. $function);
            }
            $handler->checkParams($put_data);
            $result = $handler->run($put_data);

            header('Content-Type: application/json; charset=utf-8');

            echo json_encode($result);
        } else {
            throw new \Exception('Не верный метод вызова (GET) или функция '. $function);
        }
        
        
    }
}
