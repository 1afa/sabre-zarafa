<?php
/*
 * Copyright 2011 - 2012 Guillaume Lapierre
 * Copyright 2012 - 2013 Bokxing IT, http://www.bokxing-it.nl
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

	// Configuration file for Zarafa SabreDAV interface

	// Location of the SabreDAV server.
	// see SabreDAV documentation: 
	//      http://code.google.com/p/sabredav/wiki/CardDAV#Get_clients_working
	define ('CARDDAV_ROOT_URI', '/sabre-zarafa');

	// Zarafa server location
	define ('ZARAFA_SERVER', 'file:///var/run/zarafa');
	
	// Authentication realm
	define ('SABRE_AUTH_REALM', 'Zarafa SabreDAV CardDav');
	
	// Product ID sent in vcards
	define ('VCARD_PRODUCT_ID', '-//SabreDav/ZarafaBackend/0.21');
	
	// Choose VCard version
	// Supported values:
	// - 2 : old 2.1 format
	// - 3 : 3.0 format - compatible with OS.X addressbook
	// - 4 : newer 4.0 format - compatible with emClient
	define('VCARD_VERSION', 3);
	
	// Pattern to generate the "name" of the contact in Zarafa
	// Only used when no SORT-AS or X-CN is provided
	// unless SAVE_AS_OVERRIDE_SORTAS is set to true
	//
	// If only a company name is given, use it
	//
	// Options available:
	// %d - display name (default up to 0.12)
	// %l - last name
	// %f - first name
	// %c - company name
	define ('SAVE_AS_PATTERN', '%d');
	define ('SAVE_AS_OVERRIDE_SORTAS', false);
	
	// Charset to convert data to.
	// iPhone does not support UTF8 nor windows contact
	// vcard 4 are supposed to be utf8 encoded according to RFC :(
	// This is a ICONV parameter so one can use //TRANSLIT if needed
	// Default empty: let producer decide! (vcard4 utf8, other: iso-8859-1)
	define ('VCARD_CHARSET', '');
		// utf-8
		// 'ISO-8859-1//TRANSLIT');
	
	// If set to true all the write operations will be refused
	define ('READ_ONLY', false);

	// Allow SabreDAV browser plugin
	define('SABRE_DAV_BROWSER_PLUGIN', true);
	
	// Some clients do not add a TYPE= attribute for telephone numbers
	// This parameters maps empty TYPE to a MAPI attribute
	// unmapped properties are not exported by sabre-zarafa to VCARDs
	// unless used as DEFAULT_TELEPHONE_NUMBER_PROPERTY.
	
	// Valid values are:
	//  home_telephone_number			(mapped)
	//	home2_telephone_number			(mapped)
	//	cellular_telephone_number		(mapped)
	//	office_telephone_number			(mapped)
	//	business2_telephone_number		(mapped)
	//	business_fax_number				(mapped)
	//	home_fax_number					(mapped)
	//	pager_telephone_number			(mapped)
	//	isdn_number						(mapped)
	//	company_telephone_number		(mapped)
	//	car_telephone_number			(mapped)
	//	assistant_telephone_number		(mapped)
	//	primary_telephone_number		(mapped)
	//	callback_telephone_number		* unmapped *
	//	other_telephone_number			* unmapped *
	//	primary_fax_number				* unmapped *
	//	radio_telephone_number			* unmapped *
	//	telex_telephone_number			* unmapped *
	//	ttytdd_telephone_number			* unmapped *
	define ('DEFAULT_TELEPHONE_NUMBER_PROPERTY', 'primary_telephone_number');
	
	// If set, missing information from VCard will be removed from existing contact
	// if present. This could be lead to information loss if CardDav client
	// does not handle correctly all information or does not send back some
	// information.
	// Information sent as X-PROPERTY are not deleted even if this setting
	// is set to true
	define ('CLEAR_MISSING_PROPERTIES', true);
	
	// If set to false, one cannot delete a collection
	// As SabreDav does not handle sub-folders, deleting "root" collection
	// would delete all folders and contacts which might be dangerous
	define ('ALLOW_DELETE_FOLDER', true);

	// If set to true, all shared address books from other
	// users are included under the own account. The list is taken
	// from set Zarafa Webapp setting. You will need to make sure use a
	// rename pattern the includes the provenance if there are name
	// conflicts with own address books
	define ('INCLUDE_SHARED_ADDRESSES', false);
	
	// When set to true SAVE_RAW_VCARD, the vCard will not
	// only be parsed but also saved as a custom property to the contact.
	// When such a vCard is attached to a contact this vCard is sent
	// back to the requesting client instead of being generated from
	// mapi properties. This should be a performance boost but it will
	// require some storage capacity.
	// This should improve compatibility with CardDav clients using fields
	// that do not map easily with zarafa (multiple IMPP in emClient for
	// example)
	define ('SAVE_RAW_VCARD', false);
	
	// How to "write" dates to VCard
	define ('DATE_PATTERN', 'Ymd');
	
	// VCard cache version. Change cache version to force generation of
	// vcards from contact properties
	define ('CACHE_VERSION', '2.' . VCARD_VERSION);
	
	// Set default timezone
	if (function_exists("date_default_timezone_set")) {
		date_default_timezone_set('Europe/Paris');
	}

	// Return ETags to the client. Normally, with reasonable clients like
	// OSX Contacts or SoGo Connector, you want this. However, according to
	// the SabreDAV documentation, there are certain clients (which?) that
	// become confused when, for a given ETag, we don't return
	// byte-for-byte the exact body that the client sent. So support for
	// this feature is kept optional:
	define('ETAG_ENABLE', TRUE);

	// Change the name of the folder as returned by Sabre-Zarafa.
	// Format options available:
	//  %d - display name (default up to 0.20)
	//  %p - provenance, either 'public' or 'private' or the other user's
	//       name if shared
	define('FOLDER_RENAME_PATTERN', '%d');
