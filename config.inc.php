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

	// Configuration file for Zarafa SabreDAV interface
	define ('ZARAFA_SERVER', 'file:///var/run/zarafa');
	define ('SABRE_AUTH_REALM', 'Zarafa SabreDAV CardDav');
	define ('CARDDAV_ROOT_URI', '/sabre-zarafa');
	define ('ZARAFA_DOMAINNAME', 'zeguigui.com');
	define ('VCARD_PRODUCT_ID', '-//SabreDav/ZarafaBackend/0.1');
	
	// Charset to convert data to.
	// iPhone does not support UTF8 nor windows contact
	// vcard 4 are supposed to be utf8 encoded according to RFC :(
	// This is a ICONV parameter so one can use //TRANSLIT if needed
	// Note that sabre zarafa has a bug handling UTF8 vcard data so you
	// should consider setting this to something not UTF8
	define ('VCARD_CHARSET', 'utf8');
		// 'ISO-8859-1//TRANSLIT');
	
	// If set to true all the write operations will be refused
	define ('READ_ONLY', false);

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
	
	// Set default timezone
	date_default_timezone_set('Europe/Paris');
	
?>