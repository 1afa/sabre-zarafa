<?php
/*
 * Copyright 2011 - 2012 Guillaume Lapierre
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

	include_once ("log4php/Logger.php");
	Logger::configure("log4php.xml");
 
	class Zarafa_Auth_Basic_Backend extends Sabre_DAV_Auth_Backend_AbstractBasic {
		
		protected $bridge;
		private $logger;
		
		public function __construct($zarafaBridge) {
			// Stores a reference to Zarafa Auth Backend so as to get the session
			$this->bridge = $zarafaBridge;
			$this->logger = Logger::getLogger(__CLASS__);
		}
		
		// Implements
		protected function validateUserPass($username, $password) {
			$this->logger->debug("validateUserPass($username," . md5($password) .  ")");
			$connect = $this->bridge->connect($username, $password);
			if (!$connect) {
				$this->logger->warn("Connection failed for $username");
			}
			return $connect;
		}
		
	}

?>
