<?php

use PhpParser\Lexer;
use PhpParser\Parser;


class KeepOriginalValueLexer extends Lexer\Emulative
{

	/**
	 * @see https://github.com/nikic/PHP-Parser/issues/26#issuecomment-6150035
	 * @param NULL $value
	 * @param NULL $startAttributes
	 * @param NULL $endAttributes
	 * @return int
	 */
	public function getNextToken(&$value = NULL, &$startAttributes = NULL, &$endAttributes = NULL)
	{
		$tokenId = parent::getNextToken($value, $startAttributes, $endAttributes);

		if ($tokenId == Parser::T_CONSTANT_ENCAPSED_STRING
			|| $tokenId == Parser::T_LNUMBER
			|| $tokenId == Parser::T_DNUMBER
		) {
			// could also use $startAttributes, doesn't really matter here
			$endAttributes['originalValue'] = $value;
		}

		return $tokenId;
	}

}
