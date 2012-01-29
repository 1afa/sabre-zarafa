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