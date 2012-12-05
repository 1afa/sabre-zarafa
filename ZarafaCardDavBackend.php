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

require_once("common.inc.php");

// Logging
include_once ("log4php/Logger.php");
Logger::configure("log4php.xml");

// PHP-MAPI
require_once("mapi/mapi.util.php");
require_once("mapi/mapicode.php");
require_once("mapi/mapidefs.php");
require_once("mapi/mapitags.php");
require_once("mapi/mapiguid.php");
	
class Zarafa_CardDav_Backend extends Sabre_CardDAV_Backend_Abstract {
	
	protected $bridge;
	private $logger;
	
    public function __construct($zarafaBridge) {
		// Stores a reference to Zarafa Auth Backend so as to get the session
        $this->bridge = $zarafaBridge;
		$this->logger = Logger::getLogger(__CLASS__);		
    }
	
    /**
     * Returns the list of addressbooks for a specific user.
     *
     * Every addressbook should have the following properties:
     *   id - an arbitrary unique id
     *   uri - the 'basename' part of the url
     *   principaluri - Same as the passed parameter
     *
     * Any additional clark-notation property may be passed besides this. Some 
     * common ones are :
     *   {DAV:}displayname
     *   {urn:ietf:params:xml:ns:carddav}addressbook-description
     *   {http://calendarserver.org/ns/}getctag
     * 
     * @param string $principalUri 
     * @return array 
     */
    public function getAddressBooksForUser($principalUri) {
		
		$this->logger->info("getAddressBooksForUser($principalUri)");
		
		$adressBooks = array();
		
		$folders = $this->bridge->getAdressBooks();
		foreach ($folders as $entryId => $f) {
			$adressBooks[] = array(
				'id'  => $entryId,
				'uri' =>  $f['displayname'],
				'principaluri' => $principalUri,
				'{DAV:}displayname' => $f['displayname'],
				'{' . Sabre_CardDAV_Plugin::NS_CARDDAV . '}addressbook-description' => $f['description'],
				'{http://calendarserver.org/ns/}getctag' => $f['ctag'],
				'{' . Sabre_CardDAV_Plugin::NS_CARDDAV . '}supported-address-data' => 
                    new Sabre_CardDAV_Property_SupportedAddressData()
			);
		}

		$dump = print_r($adressBooks, true);
		$this->logger->debug("Address books:\n$dump");
		
		return $adressBooks;
	} 

    /**
     * Updates an addressbook's properties
     *
     * See Sabre_DAV_IProperties for a description of the mutations array, as 
     * well as the return value. 
     *
     * @param mixed $addressBookId
     * @param array $mutations
     * @see Sabre_DAV_IProperties::updateProperties
     * @return bool|array
     */
    public function updateAddressBook($addressBookId, array $mutations) {

		$this->logger->info("updateAddressBook(" . bin2hex($addressBookId). ")");
	
		if (READ_ONLY) {
			$this->logger->warn("Trying to update read-only address book");
			return false;
		}
		if (($store = $this->bridge->storeFromAddressBookId($addressBookId)) === FALSE) {
			$this->logger->warn(__FUNCTION__.": store not found!");
			return FALSE;
		}
		// Debug information
		$dump = print_r($mutations, true);
		$this->logger->debug("Mutations:\n$dump");
		
		// What we know to change
		$authorizedMutations = array ('{DAV:}displayname', '{' . Sabre_CardDAV_Plugin::NS_CARDDAV . '}addressbook-description');
		
		// Check the mutations
		foreach ($mutations as $m => $value) {
			if (!in_array($m, $authorizedMutations)) {
				$this->logger->warn("Unknown mutation: $m => $value");
				return false;
			}
		}
		
		// Do the mutations
		$this->logger->trace("applying mutations");
		$folder = mapi_msgstore_openentry($store, $addressBookId);
		
		if (mapi_last_hresult() > 0) {
			$this->logger->fatal("Error opening addressbook: " . get_mapi_error_name());
			return false;
		}
		
		$mapiProperties = array();
		
		// Display Name
		if (isset($mutations['{DAV:}displayname'])) {
			$displayName = $mutations['{DAV:}displayname'];
			if ($displayName == '') {
				return false;
			}
			$mapiProperties[PR_DISPLAY_NAME] = $displayName;
		}
		
		// Description
		if (isset($mutations['{' . Sabre_CardDAV_Plugin::NS_CARDDAV . '}addressbook-description'])) {
			$description = $mutations['{' . Sabre_CardDAV_Plugin::NS_CARDDAV . '}addressbook-description'];
			$mapiProperties[805568542] = $description;
		}
		
		// Apply changes
		if (count($mapiProperties) > 0) {
			mapi_setprops($folder, $mapiProperties);
			if (mapi_last_hresult() > 0) {
				$this->logger->fatal("Error applying mutations: " . get_mapi_error_name());
				return false;
			}

			mapi_savechanges($folder);
			if (mapi_last_hresult() > 0) {
				$this->logger->fatal("Error saving changes to addressbook: " . get_mapi_error_name());
				return false;
			}

			return true;
		}

		// No detected change
		$this->logger->info("No changes detected for addressbook");
		return false;
	}

    /**
     * Creates a new address book 
     *
     * @param string $principalUri 
     * @param string $url Just the 'basename' of the url. 
     * @param array $properties 
     * @return void
     */
    public function createAddressBook($principalUri, $url, array $properties) {
		$this->logger->info("createAddressBook($principalUri, $url)");
		
		if (READ_ONLY) {
			$this->logger->warn("Cannot create address book: read-only");
			return false;
		}
		
		$rootFolder = $this->bridge->getRootFolder();
		$displayName = isset($properties['{DAV:}displayname']) ? $properties['{DAV:}displayname'] : '';
		$description = isset($properties['{' . Sabre_CardDAV_Plugin::NS_CARDDAV . '}addressbook-description']) ? $properties['{' . Sabre_CardDAV_Plugin::NS_CARDDAV . '}addressbook-description'] : '';
		
		$subFolder = mapi_folder_createfolder($rootFolder, $displayName, $description, MAPI_UNICODE | OPEN_IF_EXISTS, FOLDER_GENERIC);
		mapi_setprops ($subFolder, array (907214878 => 'IPF.Contact'));
		mapi_savechanges($subFolder);

		if (mapi_last_hresult() > 0) {
			$this->logger->fatal("Error saving changes to addressbook: " . get_mapi_error_name());
			return false;
		}
	}

    /**
     * Deletes an entire addressbook and all its contents
     *
     * @param mixed $addressBookId 
     * @return void
     */
    public function deleteAddressBook($addressBookId) {

		$this->logger->info("deleteAddressBook(" . bin2hex($addressBookId) . ")");
	
		if (READ_ONLY || !ALLOW_DELETE_FOLDER) {
			$this->logger->warn("Cannot delete address book: permission denied by config");
			return false;
		}
		$folders = $this->bridge->getAdressBooks();
		if (($store = $this->bridge->storeFromAddressBookId($addressBookId)) === FALSE) {
			$this->logger->warn(__FUNCTION__.": store not found!");
			return FALSE;
		}
		$parentFolderId = $folders[$addressBookId]['parentId'];
		$folder         = mapi_msgstore_openentry($store, $addressBookId);
		$parentFolder   = mapi_msgstore_openentry($store, $parentFolderId);
		
		// Delete folder content
		mapi_folder_emptyfolder($folder, DEL_ASSOCIATED);
		mapi_folder_deletefolder($parentFolder, $addressBookId);

		if (mapi_last_hresult() > 0) {
			$this->logger->fatal("Error deleting addressbook: " . get_mapi_error_name());
		}
	}

    /**
     * Returns all cards for a specific addressbook id. 
     *
     * This method should return the following properties for each card:
     *   * carddata - raw vcard data
     *   * uri - Some unique url
     *   * lastmodified - A unix timestamp

     * @param mixed $addressbookId 
     * @return array 
     */
	public function
	getCards ($addressbookId)
	{
		$this->logger->info("getCards(" . bin2hex($addressbookId) . ")");
	
		$cards = array();
		do {
			if (($store = $this->bridge->storeFromAddressbookId($addressbookId)) === FALSE) {
				break;
			}
			if (($folder = mapi_msgstore_openentry($store, $addressbookId)) === FALSE) {
				break;
			}
			if (($contactsTable = mapi_folder_getcontentstable($folder)) === FALSE) {
				break;
			}
			if (($contacts = mapi_table_queryallrows($contactsTable, Array(PR_ENTRYID, PR_CARDDAV_URI, PR_LAST_MODIFICATION_TIME))) === FALSE) {
				break;
			}
			foreach ($contacts as $c)
			{
				// URI is based on PR_CARDDAV_URI or use ENTRYID
				if (isset($c[PR_CARDDAV_URI])) {
					$this->logger->debug("Using contact URI: " . $c[PR_CARDDAV_URI]);
					$uri = $c[PR_CARDDAV_URI];
				} else {
					// Create an URI from the EntryID:
					$uri = $this->bridge->guidFromEntryID($c[PR_ENTRYID]) . '.vcf';
					// Note: we do not write this change back to the database;
					// if this is a public contact created by someone else, we
					// do not have the permissions to write it.
				}
				$cards[] = array(
					'id' => $c[PR_ENTRYID],
		//			'carddata' => $this->bridge->getContactVCard($contactProperties[PR_ENTRYID]),
					'uri' => $uri,
					'lastmodified' => $c[PR_LAST_MODIFICATION_TIME]
				);
			}
			$dump = print_r ($cards, true);
			$this->logger->trace("Addressbook cards\n$dump");
			return $cards;
		}
		while (0);

		// If we're here, that's not good:
		$this->logger->warn("getCards failed early");
		return $cards;
	}

    /**
     * Returns a specfic card
     * 
     * @param mixed $addressBookId 
     * @param string $cardUri 
     * @return void
     */
    public function getCard($addressBookId, $cardUri) {

		// Init
		$this->logger->info("getCard(" . bin2hex($addressBookId) . ", $cardUri)");

		if (FALSE === ($store = $this->bridge->storeFromAddressBookId($addressBookId))) {
			return FALSE;
		}
		$folder = mapi_msgstore_openentry($store, $addressBookId);
		$entryId = $this->getContactEntryId($addressBookId, $cardUri);
		
		if ($entryId === 0) {
			$this->logger->warn("Contact not found!");
			return false;
		}
		
		$contactProperties = $this->bridge->getProperties($entryId, $store);
		$card = array(
			'id' => $contactProperties[PR_ENTRYID],
			'carddata' => $this->bridge->getContactVCard($contactProperties, $store),
			'uri' => $cardUri,
			'lastmodified' => $contactProperties[PR_LAST_MODIFICATION_TIME]
		);
		
		return $card;
	} 

    /**
     * Creates a new card
     * 
     * @param mixed $addressBookId 
     * @param string $cardUri 
     * @param string $cardData 
     * @return bool 
     */
    public function createCard($addressBookId, $cardUri, $cardData) {
		$this->logger->info("createCard - $cardUri\n$cardData");
		
		if (READ_ONLY) {
			$this->logger->warn("createCard failed: read-only");
			return false;
		}
		if (($store = $this->bridge->storeFromAddressBookId($addressBookId)) === FALSE) {
			return FALSE;
		}
		$folder = mapi_msgstore_openentry($store, $addressBookId);
		$contact = mapi_folder_createmessage($folder);
	
		if (mapi_last_hresult() != 0) {
			$this->logger->fatal("MAPI error - cannot create contact: " . get_mapi_error_name());
			return false;
		}
	
		$this->logger->trace("Getting properties from vcard");
		$mapiProperties = $this->bridge->vcardToMapiProperties($cardData);
		$mapiProperties[PR_CARDDAV_URI] = $cardUri;
		
		if (SAVE_RAW_VCARD) {
			// Save RAW vCard
			$this->logger->debug("Saving raw vcard");
			$mapiProperties[PR_CARDDAV_RAW_DATA] = $cardData;
			$mapiProperties[PR_CARDDAV_RAW_DATA_GENERATION_TIME] = time();
		}
		
		// Handle contact picture
		$contactPicture = NULL;
		if (isset($mapiProperties['ContactPicture'])) {
			$this->logger->debug("Contact picture detected");
			$contactPicture = $mapiProperties['ContactPicture'];
			unset($mapiProperties['ContactPicture']);
			$this->bridge->setContactPicture($contact, $contactPicture);
		}
		
		// Do not set empty properties
		$this->logger->trace("Removing empty properties");
		foreach ($mapiProperties as $p => $v) {
			if (empty($v)) {
				unset($mapiProperties[$p]);
			}
		}
		
		// Add missing properties for new contacts
		$this->logger->trace("Adding missing properties for new contacts");
		$p = $this->bridge->getExtendedProperties();
		$mapiProperties[$p["icon_index"]] = "512";
		$mapiProperties[$p["message_class"]] = 'IPM.Contact';
		$mapiProperties[PR_LAST_MODIFICATION_TIME] = time();
		// message flags ?
		
		mapi_setprops($contact, $mapiProperties);
		mapi_savechanges($contact);
		
		return mapi_last_hresult() == 0;
	} 

    /**
     * Updates a card
     * 
     * @param mixed $addressBookId 
     * @param string $cardUri 
     * @param string $cardData 
     * @return bool 
     */
    public function updateCard($addressBookId, $cardUri, $cardData) {
		$this->logger->info("updateCard - $cardUri");

		if (READ_ONLY) {
			$this->logger->warn("Cannot update card: read-only");
			return false;
		}
		
		// Update object properties
		$entryId = $this->getContactEntryId($addressBookId, $cardUri);
		
		if ($entryId === 0) {
			$this->logger->warn("Cannot find contact");
			return false;
		}
		if (($store = $this->bridge->storeFromAddressBookId($addressBookId)) === FALSE) {
			$this->logger->warn(__FUNCTION__.": store not found!");
			return FALSE;
		}
		$mapiProperties = $this->bridge->vcardToMapiProperties($cardData);
		$contact = mapi_msgstore_openentry($store, $entryId);
		
		if (SAVE_RAW_VCARD) {
			// Save RAW vCard
			$this->logger->debug("Saving raw vcard");
			$mapiProperties[PR_CARDDAV_RAW_DATA] = $cardData;
			$mapiProperties[PR_CARDDAV_RAW_DATA_GENERATION_TIME] = time();
		} else {
			$this->logger->trace("Saving raw vcard skiped by config");
		}

		// Handle contact picture
		if (array_key_exists('ContactPicture', $mapiProperties)) {
			$this->logger->debug("Updating contact picture");
			$contactPicture = $mapiProperties['ContactPicture'];
			unset($mapiProperties['ContactPicture']);
			$this->bridge->setContactPicture($contact, $contactPicture);
		}
		
		// Remove NULL properties
		if (CLEAR_MISSING_PROPERTIES) {
			$this->logger->debug("Clearing missing properties");
			$nullProperties = array();
			foreach ($mapiProperties as $p => $v) {
				if ($v == NULL) {
					$nullProperties[] = $p;
					unset($mapiProperties[$p]);
				}
			}
			$dump = print_r ($nullProperties, true);
			$this->logger->trace("Removing properties\n$dump");
			mapi_deleteprops($contact, $nullProperties);
		}
		
		// Set properties
		$mapiProperties[PR_LAST_MODIFICATION_TIME] = time();
		mapi_setprops ($contact, $mapiProperties);
		
		// Save changes to backend
		mapi_savechanges($contact);
		
		return mapi_last_hresult() == 0;
	}

    /**
     * Deletes a card
     * 
     * @param mixed $addressBookId 
     * @param string $cardUri 
     * @return bool 
     */
    public function deleteCard($addressBookId, $cardUri) {

		$this->logger->info("deleteCard($cardUri)");
	
		if (READ_ONLY) {
			$this->logger->warn("Cannot delete card: read-only");
			return false;
		}
		if (($store = $this->bridge->storeFromAddressBookId($addressBookId)) === FALSE) {
			$this->logger->warn(__FUNCTION__.": store not found!");
			return FALSE;
		}
		$folder = mapi_msgstore_openentry($store, $addressBookId);
		$entryId = $this->getContactEntryId($addressBookId, $cardUri);

		if ($entryId === 0) {
			$this->logger->warn("Contact not found!");
			return false;
		}

		// $folder = mapi_msgstore_openentry($this->bridge->getStore(), $addressBookId);
		mapi_folder_deletemessages($folder, array($entryId));

		if (mapi_last_hresult() > 0) {
			return false;
		}

		return true;
	}

	/**
	 * Translate ($addressBookId, $cardUri) to entry id
	 * @param $addressBookId address book to search contact in
	 * @param $cardUri name of contact card to retrieve
	 */
	protected function
	getContactEntryId ($addressBookId, $cardUri)
	{
		// Local cache:
		static $cache = Array();

		// Update object properties
		$this->logger->trace("getContactEntryId($cardUri)");

		// Can we satisfy this request from the cache?
		if (isset($cache[$addressBookId])
		 && isset($cache[$addressBookId][$cardUri])) {
		   return $cache[$addressBookId][$cardUri];
		}
		if (($store = $this->bridge->storeFromAddressBookId($addressBookId)) === FALSE) {
			$this->logger->warn(__FUNCTION__.": store not found!");
			return FALSE;
		}
		$folder = mapi_msgstore_openentry($store, $addressBookId);
		$contactsTable = mapi_folder_getcontentstable($folder);
		$rows = mapi_table_queryallrows($contactsTable, array(PR_ENTRYID, PR_CARDDAV_URI));
		$strip_extension = substr($cardUri, 0, -4);
		foreach ($rows as $row) {
			if ((isset($row[PR_CARDDAV_URI]) && $row[PR_CARDDAV_URI] == $cardUri)
			  || $this->bridge->guidFromEntryId($row[PR_ENTRYID]) == $strip_extension) {
				if (!isset($cache[$addressBookId])) {
					$cache[$addressBookId] = Array();
				}
				$cache[$addressBookId][$cardUri] = $row[PR_ENTRYID];
			}
		}
		return (isset($cache[$addressBookId][$cardUri])) ? $cache[$addressBookId][$cardUri] : 0;
	}
}

?>