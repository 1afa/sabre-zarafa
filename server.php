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

 	// Load configuration file
	define('BASE_PATH', dirname(__FILE__) . "/");

	// Change include path
	set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/share/php/" . PATH_SEPARATOR . BASE_PATH . "lib/");

	// Logging and error handling
	require_once 'ZarafaLogger.php';
	$logger = new Zarafa_Logger('server');

	error_reporting(E_ALL);
	ini_set('display_errors', false);
	ini_set("html_errors", false);

	// Include Zarafa SabreDav Bridge
	include ("./ZarafaBridge.php");
	
	// Disable MAPI exceptions;
	// we handle errors by checking a function's return status (at least for now):
	mapi_enable_exceptions(false);

	// SabreDAV
	include('lib/SabreDAV/vendor/autoload.php');

	// Custom classes to tie together SabreDav and Zarafa
	include "ZarafaAuthBackend.php";			// Authentification
	include "ZarafaCardDavBackend.php";			// CardDav
	include "ZarafaPrincipalsBackend.php";		// Principals

	function checkMapiError($msg) {
		global $logger;
		if (mapi_last_hresult() != 0) {
			$logger->warn("MAPI error $msg: " . get_mapi_error_name());
			exit;
		}
	}
	
	// Zarafa bridge
	$logger->trace("Init bridge");
	$bridge = new Zarafa_Bridge();
	
	// Backends
	$logger->trace("Loading backends");
	$authBackend      = new Zarafa_Auth_Basic_Backend($bridge);
	$principalBackend = new Zarafa_Principals_Backend($bridge);
	$carddavBackend   = new Zarafa_CardDav_Backend($bridge); 

	// Setting up the directory tree // 
	$nodes = array(
		new Sabre\DAVACL\PrincipalCollection($principalBackend),
		new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend)
	);

	// The object tree needs in turn to be passed to the server class
	$logger->trace("Starting server");
	$server = new Sabre\DAV\Server($nodes);
	$server->setBaseUri(CARDDAV_ROOT_URI);

	// Required plugins 
	$logger->trace("Adding plugins");
	$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend, SABRE_AUTH_REALM));
	$server->addPlugin(new Sabre\CardDAV\Plugin());
	$server->addPlugin(new Sabre\DAVACL\Plugin());

	// Optional plugins
	if (SABRE_DAV_BROWSER_PLUGIN) {
		// Do not allow POST
		$server->addPlugin(new Sabre\DAV\Browser\Plugin(false));
	}
	
	// Start server
	$logger->trace("Server exec");
	$logger->info("SabreDAV version " . Sabre\DAV\Version::VERSION . '-' . Sabre\DAV\Version::STABILITY);
	$logger->info("Producer: " . VCARD_PRODUCT_ID );
	$logger->info("Revision: " . (SABRE_ZARAFA_REV + 1) . ' - ' . SABRE_ZARAFA_DATE);
	$server->exec();
