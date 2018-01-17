<?php
/*
 * Copyright 2011 - 2012 Guillaume Lapierre
 * Copyright 2012 - 2014 Bokxing IT, http://www.bokxing-it.nl
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
 * Project page: <http://github.com/1afa/sabre-zarafa/>
 * 
 */

namespace SabreZarafa;

error_reporting(E_ALL);
ini_set('display_errors', false);
ini_set("html_errors", false);

// Include the config and version number file:
include __DIR__ . '/version.inc.php';
include __DIR__ . '/config.inc.php';

// Expand the include path with the standard location for the PHP-MAPI includes:
set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/kopano/php/');

// Include PHP-MAPI libraries:
include 'mapi/mapi.util.php';
include 'mapi/mapicode.php';
include 'mapi/mapidefs.php';
include 'mapi/mapitags.php';
include 'mapi/mapiguid.php';

// Include the Composer autoloader:
include __DIR__ . '/vendor/autoload.php';

// Load global logging config:
\Logger::configure(__DIR__ . '/log4php.xml');

// Create logger for this server:
$logger = \Logger::getLogger('server');
$logger->trace(sprintf('Initializing Sabre-Zarafa version %s, revision %s', SABRE_ZARAFA_VERSION, SABRE_ZARAFA_REV));

// Disable MAPI exceptions;
// we handle errors by checking a function's return status (at least for now):
// Makes MAPI.SO CRASH
// mapi_enable_exceptions(false);

function checkMapiError($msg) {
	global $logger;
	if (mapi_last_hresult() != 0) {
		$logger->warn("MAPI error $msg: " . get_mapi_error_name());
		exit;
	}
}

// Add some custom properties to store whatever I need
// Decided to start property IDs to 0xB600 which should not interfere with Zarafa (hope so)
define ('CARDDAV_CUSTOM_PROPERTY_ID', 0xB600);

define ('PR_CARDDAV_URI', 			mapi_prop_tag(PT_STRING8, CARDDAV_CUSTOM_PROPERTY_ID | 0x0000));
define ('PR_CARDDAV_RAW_DATA',			mapi_prop_tag(PT_STRING8, CARDDAV_CUSTOM_PROPERTY_ID | 0x0001));
define ('PR_CARDDAV_RAW_DATA_GENERATION_TIME',	mapi_prop_tag(PT_SYSTIME, CARDDAV_CUSTOM_PROPERTY_ID | 0x0002));
define ('PR_CARDDAV_AB_CONTACT_COUNT',		mapi_prop_tag(PT_LONG,    CARDDAV_CUSTOM_PROPERTY_ID | 0x0003));
define ('PR_CARDDAV_RAW_DATA_VERSION',		mapi_prop_tag(PT_STRING8, CARDDAV_CUSTOM_PROPERTY_ID | 0x0004));

// Zarafa bridge
$logger->trace("Init bridge");
$bridge = new Bridge();

// Backends
$logger->trace("Loading backends");
$authBackend      = new AuthBasicBackend($bridge);
$principalBackend = new PrincipalsBackend($bridge);
$carddavBackend   = new CardDavBackend($bridge);

// Setting up the directory tree
$nodes = array(
	new \Sabre\DAVACL\PrincipalCollection($principalBackend),
	new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend)
);

// The object tree needs in turn to be passed to the server class
$logger->trace("Starting server");
$server = new \Sabre\DAV\Server($nodes);
$server->setBaseUri(CARDDAV_ROOT_URI);

// Required plugins
$logger->trace("Adding plugins");
$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, SABRE_AUTH_REALM));
$server->addPlugin(new \Sabre\CardDAV\Plugin());
$server->addPlugin(new \Sabre\DAVACL\Plugin());

// Optional plugins
if (SABRE_DAV_BROWSER_PLUGIN) {
	// Do not allow POST
	$server->addPlugin(new \Sabre\DAV\Browser\Plugin(false));
}

// Start server
$logger->trace("Server exec");
$logger->info("SabreDAV version " . \Sabre\DAV\Version::VERSION);
$logger->info("Producer: " . VCARD_PRODUCT_ID );
$logger->info("Revision: " . (SABRE_ZARAFA_REV + 1) . ' - ' . SABRE_ZARAFA_DATE);
$server->exec();
