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

require_once "vcard/IVCardProducer.php";
require_once "config.inc.php";
	
// PHP-MAPI
require_once("mapi/mapi.util.php");
require_once("mapi/mapicode.php");
require_once("mapi/mapidefs.php");
require_once("mapi/mapitags.php");
require_once("mapi/mapiguid.php");
	
class VCardProducer implements IVCardProducer
{
	public $defaultCharset;
	protected $bridge;
	protected $version;
	protected $logger;
	protected $vcard = FALSE;
	protected $extendedProperties = FALSE;
	
	function __construct ($bridge, $version)
	{
		$this->bridge = $bridge;
		$this->version = $version;
		$this->defaultCharset = 'utf-8';
		$this->logger = new Zarafa_Logger(__CLASS__);

		$this->vcard = new Sabre\VObject\Component\VCard();
	}

	/**
	 * Decide charset for vcard
	 * conversion is done by the bridge, vobject is always UTF8 encoded
	 */
	public function getDefaultCharset() {
		$this->logger->debug("getDefaultCharset");
		$charset = 'ISO-8859-1//TRANSLIT';
		
		if ($this->version >= 3) {
			$charset = "utf-8";
		} 
		
		$this->logger->debug("Charset: $charset");
		
		return $charset;
	}

	public function serialize ()
	{
		return (FALSE($this->vcard)) ? FALSE : $this->vcard->serialize();
	}

	/**
	 * Convert vObject to an array of properties
	 * @param array $properties
	 * @return bool
	 */
	public function
	propertiesToVObject ($contact, $contactTableProps)
	{
		$this->logger->debug('Generating contact vCard from properties');

		if (FALSE($this->vcard)) {
			$this->logger->fatal('failed to create vCard object');
			return FALSE;
		}
		if (FALSE($p = $this->extendedProperties = $this->bridge->getExtendedProperties())) {
			$this->logger->fatal('cannot get extended properties');
			return FALSE;
		}
		if (FALSE($contactProperties = mapi_getprops($contact))) {
			$this->logger->fatal('cannot get properties for contact: '.get_mapi_error_name());
			return FALSE;
		}
		// $contactTableProps contains extra properties from the table,
		// such as PR_ENTRYID, PR_LAST_MODIFICATION_TIME and PR_CARDDAV_URI.
		// Mix these in with the contact properties:
		// (Cannot use array_merge() since it renumbers numeric indices):
		foreach ($contactTableProps as $key => $val) {
			if (!isset($contactProperties[$key])) {
				$contactProperties[$key] = $val;
			}
		}
		$this->logger->trace("Contact properties: \n" . print_r($contactProperties, TRUE));
		
		// Version check
		switch ($this->version) {
			case 2: $this->vcard->VERSION = '2.1'; break;
			case 3: $this->vcard->VERSION = '3.0'; break;
			case 4: $this->vcard->VERSION = '4.0'; break;
			default:
				$this->logger->fatal("Unrecognised VCard version: " . $this->version);
				return FALSE;
		}
		// Private contact ?
		if (isset($contactProperties[$p['private']]) && $contactProperties[$p['private']]) {
			$this->vcard->add('CLASS', 'PRIVATE');		// Not in VCARD 4.0 but keep it for compatibility
		}
		// Mandatory FN
		$this->setVCard('FN', $contactProperties, $p['display_name']);
		
		// Contact name and pro information
		// N property
		/*
		   Special note:  The structured property value corresponds, in
			  sequence, to the Family Names (also known as surnames), Given
			  Names, Additional Names, Honorific Prefixes, and Honorific
			  Suffixes.  The text components are separated by the SEMICOLON
			  character (U+003B).  Individual text components can include
			  multiple text values separated by the COMMA character (U+002C).
			  This property is based on the semantics of the X.520 individual
			  name attributes [CCITT.X520.1988].  The property SHOULD be present
			  in the vCard object when the name of the object the vCard
			  represents follows the X.520 model.

			  The SORT-AS parameter MAY be applied to this property.
		*/		
		
		$contactInfos = array();
		$contactInfos[] = isset($contactProperties[$p['surname']])             ? $contactProperties[$p['surname']] : '';
		$contactInfos[] = isset($contactProperties[$p['given_name']])          ? $contactProperties[$p['given_name']] : '';
		$contactInfos[] = isset($contactProperties[$p['middle_name']])         ? $contactProperties[$p['middle_name']] : '';
		$contactInfos[] = isset($contactProperties[$p['display_name_prefix']]) ? $contactProperties[$p['display_name_prefix']] : '';
		$contactInfos[] = isset($contactProperties[$p['generation']])          ? $contactProperties[$p['generation']] : '';

		if (strlen(implode('', $contactInfos)) > 0) {
			if ($this->version >= 4 && isset($contactProperties[$p['fileas']]) && !empty($contactProperties[$p['fileas']])) {
				$this->vcard->add('N', $contactInfos, array('SORT-AS' => $contactProperties[$p['fileas']]));
			}
			else {
				$this->vcard->add('N', $contactInfos);
			}
		}
		// Add ORG:<company>;<department>
		$orgdata = array();
		$orgdata[] = (isset($contactProperties[$p['company_name']])) ? $contactProperties[$p['company_name']] : '';
		$orgdata[] = (isset($contactProperties[$p['department_name']])) ? $contactProperties[$p['department_name']] : '';

		if (strlen(implode('', $orgdata)) > 0) {
			$this->vcard->add('ORG', $orgdata);
		}
		$map = array
			( 'fileas'          => 'SORT-STRING'	// FIXME: not available in vCard 4, should ignore
			, 'nickname'        => 'NICKNAME'
			, 'title'           => 'TITLE'
			, 'profession'      => 'ROLE'
			, 'office_location' => 'OFFICE'

			// older syntax - may be needed by some clients so keep it!
			, 'assistant'       => 'X-MS-ASSISTANT'
			, 'manager_name'    => 'X-MS-MANAGER'
			, 'spouse_name'     => 'X-MS-SPOUSE'
			);

		foreach ($map as $prop_mapi => $prop_vcard) {
			$this->setVCard($prop_vcard, $contactProperties, $p[$prop_mapi]);
		}

		// Convert 'im' to IMPP tags:
		$this->instantMessagingConvert($contactProperties);

		// Convert 'webpage' to URL tags:
		$this->websiteConvert($contactProperties);

		if ($this->version >= 4) {
			// Relation types 'assistant' and 'manager' are not RFC6350-compliant.
			if (isset($contactProperties[$p['assistant']])
			&& !empty($contactProperties[$p['assistant']])) {
				$this->vcard->add('RELATED', $contactProperties[$p['assistant']], array('VALUE' => 'text', 'TYPE' => 'assistant'));
			}
			if (isset($contactProperties[$p['manager_name']])
			&& !empty($contactProperties[$p['manager_name']])) {
				$this->vcard->add('RELATED', $contactProperties[$p['manager_name']], array('VALUE' => 'text', 'TYPE' => 'manager'));
			}
			if (isset($contactProperties[$p['spouse_name']])
			&& !empty($contactProperties[$p['spouse_name']])) {
				$this->vcard->add('RELATED', $contactProperties[$p['spouse_name']], array('VALUE' => 'text', 'TYPE' => 'spouse'));
			}
		}
		// Dates:
		if (isset($contactProperties[$p['birthday']])) {
			$this->vcard->add('BDAY', date(DATE_PATTERN, $contactProperties[$p['birthday']]));
		}
		if (isset($contactProperties[$p['wedding_anniversary']])) {
			if ($this->version >= 4) {
				$this->vcard->add('ANNIVERSARY', date(DATE_PATTERN, $contactProperties[$p['wedding_anniversary']]));
			}
			else {
				$this->vcard->add('X-ANNIVERSARY', date(DATE_PATTERN, $contactProperties[$p['wedding_anniversary']]));
			}
		}
		if (isset($contactProperties[$p['last_modification_time']])) {
			// This timestamp is always in ISO8601 form, no DATE_PATTERN here:
			$this->vcard->add('REV', date('c', $contactProperties[$p['last_modification_time']]));
		}
		// Telephone numbers
		// webaccess can handle 19 telephone numbers...
		$map = array
			( 'home_telephone_number'      => array('type' => array('HOME','VOICE'), 'pref' => '1')
			, 'home2_telephone_number'     => array('type' => array('HOME','VOICE'), 'pref' => '2')
			, 'office_telephone_number'    => array('type' => array('WORK','VOICE'), 'pref' => '1')
			, 'business2_telephone_number' => array('type' => array('WORK','VOICE'), 'pref' => '2')
			, 'business_fax_number'        => array('type' => array('WORK','FAX'))
			, 'home_fax_number'            => array('type' => array('HOME','FAX'))
			, 'mobile_telephone_number'    => array('type' => 'CELL')
			, 'pager_telephone_number'     => array('type' => 'PAGER')
			, 'isdn_number'                => array('type' => 'ISDN')
			, 'company_telephone_number'   => array('type' => 'WORK')
			, 'car_telephone_number'       => array('type' => 'CAR')
			, 'assistant_telephone_number' => array('type' => 'SECR')
			, 'other_telephone_number'     => array('type' => 'OTHER')
			, 'primary_telephone_number'   => array('type' => 'VOICE', 'pref' => '1')
			, 'primary_fax_number'         => array('type' => 'FAX', 'pref' => '1')
			, 'ttytdd_telephone_number'    => array('type' => 'TEXTPHONE')

			// Only Evolution has any support for the following:
			, 'radio_telephone_number'     => array('type' => 'X-EVOLUTION-RADIO')
			, 'telex_telephone_number'     => array('type' => 'X-EVOLUTION-TELEX')
			, 'callback_telephone_number'  => array('type' => 'X-EVOLUTION-CALLBACK')
			);

		// OSX Addressbook sends back VCards in this format:
		// TEL;type=WORK;type=VOICE:00334xxxxx
		foreach ($map as $prop_mapi => $prop_vcard) {
			if (!isset($contactProperties[$p[$prop_mapi]]) || $contactProperties[$p[$prop_mapi]] == '') {
				continue;
			}
			$this->vcard->add('TEL', $contactProperties[$p[$prop_mapi]], $prop_vcard);
		}
		// There are unmatched telephone numbers in zarafa, use them!
		$unmatchedProperties = array
			( 'callback_telephone_number'
			, 'radio_telephone_number'
			, 'telex_telephone_number'
			);
		if (in_array(DEFAULT_TELEPHONE_NUMBER_PROPERTY, $unmatchedProperties)) {
			// unmatched found a match!
			$this->setVCard('TEL', $contactProperties, $p[DEFAULT_TELEPHONE_NUMBER_PROPERTY]);
		}
		$this->setVCardAddress('HOME',  $contactProperties, 'home');
		$this->setVCardAddress('WORK',  $contactProperties, 'business');
		$this->setVCardAddress('OTHER', $contactProperties, 'other');
		
		// emails
		for ($i = 1; $i <= 3; $i++) {
			if (!isset($contactProperties[$p["email_address_$i"]])) {
				continue;
			}
			// Get display name:
			$dn = isset($contactProperties[$p["email_address_display_name_$i"]])
			          ? $contactProperties[$p["email_address_display_name_$i"]]
			          : (isset($contactProperties[$p['display_name']])
			                 ? $contactProperties[$p['display_name']]
			                 : FALSE);

			if (FALSE($dn)) {
				$this->vcard->add('EMAIL', $contactProperties[$p["email_address_$i"]], array('pref' => "$i"));
			}
			else {
				$this->vcard->add('EMAIL', $contactProperties[$p["email_address_$i"]], array('pref' => "$i", 'X-CN' => "\"$dn\""));
			}
		}
		// Categories: $contactProperties[$p['categories']] can be array or string:
		$this->setVCard('CATEGORIES', $contactProperties, $p['categories']);

		// Contact picture?
		$this->get_contact_picture($contact, $contactProperties);

		// Misc
		if (!isset($contactProperties[PR_CARDDAV_URI])) {
			// Create an URI from the EntryID:
			$contactProperties[PR_CARDDAV_URI] = $this->bridge->entryid_to_uri($contactProperties[PR_ENTRYID]);
		}
		$this->vcard->add('UID', substr($contactProperties[PR_CARDDAV_URI], 0, -4));
		$this->setVCard('NOTE', $contactProperties, $p['notes']);

		$this->vcard->PRODID = VCARD_PRODUCT_ID;
		return TRUE;
	}

	private function
	get_contact_picture ($contact, $props)
	{
		if (!isset($props[PR_HASATTACH]) || !$props[PR_HASATTACH] || FALSE($this->vcard)) {
			return;
		}
		if (FALSE($attachment_table = mapi_message_getattachmenttable($contact))
		 || FALSE($attachments = mapi_table_queryallrows($attachment_table, array
			( PR_ATTACH_NUM
			, PR_ATTACH_SIZE
			, PR_ATTACH_LONG_FILENAME
			, PR_ATTACH_FILENAME
			, PR_ATTACHMENT_HIDDEN
			, PR_DISPLAY_NAME
			, PR_ATTACH_METHOD
			, PR_ATTACH_CONTENT_ID
			, PR_ATTACH_MIME_TAG
			, PR_ATTACHMENT_CONTACTPHOTO
			, PR_EC_WA_ATTACHMENT_HIDDEN_OVERRIDE
			)))) {
			return;
		}
		$photo = FALSE;
		foreach ($attachments as $attachment) {
			if (!isset($attachment[PR_ATTACHMENT_CONTACTPHOTO]) || !$attachment[PR_ATTACHMENT_CONTACTPHOTO]) {
				continue;
			}
			if (FALSE($handle = mapi_message_openattach($contact, $attachment[PR_ATTACH_NUM]))
			 || FALSE($photo = mapi_attach_openbin($handle, PR_ATTACH_DATA_BIN))) {
				continue;
			}
			$mime = (isset($attachment[PR_ATTACH_MIME_TAG])) ? $attachment[PR_ATTACH_MIME_TAG] : 'image/jpeg';
			break;
		}
		if (FALSE($photo)) {
			return;
		}
		// SogoConnector does not like image/jpeg
		if ($mime == 'image/jpeg') {
			$mime = 'JPEG';
		}
		$this->logger->trace("Adding contact picture to VCard");
		$this->vcard->add('PHOTO', $photo, array('TYPE' => $mime, 'ENCODING' => 'b'));
	}

	/**
	 * Helper function to set a vObject property
	 */
	protected function setVCard ($vCardProperty, &$contactProperties, $propertyId)
	{
		if (isset($contactProperties[$propertyId]) && ($contactProperties[$propertyId] != '')) {
			$this->vcard->add($vCardProperty, $contactProperties[$propertyId]);
		}
	}

	/**
	 * Helper function to set an address in vObject
	 */
	protected function setVCardAddress ($addressType, &$contactProperties, $propertyPrefix)
	{
		$this->logger->trace("setVCardAddress - $addressType");

		if (FALSE($this->vcard)) {
			$this->logger->fatal('failed to create vCard object');
			return FALSE;
		}
		// Shorthand:
		$p = $this->extendedProperties;

		$address = array();
		if (isset($p["{$propertyPrefix}_address"])) {
			$address[] = '';	// post office box
			$address[] = '';	// extended address
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_street']])      ? $contactProperties[$p[$propertyPrefix . '_address_street']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_city']])        ? $contactProperties[$p[$propertyPrefix . '_address_city']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_state']])       ? $contactProperties[$p[$propertyPrefix . '_address_state']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_postal_code']]) ? $contactProperties[$p[$propertyPrefix . '_address_postal_code']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_country']])     ? $contactProperties[$p[$propertyPrefix . '_address_country']] : '';
		}
		if (strlen(implode('', $address)) === 0) {
			return;
		}
		$this->logger->trace("Nonempty address - adding $propertyPrefix");
		$this->vcard->add('ADR', $address, array('TYPE' => $addressType));
	}

	private function
	instantMessagingConvert ($contactProperties)
	{
		// Shorthand notation:
		$p = $this->extendedProperties['im'];

		if (!isset($contactProperties[$p]) || $contactProperties[$p] === '') {
			return;
		}
		$elems = array();
		foreach (explode(';', $contactProperties[$p]) as $elem)
		{
			$type = FALSE;
			$name = FALSE;

			if (FALSE($pos = strpos($elem, ':'))) {
				$name = $elem;
			}
			else if ($pos === 0 && strlen($elem) > 1) {
				$name = strpos($elem, 1);
			}
			else if ($pos < strlen($elem) - 1) {
				$type = substr($elem, 0, $pos);
				$name = substr($elem, $pos + 1);
			}
			// Check for exact duplicates:
			foreach ($elems as $e) {
				if ($e[0] === $type && $e[1] === $name) {
					continue 2;
				}
			}
			// If no type tag, only add if same name with type tag
			// not added (theirs is more specific):
			if (FALSE($type)) {
				foreach ($elems as $e) {
					if (!FALSE($e[0]) && $e[1] === $name) {
						continue 2;
					}
				}
			}
			// If type tag, delete any existing element with same
			// name but no type (ours is more specific):
			else {
				for ($i = count($elems) - 1; $i >= 0; $i--) {
					if (FALSE($elems[$i][0]) && $elems[$i][1] === $name) {
						unset($elems[$i]);
					}
				}
			}
			$elems[] = array($type, $name, $elem);
		}
		// Add each element:
		foreach ($elems as $elem) {
			$this->vcard->add('IMPP', $elem[2]);
		}
	}

	private function
	websiteConvert ($contactProperties)
	{
		// Shorthand notation:
		$p = $this->extendedProperties['webpage'];

		if (!isset($contactProperties[$p]) || $contactProperties[$p] === '') {
			return;
		}
		foreach (explode(';', $contactProperties[$p]) as $elem) {
			$this->vcard->add('URL', $elem);
		}
	}
}
