<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/KeepOriginalValueLexer.php';
require __DIR__ . '/Printer.php';

ini_set('xdebug.max_nesting_level', 3000); // TODO condition this

$file = $argv[1];
$code = file_get_contents($file);

$parser = new PhpParser\Parser(new KeepOriginalValueLexer());
$stmts = $parser->parse($code);

$printer = new Printer;
echo $printer->prettyPrintFile($stmts);
