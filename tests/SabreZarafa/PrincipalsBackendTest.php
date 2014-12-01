<?php

namespace SabreZarafa;

class PrincipalsBackendTest extends \PHPUnit_Framework_TestCase
{
	public function
	testConstruct ()
	{
		// Check if we can construct this object without syntax errors:
		$principalsBackend = new PrincipalsBackend(null, false);

		$this->assertTrue(is_object($principalsBackend));
	}
}
