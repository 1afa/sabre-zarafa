<?php
/*
 * Copyright 2013 Bokxing IT, http://www.bokxing-it.nl
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * "Zarafa" is a registered trademark of Zarafa B.V.
 *
 * This software use SabreDAV, an open source software distributed
 * with New BSD License. Please see <http://code.google.com/p/sabredav/>
 * for more information about SabreDAV
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Project page: <http://github.com/bokxing-it/sabre-zarafa/>
 *
 */

// A shim class for logging.
// Try to get a log4php facility, but if that fails, ignore log messages.
// This makes the installation of log4php optional.
//
// This include statement will fail silently and without warning if the file
// is not available, which is what we want in this case:
@include_once 'log4php/Logger.php';

class Zarafa_Logger
{
	private $logger = FALSE;

	public function __construct ($classname)
	{
		// Check whether log4php was loaded:
		if (!class_exists('Logger')) {
			return;
		}
		Logger::configure('log4php.xml');
		$this->logger = Logger::getLogger($classname);
	}

	public function fatal ($msg) {
		if ($this->logger) $this->logger->fatal($msg);
	}

	public function error ($msg) {
		if ($this->logger) $this->logger->error($msg);
	}

	public function warn ($msg) {
		if ($this->logger) $this->logger->warn($msg);
	}

	public function info ($msg) {
		if ($this->logger) $this->logger->info($msg);
	}

	public function debug ($msg) {
		if ($this->logger) $this->logger->debug($msg);
	}

	public function trace ($msg) {
		if ($this->logger) $this->logger->trace($msg);
	}
}
