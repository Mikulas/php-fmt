<?php

require __DIR__ . '/../vendor/autoload.php';

ini_set('xdebug.max_nesting_level', 3000); // TODO condition this

$file = $argv[1];
$code = file_get_contents($file);

$parser = new PhpParser\Parser(new \PhpFmt\KeepOriginalValueLexer());
$stmts = $parser->parse($code);

$printer = new \PhpFmt\Printer();
echo $printer->prettyPrintFile($stmts);
