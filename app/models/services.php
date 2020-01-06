<?php
/**
 * Service management
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Services extends AppModel {

	/**
	 * Initialize Services
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("services"));
	}

	/**
	 * Returns the number of results available for the given status
	 *
	 * @param int $client_id The ID of the client to select status count values for
	 * @param string $status The status value to select a count of ('active', 'canceled', 'pending', 'suspended')
	 * @param boolean $children True to fetch all services, including child services, or false to fetch only services without a parent (optional, default true)
	 * @return int The number representing the total number of services for this client with that status
	 */
	public function getStatusCount($client_id, $status="active", $children=true) {
		$this->Record->select(array("id"))->from("services")->
			where("client_id", "=", $client_id)->where("status", "=", $status);

		if (!$children)
			$this->Record->where("parent_service_id", "=", null);

		return $this->Record->numResults();
	}

	/**
	 * Returns a list of services for the given client and status
	 *
	 * @param int $client_id The ID of the client to select services for
	 * @param string $status The status to filter by (optional, default "active"), one of:
	 * 	- active All active services
	 * 	- canceled All canceled services
	 * 	- pending All pending services
	 * 	- suspended All suspended services
	 * 	- in_review All services that require manual review before they may become pending
	 * 	- scheduled_cancellation All services scheduled to be canceled
	 * 	- all All active/canceled/pending/suspended/in_review
	 * @param int $page The page to return results for (optional, default 1)
	 * @param array $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @param boolean $children True to fetch all services, including child services, or false to fetch only services without a parent (optional, default true)
	 * @return array An array of stdClass objects representing services
	 */
	public function getList($client_id=null, $status="active", $page=1, $order_by=array('date_added'=>"DESC"), $children=true) {

		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));

		// If sorting by term, sort by both term and period
		if (isset($order_by['term'])) {
			$temp_order_by = $order_by;

			$order_by = array('period'=>$order_by['term'], 'term'=>$order_by['term']);

			// Sort by any other fields given as well
			foreach ($temp_order_by as $sort=>$order) {
				if ($sort == "term")
					continue;

				$order_by[$sort] = $order;
			}
		}

		// Get a list of services
		$this->Record = $this->getServices($client_id, $status, $children);

		$services = $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();

		foreach ($services as &$service) {
			// Service meta fields
			$service->fields = $this->getFields($service->id);
			// Collect package pricing data
			$service->package_pricing = $this->getPackagePricing($service->pricing_id);
			// Collect package data
			$service->package = $this->Record->select()->from("packages")->
				where("packages.id", "=", $service->package_pricing->package_id)->fetch();

			$service->name = $this->ModuleManager->moduleRpc($service->package->module_id, "getServiceName", array($service));
		}

		return $services;
	}

	/**
	 * Returns the total number of services for a client, useful
	 * in constructing pagination for the getList() method.
	 *
	 * @param int $client_id The client ID
	 * @param string $status The status type of the services to fetch (optional, default 'active'), one of:
	 * 	- active All active services
	 * 	- canceled All canceled services
	 * 	- pending All pending services
	 * 	- suspended All suspended services
	 * 	- in_review All services that require manual review before they may become pending
	 * 	- scheduled_cancellation All services scheduled to be canceled
	 * 	- all All active/canceled/pending/suspended/in_review
	 * @param boolean $children True to fetch all services, including child services, or false to fetch only services without a parent (optional, default true)
	 * @return int The total number of services
	 * @see Services::getList()
	 */
	public function getListCount($client_id, $status="active", $children=true) {
		$this->Record = $this->getServices($client_id, $status, $children);

		// Return the number of results
		return $this->Record->numResults();
	}

	/**
	 * Search services
	 *
	 * @param string $query The value to search services for
	 * @param int $page The page number of results to fetch (optional, default 1)
	 * @param boolean $search_fields If true will also search service fields for the value
	 * @return array An array of services that match the search criteria
	 */
	public function search($query, $page=1, $search_fields = false) {
		$this->Record = $this->searchServices($query, $search_fields);

		// Set order by clause
		$order_by = array();
		if (Configure::get("Blesta.id_code_sort_mode")) {
			foreach ((array)Configure::get("Blesta.id_code_sort_mode") as $key) {
				$order_by[$key] = "ASC";
			}
		}
		else
			$order_by = array("date_added"=>"ASC");

		return $this->Record->group(array("temp.id"))->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}

	/**
	 * Return the total number of services returned from Services::search(), useful
	 * in constructing pagination
	 *
	 * @param string $query The value to search services for
	 * @see Transactions::search()
	 */
	public function getSearchCount($query, $search_fields = false) {
		$this->Record = $this->searchServices($query, $search_fields);
		return $this->Record->group(array("temp.id"))->numResults();
	}

	/**
	 * Determines whether a service has a parent services of the given status
	 *
	 * @param int $service_id The ID of the service to check
	 * @return boolean True if the service has a parent, false otherwise
	 */
	public function hasParent($service_id) {
		return (boolean)$this->Record->select()->from("services")->
			where("parent_service_id", "!=", null)->
			where("id", "=", $service_id)->fetch();
	}

	/**
	 * Determines whether a service has any child services of the given status
	 *
	 * @param int $service_id The ID of the service to check
	 * @param string $status The status of any child services to filter on (e.g. "active", "canceled", "pending", "suspended", "in_review", or null for any status) (optional, default null)
	 * @return boolean True if the service has children, false otherwise
	 */
	public function hasChildren($service_id, $status=null) {
		$this->Record->select()->from("services")->
			where("parent_service_id", "=", $service_id);

		if ($status)
			$this->Record->where("status", "=", $status);

		return ($this->Record->numResults() > 0);
	}

	/**
	 * Retrieves a list of all services that are child of the given parent service ID
	 *
	 * @param int $parent_service_id The ID of the parent service whose child services to fetch
	 * @param string $status The status type of the services to fetch (optional, default 'all'):
	 * 	- active All active services
	 * 	- canceled All canceled services
	 * 	- pending All pending services
	 * 	- suspended All suspended services
	 * 	- in_review All services that require manual review before they may become pending
	 * 	- scheduled_cancellation All services scheduled to be canceled
	 * 	- all All active/canceled/pending/suspended/in_review
	 * @return array A list of stdClass objects representing each child service
	 */
	public function getAllChildren($parent_service_id, $status="all") {
		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));

		// Get all child services
		$services = $this->getServices(null, $status)->
			where("services.parent_service_id", "=", $parent_service_id)->
			fetchAll();

		foreach ($services as &$service) {
			// Service meta fields
			$service->fields = $this->getFields($service->id);
			// Collect package pricing data
			$service->package_pricing = $this->getPackagePricing($service->pricing_id);
			// Collect package data
			$service->package = $this->Record->select()->from("packages")->
				where("packages.id", "=", $service->package_pricing->package_id)->fetch();

			$service->name = $this->ModuleManager->moduleRpc($service->package->module_id, "getServiceName", array($service));
		}

		return $services;
	}

	/**
	 * Retrieves the date on which the next invoice is expected to be generated for a service
	 *
	 * @param int $service_id The ID of the service whose next invoice date to fetch
	 * @param string $format The date format to return the date in (optional, default 'Y-m-d H:i:s')
	 * @return mixed The next expected invoice date in UTC, or null if no further invoices are expected to be generated
	 */
	public function getNextInvoiceDate($service_id, $format = "Y-m-d H:i:s") {
		// Fetch the service
		$service = $this->Record->select(array("services.*", 'client_groups.id' => "client_group_id"))->
			from("services")->
			innerJoin("clients", "clients.id", "=", "services.client_id", false)->
				on("client_groups.company_id", "=", Configure::get("Blesta.company_id"))->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
			where("services.id", "=", $service_id)->fetch();

		// No expected renewal
		if (!$service || empty($service->date_renews))
			return null;

		// Get the invoice days before renewal, and whether services can be renewed (invoiced) when suspended
		Loader::loadModels($this, array("ClientGroups"));
		Loader::loadHelpers($this, array("Form"));
		$client_group_settings = $this->ClientGroups->getSettings($service->client_group_id);
		$client_group_settings = $this->Form->collapseObjectArray($client_group_settings, "value", "key");
		$inv_suspended_services = ((isset($client_group_settings['inv_suspended_services']) && $client_group_settings['inv_suspended_services'] == "true") ? true : false);
		$inv_days_before_renewal = abs((int)$client_group_settings['inv_days_before_renewal']);
		unset($client_group_settings);

		// Set the date at which invoices would be created based on the
		// renew date and invoice days before renewal, and encompass the entire day
		$invoice_date = date("Y-m-d 23:59:59", strtotime($service->date_renews . "Z -" . $inv_days_before_renewal . " days"));

		if ($service->status == "active" || ($inv_suspended_services && $service->status == "suspended"))
			return $this->Date->cast($invoice_date, $format);
		return null;
	}

	/**
	 * Retrieves a list of services ready to be renewed for this client group
	 *
	 * @param int $client_group_id The client group ID to fetch renewing services from
	 * @return array A list of stdClass objects representing services set ready to be renewed
	 */
	public function getAllRenewing($client_group_id) {
		Loader::loadModels($this, array("ClientGroups"));
		Loader::loadHelpers($this, array("Form"));

		// Determine whether services can be renewed (invoiced) if suspended
		$client_group_settings = $this->ClientGroups->getSettings($client_group_id);
		$client_group_settings = $this->Form->collapseObjectArray($client_group_settings, "value", "key");
		$inv_suspended_services = ((isset($client_group_settings['inv_suspended_services']) && $client_group_settings['inv_suspended_services'] == "true") ? true : false);
		$inv_days_before_renewal = abs((int)$client_group_settings['inv_days_before_renewal']);
		unset($client_group_settings);

		$fields = array(
			"services.*",
			"pricings.term", "pricings.period", "pricings.price",
			"pricings.setup_fee", "pricings.cancel_fee", "pricings.currency",
			'packages.id' => "package_id", "packages.name"
		);

		$this->Record->select($fields)->
			from("services")->
			innerJoin("package_pricing", "package_pricing.id", "=", "services.pricing_id", false)->
			innerJoin("pricings", "pricings.id", "=", "package_pricing.pricing_id", false)->
			innerJoin("packages", "packages.id", "=", "package_pricing.package_id", false)->
			innerJoin("clients", "services.client_id", "=", "clients.id", false)->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
			open()->
				where("services.status", "=", "active");

		// Also invoice suspended services
		if ($inv_suspended_services)
			$this->Record->orWhere("services.status", "=", "suspended");

		$this->Record->close();

		// Ensure only fetching records for the current company
		// whose renew date is <= (today + invoice days before renewal)
		$invoice_date = date("Y-m-d 23:59:59", strtotime(date("c") . " +" . $inv_days_before_renewal . " days"));
		$this->Record->where("client_groups.company_id", "=", Configure::get("Blesta.company_id"))->
			where("client_groups.id", "=", $client_group_id)->
			where("services.date_renews", "!=", null)->
			where("pricings.period", "!=", "onetime")->
			where("pricings.term", ">", "0")->
			where("services.date_renews", "<=", $this->dateToUtc($invoice_date))->
			open()->
				where("services.date_canceled", "=", null)->
				orWhere("services.date_canceled", ">", "services.date_renews", false)->
			close()->
			order(array('services.client_id' => "ASC"));

		return $this->Record->fetchAll();
	}

	/**
	 * Retrieves a list of renewable paid services
	 *
	 * @param string $date The date after which to fetch paid renewable services
	 * @return array A list of services that have been paid and may be processed
	 */
	public function getAllRenewablePaid($date) {
		// Get all active services
		$this->Record = $this->getServices();
		$this->Record->where("date_last_renewed", "!=", null);

		$sub_query_sql = $this->Record->get();
		$values = $this->Record->values;
		$this->Record->reset();

		// Get all invoices and attached services greater than the given date
		return $this->Record->select(array("temp_services.*"))->
			from("invoices")->
			innerJoin("invoice_lines", "invoice_lines.invoice_id", "=", "invoices.id", false)->
			appendValues($values)->
			innerJoin(array($sub_query_sql => "temp_services"), "temp_services.id", "=", "invoice_lines.service_id", false)->
			where("invoices.date_closed", ">", $this->dateToUtc($date))->
			group(array("temp_services.id"))->
			fetchAll();
	}

	/**
	 * Retrieves a list of paid pending services
	 *
	 * @param int $client_group_id The ID of the client group whose paid pending invoices to fetch
	 * @return array A list of services that have been paid and are still pending
	 */
	public function getAllPaidPending($client_group_id) {
		$current_time = $this->dateToUtc(date("c"));

		// Get pending services that are neither canceled nor suspended
		$this->Record = $this->getServices(null, "pending");
		$this->Record->open()->
				where("services.date_suspended", "=", null)->
				orWhere("services.date_suspended", ">", $current_time)->
			close()->
			open()->
				where("services.date_canceled", "=", null)->
				orWhere("services.date_canceled", ">", $current_time)->
			close();

		$sub_query_sql = $this->Record->get();
		$values = $this->Record->values;
		$this->Record->reset();

		// Get all pending services that have been paid
		$services = $this->Record->select(array("temp_services.*"))->
			appendValues($values)->
			from(array($sub_query_sql => "temp_services"))->
			leftJoin("invoice_lines", "temp_services.id", "=", "invoice_lines.service_id", false)->
			on("invoices.status", "in", array("active", "proforma"))->
			leftJoin("invoices", "invoice_lines.invoice_id", "=", "invoices.id", false)->
			innerJoin("clients", "clients.id", "=", "temp_services.client_id", false)->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
			where("client_groups.id", "=", $client_group_id)->
			open()->
				where("invoices.date_closed", "!=", null)->
				orWhere("invoices.id", "=", null)->
			close()->
			group(array("temp_services.id"))->
			fetchAll();

		// Fetch each services' fields and add them to the list
		foreach ($services as &$service) {
			// Get all fields
			$service->fields = $this->getFields($service->id);
			// Collect service options
			$service->options = $this->getOptions($service->id);
		}

		return $services;
	}

	/**
	 * Retrieves a list of services ready to be suspended
	 *
	 * @param int $client_group_id The ID of the client group
	 * @param string $suspension_date The date before which service would be considered suspended
	 * @return array A list of stdClass objects representing services pending suspension
	 */
	public function getAllPendingSuspension($client_group_id, $suspension_date) {
		$this->Record = $this->getServices(null, "active");

		return $this->Record->
			innerJoin("invoice_lines", "invoice_lines.service_id", "=", "services.id", false)->
			innerJoin("invoices", "invoices.id", "=", "invoice_lines.invoice_id", false)->
			where("invoices.status", "in", array("active", "proforma"))->
			where("invoices.date_closed", "=", null)->
			where("invoices.date_due", "<=", $this->dateToUtc($suspension_date))->
			where("client_groups.id", "=", $client_group_id)->
			group(array("services.id"))->
			fetchAll();
	}

	/**
	 * Retrieves a list of paid suspended services ready to be unsuspended. Will
	 * only return services that were automatically suspended (not manually
	 * suspended by a staff member).
	 *
	 * @param int $client_group_id The ID of the client group
	 * @return array A list of stdClass objects representing services pending unsuspension
	 */
	public function getAllPendingUnsuspension($client_group_id) {

		$log_services = clone $this->Record;
		$log_services_sql = $log_services->select(array('MAX(log_services.id)' => 'id'))->
			from("log_services")->where("log_services.status", "=", "suspended")->
			where("log_services.service_id", "=", "services.id", false)->
			group(array("log_services.service_id"))->get();
		$log_services_values = $log_services->values;
		unset($log_services);

		$invoices = clone $this->Record;
		$invoices_sql = $invoices->select(array("invoice_lines.service_id"))->
			from("invoice_lines")->
			on("invoices.status", "in", array("active", "proforma"))->
			on("invoices.date_closed", "=", null)->
			on("invoices.date_due", "<=", $this->dateToUtc(date("c")))->
			innerJoin("invoices", "invoices.id", "=", "invoice_lines.invoice_id", false)->
			where("invoice_lines.service_id", "=", "services.id", false)->
			group(array("invoice_lines.service_id"))->get();
		$invoices_values = $invoices->values;
		unset($invoices);

		$this->Record = $this->getServices(null, "suspended");

		$sql = $this->Record->innerJoin("log_services", "log_services.service_id", "=", "services.id", false)->
			where("log_services.staff_id", "=", null)->
			where("client_groups.id", "=", $client_group_id)->
			where("log_services.id", "in", array($log_services_sql), false)->
			where("services.id", "notin", array($invoices_sql), false)->
			group(array("log_services.service_id"))->get();

		$values = $this->Record->values;
		$this->Record->reset();

		return $this->Record->query($sql, array_merge($values, $log_services_values, $invoices_values))->fetchAll();
	}

	/**
	 * Retrieves a list of services ready to be canceled
	 *
	 * @return array A list of stdClass objects representing services pending cancelation
	 */
	public function getAllPendingCancelation() {
		// Get services set to be canceled
		$this->Record = $this->getServices(null, "all");
		return $this->Record->where("services.date_canceled", "<=", $this->dateToUtc(date("c")))->
			open()->
				where("services.status", "=", "active")->
				orWhere("services.status", "=", "suspended")->
			close()->
			fetchAll();
	}

	/**
	 * Searches services of the given module that contains the given service
	 * field key/value pair.
	 *
	 * @param int $module_id The ID of the module to search services on
	 * @param string $key They service field key to search
	 * @param string $value The service field value to search
	 * @return array An array of stdClass objects, each containing a service
	 */
	public function searchServiceFields($module_id, $key, $value) {
		$this->Record = $this->getServices(null, "all");
		return $this->Record->innerJoin("module_rows", "module_rows.id", "=", "services.module_row_id", false)->
			on("service_fields.key", "=", $key)->on("service_fields.value", "=", $value)->
			innerJoin("service_fields", "service_fields.service_id", "=", "services.id", false)->
			where("module_rows.module_id", "=", $module_id)->
			group("services.id")->fetchAll();
	}

	/**
	 * Partially constructs the query for searching services
	 *
	 * @param string $query The value to search services for
	 * @param boolean $search_fields If true will also search service fields for the value
	 * @return Record The partially constructed query Record object
	 * @see Services::search(), Services::getSearchCount()
	 */
	private function searchServices($query, $search_fields = false) {
		$this->Record = $this->getServices(null, "all");

		if ($search_fields) {
			$this->Record->select(array('service_fields.value' => "service_field_value"))->
				leftJoin("module_rows", "module_rows.id", "=", "services.module_row_id", false)->
				on("service_fields.encrypted", "=", 0)->
				on("service_fields.value", "like", "%" . $query . "%")->
				leftJoin("service_fields", "service_fields.service_id", "=", "services.id", false);
		}

		$sub_query_sql = $this->Record->get();
		$values = $this->Record->values;
		$this->Record->reset();

		$this->Record = $this->Record->select()->appendValues($values)->from(array($sub_query_sql => "temp"))->
			like("CONVERT(temp.id_code USING utf8)", "%" . $query . "%", true, false)->
			orLike("temp.name", "%" . $query . "%");
		if ($search_fields)
			$this->Record->orLike("temp.service_field_value", "%" . $query . "%");

		return $this->Record;
	}

	/**
	 * Partially constructs the query required by Services::getList() and others
	 *
	 * @param int $client_id The client ID (optional)
	 * @param string $status The status type of the services to fetch (optional, default 'active'):
	 * 	- active All active services
	 * 	- canceled All canceled services
	 * 	- pending All pending services
	 * 	- suspended All suspended services
	 * 	- in_review All services that require manual review before they may become pending
	 * 	- scheduled_cancellation All services scheduled to be canceled
	 * 	- all All active/canceled/pending/suspended/in_review
	 * @param boolean $children True to fetch all services, including child services, or false to fetch only services without a parent (optional, default true)
	 * @return Record The partially constructed query Record object
	 */
	private function getServices($client_id=null, $status="active", $children=true) {
		$fields = array(
			"services.*",
			'REPLACE(services.id_format, ?, services.id_value)' => "id_code",
			'REPLACE(clients.id_format, ?, clients.id_value)' => "client_id_code",
			'pricings.term', 'packages.name',
			'contacts.first_name' => "client_first_name",
			'contacts.last_name' => "client_last_name",
			'contacts.company' => "client_company",
			'contacts.address1' => "client_address1",
			'contacts.email' => "client_email"
		);

		$this->Record->select($fields)->appendValues(array($this->replacement_keys['services']['ID_VALUE_TAG'], $this->replacement_keys['clients']['ID_VALUE_TAG']))->
			from("services")->
			innerJoin("package_pricing", "package_pricing.id", "=", "services.pricing_id", false)->
			innerJoin("pricings", "pricings.id", "=", "package_pricing.pricing_id", false)->
			innerJoin("packages", "packages.id", "=", "package_pricing.package_id", false)->
			innerJoin("clients", "services.client_id", "=", "clients.id", false)->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
			on("contacts.contact_type", "=", "primary")->
			innerJoin("contacts", "contacts.client_id", "=", "clients.id", false);

		// Filter out child services
		if (!$children)
			$this->Record->where("services.parent_service_id", "=", null);

		// Filter on client ID
		if ($client_id != null)
			$this->Record->where("services.client_id", "=", $client_id);

		// Filter on status
		if ($status != "all") {
			$custom_statuses = array("scheduled_cancellation");

			if (!in_array($status, $custom_statuses))
				$this->Record->where("services.status", "=", $status);
			else {
				// Custom status type
				switch ($status) {
					case "scheduled_cancellation":
						$this->Record->where("services.date_canceled", ">", $this->dateToUtc(date("c")));
						break;
					default:
						break;
				}
			}
		}

		// Ensure only fetching records for the current company
		$this->Record->where("client_groups.company_id", "=", Configure::get("Blesta.company_id"));

		return $this->Record;
	}

	/**
	 * Fetches the pricing information for a service
	 *
	 * @param int $service_id The ID of the service whose pricing info te fetch
	 * @param string $currency_code The ISO 4217 currency code to convert pricing to (optional, defaults to service's currency)
	 * @return mixed An stdClass object representing service pricing fields, or false if none exist
	 */
	public function getPricingInfo($service_id, $currency_code = null) {
		Loader::loadModels($this, array("Currencies"));
		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));

		$fields = array(
			"services.*",
			"pricings.term", "pricings.period", 'IFNULL(services.override_price, pricings.price)' => "price",
			"pricings.setup_fee", "pricings.cancel_fee", 'IFNULL(services.override_currency, pricings.currency)' => "currency",
			"packages.name", "packages.module_id", "packages.taxable"
		);
		$service = $this->Record->select($fields)->from("services")->
			innerJoin("package_pricing", "package_pricing.id", "=", "services.pricing_id", false)->
			innerJoin("pricings", "pricings.id", "=", "package_pricing.pricing_id", false)->
			innerJoin("packages", "packages.id", "=", "package_pricing.package_id", false)->
			innerJoin("clients", "services.client_id", "=", "clients.id", false)->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
			where("services.id", "=", $service_id)->
			fetch();

		if ($service) {
			// Fetch the service fields
			$service->fields = $this->getFields($service->id);

			// Get the client setting for tax exemption
			Loader::loadComponents($this, array("SettingsCollection"));
			$tax_exempt = $this->SettingsCollection->fetchClientSetting($service->client_id, null, "tax_exempt");
			$tax_exempt = (isset($tax_exempt['value']) && $tax_exempt['value'] == "true" ? true : false);

			// Get the name of the service
			$service_name = $this->ModuleManager->moduleRpc($service->module_id, "getServiceName", array($service));

			// Set the pricing info to return
			$taxable = (!$tax_exempt && ($service->taxable == "1"));
			$pricing_info = array(
				'package_name' => $service->name,
				'name' => $service_name,
				'price' => $service->price,
				'tax' => $taxable,
				'setup_fee' => $service->setup_fee,
				'cancel_fee' => $service->cancel_fee,
				'currency' => ($currency_code ? strtoupper($currency_code) : $service->currency)
			);

			// Convert amounts if another currency has been given
			if ($currency_code && $currency_code != $service->currency) {
				$pricing_info['price'] = $this->Currencies->convert($service->price, $service->currency, $currency_code, Configure::get("Blesta.company_id"));
				$pricing_info['setup_fee'] = $this->Currencies->convert($service->setup_fee, $service->currency, $currency_code, Configure::get("Blesta.company_id"));
				$pricing_info['cancel_fee'] = $this->Currencies->convert($service->cancel_fee, $service->currency, $currency_code, Configure::get("Blesta.company_id"));
			}
			/* Removed precision limit on pricing (2 -> 4 decimal places)
			else {
				$pricing_info['price'] = $this->Currencies->toDecimal($service->price, $service->currency, Configure::get("Blesta.company_id"));
				$pricing_info['setup_fee'] = $this->Currencies->toDecimal($service->setup_fee, $service->currency, Configure::get("Blesta.company_id"));
				$pricing_info['cancel_fee'] = $this->Currencies->toDecimal($service->cancel_fee, $service->currency, Configure::get("Blesta.company_id"));
			}
			*/

			return (object)$pricing_info;
		}

		return false;
	}

	/**
	 * Fetch a single service, including service field data
	 *
	 * @param int $service_id The ID of the service to fetch
	 * @return mixed A stdClass object representing the service, false if no such service exists
	 */
	public function get($service_id) {

		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));

		$fields = array("services.*", 'REPLACE(services.id_format, ?, services.id_value)' => "id_code");

		$service = $this->Record->select($fields)->appendValues(array($this->replacement_keys['services']['ID_VALUE_TAG']))->
			from("services")->where("id", "=", $service_id)->fetch();

		if ($service) {
			// Collect service fields
			$service->fields = $this->getFields($service->id);
			// Collect package pricing data
			$service->package_pricing = $this->getPackagePricing($service->pricing_id);
			// Collect package data
			$service->package = $this->Record->select()->from("packages")->
				where("packages.id", "=", $service->package_pricing->package_id)->fetch();
			// Collect service options
			$service->options = $this->getOptions($service->id);

			$service->name = $this->ModuleManager->moduleRpc($service->package->module_id, "getServiceName", array($service));
		}

		return $service;
	}

	/**
	 * Get package pricing
	 *
	 * @param int $pricing_id
	 * @return mixed stdClass object representing the package pricing, false otherwise
	 */
	private function getPackagePricing($pricing_id) {
		$fields = array("package_pricing.*", "pricings.term",
			"pricings.period", "pricings.price", "pricings.setup_fee",
			"pricings.cancel_fee", "pricings.currency"
		);
		return $this->Record->select($fields)->
			from("package_pricing")->
			innerJoin("pricings", "pricings.id", "=", "package_pricing.pricing_id", false)->
			where("package_pricing.id", "=", $pricing_id)->fetch();
	}

	/**
	 * Adds a new service to the system
	 *
	 * @param array $vars An array of service info including:
	 * 	- parent_service_id The ID of the service this service is a child of (optional)
	 * 	- package_group_id The ID of the package group this service was added from (optional)
	 * 	- pricing_id The package pricing schedule ID for this service
	 * 	- client_id The ID of the client to add the service under
	 * 	- module_row_id The module row to add the service under (optional, default module will decide)
	 * 	- coupon_id The ID of the coupon used for this service (optional)
	 * 	- qty The quanity consumed by this service (optional, default 1)
	 * 	- override_price The price to set for this service, overriding the package pricing value for the selected term (optional, default null)
	 * 	- override_currency The currency to set for this service, overriding the package pricing value for the selected term (optional, default null)
	 *	- status The status of this service (optional, default 'pending'):
	 * 		- active
	 * 		- canceled
	 * 		- pending
	 * 		- suspended
	 * 		- in_review
	 * 	- date_added The date this service is added (default to today's date UTC)
	 * 	- date_renews The date the service renews (optional, default calculated by package term)
	 * 	- date_last_renewed The date the service last renewed (optional)
	 * 	- date_suspended The date the service was last suspended (optional)
	 * 	- date_canceled The date the service was last canceled (optional)
	 * 	- use_module Whether or not to use the module when creating the service ('true','false', default 'true', forced 'false' if status is 'pending' or 'in_review')
	 * 	- configoptions An array of key/value pairs of package options where the key is the package option ID and the value is the option value (optional)
	 * 	- * Any other service field data to pass to the module
	 * @param array $packages A numerically indexed array of packages ordered along with this service to determine if the given coupon may be applied
	 * @param boolean $notify True to notify the client by email regarding this service creation, false to not send any notification (optional, default false)
	 * @return int The ID of this service, void if error
	 */
	public function add(array $vars, array $packages = null, $notify = false) {
		// Remove config options with 0 quantity
		if (isset($vars['configoptions']) && is_array($vars['configoptions']))
			$vars['configoptions'] = $this->formatConfigOptions($vars['configoptions']);

		// Validate that the service can be added
		$vars = $this->validate($vars, $packages);

		if ($errors = $this->Input->errors())
			return;

		if (!isset($vars['status']))
			$vars['status'] = "pending";
		if (!isset($vars['use_module']))
			$vars['use_module'] = "true";

		// If status is pending or in_review can't allow module to add
		if ($vars['status'] == "pending" || $vars['status'] == "in_review")
			$vars['use_module'] = "false";

		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));
		if (!isset($this->Packages))
			Loader::loadModels($this, array("Packages"));
		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));

		$module_data = $this->getModuleClassByPricingId($vars['pricing_id']);

		if ($module_data) {
			$module = $this->ModuleManager->initModule($module_data->id, Configure::get("Blesta.company_id"));

			if ($module) {

				// Find the package and parent service/package used for this service
				$parent_package = null;
				$parent_service = null;
				$package = $this->Packages->getByPricingId($vars['pricing_id']);

				// Filter config options that may be set
				if (isset($vars['configoptions']) && is_array($vars['configoptions'])) {
					// Fetch the addable service options for the new service
					$service_options = $this->getServiceOptions($vars['pricing_id'], $vars['configoptions']);
					$temp_config_options = array();

					// Filter out config options that may be added from the list given
					foreach ($service_options['add'] as $option_id => $temp_pricing_id) {
						if (array_key_exists($option_id, $vars['configoptions'])) {
							$temp_config_options[$option_id] = $vars['configoptions'][$option_id];
						}
					}

					// Set the filtered config options
					$vars['configoptions'] = $temp_config_options;
					$config_options = $vars['configoptions'];
				}

				if (isset($vars['parent_service_id'])) {
					$parent_service = $this->get($vars['parent_service_id']);

					if ($parent_service)
						$parent_package = $this->Packages->getByPricingId($parent_service->pricing_id);
				}

				// Set the module row to use if not given
				if (!isset($vars['module_row_id'])) {

					// Set module row to that defined for the package if available
					if ($package->module_row)
						$vars['module_row_id'] = $package->module_row;
					// If no module row defined for the package, let the module decide which row to use
					else
						$vars['module_row_id'] = $module->selectModuleRow($package->module_group);
				}
				$module->setModuleRow($module->getModuleRow($vars['module_row_id']));

				// Reformat $vars[configoptions] to support name/value fields defined by the package options
				if (isset($vars['configoptions']) && is_array($vars['configoptions']))
					$vars['configoptions'] = $this->PackageOptions->formatOptions($vars['configoptions']);

				// Add through the module
				$service_info = $module->addService($package, $vars, $parent_package, $parent_service, $vars['status']);

				// Set any errors encountered attempting to add the service
				if (($errors = $module->errors())) {
					$this->Input->setErrors($errors);
					return;
				}

				// Fetch company settings on services
				Loader::loadComponents($this, array("SettingsCollection"));
				$company_settings = $this->SettingsCollection->fetchSettings(null, Configure::get("Blesta.company_id"));

				// Creates subquery to calculate the next service ID value on the fly
				/*
				$values = array($company_settings['services_start'], $company_settings['services_increment'],
					$company_settings['services_start'], $company_settings['services_increment'],
					$company_settings['services_start'], $company_settings['services_pad_size'],
					$company_settings['services_pad_str']);
				*/
				$values = array($company_settings['services_start'], $company_settings['services_increment'],
					$company_settings['services_start']);

				$sub_query = new Record();
				/*
				$sub_query->select(array("LPAD(IFNULL(GREATEST(MAX(t1.id_value),?)+?,?), " .
					"GREATEST(CHAR_LENGTH(IFNULL(MAX(t1.id_value)+?,?)),?),?)"), false)->
				*/
				$sub_query->select(array("IFNULL(GREATEST(MAX(t1.id_value),?)+?,?)"), false)->
					appendValues($values)->
					from(array("services"=>"t1"))->
					innerJoin("clients", "clients.id", "=", "t1.client_id", false)->
					innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
					where("client_groups.company_id", "=", Configure::get("Blesta.company_id"))->
					where("t1.id_format", "=", $company_settings['services_format']);
				// run get on the query so $sub_query->values are built
				$sub_query->get();

				// Copy record so that it is not overwritten during validation
				$record = clone $this->Record;
				$this->Record->reset();

				$vars['id_format'] = $company_settings['services_format'];
				// id_value will be calculated on the fly using a subquery
				$vars['id_value'] = $sub_query;

				// Attempt to set cancellation date if package is single term
				if ($vars['status'] == "active" && isset($package->single_term) && $package->single_term == 1 && !isset($vars['date_canceled'])) {
					if (isset($vars['date_renews']))
						$vars['date_canceled'] = $vars['date_renews'];
				}

				// Add the service
				$fields = array("id_format", "id_value", "parent_service_id", "package_group_id", "pricing_id",
					"client_id", "module_row_id", "coupon_id", "qty", "override_price", "override_currency", "status",
                    "date_added", "date_renews", "date_last_renewed", "date_suspended", "date_canceled");

				// Assign subquery values to this record component
				$this->Record->appendValues($sub_query->values);
				// Ensure the subquery value is set first because its the first value
				$vars = array_merge(array('id_value'=>null), $vars);

				$this->Record->insert("services", $vars, $fields);

				$service_id = $this->Record->lastInsertId();

				// Add all service fields
				if (is_array($service_info))
					$this->setFields($service_id, $service_info);

				// Add all service options
				if (isset($service_options) && isset($config_options)) {
					$this->setServiceOptions($service_id, $service_options, $config_options);
				}

				// Decrement usage of quantity
				$this->decrementQuantity(isset($vars['qty']) ? $vars['qty'] : 1, $vars['pricing_id'], false);

				// Send an email regarding this service creation, only when active
				if ($notify && $vars['status'] == "active")
					$this->sendNotificationEmail($this->get($service_id), $package, $vars['client_id']);

				$event = new EventObject("Services.add", array(
					'service_id' => $service_id,
					'vars' => $vars
				));
				$this->Events->register("Services.add", array("EventsServicesCallback", "add"));
				$this->Events->trigger($event);

				return $service_id;
			}
		}
	}

	/**
	 * Edits a service. Only one module action may be performend at a time. For
	 * example, you can't change the pricing_id and edit the module service
	 * fields in a single request.
	 *
	 * @param int $service_id The ID of the service to edit
	 * @param array $vars An array of service info:
	 * 	- parent_service_id The ID of the service this service is a child of
	 * 	- package_group_id The ID of the package group this service was added from
	 * 	- pricing_id The package pricing schedule ID for this service
	 * 	- client_id The ID of the client this service belongs to
	 * 	- module_row_id The module row to add the service under
	 * 	- coupon_id The ID of the coupon used for this service
	 * 	- qty The quanity consumed by this service
	 * 	- override_price The price to set for this service, overriding the package pricing value for the selected term (optional, default null)
	 * 	- override_currency The currency to set for this service, overriding the package pricing value for the selected term (optional, default null)
	 *	- status The status of this service:
	 * 		- active
	 * 		- canceled
	 * 		- pending
	 * 		- suspended
	 * 		- in_review
	 * 	- date_added The date this service is added
	 * 	- date_renews The date the service renews
	 * 	- date_last_renewed The date the service last renewed
	 * 	- date_suspended The date the service was last suspended
	 * 	- date_canceled The date the service was last canceled
	 * 	- use_module Whether or not to use the module for this request ('true','false', default 'true')
	 * 	- prorate Whether or not to prorate price changes on upgrades by creating an invoice for the difference between the new price and the existing price
	 * 		only when updating the pricing_id to another with an equivalent period (i.e. one-time to one-time or recurring to recurring period)
	 * 		and no price overrides are set (optional; 'true','false', default 'false')
	 * 	- configoptions An array of key/value pairs of package options where the key is the package option ID and the value is the option value.
	 * 		Defining the 'configoptions' key will update all config options. Always include all config options if setting any, or changing the pricing_id. (optional)
	 * 	- * Any other service field data to pass to the module
	 * @param boolean $bypass_module $vars['use_module'] notifies the module of whether
	 * 	or not it should internally use its module connection to process the request, however
	 * 	in some instances it may be necessary to prevent the module from being notified of
	 * 	the request altogether. If true, this will prevent the module from being notified of the request.
	 * @param boolean $notify If true and the service is set to active will send the service activation notification
	 * @return int The ID of this service, void if error
	 */
	public function edit($service_id, array $vars, $bypass_module = false, $notify = false) {
		$service = $this->get($service_id);

		// If the renew date changes, set the last renew date for rule validation whether given or not
		if (isset($vars['date_renews'])) {
			if (isset($vars['date_last_renewed']))
				$vars['date_last_renewed'] = $this->dateToUtc($vars['date_last_renewed'], "c");
			else
				$vars['date_last_renewed'] = $this->dateToUtc(strtotime($service->date_last_renewed . "Z"), "c");
		}

		if (!isset($vars['use_module']))
			$vars['use_module'] = "true";

		if (!isset($vars['pricing_id']))
			$vars['pricing_id'] = $service->pricing_id;

		if (!isset($vars['qty']))
			$vars['qty'] = $service->qty;

		$vars['current_qty'] = $service->qty;

		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));

		$this->Input->setRules($this->getRules($vars, true, $service_id));

		if ($this->Input->validates($vars)) {

			extract($this->getRelations($service_id));

			$package_from = clone $package;
			$pricing_id = $service->pricing_id;

			// If changing pricing ID, load up module with the new pricing ID
			if (isset($vars['pricing_id']) && $vars['pricing_id'] != $pricing_id) {
				$pricing_id = $vars['pricing_id'];

				$package = $this->Packages->getByPricingId($pricing_id);
			}

			// Filter config options that may be set
			if (isset($vars['configoptions'])) {
				// Fetch the addable, updatable, and deletable service options for the updated service
				$service_options = $this->getServiceOptions($pricing_id, (array)$vars['configoptions'], $service_id);
				$temp_config_options = array();

				// Filter out config options that may be added from the list given
				foreach ($service_options['add'] as $option_id => $temp_pricing_id) {
					if (array_key_exists($option_id, $vars['configoptions'])) {
						$temp_config_options[$option_id] = $vars['configoptions'][$option_id];
					}
				}
				// Filter out config options that may be updated from the list given
				foreach ($service_options['edit'] as $option_id => $option_pricing) {
					if (array_key_exists($option_id, $vars['configoptions'])) {
						$temp_config_options[$option_id] = $vars['configoptions'][$option_id];
					}
				}
				// Filter out config options that may be deleted from the list given
				foreach ($service_options['delete'] as $option_id => $option_pricing) {
					if (array_key_exists($option_id, $vars['configoptions'])) {
						$temp_config_options[$option_id] = $vars['configoptions'][$option_id];
					}
				}

				// Set the filtered config options
				$vars['configoptions'] = $temp_config_options;
				$config_options = $vars['configoptions'];
			}

			$module_data = $this->getModuleClassByPricingId($pricing_id);

			if ($module_data && !$bypass_module) {
				$module = $this->ModuleManager->initModule($module_data->id, Configure::get("Blesta.company_id"));

				if ($module) {

					// Set the module row used for this service
					$module_row_id = $service->module_row_id;
					// If changing module row ID, set the correct module row for this service
					if (isset($vars['module_row_id']))
						$module_row_id = $vars['module_row_id'];
					$module->setModuleRow($module->getModuleRow($module_row_id));

					// Reformat $vars[configoptions] to support name/value fields defined by the package options
					if (isset($vars['configoptions']) && is_array($vars['configoptions']))
						$vars['configoptions'] = $this->PackageOptions->formatOptions($vars['configoptions']);
					elseif (!isset($vars['configoptions'])) {
						$vars['configoptions'] = array();
						foreach ($service->options as $option) {
							// The option value is the selected value or the quantity for quantity types
							$value = $option->option_value;
							if ($option->option_type == "quantity") {
								$value = $option->qty;
							}
							
							$vars['configoptions'][$option->option_name] = $value;
						}
						unset($option);
					}

					$service_info = null;

					// Attempt to change the package via module if pricing has changed
					if (isset($vars['pricing_id']) && $service->pricing_id != $vars['pricing_id'] && $vars['use_module'] == "true") {
						$service_info = $module->changeServicePackage($package_from, $package, $service, $parent_package, $parent_service);

						if (($errors = $module->errors())) {
							$this->Input->setErrors($errors);
							return;
						}
						elseif ($service_info && is_array($service_info)) {
							// Update the service fields changed from the package
							$this->setFields($service_id, $service_info);
							// Refetch the service (and thus the new service fields)
							$service = $this->get($service_id);
						}
					}

					// If service is currently pending and status is now "active", call addService on the module
					if ($service->status == "pending" && isset($vars['status']) && $vars['status'] == "active") {
						$vars['pricing_id'] = $service->pricing_id;
						$vars['client_id'] = $service->client_id;
						$service_info = $module->addService($package, $vars, $parent_package, $parent_service, $vars['status']);
					}
					else {
						$service_info = $module->editService($package, $service, $vars, $parent_package, $parent_service);
					}

					if (($errors = $module->errors())) {
						$this->Input->setErrors($errors);
						return;
					}

					// Set all service fields (if any given)
					if (is_array($service_info))
						$this->setFields($service_id, $service_info);

					// Add/update/delete service options
					if (isset($service_options) && isset($config_options))
						$this->setServiceOptions($service_id, $service_options, $config_options);

					// Decrement usage of quantity
					$this->decrementQuantity(isset($vars['qty']) ? $vars['qty'] : 1, $vars['pricing_id'], false, $service->qty);

					// Send an email regarding this service creation, only when active
					if ($notify && isset($vars['status']) && $vars['status'] == "active")
						$this->sendNotificationEmail($this->get($service_id), $package, $service->client_id);
				}
			}

			// Attempt to set cancellation date if package is single term
			if ($service->status == "pending" && isset($vars['status']) && $vars['status'] == "active" &&
				isset($package->single_term) && $package->single_term == 1 && !isset($vars['date_canceled'])) {
				if (isset($vars['date_renews']))
					$vars['date_canceled'] = $vars['date_renews'];
				else
					$vars['date_canceled'] = $service->date_renews;
			}

			$fields = array(
				"parent_service_id", "package_group_id", "pricing_id", "client_id", "module_row_id",
				"coupon_id", "qty", "override_price", "override_currency", "status", "date_added",
                "date_renews", "date_last_renewed", "date_suspended", "date_canceled"
			);

			// Only update if $vars contains something in $fields
			$interset = array_intersect_key($vars, array_flip($fields));
			if (!empty($interset))
				$this->Record->where("services.id", "=", $service_id)->update("services", $vars, $fields);

			$event = new EventObject("Services.edit", array(
				'service_id' => $service_id,
				'vars' => $vars
			));
			$this->Events->register("Services.edit", array("EventsServicesCallback", "edit"));
			$this->Events->trigger($event);

			// Prorate any price differences for this service and any of its config options, which may create an invoice
			if (isset($vars['prorate']) && $vars['prorate'] == "true") {
				$service_options = (isset($service_options) ? $service_options : array());
				$config_options = (isset($config_options) ? $config_options : array());
				$this->prorate($service, $this->get($service_id), $service_options, $config_options);
			}

			return $service_id;
		}
	}

	/**
	 * Updates the service to set all of its service config options
	 *
	 * @param int $service_id The ID of the service to update
	 * @param array $service_options An array of service options from Services::getServiceOptions
	 * @param array $config_options A key/value array of config options selected with the key being the option ID and the value being the selected value
	 */
	private function setServiceOptions($service_id, array $service_options, array $config_options) {
		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));

		// Add the new config options
		foreach ($service_options['add'] as $option_id => $pricing_id) {
			if (array_key_exists($option_id, $config_options) && ($option_value = $this->PackageOptions->getValue($option_id, $config_options[$option_id]))) {
				$vars = array(
					'service_id' => $service_id,
					'option_pricing_id' => $pricing_id,
					'qty' => ($option_value->value == null ? $config_options[$option_id] : 1)
				);

				$this->Record->insert("service_options", $vars);
			}
		}

		// Update the current config options
		foreach ($service_options['edit'] as $option_id => $temp_pricing) {
			$pricing_id = $temp_pricing['new'];
			$old_pricing_id = $temp_pricing['old'];

			if (array_key_exists($option_id, $config_options) && ($option_value = $this->PackageOptions->getValue($option_id, $config_options[$option_id]))) {
				$vars = array(
					'service_id' => $service_id,
					'option_pricing_id' => $pricing_id,
					'qty' => ($option_value->value == null ? $config_options[$option_id] : 1)
				);

				$this->Record->where("service_id", "=", $service_id)->
					where("option_pricing_id", "=", $old_pricing_id)->
					update("service_options", $vars);
			}
		}

		// Remove config options that can no longer be set on the service
		foreach ($service_options['delete'] as $option_id => $pricing_id) {
			$this->Record->from("service_options")->
				where("service_id", "=", $service_id)->
				where("option_pricing_id", "=", $pricing_id)->
				delete();
		}
	}

	/**
	 * Retrieves a list of package option IDs that can be added/updated to the given service
	 *
	 * @param int $pricing_id The ID of the new pricing ID to use for the service
	 * @param array $config_options A key/value array of option IDs and their selected values
	 * @param int $service_id The ID of the current service before it has been updated (optional)
	 * @return array An array containing:
	 * 	- add A key/value array of option IDs and their option pricing ID to be added
	 * 	- edit An array containing:
	 * 		- new A key/value array of option IDs and their new option pricing ID to be upgraded to
	 * 		- old A key/value array of option IDs and their old (current) option pricing ID to upgrade from
	 * 	- delete A key/value array of current option IDs and their option pricing ID to be removed
	 */
	private function getServiceOptions($pricing_id, array $config_options, $service_id = null) {
		// Fetch the selected pricing information
		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));

		// Fetch the current service options and key them by the option ID
		$current_options = array();
		if ($service_id) {
			$current_options = $this->getOptions($service_id);
			$current_option_ids = array();
			foreach ($current_options as $option) {
				$current_option_ids[$option->option_id] = $option;
			}
			$current_options = $current_option_ids;
			unset($current_option_ids);
		}

		// Fetch the available service options and key them by the option ID
		$pricing = null;
		$available_options = $this->getOptionsAvailable($pricing_id, $pricing);
		$available_option_ids = array();
		foreach ($available_options as $option) {
			$available_option_ids[$option->id] = $option;
		}
		$available_options = $available_option_ids;
		unset($available_option_ids);

		// Determine what options are to be added, updated, or removed
		$options = array('add' => array(), 'edit' => array(), 'delete' => array());
		foreach ($config_options as $option_id => $value) {
			if (($option_value = $this->PackageOptions->getValue($option_id, $value))) {
				$price = null;
				if ($pricing) {
					$price = $this->PackageOptions->getValuePrice($option_value->id, $pricing->term, $pricing->period, $pricing->currency);
				}

				// Skip any options that don't have pricing
				if (!$price)
					continue;

				// Option is available to be set
				if (array_key_exists($option_id, $available_options)) {
					// If the option is set to the quantity of 0, it should be removed, so skip it
					if ($value == 0 && ($option = $this->PackageOptions->get($option_id)) && $option->type == "quantity") {
						continue;
					}

					// Determine whether the given option will be added or updated
					if (array_key_exists($option_id, $current_options)) {
						$options['edit'][$option_id] = array('new' => $price->id, 'old' => $current_options[$option_id]->option_pricing_id);
					}
					else {
						$options['add'][$option_id] = $price->id;
					}

					// Unset the option from the current options
					unset($current_options[$option_id]);
				}
			}
		}

		// All remaining current options will be removed
		foreach ($current_options as $option_id => $option) {
			$options['delete'][$option_id] = $option->option_pricing_id;
		}

		return $options;
	}

	/**
	 * Fetches all of the package options available for a given pricing
	 *
	 * @param int $pricing_id The package pricing ID of the package from which to fetch package options
	 * @param mixed $pricing The package pricing object to update to set with the selected pricing
	 * @return array An array of package options
	 */
	private function getOptionsAvailable($pricing_id, &$pricing = null) {
		if (!isset($this->Packages))
			Loader::loadModels($this, array("Packages"));
		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));

		// Fetch the package pricing
		$available_options = array();
		if (($package = $this->Packages->getByPricingId($pricing_id))) {
			$pricing = null;
			foreach ($package->pricing as $package_pricing) {
				if ($package_pricing->id == $pricing_id) {
					$pricing = $package_pricing;
					break;
				}
			}

			// Set the available package options
			if ($pricing) {
				$available_options = $this->PackageOptions->getAllByPackageId($pricing->package_id, $pricing->term, $pricing->period, $pricing->currency);
			}
		}

		return $available_options;
	}

	/**
	 * Prorates the price difference between an updated service and its config options
	 *
	 * @param stdClass $old_service The service before an upgrade
	 * @param stdClass $new_service The service after it has been upgraded
	 * @param array $service_options An array of service options from Services::getServiceOptions
	 * @param array $config_options A key/value array of option IDs and their selected values
	 * @return mixed The ID of the invoice created if prorating, otherwise void
	 */
	private function prorate($old_service, $new_service, array $service_options, array $config_options) {
		Loader::loadModels($this, array("Invoices", "Packages"));

		// Determine the client's tax exempt status
		Loader::loadComponents($this, array("SettingsCollection"));
		$settings = $this->SettingsCollection->fetchClientSettings($new_service->client_id);
		$tax_exempt = (isset($settings['tax_exempt']) && $settings['tax_exempt'] == "true");

		// Determine whether tax applies
		$taxable = (!$tax_exempt && ($new_service->package->taxable == "1"));
		$setup_fee_taxable = (isset($settings['setup_fee_tax']) && $settings['setup_fee_tax'] == "true");

		// Determine whether to prorate credits
		$prorate_credits = (isset($settings['client_prorate_credits']) && $settings['client_prorate_credits'] == "true");

		// Set options
		$now = date("c");
		$options = array('current_date' => $now, 'taxable' => $taxable);

		$subtotal = 0;
		$credits = 0;
		$setup_fees = 0;
		$coupon = false;
		$coupon_subtotal = 0;
		$coupon_discount = 0;
		$line_items = array();

		// Can only prorate if no override pricing has been set
		if ($new_service->override_price === null && $new_service->override_currency === null) {

			// Determine whether the renew day changed
			$old_renew_day = ($old_service->date_renews ? $this->getLocalRenewDay($old_service->date_renews . "Z") : null);
			$new_renew_day = ($new_service->date_renews ? $this->getLocalRenewDay($new_service->date_renews . "Z") : null);
			$renew_date_changed = ($old_renew_day && $new_renew_day && $old_renew_day != $new_renew_day);
			$renew_date = ($new_service->date_renews !== null ? $new_service->date_renews . "Z" : null);

			// Fetch the prorated service amount.
			// Prorate the renew date if it changed, otherwise prorate the package upgrade
			$option_prorate_amounts = array();
			if ($renew_date_changed) {
				$package_prorate_amount = $this->getRenewDateProrateAmount($old_service, $new_service);
			}
			else {
				$package_prorate_amount = $this->getPackageProrateAmount($old_service, $new_service, $now);
				$option_prorate_amounts = $this->getOptionProrateAmounts($old_service, $service_options, $config_options, $now, $renew_date);

				// Fetch the coupon set on the old service that also applies to the new service
				// The coupon must be set to recur to apply to the new service
				$coupon = $this->getCouponApplies($old_service, $new_service);
				if ($coupon && $coupon->recurring != "1")
					$coupon = false;
			}

			// Set the invoice description dates
			$Date = clone $this->Date;
			$Date->setTimezone("UTC", Configure::get("Blesta.company_timezone"));
			
			// The start date is the current date unless the renew date changed into the future
			// beyond the old renew date, in which case the start date is the previous renew date
			$start_from_date = ($renew_date_changed && $package_prorate_amount > 0 ? $old_service->date_renews . "Z" : $now);
			$start_date = $Date->cast($start_from_date);
			$renew_date = ($renew_date ? $Date->cast($renew_date) : null);
			$recurring = ($new_service->package_pricing->period != "onetime");

			// Create a line item for the service upgrade
			if ($package_prorate_amount > 0) {
				// Update the subtotal
				$subtotal += $package_prorate_amount;
				// Set the total amount that the coupon may apply to
				$coupon_subtotal += $package_prorate_amount;

				// Set the description
				$description = Language::_("Invoices.!line_item.service_prorated_upgrade_description_onetime", true, $old_service->package->name, $new_service->package->name, $new_service->name);
				if ($recurring && $renew_date) {
					$description = Language::_("Invoices.!line_item.service_prorated_upgrade_description", true, $old_service->package->name, $new_service->package->name, $new_service->name, $start_date, $renew_date);
				}
				$line_items[] = $this->makeLineItem($new_service->id, $description, $new_service->qty, $package_prorate_amount, $taxable);
			}
			else {
				// Add the negative prorated package amount as a credit
				$credits += abs($package_prorate_amount);
			}

			// Create line items for each upgraded service option
			foreach ($option_prorate_amounts as $option_id => $option_amount) {

				if ($option_amount['amount'] > 0 && $option_amount['qty'] > 0) {
					// Update the subtotal
					$subtotal += ($option_amount['amount'] * $option_amount['qty']);
					// Set the total amount that the coupon may apply to for each package option
					if ($coupon && $coupon->apply_package_options == "1") {
						$coupon_subtotal += ($option_amount['amount'] * $option_amount['qty']);
					}

					// Fetch the package option
					$option = $this->PackageOptions->get($option_id);

					// Description for upgrades between values
					$description = "";
					if ($option_amount['value'] && $option_amount['old_value']) {
						// Set the description
						$description = Language::_("Invoices.!line_item.service_option_prorated_upgrade_description_onetime", true, $option->label, $option_amount['old_value'], $option_amount['value']);
						if ($recurring && $renew_date) {
							$description = Language::_("Invoices.!line_item.service_option_prorated_upgrade_description", true, $option->label, $option_amount['old_value'], $option_amount['value'], $start_date, $renew_date);
						}

						if ($option->type == "quantity" && isset($option_amount['old_qty'])) {
							// Set the description
							$description = Language::_("Invoices.!line_item.service_option_prorated_upgrade_qty_description_onetime", true, $option->label, $option_amount['old_qty'], $option_amount['old_value'], $option_amount['qty'], $option_amount['value']);
							if ($recurring && $renew_date) {
								$description = Language::_("Invoices.!line_item.service_option_prorated_upgrade_qty_description", true, $option->label, $option_amount['old_qty'], $option_amount['old_value'], $option_amount['qty'], $option_amount['value'], $start_date, $renew_date);
							}
						}
					}
					// Description for a new option
					elseif ($option_amount['value']) {
						// Set the description
						$description = Language::_("Invoices.!line_item.service_option_prorated_addition_description_onetime", true, $option->label, $option_amount['value']);
						if ($recurring && $renew_date) {
							$description = Language::_("Invoices.!line_item.service_option_prorated_addition_description", true, $option->label, $option_amount['value'], $start_date, $renew_date);
						}

						if ($option->type == "quantity") {
							// Set the description
							$description = Language::_("Invoices.!line_item.service_option_prorated_addition_qty_description_onetime", true, $option->label, $option_amount['qty'], $option_amount['value']);
							if ($recurring && $renew_date) {
								$description = Language::_("Invoices.!line_item.service_option_prorated_addition_qty_description", true, $option->label, $option_amount['qty'], $option_amount['value'], $start_date, $renew_date);
							}
						}
					}

					// Add option line item
					$line_items[] = $this->makeLineItem($new_service->id, $description, $option_amount['qty'], $option_amount['amount'], $taxable);

					// Create line item for setup fee
					if ($option_amount['setup_fee'] > 0) {
						$description = Language::_("Invoices.!line_item.service_option_setup_fee_description", true, $option->label, $option_amount['value']);
						$line_items[] = $this->makeLineItem($new_service->id, $description, 1, $option_amount['setup_fee'], ($taxable && $setup_fee_taxable));
						$setup_fees += $option_amount['setup_fee'];
					}
				}
				elseif ($option_amount['amount'] < 0 && $option_amount['qty'] > 0) {
					// Add the negative prorated option amount as a credit
					$credits += abs($option_amount['amount'] * $option_amount['qty']);
				}
			}

			// Determine the coupon discount
			$coupon_discount = $this->getCouponDiscount($coupon, $coupon_subtotal, $new_service->package_pricing->currency, $now);

			// Adjust the credit amount based on the coupon. This avoids discounting too much
			$credits -= $this->getCouponDiscount($coupon, $credits, $new_service->package_pricing->currency, $now);
		}

		// Increment the coupon's usage iff the coupon limit is set to apply to renewing services
		if ($coupon && $coupon->limit_recurring == "1" && $coupon_discount > 0) {
			$this->Coupons->incrementUsage($coupon->id);
		}

		// Remove any credits that would be set if we are not allowing prorated credits
		$credits = max(0, ($prorate_credits ? round($credits, 4) : 0));

		// Create the invoice if there is a positive total
		$total = (max(0, $subtotal - $coupon_discount) - $credits) + $setup_fees;

		if ($total > 0) {
			// Create a line item for the credits
			if ($credits > 0) {
				$line_items[] = $this->makeLineItem(null, Language::_("Invoices.!line_item.prorated_credit", true), 1, -$credits, false);
			}

			// Create a line item for the coupon discount
			if ($coupon && $coupon_discount > 0) {
				$coupon_amount = $this->getCouponAmount($coupon, $new_service->package_pricing->currency);

				// Set the line item description
				$description = Language::_("Invoices.!line_item.coupon_line_item_description_amount", true, $coupon->code);
				if ($coupon_amount && $coupon_amount->type == "percent") {
					$description = Language::_("Invoices.!line_item.coupon_line_item_description_percent", true, $coupon->code, abs($coupon_amount->amount));
				}

				$line_items[] = $this->makeLineItem(null, $description, 1, -min($subtotal, $coupon_discount), false);
			}

			// Create the invoice
			return $this->createInvoice($line_items, $new_service->client_id, $new_service->package_pricing->currency, $now, $now);
		}
		elseif ($total < 0) {
			// Apply the credit to the client account
			$this->createCredit($new_service->client_id, abs($total), $new_service->package_pricing->currency);
		}
	}

	/**
	 * Retrieves the coupon amount for the given coupon that matches the given currency
	 * @see Services::prorate
	 *
	 * @param stdClass $coupon An stdClass object representing the coupon
	 * @param string $currency The ISO 4217 currency code
	 * @return mixed An stdClass object representing the coupon amount matching the currency, or false if none exist
	 */
	private function getCouponAmount($coupon, $currency) {
		$coupon_amount = false;

		// Find the coupon amount that matches the currency given
		if ($coupon) {
			foreach ($coupon->amounts as $amount) {
				if ($amount->currency == $currency) {
					$coupon_amount = $amount;
					break;
				}
			}
		}

		return $coupon_amount;
	}

	/**
	 * Retrieves the total coupon discount calculated from the given subtotal
	 * @see Services::prorate
	 *
	 * @param stdClass $coupon An stdClass object representing the coupon
	 * @param float $subtotal The total amount to discount using the given coupon
	 * @param string $currency The ISO 4217 currency code representing the subtotal's currency
	 * @param string $date The date from which to evaluate the coupon is valid
	 * @return float The total discount amount that can be applied from the given coupon
	 */
	private function getCouponDiscount($coupon, $subtotal, $currency, $date) {
		// The coupon must be active, and we must have an amount to apply it to
		$total = 0;
		if (!$coupon || $coupon->status != "active" || $subtotal <= 0) {
			return $total;
		}

		// See if this coupon has a discount available in the given currency
		$coupon_amount = $this->getCouponAmount($coupon, $currency);

		// Determine the total amount to discount
		if ($coupon_amount) {
			// The coupon applies if there is no recurring limit, or the limit has not yet been reached
			$coupon_applies = false;
			// Max quantity may be 0 for unlimited uses, otherwise it must be larger than the used quantity to apply
			$coupon_qty_reached = ($coupon->max_qty == "0" ? false : $coupon->used_qty >= $coupon->max_qty);
			if ($coupon->limit_recurring == "0" ||
			   ($coupon->limit_recurring == "1" && !$coupon_qty_reached &&
				strtotime($this->dateToUtc($date)) >= strtotime($coupon->start_date) &&
				strtotime($this->dateToUtc($date)) <= strtotime($coupon->end_date))) {
				$coupon_applies = true;
			}

			// Assume the coupon already satisfies its exclusive/inclusive requirements, and only calculate the discount
			if ($coupon_applies) {
				$discount_amount = abs($coupon_amount->amount);

				// Determine the % discount
				if ($coupon_amount->type == "percent") {
					$total = abs($subtotal * $discount_amount / 100);
				}
				else {
					// Determine the amount discount
					$total = ($discount_amount >= $subtotal ? $subtotal : $discount_amount);
				}
			}
		}

		return $total;
	}

	/**
	 * Retrieves a coupon that applies to both of the given services
	 * @see Services::prorate
	 *
	 * @param stdClass $old_service The service before an upgrade
	 * @param stdClass $new_service The service after it has been upgraded
	 * @return mixed An stdClass object representing the coupon, or false if none exist
	 */
	private function getCouponApplies($old_service, $new_service) {
		Loader::loadModels($this, array("Coupons"));

		// Fetch the coupon in use that can be also used for the new service
		$coupon = false;
		if ($old_service->coupon_id) {
			$packages = array($new_service->package->id);
			$coupon = $this->Coupons->getForPackages(null, $old_service->coupon_id, $packages);
		}

		return $coupon;
	}

	/**
	 * Creates an in house transaction credit for a client
	 * @see Services::prorate
	 *
	 * $param int $client_id The ID of the client to create the credit for
	 * @param float $total The credit total
	 * @param string $currency The ISO 4217 currency code
	 * @return int The ID of the transaction that was created for this credit
	 */
	private function createCredit($client_id, $total, $currency) {
		Loader::loadModels($this, array("Transactions"));

		// Apply the credit to the client account
		$vars = array(
			'client_id' => $client_id,
			'amount' => $total,
			'currency' => $currency,
			'type' => "other"
		);

		// Find and set the transaction type to In House Credit, if available
		$transaction_types = $this->Transactions->getTypes();
		foreach ($transaction_types as $type) {
			if ($type->name == "in_house_credit") {
				$vars['transaction_type_id'] = $type->id;
				break;
			}
		}

		return $this->Transactions->add($vars);
	}

	/**
	 * Creates an invoice for an upgraded service/options
	 * @see Services::prorate
	 *
	 * @param array $line_items An array of line items
	 * @param int $client_id The ID of the client to create the invoice for
	 * @param string $currency The ISO 4217 currency code
	 * @param string $date The invoice date billed
	 * @param string $date_due The invoice date due
	 * @return mixed The ID of the invoice created, or void otherwise
	 */
	private function createInvoice(array $line_items, $client_id, $currency, $date_billed, $date_due) {
		if (!isset($this->Clients))
			Loader::loadModels($this, array("Clients"));
		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));

		if (!empty($line_items)) {
			// Fetch the client's invoice method to deliver the invoice to
			$delivery = array();
			if (($client = $this->Clients->get($client_id)) && isset($client->settings['inv_method'])) {
				$delivery[] = $client->settings['inv_method'];
			}
			
			$vars = array(
				'client_id' => $client_id,
				'date_billed' => $date_billed,
				'date_due' => $date_due,
				'status' => "active",
				'currency' => $currency,
				'lines' => $line_items,
				'delivery' => $delivery
			);

			return $this->Invoices->add($vars);
		}
	}

	/**
	 * Creates a line item from the given fields
	 * @see Services::prorate
	 *
	 * @param int $service_id The ID of the service assigned to the line item
	 * @param string $description The line item description
	 * @param int $quantity The quantity of the line item
	 * @param float $amount The unit price of the line item
	 * @param boolean $taxable True if the line item is taxable, or false otherwise
	 * @return array An array of line item fields
	 */
	private function makeLineItem($service_id, $description, $quantity, $amount, $taxable) {
		return array(
			'service_id' => $service_id,
			'description' => $description,
			'qty' => $quantity,
			'amount' => $amount,
			'tax' => $taxable
		);
	}

	/**
	 * Retrieves the prorated package amount for the service from a renew date change
	 * @see Services::prorate
	 *
	 * @param stdClass $old_service The service before an upgrade
	 * @param stdClass $new_service The service after it has been upgraded
	 * @return float The prorated decimal amount
	 */
	private function getRenewDateProrateAmount($old_service, $new_service) {
		$prorate_amount = 0.0;

		// Determine whether the renew day changed
		$old_renew_day = ($old_service->date_renews ? $this->getLocalRenewDay($old_service->date_renews . "Z") : null);
		$new_renew_day = ($new_service->date_renews ? $this->getLocalRenewDay($new_service->date_renews . "Z") : null);
		$renew_date_changed = ($old_renew_day && $new_renew_day && $old_renew_day != $new_renew_day);

		// If the renew date has changed, also consider the prorated cost of the date change
		if ($renew_date_changed) {
			// Prorate the price from the old renew date to the new renew date
			$renew_date_amount = $this->Packages->getProratePrice($new_service->package_pricing->price, $old_service->date_renews . "Z", $new_service->package_pricing->term, $new_service->package_pricing->period, null, true, $new_service->date_renews . "Z");

			// The prorate amount is positive or negative depending on the direction the renew date has changed to
			$date_increased = (strtotime($new_service->date_renews) > strtotime($old_service->date_renews));
			$prorate_amount = ($date_increased ? $renew_date_amount : -$renew_date_amount);
		}

		return round($prorate_amount, 4);
	}

	/**
	 * Retrieves the prorated package amount for service prices from upgrades/downgrades
	 * @see Services::prorate
	 *
	 * @param stdClass $old_service The service before an upgrade
	 * @param stdClass $new_service The service after it has been upgraded
	 * @param string $date The current date
	 * @return float The prorated decimal amount
	 */
	private function getPackageProrateAmount($old_service, $new_service, $date) {
		// To prorate a service upgrade: the service may not have override prices; pricing IDs must differ;
		// package IDs must differ; pricing periods must be upgradable (i.e. onetime->onetime or recurring->recurring);
		// the service must have either had a renew date in the future, or no renew date at all (i.e. one-time, or recurs in future)
		$period_upgradable = ($new_service->package_pricing->period == $old_service->package_pricing->period || ($new_service->package_pricing->period != "onetime" && $old_service->package_pricing->period != "onetime"));
		$future_renew_date = ($new_service->date_renews === null || strtotime($new_service->date_renews) > strtotime($this->dateToUtc($date)));
		if ($new_service->pricing_id == $old_service->pricing_id || $new_service->package->id == $old_service->package->id ||
			!$period_upgradable || !$future_renew_date) {
			return 0.0;
		}

		// The one-time prorate amount is simply the price difference
		$prorate_amount = ($new_service->package_pricing->price - $old_service->package_pricing->price);

		// Recurring services should calculate their prorate amount from the current date until the end of the term
		if ($old_service->date_renews !== null && $old_service->package_pricing->period != "onetime" &&
			$new_service->date_renews !== null && $new_service->package_pricing->period != "onetime") {
			$prorate_date = $new_service->date_renews . "Z";

			// The total prorate amount is the difference between the prorated amount being added and the amount being removed
			$service_prorate_amount = $this->Packages->getProratePrice($old_service->package_pricing->price, $date, $old_service->package_pricing->term, $old_service->package_pricing->period, null, true, $prorate_date);
			$new_service_prorate_amount = $this->Packages->getProratePrice($new_service->package_pricing->price, $date, $new_service->package_pricing->term, $new_service->package_pricing->period, null, true, $prorate_date);
			$prorate_amount = ($new_service_prorate_amount - $service_prorate_amount);
		}

		return round($prorate_amount, 4);
	}

	/**
	 * Retrieves the line items for prorated service config option upgrades
	 * @see Services::prorate
	 *
	 * @param stdClass An stdClass object representing the old service
	 * @param array $service_options An array of service options from Services::getServiceOptions
	 * @param array $config_options A key/value array of option IDs and their selected values
	 * @param string $date The current date
	 * @param string $prorate_date The date to prorate to
	 * @return array A key/value array of option IDs containing an array of prorated option value info
	 */
	private function getOptionProrateAmounts($old_service, array $service_options, array $config_options, $date, $prorate_date) {
		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));

		$options = array();
		$old_options = array();

		// Cannot prorate if the prorate date is in the past
		if ($prorate_date !== null && strtotime($prorate_date) <= strtotime($this->dateToUtc($date))) {
			return $options;
		}

		// Key the old service options by ID
		foreach ($old_service->options as $option) {
			$old_options[$option->option_id] = $option;
		}

		// Determine the pricing for new options being added
		$service_options['add'] = (isset($service_options['add']) ? $service_options['add'] : array());
		foreach ($service_options['add'] as $option_id => $option_pricing_id) {
			// Verify the value and pricing exist, as expected
			if (array_key_exists($option_id, $config_options) && ($option_value = $this->PackageOptions->getValue($option_id, $config_options[$option_id])) &&
				($pricing = $this->PackageOptions->getValuePricingById($option_pricing_id))) {

				// Determine the quantity of the option
				$quantity = ($option_value->value === null ? $config_options[$option_id] : 1);

				if ($quantity > 0) {
					// The prorate amount is the full price for one-time services, or prorated to the renew date otherwise
					$amount = $pricing->price;
					if ($pricing->period != "onetime" && $prorate_date) {
						$amount = $this->Packages->getProratePrice($pricing->price, $date, $pricing->term, $pricing->period, null, true, $prorate_date);
					}

					// Fetch the prorate amount
					$options[$option_id] = array(
						'amount' => $amount,
						'qty' => $quantity,
						'setup_fee' => $pricing->setup_fee,
						'value' => $option_value->name,
						'old_value' => null
					);
				}
			}
		}

		// Determine the pricing for existing options being updated
		$service_options['edit'] = (isset($service_options['edit']) ? $service_options['edit'] : array());
		foreach ($service_options['edit'] as $option_id => $temp_pricing) {
			$option_pricing_id = $temp_pricing['new'];
			$old_option_pricing_id = $temp_pricing['old'];

			// Verify the value and pricing exist, as expected
			if (array_key_exists($option_id, $config_options) && array_key_exists($option_id, $old_options) &&
				($option_value = $this->PackageOptions->getValue($option_id, $config_options[$option_id])) &&
				($pricing = $this->PackageOptions->getValuePricingById($option_pricing_id))) {

				// Determine the quantity of the option
				$quantity = ($option_value->value === null ? $config_options[$option_id] : 1);
				$old_quantity = $old_options[$option_id]->qty;

				// Determine the new prorate amount. One-time services are the full amount
				$new_amount = ($quantity * $pricing->price);
				if ($quantity > 0 && $pricing->period != "onetime" && $prorate_date) {
					$new_amount = $this->Packages->getProratePrice($new_amount, $date, $pricing->term, $pricing->period, null, true, $prorate_date);
				}

				// Determine the old prorate amount. One-time services are the full amount
				$old_amount = ($old_quantity * $old_options[$option_id]->option_pricing_price);
				if ($old_quantity > 0 && $old_options[$option_id]->option_pricing_period != "onetime" && $prorate_date) {
					$old_amount = $this->Packages->getProratePrice($old_amount, $date, $old_options[$option_id]->option_pricing_term, $old_options[$option_id]->option_pricing_period, null, true, $prorate_date);
				}

				// Set the option prorate info
				$options[$option_id] = array(
					'amount' => round(($new_amount - $old_amount)/max(1, $quantity), 4),
					'qty' => $quantity,
					'old_qty' => $old_quantity,
					'setup_fee' => 0,
					'value' => $option_value->name,
					'old_value' => $old_options[$option_id]->option_value_name
				);
			}
		}

		// Determine the pricing for options being removed
		$service_options['delete'] = (isset($service_options['delete']) ? $service_options['delete'] : array());
		foreach ($service_options['delete'] as $option_id => $option_pricing_id) {
			// Verify the value and pricing exist, as expected
			if (array_key_exists($option_id, $old_options)) {
				// The prorate amount is the full price for one-time services, or prorated to the previous renew date otherwise
				$amount = $old_options[$option_id]->option_pricing_price;
				if ($old_options[$option_id]->option_pricing_period != "onetime" && $old_service->date_renews) {
					$amount = $this->Packages->getProratePrice($amount, $date, $old_options[$option_id]->option_pricing_term, $old_options[$option_id]->option_pricing_period, null, true, $old_service->date_renews . "Z");
				}

				// Set the prorate amount to remove
				$options[$option_id] = array(
					'amount' => -$amount,
					'qty' => $old_options[$option_id]->qty,
					'setup_fee' => 0,
					'value' => null,
					'old_value' => $old_options[$option_id]->option_value_name
				);
			}
		}

		return $options;
	}

	/**
	 * Retrieves the day of the month that the given renew date represents
	 *
	 * @param string $date The date whose day of the month to determine
	 * @return int The day of the month
	 */
	private function getLocalRenewDay($date) {
		$Date = clone $this->Date;
		$Date->setTimezone("UTC", Configure::get("Blesta.company_timezone"));
		$local_renew_date = $Date->cast($date, "c");
		return $Date->cast($local_renew_date, "j");
	}

	/**
	 * Permanently deletes a pending service from the system
	 *
	 * @param int $service_id The ID of the pending service to delete
	 */
	public function delete($service_id) {
		// Set delete rules
		// A service may not be deleted if it has any children unless those children are all canceled
		$rules = array(
			'service_id' => array(
				'has_children' => array(
					'rule' => array(array($this, "validateHasChildren"), "canceled"),
					'negate' => true,
					'message' => $this->_("Services.!error.service_id.has_children")
				)
			),
			'status' => array(
				'valid' => array(
					'rule' => array("in_array", array("pending", "in_review")),
					'message' => $this->_("Services.!error.status.valid")
				)
			)
		);

		// Fetch the service's status
		$status = "";
		if (($service = $this->get($service_id)))
			$status = $service->status;

		$vars = array('service_id' => $service_id, 'status' => $status);
		$this->Input->setRules($rules);

		if ($this->Input->validates($vars)) {
			// Delete the pending service
			$this->Record->from("services")->
				leftJoin("service_fields", "service_fields.service_id", "=", "services.id", false)->
				where("services.id", "=", $service_id)->
				delete(array("services.*", "service_fields.*"));
		}
	}

	/**
	 * Sends the service (un)suspension email
	 *
	 * @param string $type The type of email to send (i.e. "suspend" or "unsuspend")
	 * @param stdClass $service An object representing the service
	 * @param stdClass $package An object representing the package associated with the service
	 */
	private function sendSuspensionNoticeEmail($type, $service, $package) {
		Loader::loadModels($this, array("Clients", "Contacts", "Emails"));

		// Fetch the client
		$client = $this->Clients->get($service->client_id);

		// Format package pricing
		if (!empty($service->package_pricing)) {
			Loader::loadModels($this, array("Currencies", "Packages"));

			// Format the currency values
			$service->package_pricing->price_formatted = $this->Currencies->toCurrency($service->package_pricing->price, $service->package_pricing->currency, $package->company_id);
			$service->package_pricing->setup_fee_formatted = $this->Currencies->toCurrency($service->package_pricing->setup_fee, $service->package_pricing->currency, $package->company_id);
			$service->package_pricing->cancel_fee_formatted = $this->Currencies->toCurrency($service->package_pricing->cancel_fee, $service->package_pricing->currency, $package->company_id);

			// Set pricing period to a language value
			$package_period_lang = $this->Packages->getPricingPeriods();
			if (isset($package_period_lang[$service->package_pricing->period]))
				$service->package_pricing->period_formatted = $package_period_lang[$service->package_pricing->period];
		}

		// Add each service field as a tag
		if (!empty($service->fields)) {
			$fields = array();
			foreach ($service->fields as $field)
				$fields[$field->key] = $field->value;
			$service = (object)array_merge((array)$service, $fields);
		}

		// Add each package meta field as a tag
		if (!empty($package->meta)) {
			$fields = array();
			foreach ($package->meta as $key => $value)
				$fields[$key] = $value;
			$package = (object)array_merge((array)$package, $fields);
		}

		$tags = array(
			'contact' => $this->Contacts->get($client->contact_id),
			'package' => $package,
			'pricing' => $service->package_pricing,
			'service' => $service,
			'client' => $client
		);

		$action = ($type == "suspend" ? "service_suspension" : "service_unsuspension");
		$this->Emails->send($action, $package->company_id, $client->settings['language'], $client->email, $tags, null, null, null, array('to_client_id' => $client->id));
	}

	/**
	 * Sends a service confirmation email
	 *
	 * @param stdClass $service An object representing the service created
	 * @param stdClass $package An object representing the package associated with the service
	 * @param int $client_id The ID of the client to send the notification to
	 */
	private function sendNotificationEmail($service, $package, $client_id) {
		Loader::loadModels($this, array("Clients", "Contacts", "Emails", "ModuleManager"));

		// Fetch the client
		$client = $this->Clients->get($client_id);

		// Look for the correct language of the email template to send, or default to English
		$service_email_content = null;
		foreach ($package->email_content as $index => $email) {
			// Save English so we can use it if the default language is not available
			if ($email->lang == "en_us")
				$service_email_content = $email;

			// Use the client's default language
			if ($client->settings['language'] == $email->lang) {
				$service_email_content = $email;
				break;
			}
		}

		// Set all tags for the email
		$language_code = ($service_email_content ? $service_email_content->lang : null);

		// Get the module and set the module host name
		$module = $this->ModuleManager->initModule($package->module_id, $package->company_id);
		$module_row = $this->ModuleManager->getRow($service->module_row_id);

		// Set all acceptable module meta fields
		$module_fields = array();
		if (!empty($module_row->meta)) {
            $tags = $module->getEmailTags();
            $tags = (isset($tags['module']) && is_array($tags['module']) ? $tags['module'] : array());

            if (!empty($tags)) {
                foreach ($module_row->meta as $key => $value) {
                    if (in_array($key, $tags))
                        $module_fields[$key] = $value;
                }
            }
		}
		$module = (object)$module_fields;

		// Format package pricing
		if (!empty($service->package_pricing)) {
			Loader::loadModels($this, array("Currencies", "Packages"));

			// Set pricing period to a language value
			$package_period_lang = $this->Packages->getPricingPeriods();
			if (isset($package_period_lang[$service->package_pricing->period]))
				$service->package_pricing->period = $package_period_lang[$service->package_pricing->period];
		}

		// Add each service field as a tag
		if (!empty($service->fields)) {
			$fields = array();
			foreach ($service->fields as $field)
				$fields[$field->key] = $field->value;
			$service = (object)array_merge((array)$service, $fields);
		}

		// Add each package meta field as a tag
		if (!empty($package->meta)) {
			$fields = array();
			foreach ($package->meta as $key => $value)
				$fields[$key] = $value;
			$package = (object)array_merge((array)$package, $fields);
		}

		$tags = array(
			'contact' => $this->Contacts->get($client->contact_id),
			'package' => $package,
			'pricing' => $service->package_pricing,
			'module' => $module,
			'service' => $service,
			'client' => $client,
			'package.email_html' => (isset($service_email_content->html) ? $service_email_content->html : ""),
			'package.email_text' => (isset($service_email_content->text) ? $service_email_content->text : "")
		);

		$this->Emails->send("service_creation", $package->company_id, $language_code, $client->email, $tags, null, null, null, array('to_client_id' => $client->id));
	}

	/**
	 * Fetches all relations (e.g. packages and services) for the given service ID
	 *
	 * @param int $service_id The ID of the service to fetch relations for
	 * @return array A array consisting of:
	 * 	- service The given service
	 * 	- package The service's package
	 * 	- parent_service The parent service
	 * 	- parent_package The parent service's package
	 */
	private function getRelations($service_id) {
		if (!isset($this->Packages))
			Loader::loadModels($this, array("Packages"));

		$service = $this->get($service_id);
		$package = $this->Packages->getByPricingId($service->pricing_id);
		$parent_package = null;
		$parent_service = null;

		if ($service->parent_service_id) {
			$parent_service = $this->get($service->parent_service_id);

			if ($parent_service)
				$parent_package = $this->Packages->getByPricingId($parent_service->pricing_id);
		}

		return array(
			'service' => $service,
			'package' => $package,
			'parent_service' => $parent_service,
			'parent_package' => $parent_package
		);
	}

	/**
	 * Schedule a service for cancellation. All cancellations requests are processed
	 * by the cron.
	 *
	 * @param int $service_id The ID of the service to schedule cancellation
	 * @param array $vars An array of service info including:
	 * 	- date_canceled The date the service is to be canceled. Possible values:
	 * 		- 'end_of_term' Will schedule the service to be canceled at the end of the current term
	 * 		- date greater than now will schedule the service to be canceled on that date
	 * 		- date less than now will immediately cancel the service
	 * 	- use_module Whether or not to use the module when canceling the service, if canceling now ('true','false', default 'true')
	 */
	public function cancel($service_id, array $vars) {
		// Cancel all children services as well
		$addon_services = $this->getAllChildren($service_id);
		foreach ($addon_services as $addon_service) {
			// Only cancel services not already canceled
			if ($addon_service->status !== "canceled") {
				$this->cancel($addon_service->id, $vars);
			}
		}
		
		$vars['service_id'] = $service_id;
		
		if (!isset($vars['use_module']))
			$vars['use_module'] = "true";
		if (isset($vars['status']))
			unset($vars['status']);

		if (!isset($vars['date_canceled']))
			$vars['date_canceled'] = date("c");

		$rules = array(
			'service_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "services"),
					'message' => $this->_("Services.!error.service_id.exists")
				)
			),
			//'date_canceled' must be either a valid date or 'end_of_term'
			'date_canceled' => array(
				'valid' => array(
					'rule' => array(array($this, "validateDateCanceled")),
					'message' => $this->_("Services.!error.date_canceled.valid")
				)
			)
		);
		$this->Input->setRules($rules);

		if ($this->Input->validates($vars)) {

			extract($this->getRelations($service_id));

			if ($vars['date_canceled'] == "end_of_term")
				$vars['date_canceled'] = $service->date_renews;
			else
				$vars['date_canceled'] = $this->dateToUtc($vars['date_canceled']);

			// If date_canceled is greater than now use module must be false
			if (strtotime($vars['date_canceled']) > time())
				$vars['use_module'] = "false";
			// Set service to canceled if cancel date is <= now
			else
				$vars['status'] = "canceled";


			// Cancel the service using the module
			if ($vars['use_module'] == "true") {

				if (!isset($this->ModuleManager))
					Loader::loadModels($this, array("ModuleManager"));

				$module_data = $this->getModuleClassByPricingId($service->pricing_id);

				if ($module_data) {
					$module = $this->ModuleManager->initModule($module_data->id, Configure::get("Blesta.company_id"));

					if ($module) {
						// Set the module row used for this service
						$module->setModuleRow($module->getModuleRow($service->module_row_id));

						$service_info = $module->cancelService($package, $service, $parent_package, $parent_service);

						if (($errors = $module->errors())) {
							$this->Input->setErrors($errors);
							return;
						}

						// Set all service fields (if any given)
						if (is_array($service_info))
							$this->setFields($service_id, $service_info);
					}
				}
			}

			// Update the service
			$this->Record->where("services.id", "=", $service_id)->
				update("services", $vars, array("date_canceled", "status"));
			
			$event = new EventObject("Services.cancel", array(
				'service_id' => $service_id,
				'vars' => $vars
			));
			$this->Events->register("Services.cancel", array("EventsServicesCallback", "cancel"));
			$this->Events->trigger($event);

			// Create an invoice regarding this cancelation
			if ($service->package_pricing->period != "onetime" && $service->package_pricing->cancel_fee > 0 && $service->date_renews != $vars['date_canceled']) {
				Loader::loadModels($this, array("Clients", "Invoices"));
				Loader::loadComponents($this, array("SettingsCollection"));

				// Get the client settings
				$client_settings = $this->SettingsCollection->fetchClientSettings($service->client_id, $this->Clients);

				// Get the pricing info
				if ($client_settings['default_currency'] != $service->package_pricing->currency)
					$pricing_info = $this->getPricingInfo($service->id, $client_settings['default_currency']);
				else
					$pricing_info = $this->getPricingInfo($service->id);

				// Create the invoice
				if ($pricing_info) {
					$invoice_vars = array(
						'client_id' => $service->client_id,
						'date_billed' => date("c"),
						'date_due' => date("c"),
						'status' => "active",
						'currency' => $pricing_info->currency,
						'delivery' => array($client_settings['inv_method']),
						'lines' => array(
							array(
								'service_id' => $service->id,
								'description' => Language::_("Invoices.!line_item.service_cancel_fee_description", true, $pricing_info->package_name, $pricing_info->name),
								'qty' => 1,
								'amount' => $pricing_info->cancel_fee,
								'tax' => (!isset($client_settings['cancelation_fee_tax']) || $client_settings['cancelation_fee_tax'] == "true" ? $pricing_info->tax : 0)
							)
						)
					);

					$this->Invoices->add($invoice_vars);
				}
			}
		}
	}

	/**
	 * Removes the scheduled cancellation for the given service
	 *
	 * @param int $service_id The ID of the service to remove scheduled cancellation from
	 */
	public function unCancel($service_id) {
		// Unancel all children services as well
		$addon_services = $this->getAllChildren($service_id);
		foreach ($addon_services as $addon_service) {
			$this->unCancel($addon_service->id);
		}
		
		// Update the service
		$this->Record->where("services.id", "=", $service_id)->
			where("services.status", "!=", "canceled")->
			update("services", array('date_canceled' => null));
	}

	/**
	 * Suspends a service
	 *
	 * @param int $service_id The ID of the service to suspend
	 * @param array $vars An array of info including:
	 * 	- use_module Whether or not to use the module when suspending the service ('true','false', default 'true')
	 * 	- staff_id The ID of the staff member that issued the service suspension
	 */
	public function suspend($service_id, array $vars=array()) {

		if (!isset($vars['use_module']))
			$vars['use_module'] = "true";
		$vars['date_suspended'] = $this->dateToUtc(date("c"));
		$vars['status'] = "suspended";

		extract($this->getRelations($service_id));

		// Cancel the service using the module
		if ($vars['use_module'] == "true") {

			if (!isset($this->ModuleManager))
				Loader::loadModels($this, array("ModuleManager"));

			$module_data = $this->getModuleClassByPricingId($service->pricing_id);

			if ($module_data) {
				$module = $this->ModuleManager->initModule($module_data->id, Configure::get("Blesta.company_id"));

				if ($module) {
					// Set the module row used for this service
					$module->setModuleRow($module->getModuleRow($service->module_row_id));

					$service_info = $module->suspendService($package, $service, $parent_package, $parent_service);

					if (($errors = $module->errors())) {
						$this->Input->setErrors($errors);
						return;
					}

					// Set all service fields (if any given)
					if (is_array($service_info))
						$this->setFields($service_id, $service_info);
				}
			}
		}

		// Update the service
		$this->Record->where("services.id", "=", $service_id)->
			update("services", $vars, array("date_suspended", "status"));
		
		$event = new EventObject("Services.suspend", array(
			'service_id' => $service_id,
			'vars' => $vars
		));
		$this->Events->register("Services.suspend", array("EventsServicesCallback", "suspend"));
		$this->Events->trigger($event);

		// Log the service suspension
		$log_service = array(
			'service_id' => $service_id,
			'staff_id' => (array_key_exists("staff_id", $vars) ? $vars['staff_id'] : null),
			'status' => "suspended",
			'date_added' => $this->dateToUtc(date("c"))
		);
		$this->Record->insert("log_services", $log_service);

		// Send the suspension email
		$this->sendSuspensionNoticeEmail("suspend", $service, $package);
	}

	/**
	 * Unsuspends a service
	 *
	 * @param int $service_id The ID of the service to unsuspend
	 * @param array $vars An array of info including:
	 * 	- use_module Whether or not to use the module when unsuspending the service ('true','false', default 'true')
	 * 	- staff_id The ID of the staff member that issued the service unsuspension
	 */
	public function unsuspend($service_id, array $vars=array()) {

		if (!isset($vars['use_module']))
			$vars['use_module'] = "true";
		$vars['date_suspended'] = null;
		$vars['date_canceled'] = null;
		$vars['status'] = "active";

		extract($this->getRelations($service_id));

		// Cancel the service using the module
		if ($vars['use_module'] == "true") {

			if (!isset($this->ModuleManager))
				Loader::loadModels($this, array("ModuleManager"));

			$module_data = $this->getModuleClassByPricingId($service->pricing_id);

			if ($module_data) {
				$module = $this->ModuleManager->initModule($module_data->id, Configure::get("Blesta.company_id"));

				if ($module) {
					// Set the module row used for this service
					$module->setModuleRow($module->getModuleRow($service->module_row_id));

					$service_info = $module->unsuspendService($package, $service, $parent_package, $parent_service);

					if (($errors = $module->errors())) {
						$this->Input->setErrors($errors);
						return;
					}

					// Set all service fields (if any given)
					if (is_array($service_info))
						$this->setFields($service_id, $service_info);
				}
			}
		}

		// Update the service
		$this->Record->where("services.id", "=", $service_id)->
			update("services", $vars, array("date_suspended", "date_canceled", "status"));
		
		$event = new EventObject("Services.unsuspend", array(
			'service_id' => $service_id,
			'vars' => $vars
		));
		$this->Events->register("Services.unsuspend", array("EventsServicesCallback", "unsuspend"));
		$this->Events->trigger($event);

		// Log the service unsuspension
		$log_service = array(
			'service_id' => $service_id,
			'staff_id' => (array_key_exists("staff_id", $vars) ? $vars['staff_id'] : null),
			'status' => "unsuspended",
			'date_added' => $this->dateToUtc(date("c"))
		);
		$this->Record->insert("log_services", $log_service);

		// Send the unsuspension email
		$this->sendSuspensionNoticeEmail("unsuspend", $service, $package);
	}

	/**
	 * Processes the renewal for the given service by contacting the module
	 * (if supported by the module), to let it know that the service should be
	 * renewed. Note: This method does not affect the renew date of the service
	 * in Blesta, it merely notifies the module; this action takes place after
	 * a service has been paid not when its renew date is bumped.
	 *
	 * @param int $service_id The ID of the service to process the renewal for
	 */
	public function renew($service_id) {

		extract($this->getRelations($service_id));

		if (!$service)
			return;

		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));

		$module_data = $this->getModuleClassByPricingId($service->pricing_id);

		if ($module_data) {
			$module = $this->ModuleManager->initModule($module_data->id, Configure::get("Blesta.company_id"));

			if ($module) {
				$service_info = $module->renewService($package, $service, $parent_package, $parent_service);

				if (($errors = $module->errors())) {
					$this->Input->setErrors($errors);
					return;
				}

				// Set all service fields (if any given)
				if (is_array($service_info))
					$this->setFields($service_id, $service_info);
			}
		}
	}

	/**
	 * Retrieves a list of service status types
	 *
	 * @return array Key=>value pairs of status types
	 */
	public function getStatusTypes() {
		return array(
			'active' => $this->_("Services.getStatusTypes.active"),
			'canceled' => $this->_("Services.getStatusTypes.canceled"),
			'pending' => $this->_("Services.getStatusTypes.pending"),
			'suspended' => $this->_("Services.getStatusTypes.suspended"),
			'in_review' => $this->_("Services.getStatusTypes.in_review"),
		);
	}

	/**
	 * Returns all action options that can be performed for a service.
	 *
	 * @parm string $current_status Set to filter actions that may be performed if the service is in the given state options include:
	 * 	- active
	 * 	- suspended
	 * 	- canceled
	 * @return array An array of key/value pairs where each key is the action that may be performed and the value is the friendly name for the action
	 */
	public function getActions($current_status = null) {

		$actions = array(
			'suspend' => $this->_("Services.getActions.suspend"),
			'unsuspend' => $this->_("Services.getActions.unsuspend"),
			'cancel' => $this->_("Services.getActions.cancel"),
			'schedule_cancel' => $this->_("Services.getActions.schedule_cancel"),
			'change_renew' => $this->_("Services.getActions.change_renew")
		);

		switch ($current_status) {
			case "active":
				unset($actions['unsuspend']);
				break;
			case "suspended":
				unset($actions['suspend']);
				break;
			case "pending":
			case "canceled":
				return array();
		}
		return $actions;
	}

	/**
	 * Updates the field data for the given service, removing all existing data and replacing it with the given data
	 *
	 * @param int $service_id The ID of the service to set fields on
	 * @param array $vars A numerically indexed array of field data containing:
	 * 	- key The key for this field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted ('true', 'false', default 'false')
	 */
	public function setFields($service_id, array $vars) {

		$do_delete = $this->Record->select()->from("service_fields")->
			where("service_fields.service_id", "=", $service_id)->numResults();

		$this->begin();

		// Avoid deadlock by not performing non-insert query within transaction unless record(s) exist
		if ($do_delete) {
			$this->Record->from("service_fields")->
				where("service_fields.service_id", "=", $service_id)->delete();
		}

		if (!empty($vars)) {
			foreach ($vars as $field) {
				$this->addField($service_id, $field);
			}
		}

		if ($this->Input->errors())
			$this->rollBack();
		else
			$this->commit();
	}

	/**
	 * Adds a service field for a particular service
	 *
	 * @param int $service_id The ID of the service to add to
	 * @param array $vars An array of service field info including:
	 * 	- key The name of the value to add
	 * 	- value The value to add
	 * 	- encrypted Whether or not to encrypt the value when storing ('true', 'false', default 'false')
	 */
	public function addField($service_id, array $vars) {

		$vars['service_id'] = $service_id;
		$this->Input->setRules($this->getFieldRules());

		if ($this->Input->validates($vars)) {

			// qty is a special key that may not be stored as a service field
			if ($vars['key'] == "qty")
				return;

			if (empty($vars['encrypted']))
				$vars['encrypted'] = "0";
			$vars['encrypted'] = $this->boolToInt($vars['encrypted']);

			$fields = array("service_id", "key", "value", "serialized", "encrypted");

			// Serialize if needed
			$serialize = !is_scalar($vars['value']);
			$vars['serialized'] = (int)$serialize;
			if ($serialize)
				$vars['value'] = serialize($vars['value']);

			// Encrypt if needed
			if ($vars['encrypted'] > 0)
				$vars['value'] = $this->systemEncrypt($vars['value']);

			$this->Record->insert("service_fields", $vars, $fields);
		}
	}

	/**
	 * Edit a service field for a particular service
	 *
	 * @param int $service_id The ID of the service to edit
	 * @param array $vars An array of service field info including:
	 * 	- key The name of the value to edit
	 * 	- value The value to update with
	 * 	- encrypted Whether or not to encrypt the value when storing ('true', 'false', default 'false')
	 */
	public function editField($service_id, array $vars) {

		$this->Input->setRules($this->getFieldRules());

		if ($this->Input->validates($vars)) {
			//if (empty($vars['encrypted']))
			//	$vars['encrypted'] = "0";
			if (array_key_exists("encrypted", $vars))
				$vars['encrypted'] = $this->boolToInt($vars['encrypted']);

			$fields = array("value", "serialized", "encrypted");

			// Serialize if needed
			$serialize = !is_scalar($vars['value']);
			$vars['serialized'] = (int)$serialize;
			if ($serialize)
			$vars['value'] = serialize($vars['value']);

			// Encrypt if needed
			if (array_key_exists("encrypted", $vars) && $vars['encrypted'] > 0)
				$vars['value'] = $this->systemEncrypt($vars['value']);

			$vars['service_id'] = $service_id;
			$fields[] = "key";
			$fields[] = "service_id";
			$this->Record->duplicate("value", "=", $vars['value'])->
				insert("service_fields", $vars, $fields);
		}
	}

	/**
	 * Returns the configurable options for the service
	 *
	 * @param int $service_id
	 * @return array An array of stdClass objects, each representing a service option
	 */
	public function getOptions($service_id) {
		$fields = array("service_options.*", 'package_option_values.value' => "option_value",
			'package_option_values.name' => "option_value_name",
			'package_option_values.option_id' => "option_id",
			'package_options.label' => "option_label",
			'package_options.name' => "option_name",
			'package_options.type' => "option_type",
			'package_options.addable' => "option_addable",
			'package_options.editable' => "option_editable",
			'pricings.term' => "option_pricing_term", 'pricings.period' => "option_pricing_period",
			'pricings.price' => "option_pricing_price", 'pricings.setup_fee' => "option_pricing_setup_fee",
			'pricings.currency' => "option_pricing_currency"
		);
		return $this->Record->select($fields)->from("service_options")->
			leftJoin("package_option_pricing", "package_option_pricing.id", "=", "service_options.option_pricing_id", false)->
			leftJoin("package_option_values", "package_option_values.id", "=", "package_option_pricing.option_value_id", false)->
			leftJoin("pricings", "pricings.id", "=", "package_option_pricing.pricing_id", false)->
			leftJoin("package_options", "package_options.id", "=", "package_option_values.option_id", false)->
			where("service_id", "=", $service_id)->fetchAll();
	}

	/**
	 * Sets the configurable options for the service
	 *
	 * @deprecated since 3.5.0
	 *
	 * @param int $service_id The ID of the service to set configurable options for
	 * @param array $config_options An array of key/value pairs where each key is the option ID and each value is the value of the option
	 */
	public function setOptions($service_id, array $config_options) {
		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));

		$service = $this->get($service_id);

		// Remove old service options
		$this->Record->from("service_options")->
			where("service_id", "=", $service_id)->delete();

		// Remove config options with 0 quantity
		$config_options = $this->formatConfigOptions($config_options);

		foreach ($config_options as $option_id => $value) {
			$option_value = $this->PackageOptions->getValue($option_id, $value);
			if ($option_value) {
				$price = $this->PackageOptions->getValuePrice($option_value->id, $service->package_pricing->term, $service->package_pricing->period, $service->package_pricing->currency);

				if (!$price)
					continue;

				$vars = array(
					'service_id' => $service_id,
					'option_pricing_id' => $price->id,
					'qty' => $option_value->value == null ? $value : 1
				);

				$this->Record->duplicate("option_pricing_id", "=", $vars['option_pricing_id'])->
					duplicate("qty", "=", $vars['qty'])->
					insert("service_options", $vars);
			}
		}
	}

	/**
	 * Returns all default welcome email tags, which are set into the email that is
	 * delivered when a service is provisioned.
	 *
	 * @return array A multi-dimensional array of tags where the first dimension is the category and the second is a numeric array of tags
	 */
	public function getWelcomeEmailTags() {
		return array(
			'client' => array("id", "id_code", "first_name", "last_name"),
			'pricing' => array("term", "period", "currency", "price", "setup_fee", "cancel_fee")
		);
	}

	/**
	 * Calculates the next renew date using a given date, term, and period
	 *
	 * @param string $last_renew_date The date the service last renewed. If never renewed this should be the service add date
	 * @param int $term The term value relating to the given period
	 * @param string $period The period (day, week, month, year, onetime)
	 * @param string $format The date format to return the date in (optional, default 'Y-m-d H:i:s')
	 * @param int $pro_rata_day The day of the month to prorate to. Only set this value to fetch the prorated renew date. Only used for month/year periods. Ignored if the $last_renew_date is on the $pro_rata_day. (optional, default null)
	 * @return string The date the service renews in UTC. In the event that the service does not renew or the renew date can not be calculated null is returned
	 */
	public function getNextRenewDate($last_renew_date, $term, $period, $format = "Y-m-d H:i:s", $pro_rata_day = null) {

		if ($last_renew_date == null)
			return null;

		$last_renew_date = $this->dateToUtc($last_renew_date, "c");

		// Fetch the renew date based on the prorate day
		if ($pro_rata_day && in_array($period, array("month", "year"))) {
			if (!isset($this->Packages))
				Loader::loadModels($this, array("Packages"));

			// Use the prorate date if not null, otherwise default to the full term below (i.e. the date is on the pro rata day, so no proration is necessary)
			$prorate_date = $this->Packages->getProrateDate($last_renew_date, $period, $pro_rata_day);
			if ($prorate_date !== null) {
				if (!$prorate_date)
					return null;

				return $this->dateToUtc($prorate_date, $format);
			}
		}

		switch ($period) {
			case "day":
				return $this->dateToUtc($last_renew_date . " +" . abs((int)$term) . " days", $format);
			case "week":
				return $this->dateToUtc($last_renew_date . " +" . abs((int)$term) . " weeks", $format);
			case "month":
				return $this->dateToUtc($last_renew_date . " +" . abs((int)$term) . " months", $format);
			case "year":
				return $this->dateToUtc($last_renew_date . " +" . abs((int)$term) . " years", $format);
		}
		return null;
	}
	
	/**
	 * Retrieves a list of line item amounts from the given line items
	 * @see Services::buildServiceCouponLineItems
	 * @note Deprecate and remove this method along with Services::buildServiceCouponLineItems
	 *
	 * @param array $line_items An array of line items that the coupon will be applying toward
	 * 	- service_id The ID of the service, matching one of the given $services
	 * 	- description The line item description
	 * 	- qty The line item quantity
	 * 	- amount The line item amount
	 * 	- tax Whether or not the line item is taxable
	 * 	- service_option_id The ID of the service option the line item represents, if any
	 * 	- setup_fee Whether or not the line item is a setup fee
	 * @return array An array containing an array of services keyed by service ID, and any service option amounts
	 * 	keyed by service option ID, including:
	 * 	- before_cutoff An array of items including:
	 * 		- amount The service amount (null if no service amount is known)
	 * 		- qty The service quantity
	 * 		- options An array of service options, including:
	 * 			- amount The service option amount
	 * 			- qty The service option quantity
	 * 	- after_cutoff An array of items after the cutoff including:
	 * 		- amount The service amount (null if no service amount is known)
	 * 		- qty The service quantity
	 * 		- options An array of service options, including:
	 * 			- amount The service option amount
	 * 			- qty The service option quantity
	 */
	private function getServiceLineItemAmounts(array $line_items) {
		// Build a list of line item amounts for services
		$line_item_amounts = array();
		
		foreach ($line_items as $line_item) {
			// Line item must belong to a service, have an amount, and not be a setup fee
			if (!is_array($line_item) || empty($line_item['service_id']) ||
				(array_key_exists("setup_fee", $line_item) && $line_item['setup_fee']) ||
				!array_key_exists("amount", $line_item) || !array_key_exists("qty", $line_item)) {
				continue;
			}
			
			// If a service exists multiple times, it may be after the cutoff date in which case it
			// should be added a second time
			$cutoff = (isset($line_item['after_cutoff']) && $line_item['after_cutoff'] ? "after_cutoff" : "before_cutoff");
			
			// Setup a line item amount for a service
			if (!array_key_exists($line_item['service_id'], $line_item_amounts)) {
				$line_item_amounts[$line_item['service_id']] = array(
					'before_cutoff' => array(
						'amount' => null,
						'qty' => 1,
						'options' => array()
					),
					'after_cutoff' => array(
						'amount' => null,
						'qty' => 1,
						'options' => array()
					)
				);
			}
			
			// Set any service option amounts
			if (array_key_exists("service_option_id", $line_item) && !empty($line_item['service_option_id'])) {
				$line_item_amounts[$line_item['service_id']][$cutoff]['options'][$line_item['service_option_id']] = array(
					'amount' => $line_item['amount'],
					'qty' => $line_item['qty']
				);
			}
			else {
				// Set the service amount
				$line_item_amounts[$line_item['service_id']][$cutoff]['amount'] = $line_item['amount'];
				$line_item_amounts[$line_item['service_id']][$cutoff]['qty'] = $line_item['qty'];
			}
		}
		
		return $line_item_amounts;
	}

	/**
	 * Retrieves a list of coupons to be applied to an invoice for services, assuming the services given are for a single client only
	 *
	 * @param array $services An array of stdClass objects, each representing a service
	 * @param string $default_currency The ISO 4217 currency code for the client
	 * @param array $coupons A reference to coupons that will need to be incremented
	 * @param boolean $services_renew True if all of the given $services are renewing services, or false if all $services are new services (optional, default false)
	 * @param array $line_items An array of line items that the coupon will be applying toward (optional but highly recommended)
	 * 	- service_id The ID of the service, matching one of the given $services
	 * 	- description The line item description
	 * 	- qty The line item quantity
	 * 	- amount The line item amount
	 * 	- tax Whether or not the line item is taxable
	 * 	- service_option_id The ID of the service option the line item represents, if any
	 * 	- setup_fee Whether or not the line item is a setup fee
	 * 	- after_cutoff Whether or not the line item is after the cutoff date
	 * @return array An array of coupon line items to append to an invoice
	 */
	public function buildServiceCouponLineItems(array $services, $default_currency, &$coupons, $services_renew = false, array $line_items = array()) {
		Loader::loadModels($this, array("Coupons", "Currencies"));
		// Load Invoice language needed for line items
		if (!isset($this->Invoices))
			Language::loadLang(array("invoices"));

		$coupons = array();
		$coupon_service_ids = array();
		$service_list = array();
		$now_timestamp = $this->Date->toTime($this->Coupons->dateToUtc("c"));

		// Determine which coupons could be used
		foreach ($services as $service) {

			// Fetch the coupon associated with this service
			if ($service->coupon_id && !isset($coupons[$service->coupon_id]))
				$coupons[$service->coupon_id] = $this->Coupons->get($service->coupon_id);

			// Skip this service if it has no active coupon or it does not apply to renewing services
			if (!$service->coupon_id || !isset($coupons[$service->coupon_id]) ||
				$coupons[$service->coupon_id]->status != "active" ||
				($services_renew && $coupons[$service->coupon_id]->recurring != "1")) {
				continue;
			}

			if (!isset($service->package_pricing))
				$service->package_pricing = $this->getPackagePricing($service->pricing_id);

			// See if this coupon has a discount available in the correct currency
			$coupon_amount = false;
			foreach ($coupons[$service->coupon_id]->amounts as $amount) {
				if ($amount->currency == $service->package_pricing->currency) {
					$coupon_amount = $amount;
					break;
				}
			}
			unset($amount);

			// Add the coupon if it is usable
			if ($coupon_amount) {
				// Verify coupon applies to this service
				$coupon_applies = false;
				$coupon_recurs = ($coupons[$service->coupon_id]->recurring == "1");
				foreach ($coupons[$service->coupon_id]->packages as $coupon_package) {
					if ($coupon_package->package_id == $service->package_pricing->package_id)
						$coupon_applies = true;
				}
				
				// Determine whether the coupon applies
				$coupon_applies = ($coupon_applies && (!$services_renew || ($services_renew && $coupon_recurs)));
				
				// Validate whether the coupon passes its limitations
				$apply_coupon = false;
				if ($coupon_applies) {
					// Coupon applies to renewing services ignoring limits
					if ($services_renew && $coupons[$service->coupon_id]->limit_recurring != "1") {
						$apply_coupon = true;
					}
					else {
						// Max quantity may be 0 for unlimited uses, otherwise it must be larger than the used quantity to apply
						$coupon_qty_reached = ($coupons[$service->coupon_id]->max_qty == "0" ? false : $coupons[$service->coupon_id]->used_qty >= $coupons[$service->coupon_id]->max_qty);
						
						// Coupon must be valid within start/end dates and must not exceed used quantity
						if ($now_timestamp >= $this->Date->toTime($coupons[$service->coupon_id]->start_date) &&
							$now_timestamp <= $this->Date->toTime($coupons[$service->coupon_id]->end_date) &&
							!$coupon_qty_reached) {
							$apply_coupon = true;
						}
					}
				}
				
				// The coupon applies to the service
				if ($apply_coupon) {
					if (!isset($coupon_service_ids[$service->coupon_id]))
						$coupon_service_ids[$service->coupon_id] = array();
					$coupon_service_ids[$service->coupon_id][] = $service->id;
					$service_list[$service->id] = $service;
				}
			}
		}

		// Build a list of line item amounts for services
		$line_item_amounts = $this->getServiceLineItemAmounts($line_items);
		unset($line_items);
		
		// Create the line items for the coupons set
		$line_items = array();
		foreach ($coupon_service_ids as $coupon_id => $service_ids) {
			// Skip if coupon is not available
			if (!isset($coupons[$coupon_id]) || !$coupons[$coupon_id])
				continue;

			$line_item_amount = null;
			$line_item_description = null;
			$line_item_quantity = 1;
			$currency = null;

			// Exclusive coupons can be added with any service
			if ($coupons[$coupon_id]->type == "exclusive") {
				$discount_amount = null;
				$service_total = 0;

				// Set the line item amount/description
				foreach ($coupons[$coupon_id]->amounts as $amount) {
					// Calculate the total from each service related to this coupon
					foreach ($service_ids as $service_id) {
						// Skip if service is not available or incorrect currency
						if (!isset($service_list[$service_id]) || ($amount->currency != $service_list[$service_id]->package_pricing->currency))
							continue;

						$service_amount = $service_list[$service_id]->package_pricing->price;
						$line_item_quantity = $service_list[$service_id]->qty;
						$discount_amount = abs($amount->amount);
						$options_total = $this->getServiceOptionsTotal($service_id);
						
						// Replace the options total with the sum of each service option line item amount
						if (isset($line_item_amounts[$service_id])) {
							// Set the base service amount and quantity
							if (isset($line_item_amounts[$service_id]['before_cutoff']['amount']) ||
								isset($line_item_amounts[$service_id]['after_cutoff']['amount'])) {
								// Sum the before/after cutoff amounts
								$before_amount = $line_item_amounts[$service_id]['before_cutoff']['amount'];
								$after_amount = $line_item_amounts[$service_id]['after_cutoff']['amount'];
								$service_amount = ($before_amount === null ? 0 : $before_amount) + ($after_amount === null ? 0 : $after_amount);
								
								// Before/after cutoff quantity always presumed to be identical
								$line_item_quantity = $line_item_amounts[$service_id]['before_cutoff']['qty'];
							}
							
							// Calculate the service option amount total for each amount
							$override_option_total = false;
							foreach (array("before_cutoff", "after_cutoff") as $cutoff) {
								if (!empty($line_item_amounts[$service_id][$cutoff]['options'])) {
									// Override the option total
									$options_total = ($override_option_total === false ? 0 : $options_total);
									$override_option_total = true;
									
									foreach ($line_item_amounts[$service_id][$cutoff]['options'] as $option_amount) {
										$options_total += ($option_amount['qty'] * $option_amount['amount']);
									}
								}
							}
						}

						// Set the discount amount based on percentage
						if ($amount->type == "percent") {
							$line_item_description = Language::_("Invoices.!line_item.coupon_line_item_description_percent", true, $coupons[$coupon_id]->code, $discount_amount);
							$discount_amount /= 100;
							$line_item_amount += -(abs($service_amount * $line_item_quantity)*$discount_amount);
							
							// Include the service options amount
							if ($coupons[$coupon_id]->apply_package_options == "1" && $options_total > 0) {
								$line_item_amount += -(abs($options_total)*$discount_amount);
							}
						}
						// Set the discount amount based on amount
						else {
							// Set the coupon amount to deduct from the package
							$package_cost = ($service_amount * $line_item_quantity);
							$temp_discount_amount = ($discount_amount >= $package_cost ? $package_cost : $discount_amount);

							// Determine the coupon discount amount from the package's config options as well
							if ($coupons[$coupon_id]->apply_package_options == "1" && $options_total > 0) {
								// Set the coupon amount to deduct from the coupon remainder
								if ($temp_discount_amount < $discount_amount) {
									$temp_discount_amount += (($discount_amount - $temp_discount_amount) >= $options_total ? $options_total : ($discount_amount - $temp_discount_amount));
								}
							}

							$line_item_amount += -max(0, $temp_discount_amount);
							$line_item_description = Language::_("Invoices.!line_item.coupon_line_item_description_amount", true, $coupons[$coupon_id]->code);
						}

						$currency = $amount->currency;
					}
				}
				unset($amount);
			}
			// Inclusive coupons can only be added to all services together
			elseif ($coupons[$coupon_id]->type == "inclusive") {
				$service_total = 0;
				$options_total = 0;
				$matched_packages = array();

				// Check each coupon package correlates with a service package
				foreach ($coupons[$coupon_id]->packages as $package) {
					foreach ($service_ids as $service_id) {
						// Skip if service is not available
						if (!isset($service_list[$service_id]))
							break 2;
						
						$service_amount = $service_list[$service_id]->package_pricing->price;
						$line_item_quantity = $service_list[$service_id]->qty;
						$temp_options_total = $this->getServiceOptionsTotal($service_id);
						
						// Replace the options total with the sum of each service option line item amount
						if (isset($line_item_amounts[$service_id])) {
							// Set the base service amount and quantity
							if (isset($line_item_amounts[$service_id]['before_cutoff']['amount']) ||
								isset($line_item_amounts[$service_id]['after_cutoff']['amount'])) {
								// Sum the before/after cutoff amounts
								$before_amount = $line_item_amounts[$service_id]['before_cutoff']['amount'];
								$after_amount = $line_item_amounts[$service_id]['after_cutoff']['amount'];
								$service_amount = ($before_amount === null ? 0 : $before_amount) + ($after_amount === null ? 0 : $after_amount);
								
								// Before/after cutoff quantity always presumed to be identical
								$line_item_quantity = $line_item_amounts[$service_id]['before_cutoff']['qty'];
							}
							
							// Calculate the service option amount total for each amount
							$temp_option_total = null;
							foreach (array("before_cutoff", "after_cutoff") as $cutoff) {
								if (!empty($line_item_amounts[$service_id][$cutoff]['options'])) {
									$temp_options_total = ($temp_option_total === null ? 0 : $temp_options_total);
									foreach ($line_item_amounts[$service_id][$cutoff]['options'] as $option_amount) {
										$temp_options_total += ($option_amount['qty'] * $option_amount['amount']);
									}
								}
							}
						}
						
						// Save a list of matched packages and set the price of each
						if ($service_list[$service_id]->package_pricing->package_id == $package->package_id) {
							$matched_packages[$package->package_id] = $package->package_id;
							$service_total += ($service_amount * $line_item_quantity);
							$options_total += $temp_options_total;
						}
					}
				}

				// All service packages matched all coupon packages, this coupon can be applied
				if (count($matched_packages) == count($coupons[$coupon_id]->packages)) {
					// Calculate the amount, must be a percentage to be applied to all
					$percent = null;
					foreach ($coupons[$coupon_id]->amounts as $amount) {
						if ($amount->currency == $service_list[$service_id]->package_pricing->currency && $amount->type == "percent") {
							$percent = abs($amount->amount)/100;
							$currency = $amount->currency;
							break;
						}
					}
					unset($amount);

					if ($percent !== null) {
						$line_item_amount = -(abs($service_total)*$percent);

						// Include the service options amount
						if ($coupons[$coupon_id]->apply_package_options == "1" && $options_total > 0) {
							$line_item_amount += -(abs($options_total)*$percent);
						}

						$line_item_description = Language::_("Invoices.!line_item.coupon_line_item_description_percent", true, $coupons[$coupon_id]->code, ($percent*100));
					}
				}
			}

			// Create the line item
			if ($line_item_amount && $line_item_description && $currency) {
				// Convert the amount to the default currency for this client
				if ($currency != $default_currency)
					$line_item_amount = $this->Currencies->convert($line_item_amount, $currency, $default_currency, Configure::get("Blesta.company_id"));

				$line_items[] = array(
					'service_id' => null,
					'description' => $line_item_description,
					'qty' => 1,
					'amount' => $line_item_amount,
					'tax' => false
				);
			}
		}

		return $line_items;
	}

	/**
	 * Retrieves the full total service options cost for the given service
	 * @see Services::buildServiceCouponLineItems
	 *
	 * @param int $service_id The ID of the service to which the options belong
	 * @return float The total cost of all service options
	 */
	private function getServiceOptionsTotal($service_id) {
		Loader::loadModels($this, array("PackageOptions"));

		$total = 0.0;

		// Fetch the pricing info for this service in its defined currency (no currency conversion) so service options (below)
		// can be converted from the original service currency to the new currency
		$base_pricing_info = $this->getPricingInfo($service_id);

		// Set each service configurable option line item
		$service_options = $this->getOptions($service_id);
		foreach ($service_options as $service_option) {
			$package_option = $this->PackageOptions->getByPricingId($service_option->option_pricing_id);

			if ($package_option && property_exists($package_option, "value") && property_exists($package_option->value, "pricing") && $package_option->value->pricing) {
				// Get the total option price
				$amount = $this->PackageOptions->getValuePrice($package_option->value->id, $package_option->value->pricing->term, $package_option->value->pricing->period, (isset($base_pricing_info->currency) ? $base_pricing_info->currency : ""));

				// This doesn't consider proration
				if ($amount) {
					$total += ($service_option->qty * $amount->price);
				}
			}
		}

		return $total;
	}
	
	/**
	 * Retrieves a set of items, discounts, and taxes for a service given data from
	 * which it may be created
	 *
	 * @param int $client_id The ID of the client the service applies to
	 * @param array $vars An array of input representing a new service data
	 * 	- coupon_id The ID of the coupon to apply to the service
	 * 	- configoptions An array of key/value pairs where each key is an option ID and each value is the selected value
	 * 	- pricing_id The ID of the new pricing selected
	 * @return array An array of formatted items, discounts, and taxes from the PricingPresenter:
	 * 	- items An array of each item, including
	 * 		- price The unit price of the item
	 * 		- qty The quantity of the item
	 * 		- description The description of the item
	 * 	- discounts An array of all applying discounts, including
	 * 		- amount The amount of the discount
	 * 		- type The type of the discount
	 * 		- description A description of the discount
	 * 		- apply An array of indices referencing items to which the discount applies
	 * 	- taxes An array of arrays of each tax group containing tax rules that apply, including:
	 * 		- amount The amount of the tax
	 * 		- type The type of tax
	 * 		- description The tax description
	 * 		- apply An array of indices referencing items to which the tax applies
	 */
	public function getItemsFromData($client_id, array $vars) {
		Loader::loadModels($this, array("Clients", "Coupons", "Invoices", "Packages", "PackageOptions"));
		
		$list = array('items' => array(), 'discounts' => array(), 'taxes' => array());
		
		// Fetch the client
		$pricing = null;
		$pricing_id = (isset($vars['pricing_id']) ? (int)$vars['pricing_id'] : null);
		if (!$pricing_id || !($client = $this->Clients->get($client_id))) {
			return $list;
		}
		
		// Fetch the package and the selected pricing
		if ($pricing_id && ($package = $this->Packages->getByPricingId($pricing_id))) {
			foreach ($package->pricing as $price) {
				if ($price->id == $pricing_id) {
					$pricing = $price;
					break;
				}
			}
		}
		
		// Package and pricing must be available
		if (empty($package) || empty($pricing)) {
			return $list;
		}
		
		// Fetch all package options
		$package_options = $this->PackageOptions->getAllByPackageId($package->id, $pricing->term, $pricing->period, $pricing->currency);
		
		// Fetch the client tax rules
		$tax_rules = $this->Invoices->getTaxRules($client->id);
		
		// Fetch any coupon that should be applied
		$coupons = array();
		if (isset($vars['coupon_id']) && ($coupon = $this->Coupons->get((int)$vars['coupon_id']))) {
			$coupons[] = $coupon;
		}
		
		// Format all of the service data into items/discounts/taxes
		Loader::loadComponents($this, array('PricingPresenter' => array($client->settings, $tax_rules, $coupons)));
		return $this->PricingPresenter->formatServiceData($vars, $package, $pricing, $package_options);
	}

    /**
     * Retrieves the next expected renewal price of a service based on its current
     * configuration, options, and pricing
     *
     * @param int $service_id The ID of the service whose renewal pricing to fetch
     * @return float The next expected renewal price
     */
    public function getRenewalPrice($service_id) {
        Loader::loadModels($this, array("Coupons", "Invoices"));
        Loader::loadComponents($this, array("SettingsCollection"));

		// Fetch the service
        $service = $this->get($service_id);

        // Non-recurring pricing do not renew
        if (!$service || $service->package_pricing->period == "onetime") {
            return 0;
        }
        
		// Fetch the client's settings
        $settings = $this->SettingsCollection->fetchClientSettings($service->client_id);
		// Fetch the client tax rules
		$tax_rules = $this->Invoices->getTaxRules($service->client_id);
		// Fetch the service's set price/currency
		$pricing = $this->getServicePrice($service);
		
		// Fetch any recurring coupon that may exist
		$coupons = array();
		if ($service->coupon_id &&
            ($coupon = $this->Coupons->getRecurring($service->coupon_id, $pricing['currency'], $service->date_renews . "Z"))) {
			$coupons[] = $coupon;
		}
		
		// Format all of the service data into items/discounts/taxes
		Loader::loadComponents($this, array('PricingPresenter' => array($settings, $tax_rules, $coupons, array('recur' => true))));
		$data = $this->PricingPresenter->formatService($service);
		
		// Fetch the items and totals
		$items = $this->Invoices->getItemTotals($data['items'], $data['discounts'], $data['taxes']);
		
        return $items['totals']->total;
    }

    /**
     * Retrieves the service price
     *
     * @param stdClass $service An stdClass object representing the service
     * @return array An array containing:
     *  - price The service price
     *  - qty The service quantity
     *  - currency The service currency
     */
    private function getServicePrice($service) {
        $currency = $service->package_pricing->currency;
        $price = $service->package_pricing->price;

        // Set the service override price, if any
        if ($service->override_price !== null && $service->override_currency !== null) {
            $currency = $service->override_currency;
            $price = $service->override_price;
        }

        return array(
            'currency' => $currency,
            'price' => $price,
            'qty' => $service->qty
        );
    }

	/**
	 * Return all field data for the given service, decrypting fields where neccessary
	 *
	 * @param int $service_id The ID of the service to fetch fields for
	 * @return array An array of stdClass objects representing fields, containing:
	 * 	- key The service field name
	 * 	- value The value for this service field
	 * 	- encrypted Whether or not this field was originally encrypted (1 true, 0 false)
	 */
	protected function getFields($service_id) {
		$fields = $this->Record->select(array("key", "value", "serialized", "encrypted"))->
			from("service_fields")->where("service_id", "=", $service_id)->
			fetchAll();
		$num_fields = count($fields);
		for ($i=0; $i<$num_fields; $i++) {
			// If the field is encrypted, must decrypt the field
			if ($fields[$i]->encrypted)
				$fields[$i]->value = $this->systemDecrypt($fields[$i]->value);

			if ($fields[$i]->serialized)
				$fields[$i]->value = unserialize($fields[$i]->value);
		}

		return $fields;
	}

	/**
	 * Returns info regarding the module belonging to the given $package_pricing_id
	 *
	 * @param int $package_pricing_id The package pricing ID to fetch the module of
	 * @return mixed A stdClass object containing module info and the package ID belonging to the given $package_pricing_id, false if no such module exists
	 */
	private function getModuleClassByPricingId($package_pricing_id) {
		return $this->Record->select(array("modules.*", 'packages.id' => "package_id"))->from("package_pricing")->
			innerJoin("packages", "packages.id", "=", "package_pricing.package_id", false)->
			innerJoin("modules", "modules.id", "=", "packages.module_id", false)->
			where("package_pricing.id", "=", $package_pricing_id)->
			fetch();
	}

	/**
	 * Formats given config options by removing options with 0 quantity
	 *
	 * @param array $config_options An array of key/value pairs of package options where the key is the package option ID and the value is the option value (optional)
	 * @return array An array of key/value pairs of package options where the key is the package option ID and the value is the option value
	 */
	private function formatConfigOptions(array $config_options = array()) {
		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));

		// Remove config options with quantity of 0
		if (!empty($config_options)) {
			foreach ($config_options as $option_id => $value) {
				if ($value == 0 && ($option = $this->PackageOptions->get($option_id)) && $option->type == "quantity") {
					unset($config_options[$option_id]);
					continue;
				}
			}
		}

		return $config_options;
	}

	/**
	 * Validates a service's 'status' field
	 *
	 * @param string $status The status type
	 * @return boolean True if $status is valid, false otherwise
	 */
	public function validateStatus($status) {
		$options = array_keys($this->getStatusTypes());
		return in_array($status, $options);
	}

	/**
	 * Validates whether to use a module when adding/editing a service
	 *
	 * @param string $use_module
	 * @return boolean True if validated, false otherwise
	 */
	public function validateUseModule($use_module) {
		$options = array("true", "false");
		return in_array($use_module, $options);
	}

	/**
	 * Validates a service field's 'encrypted' field
	 *
	 * @param string $encrypted Whether or not to encrypt
	 */
	public function validateEncrypted($encrypted) {
		$options = array(0, 1, "true", "false");
		return in_array($encrypted, $options);
	}

	/**
	 * Validates whether the given service has children NOT of the given status
	 *
	 * @param int $service_id The ID of the parent service to validate
	 * @param string $status The status of children services to ignore (e.g. "canceled") (optional, default null to not ignore any child services)
	 * @return boolean True if the service has children not of the given status, false otherwise
	 */
	public function validateHasChildren($service_id, $status=null) {
		$this->Record->select()->from("services")->
			where("parent_service_id", "=", $service_id);

		if ($status)
			$this->Record->where("status", "!=", $status);

		return ($this->Record->numResults() > 0);
	}

	/**
	 * Retrieves the rule set for adding/editing service fields
	 *
	 * @return array The rules
	 */
	public function getFieldRules() {
		$rules = array(
			'key' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Services.!error.key.empty")
				)
			),
			'encrypted' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateEncrypted")),
					'message' => $this->_("Services.!error.encrypted.format"),
					'post_format' => array(array($this, "boolToInt"))
				)
			)
		);
		return $rules;
	}

	/**
	 * Retrieves the rule set for adding/editing services
	 *
	 * @param array $vars An array of input fields
	 * @param boolean $edit Whether or not this is an edit request
	 * @param int $service_id The ID of the service being edited (optional, default null)
	 * @return array The rules
	 */
	private function getRules($vars, $edit=false, $service_id = null) {
		$rules = array(
			'parent_service_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "services"),
					'message' => $this->_("Services.!error.parent_service_id.exists")
				),
				'parent' => array(
					'if_set' => true,
					'rule' => array(array($this, "hasParent")),
					'negate' => true,
					'message' => $this->_("Services.!error.parent_service_id.parent")
				)
			),
			'package_group_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "package_groups"),
					'message' => $this->_("Services.!error.package_group_id.exists")
				)
			),
			'id_format' => array(
				'empty' => array(
					'if_set' => true,
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Services.!error.id_format.empty")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 64),
					'message' => $this->_("Services.!error.id_format.length")
				)
			),
			'id_value' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "isInstanceOf"), "Record"),
					'message' => $this->_("Services.!error.id_value.valid")
				)
			),
			'pricing_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "package_pricing"),
					'message' => $this->_("Services.!error.pricing_id.exists")
				)
			),
			'client_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "clients"),
					'message' => $this->_("Services.!error.client_id.exists")
				),
				'allowed' => array(
					'rule' => array(array($this, "validateAllowed"), isset($vars['pricing_id']) ? $vars['pricing_id'] : null),
					'message' => $this->_("Services.!error.client_id.allowed")
				)
			),
			'module_row_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "module_rows"),
					'message' => $this->_("Services.!error.module_row_id.exists")
				)
			),
			'coupon_id' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateCoupon"), isset($vars['coupon_packages']) ? $vars['coupon_packages'] : null),
					'message' => $this->_("Services.!error.coupon_id.valid")
				)
			),
			'qty' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Services.!error.qty.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 10),
					'message' => $this->_("Services.!error.qty.length")
				),
				'available' => array(
					'if_set' => true,
					'rule' => array(array($this, "decrementQuantity"), isset($vars['pricing_id']) ? $vars['pricing_id'] : null, true, $edit && isset($vars['current_qty']) ? $vars['current_qty'] : null),
					'message' => $this->_("Services.!error.qty.available")
				)
			),
            'override_price' => array(
                'format' => array(
                    'if_set' => true,
                    'rule' => array(array($this, "validatePriceOverride")),
                    'message' => $this->_("Services.!error.override_price.format"),
                    'post_format'=>array(array($this, "currencyToDecimal"), array('_linked'=>"override_currency"), 4)
                ),
                'override' => array(
                    'rule' => array(array($this, "validateOverrideFields"), (isset($vars['override_currency']) ? $vars['override_currency'] : null)),
                    'message' => $this->_("Services.!error.override_price.override")
                )
            ),
            'override_currency' => array(
                'format' => array(
                    'if_set' => true,
                    'rule' => array(array($this, "validateExists"), "code", "currencies"),
                    'message' => $this->_("Services.!error.override_currency.format")
                )
            ),
			'status' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStatus")),
					'message' => $this->_("Services.!error.status.format")
				)
			),
			'date_added' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'post_format'=>array(array($this, "dateToUtc")),
					'message' => $this->_("Services.!error.date_added.format")
				)
			),
			'date_renews' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateDateRenews"), isset($vars['date_last_renewed']) ? $vars['date_last_renewed'] : null),
					'message' => $this->_("Services.!error.date_renews.valid", isset($vars['date_last_renewed']) ? $this->Date->cast($vars['date_last_renewed'], "Y-m-d") : null)
				),
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'post_format'=>array(array($this, "dateToUtc")),
					'message' => $this->_("Services.!error.date_renews.format")
				)
			),
			'date_last_renewed' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'post_format'=>array(array($this, "dateToUtc")),
					'message' => $this->_("Services.!error.date_last_renewed.format")
				)
			),
			'date_suspended' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'post_format'=>array(array($this, "dateToUtc")),
					'message' => $this->_("Services.!error.date_suspended.format")
				)
			),
			'date_canceled' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'post_format'=>array(array($this, "dateToUtc")),
					'message' => $this->_("Services.!error.date_canceled.format")
				)
			),
			'use_module' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateUseModule")),
					'message' => $this->_("Services.!error.use_module.format")
				)
			),
			'configoptions' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateConfigOptions"), isset($vars['pricing_id']) ? $vars['pricing_id'] : null),
					'message' => $this->_("Services.!error.configoptions.valid")
				)
			)
		);

		// Set rules for editing services
		if ($edit) {
			// Determine override pricing
			$override_price = (array_key_exists("override_price", $vars) ? $vars['override_price'] : "");
			$override_currency = (array_key_exists("override_currency", $vars) ? $vars['override_currency'] : "");
			if ($service_id && ($override_price === "" || $override_currency === "")
				&& ($service = $this->get($service_id))) {
				// Empty strings set for override pricing will fail validation, so if one is not given, use the current values
				if ($override_currency === "") {
					$override_currency = $service->override_currency;
				}
				if ($override_price === "") {
					$override_price = $service->override_price;
				}
			}
			
            // Pricing ID rule
            $rules['pricing_id']['overrides'] = array(
                'rule' => array(array($this, "validatePricingWithOverrides"), $service_id, $override_price, $override_currency),
                'message' => $this->_("Services.!error.pricing_id.overrides")
            );

			$rules['prorate'] = array(
				'format' => array(
					'if_set' => true,
					'rule' => array("in_array", array("true", "false")),
					'message' => $this->_("Services.!error.prorate.format")
				)
			);

			// Remove id_format and id_value, they cannot be updated
			unset($rules['id_format'], $rules['id_value'], $rules['client_id']['allowed']);

			$rules['pricing_id']['exists']['if_set'] = true;
			$rules['client_id']['exists']['if_set'] = true;
		}

		return $rules;
	}

	/**
	 * Checks if the given $field is a reference of $class
	 */
	public function isInstanceOf($field, $class) {
		return $field instanceof $class;
	}

	/**
	 * Performs all validation necessary before adding a service
	 *
	 * @param array $vars An array of service info including:
	 * 	- parent_service_id The ID of the service this service is a child of (optional)
	 * 	- package_group_id The ID of the package group this service was added from (optional)
	 * 	- pricing_id The package pricing schedule ID for this service
	 * 	- client_id The ID of the client to add the service under
	 * 	- module_row_id The module row to add the service under (optional, default is first available)
	 * 	- coupon_id The ID of the coupon used for this service (optional)
	 * 	- qty The quanity consumed by this service (optional, default 1)
	 * 	- status The status of this service ('active','canceled','pending','suspended', default 'pending')
	 * 	- date_added The date this service is added (default to today's date UTC)
	 * 	- date_renews The date the service renews (optional, default calculated by package term)
	 * 	- date_last_renewed The date the service last renewed (optional)
	 * 	- date_suspended The date the service was last suspended (optional)
	 * 	- date_canceled The date the service was last canceled (optional)
	 * 	- use_module Whether or not to use the module when creating the service ('true','false', default 'true')
	 * 	- configoptions An array of key/value pairs of package options where the key is the package option ID and the value is the option value (optional)
	 * 	- * Any other service field data to pass to the module
	 * @param array $packages A numerically indexed array of packages ordered along with this service to determine if the given coupon may be applied
	 * @return array $vars An array of $vars, modified by error checking
	 * @see Services::validateService()
	 */
	public function validate(array $vars, array $packages = null) {

		if (!isset($this->Packages))
			Loader::loadModels($this, array("Packages"));
		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));

		$vars['coupon_packages'] = $packages;

		if (!isset($vars['qty']))
			$vars['qty'] = 1;

		// Check basic rules
		$this->Input->setRules($this->getRules($vars, false));

		// Set date added if not given
		if (!isset($vars['date_added']))
			$vars['date_added'] = date("c");

		// Get the package
		if (isset($vars['pricing_id'])) {
			$package = $this->Packages->getByPricingId($vars['pricing_id']);

			// Set the next renew date based on the package pricing
			if ($package && empty($vars['date_renews'])) {
				foreach ($package->pricing as $pricing) {
					if ($pricing->id == $vars['pricing_id']) {
						// Set date renews
						$vars['date_renews'] = $this->getNextRenewDate($vars['date_added'], $pricing->term, $pricing->period, "c", $package->prorata_day);
						break;
					}
				}
				unset($pricing);
			}
		}

		if ($this->Input->validates($vars)) {

			$module = $this->ModuleManager->initModule($package->module_id);

			if ($module) {
				$module->validateService($package, $vars);

				// If any errors encountered through the module, set errors
				if (($errors = $module->errors())) {
					$this->Input->setErrors($errors);
					return;
				}
			}
		}
		return $vars;
	}

	/**
	 * Validates service info, including module options, for creating a service. An alternative to Services::validate()
	 *
	 * @param stdClass $package A stdClass object representing the package for the service
	 * @param array $vars An array of values to be evaluated, including:
	 * 	- invoice_method The invoice method to use when creating the service, options are:
	 * 		- create Will create a new invoice when adding this service
	 * 		- append Will append this service to an existing invoice (see 'invoice_id')
	 * 		- none Will not create any invoice
	 * 	- invoice_id The ID of the invoice to append to if invoice_method is set to 'append'
	 * 	- pricing_id The ID of the package pricing to use for this service
	 * 	- * Any other service field data to pass to the module
	 * @see Services::validate()
	 */
	public function validateService($package, array $vars) {

		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));

		$rules = array(
			/*
			'client_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "clients"),
					'message' => $this->_("Services.!error.client_id.exists")
				)
			),
			*/
			'invoice_method' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array("in_array", array("create", "append", "none")),
					'message' => $this->_("Services.!error.invoice_method.valid")
				)
			),
			'pricing_id' => array(
				'valid' => array(
					'rule' => array(array($this, "validateExists"), "id", "package_pricing"),
					'message' => $this->_("Services.!error.pricing_id.valid")
				)
			),
			'configoptions' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateConfigOptions"), isset($vars['pricing_id']) ? $vars['pricing_id'] : null),
					'message' => $this->_("Services.!error.configoptions.valid")
				)
			),
			'qty' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Services.!error.qty.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 10),
					'message' => $this->_("Services.!error.qty.length")
				),
				'available' => array(
					'if_set' => true,
					'rule' => array(array($this, "decrementQuantity"), isset($vars['pricing_id']) ? $vars['pricing_id'] : null, true),
					'message' => $this->_("Services.!error.qty.available")
				)
			)
			/*
			'status' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStatus")),
					'message' => $this->_("Services.!error.status.format")
				)
			),
			*/
		);

		$this->Input->setRules($rules);
		if ($this->Input->validates($vars)) {

			$module_data = $this->getModuleClassByPricingId($vars['pricing_id']);

			if ($module_data) {
				$module = $this->ModuleManager->initModule($module_data->id);

				if ($module && !$module->validateService($package, $vars))
					$this->Input->setErrors($module->errors());

			}
		}
	}

	/**
	 * Verifies if the given coupon ID can be applied to the requested packages
	 *
	 * @param int $coupon_id The ID of the coupon to validate
	 * @param array An array of pacakges to confirm the coupon can be applied
	 * @return boolean True if the coupon can be applied, false otherwise
	 */
	public function validateCoupon($coupon_id, array $packages=null) {
		if (!isset($this->Coupons))
			Loader::loadModels($this, array("Coupons"));

		return (boolean)$this->Coupons->getForPackages(null, $coupon_id, $packages);
	}

	/**
	 * Verifies that the given date value is valid for a cancel date
	 *
	 * @param string $date The date to cancel a service or "end_of_term" to cancel at the end of the term
	 * @return boolean True if $date is valid, false otherwise
	 */
	public function validateDateCanceled($date) {
		return ($this->Input->isDate($date) || strtolower($date) == "end_of_term");
	}

	/**
	 * Verifies that the given renew date is greater than the last renew date (if available)
	 *
	 * @param string $renew_date The date a service should renew
	 * @param string $last_renew_date The date a service last renewed
	 * @return boolean True if renew date is valid, false otherwise
	 */
	public function validateDateRenews($renew_date, $last_renew_date=null) {
		if ($last_renew_date)
			return $this->dateToUtc($renew_date) > $this->dateToUtc($last_renew_date);
		return true;
	}

    /**
     * Verifies that the given price override is in a valid format
     *
     * @param float $price The price override
     * @return boolean True if the price is valid, false otherwise
     */
    public function validatePriceOverride($price) {
        if ($price === null)
            return true;

        return is_numeric($price);
    }

    /**
     * Verifies that the given price and currency fields have been set together
     *
     * @param mixed $price The price override, or null
     * @param mixed $currency The currency override, or null
     * @return boolean True if the price and currency have been set properly together, or false otherwise
     */
    public function validateOverrideFields($price, $currency) {
        // Price and currency overrides need to both be null, or both be set
        if (($price === null && $currency === null) || ($price !== null && $currency !== null))
            return true;

        return false;
    }

    /**
     * Verifies that the given service and pricing ID are valid with price overrides
     *
     * @param int $pricing_id The ID of the pricing term
     * @param int $service_id The ID of the service being updated
     * @param float $price The price override amount
     * @param string $currency The price override currency
     * @return boolean True if the pricing ID may be set for this service given the price overrides, or false otherwise
     */
    public function validatePricingWithOverrides($pricing_id, $service_id, $price, $currency) {
        $service = $this->get($service_id);

        if ($service) {
            // Package pricing can only be changed if overrides are valid
            if ($service->pricing_id != $pricing_id) {
                // If removing the price and currency overrides, changing the pricing term is valid
                if ($price === null && $currency === null)
                    return true;

                // Cannot change package term when price overrides are set
                if ($service->override_price !== null || $service->override_currency !== null || $price !== null || $currency !== null)
                    return false;
            }
        }

        return true;
    }

	/**
	 * Verifies that the client has access to the package for the given pricing ID
	 *
	 * @param int $client_id The ID of the client
	 * @param int $pricing_id The ID of the package pricing
	 * @return boolean True if the client can add the package, false otherwise
	 */
	public function validateAllowed($client_id, $pricing_id) {
		if ($pricing_id == null)
			return true;
		return (boolean)$this->Record->select(array("packages.id"))->from("package_pricing")->
			innerJoin("packages", "packages.id", "=", "package_pricing.package_id", false)->
			on("client_packages.client_id", "=", $client_id)->
			leftJoin("client_packages", "client_packages.package_id", "=", "packages.id", false)->
			where("package_pricing.id", "=", $pricing_id)->
			open()->
				where("packages.status", "=", "active")->
				open()->
					orWhere("packages.status", "=", "restricted")->
					Where("client_packages.client_id", "=", $client_id)->
				close()->
			close()->
			fetch();
	}

	/**
	 * Verifies that the given package options are valid
	 *
	 * @param array $config_options An array of key/value pairs where each key is the package option ID and each value is the option value
	 * @param int $pricing_id The package pricing ID
	 * @return boolean True if valid, false otherwise
	 */
	public function validateConfigOptions($config_options, $pricing_id) {
		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));

		foreach ($config_options as $option_id => $value) {

			$result = $this->Record->select(array("package_option_values.*"))->from("package_pricing")->
				innerJoin("package_option", "package_pricing.package_id", "=", "package_option.package_id", false)->
				innerJoin("package_option_group", "package_option_group.option_group_id", "=", "package_option.option_group_id", false)->
				innerJoin("package_options", "package_options.id", "=", "package_option_group.option_id", false)->
				innerJoin("package_option_values", "package_option_values.option_id", "=", "package_options.id", false)->
				where("package_options.id", "=", $option_id)->
				where("package_pricing.id", "=", $pricing_id)->
				open()->
					where("package_option_values.value", "=", $value)->
					orWhere("package_options.type", "=", "quantity")->
				close()->fetch();

			if (!$result)
				return false;

			// Check quantities
			if ($result->min != null && $result->min > $value)
				return false;
			if ($result->max != null && $result->max < $value)
				return false;
			if ($result->step != null && $value != $result->max && ($value - (int)$result->min)%$result->step !== 0)
				return false;
		}
		return true;
	}

	/**
	 * Decrements the package quantity if $check_only is false, otherwise only validates
	 * the quantity could be decremented.
	 *
	 * @param int $quantity The quantity requested
	 * @param int $pricing_id The pricing ID
	 * @param boolean $check_only True to only verify the quantity could be decremented, false otherwise
	 * @param mixed $current_qty The currenty quantity being consumed by the service
	 * @return boolean true if the quantity could be (not necessarily has been) consumed, false otherwise
	 */
	public function decrementQuantity($quantity, $pricing_id, $check_only=true, $current_qty=null) {

		if (!$pricing_id)
			return true;

		// Check if quantity can be deductable
		$consumable = (boolean)$this->Record->select()->from("package_pricing")->
			innerJoin("packages", "package_pricing.package_id", "=", "packages.id", false)->
			where("package_pricing.id", "=", $pricing_id)->
			open()->
				where("packages.qty", ">=", $quantity-(int)$current_qty)->
				orWhere("packages.qty", "=", null)->
			close()->
			fetch();

		if ($consumable && !$check_only) {

			$this->Record->set("packages.qty", "packages.qty-?", false)->
				appendValues(array($quantity-(int)$current_qty))->
				innerJoin("package_pricing", "package_pricing.package_id", "=", "packages.id", false)->
				where("package_pricing.id", "=", $pricing_id)->
				where("packages.qty", ">", 0)->
				update("packages");
		}
		return $consumable;
	}
}
?>