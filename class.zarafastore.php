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
require_once 'class.zarafafolder.php';

class Zarafa_Store
{
	private $bridge;
	public $entryid;
	public $handle;
	public $root;
	public $root_props;
	public $folders = array();
	public $is_unicode;
	private $logger;

	public function
	__construct (&$bridge, $entryid, $handle)
	{
		$this->bridge = $bridge;
		$this->entryid = $entryid;
		$this->handle = $handle;

		$this->logger = new Zarafa_Logger(__CLASS__);

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
		$folders = $this->subtree_walk(NULL, restrict_and(restrict_propstring(PR_CONTAINER_CLASS, 'IPF.Contact'), restrict_nonhidden()));

		$deleted = (isset($this->props[PR_IPM_WASTEBASKET_ENTRYID]))
			? $this->subtree_walk($this->props[PR_IPM_WASTEBASKET_ENTRYID], restrict_propstring(PR_CONTAINER_CLASS, 'IPF.Contact'))
			: array();

		$hidden = $this->subtree_walk(NULL, restrict_and(restrict_propstring(PR_CONTAINER_CLASS, 'IPF.Contact'), restrict_hidden()));

		$deleted = array_merge($deleted, $hidden);

		// Remove deleted folders from list:
		foreach ($deleted as $entryid => $name) {
			if (isset($folders[$entryid])) {
				unset($folders[$entryid]);
			}
		}
		foreach ($folders as $entryid => $name) {
			if (FALSE($folder = mapi_msgstore_openentry($this->handle, $entryid))) {
				continue;
			}
			$node = new Zarafa_Folder($this->bridge, $this, $folder, $entryid);

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
			: FALSE;
	}

	public function
	create_folder ($properties)
	{
		// For now, create new folders in the root only:
		if (FALSE($this->root)) {
			return FALSE;
		}
		$displayname = isset($properties['{DAV:}displayname']) ? $properties['{DAV:}displayname'] : '';
		$description = isset($properties['{' . Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description']) ? $properties['{' . Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description'] : '';

		// FIXME: does this even work? According to the docs, mapi_folder_createfolder() returns a boolean...
		if (FALSE($folder = mapi_folder_createfolder($this->root, $displayname, $description, MAPI_UNICODE | OPEN_IF_EXISTS, FOLDER_GENERIC))
		 || FALSE($this->bridge->save_properties($folder, array(PR_CONTAINER_CLASS => 'IPF.Contact')))) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return FALSE;
		}
		// FIXME add folder to internal cache?
		return TRUE;
	}

	public function
	delete_folder ($parentid, $entryid, $folder_handle)
	{
		if (!isset($this->folders[$entryid])) {
			return FALSE;
		}
		if (FALSE($parent_handle = mapi_msgstore_openentry($this->handle, $parentid))) {
			return FALSE;
		}
		// Delete folder content
		if (FALSE(mapi_folder_emptyfolder($folder_handle, DEL_ASSOCIATED))
		 || FALSE(mapi_folder_deletefolder($parent_handle, $entryid))) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return FALSE;
		}
		unset($this->folders[$entryid]);
		return TRUE;
	}

	private function
	subtree_walk ($subtree_id, $restriction)
	{
		$folders = array();

		if (FALSE($folder = mapi_msgstore_openentry($this->handle, $subtree_id))
		 || FALSE($hier = mapi_folder_gethierarchytable($folder, CONVENIENT_DEPTH | MAPI_DEFERRED_ERRORS))
		 || FALSE(mapi_table_restrict($hier, $restriction))) {
			$this->logger->debug(__FUNCTION__.': '.get_mapi_error_name());
			return $folders;
		}
		foreach (mapi_table_queryallrows($hier, array(PR_ENTRYID, PR_SUBFOLDERS, PR_DISPLAY_NAME)) as $row) {
			$folders[$row[PR_ENTRYID]] = $row[PR_DISPLAY_NAME];
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
		$this->is_unicode = FALSE;
		$supportmask = mapi_getprops($this->handle, array(PR_STORE_SUPPORT_MASK));
		if (isset($supportmask[PR_STORE_SUPPORT_MASK]) && ($supportmask[PR_STORE_SUPPORT_MASK] & STORE_UNICODE_OK)) {
			// Setlocale to UTF-8 in order to support properties containing Unicode characters:
			setlocale(LC_CTYPE, "en_US.UTF-8");
			$this->is_unicode = TRUE;
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
