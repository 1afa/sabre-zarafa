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
 
require_once "vcard/IVCardParser.php";
require_once "config.inc.php";

// Logging
include_once ("log4php/Logger.php");
	
// PHP-MAPI
require_once("mapi/mapi.util.php");
require_once("mapi/mapicode.php");
require_once("mapi/mapidefs.php");
require_once("mapi/mapitags.php");
require_once("mapi/mapiguid.php");
	
class VCardParser implements IVCardParser {

	protected $bridge;
	protected $logger;
	
	function __construct($bridge) {
		$this->bridge = $bridge;
		$this->logger = Logger::getLogger(__CLASS__);
		$this->logger->trace(__CLASS__ . " constructor done.");
	}

	/**
	 * Convert vObject to an array of properties
     * @param object $vCard 
     * @param object $properties array storing MAPI properties
	 */
	public function vObjectToProperties($vcard, &$properties) {
		$this->logger->info("vObjectToProperties");
		
		ob_start();
		print_r ($vcard);
		$dump = ob_get_contents();
		ob_end_clean();

		$this->logger->trace("VObject :\n$dump");
		
		// Common VCard properties parsing
		$p = $this->bridge->getExtendedProperties();
		
		// Init properties
		if (CLEAR_MISSING_PROPERTIES) {
			$this->logger->trace("Clearing missing properties");
			$properties[$p['surname']] = NULL;
			$properties[$p['given_name']] = NULL;
			$properties[$p['middle_name']] = NULL;
			$properties[$p['display_name_prefix']] = NULL;
			$properties[$p['generation']] = NULL;
			$properties[$p['display_name']] = NULL;
			$properties[$p['nickname']] = NULL;
			$properties[$p['title']] = NULL;
			$properties[$p['profession']] = NULL;
			$properties[$p['office_location']] = NULL;
			$properties[$p['company_name']] = NULL;
			$properties[$p['birthday']] = NULL;
			$properties[$p['wedding_anniversary']] = NULL;
			$properties[$p['home_telephone_number']] = NULL;
			$properties[$p['home2_telephone_number']] = NULL;
			$properties[$p['cellular_telephone_number']] = NULL;
			$properties[$p['office_telephone_number']] = NULL;
			$properties[$p['business2_telephone_number']] = NULL;
			$properties[$p['business_fax_number']] = NULL;
			$properties[$p['home_fax_number']] = NULL;
			$properties[$p['pager_telephone_number']] = NULL;
			$properties[$p['isdn_number']] = NULL;
			$properties[$p['company_telephone_number']] = NULL;
			$properties[$p['car_telephone_number']] = NULL;
			$properties[$p['assistant_telephone_number']] = NULL;
			$properties[$p['assistant']] = NULL;
			$properties[$p['manager_name']] = NULL;
			$properties[$p['spouse_name']] = NULL;
			$properties[$p['home_address_street']] = NULL;
			$properties[$p['home_address_city']] = NULL;
			$properties[$p['home_address_state']] = NULL;
			$properties[$p['home_address_postal_code']] = NULL;
			$properties[$p['home_address_country']] = NULL;
			$properties[$p['business_address_street']] = NULL;
			$properties[$p['business_address_city']] = NULL;
			$properties[$p['business_address_state']] = NULL;
			$properties[$p['business_address_postal_code']] = NULL;
			$properties[$p['business_address_country']] = NULL;
			$properties[$p['other_address_street']] = NULL;
			$properties[$p['other_address_city']] = NULL;
			$properties[$p['other_address_state']] = NULL;
			$properties[$p['other_address_postal_code']] = NULL;
			$properties[$p['other_address_country']] = NULL;
			$nremails = array();
			$abprovidertype = 0;
			for ($i = 1; $i <= 3; $i++) {
				$properties[$p["email_address_$i"]] = NULL;
				$properties[$p["email_address_display_name_email_$i"]] = NULL;
				$properties[$p["email_address_display_name_$i"]] = NULL;
				$properties[$p["email_address_type_$i"]] = NULL;
				$properties[$p["email_address_entryid_$i"]] = NULL;
			}
			$properties[$p["address_book_mv"]] = NULL;
			$properties[$p["address_book_long"]] = NULL;
			$properties[$p['webpage']] = NULL;
			$properties[$p['im']] = NULL;
			$properties[$p['categories']] = NULL;
			$properties['ContactPicture'] = NULL;
			$properties[PR_HASATTACH] = false;
			$properties[$p['has_picture']] = false;
		}
		
		// Name components
		$sortAs = '';
		if (isset($vcard->n)) {
			$this->logger->trace("N: " . $vcard->n);
			$nameInfo = VCardParser::splitCompundProperty($vcard->n->value);
			$dump = print_r($nameInfo, true);
			$this->logger->trace("Name info\n$dump");
			
			$properties[$p['surname']]             = isset($nameInfo[0]) ? $nameInfo[0] : '';
			$properties[$p['given_name']]          = isset($nameInfo[1]) ? $nameInfo[1] : '';
			$properties[$p['middle_name']]         = isset($nameInfo[2]) ? $nameInfo[2] : ''; 
			$properties[$p['display_name_prefix']] = isset($nameInfo[3]) ? $nameInfo[3] : ''; 
			$properties[$p['generation']]          = isset($nameInfo[4]) ? $nameInfo[4] : '';
			
			// Issue 3#8
			if ($vcard->n->offsetExists('SORT-AS')) {
				$sortAs = $vcard->n->offsetGet('SORT-AS')->value;
			}
		}
		
		// Given sort-as ?
		/*
		if (isset($vcard->sort-as)) {
			$this->logger->debug("Using vcard SORT-AS");
			$sortAs = $vcard->sort-as->value;
		}
		*/
		$sortAsProperty = $vcard->select("SORT-AS");
		if (count($sortAsProperty) != 0) {
			$sortAs = current($sortAsProperty)->value;
		}
		
		if (isset($vcard->nickname))		$properties[$p['nickname']] = $vcard->nickname->value;
		if (isset($vcard->title))			$properties[$p['title']] = $vcard->title->value;
		if (isset($vcard->role))			$properties[$p['profession']] = $vcard->role->value;
		if (isset($vcard->office))			$properties[$p['office_location']] = $vcard->office->value;
		if (isset($vcard->org)) {
			$orgInfo = VCardParser::splitCompundProperty($vcard->org->value);
			$properties[$p['company_name']] = $orgInfo[0];
		}

		if (isset($vcard->fn)) {
			$properties[$p['display_name']] = $vcard->fn->value;
			$properties[PR_SUBJECT] = $vcard->fn->value;
		}

		if (empty($sortAs) || SAVE_AS_OVERRIDE_SORTAS) {
			$this->logger->trace("Empty sort-as or SAVE_AS_OVERRIDE_SORTAS set");
			$sortAs = SAVE_AS_PATTERN;		// $vcard->fn->value;
			
			// Do substitutions
			$substitutionKeys   = array('%d', '%l', '%f', '%c');
			$substitutionValues = array(
				$properties[$p['display_name']],
				$properties[$p['surname']],
				$properties[$p['given_name']],
				$properties[$p['company_name']]
			);
			$sortAs = str_replace($substitutionKeys, $substitutionValues, $sortAs);
		}

		// Should PR_SUBJET and display_name be equals to fileas? I think so!
		$this->logger->debug("Contact display name: " . $sortAs);
		$properties[$p['fileas']] = $sortAs;
		$properties[$p['display_name']] = $sortAs;
		$properties[PR_SUBJECT] = $sortAs;
		
		// Custom... not quite sure X-MS-STUFF renders as x_ms_stuff... will have to check that!
		if (isset($vcard->x_ms_assistant))	$properties[$p['assistant']] = $vcard->x_ms_assistant->value;
		if (isset($vcard->x_ms_manager))	$properties[$p['manager_name']] = $vcard->x_ms_manager->value;
		if (isset($vcard->x_ms_spouse))		$properties[$p['spouse_name']] = $vcard->x_ms_spouse->value;
		
		// Dates
		if (isset($vcard->bday)) {
			$time = new DateTime($vcard->bday->value);
			$properties[$p['birthday']] = $time->format('U');
		}
			
		if (isset($vcard->anniversary)) {
			$time = new DateTime($vcard->anniversary->value);
			$properties[$p['wedding_anniversary']] = $time->format('U');
		}
		
		// Telephone numbers... todo as this is a bit complicated :p
		$typeCount = array(
			'HOME,VOICE' => 0,
			'CELL,VOICE' => 0,
			'CELL'		 => 0,
			'WORK,VOICE' => 0,
			'WORK,FAX'   => 0,
			'PAGER'		 => 0,
			'ISDN'		 => 0,
			'WORK'		 => 0,
			'CAR'		 => 0,
			'MAIN'		 => 0,
			'SECR'		 => 0
		);
		$telephoneNumbers = $vcard->select("TEL");
		foreach ($telephoneNumbers as $tel) {
			$type = '';
			$pk = '';

			// Get type
			$typeParam = $tel->offsetGet("TYPE");
			if ($typeParam != NULL) {
				$type = '';
				foreach ($typeParam as $tp) {
					if (!in_array(strtoupper($tp->value), array('PREF', 'IPHONE'))) {
						$type .= ($type == '') ? '' : ',';
						$type .= strtoupper($tp->value);
					}
				}
			}
			
			if (($type == 'HOME,VOICE') || ($type == 'HOME')) {
				$type = 'HOME,VOICE';	// Force count key
				$pk = 'home_telephone_number';
				if ($typeCount[$type] == 1) {
					$pk = 'home2_telephone_number';
				}
			}
			if ($type == 'WORK,VOICE') {
				$pk = 'office_telephone_number';
				if ($typeCount[$type] == 1) {
					$pk = 'business2_telephone_number';
				}
			}
			if ($type == 'CELL,VOICE')		$pk = 'cellular_telephone_number';
			if ($type == 'CELL')			$pk = 'cellular_telephone_number';
			if ($type == 'IPHONE')			$pk = 'cellular_telephone_number';
			if ($type == 'WORK,FAX') 		$pk = 'business_fax_number';
			if ($type == 'HOME,FAX') 		$pk = 'home_fax_number';
			if ($type == 'PAGER')			$pk = 'pager_telephone_number';
			if ($type == 'ISDN')			$pk = 'isdn_number';
			if ($type == 'WORK')			$pk = 'company_telephone_number';
			if ($type == 'CAR')				$pk = 'car_telephone_number';
			if ($type == 'SECR')			$pk = 'assistant_telephone_number';
			if ($type == 'MAIN')			$pk = 'primary_telephone_number';
			if ($type == '')				$pk = DEFAULT_TELEPHONE_NUMBER_PROPERTY;
			
			// Counting
			if ($pk != '') {
				if (!isset($typeCount[$type])) {
					$typeCount[$type] = 0;
				}
				$properties[$p[$pk]] = $tel->value;
				$typeCount[$type]++;
			} else {
				$this->logger->warn("Unknown telephone type: '$type'");
			}
		}
		
		// Addresses...
		$addresses = $vcard->select('ADR');
		foreach ($addresses as $address) {
			$type = strtoupper($address->offsetGet('TYPE')->value);
			$this->logger->debug("Found address $type");

			switch ($type) {
				case 'HOME':
					$pStreet  = 'home_address_street';
					$pCity    = 'home_address_city';
					$pState   = 'home_address_state';
					$pPCode   = 'home_address_postal_code';
					$pCountry = 'home_address_country';
					break;
				case 'WORK':
					$pStreet  = 'business_address_street';
					$pCity    = 'business_address_city';
					$pState   = 'business_address_state';
					$pPCode   = 'business_address_postal_code';
					$pCountry = 'business_address_country';
					break;
				case 'OTHER':
					$pStreet  = 'other_address_street';
					$pCity    = 'other_address_city';
					$pState   = 'other_address_state';
					$pPCode   = 'other_address_postal_code';
					$pCountry = 'other_address_country';
					break;
				default:
					debug ("Unknwon address type $type - skipping");
					continue;
			}
			
			$addressComponents = VCardParser::splitCompundProperty($address->value);

			$dump = print_r($addressComponents, true);
			$this->logger->trace("Address components:\n$dump");
			
			// Set properties
			$properties[$p[$pStreet]]  = isset($addressComponents[2]) ? $addressComponents[2] : '';
			$properties[$p[$pCity]]    = isset($addressComponents[3]) ? $addressComponents[3] : '';
			$properties[$p[$pState]]   = isset($addressComponents[4]) ? $addressComponents[4] : '';
			$properties[$p[$pPCode]]   = isset($addressComponents[5]) ? $addressComponents[5] : '';
			$properties[$p[$pCountry]] = isset($addressComponents[6]) ? $addressComponents[6] : '';
		}
		
		// emails need to handle complementary properties plus create one off entries!
		$nremails = array();
		$abprovidertype = 0;
		$emails = $vcard->select("EMAIL");
		$emailsDisplayName = $vcard->select("X-EMAIL-CN");		// emClient handles those
		$numMail = 0;
		
		if (is_array($emailsDisplayName)) {
			$emailsDisplayName = array_values($emailsDisplayName);
		}
		
		$dump = print_r($emailsDisplayName, true);
		$this->logger->trace("Display Names\n$dump");
		
		foreach ($emails as $email) {
			$numMail++;
			$displayName = '';
			
			if ($numMail > 3) {
				// Zarafa only handles 3 mails
				break;
			}
			
			$address = $email->value;
			
			if (count($emailsDisplayName) >= $numMail) {
				// Display name exists, use it!
				$displayName = $emailsDisplayName[$numMail - 1]->value;
			} else {
				$displayName = $vcard->fn->value;
			}
			
			// Override displayName?
			if ($email->offsetExists("X-CN")) {
				$xCn = $email->offsetGet("X-CN");
				$displayName = $xCn->value;
			}
			
			$this->logger->debug("Found email $numMail : $displayName <$address>");
			
			$properties[$p["email_address_$numMail"]] = $address;
			$properties[$p["email_address_display_name_email_$numMail"]] = $address;
			$properties[$p["email_address_display_name_$numMail"]] = $displayName;
			$properties[$p["email_address_type_$numMail"]] = "SMTP";
			$properties[$p["email_address_entryid_$numMail"]] = mapi_createoneoff($displayName, "SMTP", $address);
			$nremails[] = $numMail - 1;
			$abprovidertype |= 2 ^ ($numMail - 1);
		}
		
		if ($numMail > 0) {
			if (!empty($nremails)) $properties[$p["address_book_mv"]] = $nremails;
			$properties[$p["address_book_long"]] = $abprovidertype;
		}
		
		// URLs and instant messaging. IMPP could be multivalues, will need to check that!
		if (isset($vcard->url))				$properties[$p['webpage']] = $vcard->url->value;
		if (isset($vcard->impp))			$properties[$p['im']] = $vcard->impp->value;
		
		// Categories (multi values)
		if (isset($vcard->categories)) 		$properties[$p['categories']] = explode(',', $vcard->categories->value);
		
		// Contact picture
		if (isset($vcard->photo)) {
			$type     = strtolower($vcard->photo->offsetGet("TYPE")->value);
			$encoding = $vcard->photo->offsetGet("ENCODING")->value;
			$content  = $vcard->photo->value;
			
			$this->logger->debug("Found contact picture type $type encoding $encoding");
			
			if (($encoding == 'b') || ($encoding == '')) {
				$content = base64_decode($content);
				if (($type != 'jpeg') && ($type != 'image/jpeg') && ($type != 'image/jpg')) {
					$this->logger->trace("Converting to jpeg using GD");
					$img = imagecreatefromstring($content);
					$this->logger->trace("Image loaded by GD");
					if ($img === FALSE) {
						$this->logger->warn("Corrupted contact picture or unknown format");
						$content = NULL;
					} else {
						// Capture output
						ob_start();
						$r = imagejpeg($img);
						$content = ob_get_contents();
						ob_end_clean();
						// imagedestroy($img);
						$this->logger->debug("Convert done - result: " . ($r ? "OK" : "KO"));
					}
				}
				if ($content !== NULL) {
					$this->logger->info("Contact has picture!");
					$properties['ContactPicture'] = $content;
					$properties[PR_HASATTACH] = true;
					$properties[$p['has_picture']] = true;
				}
			} else {
				$this->logger->warn("Encoding not supported: $encoding");
			}
		}
		
		// Misc
		$properties[$p["icon_index"]] = "512";		// Zarafa specific?
		if (isset($vcard->note))			$properties[$p['notes']] = $vcard->note->value;
	}

	/**
	 * Escape vcard value
	 * , -> \, (always)
	 * ; -> \; (when required)
	 * \ -> \\ (always)
     * newline -> \n (always)
	 * @param $value value to be escaped
	 * @param compound is this part of a compound property? If so ; must be escaped
	 * @return escaped value
	 */
	public static function escapeVCardValue($value, $compound) {
		
		$replaceValues = array(
			'\\' => '\\\\',
			','	=> '\\,',
			"\r\n" => '\\n',
			"\n\r" => '\\n',
			"\n" => '\\n',
			"\r" => '\\r'
		);
		
		if ($compound) {
			$replaceValues[';'] = '\\;';
		} 
		
		return str_replace(array_keys($replaceValues), array_values($replaceValues), $value);
	}
	
	/**
	 * Unescape a property value
	 */
	public static function unescapeVCardValue($value) {
		$replaceValues = array(
			'\\,' => ',',
			'\\;' => ';',			// ; MAY be escaped so we have to unescape it always
			'\\n' => "\n",
			'\\\\' => '\\'
		);
		return str_replace(array_keys($replaceValues), array_values($replaceValues), $value);
	}

	/**
	 * Split a compound property into parts
	 * @param $propertyValue
	 * @return array
	 */
	public static function splitCompundProperty($propertyValue) {
		// Do not split \;
		return preg_split("/(?!\\\\);/", $propertyValue);
	}

}

?>
