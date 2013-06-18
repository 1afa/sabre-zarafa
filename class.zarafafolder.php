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
	private $name = FALSE;
	private $ctag = FALSE;
	private $props = FALSE;
	private $store = FALSE;
	private $cards = FALSE;
	private $bridge = FALSE;
	public $handle = FALSE;
	private $entryid = FALSE;
	private $rowcount = FALSE;
	private $contacts = FALSE;
	private $contacts_table = FALSE;
	private $uri_mapping = FALSE;
	private $logger;

	public function
	__construct (&$bridge, &$store, $handle, $entryid)
	{
		$this->bridge = $bridge;
		$this->store = $store;
		$this->handle = $handle;
		$this->entryid = $entryid;
		$this->logger = new Zarafa_Logger(__CLASS__);

		// This config setting was introduced in Sabre-Zarafa 0.19 and defaults to TRUE;
		// make sure it's defined for people who migrate from an old config:
		if (!defined('ETAG_ENABLE')) define('ETAG_ENABLE', TRUE);
	}

	public function
	folder_to_dav ($principal_uri)
	{
		if (FALSE($props = $this->get_props())) {
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
		if (!FALSE($ctag = $this->get_ctag())) {
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

		if (FALSE($carddata = $this->bridge->getContactVCard($contact, $this->store->handle))) {
			return FALSE;
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
		if (!FALSE($this->cards)) {
			return $this->cards;
		}
		// Otherwise do the actual lookup:
		if (FALSE($contacts = $this->get_contacts())) {
			return array();
		}
		if (FALSE($this->uri_mapping)) {
			$this->uri_mapping = array();
		}
		$this->cards = array();
		foreach ($contacts as $contact)
		{
			if (FALSE($dav = $this->contact_to_dav($contact))) {
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
		if (!FALSE($this->cards) && isset($this->cards[$uri])) {
			return $this->cards[$uri];
		}
		// Otherwise do the actual lookup:
		if (FALSE($entryid = $this->uri_to_entryid($uri)))
		{
			// Do not log this at FATAL, ERROR or WARN levels even though it's
			// technically an error. Some clients, notably OSX' Contacts.app, check
			// whether the URI of a new contact is available by requesting that
			// card, and *expecting* the lookup to fail. So this "error" occurs
			// frequently during normal use. Don't needlessly pollute the logs:
			$this->logger->info(__FUNCTION__.': could not find contact');
			return FALSE;
		}
		if (FALSE($contacts = $this->get_contacts($entryid))) {
			return array();
		}
		if (FALSE($this->cards)) {
			$this->cards = array();
		}
		foreach ($contacts as $contact) {
			if (FALSE($dav = $this->contact_to_dav($contact))) {
				continue;
			}
			return $this->cards[$dav['uri']] = $dav;
		}
		$this->logger->fatal(__FUNCTION__.': could not find contact');
		return FALSE;
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
				return FALSE;
			}
		}
		// Do the mutations
	//	$this->logger->trace("applying mutations");
		$mapiProperties = array();

		// Display Name
		if (isset($mutations['{DAV:}displayname'])) {
			$displayName = $mutations['{DAV:}displayname'];
			if ($displayName == '') {
				return FALSE;
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
			return FALSE;
		}
		return $this->bridge->save_properties($this->handle, $mapiProperties);
	}

	public function
	delete_folder ()
	{
		if (FALSE($props = $this->get_props()) || !isset($props[PR_PARENT_ENTRYID])) {
			$this->logger->fatal(__FUNCTION__.': could not get parent ID');
			return FALSE;
		}
		return $this->store->delete_folder($props[PR_PARENT_ENTRYID], $this->entryid, $this->handle);
	}

	public function
	create_contact ($uri, $data)
	{
		if (FALSE($contact = mapi_folder_createmessage($this->handle))) {
			$this->logger->fatal(__FUNCTION__.': MAPI error: cannot create contact: '.get_mapi_error_name());
			return FALSE;
		}
		$this->logger->trace(__FUNCTION__.': getting properties from vcard');
		if (FALSE($mapiProperties = $this->bridge->vcardToMapiProperties($data))) {
			$this->logger->fatal(__FUNCTION__.': could not convert VCard properties to MAPI properties');
			return FALSE;
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

		if (FALSE($this->bridge->save_properties($contact, $mapiProperties))) {
			return FALSE;
		}
		if (!ETAG_ENABLE || FALSE($p = mapi_getprops($contact)) || !isset($p[PR_ENTRYID]) || !isset($p[PR_LAST_MODIFICATION_TIME])) {
			return TRUE;
		}
		// Don't use the modification time from $mapiProperties, use the
		// reported value; it may have been changed by the server!
		return $this->make_etag($p[PR_ENTRYID], $p[PR_LAST_MODIFICATION_TIME]);
	}

	public function
	update_contact ($uri, $data)
	{
		if (FALSE($entryid = $this->uri_to_entryid($uri))) {
			$this->logger->fatal(__FUNCTION__.': could not find contact');
			return FALSE;
		}
		if (FALSE($contact = mapi_msgstore_openentry($this->store->handle, $entryid))) {
			$this->logger->fatal(__FUNCTION__.': could not open contact object: '.get_mapi_error_name());
			return FALSE;
		}
		if (FALSE($mapiProperties = $this->bridge->vcardToMapiProperties($data))) {
			$this->logger->fatal(__FUNCTION__.': could not convert VCard properties to MAPI properties');
			return FALSE;
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
			if (FALSE(mapi_deleteprops($contact, $nullProperties))) {
				$this->logger->fatal(__FUNCTION__.': could not remove properties in backend: '.get_mapi_error_name());
				return FALSE;
			}
		}
		if (FALSE($this->bridge->save_properties($contact, $mapiProperties))) {
			return FALSE;
		}
		if (!ETAG_ENABLE || FALSE($p = mapi_getprops($contact)) || !isset($p[PR_ENTRYID]) || !isset($p[PR_LAST_MODIFICATION_TIME])) {
			return TRUE;
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
		if (FALSE($entryid = $this->uri_to_entryid($uri))) {
			$this->logger->fatal(__FUNCTION__.': could not find contact');
			return FALSE;
		}
		if (FALSE(mapi_folder_deletemessages($this->handle, array($entryid)))) {
			$this->logger->fatal(__FUNCTION__.': could not delete contact: '.get_mapi_error_name());
			return FALSE;
		}
		return TRUE;
	}

	public function
	is_empty ()
	{
		return ($this->get_rowcount() == 0);
	}

	public function
	get_props ()
	{
		return (FALSE($this->props))
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
		if (FALSE($this->ctag)) {
			if (FALSE($this->contacts) && FALSE($this->get_contacts())) {
				return FALSE;
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
			if (!FALSE($props = $this->get_props()) && isset($props[PR_LAST_MODIFICATION_TIME])) {
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
		if (FALSE($this->name)) {
			if (!FALSE($props = $this->get_props()) && isset($props[PR_DISPLAY_NAME])) {
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
		return (FALSE($this->contacts_table))
			? $this->contacts_table = mapi_folder_getcontentstable($this->handle)
			: $this->contacts_table;
	}

	private function
	get_rowcount ()
	{
		return (FALSE($this->rowcount))
			? $this->rowcount = (FALSE($table = $this->get_contacts_table()) ? FALSE : mapi_table_getrowcount($table))
			: $this->rowcount;
	}

	private function
	get_contacts ($specific_id = FALSE)
	{
		// Do we happen to have the data in our cache?
		if (!FALSE($this->contacts)) {
			// If no specific ID requested, return everything:
			if (FALSE($specific_id)) {
				return $this->contacts;
			}
			// Else return the specific contact if we have it:
			if (isset($this->contacts[$specific_id])) {
				return $this->contacts[$specific_id];
			}
		}
		if (FALSE($table = $this->get_contacts_table())) {
			return FALSE;
		}
		// If a specific ID was requested, enforce it with a table restriction:
		$restrict = (FALSE($specific_id))
			? restrict_propstring(PR_MESSAGE_CLASS, 'IPM.Contact')
			: restrict_and(restrict_propstring(PR_MESSAGE_CLASS, 'IPM.Contact'), restrict_propval(PR_ENTRYID, $specific_id, RELOP_EQ));

		if (FALSE(mapi_table_restrict($table, $restrict))) {
			return FALSE;
		}
		// Run the query, cache the result:
		if (!FALSE($ret = mapi_table_queryallrows($table, array(PR_ENTRYID, PR_CARDDAV_URI, PR_LAST_MODIFICATION_TIME))) && FALSE($specific_id)) {
			$this->contacts = $ret;
		}
		tbl_restrict_none($table);
		return $ret;
	}

	private function
	get_uri_mapping ()
	{
		// Do we already have a cached URI mapping?
		if (!FALSE($this->uri_mapping)) {
			return $this->uri_mapping;
		}
		// For all entries in this folder, generate a mapping from EntryID to URI:
		// If $this->contacts is already set, then swell; else do a cheap lookup:
		if (FALSE($contacts = $this->contacts)) {
			if (FALSE($table = $this->get_contacts_table())) {
				return FALSE;
			}
			mapi_table_restrict($table, restrict_propstring(PR_MESSAGE_CLASS, 'IPM.Contact'));
			$contacts = mapi_table_queryallrows($table, array(PR_ENTRYID, PR_CARDDAV_URI));
			tbl_restrict_none($table);
			if (FALSE($contacts)) {
				return FALSE;
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
		return TRUE;
	}

	private function
	uri_to_entryid ($uri)
	{
		if (FALSE($this->uri_mapping) && FALSE($this->get_uri_mapping())) {
			return FALSE;
		}
		return (isset($this->uri_mapping[$uri]))
			? $this->uri_mapping[$uri]
			: FALSE;
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
