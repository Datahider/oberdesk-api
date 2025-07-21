<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace losthost\OberdeskAPI\functions;

/**
 * Description of AbstractFunctionImplementation
 *
 * @author drweb_000
 */
abstract class AbstractFunctionImplementation {
    
    abstract public function run(array $params) : array;
    abstract public function checkParams(array $params) : true;
    
}
