<?php
/**
 * Admin Company Plugin Settings
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminCompanyPlugins extends AppController {
	
	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();		
		
		// Require login
		$this->requireLogin();		
		
		$this->uses(array("PluginManager", "Navigation"));
		
		Language::loadLang("admin_company_plugins");
		
		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getCompany($this->base_uri))));
	}
	
	/**
	 * Redirect to installed plugins
	 */
	public function index() {
		$this->redirect($this->base_uri . "settings/company/plugins/installed/");
	}
	
	/**
	 * Plugins Installed page
	 */
	public function installed() {
		$this->set("plugins", $this->PluginManager->getAll($this->company_id));
	}
	
	/**
	 * Plugins Available page
	 */
	public function available() {
		$this->set("plugins", $this->PluginManager->getAvailable($this->company_id));		
	}
	
	/**
	 * Manage a plugin
	 */
	public function manage() {
		// Fetch the plugin to manage
		if (!isset($this->get[0]) || !($plugin = $this->PluginManager->get($this->get[0])))
			$this->redirect($this->base_uri . "settings/company/plugins/installed/");
		
		$controller = "admin_manage_plugin";
		$action = "index";
		
		// Allow the URL to override the default controller and action to invoke
		if (isset($this->get['controller']))
			$controller = $this->get['controller'];
		if (isset($this->get['action']))
			$action = $this->get['action'];
		
		$controller = Loader::fromCamelCase($controller);
		$controller_name = Loader::toCamelCase($controller);
		$action = Loader::toCamelCase($action);
		
		$manage_controller = PLUGINDIR . $plugin->dir . DS . "controllers" . DS . $controller . ".php";	
		if (!file_exists($manage_controller)) {
			$this->flashMessage("error", Language::_("AdminCompanyPlugins.!error.setting_controller_invalid", true));
			$this->redirect($this->base_uri . "settings/company/plugins/installed/");
		}
		
		// In order to manage a plugin we need to execute a controller within a controller,
		// so we must initialize and prime the embedded controller with data that
		// would normally be made available with automated bootstrapping.
		// Therefore, the management section of a plugin should be as simple as possible.
		// If there are complex actions that need to be performed it would be best to create
		// a plugin with the proper actions to create new pages from which the plugin
		// could be accessed directly, rather than filtered through AdminCompanyPlugins::manage().
		// Also, consider redirecting from a ManagePlugin controller to a URL controlled by the plugin itself.
		
		// Load the controller
		Loader::load($manage_controller);
		
		// Initialize and prime the controller
		$ctrl = new $controller_name($controller, $action, $this->is_cli);
		$ctrl->uri = $this->uri;
		$ctrl->uri_str = $this->uri_str;
		$ctrl->get = $this->get;
		$ctrl->post = $this->post;
		$ctrl->files = $this->files;
		$ctrl->controller = $controller;
		$ctrl->action = $action;
		$ctrl->is_cli = $this->is_cli;
		$ctrl->base_uri = $this->base_uri;
		$ctrl->parent = $this;
		
		// Execute the action and set the details in the view
		$this->set("content", $ctrl->$action());
	}
	
	/**
	 * Install a plugin
	 */
	public function install() {
		if (!isset($this->post['id']))
			$this->redirect($this->base_uri . "settings/company/plugins/available/");
		
		if (!isset($this->StaffGroups))
			$this->uses(array("StaffGroups"));
		$group = $this->StaffGroups->getStaffGroupByStaff($this->Session->read("blesta_staff_id"), $this->company_id);
		
		$plugin_id = $this->PluginManager->add(array('dir'=>$this->post['id'], 'company_id'=>$this->company_id, 'staff_group_id' => $group->id));
		
		if (($errors = $this->PluginManager->errors())) {
			$this->flashMessage("error", $errors);
			$this->redirect($this->base_uri . "settings/company/plugins/available/");
		}
		else {
			$this->flashMessage("message", Language::_("AdminCompanyPlugins.!success.installed", true));
			$this->redirect($this->base_uri . "settings/company/plugins/installed/");
		}
	}
	
	/**
	 * Uninstall a plugin
	 */
	public function uninstall() {
		$this->performAction("delete", Language::_("AdminCompanyPlugins.!success.uninstalled", true));
	}
	
	/**
	 * Disable a plugin
	 */
	public function disable() {
		$this->performAction("disable", Language::_("AdminCompanyPlugins.!success.disabled", true));
	}
	
	/**
	 * Enable a plugin
	 */
	public function enable() {
		$this->performAction("enable", Language::_("AdminCompanyPlugins.!success.enabled", true));
	}

	/**
	 * Upgrades a plugin
	 */
	public function upgrade() {
		$this->performAction("upgrade", Language::_("AdminCompanyPlugins.!success.upgraded", true));
	}
	
	/**
	 * Performs an action on the given installed plugin
	 *
	 * @param string $action The PluginManager method to invoke
	 * @param string $message The success message to set on success
	 */
	protected function performAction($action, $message) {
		if (!isset($this->post['id']) || !($plugin = $this->PluginManager->get($this->post['id'])))
			$this->redirect($this->base_uri . "settings/company/plugins/installed/");
		
		call_user_func_array(array($this->PluginManager, $action), array($this->post['id']));
		
		if (($errors = $this->PluginManager->errors())) {
			$this->flashMessage("error", $errors);
			$this->redirect($this->base_uri . "settings/company/plugins/installed/");
		}
		else {
			$this->flashMessage("message", $message);
			$this->redirect($this->base_uri . "settings/company/plugins/installed/");
		}
	}
}
?>