<?php

namespace SabreZarafa;

class ParserTest extends \PHPUnit_Framework_TestCase
{
	public function
	testConstruct ()
	{
		// Check if we can construct this object without getting syntax
		// errors:
		$parser = new VCard\Parser(null, false);

		$this->assertTrue(is_object($parser));
	}
}
