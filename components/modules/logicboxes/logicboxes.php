<?php
/**
 * Logicboxes Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.logicboxes
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Logicboxes extends Module {
	
	/**
	 * @var string The version of this module
	 */
	private static $version = "2.2.1";
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
		Language::loadLang("logicboxes", null, dirname(__FILE__) . DS . "language" . DS);
		
		Configure::load("logicboxes", dirname(__FILE__) . DS . "config" . DS);
	}

	/**
	 * Returns the name of this module
	 *
	 * @return string The common name of this module
	 */
	public function getName() {
		return Language::_("Logicboxes.name", true);
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
			if ($field->key == "domain-name")
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
		return Language::_("Logicboxes.module_row", true);
	}
	
	/**
	 * Returns a noun used to refer to a module row in plural form (e.g. "Servers", "VPSs", "Reseller Accounts", etc.)
	 *
	 * @return string The noun used to refer to a module row in plural form
	 */
	public function moduleRowNamePlural() {
		return Language::_("Logicboxes.module_row_plural", true);
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
		return "registrar";
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
	public function getPackageServiceName($packages, array $vars=null) {
		if (isset($vars['domain-name']))
			return $vars['domain-name'];
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
		
		$row = $this->getModuleRow($package->module_row);
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		
		#
		# TODO: Handle validation checks
		#
		
		$tld = null;
		$input_fields = array();
		
		if (isset($vars['domain-name']))
			$tld = $this->getTld($vars['domain-name'], true);
		
		if ($package->meta->type == "domain") {
			$contact_fields = Configure::get("Logicboxes.contact_fields");
			$customer_fields = Configure::get("Logicboxes.customer_fields");
			$domain_field_basics = array('years' => true, 'ns' => true, 'customer-id' => true, 'reg-contact-id' => true, 'admin-contact-id' => true, 'tech-contact-id' => true, 'billing-contact-id' => true, 'invoice-option' => true, 'protect-privacy' => true);
			$transfer_fields = array_merge(Configure::get("Logicboxes.transfer_fields"), $domain_field_basics);
			$domain_fields = array_merge(Configure::get("Logicboxes.domain_fields"), $domain_field_basics);
			$domain_contact_fields = (array)Configure::get("Logicboxes.contact_fields" . $tld);
			
			$input_fields = array_merge($contact_fields, $customer_fields, $transfer_fields, $domain_fields, $domain_field_basics, $domain_contact_fields);
		}
		
		if (isset($vars['use_module']) && $vars['use_module'] == "true") {
			if ($package->meta->type == "domain") {
				$api->loadCommand("logicboxes_domains");
				$domains = new LogicboxesDomains($api);
				
				$contact_type = $this->getContactType($tld);
				$order_id = null;
				$vars['years'] = 1;
				
				foreach ($package->pricing as $pricing) {
					if ($pricing->id == $vars['pricing_id']) {
						$vars['years'] = $pricing->term;
						break;
					}
				}
				
				// Set all whois info from client ($vars['client_id'])
				if (!isset($this->Clients))
					Loader::loadModels($this, array("Clients"));
					
				$client = $this->Clients->get($vars['client_id']);
				$customer_id = $this->getCustomerId($package->module_row, $client->email);
				$contact_id = null;
				
				foreach (array_merge($contact_fields, $customer_fields) as $key => $field) {
					if ($key == "name")
						$vars[$key] = $client->first_name . " " . $client->last_name;
					elseif ($key == "company")
						$vars[$key] = $client->company != "" ? $client->company : "Not Applicable";
					elseif ($key == "email")
						$vars[$key] = $client->email;
					elseif ($key == "address-line-1")
						$vars[$key] = $client->address1;
					elseif ($key == "address-line-2")
						$vars[$key] = $client->address2;
					elseif ($key == "city")
						$vars[$key] = $client->city;
					elseif ($key == "state")
						$vars[$key] = $client->state;
					elseif ($key == "zipcode")
						$vars[$key] = $client->zip;
					elseif ($key == "country")
						$vars[$key] = $client->country;
					elseif ($key == "phone-cc") {
						$part = explode(".", $this->formatPhone(isset($client->numbers[0]) ? $client->numbers[0]->number : null, $client->country));
						if (count($part) == 2) {
							$vars[$key] = ltrim($part[0], "+");
							$vars['phone'] = $part[1] != "" ? $part[1] : "1111111";
						}
					}
					elseif ($key == "username")
						$vars[$key] = $client->email;
					elseif ($key == "passwd")
						$vars[$key] = substr(md5(mt_rand()), 0, 15);
					elseif ($key == "lang-pref")
						$vars[$key] = substr($client->settings['language'], 0, 2);
				}
				
				// Set locality for .ASIA
				if ($tld == ".asia") {
					$vars['attr_locality'] = $client->country;
				}
				elseif ($tld == ".ru") {
					$vars['attr_org-r'] = $vars['company'];
					$vars['attr_address-r'] = $vars['address-line-1'];
					$vars['attr_person-r'] = $vars['name'];
				}
				
				// Create customer if necessary
				if (!$customer_id)
					$customer_id = $this->createCustomer($package->module_row, array_intersect_key($vars, array_merge($contact_fields, $customer_fields)));
				
				$vars['type'] = $contact_type;
				
				$vars['customer-id'] = $customer_id;
				$contact_id = $this->createContact($package->module_row, array_intersect_key($vars, array_merge($contact_fields, $domain_contact_fields)));
				$vars['reg-contact-id'] = $contact_id;
				$contact_id = $this->createContact($package->module_row, array_intersect_key($vars, array_merge($contact_fields, $domain_contact_fields)));
				$vars['admin-contact-id'] = $this->formatContact($contact_id, $tld, "admin");
				$contact_id = $this->createContact($package->module_row, array_intersect_key($vars, array_merge($contact_fields, $domain_contact_fields)));
				$vars['tech-contact-id'] = $this->formatContact($contact_id, $tld, "tech");
				$contact_id = $this->createContact($package->module_row, array_intersect_key($vars, array_merge($contact_fields, $domain_contact_fields)));
				$vars['billing-contact-id'] = $this->formatContact($contact_id, $tld, "billing");
				$vars['invoice-option'] = "NoInvoice";
				$vars['protect-privacy'] = "false";
				
				// Handle special contact assignment case for .ASIA
				if ($tld == ".asia") {
					$vars['attr_cedcontactid'] = $contact_id;
				}
				// Handle special assignment case for .AU
				elseif ($tld == ".au") {
					$vars['attr_eligibilityName'] = $client->company;
					$vars['attr_registrantName'] = $client->first_name . " " . $client->last_name;
				}
				
				$vars = array_merge($vars, $this->createMap($vars));
				
				// Include all attr-name and attr-value fields as acceptable keys for transfer/domains
				foreach ($vars as $key => $value) {
					if (substr($key, 0, 5) == "attr-") {
						$transfer_fields[$key] = "";
						$domain_fields[$key] = "";
					}
				}
				
				// Handle transfer
				if (isset($vars['transfer']) || isset($vars['auth-code'])) {
					$response = $domains->transfer(array_intersect_key($vars, $transfer_fields));
				}
				// Handle registration
				else {
					// Set nameservers
					$vars['ns'] = array();
					for ($i=1; $i<=5; $i++) {
						if (isset($vars["ns" . $i]) && $vars["ns" . $i] != "")
							$vars['ns'][] = $vars["ns" . $i];
					}

					$response = $domains->register(array_intersect_key($vars, $domain_fields));
				}

				if (isset($response->response()->entityid))
					$order_id = $response->response()->entityid;

				$this->processResponse($api, $response);
				
				if ($this->Input->errors())
					return;
				
				return array(
					array('key' => "domain-name", 'value' => $vars['domain-name'], 'encrypted' => 0),
					array('key' => "order-id", 'value' => $order_id, 'encrypted' => 0)
				);
			}
			else {
				
				#
				# TODO: Create SSL cert
				#
			}
		}
		elseif ($status == "active") {
			if ($package->meta->type == "domain") {
				$api->loadCommand("logicboxes_domains");
				$domains = new LogicboxesDomains($api);
				
                $order = array_intersect_key($vars, array('domain-name' => ""));
				$response = $domains->orderid($order);
				$this->processResponse($api, $response);
				
				if ($this->Input->errors())
					return;
				
				$order_id = null;
				if ($response->response())
					$order_id = $response->response();
				
				return array(
					array('key' => "domain-name", 'value' => $vars['domain-name'], 'encrypted' => 0),
					array('key' => "order-id", 'value' => $order_id, 'encrypted' => 0)
				);
			}
		}
        
		$meta = array();
		$fields = array_intersect_key($vars, array_merge(array('ns1' => true,'ns2' => true,'ns3' => true,'ns4' => true,'ns5' => true), $input_fields));

		foreach ($fields as $key => $value) {
			$meta[] = array(
				'key' => $key,
				'value' => $value,
				'encrypted' => 0
			);
		}

		return $meta;
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
        // Get the service fields
		$service_fields = $this->serviceFieldsToObject($service->fields);

        if (empty($service_fields->{'order-id'})) {
            // Fetch the order ID
            $row = $this->getModuleRow($package->module_row);
            $api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");

            $api->loadCommand("logicboxes_domains");
            $domains = new LogicboxesDomains($api);

            $order = array_intersect_key($vars, array('domain-name' => ""));
            $response = $domains->orderid(array('domain-name' => (isset($service_fields->{'domain-name'}) ? $service_fields->{'domain-name'} : "")));
            $this->processResponse($api, $response);

            if ($this->Input->errors())
                return;

            $order_id = null;
            if ($response->response())
                $order_id = $response->response();

            $fields = array(array('key' => "order-id", 'value' => $order_id, 'encrypted' => 0));
            foreach ($service_fields as $key => $value) {
                $fields[] = array(
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                );
            }

            return $fields;
        }

		return null; // All this handled by admin/client tabs instead
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
		return null; // Nothing to do
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
		return null; // Nothing to do
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
		return null; // Nothing to do
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
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		
		// Renew domain
		if ($package->meta->type == "domain") {
			$fields = $this->serviceFieldsToObject($service->fields);
			
			// Load the API
			$api->loadCommand("logicboxes_domains");
			$domains = new LogicboxesDomains($api);
			
			$response = $domains->details(array('order-id' => $fields->{'order-id'}, 'options' => array("OrderDetails")));
			$this->processResponse($api, $response);
			$order = $response->response();
			
			$vars = array(
				'years' => 1,
				'order-id' => $fields->{'order-id'},
				'exp-date' => $order->endtime,
				'invoice-option' => "NoInvoice"
			);
			
			foreach ($package->pricing as $pricing) {
				if ($pricing->id == $service->pricing_id) {
					$vars['years'] = $pricing->term;
					break;
				}
			}
			
			// Only process renewal if adding years today will add time to the expiry date
			if (strtotime("+" . $vars['years'] . " years") > $order->endtime) {
				$response = $domains->renew($vars);
				$this->processResponse($api, $response);
			}
		}
		else {
			#
			# TODO: SSL Cert: Set cancelation date of service?
			#
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
		return null; // Nothing to do
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "logicboxes" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "logicboxes" . DS);
		
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
		$this->view->setDefaultView("components" . DS . "modules" . DS . "logicboxes" . DS);
		
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
		$meta_fields = array("registrar", "reseller_id", "key", "sandbox");
		$encrypted_fields = array("key");
		
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
		
		$types = array(
			'domain' => Language::_("Logicboxes.package_fields.type_domain", true),
			//'ssl' => Language::_("Logicboxes.package_fields.type_ssl", true)
		);
		
		// Set type of package
		$type = $fields->label(Language::_("Logicboxes.package_fields.type", true), "logicboxes_type");
		$type->attach($fields->fieldSelect("meta[type]", $types,
			$this->Html->ifSet($vars->meta['type']), array('id'=>"logicboxes_type")));
		$fields->setField($type);	
		
		// Set all TLD checkboxes
        $tld_options = $fields->label(Language::_("Logicboxes.package_fields.tld_options", true));
		
		$tlds = Configure::get("Logicboxes.tlds");
		sort($tlds);
		foreach ($tlds as $tld) {
			$tld_label = $fields->label($tld, "tld_" . $tld);
			$tld_options->attach($fields->fieldCheckbox("meta[tlds][]", $tld, (isset($vars->meta['tlds']) && in_array($tld, $vars->meta['tlds'])), array('id' => "tld_" . $tld), $tld_label));
		}
		$fields->setField($tld_options);
		
		// Set nameservers
		for ($i=1; $i<=5; $i++) {
			$type = $fields->label(Language::_("Logicboxes.package_fields.ns" . $i, true), "logicboxes_ns" . $i);
			$type->attach($fields->fieldText("meta[ns][]",
				$this->Html->ifSet($vars->meta['ns'][$i-1]), array('id'=>"logicboxes_ns" . $i)));
			$fields->setField($type);
		}		
		
		$fields->setHtml("
			<script type=\"text/javascript\">
				$(document).ready(function() {
					toggleTldOptions($('#logicboxes_type').val());
				
					// Re-fetch module options to toggle fields
					$('#logicboxes_type').change(function() {
						toggleTldOptions($(this).val());
					});
					
					function toggleTldOptions(type) {
						if (type == 'ssl')
							$('.logicboxes_tlds').hide();
						else
							$('.logicboxes_tlds').show();
					}
				});
			</script>
		");
		
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
		return array('service' => array('domain-name'));
	}

	/**
	 * Returns all fields to display to an admin attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getAdminAddFields($package, $vars=null) {
		
		// Handle universal domain name
		if (isset($vars->domain))
			$vars->{'domain-name'} = $vars->domain;
			
		if ($package->meta->type == "domain") {
			// Set default name servers
			if (!isset($vars->ns1) && isset($package->meta->ns)) {
				$i=1;
				foreach ($package->meta->ns as $ns) {
					$vars->{"ns" . $i++} = $ns;
				}
			}
		
			// Handle transfer request
			if (isset($vars->transfer) || isset($vars->{'auth-code'})) {
				return $this->arrayToModuleFields(Configure::get("Logicboxes.transfer_fields"), null, $vars);
			}
			// Handle domain registration
			else {
				
				$module_fields = $this->arrayToModuleFields(array_merge(Configure::get("Logicboxes.domain_fields"), Configure::get("Logicboxes.nameserver_fields")), null, $vars);
				
				if (isset($vars->{'domain-name'})) {
					$tld = $this->getTld($vars->{'domain-name'});
					
					if ($tld) {
						$extension_fields = array_merge((array)Configure::get("Logicboxes.domain_fields" . $tld), (array)Configure::get("Logicboxes.contact_fields" . $tld));
						if ($extension_fields)
							$module_fields = $this->arrayToModuleFields($extension_fields, $module_fields, $vars);
					}
				}
				
				return $module_fields;
			}
		}
		else {
			return new ModuleFields();
		}
	}
	
	/**
	 * Returns all fields to display to a client attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getClientAddFields($package, $vars=null) {
		
		// Handle universal domain name
		if (isset($vars->domain))
			$vars->{'domain-name'} = $vars->domain;
		
		if ($package->meta->type == "domain") {

			// Set default name servers
			if (!isset($vars->ns1) && isset($package->meta->ns)) {
				$i=1;
				foreach ($package->meta->ns as $ns) {
					$vars->{"ns" . $i++} = $ns;
				}
			}

			$tld = (property_exists($vars, "domain-name") ? $this->getTld($vars->{'domain-name'}, true) : null);
			
			// Handle transfer request
			if (isset($vars->transfer) || isset($vars->{'auth-code'})) {
				$fields = Configure::get("Logicboxes.transfer_fields");
				
				// We should already have the domain name don't make editable
				$fields['domain-name']['type'] = "hidden";
				$fields['domain-name']['label'] = null;
				
				$module_fields = $this->arrayToModuleFields($fields, null, $vars);
				
				$extension_fields = Configure::get("Logicboxes.contact_fields" . $tld);
				if ($extension_fields)
					$module_fields = $this->arrayToModuleFields($extension_fields, $module_fields, $vars);
					
				return $module_fields;
			}
			// Handle domain registration
			else {
				$fields = array_merge(Configure::get("Logicboxes.nameserver_fields"), Configure::get("Logicboxes.domain_fields"));
				
				// We should already have the domain name don't make editable
				$fields['domain-name']['type'] = "hidden";
				$fields['domain-name']['label'] = null;
				
				$module_fields = $this->arrayToModuleFields($fields, null, $vars);
				
				if (isset($vars->{'domain-name'})) {
					$extension_fields = array_merge((array)Configure::get("Logicboxes.domain_fields" . $tld), (array)Configure::get("Logicboxes.contact_fields" . $tld));
					if ($extension_fields)
						$module_fields = $this->arrayToModuleFields($extension_fields, $module_fields, $vars);
				}
				
				return $module_fields;
			}
		}
		else {
			return new ModuleFields();
		}
	}
	
	/**
	 * Returns all fields to display to an admin attempting to edit a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getAdminEditFields($package, $vars=null) {
		if ($package->meta->type == "domain") {
			return new ModuleFields();
		}
		else {
			return new ModuleFields();
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
		if ($package->meta->type == "domain") {
			return array(
				'tabWhois' => Language::_("Logicboxes.tab_whois.title", true),
				'tabNameservers' => Language::_("Logicboxes.tab_nameservers.title", true),
				'tabSettings' => Language::_("Logicboxes.tab_settings.title", true)
			);
		}
		else {
			#
			# TODO: Activate & uploads CSR, set field data, etc.
			#
		}
	}

	/**
	 * Returns all tabs to display to a client when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 */
	public function getClientTabs($package) {
		if ($package->meta->type == "domain") {
			return array(
				'tabClientWhois' => Language::_("Logicboxes.tab_whois.title", true),
				'tabClientNameservers' => Language::_("Logicboxes.tab_nameservers.title", true),
				'tabClientSettings' => Language::_("Logicboxes.tab_settings.title", true)
			);
		}
		else {
			#
			# TODO: Activate & uploads CSR, set field data, etc.
			#
		}
	}
	
	/**
	 * Admin Whois tab
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabWhois($package, $service, array $get=null, array $post=null, array $files=null) {
		return $this->manageWhois("tab_whois", $package, $service, $get, $post, $files);
	}
	
	/**
	 * Client Whois tab
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabClientWhois($package, $service, array $get=null, array $post=null, array $files=null) {
		return $this->manageWhois("tab_client_whois", $package, $service, $get, $post, $files);
	}
	
	/**
	 * Admin Nameservers tab
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabNameservers($package, $service, array $get=null, array $post=null, array $files=null) {
		return $this->manageNameservers("tab_nameservers", $package, $service, $get, $post, $files);
	}
	
	/**
	 * Admin Nameservers tab
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabClientNameservers($package, $service, array $get=null, array $post=null, array $files=null) {
		return $this->manageNameservers("tab_client_nameservers", $package, $service, $get, $post, $files);
	}
	
	/**
	 * Admin Settings tab
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabSettings($package, $service, array $get=null, array $post=null, array $files=null) {
		return $this->manageSettings("tab_settings", $package, $service, $get, $post, $files);
	}
	
	/**
	 * Client Settings tab
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabClientSettings($package, $service, array $get=null, array $post=null, array $files=null) {
		return $this->manageSettings("tab_client_settings", $package, $service, $get, $post, $files);
	}
	
	/**
	 * Handle updating whois information
	 *
	 * @param string $view The view to use
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	private function manageWhois($view, $package, $service, array $get=null, array $post=null, array $files=null) {
		$row = $this->getModuleRow($package->module_row);
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		$api->loadCommand("logicboxes_domains");
		$domains = new LogicboxesDomains($api);
		
		$vars = new stdClass();
		
		$contact_fields = Configure::get("Logicboxes.contact_fields");
		$fields = $this->serviceFieldsToObject($service->fields);
		$sections = array('registrantcontact', 'admincontact', 'techcontact', 'billingcontact');
		$show_content = true;
		
		if (!empty($post)) {
			$api->loadCommand("logicboxes_contacts");
			$contacts = new LogicboxesContacts($api);
			
			foreach ($sections as $section) {
				$contact = array();
				foreach ($post as $key => $value) {
					if (strpos($key, $section . "_") !== false && $value != "")
						$contact[str_replace($section . "_", "", $key)] = $value;
				}
				
				$response = $contacts->modify($contact);
				$this->processResponse($api, $response);
				if ($this->Input->errors())
					break;
			}

			$vars = (object)$post;
		}
		elseif (property_exists($fields, "order-id")) {
			$response = $domains->details(array('order-id' => $fields->{'order-id'}, 'options' => array("RegistrantContactDetails", "AdminContactDetails", "TechContactDetails", "BillingContactDetails")));
			
			if ($response->status() == "OK") {
				$data= $response->response();
				
				// Format fields
				foreach ($sections as $section) {
					foreach ($data->$section as $name => $value) {
						if ($name == "address1")
							$name = "address-line-1";
						elseif ($name == "address2")
							$name = "address-line-2";
						elseif ($name == "zip")
							$name = "zipcode";
						elseif ($name == "telnocc")
							$name = "phone-cc";
						elseif ($name == "telno")
							$name = "phone";
						elseif ($name == "emailaddr")
							$name = "email";
						elseif ($name == "contactid")
							$name = "contact-id";
						$vars->{$section . "_" . $name} = $value;
					}
				}
			}
		}
		else {
			// No order-id; info is not available
			$show_content = false;
		}
		
		$contact_fields = array_merge(Configure::get("Logicboxes.contact_fields"), array('contact-id' => array('type' => "hidden")));
		unset($contact_fields['customer-id']);
		unset($contact_fields['type']);
		
		$all_fields = array();
		foreach ($contact_fields as $key => $value) {
			$all_fields['admincontact_' . $key] = $value;
			$all_fields['techcontact_' . $key] = $value;
			$all_fields['registrantcontact_' . $key] = $value;
			$all_fields['billingcontact_' . $key] = $value;
		}

		$module_fields = $this->arrayToModuleFields(Configure::get("Logicboxes.contact_fields"), null, $vars);
		
		$view = ($show_content ? $view : "tab_unavailable");
		$this->view = new View($view, "default");
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		
		$this->view->set("vars", $vars);
		$this->view->set("fields", $this->arrayToModuleFields($all_fields, null, $vars)->getFields());
		$this->view->set("sections", $sections);
		$this->view->setDefaultView("components" . DS . "modules" . DS . "logicboxes" . DS);
		return $this->view->fetch();
	}
	
	/**
	 * Handle updating nameserver information
	 *
	 * @param string $view The view to use
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	private function manageNameservers($view, $package, $service, array $get=null, array $post=null, array $files=null) {
		$vars = new stdClass();
		
		$row = $this->getModuleRow($package->module_row);
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		$api->loadCommand("logicboxes_domains");
		$domains = new LogicboxesDomains($api);
		
		$fields = $this->serviceFieldsToObject($service->fields);
		$show_content = true;
		
		$tld = $this->getTld($fields->{'domain-name'});
		$sld = substr($fields->{'domain-name'}, 0, -strlen($tld));
		
		if (property_exists($fields, "order-id")) {
			if (!empty($post)) {
				$ns = array();
				foreach ($post['ns'] as $i => $nameserver) {
					if ($nameserver != "")
						$ns[] = $nameserver;
				}
				$post['order-id'] = $fields->{'order-id'};
				$response = $domains->modifyNs(array('order-id' => $fields->{'order-id'}, 'ns' => $ns));
				$this->processResponse($api, $response);
				
				$vars = (object)$post;
			}
			else {
				$response = $domains->details(array('order-id' => $fields->{'order-id'}, 'options' => "NsDetails"))->response();
				
				$vars->ns = array();
				for ($i=0; $i<5 ;$i++) {
					if (isset($response->{"ns" . ($i+1)}))
						$vars->ns[] = $response->{"ns" . ($i+1)};
				}
			}
		}
		else {
			// No order-id; info is not available
			$show_content = false;
		}
		
		$view = ($show_content ? $view : "tab_unavailable");
		$this->view = new View($view, "default");
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		
		$this->view->set("vars", $vars);
		$this->view->setDefaultView("components" . DS . "modules" . DS . "logicboxes" . DS);
		return $this->view->fetch();
	}
	
	/**
	 * Handle updating settings
	 *
	 * @param string $view The view to use
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	private function manageSettings($view, $package, $service, array $get=null, array $post=null, array $files=null) {
		$vars = new stdClass();
		
		$row = $this->getModuleRow($package->module_row);
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		$api->loadCommand("logicboxes_domains");
		$domains = new LogicboxesDomains($api);
		
		$fields = $this->serviceFieldsToObject($service->fields);
		$show_content = true;
		
		if (property_exists($fields, "order-id")) {
			if (!empty($post)) {
				
				if (isset($post['registrar_lock'])) {
					if ($post['registrar_lock'] == "true") {
						$response = $domains->enableTheftProtection(array(
							'order-id' => $fields->{'order-id'},
						));
					}
					else {
						$response = $domains->disableTheftProtection(array(
							'order-id' => $fields->{'order-id'},
						));
					}
					$this->processResponse($api, $response);
				}
				
				$vars = (object)$post;
			}
			else {
				
				$response = $domains->details(array('order-id' => $fields->{'order-id'}, 'options' => array("OrderDetails")))->response();
				
				if ($response) {
					$vars->registrar_lock = "false";
					if (in_array("transferlock", $response->orderstatus))
						$vars->registrar_lock = "true";
						
					$vars->epp_code = $response->domsecret;
				}
			}
		}
		else {
			// No order-id; info is not available
			$show_content = false;
		}
		
		$view = ($show_content ? $view : "tab_unavailable");
		$this->view = new View($view, "default");
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		
		$this->view->set("vars", $vars);
		$this->view->setDefaultView("components" . DS . "modules" . DS . "logicboxes" . DS);
		return $this->view->fetch();
	}
	
	/**
	 * Creates a customer
	 *
	 * @param int $module_row_id The module row ID to add the customer under
	 * @param array $vars An array of customer information
	 * @return int The customer-id created, null otherwise
	 * @see LogicboxesCustomers::signup()
	 */
	private function createCustomer($module_row_id, $vars) {
		$row = $this->getModuleRow($module_row_id);
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		$api->loadCommand("logicboxes_customers");
		$customers = new LogicboxesCustomers($api);
		
		$response = $customers->signup($vars);
		
		$this->processResponse($api, $response);
		
		if (!$this->Input->errors() && $response->response() > 0)
			return $response->response();
		return null;
	}
	
	/**
	 * Creates a contact
	 *
	 * @param int $module_row_id The module row ID to add the contact under
	 * @param array $vars An array of contact information
	 * @return int The contact-id created, null otherwise
	 * @see LogicboxesContacts::add()
	 */
	private function createContact($module_row_id, $vars) {
		unset($vars['lang-pref'], $vars['username'], $vars['passwd']);
		
		$row = $this->getModuleRow($module_row_id);
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		$api->loadCommand("logicboxes_contacts");
		$contacts = new LogicboxesContacts($api);
		
		$vars = array_merge($vars, $this->createMap($vars));
		$response = $contacts->add($vars);
		
		$this->processResponse($api, $response);
		
		if (!$this->Input->errors() && $response->response() > 0)
			return $response->response();
		return null;
	}
	
	/**
	 * Fetches the logicboxes customer ID based on username
	 *
	 * @param int $module_row_id The module row ID to search on
	 * @param string $username The customer username (should be an email address)
	 * @return int The logicboxes customer-id if one exists, null otherwise
	 */
	private function getCustomerId($module_row_id, $username) {
		$row = $this->getModuleRow($module_row_id);
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		$api->loadCommand("logicboxes_customers");
		$customers = new LogicboxesCustomers($api);
		
		$vars = array('username' => $username, 'no-of-records' => 10, 'page-no' => 1);
		$response = $customers->search($vars);
		
		$this->processResponse($api, $response);
		
		if (isset($response->response()->{'1'}->{'customer.customerid'}))
			return $response->response()->{'1'}->{'customer.customerid'};
		return null;
	}
	
	/**
	 * Fetches a logicboxes contact ID of a given logicboxes customer ID
	 *
	 * @param int $module_row_id The module row ID to search on
	 * @param string $customer_id The logicboxes customer-id
	 * @param string $type includes one of:
	 * 	- Contact
	 * 	- CoopContact
	 * 	- UkContact
	 * 	- EuContact
	 * 	- Sponsor
	 * 	- CnContact
	 * 	- CoContact
	 * 	- CaContact
	 * 	- DeContact
	 * 	- EsContact
	 * @return int The logicboxes contact-id if one exists, null otherwise
	 */
	private function getContactId($module_row_id, $customer_id, $type="Contact") {
		$row = $this->getModuleRow($module_row_id);
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		$api->loadCommand("logicboxes_contacts");
		$contacts = new LogicboxesContacts($api);
		
		$vars = array('customer-id' => $customer_id, 'no-of-records' => 10, 'page-no' => 1, 'type' => $type);
		$response = $contacts->search($vars);
		
		$this->processResponse($api, $response);
		
		if (isset($response->response()->{'1'}->{'entity.entitiyid'}))
			return $response->response()->{'1'}->{'entity.entitiyid'};
		return null;
	}
	
	/**
	 * Return the contact type required for the given TLD
	 *
	 * @param $tld The TLD to return the contact type for
	 * @return string The contact type
	 */
	private function getContactType($tld) {
		$type = "Contact";
		// Detect contact type from TLD
		if (($tld_part = ltrim(strstr($tld, "."), ".")) &&
			in_array($tld_part, array("ca", "cn", "co", "coop", "de", "es", "eu", "nl", "ru", "uk"))) {
			$type = ucfirst($tld_part) . $type;
		}
		return $type;
	}
	
	/**
	 * Create a so-called 'map' of attr-name and attr-value fields to cope with Logicboxes
	 * ridiculous format requirements.
	 *
	 * @param $attr array An array of key/value pairs
	 * @retrun array An array of key/value pairs where each $attr[$key] becomes "attr-nameN" and "attr-valueN" whose values are $key and $attr[$key], respectively
	 */
	private function createMap($attr) {
		$map = array();
		
		$i=1;
		foreach ($attr as $key => $value) {
			if (substr($key, 0, 5) == "attr_") {
				$map['attr-name' . $i] = str_replace("attr_", "", $key);
				$map['attr-value' . $i] = $value;
				$i++;
			}
		}
		return $map;
	}
	
	/**
	 * Performs a whois lookup on the given domain
	 *
	 * @param string $domain The domain to lookup
	 * @return boolean True if available, false otherwise
	 */
	public function checkAvailability($domain) {

		$row = $this->getModuleRow();
		$api = $this->getApi($row->meta->reseller_id, $row->meta->key, $row->meta->sandbox == "true");
		$api->loadCommand("logicboxes_domains");
		$domains = new LogicboxesDomains($api);
		$tld = $this->getTld($domain);
		$sld = substr_replace($domain, '', -strlen($tld));
		$result = $domains->available(array('domain-name' => $sld, 'tlds' => ltrim($tld, '.')));
		
		if ($result->status() != "OK")
			return false;
		
		$response = $result->response();

		return in_array($response->{$domain}->status, array("unknown", "available"));
	}
	
	/**
	 * Builds and returns the rules required to add/edit a module row
	 *
	 * @param array $vars An array of key/value data pairs
	 * @return array An array of Input rules suitable for Input::setRules()
	 */
	private function getRowRules(&$vars) {
		return array(
			'reseller_id' => array(
				'valid' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Logicboxes.!error.reseller_id.valid", true)
				)
			),
			'key' => array(
				'valid' => array(
					'last' => true,
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Logicboxes.!error.key.valid", true)
				),
				'valid_connection' => array(
					'rule' => array(array($this, "validateConnection"), $vars['reseller_id'], isset($vars['sandbox']) ? $vars['sandbox'] : "false"),
					'message' => Language::_("Logicboxes.!error.key.valid_connection", true)
				)
			)
		);
	}
	
	/**
	 * Validates that the given connection details are correct by attempting to check the availability of a domain
	 *
	 * @param string $key The API key
	 * @param string $reseller_id The API reseller ID
	 * @param string $sandbox "true" if this is a sandbox account, false otherwise
	 * @return boolean True if the connection details are valid, false otherwise
	 */
	public function validateConnection($key, $reseller_id, $sandbox) {
		$api = $this->getApi($reseller_id, $key, $sandbox == "true");
		$api->loadCommand("logicboxes_domains");
		$domains = new LogicboxesDomains($api);
		return $domains->available(array('domain-name' => "logicboxes", 'tlds' => array("com")))->status() == "OK";
	}
	
	/**
	 * Initializes the LogicboxesApi and returns an instance of that object
	 *
	 * @param string $reseller_id The reseller ID to connect as
	 * @param string $key The key to use when connecting
	 * @param boolean $sandbox Whether or not to process in sandbox mode (for testing)
	 * @return LogicboxesApi The LogicboxesApi instance
	 */
	private function getApi($reseller_id, $key, $sandbox) {
		Loader::load(dirname(__FILE__) . DS . "apis" . DS . "logicboxes_api.php");
		
		return new LogicboxesApi($reseller_id, $key, $sandbox);
	}
	
	/**
	 * Process API response, setting an errors, and logging the request
	 *
	 * @param LogicboxesApi $api The logicboxes API object
	 * @param LogicboxesResponse $response The logicboxes API response object
	 */
	private function processResponse(LogicboxesApi $api, LogicboxesResponse $response) {
		$this->logRequest($api, $response);
		
		// Set errors, if any
		if ($response->status() != "OK") {
			if (isset($response->errors()->message))
				$errors = $response->errors()->message;
			elseif (isset($response->errors()->error))
				$errors = $response->errors()->error;
			$this->Input->setErrors(array('errors' => (array)$errors));
		}
	}
	
	/**
	 * Logs the API request
	 *
	 * @param LogicboxesApi $api The logicboxes API object
	 * @param LogicboxesResponse $response The logicboxes API response object
	 */
	private function logRequest(LogicboxesApi $api, LogicboxesResponse $response) {
		$last_request = $api->lastRequest();
		
		$masks = array("api-key");
		foreach ($masks as $mask) {
			if (isset($last_request['args'][$mask]))
				$last_request['args'][$mask] = str_repeat("x", strlen($last_request['args'][$mask]));
		}
		
		$this->log($last_request['url'], serialize($last_request['args']), "input", true);
		$this->log($last_request['url'], $response->raw(), "output", $response->status() == "OK");
	}
	
	/**
	 * Returns the TLD of the given domain
	 *
	 * @param string $domain The domain to return the TLD from
	 * @param boolean $top If true will return only the top TLD, else will return the first matched TLD from the list of TLDs
	 * @return string The TLD of the domain
	 */
	private function getTld($domain, $top = false) {
		$tlds = Configure::get("Logicboxes.tlds");
		
		$domain = strtolower($domain);
		
		if (!$top) {
			foreach ($tlds as $tld) {
				if (substr($domain, -strlen($tld)) == $tld)
					return $tld;
			}
		}
		return strrchr($domain, ".");
	}
	
	/**
	 * Formats a phone number into +NNN.NNNNNNNNNN
	 *
	 * @param string $number The phone number
	 * @param string $country The ISO 3166-1 alpha2 country code
	 * @return string The number in +NNN.NNNNNNNNNN
	 */
	private function formatPhone($number, $country) {
		if (!isset($this->Contacts))
			Loader::loadModels($this, array("Contacts"));
		
		return $this->Contacts->intlNumber($number, $country, ".");
	}
	
	/**
	 * Formats the contact ID for the given TLD and type
	 *
	 * @param int $contact_id The contact ID
	 * @param string $tld The TLD being registered/transferred
	 * @param string $type The contact type
	 * @return int The contact ID to use
	 */
	private function formatContact($contact_id, $tld, $type) {
		$tlds = array();
		switch ($type) {
			case "admin":
			case "tech":
				$tlds = array(".eu", ".nz", ".ru", ".uk");
				break;
			case "billing":
				$tlds = array(".ca", ".eu", ".nl", ".nz", ".ru", ".uk");
				break;
		}
		if (in_array(strtolower($tld), $tlds))
			return -1;
		return $contact_id;
	}
}
?>