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

require_once 'common.inc.php';
require_once 'ZarafaLogger.php';

// PHP-MAPI
require_once("mapi/mapi.util.php");
require_once("mapi/mapicode.php");
require_once("mapi/mapidefs.php");
require_once("mapi/mapitags.php");
require_once("mapi/mapiguid.php");
	
class Zarafa_CardDav_Backend extends Sabre\CardDAV\Backend\AbstractBackend
{
	protected $bridge;
	private $logger;

	public function __construct ($zarafaBridge)
	{
		// Stores a reference to Zarafa Auth Backend so as to get the session
		$this->bridge = $zarafaBridge;
		$this->logger = new Zarafa_Logger(__CLASS__);
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
	public function
	getAddressBooksForUser ($principalUri)
	{
		$this->logger->trace(__FUNCTION__."($principalUri)");

		$folders = array_merge(
			$this->bridge->get_folders_private($principalUri),
			$this->bridge->get_folders_public($principalUri),
			$this->bridge->get_folders_other($principalUri)
		);
		$dump = print_r($folders, true);
		$this->logger->debug("Address books:\n$dump");

		return $folders;
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
	public function
	updateAddressBook ($addressBookId, array $mutations)
	{
		$this->logger->trace(__FUNCTION__.'('.bin2hex($addressBookId).', (mutations))');

		if (READ_ONLY) {
			return $this->exc_forbidden(__FUNCTION__.': cannot update address book: permission denied by config (read-only)');
		}
		if (($folder = $this->bridge->get_folder($addressBookId)) === false) {
			return $this->exc_notfound(__FUNCTION__.': cannot find folder');
		}
		if ($folder->update_folder($mutations) === false) {
			$this->logger->fatal(__FUNCTION__.': cannot apply mutations');
			return false;
		}
		return true;
	}

	/**
	 * Creates a new address book
	 *
	 * @param string $principalUri
	 * @param string $url Just the 'basename' of the url.
	 * @param array $properties
	 * @return bool
	 */
	public function
	createAddressBook ($principalUri, $url, array $properties)
	{
		$this->logger->trace(__FUNCTION__."($principalUri, $url, (properties))");

		// FIXME: we don't actually do anything with the principal URI or the
		// url; we just create a folder in the root of the user's private store.
		if (READ_ONLY) {
			return $this->exc_forbidden(__FUNCTION__.': cannot create address book: permission denied by config (read-only)');
		}
		if (($store = $this->bridge->get_private_store()) === false) {
			return $this->exc_notfound(__FUNCTION__.': cannot find private store');
		}
		return $store->create_folder($properties);
	}

	/**
	 * Deletes an entire addressbook and all its contents
	 *
	 * @param mixed $addressBookId
	 * @return void
	 */
	public function
	deleteAddressBook ($addressBookId)
	{
		$this->logger->trace(__FUNCTION__.'('.bin2hex($addressBookId).')');
	
		if (READ_ONLY || !ALLOW_DELETE_FOLDER) {
			return $this->exc_forbidden(__FUNCTION__.': cannot delete address book: permission denied by config (read-only)');
		}
		if (($folder = $this->bridge->get_folder($addressBookId)) === false) {
			return $this->exc_notfound(__FUNCTION__.': cannot find folder');
		}
		return $folder->delete_folder();
	}

	/**
	 * Returns all cards for a specific addressbook id.
	 *
	 * This method should return the following properties for each card:
	 *   * carddata - raw vcard data
	 *   * uri - Some unique url
	 *   * lastmodified - A unix timestamp

	 * @param mixed $addressBookId
	 * @return array
	 */
	public function
	getCards ($addressBookId)
	{
		$this->logger->trace(__FUNCTION__.'('.bin2hex($addressBookId).')');
	
		if (($folder = $this->bridge->get_folder($addressBookId)) === false) {
			$this->exc_notfound(__FUNCTION__.': cannot find folder');
			return Array();
		}
		return $folder->get_dav_cards();
	}

	/**
	 * Returns a specfic card
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @return void
	 */
	public function
	getCard ($addressBookId, $uri)
	{
		$this->logger->trace(__FUNCTION__.'('.bin2hex($addressBookId).", $uri)");

		if (($folder = $this->bridge->get_folder($addressBookId)) === false) {
			$this->exc_notfound(__FUNCTION__.': cannot find folder');
			return Array();
		}
		return $folder->get_dav_card($uri);
	} 

	/**
	 * Creates a new card
	 *
	 * @param mixed $addressBookId
	 * @param string $uri
	 * @param string $data
	 * @return string|null
	 */
	public function
	createCard ($addressBookId, $uri, $data)
	{
		$this->logger->trace(__FUNCTION__." - $uri\n$data");

		if (READ_ONLY) {
			return $this->exc_forbidden(__FUNCTION__.': cannot create card: permission denied by config (read-only)');
		}
		if (($folder = $this->bridge->get_folder($addressBookId)) === false) {
			return $this->exc_notfound(__FUNCTION__.': cannot find folder');
		}
		if (($etag = $folder->create_contact($uri, $data)) === false) {
			$this->logger->fatal(__FUNCTION__.': could not create card');
			return false;
		}
		return (is_string($etag)) ? $etag : null;
	} 

	/**
	 * Updates a card
	 *
	 * @param mixed $addressBookId
	 * @param string $uri
	 * @param string $data
	 * @return string|null
	 */
	public function
	updateCard ($addressBookId, $uri, $data)
	{
		$this->logger->trace(__FUNCTION__." - $uri");

		if (READ_ONLY) {
			return $this->exc_forbidden(__FUNCTION__.': cannot update card: permission denied by config (read-only)');
		}
		if (($folder = $this->bridge->get_folder($addressBookId)) === false) {
			return $this->exc_notfound(__FUNCTION__.': cannot find folder');
		}
		if (($etag = $folder->update_contact($uri, $data)) === false) {
			$this->logger->fatal(__FUNCTION__.': failed to update card');
			return false;
		}
		return (is_string($etag)) ? $etag : null;
	}

	/**
	 * Deletes a card
	 *
	 * @param mixed $addressBookId
	 * @param string $uri
	 * @return bool
	 */
	public function
	deleteCard ($addressBookId, $uri)
	{
		$this->logger->trace(__FUNCTION__." - $uri");

		if (READ_ONLY) {
			return $this->exc_forbidden(__FUNCTION__.': cannot delete card: permission denied by config (read-only)');
		}
		if (($folder = $this->bridge->get_folder($addressBookId)) === false) {
			return $this->exc_notfound(__FUNCTION__.': cannot find folder');
		}
		if ($folder->delete_contact($uri) === false) {
			$this->logger->fatal(__FUNCTION__.': failed to delete card');
			return false;
		}
		return true;
	}

	private function
	exc_forbidden ($msg)
	{
		$this->logger->fatal($msg);
		throw new Sabre\DAV\Exception\Forbidden($msg);
		return false;
	}

	private function
	exc_notfound ($msg)
	{
		$this->logger->fatal($msg);
		throw new Sabre\DAV\Exception\NotFound($msg);
		return false;
	}
}
