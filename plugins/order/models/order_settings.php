<?php
/**
 * Order Settings
 *
 * Manage all order settings for the plugin
 *
 * @package blesta
 * @subpackage blesta.plugins.order.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class OrderSettings extends OrderModel {
	
	/**
	 * Fetches all order settings
	 *
	 * @param int $company_id The company ID
	 * @return mixed An array of objects with key/value pairs of settings, false if no results found
	 */
	public function getSettings($company_id) {
		$settings = $this->Record->select(array("key", "value", "encrypted"))->
			from("order_settings")->
			where("company_id", "=", $company_id)->
			fetchAll();
		
		// Decrypt values where necessary
		for($i=0; $i<count($settings); $i++) {
			if ($settings[$i]->encrypted)
				$settings[$i]->value = $this->systemDecrypt($settings[$i]->value);
		}
		return $settings;
	}

	/**
	 * Fetch a single setting by key name
	 *
	 * @param int $company_id The company ID
	 * @param string $key The key name of the setting to fetch
	 * @return mixed An stdObject containg the key and value, false if no such key exists
	 */
	public function getSetting($company_id, $key) {
		$setting = $this->Record->select(array("key", "value", "encrypted"))->
			from("order_settings")->
			where("company_id", "=", $company_id)->
			where("key", "=", $key)->fetch();
			
		if ($setting && $setting->encrypted)
			$setting->value = $this->systemDecrypt($setting->value);
		return $setting;
	}
	
	/**
	 * Sets a group of settings with key/value pairs
	 *
	 * @param int $company_id The company ID
	 * @param array $settings Settings to set as key/value pairs
	 * @see Settings::setSetting()
	 */
	public function setSettings($company_id, array $settings) {
		foreach ($settings as $key => $value)
			$this->setSetting($company_id, $key, $value);
	}
	
	/**
	 * Sets the setting with the given key, overwriting any existing value with that key
	 *
	 * @param int $company_id The company ID
	 * @param string $key The setting identifier
	 * @param string $value The value to set for this setting
	 * @param mixed $encrypted True to encrypt $value, false to store unencrypted, null to encrypt if currently set to encrypt
	 */
	public function setSetting($company_id, $key, $value, $encrypted=null) {
		$fields = array('company_id' => $company_id, 'key'=>$key, 'value'=>$value);
		
		// If encryption is mentioned set the appropriate value and encrypt if necessary
		if ($encrypted !== null) {
			$fields['encrypted'] = (int)$encrypted;
			if ($encrypted)
				$fields['value'] = $this->systemEncrypt($fields['value']);
		}
		// Check if the value is currently encrypted and encrypt if necessary
		else {
			$setting = $this->getSetting($company_id, $key);
			if ($setting && $setting->encrypted) {
				$fields['encrypted'] = 1;
				$fields['value'] = $this->systemEncrypt($fields['value']);
			}
		}
		
		$this->Record->duplicate("value", "=", $fields['value'])->
			insert("order_settings", $fields);
	}
	
	/**
	 * Unsets a setting from the order settings.
	 *
	 * @param int $company_id The company ID
	 * @param string $key The setting to unset
	 */
	public function unsetSetting($company_id, $key) {
		$this->Record->from("order_settings")->where("company_id", "=", $company_id)->
			where("key", "=", $key)->delete();
	}
	
	/**
	 * Fetches all antifraud components available
	 *
	 * @return array An array of key/value pairs where each key is the antifraud component class and each value is its name
	 */
	public function getAntifraud() {
		$antifrauds = array();
		
		$antifraud_dir = realpath(dirname(__FILE__) . DS . ".." . DS) . DS . "components" . DS . "antifraud";
		$dir = opendir($antifraud_dir);
		while (false !== ($antifraud = readdir($dir))) {
			// If the file is not a hidden file, and is a directory, accept it
			if ($antifraud != "lib" && substr($antifraud, 0, 1) != "." && is_dir($antifraud_dir . DS . $antifraud)) {
				$antifrauds[$antifraud] = Loader::toCamelCase($antifraud);
			}
		}
		return $antifrauds;
	}
}
?>