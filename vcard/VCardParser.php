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
 
require_once "vcard/IVCardParser.php";
require_once "config.inc.php";

require_once 'ZarafaLogger.php';

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
		$this->logger = new Zarafa_Logger(__CLASS__);
		$this->logger->trace(__CLASS__ . " constructor done.");
	}

	/**
	 * Convert vObject to an array of properties
     * @param object $vCard 
     * @param object $properties array storing MAPI properties
	 */
	public function vObjectToProperties ($vcard, &$properties)
	{
		$this->logger->info("vObjectToProperties");
		
		$this->logger->trace("VObject: \n" . print_r($vcard, TRUE));
		
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
			$properties[$p['department_name']] = NULL;
			$properties[$p['birthday']] = NULL;
			$properties[$p['wedding_anniversary']] = NULL;
			$properties[$p['home_telephone_number']] = NULL;
			$properties[$p['home2_telephone_number']] = NULL;
			$properties[$p['cellular_telephone_number']] = NULL;
			$properties[$p['office_telephone_number']] = NULL;
			$properties[$p['business2_telephone_number']] = NULL;
			$properties[$p['business_fax_number']] = NULL;
			$properties[$p['home_fax_number']] = NULL;
			$properties[$p['primary_fax_number']] = NULL;
			$properties[$p['primary_telephone_number']] = NULL;
			$properties[$p['pager_telephone_number']] = NULL;
			$properties[$p['other_telephone_number']] = NULL;
			$properties[$p['isdn_number']] = NULL;
			$properties[$p['company_telephone_number']] = NULL;
			$properties[$p['car_telephone_number']] = NULL;
			$properties[$p['assistant_telephone_number']] = NULL;
			$properties[$p['assistant']] = NULL;
			$properties[$p['manager_name']] = NULL;
			$properties[$p['mobile_telephone_number']] = NULL;
			$properties[$p['ttytdd_telephone_number']] = NULL;
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
		if (isset($vcard->N)) {
			$this->logger->trace("N: " . $vcard->N);
			$parts = $vcard->N->getParts();

			$dump = print_r($parts, true);
			$this->logger->trace("Name info\n$dump");
			
			$properties[$p['surname']]             = isset($parts[0]) ? $parts[0] : '';
			$properties[$p['given_name']]          = isset($parts[1]) ? $parts[1] : '';
			$properties[$p['middle_name']]         = isset($parts[2]) ? $parts[2] : '';
			$properties[$p['display_name_prefix']] = isset($parts[3]) ? $parts[3] : '';
			$properties[$p['generation']]          = isset($parts[4]) ? $parts[4] : '';
			
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

		if (isset($vcard->ORG)) {
			$parts = $vcard->ORG->getParts();
			if (isset($parts[0])) $properties[$p['company_name']] = $parts[0];
			if (isset($parts[1])) $properties[$p['department_name']] = $parts[1];
		}
		if (isset($vcard->FN)) {
			$properties[$p['display_name']] = $vcard->FN->value;
			$properties[PR_SUBJECT] = $vcard->FN->value;
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
		
		// Dates:
		if (isset($vcard->bday)) {
			$time = new DateTime($vcard->bday->value);
			$properties[$p['birthday']] = $time->format('U');
		}
		if (isset($vcard->anniversary)) {
			$time = new DateTime($vcard->anniversary->value);
			$properties[$p['wedding_anniversary']] = $time->format('U');
		}
		if (isset($vcard->rev)) {
			$time = new DateTime($vcard->rev->value);
			$properties[$p['last_modification_time']] = $time->format('U');
		}
		else {
			$properties[$p['last_modification_time']] = time();
		}
		// Telephone numbers
		$this->phoneConvert($vcard, $properties, $p);

		// Social media profiles:
		$this->socialProfileConvert($vcard, $properties, $p);

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
					$this->logger->debug("Unknown address type '$type' - skipping");
					// 'continue' in PHP would break from the switch, not the for-loop;
					// need to break two levels to proceed to the next foreach() iteration:
					continue 2;
			}
			$addressComponents = $address->getParts();

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
			$type     = strtolower($vcard->photo->offsetGet('TYPE')->value);
			$encoding = strtolower($vcard->photo->offsetGet('ENCODING')->value);
			$content  = $vcard->photo->value;

			$this->logger->debug("Found contact picture type $type encoding $encoding");

			$this->photoConvert($content, $type, $encoding, $properties, $p);
		}
		
		// Misc
		$properties[$p["icon_index"]] = "512";		// Zarafa specific?
		if (isset($vcard->note))			$properties[$p['notes']] = $vcard->note->value;
	}

	private function
	phoneConvert (&$vcard, &$mapi, &$propertyKeys)
	{
		$n_home_voice = 0;
		$n_work_voice = 0;
		foreach ($vcard->select('TEL') as $tel)
		{
			$pk = FALSE;
			$types = array();

			// Get array of types; $type is a Sabre\VObject\Parameter:
			foreach ($tel->offsetGet('TYPE') as $type) {
				$types[strtoupper($type->value)] = TRUE;
			}
			if (isset($types['HOME'])) {
				if (isset($types['FAX'])) {
					$pk = 'home_fax_number';
				}
				else {
					if (($pref = $tel->offsetGet('PREF')) !== NULL) {
						$pk = ($pref->value == '1')
						    ? 'home_telephone_number'
						    : 'home2_telephone_number';
					}
					else {
						$pk = ($n_home_voice == 1)
						    ? 'home2_telephone_number'
						    : 'home_telephone_number';
					}
					$n_home_voice++;
				}
			}
			elseif (isset($types['WORK'])) {
				if (isset($types['VOICE'])) {
					if (($pref = $tel->offsetGet('PREF')) !== NULL) {
						$pk = ($pref->value == '1')
						    ? 'office_telephone_number'
						    : 'business2_telephone_number';
					}
					else {
						$pk = ($n_work_voice == 1)
						    ? 'business2_telephone_number'
						    : 'office_telephone_number';
					}
					$n_work_voice++;
				}
				elseif (isset($types['FAX'])) {
					$pk = 'business_fax_number';
				}
				else $pk = 'company_telephone_number';
			}
			elseif (isset($types['OTHER']))
			{
				// There is unfortunately no 'other_fax_number'.
				// TODO: Zarafa defines faxes 1..3, maybe use them here:
				if (!isset($types['FAX'])) {
					$pk = 'other_telephone_number';
				}
			}
			if (FALSE($pk)) {
				// No match yet? Try to match against map:
				// Note: there is also 'cellular_telephone_number',
				// but it's an alias for 'mobile_telephone_number'.
				$map = array
					( 'CAR'       => 'car_telephone_number'
					, 'CELL'      => 'mobile_telephone_number'
					, 'FAX'       => 'primary_fax_number'
					, 'IPHONE'    => 'mobile_telephone_number'
					, 'ISDN'      => 'isdn_number'
					, 'MAIN'      => 'primary_telephone_number'
					, 'PAGER'     => 'pager_telephone_number'
					, 'SECR'      => 'assistant_telephone_number'
					, 'TEXTPHONE' => 'ttytdd_telephone_number'
					);

				foreach ($map as $prop_vcard => $prop_mapi) {
					if (isset($types[$prop_vcard])) {
						$pk = $prop_mapi;
						break;
					}
				}
			}
			// Still no match found?
			if (FALSE($pk)) {
				// If no type info set (so just 'TEL:'), use default phone property:
				if (count($types) == 0) {
					$pk = DEFAULT_TELEPHONE_NUMBER_PROPERTY;
				}
				// Otherwise some unknown type was specified:
				else {
					$this->logger->warn('Unknown telephone type(s): '.implode(';', array_keys($types)));
					continue;
				}
			}
			$mapi[$propertyKeys[$pk]] = $tel->value;
		}
	}

	private function
	photoConvert ($content, $type, $encoding, &$mapi, &$propertyKeys)
	{
		if ($encoding !== 'b' && $encoding != '') {
			$this->logger->warn("Encoding not supported: $encoding");
			return FALSE;
		}
		if (FALSE($content = base64_decode($content))) {
			$this->logger->warn('Error: failed to base64-decode contact photo');
			return FALSE;
		}
		// Convert to JPEG if not already in that format:
		if ($type != 'jpeg' && $type != 'image/jpeg' && $type != 'image/jpg')
		{
			if (FALSE(extension_loaded('gd'))) {
				$this->logger->warn("Cannot convert image of type \"$type\" to jpeg: GD extension not installed");
				return FALSE;
			}
			$this->logger->trace('Converting to jpeg using GD');
			if (FALSE($img = imagecreatefromstring($content))) {
				$this->logger->warn('Corrupted contact picture or unknown format');
				return FALSE;
			}
			$this->logger->trace('Image loaded by GD');
			// Capture output
			ob_start();
			$r = imagejpeg($img);
			$content = ob_get_contents();
			ob_end_clean();
			imagedestroy($img);
		}
		$this->logger->info('Contact has picture!');
		$mapi['ContactPicture'] = $content;
		$mapi[PR_HASATTACH] = TRUE;
		$mapi[$propertyKeys['has_picture']] = TRUE;

		return TRUE;
	}

	private function
	socialProfileConvert (&$vcard, &$mapi, &$propertyKeys)
	{
		foreach ($vcard->select('X-SOCIALPROFILE') as $prop)
		{
			if (($params = $prop->offsetGet('TYPE')) === NULL) {
				$this->logger->trace(sprintf('Ignoring social profile with value "%s"', $prop->value));
				continue;
			}
			$types = array();
			foreach ($params as $param) {
				$types[$param->value] = TRUE;
			}
			// Possibly do something with the types and objects here.
			// Observed strings passed by OSX Contacts:
			//   X-SOCIALPROFILE;type=twitter:http://twitter.com/name
			//   X-SOCIALPROFILE;type=facebook:http://facebook.com/name
			//   X-SOCIALPROFILE;type=flickr:http://www.flickr.com/photos/name
			//   X-SOCIALPROFILE;type=linkedin:http://www.linkedin.com/in/name
			//   X-SOCIALPROFILE;type=myspace:http://www.myspace.com/name
			//   X-SOCIALPROFILE;type=sinaweibo:http://weibo.com/n/name

			$this->logger->trace(sprintf('Ignoring social profile at "%s" with value "%s"', implode('/', array_keys($types)), $prop->value));
		}
	}
}
