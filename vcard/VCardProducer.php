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

// Logging
include_once ("log4php/Logger.php");
Logger::configure("log4php.xml");
 
require_once "vcard/IVCardProducer.php";
require_once "config.inc.php";
	
// PHP-MAPI
require_once("mapi/mapi.util.php");
require_once("mapi/mapicode.php");
require_once("mapi/mapidefs.php");
require_once("mapi/mapitags.php");
require_once("mapi/mapiguid.php");
	
class VCardProducer implements IVCardProducer {

	public $defaultCharset;
	protected $bridge;
	protected $version;
	protected $logger;
	
	function __construct($bridge, $version) {
		$this->bridge = $bridge;
		$this->version = $version;
		$this->defaultCharset = 'utf-8';
		$this->logger = Logger::getLogger(__CLASS__);
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
	
	/**
	 * Convert vObject to an array of properties
     * @param array $properties 
	 * @param object $vCard
	 */
	public function propertiesToVObject($contact, &$vCard) {

		$this->logger->debug("Generating contact vCard from properties");
		
		$p = $this->bridge->getExtendedProperties();
		$contactProperties =  mapi_getprops($contact); // $this->bridge->getProperties($contactId);
		
		$dump = print_r($contactProperties, true);
		$this->logger->trace("Contact properties:\n$contactProperties");
		
		// Version check
		switch ($this->version) {
			case 2:		$vCard->add('VERSION', '2.1');	break;
			case 3:		$vCard->add('VERSION', '3.0');	break;
			case 4:		$vCard->add('VERSION', '4.0');	break;
			default:
				$this->logger->fatal("Unrecognised VCard version: " . $this->version);
				return;
		}
		
		// Private contact ?
		if (isset($contactProperties[$p['private']]) && $contactProperties[$p['private']]) {
			$vCard->add('CLASS', 'PRIVATE');		// Not in VCARD 4.0 but keep it for compatibility
		}

		// Mandatory FN
		$this->setVCard($vCard, 'FN', $contactProperties, $p['display_name']);
		
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
		
		$element = new Sabre_VObject_Property("N");
		$element->setValue(implode(';', $contactInfos));
		// $element->offsetSet("SORT-AS", '"' . $contactProperties[$p['fileas']] . '"');
		$vCard->add($element);
		
		$this->setVCard($vCard, 'SORT-AS',         $contactProperties, $p['fileas']);
		$this->setVCard($vCard, 'NICKNAME',        $contactProperties, $p['nickname']);
		$this->setVCard($vCard, 'TITLE',           $contactProperties, $p['title']);
		$this->setVCard($vCard, 'ROLE',            $contactProperties, $p['profession']);
		$this->setVCard($vCard, 'ORG',             $contactProperties, $p['company_name']);
		$this->setVCard($vCard, 'OFFICE',          $contactProperties, $p['office_location']);

		if ($this->version >= 4) {
			if (isset($contactProperties[$p['assistant']])) {
				if (!empty ($contactProperties[$p['assistant']])) {
					$element = new Sabre_VObject_Property('RELATED');
					$element->setValue( $contactProperties[$p['assistant']]);
					$element->offsetSet('TYPE','assistant');	// Not RFC compliant
					$vCard->add($element);
				}
			}

			if (isset($contactProperties[$p['manager_name']])) {
				if (!empty ($contactProperties[$p['manager_name']])) {
					$element = new Sabre_VObject_Property('RELATED');
					$element->setValue( $contactProperties[$p['manager_name']]);
					$element->offsetSet('TYPE','manager');		// Not RFC compliant
					$vCard->add($element);
				}
			}

			if (isset($contactProperties[$p['spouse_name']])) {
				if (!empty ($contactProperties[$p['spouse_name']])) {
					$element = new Sabre_VObject_Property('RELATED');
					$element->setValue( $contactProperties[$p['spouse_name']]);
					$element->offsetSet('TYPE','spouse');
					$vCard->add($element);
				}
			}
		} 
		
		// older syntax - may be needed by some clients so keep it!
		$this->setVCard($vCard, 'X-MS-ASSISTANT',  $contactProperties, $p['assistant']);
		$this->setVCard($vCard, 'X-MS-MANAGER',    $contactProperties, $p['manager_name']);
		$this->setVCard($vCard, 'X-MS-SPOUSE',     $contactProperties, $p['spouse_name']);
		
		// Dates
		if (isset($contactProperties[$p['birthday']]) && ($contactProperties[$p['birthday']] > 0))
			$vCard->add('BDAY', date(DATE_PATTERN, $contactProperties[$p['birthday']]));
		
		if (isset($contactProperties[$p['wedding_anniversary']]) && ($contactProperties[$p['wedding_anniversary']] > 0)) {
			if ($this->version >= 4) {
				$vCard->add('ANNIVERSARY', date(DATE_PATTERN, $contactProperties[$p['wedding_anniversary']]));
			} else {
				$vCard->add('X-ANNIVERSARY', 		   date(DATE_PATTERN, $contactProperties[$p['wedding_anniversary']]));
			}
		}
		
		// Telephone numbers
		// webaccess can handle 19 telephone numbers...
		$this->setVCard($vCard,'TEL;TYPE=HOME,VOICE', $contactProperties,$p['home_telephone_number']);
		$this->setVCard($vCard,'TEL;TYPE=HOME,VOICE', $contactProperties,$p['home2_telephone_number']);
		$this->setVCard($vCard,'TEL;TYPE=CELL',		  $contactProperties,$p['cellular_telephone_number']);
		$this->setVCard($vCard,'TEL;TYPE=WORK,VOICE', $contactProperties,$p['office_telephone_number']);
		$this->setVCard($vCard,'TEL;TYPE=WORK,VOICE', $contactProperties,$p['business2_telephone_number']);
		$this->setVCard($vCard,'TEL;TYPE=WORK,FAX',   $contactProperties,$p['business_fax_number']);
		$this->setVCard($vCard,'TEL;TYPE=HOME,FAX',   $contactProperties,$p['home_fax_number']);
		$this->setVCard($vCard,'TEL;TYPE=PAGER',      $contactProperties,$p['pager_telephone_number']);
		$this->setVCard($vCard,'TEL;TYPE=ISDN',       $contactProperties,$p['isdn_number']);
		$this->setVCard($vCard,'TEL;TYPE=WORK',       $contactProperties,$p['company_telephone_number']);
		$this->setVCard($vCard,'TEL;TYPE=CAR',        $contactProperties,$p['car_telephone_number']);
		$this->setVCard($vCard,'TEL;TYPE=SECR',		  $contactProperties,$p['assistant_telephone_number']);

		// There are unmatched telephone numbers in zarafa, use them!
		$unmatchedProperties = array("callback_telephone_number", "other_telephone_number", "primary_fax_number",
									 "primary_telephone_number", "radio_telephone_number", "telex_telephone_number",
									 "ttytdd_telephone_number"
									);
		
		if (in_array(DEFAULT_TELEPHONE_NUMBER_PROPERTY, $unmatchedProperties)) {
			// unmatched found a match!
			$this->setVCard($vCard, 'TEL', $contactProperties, $p[DEFAULT_TELEPHONE_NUMBER_PROPERTY]);
		}

		$this->setVCardAddress($vCard, 'HOME',  $contactProperties, 'home');
		$this->setVCardAddress($vCard, 'WORK',  $contactProperties, 'business');
		$this->setVCardAddress($vCard, 'OTHER', $contactProperties, 'other');
		
		// emails
		for ($i = 1; $i <= 3; $i++) {
			if (isset($contactProperties[$p["email_address_$i"]])) {
				// Zarafa needs an email display name
				$emailProperty = new Sabre_VObject_Property('EMAIL', $contactProperties[$p["email_address_$i"]]);
				
				// Get display name
				$dn = isset($contactProperties[$p["email_address_display_name_$i"]]) ? $contactProperties[$p["email_address_display_name_$i"]]
																					 : $contactProperties[$p['display_name']];
				
				$emailProperty->offsetSet("X-CN", '"' . $dn . '"');
				$vCard->add($emailProperty);
			}
		}
		
		// URL and Instant Messenging (vCard 3.0 extension)
		$this->setVCard($vCard,'URL',   $contactProperties,$p["webpage"]); 
		$this->setVCard($vCard,'IMPP',  $contactProperties,$p["im"]); 
		
		// Categories
		$contactCategories = '';
		if (isset($contactProperties[$p['categories']])) {
			if (is_array($contactProperties[$p['categories']])) {
				$contactCategories = implode(',', $contactProperties[$p['categories']]);
			} else {
				$contactCategories = $contactProperties[$p['categories']];
			}
		}
		if ($contactCategories != '') {
			$vCard->add('CATEGORIES',  $contactCategories);
		}

		// Contact picture?
		$hasattachProp = mapi_getprops($contact, array(PR_HASATTACH));
		$photo = NULL;
		$photoMime = '';
		if (isset($hasattachProp[PR_HASATTACH])&& $hasattachProp[PR_HASATTACH]) {
			$attachmentTable = mapi_message_getattachmenttable($contact);
			$attachments = mapi_table_queryallrows($attachmentTable, array(PR_ATTACH_NUM, PR_ATTACH_SIZE, PR_ATTACH_LONG_FILENAME, PR_ATTACH_FILENAME, PR_ATTACHMENT_HIDDEN, PR_DISPLAY_NAME, PR_ATTACH_METHOD, PR_ATTACH_CONTENT_ID, PR_ATTACH_MIME_TAG, PR_ATTACHMENT_CONTACTPHOTO, PR_EC_WA_ATTACHMENT_HIDDEN_OVERRIDE));
			
			$dump = print_r ($attachments, true);
			$this->logger->trace("Contact attachments:\n$dump");
			
			foreach ($attachments as $attachmentRow) {
				if (isset($attachmentRow[PR_ATTACHMENT_CONTACTPHOTO]) && $attachmentRow[PR_ATTACHMENT_CONTACTPHOTO]) {
					$attach = mapi_message_openattach($contact, $attachmentRow[PR_ATTACH_NUM]);
					$photo = mapi_attach_openbin($attach,PR_ATTACH_DATA_BIN);
					if (isset($attachmentRow[PR_ATTACH_MIME_TAG])) {
						$photoMime = $attachmentRow[PR_ATTACH_MIME_TAG];
					} else {
						$photoMime = 'image/jpeg';
					}
					break;
				}
			}
		}
		if ($photo != NULL) {
			// SogoConnector does not like image/jpeg
			if ($photoMime == 'image/jpeg') {
				$photoMime = 'JPEG';
			}
		
			$this->logger->trace("Adding contact picture to VCard");
			$photoEncoded = base64_encode($photo);
			$photoProperty = new Sabre_VObject_Property('PHOTO',$photoEncoded);
			$photoProperty->offsetSet('TYPE', $photoMime);
			$photoProperty->offsetSet('ENCODING','b');
			$vCard->add($photoProperty);
		}
		
		// Misc
		$vCard->add('UID', "urn:uuid:" . substr($contactProperties[PR_CARDDAV_URI], 0, -4)); // $this->entryIdToStr($contactProperties[PR_ENTRYID]));
		$this->setVCard($vCard, 'NOTE', $contactProperties, $p['notes']);
		$vCard->add('PRODID', VCARD_PRODUCT_ID);
		$vCard->add('REV', date('c',$contactProperties[$p['last_modification_time']]));
	}
	
	/**
	 * Helper function to set a vObject property
	 */
	protected function setVCard($vCard, $vCardProperty, &$contactProperties, $propertyId) {
		if (isset($contactProperties[$propertyId]) && ($contactProperties[$propertyId] != '')) {
			$vCard->add($vCardProperty, $contactProperties[$propertyId]);
		}
	}

	/**
	 * Helper function to set an address in vObject
	 */
	protected function setVCardAddress($vCard, $addressType, &$contactProperties, $propertyPrefix) {

		$this->logger->trace("setVCardAddress - $addressType");
		
		$p = $this->bridge->getExtendedProperties();
		
		$address = array();
		if (isset($contactProperties[$p[$propertyPrefix ."_address"]])) {
			$address[] = '';	// post office box
			$address[] = '';	// extended address
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_street']])      ? $contactProperties[$p[$propertyPrefix . '_address_street']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_city']])        ? $contactProperties[$p[$propertyPrefix . '_address_city']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_state']])       ? $contactProperties[$p[$propertyPrefix . '_address_state']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_postal_code']]) ? $contactProperties[$p[$propertyPrefix . '_address_postal_code']] : '';
			$address[] = isset($contactProperties[$p[$propertyPrefix . '_address_country']])     ? $contactProperties[$p[$propertyPrefix . '_address_country']] : '';
		}
		
		$address = implode(';', $address);
		
		if ($address != ';;;;;;') {
			$this->logger->trace("Not empty address - adding $address");
			$element = new Sabre_VObject_Property('ADR');
			$element->setValue($address);
			$element->offsetSet('TYPE', $addressType);
			$vCard->add($element);
		}
	}

}

?>
