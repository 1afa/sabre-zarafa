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

require_once 'ZarafaLogger.php';

class Zarafa_Folder
{
	private $name = false;
	private $ctag = false;
	private $props = false;
	private $store = false;
	private $cards = false;
	private $bridge = false;
	public $handle = false;
	private $entryid = false;
	private $rowcount = false;
	private $contacts = false;
	private $contacts_table = false;
	private $uri_mapping = false;
	private $logger;

	public function
	__construct (&$bridge, &$store, $handle, $entryid)
	{
		$this->bridge = $bridge;
		$this->store = $store;
		$this->handle = $handle;
		$this->entryid = $entryid;
		$this->logger = new Zarafa_Logger(__CLASS__);

		// This config setting was introduced in Sabre-Zarafa 0.19 and defaults to true;
		// make sure it's defined for people who migrate from an old config:
		if (!defined('ETAG_ENABLE')) define('ETAG_ENABLE', true);
	}

	public function
	folder_to_dav ($principal_uri)
	{
		if (($props = $this->get_props()) === false) {
			return array();
		}
		$ret = array(
			'id' => $this->entryid,
			'uri' => $this->get_name(),
			'description' => (isset($props[PR_COMMENT]) ? $props[PR_COMMENT] : ''),
			'principaluri' => $principal_uri,
			'displayname' => $this->get_name(),
			'{' . Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description' => (isset($props[PR_COMMENT]) ? $props[PR_COMMENT] : ''),
			'{' . Sabre\CardDAV\Plugin::NS_CARDDAV . '}supported-address-data' => new Sabre\CardDAV\Property\SupportedAddressData()
		);
		if (($ctag = $this->get_ctag()) !== false) {
			$ret['ctag'] = $ctag;
			$ret['{http://calendarserver.org/ns/}getctag'] = $ctag;
		}
		return $ret;
	}

	private function
	contact_to_dav ($contact)
	{
		$uri = (isset($contact[PR_CARDDAV_URI]))
			? $contact[PR_CARDDAV_URI]
			: $this->bridge->entryid_to_uri($contact[PR_ENTRYID]);

		if (($carddata = $this->bridge->getContactVCard($contact, $this->store->handle)) === false) {
			return false;
		}
		$ret = array(
			'id' => $contact[PR_ENTRYID],
			'carddata' => $carddata,
			'uri' => $uri,
			'lastmodified' => $contact[PR_LAST_MODIFICATION_TIME]
		);
		if (ETAG_ENABLE) {
			$ret['etag'] = $this->make_etag($contact[PR_ENTRYID], $contact[PR_LAST_MODIFICATION_TIME]);
		}
		return $ret;
	}

	public function
	get_dav_cards ()
	{
		// Can we serve the cards from our own cache?
		if ($this->cards !== false) {
			return $this->cards;
		}
		// Otherwise do the actual lookup:
		if (($contacts = $this->get_contacts()) === false) {
			return array();
		}
		if ($this->uri_mapping === false) {
			$this->uri_mapping = array();
		}
		$this->cards = array();
		foreach ($contacts as $contact)
		{
			if (($dav = $this->contact_to_dav($contact)) === false) {
				continue;
			}
			$this->cards[$dav['uri']] = $dav;
			$this->uri_mapping[$dav['uri']] = $dav['id'];
		}
		return $this->cards;
	}

	public function
	get_dav_card ($uri)
	{
		// Can we serve the card from our own cache?
		if ($this->cards !== false && isset($this->cards[$uri])) {
			return $this->cards[$uri];
		}
		// Otherwise do the actual lookup:
		if (($entryid = $this->uri_to_entryid($uri)) === false)
		{
			// Do not log this at FATAL, ERROR or WARN levels even though it's
			// technically an error. Some clients, notably OSX' Contacts.app, check
			// whether the URI of a new contact is available by requesting that
			// card, and *expecting* the lookup to fail. So this "error" occurs
			// frequently during normal use. Don't needlessly pollute the logs:
			$this->logger->info(__FUNCTION__.': could not find contact');
			return false;
		}
		if (($contacts = $this->get_contacts($entryid)) === false) {
			return array();
		}
		if ($this->cards === false) {
			$this->cards = array();
		}
		foreach ($contacts as $contact) {
			if (($dav = $this->contact_to_dav($contact)) === false) {
				continue;
			}
			return $this->cards[$dav['uri']] = $dav;
		}
		$this->logger->fatal(__FUNCTION__.': could not find contact');
		return false;
	}

	public function
	update_folder (array $mutations)
	{
	//	// Debug information
	//	$dump = print_r($mutations, true);
	//	$this->logger->debug("Mutations:\n$dump");

		// What we know to change
		$authorizedMutations = array ('{DAV:}displayname', '{' . Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description');

		// Check the mutations
		foreach ($mutations as $m => $value) {
			if (!in_array($m, $authorizedMutations)) {
				$this->logger->warn("Unknown mutation: $m => $value");
				return false;
			}
		}
		// Do the mutations
	//	$this->logger->trace("applying mutations");
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
		if (isset($mutations['{' . Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description'])) {
			$description = $mutations['{' . Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description'];
			$mapiProperties[805568542] = $description;
		}
		if (count($mapiProperties) == 0) {
			$this->logger->info(__FUNCTION__.': no changes detected for folder');
			return false;
		}
		return $this->bridge->save_properties($this->handle, $mapiProperties);
	}

	public function
	delete_folder ()
	{
		if (($props = $this->get_props()) === false || !isset($props[PR_PARENT_ENTRYID])) {
			$this->logger->fatal(__FUNCTION__.': could not get parent ID');
			return false;
		}
		return $this->store->delete_folder($props[PR_PARENT_ENTRYID], $this->entryid, $this->handle);
	}

	public function
	create_contact ($uri, $data)
	{
		if (($contact = mapi_folder_createmessage($this->handle)) === false) {
			$this->logger->fatal(__FUNCTION__.': MAPI error: cannot create contact: '.get_mapi_error_name());
			return false;
		}
		$this->logger->trace(__FUNCTION__.': getting properties from vcard');
		if (($mapiProperties = $this->bridge->vcardToMapiProperties($data)) === false) {
			$this->logger->fatal(__FUNCTION__.': could not convert VCard properties to MAPI properties');
			return false;
		}
		$mapiProperties[PR_CARDDAV_URI] = $uri;

		if (SAVE_RAW_VCARD) {
			$mapiProperties[PR_CARDDAV_RAW_DATA] = $data;
			$mapiProperties[PR_CARDDAV_RAW_DATA_GENERATION_TIME] = time();
		}
		// Handle contact picture
		$contactPicture = NULL;
		if (isset($mapiProperties['ContactPicture'])) {
			$this->logger->debug(__FUNCTION__.': contact picture detected');
			$contactPicture = $mapiProperties['ContactPicture'];
			unset($mapiProperties['ContactPicture']);
			$this->bridge->setContactPicture($contact, $contactPicture);
		}
		// Do not set empty properties
		$this->logger->trace(__FUNCTION__.': removing empty properties');
		foreach ($mapiProperties as $p => $v) {
			if (empty($v)) {
				unset($mapiProperties[$p]);
			}
		}
		// Add missing properties for new contacts
		$this->logger->trace(__FUNCTION__.': adding missing properties for new contacts');
		$p = $this->bridge->getExtendedProperties();
		$mapiProperties[$p['icon_index']] = "512";
		$mapiProperties[$p['message_class']] = 'IPM.Contact';
		// message flags ?

		if ($this->bridge->save_properties($contact, $mapiProperties) === false) {
			return false;
		}
		if (!ETAG_ENABLE) {
			return true;
		}
		if (($p = mapi_getprops($contact)) === false) {
			return true;
		}
		if (!(isset($p[PR_ENTRYID]) && isset($p[PR_LAST_MODIFICATION_TIME]))) {
			return true;
		}
		// Don't use the modification time from $mapiProperties, use the
		// reported value; it may have been changed by the server!
		return $this->make_etag($p[PR_ENTRYID], $p[PR_LAST_MODIFICATION_TIME]);
	}

	public function
	update_contact ($uri, $data)
	{
		if (($entryid = $this->uri_to_entryid($uri)) === false) {
			$this->logger->fatal(__FUNCTION__.': could not find contact');
			return false;
		}
		if (($contact = mapi_msgstore_openentry($this->store->handle, $entryid)) === false) {
			$this->logger->fatal(__FUNCTION__.': could not open contact object: '.get_mapi_error_name());
			return false;
		}
		if (($mapiProperties = $this->bridge->vcardToMapiProperties($data)) === false) {
			$this->logger->fatal(__FUNCTION__.': could not convert VCard properties to MAPI properties');
			return false;
		}
		if (SAVE_RAW_VCARD) {
			$mapiProperties[PR_CARDDAV_RAW_DATA] = $data;
			$mapiProperties[PR_CARDDAV_RAW_DATA_GENERATION_TIME] = time();
		}
		// Handle contact picture
		if (array_key_exists('ContactPicture', $mapiProperties)) {
			$this->logger->debug(__FUNCTION__.': updating contact picture');
			$contactPicture = $mapiProperties['ContactPicture'];
			unset($mapiProperties['ContactPicture']);
			$this->bridge->setContactPicture($contact, $contactPicture);
		}
		if (CLEAR_MISSING_PROPERTIES) {
		//	$this->logger->debug("Clearing missing properties");
			$nullProperties = array();
			foreach ($mapiProperties as $p => $v) {
				if ($v == NULL) {
					$nullProperties[] = $p;
					unset($mapiProperties[$p]);
				}
			}
		//	$dump = print_r ($nullProperties, true);
		//	$this->logger->trace("Removing properties\n$dump");
			if (mapi_deleteprops($contact, $nullProperties) === false) {
				$this->logger->fatal(__FUNCTION__.': could not remove properties in backend: '.get_mapi_error_name());
				return false;
			}
		}
		if ($this->bridge->save_properties($contact, $mapiProperties) === false) {
			return false;
		}
		if (!ETAG_ENABLE) {
			return true;
		}
		if (($p = mapi_getprops($contact)) === false) {
			return true;
		}
		if (!(isset($p[PR_ENTRYID]) && isset($p[PR_LAST_MODIFICATION_TIME]))) {
			return true;
		}
		return $this->make_etag($p[PR_ENTRYID], $p[PR_LAST_MODIFICATION_TIME]);
	}

	/**
	 * Deletes a contact
	 *
	 * @param string $cardUri
	 * @return bool
	 */
	public function
	delete_contact ($uri)
	{
		if (($entryid = $this->uri_to_entryid($uri)) === false) {
			$this->logger->fatal(__FUNCTION__.': could not find contact');
			return false;
		}
		if (mapi_folder_deletemessages($this->handle, array($entryid)) === false) {
			$this->logger->fatal(__FUNCTION__.': could not delete contact: '.get_mapi_error_name());
			return false;
		}
		return true;
	}

	public function
	is_empty ()
	{
		return ($this->get_rowcount() == 0);
	}

	public function
	get_props ()
	{
		return ($this->props === false)
			? $this->props = mapi_getprops($this->handle)
			: $this->props;
	}

	private function
	get_ctag ()
	{
		// For documentation on what a CTag is, see:
		//   https://trac.calendarserver.org/browser/CalendarServer/trunk/doc/Extensions/caldav-ctag.txt
		// In short, it's an unique opaque, per-folder token that changes whenever a child resource
		// is updated.
		// It seems logical to use the folder's modification time, but the problem is that that time
		// doesn't change when a child is edited in-place. So unfortunately, to get clients to update
		// properly after an edit, we must retrive all cards and check all their modification times:
		if ($this->ctag === false) {
			if ($this->contacts === false && $this->get_contacts() === false) {
				return false;
			}
			// Find latest modification time of child:
			$this->ctag = 0;
			foreach ($this->contacts as $contact) {
				if (!isset($contact[PR_LAST_MODIFICATION_TIME])) {
					continue;
				}
				if ($contact[PR_LAST_MODIFICATION_TIME] > $this->ctag) {
					$this->ctag = $contact[PR_LAST_MODIFICATION_TIME];
				}
			}
			// Just to be sure, check against folder modification time too:
			if (($props = $this->get_props()) !== false && isset($props[PR_LAST_MODIFICATION_TIME])) {
				if ($props[PR_LAST_MODIFICATION_TIME] > $this->ctag) {
					$this->ctag = $props[PR_LAST_MODIFICATION_TIME];
				}
			}
		}
		return $this->ctag;
	}

	private function
	get_name ()
	{
		if ($this->name === false) {
			if (($props = $this->get_props()) !== false && isset($props[PR_DISPLAY_NAME])) {
				$this->name = $props[PR_DISPLAY_NAME];
			}
		}
		// Do name substitution if pattern defined:
		if (!defined('FOLDER_RENAME_PATTERN')) {
			return $this->name;
		}
		$subst = array
			( '%d' => $this->name			// Display name
			, '%p' => $this->store->storetype	// Provenance ('private' or 'public')
			) ;

		return str_replace(array_keys($subst), array_values($subst), FOLDER_RENAME_PATTERN);
	}

	private function
	get_contacts_table ()
	{
		return ($this->contacts_table === false)
			? $this->contacts_table = mapi_folder_getcontentstable($this->handle)
			: $this->contacts_table;
	}

	private function
	get_rowcount ()
	{
		return ($this->rowcount === false)
			? $this->rowcount = ((($table = $this->get_contacts_table()) === false) ? false : mapi_table_getrowcount($table))
			: $this->rowcount;
	}

	private function
	get_contacts ($specific_id = false)
	{
		// Do we happen to have the data in our cache?
		if ($this->contacts !== false) {
			// If no specific ID requested, return everything:
			if ($specific_id === false) {
				return $this->contacts;
			}
			// Else return the specific contact if we have it:
			if (isset($this->contacts[$specific_id])) {
				return $this->contacts[$specific_id];
			}
		}
		if (($table = $this->get_contacts_table()) === false) {
			return false;
		}
		// If a specific ID was requested, enforce it with a table restriction:
		$restrict = ($specific_id === false)
			? restrict_propstring(PR_MESSAGE_CLASS, 'IPM.Contact')
			: restrict_and(restrict_propstring(PR_MESSAGE_CLASS, 'IPM.Contact'), restrict_propval(PR_ENTRYID, $specific_id, RELOP_EQ));

		if (mapi_table_restrict($table, $restrict) === false) {
			return false;
		}
		// Run the query, cache the result:
		if (($ret = mapi_table_queryallrows($table, array(PR_ENTRYID, PR_CARDDAV_URI, PR_LAST_MODIFICATION_TIME))) !== false && $specific_id === false) {
			$this->contacts = $ret;
		}
		tbl_restrict_none($table);
		return $ret;
	}

	private function
	get_uri_mapping ()
	{
		// Do we already have a cached URI mapping?
		if ($this->uri_mapping !== false) {
			return $this->uri_mapping;
		}
		// For all entries in this folder, generate a mapping from EntryID to URI:
		// If $this->contacts is already set, then swell; else do a cheap lookup:
		if (($contacts = $this->contacts) === false) {
			if (($table = $this->get_contacts_table()) === false) {
				return false;
			}
			mapi_table_restrict($table, restrict_propstring(PR_MESSAGE_CLASS, 'IPM.Contact'));
			$contacts = mapi_table_queryallrows($table, array(PR_ENTRYID, PR_CARDDAV_URI));
			tbl_restrict_none($table);
			if ($contacts === false) {
				return false;
			}
		}
		$this->uri_mapping = array();
		foreach ($contacts as $contact) {
			$entryid = $contact[PR_ENTRYID];
			$uri = (isset($contact[PR_CARDDAV_URI]))
				? $contact[PR_CARDDAV_URI]
				: $this->bridge->entryid_to_uri($entryid);

			$this->uri_mapping[$uri] = $entryid;
		}
		return true;
	}

	private function
	uri_to_entryid ($uri)
	{
		if ($this->uri_mapping === false && $this->get_uri_mapping() === false) {
			return false;
		}
		return (isset($this->uri_mapping[$uri]))
			? $this->uri_mapping[$uri]
			: false;
	}

	private function
	make_etag ($entryid, $timestamp)
	{
		// Return the etag based on the entryid and the timestamp.
		// This should satisfy the requirements of being unique between
		// cards and between revisions of the same card. Using these
		// two properties makes it relatively cheap to construct.
		return sprintf('"%s"', md5($entryid . $timestamp));
	}
}
