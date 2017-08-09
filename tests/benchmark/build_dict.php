<?php

require __DIR__.'/../bootstrap.php';

$handle = @fopen(__DIR__."/dict.txt", "r");
if (!$handle) {
    die("\ndict.txt not exist!\n");
}


$dict = new \Adarts\Dictionary();

$t = microtime(true);

while (!feof($handle)) {
    $word = trim(fgets($handle, 256));
    if (!$word) {
        continue;
    }
    $dict->add($word);
}
fclose($handle);

$dict->confirm();

echo "dict build timecost: ".round((microtime(true) - $t) * 1000)."ms\n";

// 不使用 simplitfy() 方法可获得反向翻译功能，但字典容量会大大增加。
$packed = serialize($dict->simplify());
file_put_contents(__DIR__.'/packed.bin', $packed);

echo "done.\n";
