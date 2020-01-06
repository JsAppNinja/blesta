<?php
/**
 * Billing Overview plugin handler
 * 
 * @package blesta
 * @subpackage blesta.plugins.billing_overview
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class BillingOverviewPlugin extends Plugin {

	/**
	 * @var string The version of this plugin
	 */
	private static $version = "1.3.2";
	/**
	 * @var string The authors of this plugin
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	
	public function __construct() {
		Language::loadLang("billing_overview_plugin", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this plugin
	 *
	 * @return string The common name of this plugin
	 */
	public function getName() {
		return Language::_("BillingOverviewPlugin.name", true);	
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
			
		// Add the billing overview table, *IFF* not already added
		try {
			// billing_overview_settings
			$this->Record->
				setField("staff_id", array('type'=>"int",'size'=>10,'unsigned'=>true,'auto_increment'=>false))->
				setField("company_id", array('type'=>"int",'size'=>10,'unsigned'=>true,'auto_increment'=>false))->
				setField("key", array('type'=>"varchar",'size'=>255))->
				setField("value", array('type'=>"varchar",'size'=>255))->
				setField("order", array('type'=>"int",'size'=>5,'default'=>0))->
				setKey(array("staff_id", "company_id", "key"), "primary")->
				create("billing_overview_settings", true);
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
			// Handle the upgrade, set errors using $this->Input->setErrors() if any errors encountered
			if (!isset($this->Record))
				Loader::loadComponents($this, array("Record"));
			
			// Upgrade to 1.1.0
			if (version_compare($current_version, "1.1.0", "<")) {
				Loader::loadModels($this, array("BillingOverview.BillingOverviewSettings"));
				
				// Update the order of the settings to make room for 2 more settings
				$this->Record->set("order", "`order`+1", false, false)->where("order", ">", 11)->update("billing_overview_settings");
				$this->Record->set("order", "`order`+1", false, false)->where("order", ">", 2)->update("billing_overview_settings");
				
				// Fetch each staff member
				$staff = $this->Record->select(array("staff_id", "company_id"))->from("billing_overview_settings")->
					group(array("company_id", "staff_id"))->getStatement();
				
				// Update each staff member's settings to include the new revenue_year setting
				foreach ($staff as $member) {
					// Add the revenue_year setting
					$vars = array(
						'staff_id' => $member->staff_id,
						'company_id' => $member->company_id,
						'key' => "revenue_year",
						'value' => 1,
						'order' => 3
					);
					$this->Record->insert("billing_overview_settings", $vars);
					
					// Add the graph_revenue_year setting
					$vars['key'] = "graph_revenue_year";
					$vars['order'] = 13;
					$this->Record->insert("billing_overview_settings", $vars);
				}
			}
			
			// Upgrade to 1.3.0
			if (version_compare($current_version, "1.3.0", "<")) {
				$this->upgrade1_3_0();
			}
		}
		
	}
	
	/**
	 * Update to v1.3.0
	 */
	private function upgrade1_3_0() {
		Loader::loadModels($this, array("BillingOverview.BillingOverviewSettings"));
		
		// Update the order of the settings to make room for 3 more settings
		$this->Record->set("order", "`order`+3", false, false)->where("order", ">", 3)->update("billing_overview_settings");
		
		// Fetch each staff member
		$staff = $this->Record->select(array("staff_id", "company_id"))->from("billing_overview_settings")->
			group(array("company_id", "staff_id"))->getStatement();
		
		// Each new setting and its order
		$settings = array('credits_today' => 4, 'credits_month' => 5, 'credits_year' => 6);
		
		// Updat each staff member's settings to include the new credit settings
		foreach ($staff as $member) {
			// Add each new setting
			foreach ($settings as $setting => $order) {
				$vars = array(
					'staff_id' => $member->staff_id,
					'company_id' => $member->company_id,
					'key' => $setting,
					'value' => 0,
					'order' => $order
				);
				
				$this->Record->insert("billing_overview_settings", $vars);
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
		
		// Remove all billing_overview tables *IFF* no other company in the system is using this plugin
		if ($last_instance) {
			$this->Record->drop("billing_overview_settings");
		}
	}
	
	/**
	 * Returns all actions to be configured for this widget (invoked after install() or upgrade(), overwrites all existing actions)
	 *
	 * @return array A numerically indexed array containing:
	 * 	- action The action to register for
	 * 	- uri The URI to be invoked for the given action
	 * 	- name The name to represent the action (can be language definition)
	 */
	public function getActions() {
		return array(
			array(
				'action'=>"widget_staff_billing",
				'uri'=>"widget/billing_overview/admin_main/",
				'name'=>Language::_("BillingOverviewPlugin.name", true)
			)
		);
	}
}
?>