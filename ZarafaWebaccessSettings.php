<?php
/*
 * Copyright 2013 Bokxing IT, http://www.bokxing-it.nl
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

class Zarafa_Webaccess_Settings
{
	private $logger = false;
	private $handle = false;
	private $settings = false;

	public function
	__construct ($private_store_handle)
	{
		$this->logger = new Zarafa_Logger(__CLASS__);

		// Need a handle to the private store in order to do our lookups:
		$this->handle = $private_store_handle;
	}

	public function
	by_path ($path)
	{
		$this->logger->trace(__FUNCTION__);

		if (($settings = $this->get_settings()) === false) {
			return false;
		}
		if (!isset($settings['settings'])) {
			return false;
		}
		$path = explode('/', $path);
		$tmp = $settings['settings'];

		foreach ($path as $pointer) {
			if (empty($pointer)) {
				continue;
			}
			if (!isset($tmp[$pointer])) {
				return false;
			}
			$tmp = $tmp[$pointer];
		}
		return $tmp;
	}

	private function
	get_settings ()
	{
		$this->logger->trace(__FUNCTION__);

		// Return cached version if available:
		if ($this->settings !== false) {
			return $this->settings;
		}
		if ($this->handle === false) {
			$this->logger->warn(__FUNCTION__.': no handle to private store');
			return false;
		}
		// First check if property exist and we can open that using mapi_openproperty():
		if (($storeProps = mapi_getprops($this->handle, array(PR_EC_WEBACCESS_SETTINGS_JSON))) === false) {
			$this->logger->warn(__FUNCTION__.': failed to get store properties');
			return false;
		}
		if (!(isset($storeProps[PR_EC_WEBACCESS_SETTINGS_JSON]) || propIsError(PR_EC_WEBACCESS_SETTINGS_JSON, $storeProps) == MAPI_E_NOT_ENOUGH_MEMORY)) {
			$this->logger->warn(__FUNCTION__.': failed to find PR_EC_WEBACCESS_SETTINGS_JSON property');
			return false;
		}
		// Read the settings property:
		if (($stream = mapi_openproperty($this->handle, PR_EC_WEBACCESS_SETTINGS_JSON, IID_IStream, 0, 0)) === false) {
			$this->logger->warn(__FUNCTION__.': failed to open stream');
			return false;
		}
		$stat = mapi_stream_stat($stream);

		if (mapi_stream_seek($stream, 0, STREAM_SEEK_SET) === false) {
			$this->logger->warn(__FUNCTION__.': failed to seek stream');
			return false;
		}
		$settings_string = '';
		for ($i = 0; $i < $stat['cb']; $i += 1024) {
			$settings_string .= mapi_stream_read($stream, 1024);
		}
		$settings = json_decode($settings_string, true);

		if (!is_array($settings) || !isset($settings['settings']) || !is_array($settings['settings'])) {
			$this->logger->warn(__FUNCTION__.': failed to decode JSON settings string');
			return false;
		}
		return $this->settings = $settings;
	}
}
