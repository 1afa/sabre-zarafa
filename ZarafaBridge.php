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

	// Load config and common
	include (BASE_PATH . "config.inc.php");
	include (BASE_PATH . "version.inc.php");
	include (BASE_PATH . "common.inc.php");

	// PHP-MAPI
	require_once("mapi/mapi.util.php");
	require_once("mapi/mapicode.php");
	require_once("mapi/mapidefs.php");
	require_once("mapi/mapitags.php");
	require_once("mapi/mapiguid.php");
	
	// VObject to mapi properties
	require_once "vcard/IVCardParser.php";
	include_once "vcard/VCardParser.php";
	require_once "vcard/IVCardProducer.php";
	include_once "vcard/VCardProducer.php";

	require_once 'ZarafaLogger.php';
	require_once 'function.restrict.php';
	require_once 'class.zarafastore.php';
	require_once 'ZarafaWebaccessSettings.php';

/**
 * This is main class for Sabre backends
 */
 
class Zarafa_Bridge {

	protected $session = FALSE;
	protected $publicStore;
	protected $wastebasketId = FALSE;
	protected $extendedProperties = FALSE;
	protected $connectedUser = FALSE;
	private $webaccess_settings = FALSE;
	private $folders_private = array();
	private $folders_public = array();
	private $folders_other = array();
	private $stores_table = FALSE;
	private $stores_private = array();
	private $stores_public = array();
	private $stores_other = array();
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Stores a reference to Zarafa Auth Backend so as to get the session
		$this->logger = new Zarafa_Logger(__CLASS__);
	}
	
	/**
	 * Connect to Zarafa and do some init
	 * @param $user user login
	 * @param $password user password
	 */
	public function
	connect ($user, $password)
	{
		$this->logger->trace(__FUNCTION__."($user, <password>)");

		if (FALSE($this->session = mapi_logon_zarafa($user, $password, ZARAFA_SERVER))) {
			$this->logger->debug(__FUNCTION__.': connection failed: '.get_mapi_error_name());
			return FALSE;
		}
		$this->logger->debug(__FUNCTION__.': connected to zarafa server - init bridge');

		if (FALSE($this->stores_table = mapi_getmsgstorestable($this->session))) {
			$this->logger->fatal(__FUNCTION__.': could not get messagestore table');
			return FALSE;
		}
		if (FALSE($this->stores_get_private())) {
			$this->logger->warn(__FUNCTION__.': could not get private stores');
			return FALSE;
		}
		if (FALSE($this->stores_get_public())) {
			$this->logger->warn(__FUNCTION__.': could not get public stores');
			return FALSE;
		}
		if (INCLUDE_SHARED_ADDRESSES) {
			if (FALSE($this->stores_get_other())) {
				$this->logger->warn(__FUNCTION__.': could not get other stores');
				return FALSE;
			}
		}
		// Store username for principals
		$this->connectedUser = $user;

		return TRUE;
	}

	/**
	 * Get MAPI session 
	 * @return MAPI session
	 */
	public function getMapiSession() {
		$this->logger->trace(__FUNCTION__);
		return $this->session;
	}

	/**
	 * Get private store
	 * @return ZarafaStore|FALSE
	 */
	public function
	get_private_store()
	{
		// This function gets the *first* private store.
		// What happens when the user has more than one?
		$this->logger->trace(__FUNCTION__);
		return (isset($this->stores_private[0]))
			? $this->stores_private[0]
			: FALSE;
	}

	/**
	 * Get connected user login 
	 * @return connected user
	 */
	public function getConnectedUser() {
		$this->logger->trace(__FUNCTION__);
		return $this->connectedUser;
	}

	public function
	getExtendedProperties ()
	{
		$this->logger->trace(__FUNCTION__);

		if (FALSE($this->extendedProperties)) {
			$this->initProperties();
		}
		return $this->extendedProperties;
	}

	/**
	 * Get connected user email address
	 * @return email address
	 */
	public function
	getConnectedUserMailAddress ()
	{
		$this->logger->trace(__FUNCTION__);

		static $userinfo = FALSE;

		if (FALSE($userinfo)) {
			$userinfo = $this->stores_private[0]->getuser_by_name($this->connectedUser);
		}
		$this->logger->debug("User email address: {$userinfo['emailaddress']}");
		return $userinfo['emailaddress'];
	}
	
	private function
	stores_get_private ()
	{
		$this->logger->trace(__FUNCTION__);

		if (FALSE(tbl_restrict_propval($this->stores_table, PR_MDB_PROVIDER, ZARAFA_SERVICE_GUID, RELOP_EQ))) {
			return FALSE;
		}
		if (FALSE($stores = mapi_table_queryallrows($this->stores_table, array(PR_ENTRYID)))) {
			return FALSE;
		}
		foreach ($stores as $store) {
			if (FALSE($handle = mapi_openmsgstore($this->session, $store[PR_ENTRYID]))) {
				$this->logger->warn(__FUNCTION__.': failed to open private store');
				continue;
			}
			$this->stores_private[] = new Zarafa_Store($this, $store[PR_ENTRYID], $handle, 'private');
		}
		return TRUE;
	}

	private function
	stores_get_public ()
	{
		$this->logger->trace(__FUNCTION__);

		if (FALSE(tbl_restrict_propval($this->stores_table, PR_MDB_PROVIDER, ZARAFA_STORE_PUBLIC_GUID, RELOP_EQ))) {
			return FALSE;
		}
		if (FALSE($stores = mapi_table_queryallrows($this->stores_table, array(PR_ENTRYID)))) {
			return FALSE;
		}
		foreach ($stores as $store) {
			if (FALSE($handle = mapi_openmsgstore($this->session, $store[PR_ENTRYID]))) {
				$this->logger->warn(__FUNCTION__.': failed to open public store');
				continue;
			}
			$this->stores_public[] = new Zarafa_Store($this, $store[PR_ENTRYID], $handle, 'public');
		}
		return TRUE;
	}

	private function
	stores_get_other ()
	{
		$this->logger->trace(__FUNCTION__);

		if (FALSE($private_store = $this->get_private_store())) {
			$this->logger->warn(__FUNCTION__.': failed to get private store');
			return FALSE;
		}
		// Need the Webaccess settings to find the shared address books:
		if (FALSE($this->webaccess_settings)) {
			$this->webaccess_settings = new Zarafa_Webaccess_Settings($private_store->handle);
		}
		if (!is_array($other_users = $this->webaccess_settings->by_path('zarafa/v1/contexts/hierarchy/shared_stores'))) {
			return TRUE;
		}
		foreach ($other_users as $username => $folder) {
			if (!is_array($folder) || empty($folder)) {
				continue;
			}
			$user_entryid = mapi_msgstore_createentryid($private_store->handle, $username);

			if (FALSE($handle = mapi_openmsgstore($this->session, $user_entryid))) {
				$this->logger->warn(__FUNCTION__.': failed to open other store');
				continue;
			}
			$this->stores_other[] = new Zarafa_Store($this, $user_entryid, $handle, $username);
		}
		return TRUE;
	}

	public function
	get_folders_private ($principal_uri)
	{
		$this->logger->trace(__FUNCTION__."($principal_uri)");

		foreach ($this->stores_private as $store) {
			$this->folders_private = array_merge($this->folders_private, $store->get_dav_folders($principal_uri));
		}
		return $this->folders_private;
	}

	public function
	get_folders_public ($principal_uri)
	{
		$this->logger->trace(__FUNCTION__."($principal_uri)");

		foreach ($this->stores_public as $store) {
			$this->folders_public = array_merge($this->folders_public, $store->get_dav_folders($principal_uri));
		}
		return $this->folders_public;
	}

	public function
	get_folders_other ($principal_uri)
	{
		$this->logger->trace(__FUNCTION__."($principal_uri)");

		foreach ($this->stores_other as $store) {
			$this->folders_other = array_merge($this->folders_other, $store->get_dav_folders($principal_uri));
		}
		return $this->folders_other;
	}

	private function
	get_deletion_restriction ()
	{
		$this->logger->trace(__FUNCTION__);

		if (!$this->publicStore || !($trash_folder = mapi_msgstore_openentry($this->publicStore, $this->wastebasketId))) {
			return Array();
		}
		// Get all contact folders from "Deleted Items" folder:
		$trash_hier = mapi_folder_gethierarchytable($trash_folder, CONVENIENT_DEPTH);
		mapi_table_restrict($trash_hier, restrict_propstring(PR_CONTAINER_CLASS, 'IPF.Contact'));

		$restr = Array();
		if ($deleted_folders = mapi_table_queryallrows($trash_hier, array(PR_ENTRYID))) {
			foreach ($deleted_folders as $folder) {
				$restr[] = restrict_propval(PR_ENTRYID, $folder[PR_ENTRYID], RELOP_NE);
			}
		}
		return $restr;
	}

	private function
	restrict_table_contacts_nonhidden_nondeleted ($hierarchy_table)
	{
		$this->logger->trace(__FUNCTION__);

		// Restriction for only IPF.Contact folder items:
		$restr_contacts = restrict_propstring(PR_CONTAINER_CLASS, 'IPF.Contact');

		// Restriction for only nondeleted items:
		$restr_nondeleted = $this->get_deletion_restriction();

		// Combine these restrictions into one big compound restriction:
		if (count($restr_nondeleted) > 0) {
			mapi_table_restrict($hierarchy_table, restrict_and(restrict_and($restr_nondeleted, restrict_nonhidden()), $restr_contacts));
		}
		else {
			mapi_table_restrict($hierarchy_table, restrict_and(restrict_nonhidden(), $restr_contacts));
		}
	}

	/**
	 * Get properties from mapi
	 * @param $entryId
	 */
	public function
	getProperties ($entryId, $store)
	{
		$this->logger->trace(__FUNCTION__.'('.bin2hex($entryId).')');
		if (FALSE($mapiObject = mapi_msgstore_openentry($store, $entryId))) {
			return FALSE;
		}
		return mapi_getprops($mapiObject);
	}
	
	/**
	 * Convert an entryId to a human readable string
	 */
	public function entryIdToStr($entryId) {
		return bin2hex($entryId);
	}
	
	/**
	 * Convert a human readable string to an entryid
	 */
	public function strToEntryId($str) {
		// Check if $str is a valid Zarafa entryID. If not returns 0
		if (!preg_match('/^[0-9a-zA-Z]*$/', $str)) {
			return 0;
		} 
		
		return pack("H*", $str);
	}

	public function
	get_folder ($entryid)
	{
		foreach ($this->stores_private as $store) {
			if (!FALSE($folder = $store->get_folder($entryid))) {
				return $folder;
			}
		}
		foreach ($this->stores_public as $store) {
			if (!FALSE($folder = $store->get_folder($entryid))) {
				return $folder;
			}
		}
		foreach ($this->stores_other as $store) {
			if (!FALSE($folder = $store->get_folder($entryid))) {
				return $folder;
			}
		}
	}

	public function
	entryid_to_uri ($entryid)
	{
		// GUID format: 8-4-4-4-12
		// Take MD5sum to convert to 32 byte hash:
		$str = md5($entryid);

		// Split into chunks:
		$chunk1 = substr($str,  0,  8);
		$chunk2 = substr($str,  8,  4);
		$chunk3 = substr($str, 12,  4);
		$chunk4 = substr($str, 16,  4);
		$chunk5 = substr($str, 20, 12);

		return "$chunk1-$chunk2-$chunk3-$chunk4-$chunk5.vcf";
	}

	
	/**
	 * Convert vcard data to an array of MAPI properties
	 * @param $vcardData
	 * @return array
	 */
	public function vcardToMapiProperties ($vcardData)
	{
		$this->logger->trace(__FUNCTION__);
		$this->logger->debug("VCARD:\n" . $vcardData);

		$parser = new VCardParser($this);

		if (FALSE($properties = $parser->vObjectToProperties($vcardData))) {
			return FALSE;
		}
		$this->logger->debug("VCard properties:\n" . print_r($properties, TRUE));
		return $properties;
	}

	/**
	 * Retrieve vCard for a contact. If need be will "build" the vCard data
	 * @see RFC6350 http://tools.ietf.org/html/rfc6350
	 * @param $contactId contact EntryID
	 * @return VCard 4 UTF-8 encoded content
	 */
	public function
	getContactVCard ($contactProperties, $store)
	{
		$contactId = $contactProperties[PR_ENTRYID];

		$this->logger->trace(__FUNCTION__.'(' . bin2hex($contactId).')');

		if (FALSE($contact = mapi_msgstore_openentry($store, $contactId))) {
			$this->logger->fatal(__FUNCTION__.': cannot open contact: '.get_mapi_error_name());
			return FALSE;
		}
		if (FALSE($p = $this->getExtendedProperties())) {
			$this->logger->fatal('cannot get extended properties');
			return FALSE;
		}
		$this->logger->trace("PR_CARDDAV_RAW_DATA: " . PR_CARDDAV_RAW_DATA);
		$this->logger->trace("PR_CARDDAV_RAW_DATA_GENERATION_TIME: " . PR_CARDDAV_RAW_DATA_GENERATION_TIME);
		$this->logger->trace("PR_CARDDAV_RAW_DATA_VERSION: " . PR_CARDDAV_RAW_DATA_VERSION);
		$this->logger->debug("CACHE VERSION: " . CACHE_VERSION);
		
		// dump properties
		$dump = print_r($contactProperties, true);
		$this->logger->trace("Contact properties:\n$dump");
		
		if (SAVE_RAW_VCARD && isset($contactProperties[PR_CARDDAV_RAW_DATA])) {
			// Check if raw vCard is up-to-date
			$vcardGenerationTime = $contactProperties[PR_CARDDAV_RAW_DATA_GENERATION_TIME];
			$lastModifiedDate    = $contactProperties[$p['last_modification_time']];

			// Get cache version
			$vcardCacheVersion = isset($contactProperties[PR_CARDDAV_RAW_DATA_VERSION]) ? $contactProperties[PR_CARDDAV_RAW_DATA_VERSION] : 'NONE';
			$this->logger->trace("Saved vcard cache version: " . $vcardCacheVersion);

			if (($vcardGenerationTime >= $lastModifiedDate) && ($vcardCacheVersion == CACHE_VERSION)) {
				$this->logger->debug("Using saved vcard");
				return $contactProperties[PR_CARDDAV_RAW_DATA];
			} else {
				$this->logger->trace("Contact modified or new version of Sabre-Zarafa");
			}
		} else {
			if (SAVE_RAW_VCARD) {
				$this->logger->trace("No saved raw vcard");
			} else {
				$this->logger->trace("Generation of vcards forced by config");
			}
		}
		$producer = new VCardProducer($this, VCARD_VERSION);

		// Produce VCard object
		$this->logger->trace("Producing vcard from contact properties");

		if (FALSE($producer->propertiesToVObject($contact, $contactProperties))) {
			$this->logger->fatal('failed to convert MAPI contact to vCard object');
			return FALSE;
		}
		if (FALSE($vCardData = $producer->serialize())) {
			$this->logger->fatal('failed to serialize vCard');
			return FALSE;
		}
		$this->logger->debug("Produced VCard\n" . $vCardData);
		
		// Charset conversion?
		$targetCharset = (VCARD_CHARSET == '') ? $producer->getDefaultCharset() : VCARD_CHARSET;
		
		if ($targetCharset != 'utf-8') {
			$this->logger->trace("Converting from UTF-8 to $targetCharset");
			$vCardData = iconv("UTF-8", $targetCharset, $vCardData);
		}
		
		if (SAVE_RAW_VCARD) {
			$this->logger->trace("Saving vcard to contact properties");
			// Check if raw vCard is up-to-date
			$this->save_properties($contact, array(
					PR_CARDDAV_RAW_DATA => $vCardData,
					PR_CARDDAV_RAW_DATA_VERSION => CACHE_VERSION,
					PR_CARDDAV_RAW_DATA_GENERATION_TIME => time()
			));
		}
		return $vCardData;
	}
	
	/**
	 * Init properties to read contact data
	 */
	protected function initProperties() {
		$this->logger->trace(__FUNCTION__);
		
		$properties = array();
		$properties["subject"] = PR_SUBJECT;
		$properties["icon_index"] = PR_ICON_INDEX;
		$properties["message_class"] = PR_MESSAGE_CLASS;
		$properties["display_name"] = PR_DISPLAY_NAME;
		$properties["given_name"] = PR_GIVEN_NAME;
		$properties["middle_name"] = PR_MIDDLE_NAME;
		$properties["surname"] = PR_SURNAME;
		$properties["home_telephone_number"] = PR_HOME_TELEPHONE_NUMBER;
		$properties["cellular_telephone_number"] = PR_CELLULAR_TELEPHONE_NUMBER;
		$properties["mobile_telephone_number"] = PR_MOBILE_TELEPHONE_NUMBER;
		$properties["office_telephone_number"] = PR_OFFICE_TELEPHONE_NUMBER;
		$properties["business_fax_number"] = PR_BUSINESS_FAX_NUMBER;
		$properties["company_name"] = PR_COMPANY_NAME;
		$properties["title"] = PR_TITLE;
		$properties["department_name"] = PR_DEPARTMENT_NAME;
		$properties["office_location"] = PR_OFFICE_LOCATION;
		$properties["profession"] = PR_PROFESSION;
		$properties["manager_name"] = PR_MANAGER_NAME;
		$properties["assistant"] = PR_ASSISTANT;
		$properties["nickname"] = PR_NICKNAME;
		$properties["display_name_prefix"] = PR_DISPLAY_NAME_PREFIX;
		$properties["spouse_name"] = PR_SPOUSE_NAME;
		$properties["generation"] = PR_GENERATION;
		$properties["birthday"] = PR_BIRTHDAY;
		$properties["wedding_anniversary"] = PR_WEDDING_ANNIVERSARY;
		$properties["sensitivity"] = PR_SENSITIVITY;
		$properties["fileas"] = "PT_STRING8:PSETID_Address:0x8005";
		$properties["fileas_selection"] = "PT_LONG:PSETID_Address:0x8006";
		$properties["email_address_1"] = "PT_STRING8:PSETID_Address:0x8083";
		$properties["email_address_display_name_1"] = "PT_STRING8:PSETID_Address:0x8080";
		$properties["email_address_display_name_email_1"] = "PT_STRING8:PSETID_Address:0x8084";
		$properties["email_address_type_1"] = "PT_STRING8:PSETID_Address:0x8082";
		$properties["email_address_2"] = "PT_STRING8:PSETID_Address:0x8093";
		$properties["email_address_display_name_2"] = "PT_STRING8:PSETID_Address:0x8090";
		$properties["email_address_display_name_email_2"] = "PT_STRING8:PSETID_Address:0x8094";
		$properties["email_address_type_2"] = "PT_STRING8:PSETID_Address:0x8092";
		$properties["email_address_3"] = "PT_STRING8:PSETID_Address:0x80a3";
		$properties["email_address_display_name_3"] = "PT_STRING8:PSETID_Address:0x80a0";
		$properties["email_address_display_name_email_3"] = "PT_STRING8:PSETID_Address:0x80a4";
		$properties["email_address_type_3"] = "PT_STRING8:PSETID_Address:0x80a2";
		$properties["home_address"] = "PT_STRING8:PSETID_Address:0x801a";
		$properties["business_address"] = "PT_STRING8:PSETID_Address:0x801b";
		$properties["other_address"] = "PT_STRING8:PSETID_Address:0x801c";
		$properties["mailing_address"] = "PT_LONG:PSETID_Address:0x8022";
		$properties["im"] = "PT_STRING8:PSETID_Address:0x8062";
		$properties["webpage"] = "PT_STRING8:PSETID_Address:0x802b";
		$properties["business_home_page"] = PR_BUSINESS_HOME_PAGE;
		$properties["email_address_entryid_1"] = "PT_BINARY:PSETID_Address:0x8085";
		$properties["email_address_entryid_2"] = "PT_BINARY:PSETID_Address:0x8095";
		$properties["email_address_entryid_3"] = "PT_BINARY:PSETID_Address:0x80a5";
		$properties["address_book_mv"] = "PT_MV_LONG:PSETID_Address:0x8028";
		$properties["address_book_long"] = "PT_LONG:PSETID_Address:0x8029";
		$properties["oneoff_members"] = "PT_MV_BINARY:PSETID_Address:0x8054";
		$properties["members"] = "PT_MV_BINARY:PSETID_Address:0x8055";
		$properties["private"] = "PT_BOOLEAN:PSETID_Common:0x8506";
		$properties["contacts"] = "PT_MV_STRING8:PSETID_Common:0x853a";
		$properties["contacts_string"] = "PT_STRING8:PSETID_Common:0x8586";
		$properties["categories"] = "PT_MV_STRING8:PS_PUBLIC_STRINGS:Keywords";
		$properties["last_modification_time"] = PR_LAST_MODIFICATION_TIME;

		// Detailed contacts properties
		// Properties for phone numbers
		$properties["assistant_telephone_number"] = PR_ASSISTANT_TELEPHONE_NUMBER;
		$properties["business2_telephone_number"] = PR_BUSINESS2_TELEPHONE_NUMBER;
		$properties["callback_telephone_number"] = PR_CALLBACK_TELEPHONE_NUMBER;
		$properties["car_telephone_number"] = PR_CAR_TELEPHONE_NUMBER;
		$properties["company_telephone_number"] = PR_COMPANY_MAIN_PHONE_NUMBER;
		$properties["home2_telephone_number"] = PR_HOME2_TELEPHONE_NUMBER;
		$properties["home_fax_number"] = PR_HOME_FAX_NUMBER;
		$properties["isdn_number"] = PR_ISDN_NUMBER;
		$properties["other_telephone_number"] = PR_OTHER_TELEPHONE_NUMBER;
		$properties["pager_telephone_number"] = PR_PAGER_TELEPHONE_NUMBER;
		$properties["primary_fax_number"] = PR_PRIMARY_FAX_NUMBER;
		$properties["primary_telephone_number"] = PR_PRIMARY_TELEPHONE_NUMBER;
		$properties["radio_telephone_number"] = PR_RADIO_TELEPHONE_NUMBER;
		$properties["telex_telephone_number"] = PR_TELEX_NUMBER;
		$properties["ttytdd_telephone_number"] = PR_TTYTDD_PHONE_NUMBER;
		// Additional fax properties
		$properties["fax_1_address_type"] = "PT_STRING8:PSETID_Address:0x80B2";
		$properties["fax_1_email_address"] = "PT_STRING8:PSETID_Address:0x80B3";
		$properties["fax_1_original_display_name"] = "PT_STRING8:PSETID_Address:0x80B4";
		$properties["fax_1_original_entryid"] = "PT_BINARY:PSETID_Address:0x80B5";
		$properties["fax_2_address_type"] = "PT_STRING8:PSETID_Address:0x80C2";
		$properties["fax_2_email_address"] = "PT_STRING8:PSETID_Address:0x80C3";
		$properties["fax_2_original_display_name"] = "PT_STRING8:PSETID_Address:0x80C4";
		$properties["fax_2_original_entryid"] = "PT_BINARY:PSETID_Address:0x80C5";
		$properties["fax_3_address_type"] = "PT_STRING8:PSETID_Address:0x80D2";
		$properties["fax_3_email_address"] = "PT_STRING8:PSETID_Address:0x80D3";
		$properties["fax_3_original_display_name"] = "PT_STRING8:PSETID_Address:0x80D4";
		$properties["fax_3_original_entryid"] = "PT_BINARY:PSETID_Address:0x80D5";

		// Properties for addresses
		// Home address
		$properties["home_address_street"] = PR_HOME_ADDRESS_STREET;
		$properties["home_address_city"] = PR_HOME_ADDRESS_CITY;
		$properties["home_address_state"] = PR_HOME_ADDRESS_STATE_OR_PROVINCE;
		$properties["home_address_postal_code"] = PR_HOME_ADDRESS_POSTAL_CODE;
		$properties["home_address_country"] = PR_HOME_ADDRESS_COUNTRY;
		// Other address
		$properties["other_address_street"] = PR_OTHER_ADDRESS_STREET;
		$properties["other_address_city"] = PR_OTHER_ADDRESS_CITY;
		$properties["other_address_state"] = PR_OTHER_ADDRESS_STATE_OR_PROVINCE;
		$properties["other_address_postal_code"] = PR_OTHER_ADDRESS_POSTAL_CODE;
		$properties["other_address_country"] = PR_OTHER_ADDRESS_COUNTRY;
		// Business address
		$properties["business_address_street"] = "PT_STRING8:PSETID_Address:0x8045";
		$properties["business_address_city"] = "PT_STRING8:PSETID_Address:0x8046";
		$properties["business_address_state"] = "PT_STRING8:PSETID_Address:0x8047";
		$properties["business_address_postal_code"] = "PT_STRING8:PSETID_Address:0x8048";
		$properties["business_address_country"] = "PT_STRING8:PSETID_Address:0x8049";
		// Mailing address
		$properties["country"] = PR_COUNTRY;
		$properties["city"] = PR_LOCALITY;
		$properties["postal_address"] = PR_POSTAL_ADDRESS;
		$properties["postal_code"] = PR_POSTAL_CODE;
		$properties["state"] = PR_STATE_OR_PROVINCE;
		$properties["street"] = PR_STREET_ADDRESS;
		// Special Date such as birthday n anniversary appoitment's entryid is store
		$properties["birthday_eventid"] = "PT_BINARY:PSETID_Address:0x804D";
		$properties["anniversary_eventid"] = "PT_BINARY:PSETID_Address:0x804E";

		$properties["notes"] = PR_BODY;
		
		// Has contact picture
		$properties["has_picture"] = "PT_BOOLEAN:{00062004-0000-0000-C000-000000000046}:0x8015";
		
		// Custom properties needed for carddav functionnality
		$properties["carddav_uri"] = PR_CARDDAV_URI;
		$properties["carddav_rawdata"] = PR_CARDDAV_RAW_DATA;
		$properties["carddav_generation_time"] = PR_CARDDAV_RAW_DATA_GENERATION_TIME;
		$properties["contact_count"] = PR_CARDDAV_AB_CONTACT_COUNT;
		$properties["carddav_version"] = PR_CARDDAV_RAW_DATA_VERSION;
		
		// Ask Mapi to load those properties and store mapping.
		$this->extendedProperties = $this->stores_private[0]->get_propids_from_strings($properties);
		
		// Dump properties to debug
		$dump = print_r ($this->extendedProperties, true);
		$this->logger->trace("Properties init done:\n$dump");
	}

	public function
	save_properties (&$handle, $properties)
	{
		if (FALSE(mapi_setprops($handle, $properties))) {
			$this->logger->fatal(__FUNCTION__.': MAPI error when applying mutations: '.get_mapi_error_name());
			return FALSE;
		}
		if (FALSE(mapi_savechanges($handle))) {
			$this->logger->fatal(__FUNCTION__.': MAPI error when saving changes to object: '.get_mapi_error_name());
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Generate a GUID using random numbers (version 4)
	 * GUID are 128 bits long numbers 
	 * returns string version {8-4-4-4-12}
	 * Use uuid_create if php5-uuid extension is available
	 */
	public function generateRandomGuid() {
		
		$this->logger->trace(__FUNCTION__);
		
		/*
		if (function_exists('uuid_create')) {
			// Not yet tested :)
			$this->logger->debug("Using uuid_create");
			uuid_create($context);
			uuid_make($context, UUID_MAKE_V4);
			uuid_export($context, UUID_FMT_STR, $uuid);
			return trim($uuid);
		}
		*/
		
		$data1a = mt_rand(0, 0xFFFF);		// 32 bits - splited
		$data1b = mt_rand(0, 0xFFFF);
		$data2  = mt_rand(0, 0xFFFF);		// 16 bits
		$data3  = mt_rand(0, 0xFFF);		// 12 bits (last 4 bits is version generator)
		
		// data4 is 64 bits long 
		$data4a = mt_rand(0, 0xFFFF);
		$data4b = mt_rand(0, 0xFFFF);
		$data4c = mt_rand(0, 0xFFFF);
		$data4d = mt_rand(0, 0xFFFF);

		// Force variant 4 + standard for this GUID
		$data4a = ($data4a | 0x8000) & 0xBFFF;	// standard
		
		return sprintf("%04x%04x-%04x-%03x4-%04x-%04x%04x%04x", $data1a, $data1b, $data2, $data3, $data4a, $data4b, $data4c, $data4d);
	}


	/**
	 * Assign a contact picture to a contact
	 * @param entryId contact entry id
	 * @param contactPicture must be a valid jpeg file. If contactPicture is NULL will remove contact picture from contact if exists
	 */
	public function setContactPicture(&$contact, $contactPicture)
	{
		$this->logger->trace(__FUNCTION__);

		$contactAttachment = FALSE;

		// Find if contact picture is already set:
		if (mapi_getprops($contact, array(PR_HASATTACH))) {
			$attachmentTable = mapi_message_getattachmenttable($contact);
			$attachments = mapi_table_queryallrows($attachmentTable, array(
				PR_ATTACH_NUM,
				PR_ATTACH_SIZE,
				PR_ATTACH_LONG_FILENAME,
				PR_ATTACH_FILENAME,
				PR_ATTACHMENT_HIDDEN,
				PR_DISPLAY_NAME,
				PR_ATTACH_METHOD,
				PR_ATTACH_CONTENT_ID,
				PR_ATTACH_MIME_TAG,
				PR_ATTACHMENT_CONTACTPHOTO,
				PR_EC_WA_ATTACHMENT_HIDDEN_OVERRIDE
			));
			foreach ($attachments as $attachmentRow) {
				if (isset($attachmentRow[PR_ATTACHMENT_CONTACTPHOTO])
				       && $attachmentRow[PR_ATTACHMENT_CONTACTPHOTO]) {
					$contactAttachment = $attachmentRow[PR_ATTACH_NUM];
					break;
				}
			}
		}
		// Remove existing attachment if necessary:
		if (!FALSE($contactAttachment)) {
			$this->logger->trace('removing existing contact picture');
			if (FALSE(mapi_message_deleteattach($contact, $contactAttachment))) {
				$this->logger->warn(__FUNCTION__.': could not delete attachment: '.get_mapi_error_name());
				// TODO: should we return with error?
			}
		}
		if ($contactPicture == NULL) {
			return TRUE;
		}
		$this->logger->debug('Saving contact picture as attachment');

		// Create attachment:
		if (FALSE($attach = mapi_message_createattach($contact))) {
			$this->logger->warn(__FUNCTION__.': could not create attachment: '.get_mapi_error_name());
			return FALSE;
		}
		// Update contact attachment properties:
		$properties = array(
			PR_ATTACH_SIZE => strlen($contactPicture),
			PR_ATTACH_LONG_FILENAME => 'ContactPicture.jpg',
			PR_ATTACHMENT_HIDDEN => false,
			PR_DISPLAY_NAME => 'ContactPicture.jpg',
			PR_ATTACH_METHOD => ATTACH_BY_VALUE,
			PR_ATTACH_MIME_TAG => 'image/jpeg',
			PR_ATTACHMENT_CONTACTPHOTO =>  true,
			PR_ATTACH_DATA_BIN => $contactPicture,
			PR_ATTACHMENT_FLAGS => 1,
			PR_ATTACH_EXTENSION_A => '.jpg',
			PR_ATTACH_NUM => 1
		);
		return $this->save_properties($attach, $properties);
	}
}
