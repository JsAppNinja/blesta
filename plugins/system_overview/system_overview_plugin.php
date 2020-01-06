<?php
/**
 * System Overview plugin handler
 * 
 * @package blesta
 * @subpackage blesta.plugins.system_overview
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class SystemOverviewPlugin extends Plugin {

	/**
	 * @var string The version of this plugin
	 */
	private static $version = "1.2.1";
	/**
	 * @var string The authors of this plugin
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	
	public function __construct() {
		Language::loadLang("system_overview_plugin", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this plugin
	 *
	 * @return string The common name of this plugin
	 */
	public function getName() {
		return Language::_("SystemOverviewPlugin.name", true);	
	}
	
	/**
	 * Returns the version of this plugin
	 *
	 * @return string The current version of this plugin
	 */
	public function getVersion() {
		return self::$version;
	}

	/**
	 * Returns the name and URL for the authors of this plugin
	 *
	 * @return array The name and URL of the authors of this plugin
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Performs any necessary bootstraping actions
	 *
	 * @param int $plugin_id The ID of the plugin being installed
	 */
	public function install($plugin_id) {
		if (!isset($this->Record))
			Loader::loadComponents($this, array("Input", "Record"));
			
		// Add the system overview table, *IFF* not already added
		try {
			// system_overview_settings
			$this->Record->
				setField("staff_id", array('type'=>"int",'size'=>10,'unsigned'=>true,'auto_increment'=>false))->
				setField("company_id", array('type'=>"int",'size'=>10,'unsigned'=>true,'auto_increment'=>false))->
				setField("key", array('type'=>"varchar",'size'=>255))->
				setField("value", array('type'=>"varchar",'size'=>255))->
				setField("order", array('type'=>"int",'size'=>5,'default'=>0))->
				setKey(array("staff_id", "company_id", "key"), "primary")->
				create("system_overview_settings", true);
		}
		catch(Exception $e) {
			// Error adding... no permission?
			$this->Input->setErrors(array('db'=> array('create'=>$e->getMessage())));
			return;
		}
	}
	
	/**
	 * Performs migration of data from $current_version (the current installed version)
	 * to the given file set version
	 *
	 * @param string $current_version The current installed version of this plugin
	 * @param int $plugin_id The ID of the plugin being upgraded
	 */
	public function upgrade($current_version, $plugin_id) {
		
		// Upgrade if possible
		if (version_compare($this->getVersion(), $current_version, ">")) {
			if (!isset($this->Record))
				Loader::loadComponents($this, array("Input", "Record"));
			
			// Handle the upgrade, set errors using $this->Input->setErrors() if any errors encountered
			
			// Upgrade from 1.0.0 -> 1.1.0
			if ($current_version == "1.0.0") {
				// Get all staff
				$settings = $this->Record->select(array("staff_id", "company_id"))->
					from("system_overview_settings")->
					group(array("staff_id", "company_id"))->
					getStatement();
				
				// Update all staff overview settings to include the new services_scheduled_cancellation setting
				// and adjust the order of all the other settings
				$this->Record->begin();
				
				// Update the order for all staff for all companies
				// Escape order since it's a keyword
				$this->Record->set("order", "`order`+1", false, false)->
					where("order", ">=", 4)->update("system_overview_settings");
				
				// Add the new setting
				$fields = array("staff_id", "company_id", "key", "value", "order");
				while (($setting = $settings->fetch())) {
					$vars = array(
						'staff_id' => $setting->staff_id,
						'company_id' => $setting->company_id,
						'key' => "services_scheduled_cancellation",
						'value' => 0,
						'order' => 4
					);
					$this->Record->insert("system_overview_settings", $vars, $fields);
				}
				
				$this->Record->commit();
			}
		}
	}
	
	/**
	 * Performs any necessary cleanup actions
	 *
	 * @param int $plugin_id The ID of the plugin being uninstalled
	 * @param boolean $last_instance True if $plugin_id is the last instance across all companies for this plugin, false otherwise
	 */
	public function uninstall($plugin_id, $last_instance) {
		if (!isset($this->Record))
			Loader::loadComponents($this, array("Record"));
		
		// Remove all system_overview tables *IFF* no other company in the system is using this plugin
		if ($last_instance) {
			$this->Record->drop("system_overview_settings");
		}
	}
	
	/**
	 * Returns all actions to be configured for this widget (invoked after install() or upgrade(), overwrites all existing actions)
	 *
	 * @return array A numerically indexed array containing:
	 * 	-action The action to register for
	 * 	-uri The URI to be invoked for the given action
	 * 	-name The name to represent the action (can be language definition)
	 */
	public function getActions() {
		return array(
			array(
				'action'=>"widget_staff_home",
				'uri'=>"widget/system_overview/admin_main/",
				'name'=>Language::_("SystemOverviewPlugin.name", true)
			)
		);
	}
}
?>