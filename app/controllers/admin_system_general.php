<?php
/**
 * Admin System General Settings
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminSystemGeneral extends AppController {
	
	public function preAction() {
		parent::preAction();		
		
		// Require login
		$this->requireLogin();		
		
		$this->uses(array("Navigation", "Settings", "Transactions"));
		$this->components(array("SettingsCollection"));
		
		Language::loadLang("admin_system_general");
		
		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getSystem($this->base_uri))));
	}
	
	// General settings
	public function index() {
		$this->redirect($this->base_uri . "settings/system/general/basic/");
	}
	
	/**
	 * Basic settings
	 */
	public function basic() {
		// Update basic settings
		if (!empty($this->post)) {
			// Set updatable fields
			$fields = array('log_days' => "", 'temp_dir' => "", 'uploads_dir' => "", 'root_web_dir' => "");
			$data = array_intersect_key($this->post, $fields);
			
			// Set trailing slashes if missing
			if (!empty($data['temp_dir']) && substr($data['temp_dir'], -1, 1) != DS)
				$data['temp_dir'] .= DS;
			if (!empty($data['uploads_dir']) && substr($data['uploads_dir'], -1, 1) != DS)
				$data['uploads_dir'] .= DS;
			if (!empty($data['root_web_dir']) && substr($data['root_web_dir'], -1, 1) != DS)
				$data['root_web_dir'] .= DS;
			
			// Update the settings
			$this->Settings->setSettings($data, array_keys($fields));
			
			$this->setMessage("message", Language::_("AdminSystemGeneral.!success.basic_updated", true));
		}
		
		// Get all settings
		$settings = $this->SettingsCollection->fetchSystemSettings($this->Settings);
		
		// Check if directories are writable and set them accordingly
		$dirs_writable = array(
			'temp_dir'=>false,
			'uploads_dir'=>false
		);
		
		if (isset($settings['temp_dir'])) {
			if (is_dir($settings['temp_dir']) && is_writable($settings['temp_dir']))
				$dirs_writable['temp_dir'] = true;
		}
		if (isset($settings['uploads_dir'])) {
			if (is_dir($settings['uploads_dir']) && is_writable($settings['uploads_dir']))
				$dirs_writable['uploads_dir'] = true;
		}
		
		// Set rotation policy drop-down for module logs
		$log_days = array();
		$log_days[1] = "1 " . Language::_("AdminSystemGeneral.basic.text_day", true);
		for ($i=2; $i<=90; $i++)
			$log_days[$i] = $i . " " . Language::_("AdminSystemGeneral.basic.text_days", true);
		$log_days['never'] = Language::_("AdminSystemGeneral.basic.text_no_log", true);
		
		$this->set("log_days", $log_days);
		$this->set("dirs_writable", $dirs_writable);
		$this->set("vars", $settings);		
	}
	
	/**
	 * General GeoIP Settings page
	 */
	public function geoIp() {
		$vars = array();
		$settings = $this->SettingsCollection->fetchSystemSettings($this->Settings);
		
		if (!empty($this->post)) {
			// Set geoip enabled field if not given
			if (empty($this->post['geoip_enabled']))
				$this->post['geoip_enabled'] = "false";
			
			if ($this->post['geoip_enabled'] == "true" && !extension_loaded('mbstring')) {
				$this->setMessage("error", Language::_("AdminSystemGeneral.!error.geoip_mbstring_required", true));
			}
			else {
				$this->Settings->setSettings($this->post, array("geoip_enabled"));
				
				$this->setMessage("message", Language::_("AdminSystemGeneral.!success.geoip_updated", true));
			}
			$vars = $this->post;
		}
		
		// Set GeoIP settings
		if (empty($vars))
			$vars = $settings;
		
		// Set whether the GeoIP database exists or not
		$geoip_database_filename = "GeoLiteCity.dat";
		$geoip_database_exists = false;
		if (isset($settings['uploads_dir'])) {
			if (file_exists($settings['uploads_dir'] . "system" . DS . $geoip_database_filename))
				$geoip_database_exists = true;
			
			$this->set("uploads_dir", $settings['uploads_dir']);
		}
		
		$this->set("geoip_database_filename", $geoip_database_filename);
		$this->set("geoip_database_exists", $geoip_database_exists);
		$this->set("vars", $vars);
	}
	
	/**
	 * General Maintenance Settings page
	 */
	public function maintenance() {				
		$vars = array();
		
		if (!empty($this->post)) {
			// Set maintenance mode if not given
			if (empty($this->post['maintenance_mode']))
				$this->post['maintenance_mode'] = "false";
			
			$fields = array("maintenance_reason", "maintenance_mode");
			$this->Settings->setSettings($this->post, $fields);
			
			$this->setMessage("message", Language::_("AdminSystemGeneral.!success.maintenance_updated", true));
		}
		
		if (empty($vars))
			$vars = $this->SettingsCollection->fetchSystemSettings($this->Settings);
		
		$this->set("vars", $vars);
	}
	
	/**
	 * General License Settings page
	 */
	public function license() {
		$this->uses(array("License"));
		$vars = array();
		
		if (!empty($this->post) && isset($this->post['license_key'])) {
			
			$this->License->updateLicenseKey($this->post['license_key']);
			
			if (($errors = $this->License->errors()))
				$this->setMessage("error", $errors);
			else
				$this->setMessage("message", Language::_("AdminSystemGeneral.!success.license_updated", true));
		}
		
		if (empty($vars))
			$vars = $this->SettingsCollection->fetchSystemSettings($this->Settings);
		
		$this->set("vars", $vars);
	}
	
	/**
	 * Payment Types settings
	 */
	public function paymentTypes() {
		$this->set("types", $this->Transactions->getTypes());
		$this->set("debit_types", $this->Transactions->getDebitTypes());
	}
	
	/**
	 * Add a payment type
	 */
	public function addType() {
		// Add a payment type
		if (!empty($this->post)) {
			$type_id = $this->Transactions->addType($this->post);
			
			if (($errors = $this->Transactions->errors())) {
				// Error, reset vars
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success
				$payment_type = $this->Transactions->getType($type_id);
				$this->flashMessage("message", Language::_("AdminSystemGeneral.!success.addtype_created", true, array($payment_type->real_name)));
				$this->redirect($this->base_uri . "settings/system/general/paymenttypes/");
			}
		}
		
		if (empty($vars))
			$vars = new stdClass();
		
		$this->set("vars", $vars);
		$this->set("types", $this->Transactions->getDebitTypes());
	}
	
	/**
	 * Edit a payment type
	 */
	public function editType() {
		if (!isset($this->get[0]) || !($type = $this->Transactions->getType((int)$this->get[0])))
			$this->redirect($this->base_uri . "settings/system/general/paymenttypes/");
		
		// Add a payment type
		if (!empty($this->post)) {
			// Set empty checkbox
			if (empty($this->post['is_lang']))
				$this->post['is_lang'] = "0";
			
			$this->Transactions->editType($type->id, $this->post);
			
			if (($errors = $this->Transactions->errors())) {
				// Error, reset vars
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success
				$payment_type = $this->Transactions->getType($type->id);
				$this->flashMessage("message", Language::_("AdminSystemGeneral.!success.edittype_updated", true, array($payment_type->real_name)));
				$this->redirect($this->base_uri . "settings/system/general/paymenttypes/");
			}
		}
		
		if (empty($vars))
			$vars = $this->Transactions->getType($type->id);
		
		$this->set("vars", $vars);
		$this->set("types", $this->Transactions->getDebitTypes());
	}
	
	/**
	 * Delete a payment type
	 */
	public function deleteType() {
		if (!isset($this->post['id']) || !($type = $this->Transactions->getType((int)$this->post['id'])))
			$this->redirect($this->base_uri . "settings/system/general/paymenttypes/");
		
		// Delete the payment type
		$this->Transactions->deleteType($type->id);
		
		$this->flashMessage("message", Language::_("AdminSystemGeneral.!success.deletetype_deleted", true, array($type->real_name)));
		$this->redirect($this->base_uri . "settings/system/general/paymenttypes/");
	}
}
?>