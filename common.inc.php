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

	// PHP-MAPI
	require_once("mapi/mapi.util.php");
	require_once("mapi/mapicode.php");
	require_once("mapi/mapidefs.php");
	require_once("mapi/mapitags.php");
	require_once("mapi/mapiguid.php");

	// Add some custom properties to store whatever I need
	// Decided to start property IDs to 0xB600 which should not interfere with Zarafa (hope so)
	define ('CARDDAV_CUSTOM_PROPERTY_ID', 0xB600);
	
	define ('PR_CARDDAV_URI', 						mapi_prop_tag(PT_STRING8, CARDDAV_CUSTOM_PROPERTY_ID | 0x0000));
	define ('PR_CARDDAV_RAW_DATA',					mapi_prop_tag(PT_STRING8, CARDDAV_CUSTOM_PROPERTY_ID | 0x0001));
	define ('PR_CARDDAV_RAW_DATA_GENERATION_TIME',	mapi_prop_tag(PT_SYSTIME, CARDDAV_CUSTOM_PROPERTY_ID | 0x0002));
	define ('PR_CARDDAV_AB_CONTACT_COUNT',			mapi_prop_tag(PT_LONG,    CARDDAV_CUSTOM_PROPERTY_ID | 0x0003));
	define ('PR_CARDDAV_RAW_DATA_VERSION',			mapi_prop_tag(PT_STRING8, CARDDAV_CUSTOM_PROPERTY_ID | 0x0004));

if (!function_exists('FALSE'))
{
	function FALSE ($expr)
	{
		return ($expr === FALSE);
	}
}
