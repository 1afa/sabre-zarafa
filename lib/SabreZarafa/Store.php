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

namespace SabreZarafa;

use \Sabre\CardDAV;

class Store
{
	private $bridge;
	public $entryid;
	public $handle;
	public $root;
	public $root_props;
	public $folders = array();
	public $is_unicode;
	private $logger;
	public $storetype = 'unknown';

	public function
	__construct (&$bridge, $entryid, $handle, $storetype)
	{
		$this->bridge = $bridge;
		$this->entryid = $entryid;
		$this->handle = $handle;
		$this->storetype = $storetype;

		$this->logger = \Logger::getLogger(__CLASS__);

		$this->root = mapi_msgstore_openentry($this->handle, NULL);
		$this->root_props = mapi_getprops($this->root, array(PR_IPM_CONTACT_ENTRYID));

		$this->is_unicode_store();
		$this->get_folders();
	}

	public function
	get_dav_folders ($principal_uri)
	{
		$ret = array();
		foreach ($this->folders as $folder) {
			$ret[] = $folder->folder_to_dav($principal_uri);
		}
		return $ret;
	}

	private function
	get_folders ()
	{
		$folders = $this->subtree_walk(NULL, Restrict::rAnd(Restrict::propstring(PR_CONTAINER_CLASS, 'IPF.Contact'), Restrict::nonhidden()));

		$deleted = (isset($this->props[PR_IPM_WASTEBASKET_ENTRYID]))
			? $this->subtree_walk($this->props[PR_IPM_WASTEBASKET_ENTRYID], Restrict::propstring(PR_CONTAINER_CLASS, 'IPF.Contact'))
			: array();

		$hidden = $this->subtree_walk(NULL, Restrict::rAnd(Restrict::propstring(PR_CONTAINER_CLASS, 'IPF.Contact'), Restrict::hidden()));

		$deleted = array_merge($deleted, $hidden);

		// Remove deleted folders from list:
		foreach ($deleted as $entryid => $dummy) {
			if (isset($folders[$entryid])) {
				unset($folders[$entryid]);
			}
		}
		foreach ($folders as $entryid => $dummy) {
			if (($folder = mapi_msgstore_openentry($this->handle, $entryid)) === false) {
				continue;
			}
			$node = new Folder($this->bridge, $this, $folder, $entryid);

			if ($node->is_empty()) {
				continue;
			}
			$this->folders[$entryid] = $node;
		}
	}

	public function
	get_propids_from_strings ($properties)
	{
		return getPropIdsFromStrings($this->handle, $properties);
	}

	public function
	getuser_by_name ($name)
	{
		return mapi_zarafa_getuser_by_name($this->handle, $name);
	}

	public function
	get_folder ($entryid)
	{
		return (isset($this->folders[$entryid]))
			? $this->folders[$entryid]
			: false;
	}

	public function
	create_folder ($properties)
	{
		// For now, create new folders in the root only:
		if ($this->root === false) {
			return false;
		}
		$displayname = isset($properties['{DAV:}displayname']) ? $properties['{DAV:}displayname'] : '';
		$description = isset($properties['{' . CardDAV\Plugin::NS_CARDDAV . '}addressbook-description']) ? $properties['{' . CardDAV\Plugin::NS_CARDDAV . '}addressbook-description'] : '';

		// FIXME: does this even work? According to the docs, mapi_folder_createfolder() returns a boolean...
		if (($folder = mapi_folder_createfolder($this->root, $displayname, $description, MAPI_UNICODE | OPEN_IF_EXISTS, FOLDER_GENERIC)) === false) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return false;
		}
		if (($this->bridge->save_properties($folder, array(PR_CONTAINER_CLASS => 'IPF.Contact'))) === false) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return false;
		}
		// FIXME add folder to internal cache?
		return true;
	}

	public function
	delete_folder ($parentid, $entryid, $folder_handle)
	{
		if (!isset($this->folders[$entryid])) {
			return false;
		}
		if (($parent_handle = mapi_msgstore_openentry($this->handle, $parentid)) === false) {
			return false;
		}
		// Delete folder content:
		if (mapi_folder_emptyfolder($folder_handle, DEL_ASSOCIATED) === false) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return false;
		}
		if (mapi_folder_deletefolder($parent_handle, $entryid) === false) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return false;
		}
		unset($this->folders[$entryid]);
		return true;
	}

	private function
	subtree_walk ($subtree_id, $restriction)
	{
		$folders = array();

		if (($folder = mapi_msgstore_openentry($this->handle, $subtree_id)) === false) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return $folders;
		}
		if (($hier = mapi_folder_gethierarchytable($folder, CONVENIENT_DEPTH | MAPI_DEFERRED_ERRORS)) === false) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return $folders;
		}
		if (mapi_table_restrict($hier, $restriction) === false) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return $folders;
		}
		foreach (mapi_table_queryallrows($hier, array(PR_ENTRYID, PR_SUBFOLDERS)) as $row) {
			$folders[$row[PR_ENTRYID]] = true;
			$folders = array_merge($folders, $this->subtree_walk($row[PR_ENTRYID], $restriction));
		}
		return $folders;
	}

	/**
	 * Check if store supports UTF-8 (zarafa 7+)
	 */
	public function
	is_unicode_store ()
	{
		$this->is_unicode = false;
		$supportmask = mapi_getprops($this->handle, array(PR_STORE_SUPPORT_MASK));
		if (isset($supportmask[PR_STORE_SUPPORT_MASK]) && ($supportmask[PR_STORE_SUPPORT_MASK] & STORE_UNICODE_OK)) {
			// Setlocale to UTF-8 in order to support properties containing Unicode characters:
			setlocale(LC_CTYPE, "en_US.UTF-8");
			$this->is_unicode = true;
		}
	}

	public function
	to_charset ($string)
	{
		// Zarafa 7 supports unicode chars, convert properties to utf-8 if it's another encoding:
		return ($this->is_unicode)
			? $string
			: utf8_encode($string);
	}
}
