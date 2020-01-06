<?php
/**
 * Abstract class that all Modules must extend
 *
 * @package blesta
 * @subpackage blesta.components.modules
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
abstract class Module {

	/**
	 * @var Http An Http object, used to make HTTP requests
	 */
	protected $Http;
	/**
	 * @var stdClass A stdClass object representing the configuration for this module
	 */
	protected $config;
	/**
	 * @var string The base URI for the requested module action
	 */
	public $base_uri;
	/**
	 * @var stdClass A stdClass object representing the module
	 */
	private $module;
	/**
	 * @var stdClass A stdClass object representing the module row
	 */
	private $module_row;
	/**
	 * @var string The random ID to identify the group of this module request for logging purposes
	 */
	private $log_group;
	/**
	 * @var int The ID of the staff member using the module
	 */
	private $staff_id;
	
	/**
	 * Returns the name of this module
	 *
	 * @return string The common name of this module
	 */
	public function getName() {
		if (isset($this->config->name))
			return $this->translate($this->config->name);
		return null;
	}
	
	/**
	 * Returns the version of this module
	 *
	 * @return string The current version of this module
	 */
	public function getVersion() {
		if (isset($this->config->version)) {
			return $this->config->version;
		}
		return null;
	}

	/**
	 * Returns the name and URL for the authors of this module
	 *
	 * @return array A numerically indexed array that contains an array with key/value pairs for 'name' and 'url', representing the name and URL of the authors of this module
	 */
	public function getAuthors() {
		if (isset($this->config->authors)) {
			foreach ($this->config->authors as &$author) {
				$author = (array)$author;
			}
			return $this->config->authors;
		}
		return null;
	}
	
	/**
	 * Returns the value used to identify a particular service
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @return string A value used to identify this service amongst other similar services
	 */
	public function getServiceName($service) {
		if (isset($this->config->service->name_key)) {
			foreach ($service->fields as $field) {
				if ($field->key == $this->config->service->name_key)
					return $field->value;
			}
		}
		return null;
	}
	
	/**
	 * Returns a noun used to refer to a module row (e.g. "Server", "VPS", "Reseller Account", etc.)
	 *
	 * @return string The noun used to refer to a module row
	 */
	public function moduleRowName() {
		if (isset($this->config->module->row))
			return $this->translate($this->config->module->row);
		return null;
	}
	
	/**
	 * Returns a noun used to refer to a module row in plural form (e.g. "Servers", "VPSs", "Reseller Accounts", etc.)
	 *
	 * @return string The noun used to refer to a module row in plural form
	 */
	public function moduleRowNamePlural() {
		if (isset($this->config->module->rows))
			return $this->translate($this->config->module->rows);
		return null;
	}
	
	/**
	 * Returns a noun used to refer to a module group (e.g. "Server Group", "Cloud", etc.)
	 *
	 * @return string The noun used to refer to a module group
	 */
	public function moduleGroupName() {
		if (isset($this->config->module->group))
			return $this->translate($this->config->module->group);
		return null;
	}
	
	/**
	 * Returns the key used to identify the primary field from the set of module row meta fields.
	 * This value can be any of the module row meta fields.
	 *
	 * @return string The key used to identify the primary field from the set of module row meta fields
	 */
	public function moduleRowMetaKey() {
		if (isset($this->config->module->row_key))
			return $this->config->module->row_key;
		return null;
	}
	
	/**
	 * Performs any necessary bootstraping actions. Sets Input errors on
	 * failure, preventing the module from being added.
	 *
	 * @return array A numerically indexed array of meta data containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function install() {
		
	}
	
	/**
	 * Performs migration of data from $current_version (the current installed version)
	 * to the given file set version. Sets Input errors on failure, preventing
	 * the module from being upgraded.
	 *
	 * @param string $current_version The current installed version of this module
	 */
	public function upgrade($current_version) {
		
	}
	
	/**
	 * Performs any necessary cleanup actions. Sets Input errors on failure
	 * after the module has been uninstalled.
	 *
	 * @param int $module_id The ID of the module being uninstalled
	 * @param boolean $last_instance True if $module_id is the last instance across all companies for this module, false otherwise
	 */
	public function uninstall($module_id, $last_instance) {
		
	}
	
	/**
	 * Returns the relative path from this module's directory to the logo for
	 * this module. Defaults to views/default/images/logo.png
	 *
	 * @return string The relative path to the module's logo
	 */
	public function getLogo() {
		if (isset($this->config->logo))
			return $this->config->logo;
		return "views/default/images/logo.png";
	}
	
	/**
	 * Sets the module to be used for any subsequent requests
	 *
	 * @param stdClass A stdClass object representing the module
	 * @see ModuleManager::get()
	 */
	public final function setModule($module) {
		$this->module = $module;
	}

	/**
	 * Sets the module row to be used for any subsequent requests
	 *
	 * @param stdClass A stdClass object representing the module row
	 * @see ModuleManager::getRow()
	 */	
	public final function setModuleRow($module_row) {
		$this->module_row = $module_row;
	}
	
	/**
	 * Fetches the module currently in use
	 *
	 * @return stdClass A stdClass object representing the module
	 */
	public final function getModule() {
		return $this->module;
	}
	
	/**
	 * Fetches the requested module row for the current module
	 *
	 * @param int $module_row_id The ID of the module row to fetch for the current module
	 * @return stdClass A stdClass object representing the module row
	 */
	public final function getModuleRow($module_row_id=null) {
		
		if ($module_row_id) {
			if (!isset($this->ModuleManager))
				Loader::loadModels($this, array("ModuleManager"));
			
			$row = $this->ModuleManager->getRow($module_row_id);
			
			if ($row && $row->module_id == $this->module->id)
				return $row;
			return false;
		}
		return $this->module_row;
	}
	
	/**
	 * Returns all module rows available to the current module
	 *
	 * @param int $module_group_id The ID of the module group to filter rows by
	 * @return array An array of stdClass objects each representing a module row, false if no module set
	 */
	public final function getModuleRows($module_group_id=null) {
		if (!isset($this->module->id))
			return false;
		
		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));
		
		return $this->ModuleManager->getRows($this->module->id, $module_group_id);
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
		if (isset($this->config->package->name_key) && isset($vars[$this->config->package->name_key]))
			return $vars[$this->config->package->name_key];
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
		return true;
	}
	
	/**
	 * Adds the service to the remote server. Sets Input errors on failure,
	 * preventing the service from being added.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being added (if the current service is an addon service and parent service has already been provisioned)
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
		return array();
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
	public function editService($package, $service, array $vars=array(), $parent_package=null, $parent_service=null) {
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
		
		$meta = array();
		if (isset($vars['meta']) && is_array($vars['meta'])) {
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
		
		$meta = array();
		if (isset($vars['meta']) && is_array($vars['meta'])) {
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
		
	}
	
	/**
	 * Returns the rendered view of the manage module page
	 *
	 * @param mixed $module A stdClass object representing the module and its rows
	 * @param array $vars An array of post data submitted to or on the manage module page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the manager module page
	 */
	public function manageModule($module, array &$vars) {
		return "";
	}
	
	/**
	 * Returns the rendered view of the add module row page
	 *
	 * @param array $vars An array of post data submitted to or on the add module row page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the add module row page
	 */
	public function manageAddRow(array &$vars) {
		return "";		
	}

	/**
	 * Returns the rendered view of the edit module row page
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 * @param array $vars An array of post data submitted to or on the edit module row page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the edit module row page
	 */	
	public function manageEditRow($module_row, array &$vars) {
		return "";
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
		$meta = array();
		foreach ($vars as $key => $value) {
			$meta[] = array(
				'key'=>$key,
				'value'=>$value,
				'encrypted'=>0
			);
		}
		return $meta;
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
		$meta = array();
		foreach ($vars as $key => $value) {
			$meta[] = array(
				'key'=>$key,
				'value'=>$value,
				'encrypted'=>0
			);
		}
		return $meta;
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
	 * Returns an array of available service delegation order methods. The module
	 * will determine how each method is defined. For example, the method "first"
	 * may be implemented such that it returns the module row with the least number
	 * of services assigned to it.
	 *
	 * @return array An array of order methods in key/value pairs where the key is the type to be stored for the group and value is the name for that option
	 * @see Module::selectModuleRow()
	 */
	public function getGroupOrderOptions() {
		
	}
	
	/**
	 * Determines which module row should be attempted when a service is provisioned
	 * for the given group based upon the order method set for that group.
	 *
	 * @return int The module row ID to attempt to add the service with
	 * @see Module::getGroupOrderOptions()
	 */
	public function selectModuleRow($module_group_id) {
		
	}
	
	/**
	 * Returns all fields used when adding/editing a package, including any
	 * javascript to execute when the page is rendered with these fields.
	 *
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getPackageFields($vars=null) {
		return new ModuleFields();
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
		if (isset($this->config->email_tags)) {
			return (array)$this->config->email_tags;
		}
		return array();
	}

	/**
	 * Returns all fields to display to an admin attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getAdminAddFields($package, $vars=null) {
		return new ModuleFields();
	}
	
	/**
	 * Returns all fields to display to a client attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getClientAddFields($package, $vars=null) {
		return new ModuleFields();		
	}
	
	/**
	 * Returns all fields to display to an admin attempting to edit a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getAdminEditFields($package, $vars=null) {
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
		return "";
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
		return "";
	}
	
	/**
	 * Returns all tabs to display to an admin when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 */
	public function getAdminTabs($package) {
		return array();
	}

	/**
	 * Returns all tabs to display to a client when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title, or method => array where array contains:
	 * 	- name (required) The name of the link
	 * 	- icon (optional) use to display a custom icon
	 * 	- href (optional) use to link to a different URL
	 * 		Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 * 		array('methodName' => array('name' => "Title", 'icon' => "icon"))
	 */
	public function getClientTabs($package) {
		return array();
	}
	
	/**
	 * Return all validation errors encountered
	 *
	 * @return mixed Boolean false if no errors encountered, an array of errors otherwise
	 */
	public function errors() {
		if (isset($this->Input) && is_object($this->Input) && $this->Input instanceof Input)
			return $this->Input->errors();
	}
	
	/**
	 * Process a request over HTTP using the supplied method type, url and parameters.
	 *
	 * @param string $method The method type (e.g. GET, POST)
	 * @param string $url The URL to post to
	 * @param mixed An array of parameters or a URL encoded list of key/value pairs
	 * @param string The output result from executing the request
	 */
	protected function httpRequest($method, $url=null, $params=null) {
		if (!isset($this->Http)) {
			Loader::loadComponents($this, array("Net"));
			$this->Http = $this->Net->create("Http");
		}
		
		if (is_array($params))
			$params = http_build_query($params);
		
		return $this->Http->request($method, $url, $params);
	}
	
	/**
	 * Attempts to log the given info to the module log.
	 *
	 * @param string $url The URL contacted for this request
	 * @param string $data A string of module data sent along with the request (optional)
	 * @param string $direction The direction of the log entry (input or output, default input)
	 * @param boolean $success True if the request was successful, false otherwise
	 * @return string Returns the 8-character group identifier, used to link log entries together
	 * @throws Exception Thrown if $data was invalid and could not be added to the log
	 */
	protected function log($url, $data=null, $direction="input", $success=false) {
		if (!isset($this->Logs))
			Loader::loadModels($this, array("Logs"));
		
		// Create a random 8-character group identifier
		if ($this->log_group == null)
			$this->log_group = substr(md5(mt_rand()), mt_rand(0, 23), 8);
		
		$log = array(
			'staff_id'=>$this->staff_id,
			'module_id'=>$this->module->id,
			'direction'=>$direction,
			'url'=>$url,
			'data'=>$data,
			'status'=>($success ? "success" : "error"),
			'group'=>$this->log_group
		);
		$this->Logs->addModule($log);
		
		if (($error = $this->Logs->errors()))
			throw new Exception(serialize($error));
			
		return $this->log_group;
	}
	
	/**
	 * Converts numerically indexed service field arrays into an object with member variables
	 *
	 * @param array $fields A numerically indexed array of stdClass objects containing key and value member variables, or an array containing 'key' and 'value' indexes
	 * @return stdClass A stdClass objects with member variables
	 */
	protected function serviceFieldsToObject(array $fields) {
		$data = new stdClass();
		foreach ($fields as $field) {
			if (is_array($field))
				$data->{$field['key']} = $field['value'];
			else
				$data->{$field->key} = $field->value;
		}
		
		return $data;
	}
	
	/**
	 * Converts an array to a ModuleFields object
	 *
	 * @param array An array of key/value pairs where each key is the field name and each value is array consisting of:
	 * 	- label The field label
	 * 	- type The field type(text, textarea, select, checkbox, radio)
	 * 	- options A key/value array where each key is the option value and each value is the option name, or a string to set as the default value for hidden and text inputs
	 * 	- attributes A key/value array
	 * @param ModuleFields $fields An existing ModuleFields object to append fields to, null to create create a new object
	 * @param stdClass $vars A stdClass object of input key/value pairs
	 * @return ModuleFields A ModuleFields object containing the fields
	 */
	protected function arrayToModuleFields($arr, ModuleFields $fields = null, $vars = null) {
		
		if ($fields == null)
			$fields = new ModuleFields();
		
		foreach ($arr as $name => $field) {
			
			$label = isset($field['label']) ? $field['label'] : null;
			$type = isset($field['type']) ? $field['type'] : null;
			$options = isset($field['options']) ? $field['options'] : null;
			$attributes = isset($field['attributes']) ? $field['attributes'] : array();
			
			$field_id = isset($attributes['id']) ? $attributes['id'] : $name . "_id";
			
			$field_label = null;
			if ($type !== "hidden")
				$field_label = $fields->label($label, $field_id);
			
			$attributes['id'] = $field_id;
			
			switch ($type) {
				default:
					$value = $options;
					$type = "field" . ucfirst($type);
					$field_label->attach($fields->{$type}($name, isset($vars->{$name}) ? $vars->{$name} : $value, $attributes));
					break;
				case "hidden":
					$value = $options;
					$fields->setField($fields->fieldHidden($name, isset($vars->{$name}) ? $vars->{$name} : $value, $attributes));
					break;
				case "select":
					$field_label->attach($fields->fieldSelect($name, $options, isset($vars->{$name}) ? $vars->{$name} : null, $attributes));
					break;
				case "checkbox":
				case "radio":
					$i=0;
					foreach ($options as $key => $value) {
						$option_id = $field_id . "_" . $i++;
						$option_label = $fields->label($value, $option_id);
						
						$checked = false;
						if (isset($vars->{$name})) {
							if (is_array($vars->{$name}))
								$checked = in_array($key, $vars->{$name});
							else
								$checked = $key == $vars->{$name};
						}
						
						if ($type == "checkbox")
							$field_label->attach($fields->fieldCheckbox($name, $key, $checked, array('id' => $option_id), $option_label));
						else
							$field_label->attach($fields->fieldRadio($name, $key, $checked, array('id' => $option_id), $option_label));
					}
					break;
			}
			
			if ($field_label)
				$fields->setField($field_label);
		}
		
		return $fields;
	}
	
	/**
	 * Loads a given config file
	 */
	protected function loadConfig($file) {
		if (!isset($this->Json))
			Loader::loadComponents($this, array("Json"));
		
		if (file_exists($file))
			$this->config = $this->Json->decode(file_get_contents($file));
	}
	
	/**
	 * Translate the given str, or passthrough if no translation et
	 *
	 * @param string $str The string to translate
	 * @return string The translated string
	 */
	private function translate($str) {
		$pass_through = Configure::get("Language.allow_pass_through");
		Configure::set("Language.allow_pass_through", true);
		$str = Language::_($str, true);
		Configure::set("Language.allow_pass_through", $pass_through);
		
		return $str;
	}
}
?>