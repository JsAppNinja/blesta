<?php
/**
 * TheSslStore Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.thesslstore
 * @author Phillips Data, Inc.
 * @author Full Ambit Networks
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link https://www.fullambit.net/
 */
class Thesslstore extends Module {
	
	/**
	 * @var string The version of this module
	 */
	private static $version = "2.0.2";
	/**
	 * @var string The authors of this module
	 */
	private static $authors = array(
		array('name' => "Phillips Data, Inc.", 'url' => "http://www.blesta.com"),
		array('name' => "Full Ambit Networks", 'url' => "https://fullambit.net")
	);
	
	/**
	 * Initializes the module
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("thesslstore", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this module
	 *
	 * @return string The common name of this module
	 */
	public function getName() {
		return Language::_("TheSSLStore.name", true);
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
			if ($field->key == "thesslstore_fqdn")
				return $field->value;
		}
		return "New";
	}
	
	/**
	 * Returns a noun used to refer to a module row (e.g. "Server", "VPS", "Reseller Account", etc.)
	 *
	 * @return string The noun used to refer to a module row
	 */
	public function moduleRowName() {
		return Language::_("TheSSLStore.module_row", true);
	}
	
	/**
	 * Returns a noun used to refer to a module row in plural form (e.g. "Servers", "VPSs", "Reseller Accounts", etc.)
	 *
	 * @return string The noun used to refer to a module row in plural form
	 */
	public function moduleRowNamePlural() {
		return Language::_("TheSSLStore.module_row_plural", true);
	}
	
	/**
	 * Returns a noun used to refer to a module group (e.g. "Server Group", "Cloud", etc.)
	 *
	 * @return string The noun used to refer to a module group
	 */
	public function moduleGroupName() {
		return null;
	}
	
	/**
	 * Returns the key used to identify the primary field from the set of module row meta fields.
	 * This value can be any of the module row meta fields.
	 *
	 * @return string The key used to identify the primary field from the set of module row meta fields
	 */
	public function moduleRowMetaKey() {
		return "thesslstore_name";
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
		if (isset($vars['thesslstore_name']))
			return $vars['thesslstore_name'];
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
		// Set rules
		$rules = array(
			'thesslstore_approver_email' => array(
				'format' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("TheSSLStore.!error.thesslstore_approver_email.format", true)
				)
			),
			'thesslstore_csr' => array(
				'format' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("TheSSLStore.!error.thesslstore_csr.format", true)
				)
			),
			'thesslstore_webserver_type' => array(
				'format' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("TheSSLStore.!error.thesslstore_webserver_type.format", true)
				)
			)
		);
		
		if(!$edit) {
			$rules['thesslstore_fqdn' ] = array(
				'format' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("TheSSLStore.!error.thesslstore_fqdn.format", true)
				)
			);
		}
		
		$this->Input->setRules($rules);
		return $this->Input->validates($vars);
	}
	
	/**
	 * Fills SSL data for order API calls from given vars
	 *
	 * @param stdClass $package The package
	 * @param integer $client_id The ID of the client
	 * @param mixed $vars Array or object representing user input
	 * @return mixed The SSL data prefilled for API calls
	 */
	private function fillSSLDataFrom($package, $client_id, $vars) {
		$vars = (object)$vars;
	
		//General contact to be used
		$contact = new contact();
		$contact->AddressLine1 = $vars->thesslstore_address1;
		$contact->AddressLine2 = $vars->thesslstore_address2;
		$contact->City = $vars->thesslstore_city;
		$contact->Country = $vars->thesslstore_country;
		$contact->Email = $vars->thesslstore_email;
		$contact->Fax = $vars->thesslstore_fax;
		$contact->FirstName = $vars->thesslstore_firstname;
		$contact->LastName = $vars->thesslstore_lastname;
		$contact->OrganizationName = $vars->thesslstore_organization;
		$contact->Phone = $vars->thesslstore_number;
		$contact->PostalCode = $vars->thesslstore_zip;
		$contact->Region = $vars->thesslstore_state;
		$contact->Title = $vars->thesslstore_title;

		$neworder = new order_neworder_request();		
		$neworder->AdminContact = $contact;
		$neworder->TechnicalContact = $contact;
		
		$neworder->ApproverEmail = $vars->thesslstore_approver_email;
		$neworder->WebServerType = $vars->thesslstore_webserver_type;
		
		$neworder->CSR = $vars->thesslstore_csr;
		
		$neworder->OrganisationInfo->DUNS = '';
		$neworder->OrganisationInfo->Division = $vars->thesslstore_organization_unit;
		$neworder->OrganisationInfo->IncorporatingAgency = '';
		$neworder->OrganisationInfo->JurisdictionCity = $contact->City;
		$neworder->OrganisationInfo->JurisdictionCountry = $contact->Country;
		$neworder->OrganisationInfo->JurisdictionRegion = $contact->Region;
		$neworder->OrganisationInfo->OrganizationName = $contact->OrganizationName;
		$neworder->OrganisationInfo->RegistrationNumber = '';
		$neworder->OrganisationInfo->OrganizationAddress->AddressLine1 = $contact->AddressLine1;
		$neworder->OrganisationInfo->OrganizationAddress->AddressLine2 = $contact->AddressLine2;
		$neworder->OrganisationInfo->OrganizationAddress->AddressLine3 = '';
		$neworder->OrganisationInfo->OrganizationAddress->City = $contact->City;
		$neworder->OrganisationInfo->OrganizationAddress->Country = $contact->Country;
		$neworder->OrganisationInfo->OrganizationAddress->Fax = $contact->Fax;
		$neworder->OrganisationInfo->OrganizationAddress->LocalityName = '';
		$neworder->OrganisationInfo->OrganizationAddress->Phone = $contact->Phone;
		$neworder->OrganisationInfo->OrganizationAddress->PostalCode = $contact->PostalCode;
		$neworder->OrganisationInfo->OrganizationAddress->Region = $contact->Region;
		
		$neworder->ProductCode = $package->meta->thesslstore_product;
		
		$neworder->ValidityPeriod = 12;
		foreach($package->pricing as $pricing) {
			if ($pricing->id == $vars->pricing_id) {
				if($pricing->period == 'month')
					$neworder->ValidityPeriod = $pricing->term;
				elseif($pricing->period == 'year')
					$neworder->ValidityPeriod = $pricing->term * 12;
				break;
			}
		}
		
		$neworder->ReserveSANCount = 0;
		$neworder->ServerCount = -1;
		$neworder->SpecialInstructions = '';
		$neworder->isCUOrder = false;
		$neworder->isTrialOrder = false;
		$neworder->FileAuthDVIndicator = false;
		$neworder->AddInstallationSupport = false;
		$neworder->EmailLanguageCode = 'EN';
		$neworder->ExtraProductCodes = '';
		
		$neworder->CustomOrderID = uniqid('FullOrder-');
		
		return $neworder;
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
		// Validate the service-specific fields
		$this->validateService($package, $vars);
		
		if ($this->Input->errors())
			return;
			
		$row = $this->getModuleRow($package->module_row);
		$api = $this->getApi($row->meta->api_partner_id, $row->meta->api_token, $row->meta->sandbox, $row);
		
		$order_id = '';
		
		if($vars["use_module"] == "true") {
			$neworder = $this->fillSSLDataFrom($package, (isset($vars['client_id']) ? $vars['client_id'] : ""), $vars);
			$neworder->isRenewalOrder = false;
			
			$this->log($row->meta->api_partner_id . "|ssl-new-order", serialize($neworder), "input", true);
			$result = $this->parseResponse($api->order_neworder($neworder), $row);
			
			if(empty($result)) {
				return;
			}
			
			if(!empty($result->TheSSLStoreOrderID)) {
				$order_id = $result->TheSSLStoreOrderID;
			}
			else {
				$this->Input->setErrors(array('api' => array('internal' => 'No OrderID')));
				return;
			}
		}
		
		// Return service fields
		return array(
			array(
				'key' => "thesslstore_approver_email",
				'value' => $vars["thesslstore_approver_email"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_fqdn",
				'value' => $vars["thesslstore_fqdn"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_webserver_type",
				'value' => $vars["thesslstore_webserver_type"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_csr",
				'value' => $vars["thesslstore_csr"],
				'encrypted' => 1
			),
			array(
				'key' => "thesslstore_orderid",
				'value' => $order_id,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_title",
				'value' => $vars["thesslstore_title"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_firstname",
				'value' => $vars["thesslstore_firstname"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_lastname",
				'value' => $vars["thesslstore_lastname"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_address1",
				'value' => $vars["thesslstore_address1"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_address2",
				'value' => $vars["thesslstore_address2"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_city",
				'value' => $vars["thesslstore_city"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_zip",
				'value' => $vars["thesslstore_zip"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_state",
				'value' => $vars["thesslstore_state"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_country",
				'value' => $vars["thesslstore_country"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_email",
				'value' => $vars["thesslstore_email"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_number",
				'value' => $vars["thesslstore_number"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_fax",
				'value' => $vars["thesslstore_fax"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_organization",
				'value' => $vars["thesslstore_organization"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_organization_unit",
				'value' => $vars["thesslstore_organization_unit"],
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
	public function editService($package, $service, array $vars=array(), $parent_package=null, $parent_service=null) {
		// Validate the service-specific fields
		$this->validateService($package, $vars, true);
		
		if ($this->Input->errors())
			return;
			
		$row = $this->getModuleRow($package->module_row);
		$api = $this->getApi($row->meta->api_partner_id, $row->meta->api_token, $row->meta->sandbox, $row);
		
		$service_fields = $this->serviceFieldsToObject($service->fields);
		
		$order_id = $service_fields->thesslstore_orderid;
		
		if($vars["use_module"] == "true") {
			$orderreissuereq = new order_reissue_request();
			$orderreissuereq->CSR = $vars["thesslstore_csr"];
			$orderreissuereq->TheSSLStoreOrderID = $service_fields->thesslstore_orderid;
			$orderreissuereq->WebServerType = $vars["thesslstore_webserver_type"];
			$orderreissuereq->DNSNames = array('');
			$orderreissuereq->isRenewalOrder = true;
			$orderreissuereq->SpecialInstructions = '';
			$orderreissuereq->AddSAN = array();
			$orderreissuereq->EditSAN = array();
			$orderreissuereq->DeleteSAN = array();
			$orderreissuereq->isWildCard = false;
			$orderreissuereq->ReissueEmail = $vars["thesslstore_approver_email"];

			$this->log($row->meta->api_partner_id . "|ssl-reissue", serialize($orderreissuereq), "input", true);
			$result = $this->parseResponse($api->order_reissue($orderreissuereq), $row);
			
			if(empty($result)) {
				return;
			}
			
			if(!empty($result->TheSSLStoreOrderID)) {
				$order_id = $result->TheSSLStoreOrderID;
			}
			else {
				$this->Input->setErrors(array('api' => array('internal' => 'No OrderID')));
				return;
			}
		}
		
		// Return service fields
		return array(
			array(
				'key' => "thesslstore_approver_email",
				'value' => $vars["thesslstore_approver_email"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_fqdn",
				'value' => $service_fields->thesslstore_fqdn,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_webserver_type",
				'value' => $vars["thesslstore_webserver_type"],
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_csr",
				'value' => $vars["thesslstore_csr"],
				'encrypted' => 1
			),
			array(
				'key' => "thesslstore_orderid",
				'value' => $order_id,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_title",
				'value' => $service_fields->thesslstore_title,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_firstname",
				'value' => $service_fields->thesslstore_firstname,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_lastname",
				'value' => $service_fields->thesslstore_lastname,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_address1",
				'value' => $service_fields->thesslstore_address1,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_address2",
				'value' => $service_fields->thesslstore_address2,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_city",
				'value' => $service_fields->thesslstore_city,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_zip",
				'value' => $service_fields->thesslstore_zip,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_state",
				'value' => $service_fields->thesslstore_state,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_country",
				'value' => $service_fields->thesslstore_country,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_email",
				'value' => $service_fields->thesslstore_email,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_number",
				'value' => $service_fields->thesslstore_number,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_fax",
				'value' => $service_fields->thesslstore_fax,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_organization",
				'value' => $service_fields->thesslstore_organization,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_organization_unit",
				'value' => $service_fields->thesslstore_organization_unit,
				'encrypted' => 0
			)
		);
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
		$row = $this->getModuleRow($package->module_row);
		$api = $this->getApi($row->meta->api_partner_id, $row->meta->api_token, $row->meta->sandbox, $row);
			
		$order_id = '';
		
		$service_fields = $this->serviceFieldsToObject($service->fields);
		
		if($vars["use_module"] == "true") {
			$neworder = $this->fillSSLDataFrom($package, $service->client_id, $service_fields);
			$neworder->isRenewalOrder = true;
			
			$this->log($row->meta->api_partner_id . "|ssl-renew-order", serialize($neworder), "input", true);
			$result = $this->parseResponse($api->order_neworder($data), $row);
			
			if(empty($result)) {
				return;
			}
			
			if(!empty($result->TheSSLStoreOrderID)) {
				$order_id = $result->TheSSLStoreOrderID;
			}
			else {
				$this->Input->setErrors(array('api' => array('internal' => 'No OrderID')));
				return;
			}
		}
		
		// Return service fields
		return array(
			array(
				'key' => "thesslstore_approver_email",
				'value' => $service_fields->thesslstore_approver_email,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_fqdn",
				'value' => $service_fields->thesslstore_fqdn,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_webserver_type",
				'value' => $service_fields->thesslstore_webserver_type,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_csr",
				'value' => $service_fields->thesslstore_csr,
				'encrypted' => 1
			),
			array(
				'key' => "thesslstore_orderid",
				'value' => $order_id,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_title",
				'value' => $service_fields->thesslstore_title,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_firstname",
				'value' => $service_fields->thesslstore_firstname,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_lastname",
				'value' => $service_fields->thesslstore_lastname,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_address1",
				'value' => $service_fields->thesslstore_address1,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_address2",
				'value' => $service_fields->thesslstore_address2,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_city",
				'value' => $service_fields->thesslstore_city,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_zip",
				'value' => $service_fields->thesslstore_zip,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_state",
				'value' => $service_fields->thesslstore_state,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_country",
				'value' => $service_fields->thesslstore_country,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_email",
				'value' => $service_fields->thesslstore_email,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_number",
				'value' => $service_fields->thesslstore_number,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_fax",
				'value' => $service_fields->thesslstore_fax,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_organization",
				'value' => $service_fields->thesslstore_organization,
				'encrypted' => 0
			),
			array(
				'key' => "thesslstore_organization_unit",
				'value' => $service_fields->thesslstore_organization_unit,
				'encrypted' => 0
			)
		);
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		// Set unspecified checkboxes
		if (!empty($vars)) {
			if (empty($vars['sandbox']))
				$vars['sandbox'] = "false";
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		if (empty($vars))
			$vars = $module_row->meta;
		else {
			// Set unspecified checkboxes
			if (empty($vars['sandbox']))
				$vars['sandbox'] = "false";
		}
		
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
		$meta_fields = array("thesslstore_name", "api_partner_id", "api_token", "sandbox");
		$encrypted_fields = array("api_partner_id", "api_token");
		
		// Set unspecified checkboxes
		if (empty($vars['sandbox']))
			$vars['sandbox'] = "false";
		
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
		return null; // Nothing to do
	}
	
	/**
	 * Returns all fields used when adding/editing a package, including any
	 * javascript to execute when the page is rendered with these fields.
	 *
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getPackageFields($vars=null) {
		Loader::loadHelpers($this, array("Form", "Html"));
		
		$fields = new ModuleFields();
		
		$row = null;
		if (isset($vars->module_group) && $vars->module_group == "") {
			if (isset($vars->module_row) && $vars->module_row > 0) {
				$row = $this->getModuleRow($vars->module_row);
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
			$rows = $this->getModuleRows($vars->module_group);
			
			if (isset($rows[0]))
				$row = $rows[0];
			unset($rows);
		}
		
		if($row) {
			$api = $this->getApi($row->meta->api_partner_id, $row->meta->api_token, $row->meta->sandbox, $row);
			$products = $this->getProducts($api, $row);
		} else {
			$products = array();
		}
		
		// Show nodes, and set javascript field toggles
		$this->Form->setOutput(true);
		
		// Set the product as a selectable option
		$thesslstore_product = $fields->label(Language::_("TheSSLStore.package_fields.product", true), "thesslstore_product");
		$thesslstore_product->attach($fields->fieldSelect("meta[thesslstore_product]", $products,
			$this->Html->ifSet($vars->meta['thesslstore_product']), array('id' => "thesslstore_product")));
		$fields->setField($thesslstore_product);
		unset($thesslstore_product);
		
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
			'package' => array("thesslstore_product"),
			'service' => array("thesslstore_approver_email", "thesslstore_fqdn", "thesslstore_webserver_type", "thesslstore_csr",
								"thesslstore_orderid", "thesslstore_title", "thesslstore_firstname", "thesslstore_lastname",
								"thesslstore_address1", "thesslstore_address2", "thesslstore_city", "thesslstore_zip", "thesslstore_state",
								"thesslstore_country", "thesslstore_email", "thesslstore_number", "thesslstore_fax", "thesslstore_organization",
								"thesslstore_organization_unit"
								)
		);
	}
	
	/**
	 * Returns array of valid approver E-Mails for domain
	 *
	 * @param TheSSLStoreApi $api the API to use
	 * @param stdClass $package The package
	 * @param string $domain The domain
	 * @return array E-Mails that are valid approvers for the domain
	 */
	public function getApproverEmails($api, $package, $domain) {
        if (empty($domain))
            return array();

		$row = $this->getModuleRow($package->module_row);
	
		$approverreq = new order_neworder_request();
		$approverreq->DomainName = $domain;
		$approverreq->AddInstallationSupport = false;
		$approverreq->ProductCode = $package->meta->thesslstore_product;
		$approverreq->ReserveSANCount = 0;
		$approverreq->ServerCount = -1;
		$approverreq->SpecialInstructions = '';
		$approverreq->ValidityPeriod = 12; //Does not even matter, we just want the approver E-mails...
		$approverreq->WebServerType = 'Other';
		$approverreq->isCUOrder = false;
		$approverreq->isRenewalOrder = false;
		$approverreq->isTrialOrder = false;
		$approverreq->FileAuthDVIndicator = false; //USED For DV File Authentication. Only for Symantec/Comodo Domain Vetted Products. You need to pass value "true".
		
		$this->log($row->meta->api_partner_id . "|ssl-domain-emails", serialize($approverreq), "input", true);
		$thesslstore_approver_emails = $this->parseResponse($api->order_approverlist($approverreq), $row);
		
        $emails = array();
        if ($thesslstore_approver_emails && !empty($thesslstore_approver_emails->ApproverEmailList)) {
            foreach ($thesslstore_approver_emails->ApproverEmailList as $email)
                $emails[$email] = $email;
        }
        
		return $emails;
	}
	
	/**
	 * Returns ModuleFields for adding a package
	 *
	 * @param stdClass $package The package
	 * @param stdClass $vars Passed vars
	 * @return ModuleFields Fields to display
	 */	
	private function makeAddFields($package, $vars) {
	
		Loader::loadHelpers($this, array("Form", "Html"));
				
		// Load the API
		$row = $this->getModuleRow($package->module_row);
		$api = $this->getApi($row->meta->api_partner_id, $row->meta->api_token, $row->meta->sandbox, $row);
	
		$fields = new ModuleFields();
		
		$fields->setHtml("
			<script type=\"text/javascript\">
                $(document).ready(function() {
                    $('#thesslstore_fqdn').change(function() {
                        var form = $(this).closest('form');
                        $(form).append('<input type=\"hidden\" name=\"refresh_fields\" value=\"true\">');
                        $(form).submit();
                    });
                });
			</script>
		");
		
		$thesslstore_fqdn = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_fqdn", true), "thesslstore_fqdn");
		$thesslstore_fqdn->attach($fields->fieldText("thesslstore_fqdn", $this->Html->ifSet($vars->thesslstore_fqdn), array('id' => "thesslstore_fqdn")));
		$fields->setField($thesslstore_fqdn);
		unset($thesslstore_fqdn);
	
		$approver_emails = $this->getApproverEmails($api, $package, $this->Html->ifSet($vars->thesslstore_fqdn));
		
		$thesslstore_approver_emails = array('' => Language::_("TheSSLStore.please_select", true)) + $approver_emails;
		$thesslstore_approver_email = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_approver_email", true), "thesslstore_approver_email");
		$thesslstore_approver_email->attach($fields->fieldSelect("thesslstore_approver_email", $thesslstore_approver_emails,
			$this->Html->ifSet($vars->thesslstore_approver_email), array('id' => "thesslstore_approver_email")));
		$fields->setField($thesslstore_approver_email);
		unset($thesslstore_approver_email);
		
		$thesslstore_csr = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_csr", true), "thesslstore_csr");
		$thesslstore_csr->attach($fields->fieldTextArea("thesslstore_csr", $this->Html->ifSet($vars->thesslstore_csr), array('id' => "thesslstore_csr")));
		$fields->setField($thesslstore_csr);
		unset($thesslstore_csr);
		
		$thesslstore_webserver_type = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_webserver_type", true), "thesslstore_webserver_type");
		$thesslstore_webserver_type->attach($fields->fieldSelect("thesslstore_webserver_type", $this->getWebserverTypes($api, $package),
			$this->Html->ifSet($vars->thesslstore_webserver_type), array('id' => "thesslstore_webserver_type")));
		$fields->setField($thesslstore_webserver_type);
		unset($thesslstore_webserver_type);
		
		$thesslstore_title = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_title", true), "thesslstore_title");
		$thesslstore_title->attach($fields->fieldText("thesslstore_title", $this->Html->ifSet($vars->thesslstore_title), array('id' => "thesslstore_title")));
		$fields->setField($thesslstore_title);
		unset($thesslstore_title);
		
		$thesslstore_firstname = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_firstname", true), "thesslstore_firstname");
		$thesslstore_firstname->attach($fields->fieldText("thesslstore_firstname", $this->Html->ifSet($vars->thesslstore_firstname), array('id' => "thesslstore_firstname")));
		$fields->setField($thesslstore_firstname);
		unset($thesslstore_firstname);
		
		$thesslstore_lastname = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_lastname", true), "thesslstore_lastname");
		$thesslstore_lastname->attach($fields->fieldText("thesslstore_lastname", $this->Html->ifSet($vars->thesslstore_lastname), array('id' => "thesslstore_lastname")));
		$fields->setField($thesslstore_lastname);
		unset($thesslstore_lastname);
		
		$thesslstore_address1 = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_address1", true), "thesslstore_address1");
		$thesslstore_address1->attach($fields->fieldText("thesslstore_address1", $this->Html->ifSet($vars->thesslstore_address1), array('id' => "thesslstore_address1")));
		$fields->setField($thesslstore_address1);
		unset($thesslstore_address1);
		
		$thesslstore_address2 = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_address2", true), "thesslstore_address2");
		$thesslstore_address2->attach($fields->fieldText("thesslstore_address2", $this->Html->ifSet($vars->thesslstore_address2), array('id' => "thesslstore_address2")));
		$fields->setField($thesslstore_address2);
		unset($thesslstore_address2);
		
		$thesslstore_city = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_city", true), "thesslstore_city");
		$thesslstore_city->attach($fields->fieldText("thesslstore_city", $this->Html->ifSet($vars->thesslstore_city), array('id' => "thesslstore_city")));
		$fields->setField($thesslstore_city);
		unset($thesslstore_city);
		
		$thesslstore_zip = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_zip", true), "thesslstore_zip");
		$thesslstore_zip->attach($fields->fieldText("thesslstore_zip", $this->Html->ifSet($vars->thesslstore_zip), array('id' => "thesslstore_zip")));
		$fields->setField($thesslstore_zip);
		unset($thesslstore_zip);
		
		$thesslstore_state = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_state", true), "thesslstore_state");
		$thesslstore_state->attach($fields->fieldText("thesslstore_state", $this->Html->ifSet($vars->thesslstore_state), array('id' => "thesslstore_state")));
		$fields->setField($thesslstore_state);
		unset($thesslstore_state);
		
		$thesslstore_country = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_country", true), "thesslstore_country");
		$thesslstore_country->attach($fields->fieldText("thesslstore_country", $this->Html->ifSet($vars->thesslstore_country), array('id' => "thesslstore_country")));
		$fields->setField($thesslstore_country);
		unset($thesslstore_country);
		
		$thesslstore_email = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_email", true), "thesslstore_email");
		$thesslstore_email->attach($fields->fieldText("thesslstore_email", $this->Html->ifSet($vars->thesslstore_email), array('id' => "thesslstore_email")));
		$fields->setField($thesslstore_email);
		unset($thesslstore_email);

		$thesslstore_number = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_number", true), "thesslstore_number");
		$thesslstore_number->attach($fields->fieldText("thesslstore_number", $this->Html->ifSet($vars->thesslstore_number), array('id' => "thesslstore_number")));
		$fields->setField($thesslstore_number);
		unset($thesslstore_number);
		
		$thesslstore_fax = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_fax", true), "thesslstore_fax");
		$thesslstore_fax->attach($fields->fieldText("thesslstore_fax", $this->Html->ifSet($vars->thesslstore_fax), array('id' => "thesslstore_fax")));
		$fields->setField($thesslstore_fax);
		unset($thesslstore_fax);
		
		$thesslstore_organization = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_organization", true), "thesslstore_organization");
		$thesslstore_organization->attach($fields->fieldText("thesslstore_organization", $this->Html->ifSet($vars->thesslstore_organization), array('id' => "thesslstore_organization")));
		$fields->setField($thesslstore_organization);
		unset($thesslstore_organization);
		
		$thesslstore_organization_unit = $fields->label(Language::_("TheSSLStore.service_field.thesslstore_organization_unit", true), "thesslstore_organization_unit");
		$thesslstore_organization_unit->attach($fields->fieldText("thesslstore_organization_unit", $this->Html->ifSet($vars->thesslstore_organization_unit), array('id' => "thesslstore_organization_unit")));
		$fields->setField($thesslstore_organization_unit);
		unset($thesslstore_organization_unit);
	
		return $fields;
	}
	
	/**
	 * Returns all fields to display to an admin attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getAdminAddFields($package, $vars=null) {
		return $this->makeAddFields($package, $vars);
	}
	
	/**
	 * Returns all fields to display to a client attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getClientAddFields($package, $vars=null) {
		return $this->makeAddFields($package, $vars);
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
	 * Returns all tabs to display to an admin when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 */
	public function getAdminTabs($package) {
		return array(
			'tabReissue' => Language::_("TheSSLStore.tab_reissue", true),
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
			'tabClientReissue' => Language::_("TheSSLStore.tab_reissue", true),
		);
	}
	
	/**
	 * Client Reissue tab
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabClientReissue($package, $service, array $get=null, array $post=null, array $files=null) {
		$this->view = new View("tab_client_reissue", "default");
		return $this->tabReissueInternal($package, $service, $get, $post, $files);
	}

	/**
	 * Admin Reissue tab
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */	
	public function tabReissue($package, $service, array $get=null, array $post=null, array $files=null) {
		$this->view = new View("tab_reissue", "default");
		return $this->tabReissueInternal($package, $service, $get, $post, $files);
	}
	
	/**
	 * Reissue tab generic function
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	private function tabReissueInternal($package, $service, array $get=null, array $post=null, array $files=null) {
		$this->view->base_uri = $this->base_uri;
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		
		// Get the service fields
		$service_fields = $this->serviceFieldsToObject($service->fields);
		$module_row = $this->getModuleRow($package->module_row);
		
		$api = $this->getApi($module_row->meta->api_partner_id, $module_row->meta->api_token, $module_row->meta->sandbox, $module_row);
		
		if (empty($vars)) {
			$vars = array(
				'thesslstore_webserver_type' => $service_fields->thesslstore_webserver_type,
				'thesslstore_approver_email' => $service_fields->thesslstore_approver_email,
				'thesslstore_csr' => $service_fields->thesslstore_csr
			);
		}
		
		$this->view->set("vars", (object)$vars);
		$this->view->set("client_id", $service->client_id);
		$this->view->set("service_id", $service->id);
		
		$this->view->set("thesslstore_webserver_types", $this->getWebserverTypes($api, $package));
		$this->view->set("thesslstore_approver_emails", $this->getApproverEmails($api, $package, $service_fields->thesslstore_fqdn));
		
		if(isset($post["thesslstore_csr"])) {
			Loader::loadModels($this, array("Services"));
			$vars = array(
				'use_module' => true,
				'client_id' => $service->client_id,
				'thesslstore_webserver_type' => $this->Html->ifSet($post['thesslstore_webserver_type']),
				'thesslstore_approver_email' => $this->Html->ifSet($post['thesslstore_approver_email']),
				'thesslstore_csr' => $this->Html->ifSet($post['thesslstore_csr'])
			);
			$res = $this->editService($package, $service, $vars);
			
			if (!$this->Input->errors())
				$this->Services->setFields($service->id, $res);			
		}
		
		$this->view->set("view", $this->view->view);
		$this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore" . DS);
		return $this->view->fetch();
	}
	
	/**
	 * Initializes the API and returns an instance of that object with the given $partner_id, and $token set
	 *
	 * @param string $partner_id The TheSSLStore partner ID
	 * @param string $token The token to the TheSSLStore server
	 * @param string $sandbox Whether sandbox or not
	 * @param stdClass $row A stdClass object representing a single reseller
	 * @return TheSSLStoreApi The TheSSLStoreApi instance
	 */
	private function getApi($partner_id, $token, $sandbox, $row) {
		Loader::load(dirname(__FILE__) . DS . "apis" . DS . "TheSSLStoreApi.php");
		
		$api = new TheSSLStoreApi($partner_id, $token, ($sandbox == "true") ? TheSSLStoreApi::$API_MODE_TEST : TheSSLStoreApi::$API_MODE_LIVE);
		
		$this->log($row->meta->api_partner_id . "|health-validate", '', "input", true);
		$this->parseResponse($api->health_validate(new health_validate_request()), $row);
		
		return $api;
	}
	
	private function getWebserverTypes() {
		//Dumped from https://www.thesslstore.com/api/web-server-types
		//No API for this :(
		return array(
			"aol" => "AOL",
			"apachessl" => "Apache + MOD SSL",
			"apacheraven" => "Apache + Raven",
			"apachessleay" => "Apache + SSLeay",
			"iis" => "Microsoft Internet Information Server",
			"iis4" => "Microsoft IIS 4.0",
			"iis5" => "Microsoft IIS 5.0",
			"c2net" => "C2Net Stronghold",
			"Ibmhttp" => "IBM HTTP",
			"Ibminternet" => "IBM Internet Connection Server",
			"Iplanet" => "iPlanet Server 4.1",
			"Dominogo4625" => "Lotus Domino Go 4.6.2.51",
			"Dominogo4626" => "Lotus Domino Go 4.6.2.6+",
			"Domino" => "Lotus Domino 4.6+",
			"Netscape" => "Netscape Enterprise/FastTrack",
			"NetscapeFastTrack" => "Netscape FastTrack",
			"zeusv3" => "Zeus v3+",
			"Other" => "Other",
			"apacheopenssl" => "Apache + OpenSSL",
			"apache2" => "Apache 2",
			"apacheapachessl" => "Apache + ApacheSSL",
			"cobaltseries" => "Cobalt Series",
			"covalentserver" => "Covalent Server Software",
			"cpanel" => "Cpanel",
			"ensim" => "Ensim",
			"hsphere" => "Hsphere",
			"ipswitch" => "Ipswitch",
			"plesk" => "Plesk",
			"tomcat" => "Jakart-Tomcat",
			"WebLogic" => "WebLogic – all versions",
			"website" => "O’Reilly WebSite Professional",
			"webstar" => "WebStar",
			"sapwebserver" => "SAP Web Application Server",
			"webten" => "WebTen (from Tenon)",
			"redhat" => "RedHat Linux",
			"reven" => "Raven SSL",
			"r3ssl" => "R3 SSL Server",
			"quid" => "Quid Pro Quo",
			"oracle" => "Oracle",
			"javawebserver" => "Java Web Server (Javasoft / Sun)",
			"cisco3000" => "Cisco 3000 Series VPN Concentrator",
			"citrix" => "Citrix"
		);		
	}
	
	/**
	 * Retrieves a list of products
	 *
	 * @param TheSSLStoreApi $api the API to use
	 * @param stdClass $row A stdClass object representing a single reseller (optional, required when Module::getModuleRow() is unavailable)
	 * @return array A list of products
	 */
	private function getProducts($api, $row) {
		$product_query_request = new product_query_request();
		$product_query_request->ProductCode = '';
		$product_query_request->ProductType = 0;
		
		$this->log($row->meta->api_partner_id . "|ssl-products", serialize($product_query_request), "input", true);
		$results = $this->parseResponse($api->product_query($product_query_request), $row);

		$products = array();
		
		if ($results && is_array($results)) {
			foreach($results AS $result) {
				$products[$result->ProductCode] = $result->ProductName;
			}
		}
		
		return $products;
	}

	/**
	 * Retrieves a list of rules for validating adding/editing a module row
	 *
	 * @param array $vars A list of input vars
	 * @return array A list of rules
	 */
	private function getRowRules(array &$vars) {
		return array(
			'api_partner_id' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("TheSSLStore.!error.api_partner_id.empty", true)
				),
				'valid' => array(
					'rule' => array(array($this, "validateConnection"), $vars),
					'message' => Language::_("TheSSLStore.!error.api_partner_id.valid", true)
				)
			),
			'api_token' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("TheSSLStore.!error.api_token.empty", true)
				)
			),
			'thesslstore_name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("TheSSLStore.!error.thesslstore_name.empty", true)
				)
			),
			'sandbox' => array(
			)
		);
	}
	
	/**
	 * Retrieves a list of rules for validating adding/editing a package
	 *
	 * @param array $vars A list of input vars
	 * @return array A list of rules
	 */
	private function getPackageRules(array $vars = null) {
		$rules = array(
			'meta[thesslstore_product]' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("TheSSLStore.!error.meta[thesslstore_product].valid", true)
				)
			)
		);
		
		return $rules;
	}
	
	/**
	 * Validates whether or not the connection details are valid by attempting to fetch
	 * the number of accounts that currently reside on the server
	 *
	 * @param string $api_username The reseller API username
	 * @param array $vars A list of other module row fields including:
	 * 	- api_token The API token
	 * 	- sandbox "true" or "false" as to whether sandbox is enabled
	 * @return boolean True if the connection is valid, false otherwise
	 */
	public function validateConnection($api_partner_id, $vars) {
		try {
			$api_token = (isset($vars['api_token']) ? $vars['api_token'] : "");
			$sandbox = (isset($vars['sandbox']) && $vars['sandbox'] == "true" ? "true" : "false");
			$module_row = (object)array('meta' => (object)$vars);
			
			$this->getApi($api_partner_id, $api_token, $sandbox, $module_row);
			
			if (!$this->Input->errors())
				return true;
			
			// Remove the errors set
			$this->Input->setErrors(array());
		}
		catch (Exception $e) {
			// Trap any errors encountered, could not validate connection
		}
		return false;
	}
	
	/**
	 * Parses the response from TheSslStore into an stdClass object
	 *
	 * @param mixed $response The response from the API
	 * @param stdClass $module_row A stdClass object representing a single reseller (optional, required when Module::getModuleRow() is unavailable)
	 * @param boolean $ignore_error Ignores any response error and returns the response anyway; useful when a response is expected to fail (optional, default false)
	 * @return stdClass A stdClass object representing the response, void if the response was an error
	 */
	private function parseResponse($response, $module_row = null, $ignore_error = false) {
		Loader::loadHelpers($this, array("Html"));
		
		// Set the module row
		if (!$module_row)
			$module_row = $this->getModuleRow();
		
		$success = true;
		
		if(empty($response)) {
			$success = false;
			
			if (!$ignore_error)
				$this->Input->setErrors(array('api' => array('internal' => Language::_("TheSSLStore.!error.api.internal", true))));
		}
		elseif ($response) {
			$auth_response = null;
			if (is_array($response) && isset($response[0]) && $response[0] && is_object($response[0]) && property_exists($response[0], "AuthResponse"))
				$auth_response = $response[0]->AuthResponse;
			elseif (is_object($response) && $response && property_exists($response, "AuthResponse"))
				$auth_response = $response->AuthResponse;
			
			if ($auth_response && property_exists($auth_response, "isError") && $auth_response->isError) {
				$success = false;
				$error_message = (property_exists($auth_response, "Message") && isset($auth_response->Message[0]) ? $auth_response->Message[0] : Language::_("TheSSLStore.!error.api.internal", true));
				
				if (!$ignore_error)
					$this->Input->setErrors(array('api' => array('internal' => $error_message)));
			}
			elseif ($auth_response === null) {
				$success = false;
				
				if (!$ignore_error)
					$this->Input->setErrors(array('api' => array('internal' => $error_message)));
			}
		}
		
		// Log the response
		$this->log($module_row->meta->api_partner_id, serialize($response), "output", $success);
		
		if (!$success && !$ignore_error)
			return;
		
		return $response;
	}
}
?>