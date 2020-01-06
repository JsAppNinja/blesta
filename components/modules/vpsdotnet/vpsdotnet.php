<?php
/**
 * VPS.NET Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.vpsdotnet
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Vpsdotnet extends Module {
	
	/**
	 * @var string The version of this module
	 */
	private static $version = "2.1.3";
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
		Language::loadLang("vpsdotnet", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this module
	 *
	 * @return string The common name of this module
	 */
	public function getName() {
		return Language::_("Vpsdotnet.name", true);
	}
	
	/**
	 * Returns the version of this module
	 *
	 * @return string The current version of this module
	 */
	public function getVersion() {
		return self::$version;
	}

	/**
	 * Returns the name and URL for the authors of this module
	 *
	 * @return array A numerically indexed array that contains an array with key/value pairs for 'name' and 'url', representing the name and URL of the authors of this module
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Returns the value used to identify a particular service
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @return string A value used to identify this service amongst other similar services
	 */
	public function getServiceName($service) {
		foreach ($service->fields as $field) {
			if ($field->key == "vpsdotnet_hostname")
				return $field->value;
		}
		return null;
	}
	
	/**
	 * Returns a noun used to refer to a module row (e.g. "Server", "VPS", "Reseller Account", etc.)
	 *
	 * @return string The noun used to refer to a module row
	 */
	public function moduleRowName() {
		return Language::_("Vpsdotnet.module_row", true);
	}
	
	/**
	 * Returns a noun used to refer to a module row in plural form (e.g. "Servers", "VPSs", "Reseller Accounts", etc.)
	 *
	 * @return string The noun used to refer to a module row in plural form
	 */
	public function moduleRowNamePlural() {
		return Language::_("Vpsdotnet.module_row_plural", true);
	}
	
	/**
	 * Returns a noun used to refer to a module group (e.g. "Server Group", "Cloud", etc.)
	 *
	 * @return string The noun used to refer to a module group
	 */
	public function moduleGroupName() {
		return Language::_("Vpsdotnet.module_group", true);
	}
	
	/**
	 * Returns the key used to identify the primary field from the set of module row meta fields.
	 * This value can be any of the module row meta fields.
	 *
	 * @return string The key used to identify the primary field from the set of module row meta fields
	 */
	public function moduleRowMetaKey() {
		return "server_name";
	}
	
	/**
	 * Returns the value used to identify a particular package service which has
	 * not yet been made into a service. This may be used to uniquely identify
	 * an uncreated service of the same package (i.e. in an order form checkout)
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @return string The value used to identify this package service
	 * @see Module::getServiceName()
	 */
	public function getPackageServiceName($packages, array $vars=null) {
		if (isset($vars['vpsdotnet_hostname']))
			return $vars['vpsdotnet_hostname'];
		return null;
	}
	
	/**
	 * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @return boolean True if the service validates, false otherwise. Sets Input errors when false.
	 */
	public function validateService($package, array $vars=null, $edit=false) {
		if (!isset($this->Html))
			Loader::loadHelpers($this, array("Html"));
		
		$temp_vars = $vars;
		
		// Set cloud, template group, and template from the package if set
		if ($this->Html->ifSet($package->meta->cloud))
			$temp_vars['vpsdotnet_cloud'] = $package->meta->cloud;
		if ($this->Html->ifSet($package->meta->template_group))
			$temp_vars['vpsdotnet_template_group'] = $package->meta->template_group;
		if ($this->Html->ifSet($package->meta->template))
			$temp_vars['vpsdotnet_template'] = $package->meta->template;
		
		$rules = array(
			'vpsdotnet_hostname' => array(
				'format' => array(
					'rule' => array(array($this, "validateHostName")),
					'message' => Language::_("Vpsdotnet.!error.vpsdotnet_hostname.format", true)
				)
			),
			'vpsdotnet_label' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Vpsdotnet.!error.vpsdotnet_label.empty", true)
				),
				'format' => array(
					'rule' => array("matches", "/^[a-z0-9 \.,]*$/i"),
					'message' => Language::_("Vpsdotnet.!error.vpsdotnet_label.format", true)
				)
			),
			#
			# TODO: may want to improve error checking by validating each zone, OS, and template are valid values
			#
			'vpsdotnet_cloud' => array(
				'format' => array(
					// Cloud integer ID must be set
					'rule' => array("matches", "/^[0-9]+$/"),
					'message' => Language::_("Vpsdotnet.!error.vpsdotnet_cloud.format", true)
				)
			),
			'vpsdotnet_template_group' => array(
				'format' => array(
					// OS must be set
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Vpsdotnet.!error.vpsdotnet_template_group.format", true)
				)
			),
			'vpsdotnet_template' => array(
				'format' => array(
					// Template ID must be set
					'rule' => array("matches", "/^[0-9]+$/"),
					'message' => Language::_("Vpsdotnet.!error.vpsdotnet_template.format", true)
				)
			)
		);
		
		if ($edit) {
			// Set rules to optional
			foreach ($rules as $name => $rule) {
				foreach ($rule as $index => $rule_format) {
					$rules[$name][$index]['if_set'] = true;
				}
			}
		}
		
		$this->Input->setRules($rules);
		return $this->Input->validates($temp_vars);
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
		// Load the API
		$row = $this->getModuleRow();
		
		// Get the fields for the service
		$params = $this->getFieldsFromInput($vars, $package);
		
		// Validate the service-specific fields
		$this->validateService($package, $vars);
		
		if ($this->Input->errors())
			return;
		
		// Only provision the service if 'use_module' is true
		if ($vars['use_module'] == "true") {
			// Create the VPS
			$result = $this->addVps($row, $params);
			
			if ($this->Input->errors())
				return;
		}
		
		// Set fields to store for the service from the response
		$service_params = array();
		if (isset($result) && $result && property_exists($result, "virtual_machine")) {
			// Set information about the primary IP address
			if (property_exists($result->virtual_machine, "primary_ip_address") &&
				property_exists($result->virtual_machine->primary_ip_address, "ip_address")) {
				if (property_exists($result->virtual_machine->primary_ip_address->ip_address, "ip_address")) {
					$service_params['primary_ip_address'] = $result->virtual_machine->primary_ip_address->ip_address->ip_address;
				}
			}
			
			// Set additional information about the VPS
			$service_params['consumer_id'] = $result->virtual_machine->consumer_id;
			$service_params['password'] = $result->virtual_machine->password;
			$service_params['id'] = $result->virtual_machine->id;
		}
		
		// Return service fields
		return array(
			array(
				'key' => "vpsdotnet_hostname",
				'value' => $params['hostname'],
				'encrypted' => 0
			),
			array(
				'key' => "vpsdotnet_label",
				'value' => $params['label'],
				'encrypted' => 0
			),
			array(
				'key' => "vpsdotnet_cloud",
				'value' => $params['cloud_id'],
				'encrypted' => 0
			),
			array(
				'key' => "vpsdotnet_template",
				'value' => $params['system_template_id'],
				'encrypted' => 0
			),
			array(
				'key' => "vpsdotnet_template_group",
				'value' => $params['operating_system'],
				'encrypted' => 0
			),
			array(
				'key' => "vpsdotnet_nodes",
				'value' => $params['slices_required'],
				'encrypted' => 0
			),
			array(
				'key' => "vpsdotnet_primary_ip_address",
				'value' => (isset($service_params['primary_ip_address']) ? $service_params['primary_ip_address'] : ""),
				'encrypted' => 0
			),
			array(
				'key' => "vpsdotnet_consumer_id",
				'value' => (isset($service_params['consumer_id']) ? $service_params['consumer_id'] : ""),
				'encrypted' => 0
			),
			array(
				'key' => "vpsdotnet_password",
				'value' => (isset($service_params['password']) ? $service_params['password'] : ""),
				'encrypted' => 1
			),
			array(
				'key' => "vpsdotnet_id",
				'value' => (isset($service_params['id']) ? $service_params['id'] : ""),
				'encrypted' => 0
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
		// Load the API
		$module_row = $this->getModuleRow();
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		
		// Validate the service-specific fields
		$this->validateService($package, $vars, true);
		
		if ($this->Input->errors())
			return;
		
		// Get the service fields
		$service_fields = $this->serviceFieldsToObject($service->fields);
		
		// Only provision the service if 'use_module' is true
		if ($vars['use_module'] == "true") {
			
			// Update the VPS
			$delta = $this->updateVps($module_row, $service_fields, $vars);
			
			if ($this->Input->errors())
				return;
			
			// Update the service fields that have changed
			foreach ($delta as $key => $value) {
				if (property_exists($service_fields, $key) && $value != $service_fields->{$key})
					$service_fields->{$key} = $value;
			}
		}
		
		// Return all the service fields
		$fields = array();
		$encrypted_fields = array("vpsdotnet_password");
		foreach ($service_fields as $key => $value)
			$fields[] = array('key' => $key, 'value' => $value, 'encrypted' => (in_array($key, $encrypted_fields) ? 1 : 0));
		
		return $fields;
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
		if (($module_row = $this->getModuleRow())) {
			// Load the API
			$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
			
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			// Load the Virtual Machine
			$vm = new VirtualMachine();
			$vm->loadFully($service_fields->vpsdotnet_id);
			
			// Remove the VM and do not delete the nodes
			$delete_nodes = false;
			$params = array($delete_nodes);
			$this->log($module_row->meta->email . "|remove", serialize($params), "input", true);
			$response = $this->callApi($vm, "remove", $params);
			
			// Set any error
			$success = true;
			if (empty($response['result']) || !empty($response['error'])) {
				$error = (empty($response['result']) && empty($response['error']) ? Language::_("Vpsdotnet.!error.vps.cancel_failed", true) : $response['error']);
				$this->Input->setErrors(array('vm' => array('api' => $error)));
				$success = false;
			}
			
			$this->log($module_row->meta->email, serialize($response), "output", $success);
		}
		return null;
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
			$api = $this->getApi($row->meta->email, $row->meta->key);
			
			// Get the service fields
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			// Shutdown the server
			$response = $this->performAction("shutdown", $service_fields->vpsdotnet_id, $row);
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
			$api = $this->getApi($row->meta->email, $row->meta->key);
			
			// Get the service fields
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			// Boot the server
			$response = $this->performAction("power_on", $service_fields->vpsdotnet_id, $row);
		}
		
		return null;
	}
	
	/**
	 * Allows the module to perform an action when the service is ready to renew.
	 * Sets Input errors on failure, preventing the service from renewing.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being renewed (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function renewService($package, $service, $parent_package=null, $parent_service=null) {
		// Nothing to do
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
		// Nothing to do
		return null;
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
		$this->Input->setRules($this->getPackageRules($vars));
		
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
		$this->Input->setRules($this->getPackageRules($vars));
		
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
	 * Deletes the package on the remote server. Sets Input errors on failure,
	 * preventing the package from being deleted.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function deletePackage($package) {
		// Nothing to do
		return null;
	}
	
	/**
	 * Returns the rendered view of the manage module page
	 *
	 * @param mixed $module A stdClass object representing the module and its rows
	 * @param array $vars An array of post data submitted to or on the manage module page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the manager module page
	 */
	public function manageModule($module, array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("manage", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "vpsdotnet" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "vpsdotnet" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "vpsdotnet" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		if (empty($vars))
			$vars = $module_row->meta;
		
		$this->view->set("vars", (object)$vars);
		return $this->view->fetch();
	}
	
	/**
	 * Adds the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being added.
	 *
	 * @param array $vars An array of module info to add
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function addModuleRow(array &$vars) {
		$meta_fields = array("server_name", "email", "key");
		$encrypted_fields = array("email", "key");
		
		$this->Input->setRules($this->getRowRules($vars));
		
		// Validate module row
		if ($this->Input->validates($vars)) {

			// Build the meta data for this row
			$meta = array();
			foreach ($vars as $key => $value) {
				
				if (in_array($key, $meta_fields)) {
					$meta[] = array(
						'key' => $key,
						'value' => $value,
						'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
					);
				}
			}
			
			return $meta;
		}
	}
	
	/**
	 * Edits the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being updated.
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 * @param array $vars An array of module info to update
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function editModuleRow($module_row, array &$vars) {
		// Same as adding
		return $this->addModuleRow($vars);
	}
	
	/**
	 * Deletes the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being deleted.
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 */
	public function deleteModuleRow($module_row) {
		// Nothing to do
		return null;
	}
	
	/**
	 * Returns an array of available service delegation order methods. The module
	 * will determine how each method is defined. For example, the method "first"
	 * may be implemented such that it returns the module row with the least number
	 * of services assigned to it.
	 *
	 * @return array An array of order methods in key/value pairs where the key is the type to be stored for the group and value is the name for that option
	 * @see Module::selectModuleRow()
	 */
	public function getGroupOrderOptions() {
		return array('first'=>Language::_("Vpsdotnet.order_options.first", true));
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
		Loader::loadHelpers($this, array("Form", "Html"));
		
		$please_select = array('' => Language::_("AppController.select.please", true));
		$clouds = $please_select;
		$template_groups = $please_select;
		$templates = $please_select;
		
		// Fetch all packages available for the given server or server group
		$module_row = $this->getModuleRowByServer((isset($vars->module_row) ? $vars->module_row : 0), (isset($vars->module_group) ? $vars->module_group : ""));
		
		// Fetch clouds, OSs, templates
		if ($module_row) {
			// Fetch clouds
			if ($this->Html->ifSet($vars->meta['set_cloud']) == "admin") {
				$clouds = $please_select + $this->getClouds($module_row);
			}
			
			// Fetch template groups (operating systems) based on cloud
			if ($this->Html->ifSet($vars->meta['set_template_group']) == "admin" && $this->Html->ifSet($vars->meta['cloud'], false)) {
				$template_groups = $please_select + $this->getTemplateGroups($module_row, $vars->meta['cloud']);
			}
			
			// Fetch templates based on template group
			if ($this->Html->ifSet($vars->meta['set_template']) == "admin" && $this->Html->ifSet($vars->meta['cloud'], false) && $this->Html->ifSet($vars->meta['template_group'], false)) {
				$all_templates = $this->getTemplates($module_row, $vars->meta['cloud'], $vars->meta['template_group']);
				
				if (!empty($all_templates))
					$templates = $please_select + $all_templates;
			}
		}
		
		$fields = new ModuleFields();
		$this->Form->setOutput(true);
		$fields->setHtml("
			<script type=\"text/javascript\">
				$(document).ready(function() {
					$('.vpsdotnet_chosen_cloud, .vpsdotnet_chosen_template_group, .vpsdotnet_chosen_template, #vpsdotnet_template_group, #vpsdotnet_cloud').change(function() {
						fetchModuleOptions();
					});
				});
			</script>
		");
		
		// Allow ajax requests
		$ajax = $fields->fieldHidden("allow_ajax", "true", array('id'=>"vpsdotnet_allow_ajax"));
		$fields->setField($ajax);
		
		// Set the number of nodes
		$nodes = $fields->label(Language::_("Vpsdotnet.package_fields.number_of_nodes", true), "vpsdotnet_number_of_nodes");
		$nodes->attach($fields->fieldText("meta[number_of_nodes]",
			$this->Html->ifSet($vars->meta['number_of_nodes']), array('id'=>"vpsdotnet_number_of_nodes", 'class' => "small")));
		$fields->setField($nodes);
		
		// Set field whether client or admin may choose cloud
		$set_cloud = $fields->label("", "vpsdotnet_client_set_cloud");
		$admin_set_cloud = $fields->label(Language::_("Vpsdotnet.package_fields.admin_set_cloud", true), "vpsdotnet_admin_set_cloud");
		$client_set_cloud = $fields->label(Language::_("Vpsdotnet.package_fields.client_set_cloud", true), "vpsdotnet_client_set_cloud");
		$set_cloud->attach($fields->fieldRadio("meta[set_cloud]", "client",
			$this->Html->ifSet($vars->meta['set_cloud'], "client") == "client", array('id' => "vpsdotnet_client_set_cloud", 'class' => "vpsdotnet_chosen_cloud"), $client_set_cloud));
		$set_cloud->attach($fields->fieldRadio("meta[set_cloud]", "admin",
			$this->Html->ifSet($vars->meta['set_cloud']) == "admin", array('id' => "vpsdotnet_admin_set_cloud", 'class' => "vpsdotnet_chosen_cloud"), $admin_set_cloud));
		$fields->setField($set_cloud);
		
		if ($this->Html->ifSet($vars->meta['set_cloud']) == "admin") {
			// Set clouds that admin may choose from
			$cloud = $fields->label(Language::_("Vpsdotnet.package_fields.cloud", true), "vpsdotnet_cloud");
			$cloud->attach($fields->fieldSelect("meta[cloud]", $clouds,
				$this->Html->ifSet($vars->meta['cloud']), array('id'=>"vpsdotnet_cloud")));
			$fields->setField($cloud);
			
			if ($this->Html->ifSet($vars->meta['cloud'])) {
				// Set field whether client or admin may choose template group (operating system)
				$set_template_group = $fields->label("", "vpsdotnet_client_set_template_group");
				$admin_set_template = $fields->label(Language::_("Vpsdotnet.package_fields.admin_set_template_group", true), "vpsdotnet_admin_set_template_group");
				$client_set_template = $fields->label(Language::_("Vpsdotnet.package_fields.client_set_template_group", true), "vpsdotnet_client_set_template_group");
				$set_template_group->attach($fields->fieldRadio("meta[set_template_group]", "client",
					$this->Html->ifSet($vars->meta['set_template_group'], "client") == "client", array('id' => "vpsdotnet_client_set_template_group", 'class' => "vpsdotnet_chosen_template_group"), $client_set_template));
				$set_template_group->attach($fields->fieldRadio("meta[set_template_group]", "admin",
					$this->Html->ifSet($vars->meta['set_template_group']) == "admin", array('id' => "vpsdotnet_admin_set_template_group", 'class' => "vpsdotnet_chosen_template_group"), $admin_set_template));
				$fields->setField($set_template_group);
				
				// Set template groups (operating systems)
				if ($this->Html->ifSet($vars->meta['set_template_group']) == "admin") {
					// Set template groups (operating systems) that admin may choose from
					$group = $fields->label(Language::_("Vpsdotnet.package_fields.template_group", true), "vpsdotnet_template_group");
					$group->attach($fields->fieldSelect("meta[template_group]", $template_groups,
						$this->Html->ifSet($vars->meta['template_group']), array('id'=>"vpsdotnet_template_group")));
					$fields->setField($group);
					
					if ($this->Html->ifSet($vars->meta['template_group'])) {
						// Set field whether client or admin may choose template
						$set_template = $fields->label("", "vpsdotnet_client_set_template");
						$admin_set_template = $fields->label(Language::_("Vpsdotnet.package_fields.admin_set_template", true), "vpsdotnet_admin_set_template");
						$client_set_template = $fields->label(Language::_("Vpsdotnet.package_fields.client_set_template", true), "vpsdotnet_client_set_template");
						$set_template->attach($fields->fieldRadio("meta[set_template]", "client",
							$this->Html->ifSet($vars->meta['set_template'], "client") == "client", array('id' => "vpsdotnet_client_set_template", 'class' => "vpsdotnet_chosen_template"), $client_set_template));
						$set_template->attach($fields->fieldRadio("meta[set_template]", "admin",
							$this->Html->ifSet($vars->meta['set_template']) == "admin", array('id' => "vpsdotnet_admin_set_template", 'class' => "vpsdotnet_chosen_template"), $admin_set_template));
						$fields->setField($set_template);
						
						// Set templates that admin may choose from
						if ($this->Html->ifSet($vars->meta['set_template']) == "admin") {
							// Set templates that admin may choose from
							$template = $fields->label(Language::_("Vpsdotnet.package_fields.template", true), "vpsdotnet_template");
							$template->attach($fields->fieldSelect("meta[template]", $templates,
								$this->Html->ifSet($vars->meta['template']), array('id'=>"vpsdotnet_template")));
							$fields->setField($template);
						}
					}
				}
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
			'module' => array(),
			'package' => array(),
			'service' => array("vpsdotnet_hostname", "vpsdotnet_label", "vpsdotnet_cloud",
				"vpsdotnet_template", "vpsdotnet_template_group", "vpsdotnet_primary_ip_address",
				"vpsdotnet_nodes", "vpsdotnet_id", "vpsdotnet_password"
			)
		);
	}
	
	/**
	 * Returns all fields to display to an admin attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @param boolean $client True if the request is coming from the client interface, false otherwise (optional, default false)
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getAdminAddFields($package, $vars=null, $client=false) {
		Loader::loadHelpers($this, array("Form", "Html"));
		
		// Fetch the module row available for this package
		$module_row = $this->getModuleRowByServer((isset($package->module_row) ? $package->module_row : 0), (isset($package->module_group) ? $package->module_group : ""));
		$module = $this->getModule();
		
		// Determine which fields can be chosen that is not already set in the package
		// assuming the hierarchy: Cloud => Template Group => Template
		$choose_fields = array();
		$please_select = array('' => Language::_("AppController.select.please", true));
		
		if ($this->Html->ifSet($package->meta->set_template) == "client")
			$choose_fields = array("template");
		if ($this->Html->ifSet($package->meta->set_template_group) == "client")
			$choose_fields = array("template_group", "template");
		if ($this->Html->ifSet($package->meta->set_cloud) == "client")
			$choose_fields = array("cloud", "template_group", "template");
		
		$fields = new ModuleFields();
		$this->Form->setOutput(true);
		
		// Set the appropriate javascript
		$fields->setHtml("
			<script type=\"text/javascript\">
				$(document).ready(function() {
                    $('#vpsdotnet_cloud, #vpsdotnet_template_group').change(function() {
						var form = $(this).closest('form');
						$(form).append('<input type=\"hidden\" name=\"refresh_fields\" value=\"true\">');
						$(form).submit();
					});
				});
			</script>
		");
		
		// Create the hostname label
		$host_name = $fields->label(Language::_("Vpsdotnet.service_field.vpsdotnet_hostname", true), "vpsdotnet_hostname");
		// Create hostname field and attach to hostname label
		$host_name->attach($fields->fieldText("vpsdotnet_hostname", $this->Html->ifSet($vars->vpsdotnet_hostname, $this->Html->ifSet($vars->domain)), array('id'=>"vpsdotnet_hostname")));
		// Set the label as a field
		$fields->setField($host_name);
		
		// Create the label used to identify the virtual machine
		$label = $fields->label(Language::_("Vpsdotnet.service_field.vpsdotnet_label", true), "vpsdotnet_label");
		// Create hostname field and attach to the label
		$label->attach($fields->fieldText("vpsdotnet_label", $this->Html->ifSet($vars->vpsdotnet_label), array('id'=>"vpsdotnet_label")));
		// Set the label as a field
		$fields->setField($label);
		
		// Set the cloud field
		if (in_array("cloud", $choose_fields)) {
			// Set clouds to choose from
			$clouds = $please_select + $this->getClouds($module_row);
			
			$cloud = $fields->label(Language::_("Vpsdotnet.service_field.vpsdotnet_cloud", true), "vpsdotnet_cloud");
			$cloud->attach($fields->fieldSelect("vpsdotnet_cloud", $clouds,
				$this->Html->ifSet($vars->vpsdotnet_cloud), array('id'=>"vpsdotnet_cloud")));
			$fields->setField($cloud);
		}
		
		// Set the operating system field
		$cloud = $this->Html->ifSet($package->meta->cloud, $this->Html->ifSet($vars->vpsdotnet_cloud));
		if (in_array("template_group", $choose_fields) && !empty($cloud)) {
			// Set template groups to choose from
			$template_groups = $please_select + $this->getTemplateGroups($module_row, $cloud);
			
			$group = $fields->label(Language::_("Vpsdotnet.service_field.vpsdotnet_template_group", true), "vpsdotnet_template_group");
			$group->attach($fields->fieldSelect("vpsdotnet_template_group", $template_groups,
				$this->Html->ifSet($vars->vpsdotnet_template_group), array('id'=>"vpsdotnet_template_group")));
			$fields->setField($group);
		}
		
		// Set the template field
		$template_group = $this->Html->ifSet($package->meta->template_group, $this->Html->ifSet($vars->vpsdotnet_template_group));
		if (in_array("template", $choose_fields) && !empty($cloud) && !empty($template_group)) {
			// Set templates to choose from
			$all_templates = $this->getTemplates($module_row, $cloud, $template_group);
			$templates = $please_select;
			if (!empty($all_templates))
				$templates = $please_select + $all_templates;
			
			$group = $fields->label(Language::_("Vpsdotnet.service_field.vpsdotnet_template", true), "vpsdotnet_template");
			$group->attach($fields->fieldSelect("vpsdotnet_template", $templates,
				$this->Html->ifSet($vars->vpsdotnet_template), array('id'=>"vpsdotnet_template")));
			$fields->setField($group);
		}
		
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
		// Same as admin fields
		return $this->getAdminAddFields($package, $vars, true);
	}
	
	/**
	 * Returns all fields to display to an admin attempting to edit a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getAdminEditFields($package, $vars=null) {
		// No fields
		return new ModuleFields();
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "vpsdotnet" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "vpsdotnet" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("module_row", $row);
		$this->view->set("package", $package);
		$this->view->set("service", $service);
		$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));
		
		return $this->view->fetch();
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
			'tabActions' => Language::_("Vpsdotnet.tab_actions", true),
			'tabConsole' => Language::_("Vpsdotnet.tab_console", true),
		);
	}
	
	/**
	 * Returns all tabs to display to an admin when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 */
	public function getClientTabs($package) {
		return array(
			'tabClientActions' => Language::_("Vpsdotnet.tab_client_actions", true),
			'tabClientConsole' => Language::_("Vpsdotnet.tab_client_console", true),
		);
	}
	
	/**
	 * Actions tab for the admin interface (boot, reboot, shutdown, etc.)
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabConsole($package, $service, array $get=null, array $post=null, array $files=null) {
		$view = $this->getConsoleTab($package, $service, $get, $post, $files);
		return $view->fetch();
	}
	
	/**
	 * Actions tab for the client interface (boot, reboot, shutdown, etc.)
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabClientConsole($package, $service, array $get=null, array $post=null, array $files=null) {
		$view = $this->getConsoleTab($package, $service, $get, $post, $files, true);
		return $view->fetch();
	}
	
	/**
	 * Builds the console tab content
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @param boolean $client True for the client template, or false for the admin template (optional, default false)
	 * @return string The string representing the contents of this tab
	 */
	private function getConsoleTab($package, $service, array $get=null, array $post=null, $files=null, $client=false) {
		// Determine template
		$template = ($client ? "tab_client_console" : "tab_console");
		
		$this->view = new View($template, "default");
		$this->view->base_uri = $this->base_uri;
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Html"));
		
		// Get the service fields
		$service_fields = $this->serviceFieldsToObject($service->fields);
		$module_row = $this->getModuleRow($package->module_row);
		
		$this->view->set("console", $this->getConsole($module_row, $service_fields->vpsdotnet_id));
		$this->view->set("service_fields", $service_fields);
		
		$this->view->setDefaultView("components" . DS . "modules" . DS . "vpsdotnet" . DS);
		return $this->view;
	}
	
	/**
	 * Actions tab for the admin interface (boot, reboot, shutdown, etc.)
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabActions($package, $service, array $get=null, array $post=null, array $files=null) {
		$view = $this->getActionTab($package, $service, $get, $post, $files);
		return $view->fetch();
	}
	
	/**
	 * Actions tab for the client interface (boot, reboot, shutdown, etc.)
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabClientActions($package, $service, array $get=null, array $post=null, array $files=null) {
		$view = $this->getActionTab($package, $service, $get, $post, $files, true);
		return $view->fetch();
	}
	
	/**
	 * Builds the action tab content
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @param boolean $client True for the client template, or false for the admin template (optional, default false)
	 * @return string The string representing the contents of this tab
	 */
	private function getActionTab($package, $service, array $get=null, array $post=null, $files=null, $client=false) {
		$get_key = ($client ? 2 : 3);
		
		// Handle AJAX request for fetching templates
		if (isset($get[$get_key]) && strtolower($get[$get_key]) == "gettemplatesfromgroup")
			return $this->getTemplatesFromGroup($package, $service, $get, $post, $files, $client);
		
		// Determine template
		$template = ($client ? "tab_client_actions" : "tab_actions");
		
		$this->view = new View($template, "default");
		$this->view->base_uri = $this->base_uri;
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		
		// Get the service fields
		$service_fields = $this->serviceFieldsToObject($service->fields);
		$module_row = $this->getModuleRow($package->module_row);
		
		// Get template groups (operating systems)
		$template_groups = $this->getTemplateGroups($module_row, $service_fields->vpsdotnet_cloud);
		
		// Get templates
		$template_group = (isset($post['vpsdotnet_template_group']) ? $post['vpsdotnet_template_group'] : $service_fields->vpsdotnet_template_group);
		$templates = $this->getTemplates($module_row, $service_fields->vpsdotnet_cloud, $template_group);
		
		// Perform the actions
		$vars = $this->actionsTab($package, $service, $templates, $template_groups, $client, $get, $post);
		
		// Set default vars
		$vars = array_merge(
			array(
				'vpsdotnet_template_group' => $service_fields->vpsdotnet_template_group,
				'vpsdotnet_template' => $service_fields->vpsdotnet_template,
				'vpsdotnet_hostname' => $service_fields->vpsdotnet_hostname
			),
			$vars
		);
		
		// Fetch the server status and templates
		$this->view->set("server_state", $this->getServerState($module_row, $service_fields->vpsdotnet_id));
		$this->view->set("templates", $templates);
		$this->view->set("template_groups", $template_groups);
		
		$this->view->set("vars", (object)$vars);
		$this->view->set("client_id", $service->client_id);
		$this->view->set("service_id", $service->id);
		$this->view->set("module_row_id", $package->module_row);
		$this->view->set("cloud_id", $service_fields->vpsdotnet_cloud);
		
		$this->view->set("view", $this->view->view);
		$this->view->setDefaultView("components" . DS . "modules" . DS . "vpsdotnet" . DS);
		return $this->view;
	}
	
	/**
	 * AJAX Retrieves a key/value list of templates for a given group (OS)
	 *
	 * @return array A key/value list of template names and their IDs
	 */
	private function getTemplatesFromGroup($package, $service, array $get=null, array $post=null, array $files=null, $client=false) {
		// Determine which get values we expect based on client
		$module_index = ($client ? 3 : 4);
		$cloud_index = ($client ? 4 : 5);
		$group_index = ($client ? 5 : 6);
		
		// Must be a valid request
		if (!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == "xmlhttprequest") ||
			empty($get[$module_index]) || empty($get[$cloud_index]) || empty($get[$group_index]) ||
			!($module_row = $this->getModuleRow($get[$module_index])) || !property_exists($module_row, "meta") ||
			!property_exists($module_row->meta, "email") || !property_exists($module_row->meta, "key")) {
			header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : "HTTP/1.0") . " 403 Forbidden");
			exit();
		}
		
		Loader::loadComponents($this, array("Json"));
		
		echo $this->Json->encode($this->getTemplates($module_row, $get[$cloud_index], $get[$group_index]));
		die;
	}
	
	/**
	 * Retrieves the status of the virtual machine
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param int $server_id The ID of the virtual machine
	 * @return string The status of the server. One of:
	 * 	- online Server is up and running
	 * 	- offline Server is not running
	 * 	- pending Server is performing a power action
	 * 	- unknown Could not determine the server state
	 */
	private function getServerState($module_row, $server_id) {
		// Load the API
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		
		// Load the Virtual Machine
		$vm = new VirtualMachine();
		$vm->loadFully($server_id);
		
		$status = "unknown";
		if (property_exists($vm, "power_action_pending") && $vm->power_action_pending)
			$status = "pending";
		elseif (property_exists($vm, "running"))
			$status = ($vm->running ? "online" : "offline");
		
		return $status;
	}
	
	/**
	 * Handles data for the actions tab in the client and admin interfaces
	 * @see Vpsdotnet::tabActions() and Vpsdotnet::tabClientActions()
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $templates An array of VPS templates
	 * @param array $template_groups An array of VPS template groups
	 * @param boolean $client True if the action is being performed by the client, false otherwise
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @return array An array of vars for the template
	 */
	private function actionsTab($package, $service, $templates, $template_groups, $client=false, array $get=null, array $post=null) {
		$vars = array();
		
		// Get the service fields
		$service_fields = $this->serviceFieldsToObject($service->fields);
		$module_row = $this->getModuleRow($package->module_row);
		
		$get_key = ($client ? 2 : 3);
		
		// Perform actions
		if (array_key_exists($get_key, (array)$get)) {
			
			switch ($get[$get_key]) {
				case "reboot":
				case "shutdown":
				case "boot":
					$action = ($get[$get_key] == "shutdown" ? "power_off" : ($get[$get_key] == "boot" ? "power_on" : $get[$get_key]));
					$this->performAction($action, $service_fields->vpsdotnet_id, $module_row);
					break;
				case "password":
					// Show the root password section
					$this->view->set("password", true);
					
					if (!empty($post)) {
						// Validate the template and perform the password reset
						if (isset($post['vpsdotnet_reset_password']) && $post['vpsdotnet_reset_password'] == 1) {
							// Reset the password
							Loader::loadModels($this, array("Services"));
							$this->Services->edit($service->id, array('vpsdotnet_reset_password' => true));
							
							if ($this->Services->errors())
								$this->Input->setErrors($this->Services->errors());
							
							// Do not show the password section again
							$this->view->set("password", false);
						}
						
						$vars = $post;
					}
					break;
				case "hostname":
					// Show the hostname section
					$this->view->set("hostname", true);
					
					if (!empty($post)) {
						$rules = array(
							'vpsdotnet_hostname' => array(
								'format' => array(
									'rule' => array(array($this, "validateHostName")),
									'message' => Language::_("Vpsdotnet.!error.vpsdotnet_hostname.format", true)
								)
							)
						);
						
						// Validate and update the hostname
						$this->Input->setRules($rules);
						if ($this->Input->validates($post)) {
							// Update the service hostname
							Loader::loadModels($this, array("Services"));
							$this->Services->edit($service->id, array('vpsdotnet_hostname' => $post['vpsdotnet_hostname']));
							
							if ($this->Services->errors())
								$this->Input->setErrors($this->Services->errors());
							
							// Do not show the hostname section again
							$this->view->set("hostname", false);
						}
						
						$vars = $post;
					}
					break;
				case "reinstall":
					// Show the reinstall section
					$this->view->set("reinstall", true);
					
					if (!empty($post)) {
						$rules = array(
							'vpsdotnet_template' => array(
								'valid' => array(
									'rule' => array("array_key_exists", $templates),
									'message' => Language::_("Vpsdotnet.!error.vpsdotnet_template.valid", true)
								)
							),
							'confirm' => array(
								'valid' => array(
									'rule' => array("compares", "==", "1"),
									'message' => Language::_("Vpsdotnet.!error.confirm.valid", true)
								)
							)
						);
						
						// Validate the template and perform the reinstallation
						$this->Input->setRules($rules);
						if ($this->Input->validates($post)) {
							// Update the service template
							Loader::loadModels($this, array("Services"));
							$this->Services->edit($service->id, array('vpsdotnet_template' => $post['vpsdotnet_template']));
							
							if ($this->Services->errors())
								$this->Input->setErrors($this->Services->errors());
							
							// Do not show the reinstall section again
							$this->view->set("reinstall", false);
						}
						
						$vars = $post;
					}
					break;
			}
		}
		
		return $vars;
	}
	
	/**
	 * Builds and returns the rules required to add/edit a module row (e.g. server)
	 *
	 * @param array $vars An array of key/value data pairs
	 * @return array An array of Input rules suitable for Input::setRules()
	 */
	private function getRowRules(&$vars) {
		return array(
			'server_name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Vpsdotnet.!error.server_name.empty", true)
				)
			),
			'email' => array(
				'format' => array(
					'rule' => "isEmail",
					'message' => Language::_("Vpsdotnet.!error.email.format", true)
				)
			),
			'key' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Vpsdotnet.!error.key.empty", true)
				),
				'valid_connection'=>array(
					'rule' => array(array($this, "validateConnection"), (isset($vars['email']) ? $vars['email'] : "")),
					'message' => Language::_("Vpsdotnet.!error.key.valid_connection", true)
				)
			)
		);
	}
	
	/**
	 * Retrieves the module row given the server or server group
	 *
	 * @param string $module_row The module row ID
	 * @param string $module_group The module group (optional, default "")
	 * @return mixed An stdClass object representing the module row, or null if it could not be determined
	 */
	private function getModuleRowByServer($module_row, $module_group = "") {
		// Fetch the module row
		$row = null;
		if ($module_group == "") {
			if ($module_row > 0) {
				$row = $this->getModuleRow($module_row);
			}
			else {
				$rows = $this->getModuleRows();
				if (isset($rows[0]))
					$row = $rows[0];
				unset($rows);
			}
		}
		else {
			// Fetch the 1st server from the list of servers in the selected group
			$rows = $this->getModuleRows($module_group);
			
			if (isset($rows[0]))
				$row = $rows[0];
			unset($rows);
		}
		
		return $row;
	}
	
	/**
	 * Returns an array of service fields to set for the service using the given input
	 *
	 * @param array $vars An array of key/value input pairs
	 * @param stdClass $package A stdClass object representing the package for the service
	 * @return array An array of key/value pairs representing service fields
	 */
	private function getFieldsFromInput(array $vars, $package) {
		if (!isset($this->Html))
			Loader::loadHelpers($this, array("Html"));
		
		$module_row = $this->getModuleRow($package->module_row);
		
		// Set cloud, template group, and template from the package if set
		$cloud = $this->Html->ifSet($package->meta->cloud, $this->Html->ifSet($vars['vpsdotnet_cloud'], null));
		$template = $this->Html->ifSet($package->meta->template, $this->Html->ifSet($vars['vpsdotnet_template'], null));
		$template_group = $this->Html->ifSet($package->meta->template_group, $this->Html->ifSet($vars['vpsdotnet_template_group'], null));
		
		$fields = array(
			'hostname' => $this->Html->ifSet($vars['vpsdotnet_hostname'], null),
			'label' => $this->Html->ifSet($vars['vpsdotnet_label'], null),
			'cloud_id' => $cloud,
			'system_template_id' => $template,
			'slices_required' => $this->Html->ifSet($package->meta->number_of_nodes),
			'backups_enabled' => "false",
			'rsync_backups_enabled' => "false",
			'r1_soft_backups_enabled' => "false",
			'operating_system' => $template_group
		);
		
		return $fields;
	}
	
	/**
	 * Initializes the API and returns an instance of that object with the given $host, $user, and $pass set
	 *
	 * @param string $email The email address of the user
	 * @param string $key The API key
	 * @return VPSNET An instance of VPSNET
	 */
	private function getApi($email, $key) {
		Loader::load(dirname(__FILE__) . DS . "apis" . DS . "VPSNET.php");
		try {
			// An error may be generated if the key is not given
			return VPSNET::getInstance($email, $key);
		}
		catch (Exception $e) {
			// Nothing to do
		}
	}
	
	/**
	 * Validates that a connection could be established to the server via the API
	 *
	 * @param string $email The account email address
	 * @param string $key The API key
	 */
	public function validateConnection($key, $email) {
		$api = $this->getApi($email, $key);
		
		// Fetch the user profile to check if a connection can be established
		$result = $this->callApi($api, "getProfile");
		
		return !(empty($result['result']) || !empty($result['error']));
	}
	
	/**
	 * Creates a new virtual machine
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param array $vars A key/value list of input parameters for creating a virtual machine
	 * @return array An array of VPS field attributes from the newly-created server, or void on error adding nodes
	 */
	private function addVps($module_row, array $vars) {
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		$params = (object)$vars;
		
		// Add nodes requested, if necessary
		$this->addNodes($module_row, $params->slices_required);
		
		if ($this->Input->errors())
			return;
		
		// Create the VPS
		$this->log($module_row->meta->email . "|createVirtualMachine", serialize($params), "input", true);
		$vps = $this->callApi($api, "createVirtualMachine", array($params));
		
		// Set any error
		$success = true;
		if (empty($vps['result']) || !empty($vps['error'])) {
			$error = (empty($vps['result']) && empty($vps['error']) ? Language::_("Vpsdotnet.!error.vps.cancel_failed", true) : $vps['error']);
			$this->Input->setErrors(array('vps' => array('api' => $error)));
			$success = false;
		}
		
		$this->log($module_row->meta->email, serialize($vps), "output", $success);
		
		// Return the nodes
		return $vps['result'];
	}
	
	/**
	 * Updates an existing virtual machine. Sets Input errors on failure.
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param stdClass $service_fields A stdClass object representing the current service fields
	 * @param array $vars A key/value list of input parameters for updating a virtual machine
	 * @return array A key/value list of service fields that have changed
	 */
	private function updateVps($module_row, $service_fields, array $vars = null) {
		// Load the api
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		
		// Only allow the following fields to be updated
		$updatable_fields = array("vpsdotnet_hostname", "vpsdotnet_template", "vpsdotnet_template_group", "vpsdotnet_reset_password");
		
		// Check for fields that have changed
		$delta = array();
		foreach ($vars as $key => $value) {
			if (in_array($key, $updatable_fields) && (!property_exists($service_fields, $key) || $value != $service_fields->{$key}))
				$delta[$key] = $value;
		}
		
		// No changes
		if (empty($delta))
			return $delta;
		
		// If the template has changed, set the template group (locally) as well to maintain consistency
		if (array_key_exists("vpsdotnet_template", $delta)) {
			$cloud = array_key_exists("vpsdotnet_cloud", $delta) ? $delta['vpsdotnet_cloud'] : $service_fields->vpsdotnet_cloud;
			$template_id = array_key_exists("vpsdotnet_template", $delta) ? $delta['vpsdotnet_template'] : $service_fields->vpsdotnet_template;
			
			// Fetch info on this template
			$template = $this->getTemplateInfo($module_row, $template_id, $cloud);
			
			// Update the template group
			if ($template && property_exists($template, "operating_system")) {
				$delta['vpsdotnet_template_group'] = $template->operating_system;
			}
		}
		
		// Reset password
		if (array_key_exists("vpsdotnet_reset_password", $delta)) {
			$password = $this->performAction("reset_password", $service_fields->vpsdotnet_id, $module_row);
			
			if ($this->Input->errors())
				return array();
			
			// Set the new password
			if ($password['result'] && property_exists($password['result'], "password"))
				$delta['vpsdotnet_password'] = $password['result']->password;
			unset($delta['vpsdotnet_reset_password']);
		}
		
		// Reinstall template
		if (array_key_exists("vpsdotnet_template", $delta)) {
			$this->reinstallTemplate($module_row, $service_fields->vpsdotnet_id, $delta['vpsdotnet_template']);
			
			if ($this->Input->errors())
				return array();
		}
		
		// Update hostname on VPS
		if (array_key_exists("vpsdotnet_hostname", $delta)) {
			$this->updateHostname($module_row, $service_fields->vpsdotnet_id, $delta['vpsdotnet_hostname']);
			
			if ($this->Input->errors())
				return array();
		}
		
		return $delta;
	}
	
	/**
	 * Adds nodes to the server. Sets Input errors on failure.
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param int $requested_nodes The number of nodes to add to the server
	 * @param boolean $use_existing True to use existing free nodes, if any, or false to add all new nodes (optional, default true)
	 */
	private function addNodes($module_row, $requested_nodes, $use_existing = true) {
		$available_nodes = 0;
		
		// Retrieve a list of nodes and add only what we need
		if ($use_existing) {
			$nodes = $this->getNodes($module_row);
			
			foreach ($nodes as $node) {
				if ($node->type == "vps")
					$available_nodes++;
			}
		}
		
		// Add nodes
		if ($requested_nodes > $available_nodes) {
			$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
			
			// Add the nodes
			$quantity = ($requested_nodes - $available_nodes);
			$params = array('quantity' => $quantity);
			
			$this->log($module_row->meta->email . "|addNodes", serialize($params), "input", true);
			$vps = $this->callApi($api, "addNodes", array($quantity));
			
			// Set any error
			$success = true;
			if (empty($vps['result']) || !empty($vps['error'])) {
				$error = (empty($vps['result']) && empty($vps['error']) ? Language::_("Vpsdotnet.!error.nodes.add_failed", true) : $vps['error']);
				$this->Input->setErrors(array('vps' => array('api' => $error)));
				$success = false;
			}
			
			$this->log($module_row->meta->email, serialize($vps), "output", $success);
		}
	}
	
	/**
	 * Fetches the nodes available for the VPS server of the given type
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param int $consumer_id The ID of the consumer (optional, default 0)
	 * @param string $type The type of billable nodes on the account to fetch (i.e. "ram", "storage", "fusion", "vps", "bandwidth") (optional, default "vps")
	 * @return array A list of nodes
	 */
	private function getNodes($module_row, $consumer_id = 0, $type = "vps") {
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		$params = array('consumer_id' => $consumer_id, 'type' => $type);
		
		// Fetch the nodes
		$this->log($module_row->meta->email . "|getNodes", serialize($params), "input", true);
		$nodes = $this->callApi($api, "getNodes", array($consumer_id, $type));
		$this->log($module_row->meta->email, serialize($nodes), "output", true);
		
		// Return the nodes
		return $nodes['result'];
	}
	
	/**
	 * Fetches the available template groups for a server in the given cloud
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param int $cloud_id The ID of the cloud (optional, default 0)
	 * @return array A list of template groups
	 */
	private function getTemplateGroups($module_row, $cloud_id = 0) {
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		$params = array('cloud_id' => $cloud_id);
		
		// Fetch the template groups
		$this->log($module_row->meta->email . "|getTemplatesGroups", serialize($params), "input", true);
		$templates = $this->callApi($api, "getTemplatesGroups", array($cloud_id));
		$this->log($module_row->meta->email, serialize($templates), "output", true);
		
		$groups = array();
		if ($templates['result']) {
			foreach ($templates['result'] as $group) {
				$groups[$group] = $group;
			}
		}
		
		// Return the template groups
		return $groups;
	}
	
	/**
	 * Retrieves information regarding a template
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param int $template_id The ID of the template
	 * @param int $cloud_id The ID of the cloud (optional, default 0)
	 * @return array A list of template information
	 */
	private function getTemplateInfo($module_row, $template_id, $cloud_id = 0) {
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		$params = array('cloud_id' => $cloud_id, 'template_id' => $template_id);
		
		// Fetch the template groups
		$this->log($module_row->meta->email . "|getTemplateInfo", serialize($params), "input", true);
		$template_info = $this->callApi($api, "getTemplateInfo", array($cloud_id, $template_id));
		$this->log($module_row->meta->email, serialize($template_info), "output", true);
		
		// Return the template info
		return $template_info['result'];
	}
	
	/**
	 * Fetches the available templates for a server of the given template group
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param int $cloud The ID of the cloud (optional, default 0)
	 * @param int $template_group The ID of the template group (optional, default false)
	 * @param string $filter The templates to filter for (i.e. "free", "paid", or "all" for both; optional, default all) 
	 * @return array A list of templates
	 */
	private function getTemplates($module_row, $cloud = 0, $template_group = false, $filter = "all") {
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		$params = array('group' => $template_group, 'filter' => $filter, 'cloud' => $cloud);
		
		// Fetch the templates
		$this->log($module_row->meta->email . "|getAllTemplates", serialize($params), "input", true);
		$templates = $this->callApi($api, "getAllTemplates", array($template_group, $filter, $cloud));
		$this->log($module_row->meta->email, serialize($templates), "output", true);
		
		// Return the templates
		$formatted_templates = array();
		if ($templates['result'] && is_array($templates['result'])) {
			foreach ($templates['result'] as $template) {
				if (property_exists($template, "id") && property_exists($template, "label"))
					$formatted_templates[$template->id] = $template->label;
			}
		}
		return $formatted_templates;
	}
	
	/**
	 * Fetches available clouds
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param boolean $fusion True to fetch fusion clouds, false otherwise (optional, default false)
	 * @return array A list of clouds
	 */
	private function getClouds($module_row, $fusion = false) {
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		$params = array('fusion' => $fusion);
		
		// Fetch the nodes
		$this->log($module_row->meta->email . "|getAvailableClouds", serialize($params), "input", true);
		$clouds = $this->callApi($api, "getAvailableClouds", array($fusion));
		$this->log($module_row->meta->email, serialize($clouds), "output", true);
		
		$formatted_clouds = array();
		if ($clouds['result'] && is_array($clouds['result'])) {
			foreach ($clouds['result'] as $cloud) {
				if (isset($cloud['id']) && isset($cloud['text']))
					$formatted_clouds[$cloud['id']] = $cloud['text'];
			}
			asort($formatted_clouds);
		}
		
		// Return the clouds
		return $formatted_clouds;
	}
	
	/**
	 * Fetches available clouds and associated templates
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @return array A list of clouds and their associated templates
	 */
	private function getCloudsAndTemplates($module_row) {
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		$params = array();
		
		// Fetch the nodes
		$this->log($module_row->meta->email . "|getAvailableCloudsAndTemplates", serialize($params), "input", true);
		$clouds = $this->callApi($api, "getAvailableCloudsAndTemplates", array());
		$this->log($module_row->meta->email, serialize($clouds), "output", true);
		
		// Return the clouds
		return $clouds['result'];
	}
	
	/**
	 * Makes an API call and returns the results.
	 *
	 * @param mixed $api A reference to the api (VPSNET, VirtualMachine, etc. API objects)
	 * @param string $method The name of the method from the VPSNET class to call
	 * @return array An array containing
	 * 	- result The results of the API call
	 * 	- error An error that may be generated
	 */
	private function callApi($api, $method, $params = array()) {
		$result = array('result' => false, 'error' => false);
		
		try {
			$result['result'] = call_user_func_array(array($api, $method), $params);
		}
		catch (Exception $e) {
			// The API causes an 'undefined index: response' error when incorrect auth details are set
			if (strtolower($e->getMessage()) == "undefined index: response")
				$result['error'] = Language::_("Vpsdotnet.!error.key.valid_connection", true);
			else
				$result['error'] = $e->getMessage();
		}
		
		return $result;
	}
	
	/**
	 * Retrieves information regarding the console
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param int $server_id The ID of the server
	 * @return array A list of template information
	 */
	private function getConsole($module_row, $server_id) {
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		
		// Load the Virtual Machine
		$vm = new VirtualMachine();
		$vm->loadFully($server_id);
		
		// Fetch the console info
		$this->log($module_row->meta->email . "|getConsole", null, "input", true);
		$response = $this->callApi($vm, "getConsole");
		$this->log($module_row->meta->email, serialize($response), "output", true);
		
		// Return the console info
		return $response['result'];
	}
	
	/**
	 * Updates the server hostname. Sets Input errors on failure
	 *
	 * @param stdClass $module_row An stdClass object representing a single server
	 * @param int $server_id The virtual server ID
	 * @param string $hostname The name of the new hostname
	 * @return mixed The response to the request
	 */
	private function updateHostname($module_row, $server_id, $hostname) {
		// Load the API
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		
		// Load the Virtual Machine
		$vm = new VirtualMachine();
		$vm->loadFully($server_id);
		
		// Log the input
		$params = array('hostname' => $hostname);
		$this->log($module_row->meta->email . "|update", serialize($params), "input", true);
		
		// Update the VM
		$vm->hostname = $hostname;
		$response = $this->callApi($vm, "update");
		
		// Set any error
		$success = true;
		if (empty($response['result']) || !empty($response['error'])) {
			$error = (empty($response['result']) && empty($response['error']) ? Language::_("Vpsdotnet.!error.vps.update_failed", true) : $response['error']);
			$this->Input->setErrors(array('vps' => array('api' => $error)));
			$success = false;
		}
		
		// Log the output
		$this->log($module_row->meta->email, serialize($response), "output", $success);
		return $response;
	}
	
	/**
	 * Reinstalls a server template. Sets Input errors on failure
	 *
	 * @param stdClass $module_row An stdClass object representing a single server
	 * @param int $server_id The virtual server ID
	 * @param int $template_id The ID of the new template to reinstall
	 * @return mixed The response to the request
	 */
	private function reinstallTemplate($module_row, $server_id, $template_id) {
		// Load the API
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		
		// Load the Virtual Machine
		$vm = new VirtualMachine();
		$vm->loadFully($server_id);
		
		// Perform the action
		$params = array('system_template_id' => $template_id);
		$this->log($module_row->meta->email . "|reinstall", serialize($params), "input", true);
		$response = $this->callApi($vm, "reinstall", array($template_id));
		
		// Set any error
		$success = true;
		if (empty($response['result']) || !empty($response['error'])) {
			$error = (empty($response['result']) && empty($response['error']) ? Language::_("Vpsdotnet.!error.vps.reinstall_failed", true) : $response['error']);
			$this->Input->setErrors(array('vm' => array('api' => $error)));
			$success = false;
		}
		
		$this->log($module_row->meta->email, serialize($response), "output", $success);
		return $response;
	}
	
	/**
	 * Performs an action on the virtual server. Sets Input errors on failure
	 *
	 * @param string $action The action to perform (i.e. "power_on", "reboot", "shutdown", "power_off", "reset_password")
	 * @param int $server_id The virtual server ID
	 * @param stdClass $module_row An stdClass object representing a single server
	 * @return mixed The response to the request
	 */
	private function performAction($action, $server_id, $module_row) {
		// Load the API
		$api = $this->getApi($module_row->meta->email, $module_row->meta->key);
		
		// Load the Virtual Machine
		$vm = new VirtualMachine();
		$vm->loadFully($server_id);
		
		$method = "reboot";
		switch ($action) {
			case "power_on":
				$method = "powerOn";
				break;
			case "power_off":
				$method = "powerOff";
				break;
			case "shutdown":
				$method = "shutdown";
				break;
			case "reset_password":
				$method = "resetPassword";
				break;
			case "reboot":
			default:
				break;
		}
		
		// Perform the action
		$this->log($module_row->meta->email . "|" . $method, null, "input", true);
		$response = $this->callApi($vm, $method);
		
		// Set any error
		$success = true;
		if (empty($response['result']) || !empty($response['error'])) {
			$error = (empty($response['result']) && empty($response['error']) ? Language::_("Vpsdotnet.!error.vps.action_failed", true) : $response['error']);
			$this->Input->setErrors(array('vm' => array('api' => $error)));
			$success = false;
		}
		
		$this->log($module_row->meta->email, serialize($response), "output", $success);
		return $response;
	}
	
	/**
	 * Retrieves a list of rules for validating adding/editing a package
	 *
	 * @param array $vars A list of input vars
	 * @return array A list of rules
	 */
	private function getPackageRules(array $vars = null) {
		if (!isset($this->Html))
			Loader::loadHelpers($this, array("Html"));
		
		$user_types = array("admin", "client");
		
		#
		# TODO: may want to improve error checking by validating each zone, OS, and template are valid values
		#
		
		$rules = array(
			'meta[number_of_nodes]' => array(
				'format' => array(
					'rule' => array("matches", "/^[0-9]+$/"),
					'message' => Language::_("Vpsdotnet.!error.meta[number_of_nodes].format", true)
				)
			),
			'meta[set_cloud]' => array(
				'format' => array(
					'rule' => array("in_array", $user_types),
					'message' => Language::_("Vpsdotnet.!error.meta[set_cloud].format", true)
				)
			),
			'meta[cloud]' => array(
				'format' => array(
					// Cloud integer ID must be given if the admin chose to set a cloud
					'if_set' => !($this->Html->ifSet($vars['meta']['set_cloud']) == "admin"),
					'rule' => array("matches", "/^[0-9]+$/"),
					'message' => Language::_("Vpsdotnet.!error.meta[cloud].format", true)
				)
			),
			'meta[set_template_group]' => array(
				'format' => array(
					'if_set' => !$this->Html->ifSet($vars['meta']['cloud']),
					'rule' => array("in_array", $user_types),
					'message' => Language::_("Vpsdotnet.!error.meta[set_template_group].format", true)
				)
			),
			'meta[template_group]' => array(
				'format' => array(
					// OS must be set if the admin chose to set an OS
					'if_set' => !($this->Html->ifSet($vars['meta']['set_template_group']) == "admin"),
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Vpsdotnet.!error.meta[template_group].format", true)
				)
			),
			'meta[set_template]' => array(
				'format' => array(
					'if_set' => !$this->Html->ifSet($vars['meta']['template_group']),
					'rule' => array("in_array", $user_types),
					'message' => Language::_("Vpsdotnet.!error.meta[set_template].format", true)
				)
			),
			'meta[template]' => array(
				'format' => array(
					// Template must be set if the admin chose to set a template
					'if_set' => !($this->Html->ifSet($vars['meta']['set_template']) == "admin"),
					'rule' => array("matches", "/^[0-9]+$/"),
					'message' => Language::_("Vpsdotnet.!error.meta[template].format", true)
				)
			)
		);
		
		return $rules;
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
}
?>