<?php
/**
 * Order System plugin handler
 * 
 * @package blesta
 * @subpackage blesta.plugins.order
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class OrderPlugin extends Plugin {
	
	/**
	 * Construct
	 */
	public function __construct() {
		Language::loadLang("order_plugin", null, dirname(__FILE__) . DS . "language" . DS);
		
		// Load components required by this plugin
		Loader::loadComponents($this, array("Input", "Record"));
		
		$this->loadConfig(dirname(__FILE__) . DS . "config.json");
	}

	/**
	 * Performs any necessary bootstraping actions
	 *
	 * @param int $plugin_id The ID of the plugin being installed
	 */
	public function install($plugin_id) {
		
		Loader::loadModels($this, array("CronTasks", "Emails", "EmailGroups", "Languages", "Permissions"));
		Configure::load("order", dirname(__FILE__) . DS . "config" . DS);
		
		try {
			// order_forms
			$this->Record->
				setField("id", array('type' => "int", 'size' => 10, 'unsigned' => true, 'auto_increment' => true))->
				setField("company_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("label", array('type' => "varchar", 'size' => 32))->
				setField("name", array('type' => "varchar", 'size' => 128))->
				setField("template", array('type' => "varchar", 'size' => 64))->
				setField("template_style", array('type' => "varchar", 'size' => 64))->
				setField("type", array('type' => "varchar", 'size' => 64))->
				setField("client_group_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("manual_review", array('type' => "tinyint", 'size' => 1, 'default' => 0))->
				setField("allow_coupons", array('type' => "tinyint", 'size' => 1, 'default' => 0))->
				setField("require_ssl", array('type' => "tinyint", 'size' => 1, 'default' => 0))->
				setField("require_captcha", array('type' => "tinyint", 'size' => 1, 'default' => 0))->
				setField("require_tos", array('type' => "tinyint", 'size' => 1, 'default' => 0))->
				setField("tos_url", array('type' => "varchar", 'size' => 255, 'is_null' => true, 'default' => null))->
				setField("status", array('type' => "enum", 'size' => "'active','inactive'", 'default' => "active"))->
				setField("date_added", array('type' => "datetime"))->
				setKey(array("id"), "primary")->
				setKey(array("label", "company_id"), "unique")->
				setKey(array("status"), "index")->
				setKey(array("company_id"), "index")->
				create("order_forms", true);
			
			// order_form_groups
			$this->Record->
				setField("order_form_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("package_group_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setKey(array("order_form_id", "package_group_id"), "primary")->
				create("order_form_groups", true);
			
			// order_form_meta
			$this->Record->
				setField("order_form_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("key", array('type' => "varchar", 'size' => 32))->
				setField("value", array('type' => "text"))->
				setKey(array("order_form_id", "key"), "primary")->
				create("order_form_meta", true);
				
			// order_form_currencies
			$this->Record->
				setField("order_form_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("currency", array('type' => "char", 'size' => 3))->
				setKey(array("order_form_id", "currency"), "primary")->
				create("order_form_currencies", true);
				
			// order_form_gateways
			$this->Record->
				setField("order_form_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("gateway_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setKey(array("order_form_id", "gateway_id"), "primary")->
				create("order_form_gateways", true);
				
			// order_staff_settings
			$this->Record->
				setField("staff_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("company_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("key", array('type' => "varchar", 'size' => 32))->
				setField("value", array('type' => "text"))->
				setKey(array("staff_id", "company_id", "key"), "primary")->
				create("order_staff_settings", true);
			
			// order_settings
			$this->Record->
				setField("company_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("key", array('type' => "varchar", 'size' => 32))->
				setField("value", array('type' => "text"))->
				setField("encrypted", array('type' => "tinyint", 'size' => 1, 'default' => 0))->
				setKey(array("key", "company_id"), "primary")->
				create("order_settings", true);
				
			// orders
			$this->Record->
				setField("id", array('type' => "int", 'size' => 10, 'unsigned' => true, 'auto_increment' => true))->
				setField("order_number", array('type' => "varchar", 'size' => 16))->
				setField("order_form_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("invoice_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("fraud_report", array('type' => "text", 'is_null' => true, 'default' => null))->
				setField("fraud_status", array('type' => "enum", 'size' => "'allow','review','reject'", 'is_null' => true, 'default' => null))->
				setField("status", array('type' => "enum", 'size' => "'pending','accepted','fraud','canceled'", 'default' => "pending"))->
				setField("date_added", array('type' => "datetime"))->
				setKey(array("id"), "primary")->
				setKey(array("order_number"), "unique")->
				setKey(array("order_form_id"), "index")->
				setKey(array("invoice_id"), "index")->
				setKey(array("status"), "index")->
				create("orders", true);
			
			// order_services
			$this->Record->
				setField("order_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("service_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setKey(array("order_id", "service_id"), "primary")->
				create("order_services", true);
		}
		catch (Exception $e) {
			// Error adding... no permission?
			$this->Input->setErrors(array('db' => array('create' => $e->getMessage())));
			return;
		}
		

		// Add a cron task so we can check for incoming email tickets
		$task = array(
			'key' => "accept_paid_orders",
			'plugin_dir' => "order",
			'name' => Language::_("OrderPlugin.cron.accept_paid_orders_name", true),
			'description' => Language::_("OrderPlugin.cron.accept_paid_orders_desc", true),
			'type' => "interval"
		);
		$task_id = $this->CronTasks->add($task);
		
		if (!$task_id) {
			$cron_task = $this->CronTasks->getByKey($task['key'], $task['plugin_dir']);
			if ($cron_task)
				$task_id = $cron_task->id;
		}
		
		if ($task_id) {
			$this->CronTasks->addTaskRun($task_id, array(
				'interval' => 5,
				'enabled' => 1
			));
		}
		
		// Fetch all currently-installed languages for this company, for which email templates should be created for
		$languages = $this->Languages->getAll(Configure::get("Blesta.company_id"));
		
		// Add all email templates
		$emails = Configure::get("Order.install.emails");
		foreach ($emails as $email) {
			$group = $this->EmailGroups->getByAction($email['action']);
			if ($group)
				$group_id = $group->id;
			else {
				$group_id = $this->EmailGroups->add(array(
					'action' => $email['action'],
					'type' => $email['type'],
					'plugin_dir' => $email['plugin_dir'],
					'tags' => $email['tags']
				));
			}
			
			// Set from hostname to use that which is configured for the company
			if (isset(Configure::get("Blesta.company")->hostname))
				$email['from'] = str_replace("@mydomain.com", "@" . Configure::get("Blesta.company")->hostname, $email['from']);
			
			// Add the email template for each language
			foreach ($languages as $language) {
				$this->Emails->add(array(
					'email_group_id' => $group_id,
					'company_id' => Configure::get("Blesta.company_id"),
					'lang' => $language->code,
					'from' => $email['from'],
					'from_name' => $email['from_name'],
					'subject' => $email['subject'],
					'text' => $email['text'],
					'html' => $email['html']
				));
			}
		}
		
		// Add a new permission to the group
		$group = $this->Permissions->getGroupByAlias("admin_packages");
		$perm = array('plugin_id' => $plugin_id, 'group_id' => $group->id, 'name' => Language::_("OrderPlugin.admin_forms.name", true), 'alias' => "order.admin_forms", 'action' => "*");
		$this->Permissions->add($perm);
		
		// Add a new permission for the order widget
		$group = $this->Permissions->getGroupByAlias("admin_billing");
		$perm = array('plugin_id' => $plugin_id, 'group_id' => $group->id, 'name' => Language::_("OrderPlugin.admin_main.name", true), 'alias' => "order.admin_main", 'action' => "*");
		$this->Permissions->add($perm);
		
		if (($errors = $this->Permissions->errors())) {
			$this->Input->setErrors($errors);
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
		Configure::load("order", dirname(__FILE__) . DS . "config" . DS);
		
		// Upgrade if possible
		if (version_compare($this->getVersion(), $current_version, ">")) {
			// Handle the upgrade, set errors using $this->Input->setErrors() if any errors encountered
			if (version_compare($current_version, "1.1.0", "<")) {
				$this->Record->
					setField("require_captcha", array('type' => "tinyint", 'size' => 1, 'default' => 0))->
					alter("order_forms");
				$this->Record->
					setField("fraud_status", array('type' => "enum", 'size' => "'allow','review','reject'", 'is_null' => true, 'default' => null))->
					alter("orders");
			}
			
			// Upgrade to 1.1.7
			if (version_compare($current_version, "1.1.7", "<")) {
				Loader::loadModels($this, array("Emails", "EmailGroups", "Languages"));
				
				// Add emails missing in additional languages that have been installed before the plugin was installed
				$languages = $this->Languages->getAll(Configure::get("Blesta.company_id"));
				
				// Add all email templates in other languages IFF they do not already exist
				$emails = Configure::get("Order.install.emails");
				foreach ($emails as $email) {
					$group = $this->EmailGroups->getByAction($email['action']);
					if ($group)
						$group_id = $group->id;
					else {
						$group_id = $this->EmailGroups->add(array(
							'action' => $email['action'],
							'type' => $email['type'],
							'plugin_dir' => $email['plugin_dir'],
							'tags' => $email['tags']
						));
					}
					
					// Set from hostname to use that which is configured for the company
					if (isset(Configure::get("Blesta.company")->hostname))
						$email['from'] = str_replace("@mydomain.com", "@" . Configure::get("Blesta.company")->hostname, $email['from']);
					
					// Add the email template for each language
					foreach ($languages as $language) {
						// Check if this email already exists for this language
						$template = $this->Emails->getByType(Configure::get("Blesta.company_id"), $email['action'], $language->code);
						
						// Template already exists for this language
						if ($template !== false)
							continue;
						
						// Add the missing email for this language
						$this->Emails->add(array(
							'email_group_id' => $group_id,
							'company_id' => Configure::get("Blesta.company_id"),
							'lang' => $language->code,
							'from' => $email['from'],
							'from_name' => $email['from_name'],
							'subject' => $email['subject'],
							'text' => $email['text'],
							'html' => $email['html']
						));
					}
				}
			}
			// Upgrade to 2.0.0
			if (version_compare($current_version, "2.0.0", "<")) {
				$this->Record->query("ALTER TABLE `orders` CHANGE `status` `status` ENUM('pending', 'accepted', 'fraud', 'canceled') NOT NULL DEFAULT 'pending'");
				$this->Record->query("ALTER TABLE `order_forms` ADD `template_style` VARCHAR( 64 ) NOT NULL AFTER `template`");
				$this->Record->update("order_forms", array('template_style' => "default"));
			}
			// Update to 2.2.2
			if (version_compare($current_version, "2.2.2", "<")) {
				// Convert default order form label to ID
				$this->Record->
					from("order_forms")->
					set("order_settings.value", "order_forms.id", false)->
					where("order_settings.key", "=", "default_form")->
					where("order_settings.value", "=", "order_forms.label", false)->
					where("order_settings.company_id", "=", "order_forms.company_id", false)->
					update("order_settings");
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
		Loader::loadModels($this, array("CronTasks", "EmailGroups", "Emails", "Permissions"));
		Configure::load("order", dirname(__FILE__) . DS . "config" . DS);
		
		$emails = Configure::get("Order.install.emails");
		
		// Remove emails and email groups as necessary
		foreach ($emails as $email) {
			// Fetch the email template created by this plugin
			$group = $this->EmailGroups->getByAction($email['action']);
			
			// Delete all emails templates belonging to this plugin's email group and company
			if ($group) {
				$this->Emails->deleteAll($group->id, Configure::get("Blesta.company_id"));
				
				if ($last_instance)
					$this->EmailGroups->delete($group->id);
			}
		}			
		
		$permission = $this->Permissions->getByAlias("order.admin_forms", $plugin_id);
		if ($permission)
			$this->Permissions->delete($permission->id);
			
		$permission = $this->Permissions->getByAlias("order.admin_main", $plugin_id);
		if ($permission)
			$this->Permissions->delete($permission->id);

		$cron_task_run = $this->CronTasks->getTaskRunByKey("accept_paid_orders", "order");
		
		if ($last_instance) {
			try {
				$this->Record->drop("order_forms");
				$this->Record->drop("order_form_groups");
				$this->Record->drop("order_form_meta");
				$this->Record->drop("order_form_currencies");
				$this->Record->drop("order_form_gateways");
				$this->Record->drop("order_staff_settings");
				$this->Record->drop("order_settings");
				$this->Record->drop("orders");
				$this->Record->drop("order_services");
			}
			catch (Exception $e) {
				// Error dropping... no permission?
				$this->Input->setErrors(array('db' => array('create' => $e->getMessage())));
				return;
			}
			
			// Remove the cron task altogether
			$cron_task = $this->CronTasks->getByKey("accept_paid_orders", "order");
			if ($cron_task)
				$this->CronTasks->delete($cron_task->id, "order");
		}
		
		// Remove individual task run
		if ($cron_task_run)
			$this->CronTasks->deleteTaskRun($cron_task_run->task_run_id);
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
				'action' => "widget_staff_billing",
				'uri' => "widget/order/admin_main/",
				'name' => Language::_("OrderPlugin.admin_main.name", true)
			),
			array(
				'action' => "nav_secondary_staff",
				'uri' => "plugin/order/admin_forms/",
				'name' => Language::_("OrderPlugin.admin_forms.name", true),
				'options' => array('parent' => "packages/")
			)
		);
	}
	
	/**
	 * Execute the cron task
	 *
	 * @param string $key The cron task to execute
	 */
	public function cron($key) {
		
		if ($key == "accept_paid_orders") {
			Loader::loadModels($this, array("Order.OrderOrders"));
			$this->OrderOrders->acceptPaidOrders();
		}
	}
}
?>