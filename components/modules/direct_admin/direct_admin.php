<?php
/**
 * DirectAdmin Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.direct_admin
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class DirectAdmin extends Module {
	
	/**
	 * @var string The version of this module
	 */
	private static $version = "2.2.1";
	/**
	 * @var string The authors of this module
	 */
	private static $authors = array(array('name' => "Phillips Data, Inc.", 'url' => "http://www.blesta.com"));
	
	/**
	 * Initializes the module
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("direct_admin", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this module
	 *
	 * @return string The common name of this module
	 */
	public function getName() {
		return Language::_("DirectAdmin.name", true);
	}
	
	/**
	 * Returns the version of this gateway
	 *
	 * @return string The current version of this gateway
	 */
	public function getVersion() {
		return self::$version;
	}
	
	/**
	 * Returns the name and url of the authors of this module
	 *
	 * @return array The name and url of the authors of this module
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Returns a noun used to refer to a module row (e.g. "Server")
	 *
	 * @return string The noun used to refer to a module row
	 */
	public function moduleRowName() {
		return Language::_("DirectAdmin.module_row", true);
	}
	
	/**
	 * Returns a noun used to refer to a module row in plural form (e.g. "Servers", "VPSs", "Reseller Accounts", etc.)
	 *
	 * @return string The noun used to refer to a module row in plural form
	 */
	public function moduleRowNamePlural() {
		return Language::_("DirectAdmin.module_row_plural", true);
	}
	
	/**
	 * Returns a noun used to refer to a module group (e.g. "Server Group")
	 *
	 * @return string The noun used to refer to a module group
	 */
	public function moduleGroupName() {
		return Language::_("DirectAdmin.module_group", true);
	}
	
	/**
	 * Returns the key used to identify the primary field from the set of module row meta fields.
	 *
	 * @return string The key used to identify the primary field from the set of module row meta fields
	 */
	public function moduleRowMetaKey() {
		return "server_name";
	}
	
	/**
	 * Returns an array of available service deligation order methods. The module
	 * will determine how each method is defined. For example, the method "first"
	 * may be implemented such that it returns the module row with the least number
	 * of services assigned to it.
	 *
	 * @return array An array of order methods in key/value paris where the key is the type to be stored for the group and value is the name for that option
	 * @see Module::selectModuleRow()
	 */
	public function getGroupOrderOptions() {
		return array('first' => Language::_("DirectAdmin.order_options.first", true));
	}
	
	/**
	 * Returns the value used to identify a particular service
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @return string A value used to identify this service amongst other similar services
	 */
	public function getServiceName($service) {
		foreach ($service->fields as $field) {
			if ($field->key == "direct_admin_domain")
				return $field->value;
		}
		return null;
	}
	
	/**
	 * Returns the value used to identify a particular package service which has
	 * not yet been made into a service. This may be used to uniquly identify
	 * to uncreated services of the same package (i.e. in an order form checkout)
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @return string The value used to identify this package service
	 * @see Module::getServiceName()
	 */
	public function getPackageServiceName($package, array $vars=null) {
		if (isset($vars['direct_admin_domain']))
			return $vars['direct_admin_domain'];
		return null;
	}
	
	/**
	 * Returns all fields used when adding/editing a package, including any
	 * javascript to execute when the page is rendered with these fields.
	 *
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getPackageFields($vars=null) {
		Loader::loadHelpers($this, array("Html"));
		
		$fields = new ModuleFields();
		$fields->setHtml("
			<script type=\"text/javascript\">
				$(document).ready(function() {
					// Re-fetch module options to pull cPanel packages and ACLs
					$('.direct_admin_type').change(function() {
						fetchModuleOptions();
					});
				});
			</script>
		");
		
		// Fetch all packages available for the given server or server group
		$module_row = null;
		if (isset($vars->module_group) && $vars->module_group == "") {
			if (isset($vars->module_row) && $vars->module_row > 0) {
				$module_row = $this->getModuleRow($vars->module_row);
			}
			else {
				$rows = $this->getModuleRows();
				if (isset($rows[0]))
					$module_row = $rows[0];
				unset($rows);
			}
		}
		else {
			// Fetch the 1st server from the list of servers in the selected group
			$rows = $this->getModuleRows($vars->module_group);

			if (isset($rows[0]))
				$module_row = $rows[0];
			unset($rows);
		}
		
		// Set the type of account (user or reseller)
		$type = $fields->label(Language::_("DirectAdmin.package_fields.type", true), "direct_admin_type");
		$type_user = $fields->label(Language::_("DirectAdmin.package_fields.type_user", true), "direct_admin_type_user");
		$type_reseller = $fields->label(Language::_("DirectAdmin.package_fields.type_reseller", true), "direct_admin_type_reseller");
		$type->attach($fields->fieldRadio("meta[type]", "user",
			$this->Html->ifSet($vars->meta['type'], "user") == "user", array('id'=>"direct_admin_type_user", 'class'=>"direct_admin_type"), $type_user));
		$type->attach($fields->fieldRadio("meta[type]", "reseller",
			$this->Html->ifSet($vars->meta['type']) == "reseller", array('id'=>"direct_admin_type_reseller", 'class'=>"direct_admin_type"), $type_reseller));
		$fields->setField($type);
		
		$packages = array();
		if ($module_row) {
			// Fetch the packages associated with this user/reseller
			$command = ($this->Html->ifSet($vars->meta['type']) == "reseller" ? "getPackagesReseller" : "getPackagesUser");
			$packages = $this->getDirectAdminPackages($module_row, $command);
		}
		
		// Set the DirectAdmin package as a selectable option
		$package = $fields->label(Language::_("DirectAdmin.package_fields.package", true), "direct_admin_package");
		$package->attach($fields->fieldSelect("meta[package]", $packages,
			$this->Html->ifSet($vars->meta['package']), array('id'=>"direct_admin_package")));
		$fields->setField($package);	
		
		// Set the IP
		$ip = $fields->label(Language::_("DirectAdmin.package_fields.ip", true), "direct_admin_ip");
		if ($this->Html->ifSet($vars->meta['type']) == "reseller") {
			$reseller_ips = array(
				'shared' => Language::_("DirectAdmin.package_fields.ip_shared", true),
				'assign' => Language::_("DirectAdmin.package_fields.ip_assign", true)
			);
			$ip->attach($fields->fieldSelect("meta[ip]", $reseller_ips,
				$this->Html->ifSet($vars->meta['ip']), array('id'=>"direct_admin_ip")));
			$fields->setField($ip);
		}
		else {
			// Set a list of normal user IPs available for user creation.
			if ($module_row) {
				$results = (array)$this->getDirectAdminIps($module_row);
				$ip->attach($fields->fieldSelect("meta[ip]", $results,
					$this->Html->ifSet($vars->meta['ip']), array('id'=>"direct_admin_ip")));
				$fields->setField($ip);
			}
		}
		
		return $fields;
	}
	
	/**
	 * Returns an array of key values for fields stored for a module, package,
	 * and service under this module, used to substitute those keys with their
	 * actual module, package, or service meta values in related emails.
	 *
	 * @return array A multi-dimensional array of key/value pairs where each key is one of 'module', 'package', or 'service' and each value is a numerically indexed array of key values that match meta fields under that category.
	 * @see Modules::addModuleRow()
	 * @see Modules::editModuleRow()
	 * @see Modules::addPackage()
	 * @see Modules::editPackage()
	 * @see Modules::addService()
	 * @see Modules::editService()
	 */
	public function getEmailTags() {
		return array(
			'module' => array("host_name", "name_servers"),
			'package' => array("type", "package", "ip"),
			'service' => array("direct_admin_username", "direct_admin_password", "direct_admin_domain", "direct_admin_ip", "direct_admin_email")
		);
	}
	
	/**
	 * Returns the rendered view of the manage module page
	 *
	 * @param mixed $module A stdClass object representing the module and its rows
	 * @param array $vars An array of post data submitted to or on the manager module page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the manager module page
	 */
	public function manageModule($module, array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("manage", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "direct_admin" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));

		$this->view->set("module", $module);
		
		return $this->view->fetch();
	}
	
	/**
	 * Returns the rendered view of the add module row page
	 *
	 * @param array $vars An array of post data submitted to or on the add module row page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the add module row page
	 */
	public function manageAddRow(array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("add_row", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "direct_admin" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		// Set unspecified checkboxes
		if (!empty($vars)) {
			if (empty($vars['use_ssl']))
				$vars['use_ssl'] = "false";
		}
		
		$this->view->set("vars", (object)$vars);
		return $this->view->fetch();
	}
	
	/**
	 * Returns the rendered view of the edit module row page
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 * @param array $vars An array of post data submitted to or on the edit module row page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the edit module row page
	 */	
	public function manageEditRow($module_row, array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("edit_row", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "direct_admin" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		if (empty($vars))
			$vars = $module_row->meta;
		else {
			// Set unspecified checkboxes
			if (empty($vars['use_ssl']))
				$vars['use_ssl'] = "false";
		}
		
		$this->view->set("vars", (object)$vars);
		return $this->view->fetch();
	}
	
	/**
	 * Adds the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being added. Returns a set of data, which may be
	 * a subset of $vars, that is stored for this module row
	 *
	 * @param array $vars An array of module info to add
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function addModuleRow(array &$vars) {
		$meta_fields = array("server_name", "host_name", "user_name", "password",
			"use_ssl", "account_limit", "account_count", "name_servers", "notes");
		$encrypted_fields = array("user_name", "password");
		
		// Set unspecified checkboxes
		if (empty($vars['use_ssl']))
			$vars['use_ssl'] = "false";
		
		// Set rules to validate against
		$this->Input->setRules($this->getRowRules($vars));
		
		// Validate module row
		if ($this->Input->validates($vars)) {
			// Build the meta data for this row
			$meta = array();
			foreach ($vars as $key => $value) {
				
				if (in_array($key, $meta_fields)) {
					$meta[] = array(
						'key'=>$key,
						'value'=>$value,
						'encrypted'=>in_array($key, $encrypted_fields) ? 1 : 0
					);
				}
			}
			
			return $meta;
		}
	}
	
	/**
	 * Determines which module row should be attempted when a service is provisioned
	 * for the given group based upon the order method set for that group.
	 *
	 * @return int The module row ID to attempt to add the service with
	 * @see Module::getGroupOrderOptions()
	 */
	public function selectModuleRow($module_group_id) {
		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));
		
		$group = $this->ModuleManager->getGroup($module_group_id);
		
		if ($group) {
			switch ($group->add_order) {
				default:
				case "first":
					
					foreach ($group->rows as $row) {
						if ($row->meta->account_limit > (isset($row->meta->account_count) ? $row->meta->account_count : 0))
							return $row->id;
					}
					
					break;
			}
		}
		return 0;
	}
	
	/**
	 * Edits the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being updated. Returns a set of data, which may be
	 * a subset of $vars, that is stored for this module row
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 * @param array $vars An array of module info to update
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function editModuleRow($module_row, array &$vars) {
		$meta_fields = array("server_name", "host_name", "user_name", "password",
			"use_ssl", "account_limit", "account_count", "name_servers", "notes");
		$encrypted_fields = array("user_name", "password");
		
		// Set unspecified checkboxes
		if (empty($vars['use_ssl']))
			$vars['use_ssl'] = "false";
		
		$this->Input->setRules($this->getRowRules($vars));
		
		// Validate module row
		if ($this->Input->validates($vars)) {

			// Build the meta data for this row
			$meta = array();
			foreach ($vars as $key => $value) {
				
				if (in_array($key, $meta_fields)) {
					$meta[] = array(
						'key'=>$key,
						'value'=>$value,
						'encrypted'=>in_array($key, $encrypted_fields) ? 1 : 0
					);
				}
			}
			
			return $meta;
		}
	}
	
	/**
	 * Fetches the HTML content to display when viewing the service info in the
	 * admin interface.
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @param stdClass $package A stdClass object representing the service's package
	 * @return string HTML content containing information to display when viewing the service info
	 */
	public function getAdminServiceInfo($service, $package) {
		$row = $this->getModuleRow();
		
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("admin_service_info", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "direct_admin" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("module_row", $row);
		$this->view->set("package", $package);
		$this->view->set("service", $service);
		$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));
		
		return $this->view->fetch();
	}
	
	/**
	 * Fetches the HTML content to display when viewing the service info in the
	 * client interface.
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @param stdClass $package A stdClass object representing the service's package
	 * @return string HTML content containing information to display when viewing the service info
	 */
	public function getClientServiceInfo($service, $package) {
		$row = $this->getModuleRow();
		
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("client_service_info", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "direct_admin" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("module_row", $row);
		$this->view->set("package", $package);
		$this->view->set("service", $service);
		$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));
		
		return $this->view->fetch();
	}
	
	/**
	 * Returns all fields to display to an admin attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getAdminAddFields($package, $vars=null) {
		Loader::loadHelpers($this, array("Html"));
		
		$fields = new ModuleFields();
		
		// Create domain label
		$domain = $fields->label(Language::_("DirectAdmin.service_field.domain", true), "direct_admin_domain");
		// Create domain field and attach to domain label
		$domain->attach($fields->fieldText("direct_admin_domain", $this->Html->ifSet($vars->direct_admin_domain), array('id'=>"direct_admin_domain")));
		// Set the label as a field
		$fields->setField($domain);

		// Create username label
		$username = $fields->label(Language::_("DirectAdmin.service_field.username", true), "direct_admin_username");
		// Create username field and attach to username label
		$username->attach($fields->fieldText("direct_admin_username", $this->Html->ifSet($vars->direct_admin_username), array('id'=>"direct_admin_username")));
		// Set the label as a field
		$fields->setField($username);
		
		// Create password label
		$password = $fields->label(Language::_("DirectAdmin.service_field.password", true), "direct_admin_password");
		// Create password field and attach to password label
		$password->attach($fields->fieldText("direct_admin_password", $this->Html->ifSet($vars->direct_admin_password), array('id'=>"direct_admin_password")));
		// Set the label as a field
		$fields->setField($password);
		
		// Create email label
		$email = $fields->label(Language::_("DirectAdmin.service_field.email", true), "direct_admin_email");
		// Create password field and attach to password label
		$email->attach($fields->fieldText("direct_admin_email", $this->Html->ifSet($vars->direct_admin_email), array('id'=>"direct_admin_email")));
		// Set the label as a field
		$fields->setField($email);
		
		return $fields;
	}
	
	/**
	 * Returns all fields to display to a client attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getClientAddFields($package, $vars=null) {
		Loader::loadHelpers($this, array("Html"));
		
		$fields = new ModuleFields();
		
		// Create domain label
		$domain = $fields->label(Language::_("DirectAdmin.service_field.domain", true), "direct_admin_domain");
		// Create domain field and attach to domain label
		$domain->attach($fields->fieldText("direct_admin_domain", $this->Html->ifSet($vars->direct_admin_domain, $this->Html->ifSet($vars->domain)), array('id'=>"direct_admin_domain")));
		// Set the label as a field
		$fields->setField($domain);

		// Create username label
		$username = $fields->label(Language::_("DirectAdmin.service_field.username", true), "direct_admin_username");
		// Create username field and attach to username label
		$username->attach($fields->fieldText("direct_admin_username", $this->Html->ifSet($vars->direct_admin_username), array('id'=>"direct_admin_username")));
		// Set the label as a field
		$fields->setField($username);
		
		// Create password label
		$password = $fields->label(Language::_("DirectAdmin.service_field.password", true), "direct_admin_password");
		// Create password field and attach to password label
		$password->attach($fields->fieldText("direct_admin_password", $this->Html->ifSet($vars->direct_admin_password), array('id'=>"direct_admin_password")));
		// Set the label as a field
		$fields->setField($password);
		
		// Create email label
		$email = $fields->label(Language::_("DirectAdmin.service_field.email", true), "direct_admin_email");
		// Create password field and attach to password label
		$email->attach($fields->fieldText("direct_admin_email", $this->Html->ifSet($vars->direct_admin_email), array('id'=>"direct_admin_email")));
		// Set the label as a field
		$fields->setField($email);
		
		return $fields;
	}
	
	/**
	 * Validates input data when attempting to add a package, returns the meta
	 * data to save when adding a package. Performs any action required to add
	 * the package on the remote server. Sets Input errors on failure,
	 * preventing the package from being added.
	 *
	 * @param array An array of key/value pairs used to add the package
	 * @return array A numerically indexed array of meta fields to be stored for this package containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function addPackage(array $vars=null) {
		
		// Set rules to validate input data
		$this->Input->setRules($this->getPackageRules($vars));
		
		// Build meta data to return
		$meta = array();
		if ($this->Input->validates($vars)) {
			// Return all package meta fields
			foreach ($vars['meta'] as $key => $value) {
				$meta[] = array(
					'key' => $key,
					'value' => $value,
					'encrypted' => 0
				);
			}
		}
		return $meta;
	}
	
	/**
	 * Validates input data when attempting to edit a package, returns the meta
	 * data to save when editing a package. Performs any action required to edit
	 * the package on the remote server. Sets Input errors on failure,
	 * preventing the package from being edited.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array An array of key/value pairs used to edit the package
	 * @return array A numerically indexed array of meta fields to be stored for this package containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function editPackage($package, array $vars=null) {
		
		// Set rules to validate input data
		$this->Input->setRules($this->getPackageRules($vars));
		
		// Build meta data to return
		$meta = array();
		if ($this->Input->validates($vars)) {
			// Return all package meta fields
			foreach ($vars['meta'] as $key => $value) {
				$meta[] = array(
					'key' => $key,
					'value' => $value,
					'encrypted' => 0
				);
			}
		}
		return $meta;
	}
	
	/**
	 * Updates the package for the service on the remote server. Sets Input
	 * errors on failure, preventing the service's package from being changed.
	 *
	 * @param stdClass $package_from A stdClass object representing the current package
	 * @param stdClass $package_to A stdClass object representing the new package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being changed (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function changeServicePackage($package_from, $package_to, $service, $parent_package=null, $parent_service=null) {
		
		if (($row = $this->getModuleRow())) {
			$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->password, ($row->meta->use_ssl == "true"));
			
			// Only request a package change if it has changed
			if ($package_from->meta->package != $package_to->meta->package) {
				
				// Check whether packages are being changed between user/reseller
				if ($package_to->meta->type != $package_from->meta->type) {
					$this->Input->setErrors(array('change_package' => array('type' => Language::_("DirectAdmin.!error.change_package.type", true))));
					return;
				}
				
				// Get the service fields
				$service_fields = $this->serviceFieldsToObject($service->fields);
				
				// Set the API command
				$command = "modifyUserPackage";
				$params = array('package' => $package_to->meta->package, 'user' => $service_fields->direct_admin_username);
				
				$this->log($row->meta->host_name . "|" . $command, serialize(array($service_fields->direct_admin_username, $package_to->meta->package)), "input", true);
				$this->parseResponse($api->__call($command, $params));
			}
		}
		return null;
	}
	
	/**
	 * Adds the service to the remote server. Sets Input errors on failure,
	 * preventing the service from being added.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being added (if the current service is an addon service service and parent service has already been provisioned)
	 * @param string $status The status of the service being added. These include:
	 * 	- active
	 * 	- canceled
	 * 	- pending
	 * 	- suspended
	 * @return array A numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function addService($package, array $vars=null, $parent_package=null, $parent_service=null, $status="pending") {
		$row = $this->getModuleRow();
		$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->password, ($row->meta->use_ssl == "true"));
		
		$params = $this->getFieldsFromInput((array)$vars, $package);
		
		$this->validateService($package, $vars);
		
		if ($this->Input->errors())
			return;
		
		// Only provision the service if 'use_module' is true
		if (isset($vars['use_module']) && $vars['use_module'] == "true") {
			
			$masked_params = $params;
			$masked_params['password'] = "***";
			
			// Set the command to be used, either to create a user or reseller
			$command = "createUser";
			if ($package->meta->type == "reseller")
				$command = "createReseller";
			
			$this->log($row->meta->host_name . "|" . $command, serialize($masked_params), "input", true);
			unset($masked_params);
			
			$result = $this->parseResponse($api->__call($command, $params));
			
			if ($this->Input->errors())
				return;
			
			// Update the number of accounts on the server
			$this->updateAccountCount($row);
		}
		
		// Return service fields
		return array(
			array(
				'key' => "direct_admin_domain",
				'value' => $params['domain'],
				'encrypted' => 0
			),
			array(
				'key' => "direct_admin_username",
				'value' => $params['username'],
				'encrypted' => 0
			),
			array(
				'key' => "direct_admin_password",
				'value' => $params['passwd'],
				'encrypted' => 1
			),
			array(
				'key' => "direct_admin_email",
				'value' => $params['email'],
				'encrypted' => 0
			),
			array(
				'key' => "direct_admin_ip",
				'value' => $params['ip'],
				'encrypted' => 0
			)
		);
	}
	
	/**
	 * Suspends the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being suspended.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being suspended (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function suspendService($package, $service, $parent_package=null, $parent_service=null) {
		if (($row = $this->getModuleRow())) {
			$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->password, ($row->meta->use_ssl == "true"));
			$service_fields = $this->serviceFieldsToObject($service->fields);
			$command = "suspendUser";
			
			// Suspend the account
			$this->log($row->meta->host_name . "|" . $command, serialize($service_fields->direct_admin_username), "input", true);
			$this->parseResponse($api->__call($command, array('select0' => $service_fields->direct_admin_username)));
		}
		
		return null;
	}
	
	/**
	 * Unsuspends the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being unsuspended.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being unsuspended (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function unsuspendService($package, $service, $parent_package=null, $parent_service=null) {
		if (($row = $this->getModuleRow())) {
			$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->password, ($row->meta->use_ssl == "true"));
			$service_fields = $this->serviceFieldsToObject($service->fields);
			$command = "unsuspendUser";
			
			// Unsuspend the account
			$this->log($row->meta->host_name . "|" . $command, serialize($service_fields->direct_admin_username), "input", true);
			$this->parseResponse($api->__call($command, array('select0' => $service_fields->direct_admin_username)));
		}
		return null;
	}
	
	/**
	 * Cancels the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being canceled.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being canceled (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function cancelService($package, $service, $parent_package=null, $parent_service=null) {
		if (($row = $this->getModuleRow())) {
			$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->password, ($row->meta->use_ssl == "true"));
			$service_fields = $this->serviceFieldsToObject($service->fields);
			$command = "deleteUser";
			
			// Delete the account
			$this->log($row->meta->host_name . "|" . $command, serialize($service_fields->direct_admin_username), "input", true);
			$this->parseResponse($api->__call($command, array('select0' => $service_fields->direct_admin_username)));
			
			// Update the number of accounts on the server
			$this->updateAccountCount($row);
		}
		return null;
	}
	
	/**
	 * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @return boolean True if the service validates, false otherwise. Sets Input errors when false.
	 */
	public function validateService($package, array $vars=null) {
		// Validate the service fields
		$rules = array(
			'direct_admin_domain' => array(
				'format' => array(
					'rule' => array(array($this, "validateHostName")),
					'message' => Language::_("DirectAdmin.!error.direct_admin_domain.format", true)
				)
			),
			'direct_admin_username' => array(
				'format' => array(
					'rule' => array("matches", "/^[a-z0-9]*$/i"),
					'message' => Language::_("DirectAdmin.!error.direct_admin_username.format", true)
				),
				'length' => array(
					'rule' => array("betweenLength", 4, 8),
					'message' => Language::_("DirectAdmin.!error.direct_admin_username.length", true)
				)
			),
			'direct_admin_password' => array(
				'format' => array(
					'rule' => array("matches", "/^[(\x20-\x7F)]*$/"), // ASCII 32-127
					'message' => Language::_("DirectAdmin.!error.direct_admin_password.format", true)
				),
				'length' => array(
					'rule' => array("minLength", 5),
					'message' => Language::_("DirectAdmin.!error.direct_admin_password.length", true)
				)
			),
			'direct_admin_email' => array(
				'format' => array(
					'rule' => "isEmail",
					'message' => Language::_("DirectAdmin.!error.direct_admin_email.format", true)
				)
			)
		);
		$this->Input->setRules($rules);
		return $this->Input->validates($vars);
	}
	
	/**
	 * Validates that the given hostname is valid
	 *
	 * @param string $host_name The host name to validate
	 * @return boolean True if the hostname is valid, false otherwise
	 */
	public function validateHostName($host_name) {
		if (strlen($host_name) > 255)
			return false;
		
		return $this->Input->matches($host_name, "/^([a-z0-9]|[a-z0-9][a-z0-9\-]{0,61}[a-z0-9])(\.([a-z0-9]|[a-z0-9][a-z0-9\-]{0,61}[a-z0-9]))*$/");
	}
	
	/**
	 * Validates that at least 2 name servers are set in the given array of name servers
	 *
	 * @param array $name_servers An array of name servers
	 * @return boolean True if the array count is >=2, false otherwise
	 */
	public function validateNameServerCount($name_servers) {
		if (is_array($name_servers) && count($name_servers) >= 2)
			return true;
		return false;
	}
	
	/**
	 * Validates that the nameservers given are formatted correctly
	 *
	 * @param array $name_servers An array of name servers
	 * @return boolean True if every name server is formatted correctly, false otherwise
	 */
	public function validateNameServers($name_servers) {
		if (is_array($name_servers)) {
			foreach ($name_servers as $name_server) {
				if (!$this->validateHostName($name_server))
					return false;
			}
		}
		return true;
	}
	
	/**
	 * Initializes the DirectAdminApi and returns an instance of that object with the given $host, $user, and $pass set
	 *
	 * @param string $host The host to the cPanel server
	 * @param string $user The user to connect as
	 * @param string $pass The hash-pased password to authenticate with
	 * @param boolean $use_ssl True to use SSL, false otherwise
	 * @return DirectAdminApi The DirectAdminApi instance
	 */
	private function getApi($host, $user, $pass, $use_ssl = false) {
		Loader::load(dirname(__FILE__) . DS . "apis" . DS . "direct_admin_api.php");
		
		$api = new DirectAdminApi();
		$api->setUrl("http" . ($use_ssl ? "s" : "") . "://" . $host);
		$api->setUser($user);
		$api->setPass($pass);
		
		return $api;
	}
	
	/**
	 * Parses the response from the API into a stdClass object
	 *
	 * @param array $response The response from the API
	 * @param boolean $return_response Whether to return the response, regardless of error
	 * @return stdClass A stdClass object representing the response, void if the response was an error
	 */
	private function parseResponse($response, $return_response = false) {
		$row = $this->getModuleRow();
		$success = true;
		$invalid_response = false;
		
		// Check for an invalid HTML response from the module
		if (is_array($response) && count($response) == 1) {
			foreach ($response as $key => $value) {
				// Invalid response
				if (preg_match("/<html>/", $key) || preg_match("/<html>/", $value)) {
					$invalid_response = true;
					break;
				}
			}
		}
		
		// Set an internal error on no response or invalid response
		if (empty($response) || $invalid_response) {
			$this->Input->setErrors(array('api' => array('internal' => Language::_("DirectAdmin.!error.api.internal", true))));
			$success = false;
		}
		
		// Set an error if given
		if (isset($response['error']) && $response['error'] == "1") {
			$error = (isset($response['text']) ? $response['text'] : Language::_("DirectAdmin.!error.api.internal", true));
			$this->Input->setErrors(array('api' => array('error' => $error)));
			$success = false;
		}
		
		// Log the response
		$this->log($row->meta->host_name, serialize($response), "output", $success);
		
		// Return if any errors encountered
		if (!$success && !$return_response)
			return;
		
		return $response;
	}
	
	/**
	 * Returns an array of service field to set for the service using the given input
	 *
	 * @param array $vars An array of key/value input pairs
	 * @param stdClass $package A stdClass object representing the package for the service
	 * @return array An array of key/value pairs representing service fields
	 */
	private function getFieldsFromInput(array $vars, $package) {
		$fields = array(
			'domain' => isset($vars['direct_admin_domain']) ? $vars['direct_admin_domain'] : null,
			'username' => isset($vars['direct_admin_username']) ? $vars['direct_admin_username']: null,
			'passwd' => isset($vars['direct_admin_password']) ? $vars['direct_admin_password'] : null,
			'passwd2' => isset($vars['direct_admin_password']) ? $vars['direct_admin_password'] : null,
			'email' => isset($vars['direct_admin_email']) ? $vars['direct_admin_email'] : null,
			'ip' => isset($package->meta->ip) ? $package->meta->ip : null,
			'package' => isset($package->meta->package) ? $package->meta->package : null
		);
		
		return $fields;
	}
	
	/**
	 * Retrieves the accounts on the server
	 *
	 * @param stdClass $api The DirectAdmin API
	 * @return mixed The number of accounts on the server, or null on error
	 */
	private function getAccountCount($api, $user) {
		$user_type = "";
		
		// Get account info on this user
		try {
			// Fetch the account information
			$response = $api->__call("getUserConfig", array('user' => $user));
			
			if ($response && is_array($response) && array_key_exists("usertype", $response))
				$user_type = $response['usertype'];
		}
		catch (Exception $e) {
			return;
		}
		
		// Determine how many user accounts exist under this user
		if (in_array($user_type, array("reseller", "admin"))) {
			try {
				$action = ($user_type == "admin" ? "listUsers" : "listUsersByReseller");
				
				// Fetch the users on the server
				$response = $api->__call($action, array());
				
				// Users are set in 'list'
				$list = (isset($response['list']) ? $response['list'] : array());
				
				return count($list);
			}
			catch (Exception $e) {
				// API request failed
			}
		}
	}
	
	/**
	 * Updates the module row meta number of accounts
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 */
	private function updateAccountCount($module_row) {
		$api = $this->getApi($module_row->meta->host_name, $module_row->meta->user_name, $module_row->meta->password, ($module_row->meta->use_ssl == "true"));
		
		// Get the number of accounts on the server
		if (($count = $this->getAccountCount($api, $module_row->meta->user_name))) {
			// Update the module row account list
			Loader::loadModels($this, array("ModuleManager"));
			$vars = $this->ModuleManager->getRowMeta($module_row->id);
			
			if ($vars) {
				$vars->account_count = $count;
				$vars = (array)$vars;
				
				$this->ModuleManager->editRow($module_row->id, $vars);
			}
		}
	}
	
	/**
	 * Fetches a listing of all packages configured in DirectAdmin for the given server
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param string $command The API command to call, either getPackagesUser, or getPackagesReseller
	 * @return array An array of packages in key/value pairs
	 */
	private function getDirectAdminPackages($module_row, $command) {
		$api = $this->getApi($module_row->meta->host_name, $module_row->meta->user_name, $module_row->meta->password, ($module_row->meta->use_ssl == "true"));
		
        $this->log($module_row->meta->host_name . "|" . $command, null, "input", true);

		try {
			// Fetch the packages
			$response = $api->__call($command, array());
            $this->log($module_row->meta->host_name . "|" . $command, serialize($response), "output", !empty($response));

			// Packages are set in 'list'
			$list = (isset($response['list']) ? $response['list'] : array());
			$packages = array();
			
			// Assign the key/value for each package
			foreach ($list as $key => $value)
				$packages[$value] = $value;
			
			return $packages;
		}
		catch (Exception $e) {
			// API request failed
            $message = $e->getMessage();
            $this->log($module_row->meta->host_name . "|" . $command, serialize($message), "output", false);
		}
	}
	
	/**
	 * Fetches a listing of all IPs configured in DirectAdmin
	 *
	 * @param stdClass $module_row A stdClass object represinting a single server
	 * @return array An array of ips in key/value pairs
	 */
	private function getDirectAdminIps($module_row) {
		$api = $this->getApi($module_row->meta->host_name, $module_row->meta->user_name, $module_row->meta->password, ($module_row->meta->use_ssl == "true"));
		
        $command = "getResellerIps";
        $this->log($module_row->meta->host_name . "|" . $command, null, "input", true);

		try {
			// Fetch the IPs
			$response = $api->__call($command, array());
            $this->log($module_row->meta->host_name . "|" . $command, serialize($response), "output", ($response != "error=1"));

			// IPs are set in 'list'
			$list = (isset($response['list']) ? $response['list'] : array());
			$ips = array();
			
			// Assign the key/value for each IP
			foreach ($list as $key => $value)
				$ips[$value] = $value;
			
			return $ips;
		}
		catch (Exception $e) {
			// API request failed
            $message = $e->getMessage();
            $this->log($module_row->meta->host_name . "|" . $command, serialize($message), "output", false);
		}
	}
	
	/**
	 * Builds and returns the rules required to add/edit a module row (e.g. server)
	 *
	 * @param array $vars An array of key/value data pairs
	 * @return array An array of Input rules suitable for Input::setRules()
	 */
	private function getRowRules($vars) {
		$rules = array(
			'server_name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("DirectAdmin.!error.server_name.empty", true)
				)
			),
			'host_name' => array(
				'format' => array(
					'rule' => array(array($this, "validateHostName")),
					'message' => Language::_("DirectAdmin.!error.host_name.format", true)
				)
			),
			'user_name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("DirectAdmin.!error.user_name.empty", true)
				)
			),
			'password' => array(
				'format' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("DirectAdmin.!error.password.format", true)
				)
			),
			'use_ssl' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("in_array", array("true", "false")),
					'message' => Language::_("DirectAdmin.!error.use_ssl.format", true)
				)
			),
			'account_limit' => array(
				'valid' => array(
					'rule' => array("matches", "/^([0-9]+)?$/"),
					'message' => Language::_("DirectAdmin.!error.account_limit.valid", true)
				)
			),
			'name_servers'=>array(
				'count'=>array(
					'rule'=>array(array($this, "validateNameServerCount")),
					'message'=>Language::_("DirectAdmin.!error.name_servers.count", true)
				),
				'valid'=>array(
					'rule'=>array(array($this, "validateNameServers")),
					'message'=>Language::_("DirectAdmin.!error.name_servers.valid", true)
				)
			)
		);
		
		return $rules;
	}
	
	/**
	 * Builds and returns rules required to be validated when adding/editing a package
	 *
	 * @param array $vars An array of key/value data pairs
	 * @return array An array of Input rules suitable for Input::setRules()
	 */
	private function getPackageRules($vars) {
		$rules = array(
			'meta[type]' => array(
				'format' => array(
					'rule' => array("matches", "/^(user|reseller)$/"),
					'message' => Language::_("DirectAdmin.!error.meta[type].format", true), // type must be user or reseller
				)
			),
			'meta[package]' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("DirectAdmin.!error.meta[package].empty", true) // package must be given
				)
			),
			'meta[ip]' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("DirectAdmin.!error.meta[ip].empty", true) // IP address is required
				)
			)
		);
		
		return $rules;
	}
}
?>