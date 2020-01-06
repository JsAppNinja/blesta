<?php
/**
 * Plesk Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.plesk
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Plesk extends Module {
	
	/**
	 * @var string The version of this module
	 */
	private static $version = "2.1.2";
	/**
	 * @var string The authors of this module
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	/**
	 * @var array A list of Plesk panel versions
	 */
	private $panel_versions = array();
	
	/**
	 * Initializes the module
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("plesk", null, dirname(__FILE__) . DS . "language" . DS);
		
		// Setup panel versions
		$this->init();
	}
	
	/**
	 * Initializes the panel versions
	 */
	private function init() {
		$windows = Language::_("Plesk.panel_version.windows", true);
		$linux = Language::_("Plesk.panel_version.linux", true);
		
		$versions = array(
			'7.5.4' => array('name' => Language::_("Plesk.panel_version.plesk_type", true, "7.5.4", $linux), 'api_version' => "1.3.5.1", 'supported' => false),
			'7.5.6' => array('name' => Language::_("Plesk.panel_version.plesk_type", true, "7.5.6", $windows), 'api_version' => "1.4.0.0", 'supported' => false),
			'7.6' => array('name' => Language::_("Plesk.panel_version.plesk_type", true, "7.6", $windows), 'api_version' => "1.4.0.0", 'supported' => false),
			'7.6.1' => array('name' => Language::_("Plesk.panel_version.plesk_type", true, "7.6.1", $windows), 'api_version' => "1.4.1.1", 'supported' => false),
			'8.0' => array('name' => Language::_("Plesk.panel_version.plesk_type", true, "8.0", $linux), 'api_version' => "1.4.0.0", 'supported' => false),
			'8.0.1' => array('name' => Language::_("Plesk.panel_version.plesk_type", true, "8.0.1", $linux), 'api_version' => "1.4.1.2", 'supported' => false),
			'8.1.0' => array('name' => Language::_("Plesk.panel_version.plesk", true, "8.1.0"), 'api_version' => "1.4.2.0", 'supported' => false),
			'8.1.1' => array('name' => Language::_("Plesk.panel_version.plesk", true, "8.1.1"), 'api_version' => "1.5.0.0", 'supported' => false),
			'8.2' => array('name' => Language::_("Plesk.panel_version.plesk", true, "8.2"), 'api_version' => "1.5.1.0", 'supported' => false),
			'8.3' => array('name' => Language::_("Plesk.panel_version.plesk", true, "8.3"), 'api_version' => "1.5.2.0", 'supported' => false),
			'8.4' => array('name' => Language::_("Plesk.panel_version.plesk", true, "8.4"), 'api_version' => "1.5.2.1", 'supported' => false),
			'8.6' => array('name' => Language::_("Plesk.panel_version.plesk", true, "8.6"), 'api_version' => "1.5.2.1", 'supported' => false),
			'9.0.0' => array('name' => Language::_("Plesk.panel_version.parallels", true, "9.0.0"), 'api_version' => "1.6.0.0", 'supported' => false),
			'9.0.1' => array('name' => Language::_("Plesk.panel_version.parallels", true, "9.0.1"), 'api_version' => "1.6.0.1", 'supported' => false),
			'9.0.2' => array('name' => Language::_("Plesk.panel_version.parallels", true, "9.0.2"), 'api_version' => "1.6.0.2", 'supported' => false),
			'10.0' => array('name' => Language::_("Plesk.panel_version.parallels", true, "10.0"), 'api_version' => "1.6.3.0", 'supported' => true),
			'10.1' => array('name' => Language::_("Plesk.panel_version.parallels", true, "10.1"), 'api_version' => "1.6.3.1", 'supported' => true),
			'10.2' => array('name' => Language::_("Plesk.panel_version.parallels", true, "10.2"), 'api_version' => "1.6.3.2", 'supported' => true),
			'10.3' => array('name' => Language::_("Plesk.panel_version.parallels", true, "10.3"), 'api_version' => "1.6.3.3", 'supported' => true),
			'10.4' => array('name' => Language::_("Plesk.panel_version.parallels", true, "10.4"), 'api_version' => "1.6.3.4", 'supported' => true),
			'11.0' => array('name' => Language::_("Plesk.panel_version.parallels", true, "11.0"), 'api_version' => "1.6.3.5", 'supported' => true),
			'11.1.0' => array('name' => Language::_("Plesk.panel_version.parallels", true, "11.1.0"), 'api_version' => "1.6.4.0", 'supported' => true),
			'11.5' => array('name' => Language::_("Plesk.panel_version.parallels", true, "11.5"), 'api_version' => "1.6.5.0", 'supported' => true),
		);
		
		$this->panel_versions = array_reverse($versions);
	}
	
	/**
	 * Retrieves the API version based on the panel version in use
	 *
	 * @param string $panel_version The version number of the panel
	 * @return string The API version to use for this panel
	 */
	private function getApiVersion($panel_version) {
		return $this->panel_versions[$panel_version]['api_version'];
	}
	
	/**
	 * Retrieves Plesk panel versions that are supported by this module
	 *
	 * @param boolean $format True to format the versions as name/value pairs, false for the entire array
	 * @return array A list of supported versions
	 */
	private function getSupportedPanelVersions($format = false) {
		$versions = array();
		foreach ($this->panel_versions as $panel_version => $panel) {
			if ($panel['supported']) {
				if ($format)
					$versions[$panel_version] = $panel['name'];
				else
					$versions[$panel_version] = $panel;
			}
		}
		return $versions;
	}
	
	/**
	 * Returns the name of this module
	 *
	 * @return string The common name of this module
	 */
	public function getName() {
		return Language::_("Plesk.name", true);
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
	 * Returns all tabs to display to an admin when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 */
	public function getAdminTabs($package) {
		return array(
			'tabStats' => Language::_("Plesk.tab_stats", true)
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
			'tabClientStats' => Language::_("Plesk.tab_client_stats", true)
		);
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
		$errors = array();
		// Ensure the the system meets the requirements for this module
		if (!extension_loaded("simplexml"))
			$errors['simplexml']['required'] = Language::_("Plesk.!error.simplexml_required", true);
		
		if (!empty($errors)) {
			$this->Input->setErrors($errors);
			return;
		}
	}
	
	/**
	 * Returns the value used to identify a particular service
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @return string A value used to identify this service amongst other similar services
	 */
	public function getServiceName($service) {
		foreach ($service->fields as $field) {
			if ($field->key == "plesk_domain")
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
		return Language::_("Plesk.module_row", true);
	}
	
	/**
	 * Returns a noun used to refer to a module row in plural form (e.g. "Servers", "VPSs", "Reseller Accounts", etc.)
	 *
	 * @return string The noun used to refer to a module row in plural form
	 */
	public function moduleRowNamePlural() {
		return Language::_("Plesk.module_row_plural", true);
	}
	
	/**
	 * Returns a noun used to refer to a module group (e.g. "Server Group", "Cloud", etc.)
	 *
	 * @return string The noun used to refer to a module group
	 */
	public function moduleGroupName() {
		return Language::_("Plesk.module_group", true);
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
		if (isset($vars['plesk_domain']))
			return $vars['plesk_domain'];
		return null;
	}
	
	/**
	 * Checks whether the given webspace ID exists in Plesk
	 *
	 * @param int $webspace_id The subscription webspace ID to check
	 * @param stdClass $package An stdClass object representing the package
	 * @return boolean True if the webspace exists, false otherwise
	 */
	public function validateWebspaceExists($webspace_id, $package) {
		// Get module row and API
		$module_row = $this->getModuleRowByServer((isset($package->module_row) ? $package->module_row : 0), (isset($package->module_group) ? $package->module_group : ""));
		
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		$api_version = $this->getApiVersion($module_row->meta->panel_version);
		
		// Fetch the webspace/domain
		try {
			$subscription = $api->loadCommand("plesk_subscriptions", array($api_version));
			
			$data = array('id' => $webspace_id);
			
			$this->log($module_row->meta->ip_address . "|webspace:get", serialize($data), "input", true);
			$response = $this->parseResponse($subscription->get($data), $module_row, true);
			
			if ($response && $response->result->status == "ok")
				return true;
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
		
		return false;
	}
	
	/**
	 * Checks whether the given plan ID exists in Plesk
	 *
	 * @param int $plan_id The service plan ID
	 * @param stdClass $package An stdClass object representing the package
	 * @param boolean $reseller True if the plan is a reseller plan, false for a hosting plan (optional, default false)
	 * @return boolean True if the plan exists, false otherwise
	 */
	public function validatePlanExists($plan_id, $package, $reseller=false) {
		// Get module row and API
		$module_row = $this->getModuleRowByServer((isset($package->module_row) ? $package->module_row : 0), (isset($package->module_group) ? $package->module_group : ""));
		
		// Fetch the plans
		$plans = $this->getPleskPlans($module_row, $reseller);
		
		return (isset($plans[$plan_id]));
	}
	
	/**
	 * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param boolean $edit True if editing the service, false otherwise
	 * @return boolean True if the service validates, false otherwise. Sets Input errors when false.
	 */
	public function validateService($package, array $vars=null, $edit=false) {
		// Set rules
		$rules = array(
			'plesk_domain' => array(
				'format' => array(
					'rule' => array(array($this, "validateHostName")),
					'message' => Language::_("Plesk.!error.plesk_domain.format", true)
				)
			),
			'plesk_username' => array(
				'length' => array(
					'if_set' => true,
					'rule' => array("betweenLength", 1, 60),
					'message' => Language::_("Plesk.!error.plesk_username.length", true)
				)
			),
			'plesk_password' => array(
				'length' => array(
					'if_set' => true,
					'rule' => array("betweenLength", 5, 14),
					'message' => Language::_("Plesk.!error.plesk_password.length", true)
				)
			),
			'plesk_confirm_password' => array(
				'matches' => array(
					'if_set' => true,
					'rule' => array("compares", "==", (isset($vars['plesk_password']) ? $vars['plesk_password'] : "")),
					'message' => Language::_("Plesk.!error.plesk_confirm_password.matches", true)
				)
			),
			'plesk_webspace_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateWebspaceExists"), $package),
					'message' => Language::_("Plesk.!error.plesk_webspace_id.exists", true)
				)
			)
		);
		
		// Set the values that may be empty
		$empty_values = array("plesk_username", "plesk_password", "plesk_confirm_password");
		if (!$edit)
			$empty_values[] = "plesk_webspace_id";
		else {
			// On edit, domain is optional
			$rules['plesk_domain']['format']['if_set'] = true;
		}
		
		// Remove rules on empty fields
		foreach ($empty_values as $value) {
			// Confirm password must be given if password is too
			if ($value == "plesk_confirm_password" && !empty($vars['plesk_password']))
				continue;
			
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
		// Get module row and API
		$module_row = $this->getModuleRow();
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		$client_id = $vars['client_id'];
		
		// If no username or password given, generate them
		if (empty($vars['plesk_username']))
			$vars['plesk_username'] = $this->generateUsername((isset($vars['plesk_domain']) ? $vars['plesk_domain'] : ""), $client_id);
		if (empty($vars['plesk_password'])) {
			$vars['plesk_password'] = $this->generatePassword();
			$vars['plesk_confirm_password'] = $vars['plesk_password'];
		}
		
		$params = $this->getFieldsFromInput((array)$vars, $package);
		
		$this->validateService($package, $vars);
		
		if ($this->Input->errors())
			return;
		
		// Only provision the service if 'use_module' is true
		if ($vars['use_module'] == "true") {
			$api_version = $this->getApiVersion($module_row->meta->panel_version);
			
			// Create a reseller account
			if ($package->meta->type == "reseller") {
				$response = $this->createResellerAccount($module_row, $package, $client_id, $params);
			}
			else {
				// Create a user account
				$response = $this->createCustomerAccount($module_row, $package, $client_id, $params);
			}
			
			if ($this->Input->errors())
				return;
			
			// Create the webspace/domain subscription service
			try {
				$subscription = $api->loadCommand("plesk_subscriptions", array($api_version));
				$plan = array('id' => $package->meta->plan);
				
				$data = array(
					'general' => array(
						'name' => $params['domain'],
						'ip_address' => $module_row->meta->ip_address,
						'owner_login' => $params['username'],
						'htype' => "vrt_hst",
						'status' => "0"
					),
					'hosting' => array(
						'properties' => array(
							'ftp_login' => $params['username'],
							'ftp_password' => $params['password']
						),
						'ipv4' => $module_row->meta->ip_address
					)
				);
				
				// Set the plan on the subscription only for non-resellers;
				// The reseller has the plan associated with their account
				if ($package->meta->type != "reseller")
					$data['plan'] = $plan;
				
				$masked_data = $data;
				$masked_data['hosting']['properties']['ftp_password'] = "***";
				
				$this->log($module_row->meta->ip_address . "|webspace:add", serialize($masked_data), "input", true);
				$response = $this->parseResponse($subscription->add($data), $module_row);
				
				// Set the webspace ID
				if (property_exists($response->result, "id"))
					$params['webspace_id'] = $response->result->id;
			}
			catch (Exception $e) {
				// API request failed
				$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
				return;
			}
		}
		
		// Return service fields
		return array(
			array(
				'key' => "plesk_domain",
				'value' => $params['domain'],
				'encrypted' => 0
			),
			array(
				'key' => "plesk_username",
				'value' => $params['username'],
				'encrypted' => 0
			),
			array(
				'key' => "plesk_password",
				'value' => $params['password'],
				'encrypted' => 1
			),
			array(
				'key' => "plesk_webspace_id",
				'value' => (isset($response) && property_exists($response->result, "id") ? $response->result->id : null),
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
		// Get module row and API
		$module_row = $this->getModuleRow();
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		$client_id = $service->client_id;
		
		// If no username or password given, generate them
		if (isset($vars['plesk_username']) && $vars['plesk_username'] == "")
			$vars['plesk_username'] = $this->generateUsername((isset($vars['plesk_domain']) ? $vars['plesk_domain'] : ""), $client_id);
		if (isset($vars['plesk_password']) && $vars['plesk_password'] == "") {
			$vars['plesk_password'] = $this->generatePassword();
			$vars['plesk_confirm_password'] = $vars['plesk_password'];
		}
		
		$params = $this->getFieldsFromInput((array)$vars, $package);
		
		$this->validateService($package, $vars, true);
		
		if ($this->Input->errors())
			return;
		
		// Get the service fields
		$service_fields = $this->serviceFieldsToObject($service->fields);
		
		// Only use the module to update the service if 'use_module' is true
		if ($vars['use_module'] == "true") {
			$api_version = $this->getApiVersion($module_row->meta->panel_version);
			
			// Update the reseller account
			if ($package->meta->type == "reseller") {
				$response = $this->updateResellerAccount($module_row, $service_fields, $params);
			}
			else {
				// Update the user account
				$response = $this->updateCustomerAccount($module_row, $service_fields, $params);
			}
			
			if ($this->Input->errors())
				return;
			
			// Set updated fields
			if ($response && $response->result->status == "ok") {
				$service_fields->plesk_username = $params['username'];
				$service_fields->plesk_password = $params['password'];
			}
			
			// Update the webspace/domain
			try {
				$subscription = $api->loadCommand("plesk_subscriptions", array($api_version));
				
				// Set the information to update
				$data = array(
					'filter' => array(),
					'general' => array('name' => $params['domain'])
				);
				
				// Identify the subscription to change by name (domain), subscription ID, or by the customer login user
				if (!empty($service_fields->plesk_domain))
					$data['filter']['name'] = $service_fields->plesk_domain;
				elseif (!empty($service_fields->plesk_webspace_id))
					$data['filter']['id'] = $service_fields->plesk_webspace_id;
				elseif (!empty($service_fields->plesk_username))
					$data['filter']['owner_login'] = $service_fields->plesk_username;
				
				$this->log($module_row->meta->ip_address . "|webspace:set", serialize($data), "input", true);
				$response = $this->parseResponse($subscription->set($data), $module_row);
				
				// Set updated fields
				if ($response && $response->result->status == "ok") {
					$service_fields->plesk_domain = $params['domain'];
				}
			}
			catch (Exception $e) {
				// API request failed
				$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
			}
			
			if ($this->Input->errors())
				return;
		}
		
		// Set fields to update locally
		$fields = array("plesk_username", "plesk_password", "plesk_domain", "plesk_webspace_id");
		foreach ($fields as $field) {
			if (property_exists($service_fields, $field) && isset($vars[$field]))
				$service_fields->{$field} = $vars[$field];
		}
		
		// Return all the service fields
		$fields = array();
		$encrypted_fields = array("plesk_password");
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
			$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
			$api_version = $this->getApiVersion($module_row->meta->panel_version);
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			// Cancel (delete) the service (webspace subscription)
			try {
				$subscription = $api->loadCommand("plesk_subscriptions", array($api_version));
				
				// Identify the subscription by name (domain) or by the subscription webspace ID
				$data = array();
				if (!empty($service_fields->plesk_domain))
					$data['names'] = array($service_fields->plesk_domain);
				elseif (!empty($service_fields->plesk_webspace_id))
					$data['ids'] = array($service_fields->plesk_webspace_id);
				
				
				// Some filter options must be set to avoid Plesk deleting everything
				if (empty($data['names']) && empty($data['ids'])) {
					$this->Input->setErrors(array('api' => array('filter-missing' => Language::_("Plesk.!error.api.webspace_delete_filter_missing", true))));
					return;
				}
				
				$this->log($module_row->meta->ip_address . "|webspace:del", serialize($data), "input", true);
				$response = $this->parseResponse($subscription->delete($data), $module_row);
			}
			catch (Exception $e) {
				// API request failed
				$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
			}
			
			if ($this->Input->errors())
				return;
			
			// Delete the customer/reseller account
			if ($package->meta->type == "reseller")
				$this->deleteResellerAccount($module_row, $service_fields);
			else
				$this->deleteCustomerAccount($module_row, $service_fields);
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
		// Suspend the subscription
		$this->changeSubscriptionStatus($package, $service, $parent_package, $parent_service, true);
		
		if ($this->Input->errors())
			return;
		
		// Suspend the customer/reseller account
		$this->changeAccountStatus($package, $service, $parent_package, $parent_service, true);
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
		// Unsuspend the subscription
		$this->changeSubscriptionStatus($package, $service, $parent_package, $parent_service, false);
		
		if ($this->Input->errors())
			return;
		
		// Unsuspends the customer/reseller account
		$this->changeAccountStatus($package, $service, $parent_package, $parent_service, false);
		return null;
	}
	
	/**
	 * Suspends or unsuspends a subscription. Sets Input errors on failure,
	 * preventing the service from being (un)suspended.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being unsuspended (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @param $suspend True to suspend,  false to unsuspend (optional, default true)
	 * @see Plesk::suspendService(), Plesk::unsuspendService()
	 */
	private function changeSubscriptionStatus($package, $service, $parent_package=null, $parent_service=null, $suspend=true) {
		if (($module_row = $this->getModuleRow())) {
			$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
			$service_fields = $this->serviceFieldsToObject($service->fields);
			$reseller = (isset($module_row->meta->reseller) && $module_row->meta->reseller == "true");
			
			// Suspend/unsuspend the service (webspace subscription)
			try {
				$subscription = $api->loadCommand("plesk_subscriptions", array($this->getApiVersion($module_row->meta->panel_version)));
				
				// Change the general information status
				$data = array('filter' => array(), 'general' => array('status' => ($suspend ? ($reseller ? "32" : "16") : "0")));
				
				// Identify the subscription to update by name (domain), subscription ID, or by the customer login user
				if (!empty($service_fields->plesk_domain))
					$data['filter']['name'] = $service_fields->plesk_domain;
				elseif (!empty($service_fields->plesk_webspace_id))
					$data['filter']['id'] = $service_fields->plesk_webspace_id;
				elseif (!empty($service_fields->plesk_username))
					$data['filter']['owner_login'] = $service_fields->plesk_username;
				
				$this->log($module_row->meta->ip_address . "|webspace:set", serialize($data), "input", true);
				$response = $this->parseResponse($subscription->set($data), $module_row);
			}
			catch (Exception $e) {
				// API request failed
				$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
			}
		}
	}
	
	/**
	 * Suspends or unsuspends a customer. Sets Input errors on failure,
	 * preventing the service from being (un)suspended.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being unsuspended (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @param $suspend True to suspend,  false to unsuspend (optional, default true)
	 * @see Plesk::suspendService(), Plesk::unsuspendService()
	 */
	private function changeAccountStatus($package, $service, $parent_package=null, $parent_service=null, $suspend=true) {
		if (($module_row = $this->getModuleRow())) {
			$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
			$service_fields = $this->serviceFieldsToObject($service->fields);
			$reseller_account = (isset($module_row->meta->reseller) && $module_row->meta->reseller == "true");
			
			// Suspend/unsuspend the account
			try {
				if ($package->meta->type == "reseller") {
					// Update reseller account
					$reseller = $api->loadCommand("plesk_reseller_accounts", array($this->getApiVersion($module_row->meta->panel_version)));
					
					$data = array('filter' => array('login' => $service_fields->plesk_username), 'general' => array('status' => ($suspend ? ($reseller_account ? "32" : "16") : "0")));
					
					$this->log($module_row->meta->ip_address . "|reseller:set", serialize($data), "input", true);
					$response = $this->parseResponse($reseller->set($data), $module_row, true);
				}
				else {
					// Update customer account
					$customer = $api->loadCommand("plesk_customer_accounts", array($this->getApiVersion($module_row->meta->panel_version)));
					
					$data = array('filter' => array('login' => $service_fields->plesk_username), 'general' => array('status' => ($suspend ? ($reseller_account ? "32" : "16") : "0")));
					
					$this->log($module_row->meta->ip_address . "|customer:set", serialize($data), "input", true);
					$response = $this->parseResponse($customer->set($data), $module_row, true);
				}
			}
			catch (Exception $e) {
				// API request failed
				$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
			}
		}
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
		if (($module_row = $this->getModuleRow())) {
			
			if (!isset($this->DataStructure))
					Loader::loadHelpers($this, array("DataStructure"));
			if (!isset($this->ArrayHelper))
				$this->ArrayHelper = $this->DataStructure->create("Array");
				
			$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
			
			// Set the plan/type to update
			$update_plan = array('reseller' => false, 'to_plan' => $package_to->meta->plan, 'from_plan' => $package_from->meta->plan);
			
			// Set whether a reseller plan is being changed
			$from_reseller_plan = (isset($package_from->meta->reseller_plan) ? $package_from->meta->reseller_plan : null);
			$to_reseller_plan = (isset($package_to->meta->reseller_plan) ? $package_to->meta->reseller_plan : null);
			
			// Reseller plan changed, upgrade the customer and set the reseller plan to update
			if ($from_reseller_plan != $to_reseller_plan) {
				// Changing reseller plans
				$update_plan['reseller'] = true;
				$update_plan['to_plan'] = $to_reseller_plan;
				$update_plan['from_plan'] = $from_reseller_plan;
				
				// Cannot downgrade from reseller account to customer account
				if (!empty($from_reseller_plan) && empty($to_reseller_plan)) {
					$this->Input->setErrors(array('downgrade' => array('unsupported' => Language::_("Plesk.!error.downgrade.unsupported", true))));
				}
				elseif (empty($from_reseller_plan) && !empty($to_reseller_plan)) {
					// Upgrade the customer account to a reseller account
					$this->upgradeCustomerToReseller($module_row, $service);
				}
			}
			
			// Do not continue if there are errors
			if ($this->Input->errors())
				return;
			
			// Only change a plan change if it has changed; a customer account plan or a reseller plan
			if ($update_plan['from_plan'] != $update_plan['to_plan']) {
				$service_fields = $this->serviceFieldsToObject($service->fields);
				
				// Fetch all of the plans
				$plans = $this->getPleskPlans($module_row, $update_plan['reseller'], false);
				
				// Determine the plan's GUID based on the plan ID we currently have
				$plans = $this->ArrayHelper->numericToKey($plans, "id", "guid");
				$plan_guid = "";
				if (isset($plans[$update_plan['to_plan']]))
					$plan_guid = $plans[$update_plan['to_plan']];
				
				$api_version = $this->getApiVersion($module_row->meta->panel_version);
				
				// Switch reseller plan
				if ($update_plan['reseller']) {
					try {
						// Change customer account subscription plan
						$reseller = $api->loadCommand("plesk_reseller_accounts", array($api_version));
						
						// Set the new plan to switch to using the plan's GUID
						$data = array('filter' => array('login' => $service_fields->plesk_username), 'plan' => array('guid' => $plan_guid));
						
						$this->log($module_row->meta->ip_address . "|reseller:switch-subscription", serialize($data), "input", true);
						$response = $this->parseResponse($reseller->changePlan($data), $module_row);
					}
					catch (Exception $e) {
						// API request failed
						$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
					}
				}
				
				if ($this->Input->errors())
					return;
				
				// Also switch the subscription plan if it has changed
				if ($package_from->meta->plan != $package_to->meta->plan) {
					// Since the reseller plan was update, we also now need to fetch the subscription plans
					if ($update_plan['reseller']) {
						// Fetch subscription plans
						$plans = $this->getPleskPlans($module_row, false, false);
						
						// Determine the plan's GUID based on the plan ID we currently have
						$plans = $this->ArrayHelper->numericToKey($plans, "id", "guid");
						$plan_guid = "";
						if (isset($plans[$update_plan['to_plan']]))
							$plan_guid = $plans[$update_plan['to_plan']];
					}
					
					try {
						// Change customer account subscription plan
						$subscription = $api->loadCommand("plesk_subscriptions", array($api_version));
						
						// Set the new plan to switch to using the plan's GUID
						$data = array('filter' => array(), 'plan' => array('guid' => $plan_guid));
						
						// Identify the subscription to update by name (domain), subscription ID, or by the customer login user
						if (!empty($service_fields->plesk_domain))
							$data['filter']['name'] = $service_fields->plesk_domain;
						elseif (!empty($service_fields->plesk_webspace_id))
							$data['filter']['id'] = $service_fields->plesk_webspace_id;
						elseif (!empty($service_fields->plesk_username))
							$data['filter']['owner_login'] = $service_fields->plesk_username;
						
						$this->log($module_row->meta->ip_address . "|webspace:switch-subscription", serialize($data), "input", true);
						$response = $this->parseResponse($subscription->changePlan($data), $module_row);
					}
					catch (Exception $e) {
						// API request failed
						$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
					}
				}
			}
		}
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "plesk" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "plesk" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		// Set default port
		if (empty($vars))
			$vars['port'] = "8443";
		
		$this->view->set("vars", (object)$vars);
		$this->view->set("panel_versions", $this->getSupportedPanelVersions(true));
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "plesk" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		if (empty($vars))
			$vars = $module_row->meta;
		
		$this->view->set("vars", (object)$vars);
		$this->view->set("panel_versions", $this->getSupportedPanelVersions(true));
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
		$meta_fields = array("server_name", "ip_address", "port", "username", "password", "panel_version", "reseller");
		$encrypted_fields = array("username", "password");
		
		// Set checkbox value for whether this user is a reseller
		$vars['reseller'] = (isset($vars['reseller']) && $vars['reseller'] == "true" ? "true" : "false");
		
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
		return array('first'=>Language::_("Plesk.order_options.first", true));
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
		Loader::loadHelpers($this, array("Html"));
		
		$fields = new ModuleFields();
		$fields->setHtml("
			<script type=\"text/javascript\">
				$(document).ready(function() {
					$('input[name=\"meta[type]\"]').change(function() {
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
		
		// Fetch plans
		$plans = array('' => Language::_("Plesk.please_select", true));
		if ($module_row) {
			$plans += $this->getPleskPlans($module_row);
		}
		
		// Set the type of account (standard or reseller)
		$type = $fields->label(Language::_("Plesk.package_fields.type", true), "plesk_type");
		$type_standard = $fields->label(Language::_("Plesk.package_fields.type_standard", true), "plesk_type_standard");
		$type_reseller = $fields->label(Language::_("Plesk.package_fields.type_reseller", true), "plesk_type_reseller");
		$type->attach($fields->fieldRadio("meta[type]", "standard",
			$this->Html->ifSet($vars->meta['type'], "standard") == "standard", array('id'=>"plesk_type_standard"), $type_standard));
		$type->attach($fields->fieldRadio("meta[type]", "reseller",
			$this->Html->ifSet($vars->meta['type']) == "reseller", array('id'=>"plesk_type_reseller"), $type_reseller));
		$fields->setField($type);
		
		// Set the Plesk plans as selectable options
		$package = $fields->label(Language::_("Plesk.package_fields.plan", true), "plesk_plan");
		$package->attach($fields->fieldSelect("meta[plan]", $plans,
			$this->Html->ifSet($vars->meta['plan']), array('id'=>"plesk_plan")));
		$fields->setField($package);
		
		// Set the reseller account plan
		if (isset($vars->meta['type']) && $vars->meta['type'] == "reseller") {
			// Fetch the reseller plans
			$reseller_plans = array('' => Language::_("Plesk.please_select", true));
			$reseller_plans += $this->getPleskPlans($module_row, true);
			
			// Set the Plesk reseller account plans as selectable options
			$package = $fields->label(Language::_("Plesk.package_fields.reseller_plan", true), "plesk_reseller_plan");
			$package->attach($fields->fieldSelect("meta[reseller_plan]", $reseller_plans,
				$this->Html->ifSet($vars->meta['reseller_plan']), array('id'=>"plesk_reseller_plan")));
			$fields->setField($package);
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
			'module' => array("ip_address", "port"),
			'package' => array("type", "plan", "reseller_plan"),
			'service' => array("plesk_domain", "plesk_username", "plesk_password", "plesk_webspace_id")
		);
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
		$domain = $fields->label(Language::_("Plesk.service_field.domain", true), "plesk_domain");
		// Create domain field and attach to domain label
		$domain->attach($fields->fieldText("plesk_domain", $this->Html->ifSet($vars->plesk_domain), array('id'=>"plesk_domain")));
		// Set the label as a field
		$fields->setField($domain);
		
		// Create username label
		$username = $fields->label(Language::_("Plesk.service_field.username", true), "plesk_username");
		// Create username field and attach to username label
		$username->attach($fields->fieldText("plesk_username", $this->Html->ifSet($vars->plesk_username), array('id'=>"plesk_username")));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Plesk.service_field.tooltip.username", true));
		$username->attach($tooltip);
		// Set the label as a field
		$fields->setField($username);
		
		// Create password label
		$password = $fields->label(Language::_("Plesk.service_field.password", true), "plesk_password");
		// Create password field and attach to password label
		$password->attach($fields->fieldPassword("plesk_password", array('id'=>"plesk_password", 'value'=>$this->Html->ifSet($vars->plesk_password))));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Plesk.service_field.tooltip.password", true));
		$password->attach($tooltip);
		// Set the label as a field
		$fields->setField($password);
		
		// Confirm password label
		$confirm_password = $fields->label(Language::_("Plesk.service_field.confirm_password", true), "plesk_confirm_password");
		// Create confirm password field and attach to password label
		$confirm_password->attach($fields->fieldPassword("plesk_confirm_password", array('id'=>"plesk_confirm_password", 'value'=>$this->Html->ifSet($vars->plesk_password))));
		// Add tooltip
		$confirm_password->attach($tooltip);
		// Set the label as a field
		$fields->setField($confirm_password);
		
		$webspace_id = $fields->label(Language::_("Plesk.service_field.webspace_id", true), "plesk_webspace_id");
		// Create confirm password field and attach to password label
		$webspace_id->attach($fields->fieldText("plesk_webspace_id", $this->Html->ifSet($vars->plesk_webspace_id), array('id'=>"plesk_webspace_id")));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Plesk.service_field.tooltip.webspace_id", true));
		$webspace_id->attach($tooltip);
		// Set the label as a field
		$fields->setField($webspace_id);
		
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
		$domain = $fields->label(Language::_("Plesk.service_field.domain", true), "plesk_domain");
		// Create domain field and attach to domain label
		$domain->attach($fields->fieldText("plesk_domain", $this->Html->ifSet($vars->plesk_domain, $this->Html->ifSet($vars->domain)), array('id'=>"plesk_domain")));
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
		$domain = $fields->label(Language::_("Plesk.service_field.domain", true), "plesk_domain");
		// Create domain field and attach to domain label
		$domain->attach($fields->fieldText("plesk_domain", $this->Html->ifSet($vars->plesk_domain), array('id'=>"plesk_domain")));
		// Set the label as a field
		$fields->setField($domain);
		
		// Create username label
		$username = $fields->label(Language::_("Plesk.service_field.username", true), "plesk_username");
		// Create username field and attach to username label
		$username->attach($fields->fieldText("plesk_username", $this->Html->ifSet($vars->plesk_username), array('id'=>"plesk_username")));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Plesk.service_field.tooltip.username", true));
		$username->attach($tooltip);
		// Set the label as a field
		$fields->setField($username);
		
		// Create password label
		$password = $fields->label(Language::_("Plesk.service_field.password", true), "plesk_password");
		// Create password field and attach to password label
		$password->attach($fields->fieldPassword("plesk_password", array('id'=>"plesk_password", 'value'=>$this->Html->ifSet($vars->plesk_password))));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Plesk.service_field.tooltip.password", true));
		$password->attach($tooltip);
		// Set the label as a field
		$fields->setField($password);
		
		// Confirm password label
		$confirm_password = $fields->label(Language::_("Plesk.service_field.confirm_password", true), "plesk_confirm_password");
		// Create confirm password field and attach to password label
		$confirm_password->attach($fields->fieldPassword("plesk_confirm_password", array('id'=>"plesk_confirm_password", 'value'=>$this->Html->ifSet($vars->plesk_password))));
		// Add tooltip
		$confirm_password->attach($tooltip);
		// Set the label as a field
		$fields->setField($confirm_password);
		
		$webspace_id = $fields->label(Language::_("Plesk.service_field.webspace_id", true), "plesk_webspace_id");
		// Create confirm password field and attach to password label
		$webspace_id->attach($fields->fieldText("plesk_webspace_id", $this->Html->ifSet($vars->plesk_webspace_id), array('id'=>"plesk_webspace_id")));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Plesk.service_field.tooltip.webspace_id_edit", true));
		$webspace_id->attach($tooltip);
		// Set the label as a field
		$fields->setField($webspace_id);
		
		return $fields;
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "plesk" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "plesk" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("module_row", $row);
		$this->view->set("package", $package);
		$this->view->set("service", $service);
		$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));
		
		return $this->view->fetch();
	}
	
	/**
	 * Statistics tab
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
		#$this->view->set("user_type", $package->meta->type);
		
		$this->view->setDefaultView("components" . DS . "modules" . DS . "plesk" . DS);
		return $this->view->fetch();
	}
	
	/**
	 * Client Statistics tab
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
		#$this->view->set("user_type", $package->meta->type);
		
		$this->view->setDefaultView("components" . DS . "modules" . DS . "plesk" . DS);
		return $this->view->fetch();
	}
	
	/**
	 * Fetches all status for a given subscription service
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @return stdClass A stdClass object representing all of the stats for the account
	 */
	private function getStats($package, $service) {
		$module_row = $this->getModuleRow();
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		
		$service_fields = $this->serviceFieldsToObject($service->fields);
		
		$stats = new stdClass();
		$stats->account_info = array(
			'domain' => $service_fields->plesk_domain,
			'ip_address' => $module_row->meta->ip_address
		);
		$stats->disk_usage = array(
			'used' => null,
			'used_formatted' => null,
			'limit' => null,
			'limit_formatted' => null,
			'unused' => null,
			'unused_formatted' => Language::_("Plesk.stats.unlimited", true)
		);
		$stats->bandwidth_usage = array(
			'used' => null,
			'used_formatted' => null,
			'limit' => null,
			'limit_formatted' => null,
			'unused' => null,
			'unused_formatted' => Language::_("Plesk.stats.unlimited", true)
		);
		
		$response = false;
		try {
			$subscription = $api->loadCommand("plesk_subscriptions", array($this->getApiVersion($module_row->meta->panel_version)));
			
			// Fetch these stats
			$options = array("gen_info", "hosting", "limits", "stat", "prefs", "disk_usage",
				"performance", "subscriptions", "permissions", "plan-items", "php-settings");
			
			$data = array(
				'id' => $service_fields->plesk_webspace_id,
				'settings' => array()
			);
			
			// Set the stats we want to fetch
			foreach ($options as $option) {
				$data['settings'][$option] = true;
			}
			
			$this->log($module_row->meta->ip_address . "|webspace:get", serialize($data), "input", true);
			$response = $this->parseResponse($subscription->get($data), $module_row);
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
		
		// Format the results for the stats we will display
		if ($response && isset($response->result->data)) {
			$data = $response->result->data;
			
			// Set account info
			$stats->account_info['domain'] = $data->gen_info->name;
			$stats->account_info['ip_address'] = $data->gen_info->dns_ip_address;
			
			// Fetch account limits
			$totals = array();
			foreach ($data->limits->limit as $item) {
				$totals[$item->name] = $item->value;
			}
			
			// Set bandwidth usage
			$stats->bandwidth_usage['used'] = $data->stat->traffic;
			$stats->bandwidth_usage['limit'] = (isset($totals['max_traffic']) ? $totals['max_traffic'] : null);
			
			// Set disk usage
			$stats->disk_usage['limit'] = (isset($totals['disk_space']) ? $totals['disk_space'] : null);
			$total_disk_usage = 0;
			
			$disk_usage_options = array("httpdocs", "httpsdocs", "subdomains", "web_users", "anonftp", "logs", "dbases", "mailboxes",
				"webapps", "maillists", "domaindumps", "configs", "chroot");
			foreach ($disk_usage_options as $option) {
				if (property_exists($data->disk_usage, $option))
					$total_disk_usage += $data->disk_usage->{$option};
			}
			$stats->disk_usage['used'] = $total_disk_usage;
			
			// Format the values
			if ($stats->disk_usage['limit'] == "-1")
				$stats->disk_usage['limit_formatted'] = Language::_("Plesk.stats.unlimited", true);
			else {
				$stats->disk_usage['limit_formatted'] = $this->convertBytesToString($stats->disk_usage['limit']);
				
				// Set unused
				$stats->disk_usage['unused'] = abs($stats->disk_usage['limit']-$stats->disk_usage['used']);
				$stats->disk_usage['unused_formatted'] = $this->convertBytesToString($stats->disk_usage['unused']);
			}
			
			if ($stats->bandwidth_usage['limit'] == "-1")
				$stats->bandwidth_usage['limit_formatted'] = Language::_("Plesk.stats.unlimited", true);
			else {
				$stats->bandwidth_usage['limit_formatted'] = $this->convertBytesToString($stats->bandwidth_usage['limit']);
				
				// Set unused
				$stats->bandwidth_usage['unused'] = abs($stats->bandwidth_usage['limit']-$stats->bandwidth_usage['used']);
				$stats->bandwidth_usage['unused_formatted'] = $this->convertBytesToString($stats->bandwidth_usage['unused']);
			}
			
			$stats->disk_usage['used_formatted'] = $this->convertBytesToString($stats->disk_usage['used']);
			$stats->bandwidth_usage['used_formatted'] = $this->convertBytesToString($stats->bandwidth_usage['used']);
		}
		
		return $stats;
	}
	
	/**
	 * Converts bytes to a string representation including the type
	 *
	 * @param int $bytes The number of bytes
	 * @return string A formatted amount including the type (B, KB, MB, GB)
	 */
	private function convertBytesToString($bytes) {
		$step = 1024;
		$unit = "B";
		
		if (($value = number_format($bytes/($step*$step*$step), 2)) >= 1)
			$unit = "GB";
		elseif (($value = number_format($bytes/($step*$step), 2)) >= 1)
			$unit = "MB";
		elseif (($value = number_format($bytes/($step), 2)) >= 1)
			$unit = "KB";
		else
			$value = $bytes;
		
		return Language::_("Plesk.!bytes.value", true, $value, $unit);
	}
	
	/**
	 * Returns an array of service fields to set for the service using the given input
	 *
	 * @param array $vars An array of key/value input pairs
	 * @param stdClass $package A stdClass object representing the package for the service
	 * @return array An array of key/value pairs representing service fields
	 */
	private function getFieldsFromInput(array $vars, $package) {
		$fields = array(
			'domain' => isset($vars['plesk_domain']) ? $vars['plesk_domain'] : null,
			'username' => isset($vars['plesk_username']) ? $vars['plesk_username']: null,
			'password' => isset($vars['plesk_password']) ? $vars['plesk_password'] : null,
			'webspace_id' => !empty($vars['plesk_webspace_id']) ? $vars['plesk_webspace_id'] : null
		);
		
		return $fields;
	}
	
	/**
	 * Retrieves the module row given the server or server group
	 *
	 * @param string $module_row The module row ID
	 * @param string $module_group The module group (optional, default "")
	 * @return mixed An stdClass object representing the module row, or null if it could not be determined
	 */
	private function getModuleRowByServer($module_row, $module_group = "") {
		// Fetch the module row available for this package
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
	 * Fetches a listing of all service plans configured in Plesk for the given server
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param boolean $reseller True to fetch reseller plans, false for user/hosting plans (optional, default false)
	 * @param boolean $format True to format the response as a key/value pair (id => name), false to fetch all data (optional, default true)
	 * @return array An array of packages in key/value pairs
	 */
	private function getPleskPlans($module_row, $reseller = false, $format = true) {
		if (!isset($this->DataStructure))
			Loader::loadHelpers($this, array("DataStructure"));
		if (!isset($this->ArrayHelper))
			$this->ArrayHelper = $this->DataStructure->create("Array");
		
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		
		// Fetch the plans
		try {
			$api_version = $this->getApiVersion($module_row->meta->panel_version);
			
			// Fetch reseller plans
			if ($reseller) {
				$service_plans = $api->loadCommand("plesk_reseller_plans", array($api_version));
				
				// Fetch all reseller plans
				$data = array('filter' => array('all' => true));
				
				$this->log($module_row->meta->ip_address . "|reseller-plan:get", serialize($data), "input", true);
				$response = $this->parseResponse($service_plans->get($data), $module_row);
			}
			else {
				// Fetch user/hosting plans
				$service_plans = $api->loadCommand("plesk_service_plans", array($api_version));
				
				// Fetch all reseller plans
				$data = array('filter' => array());
				
				$this->log($module_row->meta->ip_address . "|service-plan:get", serialize($data), "input", true);
				$response = $this->parseResponse($service_plans->get($data), $module_row);
			}
			
			// Response is only an array if there is more than 1 result returned
			if (is_array($response->result)) {
				$result = $response->result;
				if ($format)
					$result = $this->ArrayHelper->numericToKey($response->result, "id", "name");
			}
			else {
				// Only 1 result
				$result = array($response->result);
				if ($format) {
					$result = array();
					if (property_exists($response->result, "id") && property_exists($response->result, "name"))
						$result = array($response->result->id => $response->result->name);
				}
			}
			
			return $result;
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
		
		return array();
	}
	
	/**
	 * Upgrades a customer account to a reseller account. Sets Input errors on failure
	 *
	 * @param stdClass $module_row An stdClass object representing a single server
	 * @param stdClass $service An stdClass object representing the service to upgrade
	 */
	private function upgradeCustomerToReseller($module_row, $service) {
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		
		$service_fields = $this->serviceFieldsToObject($service->fields);
		
		// Upgrade the account
		try {
			$customer = $api->loadCommand("plesk_customer_accounts", array($this->getApiVersion($module_row->meta->panel_version)));
			
			// Upgrade this customer account
			$data = array('filter' => array('login' => $service_fields->plesk_username));
			
			$this->log($module_row->meta->ip_address . "|customer:convert-to-reseller", serialize($data), "input", true);
			$response = $this->parseResponse($customer->upgrade($data), $module_row);
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
	}
	
	/**
	 * Creates a reseller account. Sets Input errors on failure
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param int $client_id The ID of the client this customer account is being created on behalf of
	 * @param array $params A list of data to pass into the reseller account
	 * 	- username The account username
	 * 	- password The account password
	 * @return stdClass An stdClass object representing the response
	 */
	private function createResellerAccount($module_row, $package, $client_id, $params) {
		// Fetch the client fields
		$client_params = $this->getClientAccountFields($client_id);
		
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		
		try {
			$reseller = $api->loadCommand("plesk_reseller_accounts", array($this->getApiVersion($module_row->meta->panel_version)));
			
			// Create the customer account
			$data = array_merge($client_params, array('login' => $params['username'], 'password' => $params['password'], 'plan' => array('id' => $package->meta->reseller_plan)));
			$masked_data = $data;
			$masked_data['password'] = "***";
			$this->log($module_row->meta->ip_address . "|reseller:add", serialize($masked_data), "input", true);
			$response = $this->parseResponse($reseller->add($data), $module_row);
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
		
		return (isset($response) ? $response : new stdClass());
	}
	
	/**
	 * Updates a reseller account. Sets Input errors on failure
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param stdClass $service_fields An stdClass object representing the service fields
	 * @param array $params A list of data to pass into the reseller account
	 * 	- username The account username
	 * 	- password The account password
	 * @return stdClass An stdClass object representing the response
	 */
	private function updateResellerAccount($module_row, $service_fields, $params) {
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		
		// Update the customer
		try {
			$reseller = $api->loadCommand("plesk_reseller_accounts", array($this->getApiVersion($module_row->meta->panel_version)));
			
			// Set the information to update
			$data = array(
				// Update this user
				'filter' => array('login' => $service_fields->plesk_username),
				// with this information
				'general' => array(
					'login' => $params['username'],
					'password' => $params['password']
				)
			);
			
			// Mask sensitive data
			$masked_data = $data;
			$masked_data['general']['password'] = "***";
			
			$this->log($module_row->meta->ip_address . "|reseller:set", serialize($masked_data), "input", true);
			$response = $this->parseResponse($reseller->set($data), $module_row);
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
		
		return (isset($response) ? $response : new stdClass());
	}
	
	/**
	 * Deletes a reseller account. Sets Input errors on failure
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param stdClass $service_fields An stdClass object representing the service fields
	 * @return stdClass An stdClass object representing the response
	 */
	private function deleteResellerAccount($module_row, $service_fields) {
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		
		// Delete the reseller account
		try {
			$reseller = $api->loadCommand("plesk_reseller_accounts", array($this->getApiVersion($module_row->meta->panel_version)));
			
			// Delete the account
			$data = array('filter' => array('login' => $service_fields->plesk_username));
			
			// Some filter options must be set to avoid Plesk deleting everything
			if (empty($data['filter']['login'])) {
				$this->Input->setErrors(array('api' => array('filter-missing' => Language::_("Plesk.!error.api.reseller_delete_filter_missing", true))));
				return;
			}
			
			$this->log($module_row->meta->ip_address . "|reseller:del", serialize($data), "input", true);
			$response = $this->parseResponse($reseller->delete($data), $module_row);
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
		
		return (isset($response) ? $response : new stdClass());
	}
	
	/**
	 * Creates a customer account. Sets Input errors on failure
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param int $client_id The ID of the client this customer account is being created on behalf of
	 * @param array $params A list of data to pass into the customer account
	 * 	- username The account username
	 * 	- password The account password
	 * @return stdClass An stdClass object representing the response
	 */
	private function createCustomerAccount($module_row, $package, $client_id, $params) {
		// Fetch the client fields
		$client_params = $this->getClientAccountFields($client_id);
		
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		
		try {
			$customer_accounts = $api->loadCommand("plesk_customer_accounts", array($this->getApiVersion($module_row->meta->panel_version)));
			
			// Create the customer account
			$data = array_merge($client_params, array('login' => $params['username'], 'password' => $params['password']));
			$masked_data = $data;
			$masked_data['password'] = "***";
			$this->log($module_row->meta->ip_address . "|customer:add", serialize($masked_data), "input", true);
			$response = $this->parseResponse($customer_accounts->add($data), $module_row);
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
		
		return (isset($response) ? $response : new stdClass());
	}
	
	/**
	 * Updates a customer account. Sets Input errors on failure
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param stdClass $service_fields An stdClass object representing the service fields
	 * @param array $params A list of data to pass into the customer account
	 * 	- username The account username
	 * 	- password The account password
	 * @return stdClass An stdClass object representing the response
	 */
	private function updateCustomerAccount($module_row, $service_fields, $params) {
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		
		// Update the customer
		try {
			$customer = $api->loadCommand("plesk_customer_accounts", array($this->getApiVersion($module_row->meta->panel_version)));
			
			// Set the information to update
			$data = array(
				// Update this user
				'filter' => array('login' => $service_fields->plesk_username),
				// with this information
				'general' => array(
					'login' => $params['username'],
					'password' => $params['password']
				)
			);
			
			// Mask sensitive data
			$masked_data = $data;
			$masked_data['general']['password'] = "***";
			
			$this->log($module_row->meta->ip_address . "|customer:set", serialize($masked_data), "input", true);
			$response = $this->parseResponse($customer->set($data), $module_row);
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
		
		return (isset($response) ? $response : new stdClass());
	}
	
	/**
	 * Deletes a customer account. Sets Input errors on failure
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @param stdClass $service_fields An stdClass object representing the service fields
	 * @return stdClass An stdClass object representing the response
	 */
	private function deleteCustomerAccount($module_row, $service_fields) {
		$api = $this->getApi($module_row->meta->ip_address, $module_row->meta->username, $module_row->meta->password, $module_row->meta->port);
		
		// Delete the customer account
		try {
			$customer_accounts = $api->loadCommand("plesk_customer_accounts", array($this->getApiVersion($module_row->meta->panel_version)));
			
			// Delete the account
			$data = array('filter' => array('login' => $service_fields->plesk_username));
			
			// Some filter options must be set to avoid Plesk deleting everything
			if (empty($data['filter']['login'])) {
				$this->Input->setErrors(array('api' => array('filter-missing' => Language::_("Plesk.!error.api.reseller_delete_filter_missing", true))));
				return;
			}
			
			$this->log($module_row->meta->ip_address . "|customer:del", serialize($data), "input", true);
			$response = $this->parseResponse($customer_accounts->delete($data), $module_row);
		}
		catch (Exception $e) {
			// API request failed
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
		}
		
		return (isset($response) ? $response : new stdClass());
	}
	
	/**
	 * Retrieves a list of fields for creating a customer/reseller account
	 *
	 * @param int $client_id The ID of the client whose fields to fetch
	 * @return array An array of client fields
	 * @see Plesk::createCustomerAccount(), Plesk::createResellerAccount()
	 */
	private function getClientAccountFields($client_id) {
		// Fetch the client to set additional client fields
		Loader::loadModels($this, array("Clients"));
		$client_params = array();
		if (($client = $this->Clients->get($client_id, false))) {
			$country = (!empty($client->country) ? $client->country : null);
			$client_params = array(
				'name' => $client->first_name . " " . $client->last_name,
				'email' => $client->email,
				'company' => (!empty($client->company) ? $client->company : null),
				'status' => "0",
				'address' => (empty($client->address1) ? null : ($client->address1 . (!empty($client->address2) ? " " . $client->address2 : ""))),
				'city' => (!empty($client->city) ? $client->city : null),
				'state' => (!empty($client->state) && $country == "US" ? $client->state : null),
				'country' => $country,
				'zipcode' => (!empty($client->zip) && $country == "US" ? $client->zip : null)
			);
		}
		
		return $client_params;
	}
	
	/**
	 * Parses the response from SolusVM into an stdClass object
	 *
	 * @param SolusvmResponse $response The response from the API
	 * @param string $xml_container_path The path to the XML container where the results reside
	 * @param stdClass $module_row A stdClass object representing a single server (optional, required when Module::getModuleRow() is unavailable)
	 * @param boolean $ignore_error Ignores any response error and returns the response anyway; useful when a response is expected to fail (e.g. check client exists) (optional, default false)
	 * @return stdClass A stdClass object representing the response, void if the response was an error
	 */
	private function parseResponse(PleskResponse $response, $module_row = null, $ignore_error = false) {
		Loader::loadHelpers($this, array("Html"));
		
		// Set the module row
		if (!$module_row)
			$module_row = $this->getModuleRow();
		
		$success = false;
		switch ($response->status()) {
			case "ok":
				$success = true;
				break;
			case "error":
				$success = false;
				
				// Ignore generating the error
				if ($ignore_error)
					break;
				
				// Set errors
				$errors = $response->errors();
				$error = "";
				
				if (isset($errors->errcode) && isset($errors->errtext))
					$error = $errors->errcode . " " . $errors->errtext;
				
				$this->Input->setErrors(array('api' => array('response' => $this->Html->safe($error))));
				break;
			default:
				// Invalid response
				$success = false;
				
				// Ignore generating the error
				if ($ignore_error)
					break;
				
				$this->Input->setErrors(array('api' => array('internal' => Language::_("Plesk.!error.api.internal", true))));
				break;
		}
		
		// Replace sensitive fields
		$masked_params = array();
		$output = $response->response();
		$raw_output = $response->raw();
		
		foreach ($masked_params as $masked_param) {
			if (property_exists($output, $masked_param))
				$raw_output = preg_replace("/<" . $masked_param . ">(.*)<\/" . $masked_param . ">/", "<" . $masked_param . ">***</" . $masked_param . ">", $raw_output);
		}
		
		// Log the response
		$this->log($module_row->meta->ip_address, $raw_output, "output", $success);
		
		if (!$success && !$ignore_error)
			return;
		
		return $output;
	}
	
	/**
	 * Initializes the CpanelApi and returns an instance of that object with the given $host, $user, and $pass set
	 *
	 * @param string $host The host to the Plesk server
	 * @param string $user The user to connect as
	 * @param string $pass The password to authenticate with
	 * @param string $port The port on the host to connect on
	 * @return PleskApi The PleskApi instance
	 */
	private function getApi($host, $user, $pass, $port) {
		Loader::load(dirname(__FILE__) . DS . "apis" . DS . "plesk_api.php");
		
		return new PleskApi($user, $pass, $host, $port);
	}
	
	/**
	 * Generates a username based on the given domain
	 *
	 * @param string $domain The domain name
	 * @param int $client_id The ID of the client
	 * @return string The FTP login username
	 */
	private function generateUsername($domain, $client_id) {
		// Remove everything except letters and numbers from the domain
		// ensure no number appears in the beginning
		$username = ltrim(preg_replace('/[^a-z0-9]/i', '', $domain), '0123456789');

		$length = strlen($username);
		$pool = "abcdefghijklmnopqrstuvwxyz0123456789";
		$pool_size = strlen($pool);
		
		if ($length < 5) {
			for ($i=$length; $i<8; $i++) {
				$username .= substr($pool, mt_rand(0, $pool_size-1), 1);
			}
			$length = strlen($username);
		}
		
		return (substr($username, 0, min($length, 8)) . $client_id);
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
					'message' => Language::_("Plesk.!error.server_name.empty", true)
				)
			),
			'ip_address' => array(
				'valid' => array(
					'rule' => array(array($this, "validateHostName")),
					'message' => Language::_("Plesk.!error.ip_address.valid", true)
				)
			),
			'port' => array(
				'format' => array(
					'rule' => array("matches", "/^[0-9]+$/"),
					'message' => Language::_("Plesk.!error.port.format", true)
				)
			),
			'username' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Plesk.!error.username.empty", true)
				)
			),
			'password' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Plesk.!error.password.empty", true)
				)
			),
			'panel_version' => array(
				'valid' => array(
					'rule' => array(array($this, "validatePanelVersions")),
					'message' => Language::_("Plesk.!error.panel_version.valid", true)
				)
			),
			'reseller' => array(
				'valid' => array(
					'rule' => array("in_array", array("true", "false")),
					'message' => Language::_("Plesk.!error.reseller.valid", true)
				)
			)
		);
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
					'message' => Language::_("Plesk.!error.meta[type].valid", true), // type must be standard or reseller
				)
			),
			'meta[plan]' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Plesk.!error.meta[plan].empty", true)
				)
			),
			'meta[reseller_plan]' => array(
				'empty' => array(
					'if_set' => true,
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Plesk.!error.meta[reseller_plan].empty", true)
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
	
	/**
	 * Validates that the given panel version is valid
	 *
	 * @param string $version The version to validate
	 * @return boolean True if the version validates, false otherwise
	 */
	public function validatePanelVersions($version) {
		return array_key_exists($version, $this->panel_versions);
	}
}
?>