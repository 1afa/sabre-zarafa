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
	public $handle = FALSE;
	private $entryid = FALSE;
	private $rowcount = FALSE;
	private $contacts = FALSE;
	private $contacts_table = FALSE;

	public function
	__construct (&$store, $handle, $entryid)
	{
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
		if (FALSE($this->contacts)) {
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
}
