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

	public function
	__construct (&$bridge, &$store, $handle, $entryid)
	{
		$this->bridge = $bridge;
		$this->store = $store;
		$this->handle = $handle;
		$this->entryid = $entryid;
	}

	public function
	folder_to_dav ($principal_uri)
	{
		if (FALSE($props = $this->get_props())) {
			return array();
		}
		return array(
			'id' => $this->entryid,
			'uri' => $this->get_name(),
			'ctag' => $this->get_ctag(),
			'description' => (isset($props[PR_COMMENT]) ? $props[PR_COMMENT] : ''),
			'principaluri' => $principal_uri,
			'displayname' => $this->get_name(),
			'{' . Sabre_CardDAV_Plugin::NS_CARDDAV . '}addressbook-description' => (isset($props[PR_COMMENT]) ? $props[PR_COMMENT] : ''),
			'{http://calendarserver.org/ns/}getctag' => $this->get_ctag(),
			'{' . Sabre_CardDAV_Plugin::NS_CARDDAV . '}supported-address-data' => new Sabre_CardDAV_Property_SupportedAddressData()
		);
	}

	private function
	contact_to_dav ($contact)
	{
		$uri = (isset($contact[PR_CARDDAV_URI]))
			? $contact[PR_CARDDAV_URI]
			: $this->bridge->entryid_to_uri($contact[PR_ENTRYID]);

		return array(
			'id' => $contact[PR_ENTRYID],
			'carddata' => $this->bridge->getContactVCard($contact, $this->store->handle),
			'uri' => $uri,
			'lastmodified' => $contact[PR_LAST_MODIFICATION_TIME]
		);
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
		if (FALSE($entryid = $this->uri_to_entryid($uri))) {
		//	$this->logger->fatal(__FUNCTION__.': cannot find card');
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
	//	$this->logger->fatal(__FUNCTION__.': cannot find card');
		return FALSE;
	}

	public function
	update_contact ($uri, $data)
	{
		if (FALSE($entryid = $this->uri_to_entryid($uri))) {
		//	$this->logger->fatal(__FUNCTION__.': cannot find contact');
			return FALSE;
		}
		if (FALSE($contact = mapi_msgstore_openentry($this->store->handle, $entryid))) {
		//	$this->logger->fatal(__FUNCTION__.': cannot open contact object');
			return FALSE;
		}
		$mapiProperties = $this->bridge->vcardToMapiProperties($data);

		if (SAVE_RAW_VCARD) {
		//	$this->logger->debug("Saving raw vcard");
			$mapiProperties[PR_CARDDAV_RAW_DATA] = $data;
			$mapiProperties[PR_CARDDAV_RAW_DATA_GENERATION_TIME] = time();
		}
		// Handle contact picture
		if (array_key_exists('ContactPicture', $mapiProperties)) {
		//	$this->logger->debug("Updating contact picture");
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
		//		$this->logger->warn(__FUNCTION__.': could not remove properties in backend');
				return FALSE;
			}
		}
		// Set properties
		$mapiProperties[PR_LAST_MODIFICATION_TIME] = time();

		if (FALSE(mapi_setprops($contact, $mapiProperties))) {
		//	$this->logger->warn(__FUNCTION__.': could not set properties in backend');
			return FALSE;
		}
		if (FALSE(mapi_savechanges($contact))) {
		//	$this->logger->warn(__FUNCTION__.': could not save changes to backend');
			return FALSE;
		}
		return TRUE;
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
			return FALSE;
		}
		if (FALSE(mapi_folder_deletemessages($this->handle, array($entryid)))) {
	//		$this->logger->fatal(__FUNCTION__.': could not delete contact');
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
		if (FALSE($this->ctag)) {
			if (!FALSE($props = $this->get_props()) && isset($props[PR_LAST_MODIFICATION_TIME])) {
				$this->ctag = $props[PR_LAST_MODIFICATION_TIME];
			}
			else if (!FALSE($this->contacts)) {
				$this->ctag = 0;
				foreach ($this->contacts as $contact) {
					if (!isset($contact[PR_LAST_MODIFICATION_TIME])) {
						continue;
					}
					if ($contact[PR_LAST_MODIFICATION_TIME] > $this->ctag) {
						$this->ctag = $contact[PR_LAST_MODIFICATION_TIME];
					}
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
		return $this->name;
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
}
