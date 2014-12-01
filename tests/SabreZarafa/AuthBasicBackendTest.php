<?php

namespace SabreZarafa;

class AuthBasicBackendTest extends \PHPUnit_Framework_TestCase
{
	public function
	testConstruct ()
	{
		// Check if we can construct this object without syntax errors:
		$bridge = $this->getMock('\SabreZarafa\Bridge');

		$authBasicBackend = new AuthBasicBackend($bridge);

		$this->assertTrue(is_object($authBasicBackend));
	}
}
