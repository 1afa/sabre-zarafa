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

class Zarafa_Principals_Backend implements Sabre\DAVACL\PrincipalBackend\BackendInterface
{
	protected $bridge;
	protected $principals;
	private $logger;

	public function
	__construct ($zarafaBridge)
	{
		// Stores a reference to Zarafa Auth Backend so as to get the session
		$this->bridge = $zarafaBridge;
		$this->logger = new Zarafa_Logger(__CLASS__);
	}

	/**
	 * Returns a list of principals based on a prefix.
	 *
	 * This prefix will often contain something like 'principals'. You are
	 * only expected to return principals that are in this base path.
	 *
	 * You are expected to return at least a 'uri' for every user, you can
	 * return any additional properties if you wish so. Common properties
	 * are:
	 *   {DAV:}displayname
	 *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
	 *     field that's actualy injected in a number of other properties. If
	 *     you have an email address, use this property.
	 *
	 * @param string $prefixPath
	 * @return array
	 */
	public function
	getPrincipalsByPrefix ($prefix_path)
	{
		$this->logger->trace(__FUNCTION__."($prefix_path)");

		if ($prefix_path !== 'principals') {
			$this->logger->warn("Unknown prefix path: $prefix_path");
			return array();
		}
		if (FALSE($connected_user = $this->bridge->getConnectedUser())) {
			$this->logger->warn('Not connected');
			return array();
		}
		return array(
			array(
				'uri' => "principals/$connected_user",
				'{DAV:}displayname' => $connected_user,
				'{http://sabredav.org/ns}email-address' => $this->bridge->getConnectedUserMailAddress()
			)
		);
	}

	/**
	 * Returns a specific principal, specified by it's path. The returned
	 * structure should be the exact same as from getPrincipalsByPrefix.
	 *
	 * @param string $path
	 * @return array
	 */
	public function
	getPrincipalByPath ($path)
	{
		$this->logger->trace(__FUNCTION__."($path)");

		foreach ($this->getPrincipalsByPrefix('principals') as $principal) {
			if ($principal['uri'] === $path) {
				return $principal;
			}
		}
		return array();
	}

	/**
	 * Updates one ore more webdav properties on a principal.
	 *
	 * The list of mutations is supplied as an array. Each key in the array
	 * is a propertyname, such as {DAV:}displayname.
	 *
	 * Each value is the actual value to be updated. If a value is null, it
	 * must be deleted.
	 *
	 * This method should be atomic. It must either completely succeed, or
	 * completely fail. Success and failure can simply be returned as
	 * 'true' or 'false'.
	 *
	 * It is also possible to return detailed failure information. In that
	 * case an array such as this should be returned:
	 *
	 * array(
	 *   200 => array(
	 *      '{DAV:}prop1' => null,
	 *   ),
	 *   201 => array(
	 *      '{DAV:}prop2' => null,
	 *   ),
	 *   403 => array(
	 *      '{DAV:}prop3' => null,
	 *   ),
	 *   424 => array(
	 *      '{DAV:}prop4' => null,
	 *   ),
	 * );
	 *
	 * In this previous example prop1 was successfully updated or deleted,
	 * and prop2 was succesfully created.
	 *
	 * prop3 failed to update due to '403 Forbidden' and because of this
	 * prop4 also could not be updated with '424 Failed dependency'.
	 *
	 * This last example was actually incorrect. While 200 and 201 could
	 * appear in 1 response, if there's any error (403) the other
	 * properties should always fail with 423 (failed dependency).
	 *
	 * But anyway, if you don't want to scratch your head over this, just
	 * return true or false.
	 *
	 * @param string $path
	 * @param array $mutations
	 * @return FALSE
	 */
	public function
	updatePrincipal ($path, $mutations)
	{
		// Not implemented
		$this->logger->trace(__FUNCTION__."($path, (mutations))");
		return FALSE;
	}

	/**
	 * This method is used to search for principals matching a set of
	 * properties.
	 *
	 * This search is specifically used by RFC3744's
	 * principal-property-search REPORT. You should at least allow
	 * searching on http://sabredav.org/ns}email-address.
	 *
	 * The actual search should be a unicode-non-case-sensitive search. The
	 * keys in searchProperties are the WebDAV property names, while the
	 * values are the property values to search on.
	 *
	 * If multiple properties are being searched on, the search should be
	 * AND'ed.
	 *
	 * This method should simply return an array with full principal uri's.
	 *
	 * If somebody attempted to search on a property the backend does not
	 * support, you should simply return 0 results.
	 *
	 * You can also just return 0 results if you choose to not support
	 * searching at all, but keep in mind that this may stop certain
	 * features from working.
	 *
	 * @param string $prefixPath
	 * @param array $searchProperties
	 * @return array
	 */
	public function
	searchPrincipals ($prefixPath, array $searchProperties)
	{
		$this->logger->trace(__FUNCTION__."($prefixPath, (searchProperties))");
		// Not supported
		return array();
	}

	/**
	 * Returns the list of members for a group-principal
	 *
	 * @param string $principal
	 * @return array
	 */
	public function
	getGroupMemberSet ($principal)
	{
		$this->logger->trace(__FUNCTION__."($principal)");

		if (count($this->getPrincipalByPath($principal)) == 0) {
			throw new Sabre\DAV\Exception('Principal not found');
		}
		return array();
	}

	/**
	 * Returns the list of groups a principal is a member of
	 *
	 * @param string $principal
	 * @return array
	 */
	public function
	getGroupMembership ($principal)
	{
		$this->logger->trace(__FUNCTION__."($principal)");

		if (count($this->getPrincipalByPath($principal)) == 0) {
			throw new Sabre\DAV\Exception('Principal not found');
		}
		return array();
	}

	/**
	 * Updates the list of group members for a group principal.
	 *
	 * The principals should be passed as a list of uri's.
	 *
	 * @param string $principal
	 * @param array $members
	 * @return void
	 */
	public function
	setGroupMemberSet ($principal, array $members)
	{
		$this->logger->trace(__FUNCTION__."($principal, (members))");
	}
}
