<?php
/**
 * @author andares
 */
define('IN_TEST', 1);

require __DIR__ . '/../vendor/autoload.php';


// 调试支持
Tracy\Debugger::$maxDepth  = 10;
Tracy\Debugger::$maxLength = 500;

function du($var, $flag = null) {
    static $count = 0;
    $count++;

    if ($flag) {
        Tracy\Debugger::dump("=== Dump $flag ===");
    } else {
        Tracy\Debugger::dump("=== Dump #$count ===");
    }
    Tracy\Debugger::dump($var);
}
