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
 * Project page: <http://code.google.com/p/sabre-zarafa/>
 * 
 */

	error_reporting(E_ALL);
	ini_set("display_errors", true);
	ini_set("html_errors", false);

	// Include Zarafa SabreDav Bridge
	include ("./ZarafaBridge.php");
	
	//Sabre DAV
	include("Sabre/autoload.php");

	// Custom classes to tie together SabreDav and Zarafa
	include "ZarafaAuthBackend.php";			// Authentification
	include "ZarafaCardDavBackend.php";			// CardDav
	include "ZarafaPrincipalsBackend.php";		// Principals
	
	function checkMapiError($msg) {
		if (mapi_last_hresult() != 0) {
			echo "Erreur lors de la requête $msg : " . get_mapi_error_name() . "\n";
			exit;
		}
	}

	//Mapping PHP errors to exceptions
	function exception_error_handler($errno, $errstr, $errfile, $errline ) {
		debug("PHP error $errno in $errfile:$errline : $errstr\n");
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	}
	set_error_handler("exception_error_handler");
	
	// Zarafa bridge
	$bridge = new Zarafa_Bridge();
	
	// Backends
	$authBackend      = new Zarafa_Auth_Basic_Backend($bridge);
	$principalBackend = new Zarafa_Principals_Backend($bridge);
	$carddavBackend   = new Zarafa_CardDav_Backend($bridge); 

	// Setting up the directory tree // 
	$nodes = array(
		new Sabre_DAVACL_PrincipalCollection($principalBackend),
		new Sabre_CardDAV_AddressBookRoot($principalBackend, $carddavBackend)
	);

	// The object tree needs in turn to be passed to the server class
	$server = new Sabre_DAV_Server($nodes);
	$server->setBaseUri(CARDDAV_ROOT_URI);

	// Plugins 
	$server->addPlugin(new Sabre_DAV_Auth_Plugin($authBackend, SABRE_AUTH_REALM));
	$server->addPlugin(new Sabre_DAV_Browser_Plugin());
	$server->addPlugin(new Sabre_CardDAV_Plugin());
	$server->addPlugin(new Sabre_DAVACL_Plugin());
	
	// Start server
	$server->exec();	
	
?>