<?php
/**
 * Admin System Automation Settings
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminSystemAutomation extends AppController {
	
	public function preAction() {
		parent::preAction();		
		
		// Require login
		$this->requireLogin();		
		
		$this->uses(array("Logs", "Navigation", "Settings"));
		$this->components(array("SettingsCollection"));
		$this->helpers(array("DataStructure"));
		
		$this->ArrayHelper = $this->DataStructure->create("Array");
		
		Language::loadLang("admin_system_automation");
		
		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getSystem($this->base_uri))));
	}
	
	/**
	 * Automation settings
	 */
	public function index() {
		$vars = array();
		$error = false;
		
		// Update the cron key
		if (!empty($this->post)) {
			// Check that a non-empty string was given
			if (empty($this->post['cron_key'])) {
				// Error, a key must be set
				$vars = (object)$this->post;
				$error = true;
				$this->setMessage("error", Language::_("AdminSystemAutomation.!error.empty_cron_key", true));
			}
			else {
				// Success, update cron key
				$this->Settings->setSetting("cron_key", $this->post['cron_key'], true);
				$this->flashMessage("message", Language::_("AdminSystemAutomation.!success.cron_key", true));
				$this->redirect($this->base_uri . "settings/system/automation/");
			}
		}
		
		// Set the time that the cron has last run
		if (($cron_last_ran = $this->Logs->getSystemCronLastRun()))
			$cron_last_ran = $cron_last_ran->end_date;
		
		// Set cron icon to active if the cron has run within the past 24 hours
		$icon = "exclamation";
		if ($cron_last_ran !== false && (($this->Date->toTime($cron_last_ran) + 86400) > $this->Date->toTime($this->Logs->dateToUtc(date("c")))))
			$icon = "active";
		
		$system_settings = $this->SettingsCollection->fetchSystemSettings();
		
		// Get the cron key
		if (!isset($system_settings['cron_key']))
			$cron_key = $this->createCronKey();
		else
			$cron_key = $system_settings['cron_key'];
		
		// Set current cron key
		if (empty($vars)) {
			$vars = new stdClass();
			$vars->cron_key = $cron_key;
		}
		
		// Show the cron key by default if there is an error
		$this->set("show_cron_key", $error);
		$this->set("vars", $vars);
		$this->set("cron_command", "*/5 * * * * /usr/bin/php " . ROOTWEBDIR . "index.php cron");
		$this->set("cron_icon", $icon);
		$this->set("cron_last_ran", $cron_last_ran);
	}
	
	/**
	 * Creates and saves a system cron key
	 *
	 * @return string The cron key generated
	 */
	private function createCronKey() {
		$this->StringHelper = $this->DataStructure->create("String");
		
		// Generate a random key with the following options
		$cron_key = $this->StringHelper->random();
		
		// Update the cron key setting
		$this->Settings->setSetting("cron_key", $cron_key, true);
		
		return $cron_key;
	}
}
?>