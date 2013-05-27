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
	
interface IVCardProducer
{
	public function getDefaultCharset();

	/**
	 * Convert vObject to an array of properties
	 * @param ressource $contact
	 */
	public function propertiesToVObject($contact, $contactTableProps);

	public function serialize ();
}
