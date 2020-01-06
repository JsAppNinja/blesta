<?php
/**
 * Cpanel Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.cpanel
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Cpanel extends Module {
	
	/**
	 * @var string The version of this module
	 */
	private static $version = "2.3.1";
	/**
	 * @var string The authors of this module
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	
	/**
	 * Initializes the module
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("cpanel", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this module
	 *
	 * @return string The common name of this module
	 */
	public function getName() {
		return Language::_("Cpanel.name", true);
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
	 * Returns all tabs to display to an admin when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 */
	public function getAdminTabs($package) {
		return array(
			'tabStats' => Language::_("Cpanel.tab_stats", true)
		);
	}
	
	/**
	 * Returns all tabs to display to a client when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 */
	public function getClientTabs($package) {
		return array(
            'tabClientActions' => Language::_("Cpanel.tab_client_actions", true),
			'tabClientStats' => Language::_("Cpanel.tab_client_stats", true)
		);
	}
	
	/**
	 * Returns a noun used to refer to a module row (e.g. "Server")
	 *
	 * @return string The noun used to refer to a module row
	 */
	public function moduleRowName() {
		return Language::_("Cpanel.module_row", true);
	}
	
	/**
	 * Returns a noun used to refer to a module row in plural form (e.g. "Servers", "VPSs", "Reseller Accounts", etc.)
	 *
	 * @return string The noun used to refer to a module row in plural form
	 */
	public function moduleRowNamePlural() {
		return Language::_("Cpanel.module_row_plural", true);
	}
	
	/**
	 * Returns a noun used to refer to a module group (e.g. "Server Group")
	 *
	 * @return string The noun used to refer to a module group
	 */
	public function moduleGroupName() {
		return Language::_("Cpanel.module_group", true);
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
		return array('first'=>Language::_("Cpanel.order_options.first", true));
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
	 * Returns all fields used when adding/editing a package, including any
	 * javascript to execute when the page is rendered with these fields.
	 *
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containing the fields to render as well as any additional HTML markup to include
	 */
	public function getPackageFields($vars=null) {
		Loader::loadHelpers($this, array("Html"));
		
		$fields = new ModuleFields();
		$fields->setHtml("
			<script type=\"text/javascript\">
				$(document).ready(function() {
					// Set whether to show or hide the ACL option
					$('#cpanel_acl').closest('li').hide();
					if ($('input[name=\"meta[type]\"]:checked').val() == 'reseller')
						$('#cpanel_acl').closest('li').show();
					$('input[name=\"meta[type]\"]').change(function() {
						if ($(this).val() == 'reseller')
							$('#cpanel_acl').closest('li').show();
						else
							$('#cpanel_acl').closest('li').hide();
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
		
		$packages = array();
		$acls = array('' => Language::_("Cpanel.package_fields.acl_default", true));
		
		if ($module_row) {
			$packages = $this->getCpanelPackages($module_row);
			$acls = $acls + $this->getCpanelAcls($module_row);
		}
		
		// Set the cPanel package as a selectable option
		$package = $fields->label(Language::_("Cpanel.package_fields.package", true), "cpanel_package");
		$package->attach($fields->fieldSelect("meta[package]", $packages,
			$this->Html->ifSet($vars->meta['package']), array('id'=>"cpanel_package")));
		$fields->setField($package);		
		
		// Set the type of account (standard or reseller)
		if ($module_row && $module_row->meta->user_name == "root") {
			$type = $fields->label(Language::_("Cpanel.package_fields.type", true), "cpanel_type");
			$type_standard = $fields->label(Language::_("Cpanel.package_fields.type_standard", true), "cpanel_type_standard");
			$type_reseller = $fields->label(Language::_("Cpanel.package_fields.type_reseller", true), "cpanel_type_reseller");
			$type->attach($fields->fieldRadio("meta[type]", "standard",
				$this->Html->ifSet($vars->meta['type'], "standard") == "standard", array('id'=>"cpanel_type_standard"), $type_standard));
			$type->attach($fields->fieldRadio("meta[type]", "reseller",
				$this->Html->ifSet($vars->meta['type']) == "reseller", array('id'=>"cpanel_type_reseller"), $type_reseller));
			$fields->setField($type);
		}
		else {
			// Reseller must use the standard account type
			$type = $fields->fieldHidden("meta[type]", "standard");
			$fields->setField($type);
		}

		// Set the cPanel package as a selectable option
		$acl = $fields->label(Language::_("Cpanel.package_fields.acl", true), "cpanel_acl");
		$acl->attach($fields->fieldSelect("meta[acl]", $acls,
			$this->Html->ifSet($vars->meta['acl']), array('id'=>"cpanel_acl")));
		$fields->setField($acl);
		
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
			'module' => array('host_name', 'name_servers'),
			'package' => array('type', 'package', 'acl'),
			'service' => array('cpanel_username', 'cpanel_password', 'cpanel_domain')
		);
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
			
			// If not reseller, then no need to store ACL
			if ($vars['meta']['type'] != "reseller")
				unset($vars['meta']['acl']);
			
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
			
			// If not reseller, then no need to store ACL
			if ($vars['meta']['type'] != "reseller")
				unset($vars['meta']['acl']);
			
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "cpanel" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "cpanel" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "cpanel" . DS);
		
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
		$meta_fields = array("server_name", "host_name", "user_name", "key",
			"use_ssl", "account_limit", "name_servers", "notes");
		$encrypted_fields = array("user_name", "key");
		
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
		$meta_fields = array("server_name", "host_name", "user_name", "key",
			"use_ssl", "account_limit", "account_count", "name_servers", "notes");
		$encrypted_fields = array("user_name", "key");
		
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
	 * Deletes the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being deleted.
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 */
	public function deleteModuleRow($module_row) {
		
	}

	/**
	 * Returns the value used to identify a particular service
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @return string A value used to identify this service amongst other similar services
	 */
	public function getServiceName($service) {
		foreach ($service->fields as $field) {
			if ($field->key == "cpanel_domain")
				return $field->value;
		}
		return null;
	}
	
	/**
	 * Returns the value used to identify a particular package service which has
	 * not yet been made into a service. This may be used to uniquely identify
	 * an uncreated services of the same package (i.e. in an order form checkout)
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @return string The value used to identify this package service
	 * @see Module::getServiceName()
	 */
	public function getPackageServiceName($package, array $vars=null) {
		if (isset($vars['cpanel_domain']))
			return $vars['cpanel_domain'];
		return null;
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
		$domain = $fields->label(Language::_("Cpanel.service_field.domain", true), "cpanel_domain");
		// Create domain field and attach to domain label
		$domain->attach($fields->fieldText("cpanel_domain", $this->Html->ifSet($vars->cpanel_domain), array('id'=>"cpanel_domain")));
		// Set the label as a field
		$fields->setField($domain);
		
		// Create username label
		$username = $fields->label(Language::_("Cpanel.service_field.username", true), "cpanel_username");
		// Create username field and attach to username label
		$username->attach($fields->fieldText("cpanel_username", $this->Html->ifSet($vars->cpanel_username), array('id'=>"cpanel_username")));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Cpanel.service_field.tooltip.username", true));
		$username->attach($tooltip);
		// Set the label as a field
		$fields->setField($username);
		
		// Create password label
		$password = $fields->label(Language::_("Cpanel.service_field.password", true), "cpanel_password");
		// Create password field and attach to password label
		$password->attach($fields->fieldPassword("cpanel_password", array('id'=>"cpanel_password", 'value'=>$this->Html->ifSet($vars->cpanel_password))));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Cpanel.service_field.tooltip.password", true));
		$password->attach($tooltip);
		// Set the label as a field
		$fields->setField($password);
		
		// Confirm password label
		$confirm_password = $fields->label(Language::_("Cpanel.service_field.confirm_password", true), "cpanel_confirm_password");
		// Create confirm password field and attach to password label
		$confirm_password->attach($fields->fieldPassword("cpanel_confirm_password", array('id'=>"cpanel_confirm_password", 'value'=>$this->Html->ifSet($vars->cpanel_password))));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Cpanel.service_field.tooltip.password", true));
		$confirm_password->attach($tooltip);
		// Set the label as a field
		$fields->setField($confirm_password);
		
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
		$domain = $fields->label(Language::_("Cpanel.service_field.domain", true), "cpanel_domain");
		// Create domain field and attach to domain label
		$domain->attach($fields->fieldText("cpanel_domain", $this->Html->ifSet($vars->cpanel_domain, $this->Html->ifSet($vars->domain)), array('id'=>"cpanel_domain")));
		// Set the label as a field
		$fields->setField($domain);
		
		return $fields;
	}
	
	/**
	 * Returns all fields to display to an admin attempting to edit a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getAdminEditFields($package, $vars=null) {
		Loader::loadHelpers($this, array("Html"));
		
		$fields = new ModuleFields();
		
		// Create domain label
		$domain = $fields->label(Language::_("Cpanel.service_field.domain", true), "cpanel_domain");
		// Create domain field and attach to domain label
		$domain->attach($fields->fieldText("cpanel_domain", $this->Html->ifSet($vars->cpanel_domain), array('id'=>"cpanel_domain")));
		// Set the label as a field
		$fields->setField($domain);
		
		// Create username label
		$username = $fields->label(Language::_("Cpanel.service_field.username", true), "cpanel_username");
		// Create username field and attach to username label
		$username->attach($fields->fieldText("cpanel_username", $this->Html->ifSet($vars->cpanel_username), array('id'=>"cpanel_username")));
		// Set the label as a field
		$fields->setField($username);
		
		// Create password label
		$password = $fields->label(Language::_("Cpanel.service_field.password", true), "cpanel_password");
		// Create password field and attach to password label
		$password->attach($fields->fieldPassword("cpanel_password", array('id'=>"cpanel_password", 'value' => $this->Html->ifSet($vars->cpanel_password))));
		// Set the label as a field
		$fields->setField($password);
		
		return $fields;
	}
	
	/**
	 * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param boolean $edit True if this is an edit, false otherwise
	 * @return boolean True if the service validates, false otherwise. Sets Input errors when false.
	 */
	public function validateService($package, array $vars=null, $edit=false) {
		$rules = array(
			'cpanel_domain' => array(
				'format' => array(
					'rule' => array(array($this, "validateHostName")),
					'message' => Language::_("Cpanel.!error.cpanel_domain.format", true)
				),
				'test' => array(
					'rule' => array("substr_compare", "test", 0, 4, true),
					'message' => Language::_("Cpanel.!error.cpanel_domain.test", true)
				)
			),
			'cpanel_username' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("matches", "/^[a-z]([a-z0-9])*$/i"),
					'message' => Language::_("Cpanel.!error.cpanel_username.format", true)
				),
				'test' => array(
					'if_set' => true,
					'rule' => array("matches", "/^(?!test)/"),
					'message' => Language::_("Cpanel.!error.cpanel_username.test", true)
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("betweenLength", 1, 16),
					'message' => Language::_("Cpanel.!error.cpanel_username.length", true)
				)
			),
			'cpanel_password' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array("isPassword", 8),
					'message' => Language::_("Cpanel.!error.cpanel_password.valid", true),
					'last' => true
				),
			),
			'cpanel_confirm_password' => array(
				'matches' => array(
					'if_set' => true,
					'rule' => array("compares", "==", (isset($vars['cpanel_password']) ? $vars['cpanel_password'] : "")),
					'message' => Language::_("Cpanel.!error.cpanel_password.matches", true)
				)
			)
		);
		
		if (!isset($vars['cpanel_domain']) || strlen($vars['cpanel_domain']) < 4)
			unset($rules['cpanel_domain']['test']);
		
		// Set the values that may be empty
		$empty_values = array("cpanel_username", "cpanel_password");
		
		if ($edit) {
			// If this is an edit and no password given then don't evaluate password
			// since it won't be updated
			if (!array_key_exists('cpanel_password', $vars) || $vars['cpanel_password'] == "")
				unset($rules['cpanel_password']);
			
			// Validate domain if given
			$rules['cpanel_domain']['format']['if_set'] = true;
			$rules['cpanel_domain']['test']['if_set'] = true;
		}
		
		// Remove rules on empty fields
		foreach ($empty_values as $value) {
			if (empty($vars[$value]))
				unset($rules[$value]);
		}
		
		$this->Input->setRules($rules);
		return $this->Input->validates($vars);
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
		
		if (!$row) {
			$this->Input->setErrors(array('module_row' => array('missing' => Language::_("Cpanel.!error.module_row.missing", true))));
			return;
		}
		
		$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->key, $row->meta->use_ssl);
		
		// Generate username/password
		if (array_key_exists('cpanel_domain', $vars)) {
			Loader::loadModels($this, array("Clients"));
			
			// Generate a username
			if (empty($vars['cpanel_username']))
				$vars['cpanel_username'] = $this->generateUsername($vars['cpanel_domain']);
			
			// Generate a password
			if (empty($vars['cpanel_password'])) {
				$vars['cpanel_password'] = $this->generatePassword();
				$vars['cpanel_confirm_password'] = $vars['cpanel_password'];
			}
			
			// Use client's email address
			if (isset($vars['client_id']) && ($client = $this->Clients->get($vars['client_id'], false)))
				$vars['cpanel_email'] = $client->email;
		}
		
		$params = $this->getFieldsFromInput((array)$vars, $package);

		$this->validateService($package, $vars);
		
		if ($this->Input->errors())
			return;
		
		// Only provision the service if 'use_module' is true
		if ($vars['use_module'] == "true") {
			
			$masked_params = $params;
			$masked_params['password'] = "***";
			$this->log($row->meta->host_name . "|createacct", serialize($masked_params), "input", true);
			unset($masked_params);
			$result = $this->parseResponse($api->createacct($params));
			
			if ($this->Input->errors())
				return;
			
			// If reseller and we have an ACL set, update the reseller's ACL
			if ($package->meta->type == "reseller" && $package->meta->acl != "")
				$api->setacls(array('reseller' => $params['username'], 'acllist' => $package->meta->acl));
			
			// Update the number of accounts on the server
			$this->updateAccountCount($row);
		}
		
		// Return service fields
		return array(
			array(
				'key' => "cpanel_domain",
				'value' => $params['domain'],
				'encrypted' => 0
			),
			array(
				'key' => "cpanel_username",
				'value' => $params['username'],
				'encrypted' => 0
			),
			array(
				'key' => "cpanel_password",
				'value' => $params['password'],
				'encrypted' => 1
			),
			array(
				'key' => "cpanel_confirm_password",
				'value' => $params['password'],
				'encrypted' => 1
			)
		);
	}
	
	/**
	 * Edits the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being edited.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being edited (if the current service is an addon service)
	 * @return array A numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function editService($package, $service, array $vars=null, $parent_package=null, $parent_service=null) {
		$row = $this->getModuleRow();
		$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->key, $row->meta->use_ssl);
		
		$this->validateService($package, $vars, true);
		
		if ($this->Input->errors())
			return;
		
		$service_fields = $this->serviceFieldsToObject($service->fields);
		
		// Remove password if not being updated
		if (isset($vars['cpanel_password']) && $vars['cpanel_password'] == "")
			unset($vars['cpanel_password']);
		
		// Only update the service if 'use_module' is true
		if ($vars['use_module'] == "true") {
			
			// Check for fields that changed
			$delta = array();
			foreach ($vars as $key => $value) {
				if (!array_key_exists($key, $service_fields) || $vars[$key] != $service_fields->$key)
					$delta[$key] = $value;
			}

			// Update domain (if changed)
			if (isset($delta['cpanel_domain'])) {
				$params = array('domain' => $delta['cpanel_domain']);
				
				$this->log($row->meta->host_name . "|modifyacct", serialize($params), "input", true);
				$result = $this->parseResponse($api->modifyacct($service_fields->cpanel_username, $params));
			}
			
			// Update password (if changed)
			if (isset($delta['cpanel_password'])) {
				
				$this->log($row->meta->host_name . "|passwd", "***", "input", true);
				$result = $this->parseResponse($api->passwd($service_fields->cpanel_username, $delta['cpanel_password']));
			}
			
			// Update username (if changed), do last so we can always rely on $service_fields['cpanel_username'] to contain the username
			if (isset($delta['cpanel_username'])) {
				$params = array('newuser' => $delta['cpanel_username']);
				$this->log($row->meta->host_name . "|modifyacct", serialize($params), "input", true);
				$result = $this->parseResponse($api->modifyacct($service_fields->cpanel_username, $params));
			}
		}
        
        // Set fields to update locally
		$fields = array("cpanel_domain", "cpanel_username", "cpanel_password");
		foreach ($fields as $field) {
			if (property_exists($service_fields, $field) && isset($vars[$field]))
				$service_fields->{$field} = $vars[$field];
		}
        
        // Set the confirm password to the password
        $service_fields->cpanel_confirm_password = $service_fields->cpanel_password;
        
        // Return all the service fields
		$fields = array();
		$encrypted_fields = array("cpanel_password", "cpanel_confirm_password");
		foreach ($service_fields as $key => $value)
			$fields[] = array('key' => $key, 'value' => $value, 'encrypted' => (in_array($key, $encrypted_fields) ? 1 : 0));
		
		return $fields;
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
		// suspendacct / suspendreseller ($package->meta->type == "reseller")
		
		$row = $this->getModuleRow();
		
		if ($row) {
			$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->key, $row->meta->use_ssl);
			
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			if ($package->meta->type == "reseller") {
				$this->log($row->meta->host_name . "|suspendreseller", serialize($service_fields->cpanel_username), "input", true);
				$this->parseResponse($api->suspendreseller($service_fields->cpanel_username));
			}
			else {
				$this->log($row->meta->host_name . "|suspendacct", serialize($service_fields->cpanel_username), "input", true);
				$this->parseResponse($api->suspendacct($service_fields->cpanel_username));
			}
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
		// unsuspendacct / unsuspendreseller ($package->meta->type == "reseller")
		
		if (($row = $this->getModuleRow())) {
			$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->key, $row->meta->use_ssl);
			
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			if ($package->meta->type == "reseller") {
				$this->log($row->meta->host_name . "|unsuspendreseller", serialize($service_fields->cpanel_username), "input", true);
				$this->parseResponse($api->unsuspendreseller($service_fields->cpanel_username));
			}
			else {
				$this->log($row->meta->host_name . "|unsuspendacct", serialize($service_fields->cpanel_username), "input", true);
				$this->parseResponse($api->unsuspendacct($service_fields->cpanel_username));
			}
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
			$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->key, $row->meta->use_ssl);
			
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			if ($package->meta->type == "reseller") {
				$this->log($row->meta->host_name . "|terminatereseller", serialize($service_fields->cpanel_username), "input", true);
				$this->parseResponse($api->terminatereseller($service_fields->cpanel_username));
			}
			else {
				$this->log($row->meta->host_name . "|removeacct", serialize($service_fields->cpanel_username), "input", true);
				$this->parseResponse($api->removeacct($service_fields->cpanel_username));
			}
			
			// Update the number of accounts on the server
			$this->updateAccountCount($row);
		}
		return null;
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
			$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->key, $row->meta->use_ssl);
			
			// Only request a package change if it has changed
			if ($package_from->meta->package != $package_to->meta->package) {
				
				$service_fields = $this->serviceFieldsToObject($service->fields);
				
				$this->log($row->meta->host_name . "|changepackage", serialize(array($service_fields->cpanel_username, $package_to->meta->package)), "input", true);
				
				$this->parseResponse($api->changepackage($service_fields->cpanel_username, $package_to->meta->package));
			}
		}
		return null;
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "cpanel" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "cpanel" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("module_row", $row);
		$this->view->set("package", $package);
		$this->view->set("service", $service);
		$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));
		
		return $this->view->fetch();
	}
	
	/**
	 * Statistics tab (bandwidth/disk usage)
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabStats($package, $service, array $get=null, array $post=null, array $files=null) {
		$this->view = new View("tab_stats", "default");
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		
		$stats = $this->getStats($package, $service);

		$this->view->set("stats", $stats);
		$this->view->set("user_type", $package->meta->type);
		
		$this->view->setDefaultView("components" . DS . "modules" . DS . "cpanel" . DS);
		return $this->view->fetch();
	}
	
	/**
	 * Client Statistics tab (bandwidth/disk usage)
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabClientStats($package, $service, array $get=null, array $post=null, array $files=null) {
		$this->view = new View("tab_client_stats", "default");
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		
		$stats = $this->getStats($package, $service);

		$this->view->set("stats", $stats);
		$this->view->set("user_type", $package->meta->type);
		
		$this->view->setDefaultView("components" . DS . "modules" . DS . "cpanel" . DS);
		return $this->view->fetch();
	}
	
	/**
	 * Fetches all account stats
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @return stdClass A stdClass object representing all of the stats for the account
	 */
	private function getStats($package, $service) {
		$row = $this->getModuleRow();
		$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->key, $row->meta->use_ssl);
		
		$stats = new stdClass();
		$service_fields = $this->serviceFieldsToObject($service->fields);
		
		// Fetch account info
		$this->log($row->meta->host_name . "|accountsummary", serialize($service_fields->cpanel_username), "input", true);
		$stats->account_info = $this->parseResponse($api->accountsummary($service_fields->cpanel_username));
	
		$stats->disk_usage = array(
			'used' => null,
			'limit' => null
		);
		$stats->bandwidth_usage = array(
			'used' => null,
			'limit' => null
		);
		
		// Get bandwidth/disk for reseller user
		if ($package->meta->type == "reseller") {
			$this->log($row->meta->host_name . "|resellerstats", serialize($service_fields->cpanel_username), "input", true);
			
			$reseller_info = $this->parseResponse($api->resellerstats($service_fields->cpanel_username));
			
			if (isset($reseller_info->result)) {
				$stats->disk_usage['used'] = $reseller_info->result->diskused;
				$stats->disk_usage['limit'] = $reseller_info->result->diskquota;
				$stats->disk_usage['alloc'] = $reseller_info->result->totaldiskalloc;
				
				$stats->bandwidth_usage['used'] = $reseller_info->result->totalbwused;
				$stats->bandwidth_usage['limit'] = $reseller_info->result->bandwidthlimit;
				$stats->bandwidth_usage['alloc'] = $reseller_info->result->totalbwalloc;
			}
		}
		// Get bandwidth/disk for standard user
		else {
			$params = array(
				'search' => $service_fields->cpanel_username,
				'searchtype' => "user"
			);
			$this->log($row->meta->host_name . "|showbw", serialize($params), "input", true);
			$bw = $this->parseResponse($api->showbw($params));
			
			if (isset($bw->bandwidth[0]->acct[0])) {
				$stats->bandwidth_usage['used'] = $bw->bandwidth[0]->acct[0]->totalbytes/(1024*1024);
				$stats->bandwidth_usage['limit'] = $bw->bandwidth[0]->acct[0]->limit/(1024*1024);
			}
			
			if (isset($stats->account_info->acct[0])) {
				$stats->disk_usage['used'] = preg_replace("/[^0-9]/", "", $stats->account_info->acct[0]->diskused);
				$stats->disk_usage['limit'] = preg_replace("/[^0-9]/", "", $stats->account_info->acct[0]->disklimit);
			}
		}
		
		return $stats;
	}

    /**
	 * Client Actions (reset password)
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabClientActions($package, $service, array $get=null, array $post=null, array $files=null) {
		$this->view = new View("tab_client_actions", "default");
        $this->view->base_uri = $this->base_uri;
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$service_fields = $this->serviceFieldsToObject($service->fields);

        // Perform the password reset
        if (!empty($post)) {
            Loader::loadModels($this, array("Services"));
            $data = array(
                'cpanel_password' => $this->Html->ifSet($post['cpanel_password']),
                'cpanel_confirm_password' => $this->Html->ifSet($post['cpanel_confirm_password'])
            );
            $this->Services->edit($service->id, $data);

            if ($this->Services->errors())
                $this->Input->setErrors($this->Services->errors());

            $vars = (object)$post;
        }

        $this->view->set("service_fields", $service_fields);
        $this->view->set("service_id", $service->id);
        $this->view->set("vars", (isset($vars) ? $vars : new stdClass()));

		$this->view->setDefaultView("components" . DS . "modules" . DS . "cpanel" . DS);
		return $this->view->fetch();
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
		
		return $this->Input->matches($host_name, "/^([a-z0-9]|[a-z0-9][a-z0-9\-]{0,61}[a-z0-9])(\.([a-z0-9]|[a-z0-9][a-z0-9\-]{0,61}[a-z0-9]))+$/");
	}
	
	/**
	 * Validates that at least 2 name servers are set in the given array of name servers
	 *
	 * @param array $name_servers An array of name servers
	 * @return boolean True if the array count is >= 2, false otherwise
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
	 * Retrieves the accounts on the server
	 *
	 * @param stdClass $api The cPanel API
	 * @return mixed The number of cPanel accounts on the server, or false on error
	 */
	private function getAccountCount($api) {
		// Ready JSON
		$this->loadJson();
		$accounts = false;
		
		try {
			$output = $this->Json->decode($api->listaccts());
			
			if (isset($output->acct))
				$accounts = count($output->acct);
		}
		catch (Exception $e) {
			// Nothing to do
		}
		return $accounts;
	}
	
	/**
	 * Updates the module row meta number of accounts
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 */
	private function updateAccountCount($module_row) {
		$api = $this->getApi($module_row->meta->host_name, $module_row->meta->user_name, $module_row->meta->key, $module_row->meta->use_ssl);
		
		// Get the number of accounts on the server
		if (($count = $this->getAccountCount($api)) !== false) {
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
	 * Validates whether or not the connection details are valid by attempting to fetch
	 * the number of accounts that currently reside on the server
	 *
	 * @return boolean True if the connection is valid, false otherwise
	 */
	public function validateConnection($key, $host_name, $user_name, $use_ssl, &$account_count) {
		// Ready JSON
		$this->loadJson();
		
		try {
			$api = $this->getApi($host_name, $user_name, $key, $use_ssl);
			
			$count = $this->getAccountCount($api);
			if ($count !== false) {
				$account_count = $count;
				return true;
			}
		}
		catch (Exception $e) {
			// Trap any errors encountered, could not validate connection
		}
		return false;
	}
	
	/**
	 * Generates a username from the given host name
	 *
	 * @param string $host_name The host name to use to generate the username
	 * @return string The username generated from the given hostname
	 */
	private function generateUsername($host_name) {
		// Remove everything except letters and numbers from the domain
		// ensure no number appears in the beginning
		$username = ltrim(preg_replace('/[^a-z0-9]/i', '', $host_name), '0123456789');
		
		$length = strlen($username);
		$pool = "abcdefghijklmnopqrstuvwxyz0123456789";
		$pool_size = strlen($pool);
		
		if ($length < 5) {
			for ($i=$length; $i<8; $i++) {
				$username .= substr($pool, mt_rand(0, $pool_size-1), 1);
			}
			$length = strlen($username);
		}
		
		$username = substr($username, 0, min($length, 8));
		
		// Check for existing user accounts
		$account_matching_characters = 4; // [1,4]
		$accounts = $this->getUserAccounts(substr($username, 0, $account_matching_characters) . "(.*)");
		
		// Re-key the listings
		if (!empty($accounts)) {
			foreach ($accounts as $key => $account) {
				$accounts[$account->user] = $account;
				unset($accounts[$key]);
			}
			
			// Username exists, create another instead
			if (array_key_exists($username, $accounts)) {
				for ($i=0; $i<(int)str_repeat(9, $account_matching_characters); $i++) {
					$new_username = substr($username, 0, -$account_matching_characters) . $i;
					if (!array_key_exists($new_username, $accounts)) {
						$username = $new_username;
						break;
					}
				}
			}
		}
		
		return $username;
	}
	
	/**
	 * Retrieves matching user accounts
	 *
	 * @param string $name The account username (supports regex's)
	 * @return mixed An array of stdClass objects representing each user, or null if no user exists
	 */
	private function getUserAccounts($name) {
		// Ready JSON
		$this->loadJson();
		$user = null;
		
		$row = $this->getModuleRow();
		if ($row)
			$api = $this->getApi($row->meta->host_name, $row->meta->user_name, $row->meta->key, $row->meta->use_ssl);
		
		try {
			if ($api) {
				$output = $this->Json->decode($api->listaccts("user", $name));
				
				if (isset($output->acct))
					$user = $output->acct;
			}
		}
		catch (Exception $e) {
			// Nothing to do
		}
		
		return $user;
	}
	
	/**
	 * Generates a password
	 *
	 * @param int $min_length The minimum character length for the password (5 or larger)
	 * @param int $max_length The maximum character length for the password (14 or fewer)
	 * @return string The generated password
	 */
	private function generatePassword($min_length=10, $max_length=14) {
		$pool = "abcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()";
		$pool_size = strlen($pool);
		$length = mt_rand(max($min_length, 5), min($max_length, 14));
		$password = "";
		
		for ($i=0; $i<$length; $i++) {
			$password .= substr($pool, mt_rand(0, $pool_size-1), 1);
		}
		
		return $password;
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
			'domain' => isset($vars['cpanel_domain']) ? $vars['cpanel_domain'] : null,
			'username' => isset($vars['cpanel_username']) ? $vars['cpanel_username']: null,
			'password' => isset($vars['cpanel_password']) ? $vars['cpanel_password'] : null,
			'plan' => $package->meta->package,
			'reseller' => ($package->meta->type == "reseller" ? 1 : 0),
			'contactemail' => isset($vars['cpanel_email']) ? $vars['cpanel_email'] : null
		);
		
		return $fields;
	}
	
	/**
	 * Loads the JSON component into this object, making it ready to use
	 */
	private function loadJson() {
		if (!isset($this->Json) || !($this->Json instanceof Json))
			Loader::loadComponents($this, array("Json"));
	}
	
	/**
	 * Parses the response from the API into a stdClass object
	 *
	 * @param string $response The response from the API
	 * @return stdClass A stdClass object representing the response, void if the response was an error
	 */
	private function parseResponse($response) {
		// Ready JSON
		$this->loadJson();
		
		$row = $this->getModuleRow();
		
		$result = $this->Json->decode($response);
		$success = true;
		
		// Set internal error
		if (!$result) {
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Cpanel.!error.api.internal", true))));
			$success = false;
		}
		
		// Only some API requests return status, so only use it if its available
		if (isset($result->status) && $result->status == 0) {
			$this->Input->setErrors(array('api' => array('result' => $result->statusmsg)));
			$success = false;
		}
		elseif (isset($result->result) && is_array($result->result) && isset($result->result[0]->status) && $result->result[0]->status == 0) {
			$this->Input->setErrors(array('api' => array('result' => $result->result[0]->statusmsg)));
			$success = false;
		}
		elseif (isset($result->passwd) && is_array($result->passwd) && isset($result->passwd[0]->status) && $result->passwd[0]->status == 0) {
			$this->Input->setErrors(array('api' => array('result' => $result->passwd[0]->statusmsg)));
			$success = false;
		}
		elseif (isset($result->cpanelresult) && !empty($result->cpanelresult->error)) {
			$this->Input->setErrors(array('api' => array('error' => (isset($result->cpanelresult->data->reason) ? $result->cpanelresult->data->reason : $result->cpanelresult->error))));
			$success = false;
		}
		
		// Log the response
		$this->log($row->meta->host_name, $response, "output", $success);
		
		// Return if any errors encountered
		if (!$success)
			return;
		
		return $result;
	}
	
	/**
	 * Initializes the CpanelApi and returns an instance of that object with the given $host, $user, and $pass set
	 *
	 * @param string $host The host to the cPanel server
	 * @param string $user The user to connect as
	 * @param string $pass The hash-pased password to authenticate with
	 * @return CpanelApi The CpanelApi instance
	 */
	private function getApi($host, $user, $pass, $use_ssl = true) {
		Loader::load(dirname(__FILE__) . DS . "apis" . DS . "cpanel_api.php");
		
		$api = new CpanelApi($host);
		$api->set_user($user);
		$api->set_hash($pass);
		$api->set_output("json");
		$api->set_port(($use_ssl ? 2087 : 2086));
		$api->set_protocol("http" . ($use_ssl ? "s" : ""));
		
		return $api;
	}
	
	/**
	 * Fetches a listing of all packages configured in cPanel for the given server
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @return array An array of packages in key/value pair
	 */
	private function getCpanelPackages($module_row) {
		if (!isset($this->DataStructure))
			Loader::loadHelpers($this, array("DataStructure"));
		if (!isset($this->ArrayHelper))
			$this->ArrayHelper = $this->DataStructure->create("Array");
		
		$this->loadJson();
		$api = $this->getApi($module_row->meta->host_name, $module_row->meta->user_name, $module_row->meta->key, $module_row->meta->use_ssl);
		$packages = array();
        
		try {
            $this->log($module_row->meta->host_name . "|listpkgs", null, "input", true);
            $package_list = $api->listpkgs();
            $result = $this->Json->decode($package_list);
            
            $success = false;
            if (isset($result->package)) {
                $success = true;
                $packages = $this->ArrayHelper->numericToKey($result->package, "name", "name");
            }
			
            $this->log($module_row->meta->host_name, $package_list, "output", $success);
		}
		catch (Exception $e) {
			// API request failed
		}
        
        return $packages;
	}

	/**
	 * Fetches a listing of all ACLs configured in cPanel for the given server
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @return array An array of ACLS in key/value pair
	 */
	private function getCpanelAcls($module_row) {
		if (!isset($this->DataStructure))
			Loader::loadHelpers($this, array("DataStructure"));
		if (!isset($this->ArrayHelper))
			$this->ArrayHelper = $this->DataStructure->create("Array");
		
		$this->loadJson();
		$api = $this->getApi($module_row->meta->host_name, $module_row->meta->user_name, $module_row->meta->key, $module_row->meta->use_ssl);
		
		try {
			$keys = (array)$this->Json->decode($api->listacls())->acls;

			$acls = array();
			foreach ($keys as $key => $value) {
				$acls[$key] = $key;
			}
			return $acls;
		}
		catch (Exception $e) {
			// API request failed
		}
		
		return array();
	}
	
	/**
	 * Builds and returns the rules required to add/edit a module row (e.g. server)
	 *
	 * @param array $vars An array of key/value data pairs
	 * @return array An array of Input rules suitable for Input::setRules()
	 */
	private function getRowRules(&$vars) {
		$rules = array(
			'server_name'=>array(
				'valid'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Cpanel.!error.server_name_valid", true)
				)
			),
			'host_name'=>array(
				'valid'=>array(
					'rule'=>array(array($this, "validateHostName")),
					'message'=>Language::_("Cpanel.!error.host_name_valid", true)
				)
			),
			'user_name'=>array(
				'valid'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Cpanel.!error.user_name_valid", true)
				)
			),
			'key'=>array(
				'valid'=>array(
					'last'=>true,
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Cpanel.!error.remote_key_valid", true)
				),
				'valid_connection'=>array(
					'rule'=>array(array($this, "validateConnection"), $vars['host_name'], $vars['user_name'], $vars['use_ssl'], &$vars['account_count']),
					'message'=>Language::_("Cpanel.!error.remote_key_valid_connection", true)
				)
			),
			'account_limit'=>array(
				'valid'=>array(
					'rule'=>array("matches", "/^([0-9]+)?$/"),
					'message'=>Language::_("Cpanel.!error.account_limit_valid", true)
				)
			),
			'name_servers'=>array(
				'count'=>array(
					'rule'=>array(array($this, "validateNameServerCount")),
					'message'=>Language::_("Cpanel.!error.name_servers_count", true)
				),
				'valid'=>array(
					'rule'=>array(array($this, "validateNameServers")),
					'message'=>Language::_("Cpanel.!error.name_servers_valid", true)
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
				'valid' => array(
					'rule' => array("matches", "/^(standard|reseller)$/"),
					'message' => Language::_("Cpanel.!error.meta[type].valid", true), // type must be standard or reseller
				)
			),
			'meta[package]' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Cpanel.!error.meta[package].empty", true) // package must be given
				)
			)
		);
		
		return $rules;
	}
}
?>