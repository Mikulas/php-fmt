<?php

namespace PhpFmtTests;


class FixtureException extends \Exception implements Exception
{

	/**
	 * @param string     $fileName
	 * @param \Exception $previous
	 */
	public function __construct($fileName, \Exception $previous)
	{
		parent::__construct("Failed to parser '$fileName'", NULL, $previous);
	}

}
