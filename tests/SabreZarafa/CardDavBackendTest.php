<?php

namespace SabreZarafa;

class CardDavBackendTest extends \PHPUnit_Framework_TestCase
{
	public function
	testConstruct ()
	{
		// Check if we can construct this object without getting errors:
		$cardDavBackend = new CardDavBackend(null, false);

		$this->assertTrue(is_object($cardDavBackend));
	}
}
