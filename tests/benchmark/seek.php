<?php

require __DIR__.'/../bootstrap.php';

$packed = file_get_contents(__DIR__.'/packed.bin');
$dict = unserialize($packed);

echo $dict->seek('上上下下左左右右A家宝BBA')->current(), "\n";