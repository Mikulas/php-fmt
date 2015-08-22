<?php

/**
 * @testCase
 * @covers PhpFmt\Printer
 */

namespace PhpFmtTests;

use PhpFmt\KeepOriginalValueLexer;
use PhpFmt\Printer;
use PhpParser\Error;
use PhpParser\Parser;
use Tester\Assert;

$container = require __DIR__ . '/../bootstrap.php';


class PrinterTest extends TestCase
{

	/** @var Parser */
	private $parser;

	/** @var Printer */
	private $printer;


	protected function setUp()
	{
		parent::setUp();

		$this->parser = new Parser(new KeepOriginalValueLexer());
		$this->printer = new Printer();
	}


	/**
	 * @dataProvider getCases
	 * @param string $exp absolute file name
	 * @param string $in  absolute file name
	 */
	public function testCompare($exp, $in)
	{
		try {
			$stmts = $this->parser->parse(file_get_contents($in));
		} catch (Error $e) {
			throw new FixtureException($in, $e);
		}
		$output = $this->printer->prettyPrintFile($stmts);

		Assert::same(file_get_contents($exp), $output, $in);
	}


	/**
	 * @return string[] absolute file paths
	 */
	public function getCases()
	{
		$data = [];
		foreach (glob(__DIR__ . '/cases/*.in') as $file) {
			$data[] = [substr($file, 0, -2) . 'exp', $file];
		}

		return $data;
	}

}


(new PrinterTest($container))->run();
