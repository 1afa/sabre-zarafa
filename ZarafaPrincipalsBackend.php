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
 * Project page: <http://github.com/bokxing-it/sabre-zarafa/>
 * 
 */

require_once("common.inc.php");

// Logging
include_once ("log4php/Logger.php");
Logger::configure("log4php.xml");

class Zarafa_Principals_Backend implements Sabre_DAVACL_IPrincipalBackend {

	protected $bridge;
	protected $principals;
	private $logger;
	
    public function __construct($zarafaBridge) {
		// Stores a reference to Zarafa Auth Backend so as to get the session
        $this->bridge = $zarafaBridge;
		$this->logger = Logger::getLogger(__CLASS__);		
    }

    /**
     * Returns a list of principals based on a prefix.
     *
     * This prefix will often contain something like 'principals'. You are only 
     * expected to return principals that are in this base path.
     *
     * You are expected to return at least a 'uri' for every user, you can 
     * return any additional properties if you wish so. Common properties are:
     *   {DAV:}displayname 
     *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV 
     *     field that's actualy injected in a number of other properties. If
     *     you have an email address, use this property.
     * 
     * @param string $prefixPath 
     * @return array 
     */
    public function getPrincipalsByPrefix($prefixPath) {

		$this->logger->info("getPrincipalsByPrefix($prefixPath)");
	
		// Get connectedUser
		$connectedUser = $this->bridge->getConnectedUser();
		if ($connectedUser == '') {
			// Not connected
			$this->logger->warn("Not connected!");
			return array();
		}
		
		// Build principals URIs
		$uris = array(
			"principals/$connectedUser"
		);
		
		$principals = array();
		
		foreach ($uris as $uri) {
			list($rowPrefix) = Sabre_DAV_URLUtil::splitPath($uri);
            if ($rowPrefix !== $prefixPath) continue;
			$principals[] = array(
				'uri' => $uri,
				'{DAV:}displayname' => $connectedUser,
				'{http://sabredav.org/ns}email-address' => $this->bridge->getConnectedUserMailAddress()
			);
		}
		
		return $principals;
		
    }

    /**
     * Returns a specific principal, specified by it's path.
     * The returned structure should be the exact same as from 
     * getPrincipalsByPrefix. 
     * 
     * @param string $path 
     * @return array 
     */
    public function getPrincipalByPath($path) {
		
		$this->logger->info("getPrincipalByPath($path)");
		
		// Get connectedUser
		$connectedUser = $this->bridge->getConnectedUser();
		if ($connectedUser == '') {
			// Not connected
			$this->logger->warn("getPrincipalsByPrefix - not connected");
			return array();
		}
		
		// Build principals URIs
		$uris = array(
			"principals/$connectedUser"
		);

		$principal = NULL;
		
		foreach ($uris as $uri) {
			if ($uri == $path) {
				$principal = array(
					'id'  => $connectedUser,
					'uri' => $uri,
					'{DAV:}displayname' => $connectedUser,
					'{http://sabredav.org/ns}email-address' => $this->bridge->getConnectedUserMailAddress()
				);
			}
		}
		
		return $principal;
    }
	
    /**
     * Updates one ore more webdav properties on a principal.
     *
     * The list of mutations is supplied as an array. Each key in the array is
     * a propertyname, such as {DAV:}displayname.
     *
     * Each value is the actual value to be updated. If a value is null, it
     * must be deleted.
     *
     * This method should be atomic. It must either completely succeed, or
     * completely fail. Success and failure can simply be returned as 'true' or
     * 'false'.
     *
     * It is also possible to return detailed failure information. In that case
     * an array such as this should be returned:
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
     * In this previous example prop1 was successfully updated or deleted, and
     * prop2 was succesfully created.
     *
     * prop3 failed to update due to '403 Forbidden' and because of this prop4
     * also could not be updated with '424 Failed dependency'.
     *
     * This last example was actually incorrect. While 200 and 201 could appear
     * in 1 response, if there's any error (403) the other properties should
     * always fail with 423 (failed dependency).
     *
     * But anyway, if you don't want to scratch your head over this, just
     * return true or false.
     *
     * @param string $path
     * @param array $mutations
     * @return array|bool
     */
    function updatePrincipal($path, $mutations) {
		// Not implemented
		$this->logger->info("updatePrincipal($path)");
		return false;
	}

    /**
     * This method is used to search for principals matching a set of
     * properties.
     *
     * This search is specifically used by RFC3744's principal-property-search
     * REPORT. You should at least allow searching on
     * http://sabredav.org/ns}email-address.
     *
     * The actual search should be a unicode-non-case-sensitive search. The
     * keys in searchProperties are the WebDAV property names, while the values
     * are the property values to search on.
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
     * searching at all, but keep in mind that this may stop certain features
     * from working.
     *
     * @param string $prefixPath
     * @param array $searchProperties
     * @return array
     */
    function searchPrincipals($prefixPath, array $searchProperties) {
		$this->logger->info("updatePrincipal($prefixPath)");
		// Not supported
		return array();
	}
	

    /**
     * Returns the list of members for a group-principal 
     * 
     * @param string $principal 
     * @return array 
     */
    public function getGroupMemberSet($principal) {

		$this->logger->info("getGroupMemberSet($principal)");
	
        $principal = $this->getPrincipalByPath($principal);
        if (!$principal) throw new Sabre_DAV_Exception('Principal not found');

		$result = array();
		return $result;
    }

    /**
     * Returns the list of groups a principal is a member of 
     * 
     * @param string $principal 
     * @return array 
     */
    public function getGroupMembership($principal) {

		$this->logger->info("getGroupMembership($principal)");

        $principal = $this->getPrincipalByPath($principal);
        if (!$principal) throw new Sabre_DAV_Exception('Principal not found');

		$result = array();
		return $result;
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
    public function setGroupMemberSet($principal, array $members) {
		$this->logger->info("setGroupMemberSet($principal)");
    }

}

?>