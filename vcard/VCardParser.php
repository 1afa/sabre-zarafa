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
	
class VCardParser implements IVCardParser
{
	protected $bridge;
	protected $logger;
	protected $vcard = FALSE;
	protected $mapi = array();
	protected $extendedProperties = FALSE;

	function __construct ($bridge)
	{
		$this->bridge = $bridge;
		$this->logger = new Zarafa_Logger(__CLASS__);
		$this->logger->trace(__CLASS__ . " constructor done.");
	}

	/**
	 * Convert vObject to an array of properties
	 * @param string $vcardData the vCard in string form
	 */
	public function vObjectToProperties ($vcardData)
	{
		$this->logger->trace(__FUNCTION__);

		$this->vcard = Sabre\VObject\Reader::read($vcardData);

		if (FALSE($this->vcard)) {
			$this->logger->fatal('failed to create vCard object');
			return FALSE;
		}
		$this->logger->trace("VObject: \n" . print_r($this->vcard, TRUE));
		
		// Common VCard properties parsing
		if (FALSE($this->extendedProperties = $this->bridge->getExtendedProperties())) {
			$this->logger->fatal('failed to load extended properties');
			return FALSE;
		}
		// Use shorthand notation for brevity's sake:
		$p = $this->extendedProperties;

		// Init properties
		if (CLEAR_MISSING_PROPERTIES) {
			$this->logger->trace("Clearing missing properties");
			$this->mapi[$p['surname']] = NULL;
			$this->mapi[$p['given_name']] = NULL;
			$this->mapi[$p['middle_name']] = NULL;
			$this->mapi[$p['display_name_prefix']] = NULL;
			$this->mapi[$p['generation']] = NULL;
			$this->mapi[$p['display_name']] = NULL;
			$this->mapi[$p['nickname']] = NULL;
			$this->mapi[$p['title']] = NULL;
			$this->mapi[$p['profession']] = NULL;
			$this->mapi[$p['office_location']] = NULL;
			$this->mapi[$p['company_name']] = NULL;
			$this->mapi[$p['department_name']] = NULL;
			$this->mapi[$p['birthday']] = NULL;
			$this->mapi[$p['wedding_anniversary']] = NULL;
			$this->mapi[$p['home_telephone_number']] = NULL;
			$this->mapi[$p['home2_telephone_number']] = NULL;
			$this->mapi[$p['cellular_telephone_number']] = NULL;
			$this->mapi[$p['office_telephone_number']] = NULL;
			$this->mapi[$p['business2_telephone_number']] = NULL;
			$this->mapi[$p['business_fax_number']] = NULL;
			$this->mapi[$p['home_fax_number']] = NULL;
			$this->mapi[$p['primary_fax_number']] = NULL;
			$this->mapi[$p['primary_telephone_number']] = NULL;
			$this->mapi[$p['pager_telephone_number']] = NULL;
			$this->mapi[$p['other_telephone_number']] = NULL;
			$this->mapi[$p['isdn_number']] = NULL;
			$this->mapi[$p['company_telephone_number']] = NULL;
			$this->mapi[$p['car_telephone_number']] = NULL;
			$this->mapi[$p['assistant_telephone_number']] = NULL;
			$this->mapi[$p['assistant']] = NULL;
			$this->mapi[$p['manager_name']] = NULL;
			$this->mapi[$p['mobile_telephone_number']] = NULL;
			$this->mapi[$p['callback_telephone_number']] = NULL;
			$this->mapi[$p['radio_telephone_number']] = NULL;
			$this->mapi[$p['telex_telephone_number']] = NULL;
			$this->mapi[$p['ttytdd_telephone_number']] = NULL;
			$this->mapi[$p['spouse_name']] = NULL;
			$this->mapi[$p['home_address_street']] = NULL;
			$this->mapi[$p['home_address_city']] = NULL;
			$this->mapi[$p['home_address_state']] = NULL;
			$this->mapi[$p['home_address_postal_code']] = NULL;
			$this->mapi[$p['home_address_country']] = NULL;
			$this->mapi[$p['business_address_street']] = NULL;
			$this->mapi[$p['business_address_city']] = NULL;
			$this->mapi[$p['business_address_state']] = NULL;
			$this->mapi[$p['business_address_postal_code']] = NULL;
			$this->mapi[$p['business_address_country']] = NULL;
			$this->mapi[$p['other_address_street']] = NULL;
			$this->mapi[$p['other_address_city']] = NULL;
			$this->mapi[$p['other_address_state']] = NULL;
			$this->mapi[$p['other_address_postal_code']] = NULL;
			$this->mapi[$p['other_address_country']] = NULL;
			$nremails = array();
			$abprovidertype = 0;
			for ($i = 1; $i <= 3; $i++) {
				$this->mapi[$p["email_address_$i"]] = NULL;
				$this->mapi[$p["email_address_display_name_email_$i"]] = NULL;
				$this->mapi[$p["email_address_display_name_$i"]] = NULL;
				$this->mapi[$p["email_address_type_$i"]] = NULL;
				$this->mapi[$p["email_address_entryid_$i"]] = NULL;
			}
			$this->mapi[$p["address_book_mv"]] = NULL;
			$this->mapi[$p["address_book_long"]] = NULL;
			$this->mapi[$p['webpage']] = NULL;
			$this->mapi[$p['im']] = NULL;
			$this->mapi[$p['categories']] = NULL;
			$this->mapi['ContactPicture'] = NULL;
			$this->mapi[PR_HASATTACH] = false;
			$this->mapi[$p['has_picture']] = false;
		}
		
		// Name components
		if (isset($this->vcard->N)) {
			$this->logger->trace("N: " . $this->vcard->N);
			$parts = $this->vcard->N->getParts();

			$dump = print_r($parts, true);
			$this->logger->trace("Name info\n$dump");
			
			$this->mapi[$p['surname']]             = isset($parts[0]) ? $parts[0] : '';
			$this->mapi[$p['given_name']]          = isset($parts[1]) ? $parts[1] : '';
			$this->mapi[$p['middle_name']]         = isset($parts[2]) ? $parts[2] : '';
			$this->mapi[$p['display_name_prefix']] = isset($parts[3]) ? $parts[3] : '';
			$this->mapi[$p['generation']]          = isset($parts[4]) ? $parts[4] : '';
		}
		// Some VCard properties can be mapped 1:1 to MAPI properties:
		// Properties taken from http://en.wikipedia.org/wiki/Vcard
		$map = array
			( 'NICKNAME'       => 'nickname'
			, 'TITLE'          => 'title'
			, 'ROLE'           => 'profession'
			, 'OFFICE'         => 'office_location'
			, 'NOTE'           => 'notes'

			, 'X-MS-ASSISTANT' => 'assistant'
			, 'X-MS-MANAGER'   => 'manager_name'
			, 'X-MS-SPOUSE'    => 'spouse_name'

			, 'X-EVOLUTION-ASSISTANT' => 'assistant'
			, 'X-EVOLUTION-MANAGER'   => 'manager_name'
			, 'X-EVOLUTION-SPOUSE'    => 'spouse_name'

			, 'X-KADDRESSBOOK-X-AssistantsName' => 'assistant'
			, 'X-KADDRESSBOOK-X-ManagersName'   => 'manager_name'
			, 'X-KADDRESSBOOK-X-SpouseName'     => 'spouse_name'
			);

		// Use a 'foreach' because each property can exist zero, one or more times,
		// and because $this->vcard->select() returns an array:
		foreach ($map as $prop_vcard => $prop_mapi)
		{
			// If a property occurs more than once, we take the *first*
			// mention to be the most important:
			$already_set = FALSE;
			foreach ($this->vcard->select($prop_vcard) as $prop) {
				if ($already_set) {
					$this->logger->info(sprintf('Discarding %s with value "%s"; MAPI can store just one field.', $prop_vcard, $prop->getValue()));
					continue;
				}
				$this->mapi[$p[$prop_mapi]] = $prop->getValue();
				$already_set = TRUE;
			}
		}
		if (isset($this->vcard->ORG)) {
			$parts = $this->vcard->ORG->getParts();
			if (isset($parts[0])) $this->mapi[$p['company_name']] = $parts[0];
			if (isset($parts[1])) $this->mapi[$p['department_name']] = $parts[1];
		}
		// SORT-AS:
		// Do this here because we may need the value of $this->mapi[$p['fileas']] below:
		$this->sortAsConvert();

		if (isset($this->vcard->FN)) {
			$this->mapi[$p['display_name']] = $this->vcard->FN->getValue();
			$this->mapi[PR_SUBJECT] = $this->vcard->FN->getValue();
		}
		else {
			$this->mapi[$p['display_name']] = $this->mapi[$p['fileas']];
			$this->mapi[PR_SUBJECT] = $this->mapi[$p['fileas']];
		}
		// Dates:
		if (isset($this->vcard->bday)) {
			$time = new DateTime($this->vcard->bday->getValue());
			$this->mapi[$p['birthday']] = $time->format('U');
		}
		if (isset($this->vcard->anniversary)) {
			$time = new DateTime($this->vcard->anniversary->getValue());
			$this->mapi[$p['wedding_anniversary']] = $time->format('U');
		}
		// It's tempting to interpret REV: as a Unix timestamp, but don't; Evolution
		// is known to send MD5 hashes. Besides, it seems this value is overwritten by
		// the Zarafa backend when it writes the properties to the database.
		$this->mapi[$p['last_modification_time']] = time();

		// Telephone numbers:
		$this->phoneConvert();

		// RELATED fields:
		$this->relatedConvert();

		// Postal addresses:
		$this->addressConvert();

		// E-mail addresses:
		$this->emailConvert();

		// Instant messaging profiles:
		$this->instantMessagingConvert();

		// Websites:
		$this->websiteConvert();

		// Social media profiles:
		$this->socialProfileConvert();

		// Categories (multivalue):
		if (isset($this->vcard->categories)) $this->mapi[$p['categories']] = $this->vcard->categories->getParts();
		
		// Contact picture
		if (isset($this->vcard->photo)) {
			$type     = strtolower($this->vcard->photo['TYPE']->getValue());
			$encoding = strtolower($this->vcard->photo['ENCODING']->getValue());
			$content  = $this->vcard->photo->getValue();

			$this->logger->debug("Found contact picture type $type encoding $encoding");
			$this->photoConvert($content, $type, $encoding);
		}
		
		// Misc
		$this->mapi[$p["icon_index"]] = "512";		// Zarafa specific?

		return $this->mapi;
	}

	private function
	sortAsConvert ()
	{
		$fileas = FALSE;
		$p = $this->extendedProperties;

		$map = array
			( 'SORT-AS'
			, 'SORT-STRING'
			, 'X-EVOLUTION-FILE-AS'
			);

		// Check properties in map; first one wins:
		foreach ($map as $propname) {
			foreach ($this->vcard->select($propname) as $prop) {
				$fileas = $prop->getValue();
				break 2;
			}
		}
		// SORT-AS can also be given as a parameter to N:
		if (FALSE($fileas) && isset($this->vcard->N) && $this->vcard->N->offsetExists('SORT-AS')) {
			$fileas = $this->vcard->N->offsetGet('SORT-AS')->getValue();
		}
		// If not yet found, or override specified, derive:
		if (FALSE($fileas) || SAVE_AS_OVERRIDE_SORTAS) {
			$this->logger->trace('Empty sort-as or SAVE_AS_OVERRIDE_SORTAS set');

			$fileas = SAVE_AS_PATTERN;

			// Don't use $this->mapi[$p['display_name']]; hasn't been set yet, and can
			// be set by this very function in what would be a circular definition:
			$displayname = (isset($this->vcard->FN)) ? $this->vcard->FN->getValue() : '';

			// Do substitutions
			$substitutionKeys   = array('%d', '%l', '%f', '%c');
			$substitutionValues = array(
				$displayname,
				$this->mapi[$p['surname']],
				$this->mapi[$p['given_name']],
				$this->mapi[$p['company_name']]
			);
			$fileas = str_replace($substitutionKeys, $substitutionValues, $fileas);
		}
		$this->mapi[$p['fileas']] = $fileas;
	}

	private function
	addressConvert ()
	{
		$p = $this->extendedProperties;

		$map = array
			( 'HOME'  => 'home'
			, 'WORK'  => 'business'
			, 'OTHER' => 'other'
			);

		foreach ($this->vcard->select('ADR') as $addr)
		{
			if (count($types = $this->getTypes($addr)) === 0) {
			// TODO: These properties are the so-called mailing address. This address
			// appears, in Zarafa, to always be linked to one of the home/business/other
			// types (it's a checkbox you can set on any one of them). Until we do
			// further research, don't write any unique values to these fields:
			//	$pStreet  = 'street';
			//	$pCity    = 'city';
			//	$pState   = 'state';
			//	$pPCode   = 'postal_code';
			//	$pCountry = 'country';
				$this->logger->info('Ignoring address without type parameter');
				continue;
			}
			else {
				// OS X Contacts.app sends contacts back in the following form:
				//   ADR;type=HOME;type=pref:;;Main Street;Littleville;Arizona;AAA999;Denmark
				// Nice, multiple 'type' tags. Get them all:
				$type = FALSE;
				foreach ($types as $type => $dummy) {
					if (isset($map[$type])) {
						break;
					}
					$type = FALSE;
				}
				if (FALSE($type)) {
					$this->logger->info(sprintf('Ignoring address with unknown type(s) "%s"', $types->getValue()));
					continue;
				}
				$this->logger->debug("Found address '$type', mapping to '{$map[$type]}'");

				$pStreet  = "{$map[$type]}_address_street";
				$pCity    = "{$map[$type]}_address_city";
				$pState   = "{$map[$type]}_address_state";
				$pPCode   = "{$map[$type]}_address_postal_code";
				$pCountry = "{$map[$type]}_address_country";
			}
			$parts = $addr->getParts();
			$this->logger->trace("Address components:\n".print_r($parts, TRUE));

			$this->mapi[$p[$pStreet]]  = isset($parts[2]) ? $parts[2] : '';
			$this->mapi[$p[$pCity]]    = isset($parts[3]) ? $parts[3] : '';
			$this->mapi[$p[$pState]]   = isset($parts[4]) ? $parts[4] : '';
			$this->mapi[$p[$pPCode]]   = isset($parts[5]) ? $parts[5] : '';
			$this->mapi[$p[$pCountry]] = isset($parts[6]) ? $parts[6] : '';
		}
	}

	private function
	emailConvert ()
	{
		$p = $this->extendedProperties;

		// emails need to handle complementary properties plus create one off entries!
		$nremails = array();
		$abprovidertype = 0;
		$emailsDisplayName = $this->vcard->select('X-EMAIL-CN');		// emClient handles those
		$numMail = 0;

		if (is_array($emailsDisplayName)) {
			$emailsDisplayName = array_values($emailsDisplayName);
		}
		$dump = print_r($emailsDisplayName, true);
		$this->logger->trace("Display Names\n$dump");

		foreach ($this->vcard->select('EMAIL') as $email)
		{
			$numMail++;
			$displayName = '';
			$address = $email->getValue();

			// Zarafa only handles 3 mails:
			if ($numMail > 3) {
				$this->logger->debug(sprintf('Discarding e-mail address "%s"; Zarafa has only three e-mail slots and this is number %d', $address, $numMail));
				continue;
			}
			// Find display name:
			if ($xCn = $email['X-CN']) {
				$displayName = $xCn->getValue();
			}
			else if (count($emailsDisplayName) >= $numMail) {
				$displayName = $emailsDisplayName[$numMail - 1]->getValue();
			}
			else {
				$displayName = $this->vcard->fn->getValue();
			}
			$this->logger->debug("Found email $numMail : $displayName <$address>");

			// Create one-off entry:
			if (FALSE($oneoff = mapi_createoneoff($displayName, 'SMTP', $address))) {
				$this->logger->warn(sprintf('Failed to create one-off for "%s", reason: %s', $address, get_mapi_error_name()));
				continue;
			}
			$this->mapi[$p["email_address_$numMail"]] = $address;
			$this->mapi[$p["email_address_display_name_email_$numMail"]] = $address;
			$this->mapi[$p["email_address_display_name_$numMail"]] = $displayName;
			$this->mapi[$p["email_address_type_$numMail"]] = 'SMTP';
			$this->mapi[$p["email_address_entryid_$numMail"]] = $oneoff;

			// FIXME: doc: why populate an array(0 => 0, 1 => 1, 2 => 2) and so on?
			$nremails[] = $numMail - 1;

			// FIXME: doc: some kind of bitmask, what's the significance?
			$abprovidertype |= 2 ^ ($numMail - 1);
		}
		if ($numMail == 0) {
			return;
		}
		if (!empty($nremails)) {
			$this->mapi[$p['address_book_mv']] = $nremails;
		}
		$this->mapi[$p['address_book_long']] = $abprovidertype;
	}

	private function
	phoneConvert ()
	{
		$n_home_voice = 0;
		$n_work_voice = 0;
		foreach ($this->vcard->select('TEL') as $tel)
		{
			$pk = FALSE;
			$types = $this->getTypes($tel);

			if (isset($types['HOME'])) {
				if (isset($types['FAX'])) {
					$pk = 'home_fax_number';
				}
				else {
					if ($pref = $tel['PREF']) {
						$pk = ($pref->getValue() == '1')
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
					if ($pref = $tel['PREF']) {
						$pk = ($pref->getValue() == '1')
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

					// http://en.wikipedia.org/wiki/Vcard claims these
					// properties are sent by Evolution as TEL TYPE parameters:
					, 'X-EVOLUTION-RADIO'    => 'radio_telephone_number'
					, 'X-EVOLUTION-TELEX'    => 'telex_telephone_number'
					, 'X-EVOLUTION-TTYTDD'   => 'ttytdd_telephone_number'
					, 'X-EVOLUTION-CALLBACK' => 'callback_telephone_number'
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
			$this->mapi[$this->extendedProperties[$pk]] = $tel->getValue();
		}
	}

	private function
	photoConvert ($content, $type, $encoding)
	{
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
		$this->mapi['ContactPicture'] = $content;
		$this->mapi[PR_HASATTACH] = TRUE;
		$this->mapi[$this->extendedProperties['has_picture']] = TRUE;

		return TRUE;
	}

	private function
	relatedConvert ()
	{
		foreach ($this->vcard->select('RELATED') as $prop)
		{
			if (count($types = $this->getTypes($prop)) === 0) {
				$this->logger->info(sprintf('Ignoring RELATED property without TYPE parameter "%s"', $prop->getValue()));
				continue;
			}
			$pk = (isset($types['ASSISTANT'])) ? 'assistant'
			   : ((isset($types['MANAGER'])) ? 'manager_name'
			   : ((isset($types['SPOUSE'])) ? 'spouse_name'
			   : FALSE));

			if (FALSE($pk)) {
				$this->logger->info(sprintf('Ignoring RELATED property with unknown TYPE "%s"', implode('/', array_keys($types))));
				continue;
			}
			$this->mapi[$this->extendedProperties[$pk]] = $prop->getValue();
                }
	}

	private function
	instantMessagingConvert ()
	{
		// See also RFC 4770, http://tools.ietf.org/html/rfc4770
		// Observed from OSX Contacts.app:
		//   X-AIM;type=HOME;type=pref:aimname
		//   IMPP;X-SERVICE-TYPE=AIM;type=HOME;type=pref:aim:aimname
		//   IMPP;X-SERVICE-TYPE=Facebook;type=WORK:xmpp:facebookname
		// Zarafa sadly only has a single 'im' property.
		$elems = array();

		// Look at these properties in order; collect them all into an array
		// and implode them to a string when we're done.
		// Taken from http://en.wikipedia.org/wiki/VCard
		$map = array
			( 'IMPP'
			, 'X-AIM'
			, 'X-ICQ'
			, 'X-GOOGLE-TALK'
			, 'X-JABBER'
			, 'X-MSN'
			, 'X-YAHOO'
			, 'X-TWITTER'
			, 'X-SKYPE'
			, 'X-SKYPE-USERNAME'
			, 'X-GADUGADU'
			, 'X-MS-IMADDRESS'
			, 'X-KADDRESSBOOK-X-IMAddress'
			) ;

		foreach ($map as $propname) {
			foreach ($this->vcard->select($propname) as $prop)
			{
				$elem = $prop->getValue();
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
		}
		if (count($elems) === 0) {
			return;
		}
		$val = array();
		foreach ($elems as $e) {
			$val[] = $e[2];
		}
		$this->mapi[$this->extendedProperties['im']] = implode(';', $val);
	}

	private function
	websiteConvert ()
	{
		$val = array();

		$map = array
			( 'URL'
			, 'X-EVOLUTION-BLOG-URL'
			, 'X-KADDRESSBOOK-BlogFeed'
			) ;

		foreach ($map as $propname) {
			foreach ($this->vcard->select($propname) as $prop) {
				$val[] = $prop->getValue();
			}
		}
		if (count($val) > 0) {
			$this->mapi[$this->extendedProperties['webpage']] = implode(';', $val);
		}
	}

	private function
	socialProfileConvert ()
	{
		foreach ($this->vcard->select('X-SOCIALPROFILE') as $prop)
		{
			if (count($types = $this->getTypes($prop)) === 0) {
				$this->logger->trace(sprintf('Ignoring social profile with value "%s"', $prop->getValue()));
				continue;
			}
			// Possibly do something with the types and objects here.
			// Observed strings passed by OSX Contacts:
			//   X-SOCIALPROFILE;type=twitter:http://twitter.com/name
			//   X-SOCIALPROFILE;type=facebook:http://facebook.com/name
			//   X-SOCIALPROFILE;type=flickr:http://www.flickr.com/photos/name
			//   X-SOCIALPROFILE;type=linkedin:http://www.linkedin.com/in/name
			//   X-SOCIALPROFILE;type=myspace:http://www.myspace.com/name
			//   X-SOCIALPROFILE;type=sinaweibo:http://weibo.com/n/name

			$this->logger->trace(sprintf('Ignoring social profile at "%s" with value "%s"', implode('/', array_keys($types)), $prop->getValue()));
		}
	}

	private function
	getTypes ($prop)
	{
		if ($offset = $prop['TYPE']) {
			return array_flip(array_map('strtoupper', $offset->getParts()));
		}
		return array();
	}
}
