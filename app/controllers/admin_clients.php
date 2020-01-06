<?php
/**
 * Admin Clients Management
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminClients extends AppController {

	/**
	 * @param array $widgets_state The current state of widgets to be displayed in the given view
	 */
	private $widgets_state = array();
	/**
	 * @var string The custom field prefix used in form names to keep them unique and easily referenced
	 */
	private $custom_field_prefix = "custom_field";


	public function preAction() {
		parent::preAction();

		// Require login
		$this->requireLogin();

		$this->uses(array("Clients"));
		$this->helpers(array("Color"));
		Language::loadLang(array("admin_clients"));

		// Sets the page title for this client page
		if (isset($this->get[0])) {
			// Get the client id code, assuming this is the client ID
			if (($client = $this->Clients->get((int)$this->get[0]))) {
				// Attempt to set the page title language
				try {
					$language = Language::_("AdminClients." . Loader::fromCamelCase($this->action ? $this->action : "index") . ".page_title", true, $client->id_code);
					$this->structure->set("page_title", $language);
				}
				catch(Exception $e) {
					// Attempting to set the page title language has failed, likely due to
					// the language definition requiring multiple parameters.
					// Fallback to index. Assume the specific page will set its own page title otherwise.
					$this->structure->set("page_title", Language::_("AdminClients.index.page_title", true));
				}

				$this->Javascript->setFile("date.min.js");
				$this->Javascript->setFile("jquery.datePicker.min.js");
				$this->Javascript->setInline("Date.firstDayOfWeek=" . ($client->settings['calendar_begins'] == "sunday" ? 0 : 1) . ";");
			}
		}
	}

	/**
	 * Browse Clients
	 */
	public function index() {
		$this->uses(array("ClientGroups"));

		// Set current page of results
		$status = (isset($this->get[0]) ? $this->get[0] : "active");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "id_code");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");

		// Set the number of clients of each type
		$status_count = array(
			'active' => $this->Clients->getStatusCount("active"),
			'inactive' => $this->Clients->getStatusCount("inactive"),
			'fraud' => $this->Clients->getStatusCount("fraud")
		);

		$clients = $this->Clients->getList($status, $page, array($sort => $order));
		$total_results = $this->Clients->getListCount($status);

		// Add client group info to each client
		$client_groups = array();
		for ($i=0, $num_clients=count($clients); $i<$num_clients; $i++) {
			if (!array_key_exists($clients[$i]->client_group_id, $client_groups))
				$client_groups[$clients[$i]->client_group_id] = $this->ClientGroups->get($clients[$i]->client_group_id);

			$clients[$i]->group = $client_groups[$clients[$i]->client_group_id];
		}

		$this->set("clients", $clients);
		$this->set("status_count", $status_count);
		$this->set("status", $status);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));

		// Set pagination parameters, set group if available
		$params = array('sort'=>$sort,'order'=>$order);

		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "clients/index/" . $status . "/[p]/",
				'params'=>$params
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));

		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[1]) || isset($this->get['sort']));
	}

	/**
	 * View a specific client profile, may optionally set what content to view within the content view.
	 *
	 * @param string $content The content to set in the content view of the client profile page
	 */
	public function view($content=null) {

		// If this request was made via ajax render only the right container
		if ($content == null && $this->isAjax()) {
			header($this->server_protocol . " 406 AJAX requests not supported by this resource");
			return false;
		}

		$this->uses(array("ClientGroups", "Contacts", "Invoices", "Logs", "PluginManager"));

		// Ensure we have a client ID to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Get all contacts, excluding the primary
		$client->contacts = array_merge($this->Contacts->getAll($client->id, "billing"), $this->Contacts->getAll($client->id, "other"));
		$client->numbers = $this->Contacts->getNumbers($client->contact_id);
		$client->note_count = $this->Clients->getNoteListCount($client->id);
		$client->group = $this->ClientGroups->get($client->client_group_id);

		// Set any client sticky notes
		if ($content == null) {
			$sticky_note_list_vars = array(
				'notes' => $this->Clients->getAllStickyNotes($client->id, Configure::get("Blesta.sticky_notes_max")),
				'number_notes_to_show' => Configure::get("Blesta.sticky_notes_to_show")
			);

			$sticky_note_vars = array(
				'sticky_notes' => $this->partial("admin_clients_stickynote_list", $sticky_note_list_vars)
			);

			$this->set("sticky_notes", $this->partial("admin_clients_stickynotes", $sticky_note_vars));
		}

		// Set the last time this client was logged in successfully
		if (($user_log = $this->Logs->getUserLog($client->user_id, "success"))) {
			$this->components(array("SettingsCollection"));

			// Set last activity time language (in minutes) if within the last 30 minutes
			$user_activity_timestamp = $this->Date->toTime($user_log->date_updated);
			$last_activity = ($this->Date->toTime($this->Logs->dateToUtc(date("c"))) - $user_activity_timestamp)/60;
			$thirty_minutes = 30;

			if ($last_activity < 1)
				$user_log->last_activity = Language::_("AdminClients.view.tooltip_last_activity_now", true);
			elseif ($last_activity == 1)
				$user_log->last_activity = Language::_("AdminClients.view.tooltip_last_activity_minute", true);
			elseif ($last_activity <= $thirty_minutes)
				$user_log->last_activity = Language::_("AdminClients.view.tooltip_last_activity_minutes", true, ceil($last_activity));

			// Set whether GeoIp is enabled
			$system_settings = $this->SettingsCollection->fetchSystemSettings();
			$geo_ip_db_path = $system_settings['uploads_dir'] . "system" . DS . "GeoLiteCity.dat";
			$use_geo_ip = (($system_settings['geoip_enabled'] == "true") && file_exists($geo_ip_db_path));
			if ($use_geo_ip) {
				// Load GeoIP database
				$this->components(array("Net"));
				if (!isset($this->NetGeoIp))
					$this->NetGeoIp = $this->Net->create("NetGeoIp", array($geo_ip_db_path));

				// Set GeoIp data
				$user_log->geo_ip = array('location' => $this->NetGeoIp->getLocation($user_log->ip_address));
			}
		}

		// Set all contact types besides 'primary' and 'other'
		$contact_types = $this->Contacts->getContactTypes();
		$contact_type_ids = $this->Form->collapseObjectArray($this->Contacts->getTypes($this->company_id), "real_name", "id");
		unset($contact_types['primary'], $contact_types['other']);

		$this->set("contact_types", $contact_types + $contact_type_ids);
		$this->set("user_log", $user_log);
		$this->set("client", $client);
		$this->set("content", $content);
		$this->set("status", $this->Clients->getStatusTypes());
		$this->set("default_currency", $client->settings['default_currency']);
		$this->set("multiple_groups", $this->ClientGroups->getListCount($this->company_id) > 0);
		$this->set("delivery_methods", $this->Invoices->getDeliveryMethods($client->id));
		$this->set("plugin_actions", $this->PluginManager->getActions($this->company_id, "action_staff_client", true));
		$this->set("client_account", $this->Clients->getDebitAccount($client->id));
		$this->render("admin_clients_view");
	}

	/**
	 * List services
	 */
	public function services() {
		$this->uses(array("Packages", "Services"));

		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		if (!empty($this->post)) {
			if (($errors = $this->updateServices($client, $this->post))) {
				$this->set("vars", (object)$this->post);
				$this->setMessage("error", $errors);
			} else {
				$term = "AdminClients.!success.services_scheduled_";
				$term .= (isset($this->post['action_type']) && $this->post['action_type'] == "none" ? "uncancel" : "cancel");
				$this->flashMessage("message", Language::_($term, true));
				$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
			}
		}
		
		$status = (isset($this->get[1]) ? $this->get[1] : "active");
		$page = (isset($this->get[2]) ? (int)$this->get[2] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_added");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");

		// Get only parent services
		$services = $this->Services->getList($client->id, $status, $page, array($sort => $order), false);
		$total_results = $this->Services->getListCount($client->id, $status, false);

		// Set the number of services of each type, not including children
		$status_count = array(
			'active' => $this->Services->getStatusCount($client->id, "active", false),
			'canceled' => $this->Services->getStatusCount($client->id, "canceled", false),
			'pending' => $this->Services->getStatusCount($client->id, "pending", false),
			'suspended' => $this->Services->getStatusCount($client->id, "suspended", false),
		);

        // Set the expected service renewal price
        foreach ($services as $service) {
            $service->renewal_price = $this->Services->getRenewalPrice($service->id);
        }
		
		// Build service actions
		$actions = array(
			'schedule_cancellation' => Language::_("AdminClients.services.action.schedule_cancellation", true)
		);

		$this->set("client", $client);
		$this->set("status", $status);
		$this->set("services", $services);
		$this->set("status_count", $status_count);
		$this->set("widget_state", isset($this->widgets_state['services']) ? $this->widgets_state['services'] : null);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		$this->set("actions", $actions);

		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;
		$this->set("periods", $periods);

		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "clients/services/" . $client->id . "/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order)
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));

		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : (isset($this->get[2]) || isset($this->get['sort'])));
		return $this->renderClientView($this->controller . "_" . $this->action);
	}
	
	/**
	 * Updates the given services to change their scheduled cancellation date
	 *
	 * @param stdClass $client The client whose services are being updated
	 * @param array $data An array of POST data including:
	 * 	- service_ids An array of each service ID
	 * 	- action The action to perform, e.g. "schedule_cancelation"
	 * 	- action_type The type of action to perform, e.g. "term", "date"
	 * 	- date The cancel date if the action type is "date"
	 * @return mixed An array of errors, or false otherwise
	 */
	private function updateServices($client, array $data) {
		// Require authorization to update a client's service
		if (!$this->authorized("admin_clients", "editservice")) {
			$this->flashMessage("error", Language::_("AppController.!error.unauthorized_access", true));
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
		}
		
		// Only include service IDs in the list
		$service_ids = array();
		if (isset($data['service_ids'])) {
			foreach ((array)$data['service_ids'] as $service_id) {
				if (is_numeric($service_id)) {
					$service_ids[] = $service_id;
				}
			}
		}
		
		$data['service_ids'] = $service_ids;
		$data['date'] = (isset($data['date']) ? $data['date'] : null);
		$data['action_type'] = (isset($data['action_type']) ? $data['action_type'] : null);
		$data['action'] = (isset($data['action']) ? $data['action'] : null);
		$errors = false;
		
		switch ($data['action']) {
			case "schedule_cancellation":
				// Ensure the scheduled date is in the future
				if ($data['action_type'] == "date" &&
					(!$data['date'] || $this->Date->cast($data['date'], "Ymd") < $this->Date->cast(date("c"), "Ymd"))) {
					$errors = array('error' => array('date' => Language::_("AdminClients.!error.future_cancel_date", true)));
				} else {
					// Update the services
					$vars = array(
						'date_canceled' => ($data['action_type'] == "term" ? "end_of_term" : $data['date'])
					);
					
					// Cancel or uncancel each service
					foreach ($data['service_ids'] as $service_id) {
						if ($data['action_type'] == "none") {
							$this->Services->unCancel($service_id);
						} else {
							$this->Services->cancel($service_id, $vars);
						}
						
						if (($errors = $this->Services->errors())) {
							break;
						}
					}
				}
				break;
		}
		
		return $errors;
	}

	/**
	 * Service count
	 */
	public function serviceCount() {
		$this->uses(array("Services"));

		$client_id = isset($this->get[0]) ? $this->get[0] : null;
		$status = isset($this->get[1]) ? $this->get[1] : "active";

		echo $this->Services->getStatusCount($client_id, $status);
		return false;
	}

	/**
	 * List invoices
	 */
	public function invoices() {
		$this->uses(array("Invoices","Contacts"));

		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Get current page of results
		$status = (isset($this->get[1]) ? $this->get[1] : "open");
		$page = (isset($this->get[2]) ? (int)$this->get[2] : 1);

		// Send invoices to emails specified
		if (!empty($this->post) && isset($this->post['invoice_id']) && !empty($this->post['invoice_id'])) {

			$this->components(array("InvoiceDelivery"));

			// Deliver the selected invoices
			$hostname = isset(Configure::get("Blesta.company")->hostname) ? Configure::get("Blesta.company")->hostname : "";

			// Set the email template to use
			$email_template = "invoice_delivery_unpaid";
			if ($status == "closed")
				$email_template = "invoice_delivery_paid";

			// Set the options for these invoices, but use the tags set in InvoiceDelivery::deliverInvoices()
			$options = array(
				'email_template' => $email_template,
				'base_client_url' => $this->Html->safe($hostname . $this->client_uri)
			);
			$this->InvoiceDelivery->deliverInvoices($this->post['invoice_id'], $this->post['delivery_method'], (isset($this->post[$this->post['delivery_method']]) ? $this->post[$this->post['delivery_method']] : null), $this->Session->read("blesta_staff_id"), $options);

			if (($errors = $this->InvoiceDelivery->errors())) {
				// Error
				$this->flashMessage("error", $errors);
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminClients.!success.invoices_delivered", true));

				// Add a new delivery method record for each invoice and mark them sent
				foreach ($this->post['invoice_id'] as $invoice_id) {
					$delivery_id = $this->Invoices->addDelivery($invoice_id, array('method' => $this->post['delivery_method']), $client->id);

					if ($delivery_id)
						$this->Invoices->delivered($delivery_id);
				}
			}

			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
		}

		// Get invoices for this client
		if ($status == "recurring") {
			$sort = (isset($this->get['sort']) ? $this->get['sort'] : "id");
			$order = (isset($this->get['order']) ? $this->get['order'] : "desc");

			$invoices = $this->Invoices->getRecurringList($client->id, $page, array($sort => $order));
			$total_results = $this->Invoices->getRecurringListCount($client->id);
		}
		else {
			$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_due");
			$order = (isset($this->get['order']) ? $this->get['order'] : "desc");

			$invoices = $this->Invoices->getList($client->id, $status, $page, array($sort => $order));
			$total_results = $this->Invoices->getListCount($client->id, $status);
		}

		// Set the number of invoices of each type
		$status_count = array(
			'open' => $this->Invoices->getStatusCount($client->id, "open"),
			'closed' => $this->Invoices->getStatusCount($client->id, "closed"),
			'draft' => $this->Invoices->getStatusCount($client->id, "draft"),
			'void' => $this->Invoices->getStatusCount($client->id, "void"),
			'recurring' => $this->Invoices->getRecurringCount($client->id),
			'pending' => $this->Invoices->getStatusCount($client->id, "pending")
		);


		// Set the delivery methods
		$delivery_methods = $this->Invoices->getDeliveryMethods($client->id);
		foreach ($delivery_methods as &$method)
			$method = Language::_("AdminClients.invoices.method_deliverselected", true, $method);

		// Set invoices that may be checked and sent by status
		$this->set("deliverable_invoice_statuses", array("open", "closed", "void"));
		$this->set("status", $status);
		$this->set("delivery_methods", $delivery_methods);
		$this->set("client", $client);
		$this->set("contact_fax", $this->Contacts->getNumbers($client->contact_id, "fax"));
		$this->set("invoices", $invoices);
		$this->set("status_count", $status_count);
		$this->set("widget_state", isset($this->widgets_state['invoices']) ? $this->widgets_state['invoices'] : null);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));

		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "clients/invoices/" . $client->id . "/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order)
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));

		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : (isset($this->get[2]) || isset($this->get['sort'])));
		return $this->renderClientView($this->controller . "_" . $this->action);
	}

	/**
	 * Invoice count
	 */
	public function invoiceCount() {
		$this->uses(array("Invoices"));

		$client_id = isset($this->get[0]) ? $this->get[0] : null;
		$status = isset($this->get[1]) ? $this->get[1] : "active";

		echo $this->Invoices->getStatusCount($client_id, $status);
		return false;
	}

	/**
	 * AJAX request for all transactions an invoice has applied
	 */
	public function invoiceApplied() {
		$this->uses(array("Invoices","Transactions"));

		if (!isset($this->get[0]) || !isset($this->get[1]) || !($client = $this->Clients->get((int)$this->get[0]))) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}

		$invoice = $this->Invoices->get($this->get[1]);

		// Ensure the invoice belongs to the client and this is an ajax request
		if (!$this->isAjax() || !$invoice || $invoice->client_id != $this->get[0]) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}


		$vars = array(
			'client'=>$client,
			'applied'=>$this->Transactions->getApplied(null, $this->get[1]),
			// Holds the name of all of the transaction types
			'transaction_types'=>$this->Transactions->transactionTypeNames()
		);

		// Send the template
		echo $this->partial("admin_clients_invoiceapplied", $vars);

		// Render without layout
		return false;
	}

	/**
	 * List transactions
	 */
	public function transactions() {
		$this->uses(array("Transactions"));

		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients");

		// Set the number of transactions of each type
		$status_count = array(
			'approved' => $this->Transactions->getStatusCount($client->id),
			'declined' => $this->Transactions->getStatusCount($client->id, "declined"),
			'void' => $this->Transactions->getStatusCount($client->id, "void"),
			'error' => $this->Transactions->getStatusCount($client->id, "error"),
			'pending' => $this->Transactions->getStatusCount($client->id, "pending"),
			'refunded' => $this->Transactions->getStatusCount($client->id, "refunded"),
			'returned' => $this->Transactions->getStatusCount($client->id, "returned")
		);

		$status = (isset($this->get[1]) ? $this->get[1] : "approved");
		$page = (isset($this->get[2]) ? (int)$this->get[2] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_added");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");

		$total_results = $this->Transactions->getListCount($client->id, $status);
		$this->set("transactions", $this->Transactions->getList($client->id, $status, $page, array($sort => $order)));
		$this->set("client", $client);
		$this->set("status", $status);
		$this->set("status_count", $status_count);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		$this->set("widget_state", isset($this->widgets_state['transactions']) ? $this->widgets_state['transactions'] : null);
		// Holds the name of all of the transaction types
		$this->set("transaction_types", $this->Transactions->transactionTypeNames());
		// Holds the name of all of the transaction status values
		$this->set("transaction_status", $this->Transactions->transactionStatusNames());

		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "clients/transactions/" . $client->id . "/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order),
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));

		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : (isset($this->get[2]) || isset($this->get['sort'])));
		return $this->renderClientView($this->controller . "_" . $this->action);
	}

	/**
	 * Transaction count
	 */
	public function transactionCount() {
		$this->uses(array("Transactions"));

		$client_id = isset($this->get[0]) ? $this->get[0] : null;
		$status = isset($this->get[1]) ? $this->get[1] : "approved";

		echo $this->Transactions->getStatusCount($client_id, $status);
		return false;
	}

	/**
	 * AJAX request for all invoices a transaction has been applied to
	 */
	public function transactionApplied() {
		$this->uses(array("Transactions"));

		if (!isset($this->get[0]) || !isset($this->get[1]) || !($client = $this->Clients->get((int)$this->get[0]))) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}

		$transaction = $this->Transactions->get($this->get[1]);

		// Ensure the transaction belongs to the client and this is an ajax request
		if (!$this->isAjax() || !$transaction || $transaction->client_id != $this->get[0]) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}


		$vars = array(
			'client'=>$client,
			'applied'=>$this->Transactions->getApplied($this->get[1])
		);

		// Send the template
		echo $this->partial("admin_clients_transactionapplied", $vars);

		// Render without layout
		return false;
	}

	/**
	 * View mail log
	 */
	public function emails() {
		$this->uses(array("Emails", "Staff"));

		// Get client
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Set current page of results
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_sent");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");

		$logs = $this->Clients->getMailLogList($client->id, $page, array($sort=>$order));

		// Format CC addresses, if available
		if ($logs) {
			// Fetch email signatures
			$this->uses(array("Emails"));
			$email_signatures = $this->Emails->getAllSignatures($this->company_id);
			$signatures = array();
			foreach ($email_signatures as $signature)
				$signatures[] = $signature->text;

			for ($i=0, $num_logs = count($logs); $i<$num_logs; $i++) {
				// Convert email HTML to text if necessary
				$logs[$i]->body_text = $this->getTextFromHtml($logs[$i]->body_html, $logs[$i]->body_text, $signatures);

				// Format all CC addresses from CSV to array
				$cc_addresses = $logs[$i]->cc_address;
				$logs[$i]->cc_address = array();
				foreach(explode(",", $cc_addresses) as $address) {
					if (!empty($address))
						$logs[$i]->cc_address[] = $address;
				}
			}
		}

		$this->set("logs", $logs);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		$this->set("client", $client);

		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $this->Clients->getMailLogListCount($client->id),
				'uri'=>$this->base_uri . "clients/emails/" . $client->id . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order),
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));


		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get[1]) || isset($this->get['sort']));
		return $this->renderClientView($this->controller . "_" . $this->action);
	}

	/**
	 * Converts the HTML given to text iff no text is currently set
	 *
	 * @param string $html The current HTML
	 * @param string $text The current text (optional)
	 * @param mixed $remove_text A string value, or numerically-indexed array of strings to remove from $text before attempting the conversion (optional)
	 * @return string The updated text
	 */
	private function getTextFromHtml($html, $text="", $remove_text="") {
		$text = ($text ? $text : "");

		// Remove content from the text to ignore
		if (!empty($remove_text))
			$text = str_replace($remove_text, "", $text);
		$text = trim($text);

		// Convert HTML to text if no text version is available
		if (empty($text) && !empty($html)) {
			if (!isset($this->Html2text)) {
				$this->helpers(array("TextParser"));
				$this->Html2text = $this->TextParser->create("html2text");
			}

			$this->Html2text->set_html($html);
			$text = $this->Html2text->get_text();
		}
		return $text;
	}

	/**
	 * List notes
	 */
	public function notes() {
		// Redirect if invalid client ID
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients");

		// Get page and sort order
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_added");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");

		$this->set("client", $client);
		$this->set("notes", $this->Clients->getNoteList($client->id, $page, array($sort=>$order)));
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));

		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $this->Clients->getNoteListCount($client->id),
				'uri'=>$this->base_uri . "clients/notes/" . $client->id . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order),
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));

		// Render the request if ajax
		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : (isset($this->get[1]) || isset($this->get['sort'])));
		return $this->renderClientView($this->controller . "_" . $this->action);
	}

	/**
	 * Add note
	 */
	public function addNote() {
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$vars = new stdClass();

		if (!empty($this->post)) {
			// Set unset checkboxes
			if (!isset($this->post['stickied']))
				$this->post['stickied'] = 0;

			$this->Clients->addNote($client->id, $this->Session->read("blesta_staff_id"), $this->post);

			if (($errors = $this->Clients->errors())) {
				// Error
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminClients.!success.note_added", true));
				$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
			}
		}

		$this->set("vars", $vars);
		$this->view($this->view->fetch("admin_clients_addnote"));
	}

	/**
	 * Edit note
	 */
	public function editNote() {
		// Ensure the note given belongs to this client
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])) ||
		    !isset($this->get[1]) || !($note = $this->Clients->getNote((int)$this->get[1])) ||
			($client->id != $note->client_id))
			$this->redirect($this->base_uri . "clients/view/");

		$vars = $note;

		if (!empty($this->post)) {
			// Set unset checkboxes
			if (!isset($this->post['stickied']))
				$this->post['stickied'] = 0;

			$this->Clients->editNote($note->id, $this->post);

			if (($errors = $this->Clients->errors())) {
				// Error
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminClients.!success.note_updated", true));
				$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
			}
		}

		$this->set("vars", $vars);
		$this->view($this->view->fetch("admin_clients_editnote"));
	}

	/**
	 * Delete Note
	 */
	public function deleteNote() {
		// Get client and note, ensuring they exist
		if (!isset($this->get[0]) || !isset($this->get[1]) || !($client = $this->Clients->get((int)$this->get[0])) ||
			!($note = $this->Clients->getNote((int)$this->get[1])) || ($client->id != $note->client_id))
			$this->redirect($this->base_uri . "clients/");

		// Delete the note
		$this->Clients->deleteNote($note->id);
		$this->flashMessage("message", Language::_("AdminClients.!success.note_deleted", true));

		$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
	}

	/**
	 * Displays/removes sticky notes
	 */
	public function stickyNotes() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			exit;

		// Remove a note from being stickied
		if (isset($this->get[1])) {
			$note = $this->Clients->getNote((int)$this->get[1]);

			// Ensure the note belongs to this client
			if (empty($note) || ($note->client_id != $client->id))
				exit;

			$this->Clients->unstickyNote($note->id);

			$sticky_notes = $this->Clients->getAllStickyNotes($client->id, Configure::get("Blesta.sticky_notes_max"));
			$sticky_note_vars = array(
				'notes' => $sticky_notes,
				'number_notes_to_show' => Configure::get("Blesta.sticky_notes_to_show"),
				'show_more' => (!empty($this->post['show_more']) ? $this->post['show_more'] : "false")
			);
			$response = new stdClass();

			// Set a view for sticky notes
			if (!empty($sticky_notes))
				$response->view = $this->partial("admin_clients_stickynote_list", $sticky_note_vars);

			// JSON encode the AJAX response
			$this->outputAsJson($response);
			return false;
		}

		$this->set("notes", $this->Clients->getAllStickyNotes($client->id, Configure::get("Blesta.sticky_notes_max")));
		$this->view($this->view->fetch("admin_clients_stickynotes"));
	}

	/**
	 * Prompts to download a vCard of the client's address information
	 */
	public function vCard() {
		// Ensure a client ID is given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$this->components(array("Vcard"));
		$this->uses(array("Contacts"));

		$data = array(
			'first_name' => $client->first_name,
			'last_name' => $client->last_name,
			'company' => $client->company,
			'email1' => $client->email
		);

		if ($client->company != "") {
			$data['work_address'] = $client->address1 . " " . $client->address2;
			$data['work_city'] = $client->city;
			$data['work_state'] = $client->state;
			$data['work_postal_code'] = $client->zip;
			$data['work_country'] = $client->country;
		}
		else {
			$data['home_address'] = $client->address1 . " " . $client->address2;
			$data['home_city'] = $client->city;
			$data['home_state'] = $client->state;
			$data['home_postal_code'] = $client->zip;
			$data['home_country'] = $client->country;
		}

		// Get any phone/fax numbers
		$contact_numbers = $this->Contacts->getNumbers($client->contact_id);

		// Set any contact numbers (only the first of a specific type found)
		foreach ($contact_numbers as $contact_number) {
			switch ($contact_number->location) {
				case "home":
					// Set home phone number
					if (!isset($data['home_tel']) && $contact_number->type == "phone")
						$data['home_tel'] = $contact_number->number;
					break;
				case "work":
					// Set work phone/fax number
					if (!isset($data['office_tel']) && $contact_number->type == "phone")
						$data['office_tel'] = $contact_number->number;
					elseif (!isset($data['fax_tel']) && $contact_number->type == "fax")
						$data['fax_tel'] = $contact_number->number;
					break;
				case "mobile":
					// Set mobile phone number
					if (!isset($data['cell_tel']) && $contact_number->type == "phone")
						$data['cell_tel'] = $contact_number->number;
					break;
			}
		}

		$file_name = $client->first_name . "_" . $client->last_name;

		// Create the vCard and stream it to the browser
		$vcard = $this->Vcard->create($data, true, $file_name);
		return false;
	}

	/**
	 * Add New Client
	 */
	public function add() {
		$this->uses(array("ClientGroups", "Companies", "Contacts", "Countries", "Currencies", "Languages", "States", "Users"));
		$this->components(array("SettingsCollection"));
		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Get company settings
		$company_settings = $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id);

		// Set default currency, country, and language settings from this company
		$vars = new stdClass();
		$vars->country = $company_settings['country'];
		$vars->default_currency = $company_settings['default_currency'];
		$vars->language = $company_settings['language'];

		// Create a new client
		if (!empty($this->post)) {
			$vars = $this->post;
			$vars['confirm_password'] = $vars['new_password'];
			$vars['settings'] = array(
				'username_type' => $vars['username_type'],
				'tax_exempt' => !empty($vars['tax_exempt']) ? $vars['tax_exempt'] : "false",
				'tax_id' => $vars['tax_id'],
				'default_currency' => $vars['default_currency'],
				'language' => $vars['language']
			);
			$vars['numbers'] = $this->ArrayHelper->keyToNumeric($vars['numbers']);
			foreach ($vars as $key => $value) {
				if (substr($key, 0, 12) == "custom_field") {
					$vars['custom'][substr($key, 12)] = $value;
				}
			}

			// Do not send the welcome email if not set to
			$vars['send_registration_email'] = (empty($vars['send_registration_email']) ? "false" : "true");

			$this->Clients->create($vars);

			if (($errors = $this->Clients->errors())) {
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				$this->flashMessage("message", Language::_("AdminClients.!success.client_added", true));
				$this->redirect($this->base_uri . "clients/");
			}
		}

		// Get all client groups
		$client_groups = $this->Form->collapseObjectArray($this->ClientGroups->getAll($this->company_id), "name", "id");

		// Set the current client group ID selected for displaying custom fields
		$client_group_id = (isset($vars->client_group_id) ? $vars->client_group_id : null);

		// Set partial for custom fields only if there are some to display
		if ($client_group_id != null) {
			$custom_fields = $this->Clients->getCustomFields($this->company_id, $client_group_id);
			// Swap key/value pairs for "Select" option custom fields (to display)
			foreach ($custom_fields as &$field) {
				if ($field->type == "select" && is_array($field->values))
					$field->values = array_flip($field->values);
			}

			$partial_custom_fields = array(
				'vars' => $vars,
				'custom_fields' => $custom_fields,
				'custom_field_prefix' => $this->custom_field_prefix
			);
			$this->set("custom_fields", $this->partial("admin_clients_custom_fields", $partial_custom_fields));
		}

		// Set partial for phone numbers
		$partial_vars = array(
			'numbers'=>(isset($vars->numbers) ? $vars->numbers : array()),
			'number_types'=>$this->Contacts->getNumberTypes(),
			'number_locations'=>$this->Contacts->getNumberLocations()
		);
		$this->set("partial_phones", $this->partial("admin_clients_phones", $partial_vars));

		// Set form fields
		$this->set("countries", $this->Form->collapseObjectArray($this->Countries->getList(), array("name", "alt_name"), "alpha2", " - "));
		$this->set("states", $this->Form->collapseObjectArray($this->States->getList($vars->country), "name", "code"));
		$this->set("currencies", $this->Currencies->getAll($this->company_id));
		$this->set("languages", $this->Form->collapseObjectArray($this->Languages->getAll($this->company_id), "name", "code"));
		$this->set("status", $this->Clients->getStatusTypes());
		$this->set("client_groups", $client_groups);
		$this->set("vars", $vars);
	}

	/**
	 * Edit Client
	 */
	public function edit() {
		$this->uses(array("ClientGroups", "Contacts", "Countries", "Currencies", "Languages", "States", "Users"));
		$this->components(array("SettingsCollection"));
		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Get client or redirect if not given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Fetch this user
		$user = $this->Users->get($client->user_id);

		$vars = array();

		// Update client
		if (!empty($this->post)) {
			// Begin a new transaction
			$this->Clients->begin();

			// Update the user authentication
			$user_vars = array(
				'username' => (isset($this->post['settings']['username_type']) && $this->post['settings']['username_type'] == "email") ? $this->post['email'] : (isset($this->post['username']) ? $this->post['username'] : ""),
				'new_password' => $this->post['new_password'],
				'confirm_password' => $this->post['new_password']
			);
			if (isset($this->post['two_factor_mode']))
				$user_vars['two_factor_mode'] = "none";

			// Remove the new password if not given
			if (empty($this->post['new_password']))
				unset($user_vars['new_password'], $user_vars['confirm_password']);

			$this->Users->edit($user->id, $user_vars);
			$user_errors = $this->Users->errors();

			// Update the client
			$this->post['id_code'] = $client->id_code;
			$this->post['user_id'] = $client->user_id;
			$this->Clients->edit($client->id, $this->post);
			$client_errors = $this->Clients->errors();

			// Update the client custom fields
			$custom_field_errors = $this->addCustomFields($client->id, $this->post);

			// Update client settings
			$settings = array(
				'username_type' => $this->post['settings']['username_type'],
				'tax_exempt' => !empty($this->post['tax_exempt']) ? $this->post['tax_exempt'] : "false",
				'tax_id' => $this->post['tax_id'],
				'default_currency' => $this->post['default_currency'],
				'language' => $this->post['language'],
				'inv_address_to' => $this->post['inv_address_to']
			);

			$this->Clients->setSettings($client->id, $settings, array_keys($settings));

			$vars = $this->post;

			// Format the phone numbers
			$vars['numbers'] = $this->ArrayHelper->keyToNumeric($this->post['numbers']);

			// Update the contact
			unset($vars['user_id']);
			$this->Contacts->edit($client->contact_id, $vars);
			$contact_errors = $this->Contacts->errors();

			$errors = array_merge(($client_errors ? $client_errors : array()), ($contact_errors ? $contact_errors : array()), ($user_errors ? $user_errors : array()), ($custom_field_errors ? $custom_field_errors : array()));

			if (!empty($errors)) {
				// Error, rollback
				$this->Clients->rollBack();

				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success, commit
				$this->Clients->commit();

				$this->flashMessage("message", Language::_("AdminClients.!success.client_updated", true));
				$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
			}
		}

		// Set this client
		if (empty($vars)) {
			$vars = $client;

			// Set username
			$vars->username = $user->username;

			// Set client phone numbers formatted for HTML
			$vars->numbers = $this->ArrayHelper->numericToKey($this->Contacts->getNumbers($client->contact_id));

			// Set client custom field values
			$field_values = $this->Clients->getCustomFieldValues($client->id);
			foreach ($field_values as $field) {
				$vars->{$this->custom_field_prefix . $field->id} = $field->value;
			}
		}

		// Get all client contacts for which to make invoices addressable to (primary and billing contacts)
		$contacts = array_merge($this->Contacts->getAll($client->id, "primary"), $this->Contacts->getAll($client->id, "billing"));

		// Set states and countries for drop downs
		$this->set("countries", $this->Form->collapseObjectArray($this->Countries->getList(), array("name", "alt_name"), "alpha2", " - "));
		$this->set("states", $this->Form->collapseObjectArray($this->States->getList($vars->country), "name", "code"));
		$this->set("currencies", $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code"));
		$this->set("languages", $this->Form->collapseObjectArray($this->Languages->getAll($this->company_id), "name", "code"));
		$this->set("contacts", $this->Form->collapseObjectArray($contacts, array("first_name", "last_name"), "id", " "));
		$this->set("client_groups", $this->Form->collapseObjectArray($this->ClientGroups->getAll($this->company_id), "name", "id"));
		$this->set("client", $client);
		$this->set("vars", $vars);

		// Set partial for custom fields
		$custom_fields = $this->Clients->getCustomFields($this->company_id, $client->client_group_id);
		// Swap key/value pairs for "Select" option custom fields (to display)
		foreach ($custom_fields as &$field) {
			if ($field->type == "select" && is_array($field->values))
				$field->values = array_flip($field->values);
		}

		$partial_custom_fields = array(
			'vars' => $vars,
			'custom_fields' => $custom_fields,
			'custom_field_prefix' => $this->custom_field_prefix
		);
		$this->set("custom_fields", $this->partial("admin_clients_custom_fields", $partial_custom_fields));

		// Set partial for phone numbers
		$partial_phone = array(
			'numbers'=>$vars->numbers,
			'number_types'=>$this->Contacts->getNumberTypes(),
			'number_locations'=>$this->Contacts->getNumberLocations()
		);
		$this->set("partial_phones", $this->partial("admin_clients_phones", $partial_phone));

		if ($this->isAjax())
			return false;

		$this->view($this->view->fetch("admin_clients_edit"));
	}

	/**
	 * Delete Client
	 */
	public function delete() {

		// Ensure a valid client was given
		if (!isset($this->post['id']) || !($client = $this->Clients->get($this->post['id'])) ||
			($client->company_id != $this->company_id))
			$this->redirect($this->base_uri . "clients/");

		// Delete client
		$this->Clients->delete($client->id);

		if (($errors = $this->Clients->errors())) {
			// Error
			$this->flashMessage("error", $errors);
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
		}
		else {
			// Success
			$this->flashMessage("message", Language::_("AdminClients.!success.client_deleted", true));
		}

		$this->redirect($this->base_uri . "clients/");
	}

	/**
	 * AJAX request for retrieving all custom fields for a client group
	 */
	public function getCustomFields() {
		if (!isset($this->get['group_id']) || ($custom_fields = $this->Clients->getCustomFields($this->company_id, (int)$this->get['group_id'])) === false) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}

		$custom_field_vars = new stdClass();

		// Get client-specific custom field values
		if (isset($this->get['client_id']) && ($client = $this->Clients->get((int)$this->get['client_id']))) {
			// Fetch the client custom field values already set for this client
			$field_values = $this->Clients->getCustomFieldValues($client->id);
			foreach ($field_values as $field) {
				$custom_field_vars->{$this->custom_field_prefix . $field->id} = $field->value;
			}
		}

		// Swap key/value pairs for "Select" option custom fields (to display)
		foreach ($custom_fields as &$field) {
			if ($field->type == "select" && is_array($field->values))
				$field->values = array_flip($field->values);
		}

		$vars = array(
			'vars' => $custom_field_vars,
			'custom_fields' => $custom_fields,
			'custom_field_prefix' => $this->custom_field_prefix
		);

		// Send the template
		$response = new stdClass();
		$response->custom_fields = $this->partial("admin_clients_custom_fields", $vars);

		// JSON encode the AJAX response
		$this->outputAsJson($response);
		return false;
	}

	/**
	 * Attempts to add custom fields to a client
	 *
	 * @param int $client_id The client ID to add custom fields for
	 * @param array $vars The post data, containing custom fields
	 * @return mixed An array of errors, or false if none exist
	 * @see Clients::add(), Clients::edit()
	 */
	private function addCustomFields($client_id, $vars=array()) {
		// Get the client's current custom fields
		$client_custom_fields = $this->Clients->getCustomFieldValues($client_id);

		// Create a list of custom field IDs to update
		$custom_fields = $this->Clients->getCustomFields($this->company_id, (isset($vars['client_group_id']) ? $vars['client_group_id'] : null));
		$custom_field_ids = array();
		foreach ($custom_fields as $field)
			$custom_field_ids[] = $field->id;
		unset($field);

		// Build a list of given custom fields to update
		$custom_fields_set = array();
		foreach ($vars as $field => $value) {
			// Get the custom field ID from the name
			$field_id = preg_replace("/" . $this->custom_field_prefix . "/", "", $field, 1);

			// Set the custom field
			if ($field_id != $field)
				$custom_fields_set[$field_id] = $value;
		}
		unset($field, $value);

		// Set every custom field available, even if it's not given, for validation
		$deletable_fields = array();
		foreach ($custom_field_ids as $field_id) {
			// Set a temp value for validation purposes and mark it to be deleted
			if (!isset($custom_fields_set[$field_id])) {
				$custom_fields_set[$field_id] = "";
				// Set this field to be deleted
				$deletable_fields[] = $field_id;
			}
		}
		unset($field_id);

		// Attempt to add/update each custom field
		$temp_field_errors = array();
		foreach ($custom_fields_set as $field_id => $value) {
			$this->Clients->setCustomField($field_id, $client_id, $value);
			$temp_field_errors[] = $this->Clients->errors();
		}
		unset($field_id, $value);

		// Delete the fields that were not given
		foreach ($deletable_fields as $field_id)
			$this->Clients->deleteCustomFieldValue($field_id, $client_id);

		// Combine multiple custom field errors together
		$custom_field_errors = array();
		for ($i=0, $num_errors=count($temp_field_errors); $i<$num_errors; $i++) {
			// Skip any "error" that is not an array already
			if (!is_array($temp_field_errors[$i]))
				continue;

			// Change the keys of each custom field error so we can display all of them at once
			$error_keys = array_keys($temp_field_errors[$i]);
			$temp_error = array();

			foreach ($error_keys as $key)
				$temp_error[$key . $i] = $temp_field_errors[$i][$key];

			$custom_field_errors = array_merge($custom_field_errors, $temp_error);
		}

		return (empty($custom_field_errors) ? false : $custom_field_errors);
	}

	/**
	 * AJAX quick update client to set status, invoice method, auto debit status, or auto suspension status
	 */
	public function quickUpdate() {
		// Ensure a client is given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$response = array();

		switch ($this->get[1]) {
			// Set the client's status (active/inactive, etc.)
			case "status":
				$status_types = $this->Clients->getStatusTypes();
				$keys = array_keys($status_types);
				$num_keys = count($keys);
				for ($i=0; $i<$num_keys; $i++) {
					if ($keys[$i] == $client->status) {
						$index = $keys[($i+1)%$num_keys];
						break;
					}
				}
				// Update the status
				$this->Clients->edit($client->id, array('status'=>$index));

				$response = array('class_name'=>"status_box " . $index, 'text'=>$status_types[$index]);
				break;
			// Set the client's invoice delivery method
			case "inv_method":
				$this->uses(array("Invoices"));

				$delivery_methods = $this->Invoices->getDeliveryMethods($client->id);

				$keys = array_keys($delivery_methods);

				$i=0;
				$num_methods = count($keys);
				for (; $i<$num_methods; $i++) {
					if ($keys[$i] == $client->settings['inv_method'])
						break;
				}
				$index = $keys[($i+1)%$num_methods];

				$this->Clients->setSetting($client->id, "inv_method", $index);

				$response = array('class_name'=>$index, 'text'=>$delivery_methods[$index]);
				break;
			// Set whether the client should be automatically suspended for non-payment
			case "autosuspend":
			// Set whether the client should be automatically debited when payment is due
			case "autodebit":
				$options = array('true'=>"enable", 'false'=>"disable");
				$keys = array_keys($options);
				$num_keys = count($keys);
				for ($i=0; $i<$num_keys; $i++) {
					if ($keys[$i] == $client->settings[$this->get[1]]) {
						$index = $keys[($i+1)%$num_keys];
						break;
					}
				}
				// Update the setting
				$this->Clients->setSetting($client->id, $this->get[1], $index);

				$response = array('class_name'=>$options[$index], 'text'=>($index == "true" ? Language::_("AdminClients.view.setting_enabled", true) : Language::_("AdminClients.view.setting_disabled", true)));

				if ($this->get[1] == "autodebit") {
					// Get the current account set for autodebiting
					$client_account = $this->Clients->getDebitAccount($client->id);

					if ((!$client_account && $options[$index] == "enable"))
						$response['tooltip'] = true;
				}
				if ($this->get[1] == "autosuspend") {
					$response['autosuspend_date'] = null;
					if (isset($client->settings['autosuspend_date']) && $options[$index] == "enable") {
						$response['autosuspend_date'] = true;
					}
				}

				break;
			// Set whether the client can be sent payment due notices
			case "send_payment_notices":
				$options = array('true'=>"enable", 'false'=>"disable");
				$keys = array_keys($options);
				$num_keys = count($keys);
				for ($i=0; $i<$num_keys; $i++) {
					if ($keys[$i] == $client->settings['send_payment_notices'])
						break;
				}
				$value = $keys[($i+1)%$num_keys];

				// Update the setting
				$this->Clients->setSetting($client->id, "send_payment_notices", $value);

				$response = array('class_name'=>$options[$value], 'text'=>($value == "true" ? Language::_("AdminClients.view.setting_enabled", true) : Language::_("AdminClients.view.setting_disabled", true)));
				break;
		}

		// If not an AJAX request, reload the client profile page
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "clients/view/" . $client->id);

		echo $this->Json->encode($response);
		return false;
	}

	/**
	 * Modal for viewing/setting delay suspension date
	 */
	public function delaySuspension() {
		// Ensure a client is given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$vars = new stdClass();
		$vars->autosuspend_date = isset($client->settings['autosuspend_date']) ? $this->Date->cast($client->settings['autosuspend_date'], "Y-m-d") : null;
		if (!empty($this->post)) {
			if (trim($this->post['autosuspend_date']) == "") {
				$this->Clients->unsetSetting($client->id, "autosuspend_date");
			}
			else {
				$this->Clients->setSetting($client->id, "autosuspend_date", $this->Clients->dateToUtc($this->post['autosuspend_date'], "c"));
			}
			$this->redirect($this->base_uri . "clients/view/" . $client->id);
		}

		echo $this->partial("admin_client_delaysuspension", compact("vars"));
		return false;
	}

	/**
	 * Email Client
	 */
	public function email() {
		$this->uses(array("Contacts", "Emails", "Logs"));

		// Get client ID
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Get an email to pre-populate with (resend an email)
		if (isset($this->get[1])) {
			$email = $this->Logs->getEmail((int)$this->get[1]);

			if ($email && $email->to_client_id == $client->id) {
				// Set vars of email to resend
				$vars = (object)array(
					'email_field' => "email_other",
					'recipient_other' => $email->to_address,
					'from_name' => $email->from_name,
					'from' => $email->from_address,
					'subject' => $email->subject,
					'message' => $email->body_text,
					'html' => $email->body_html
				);
			}
		}

		// Send the email
		if (!empty($this->post)) {
			if (isset($this->post['email_field']) && $this->post['email_field'] == "email_selected")
				$this->post['to'] = $this->Html->ifSet($this->post['recipient']);
			else
				$this->post['to'] = $this->Html->ifSet($this->post['recipient_other']);

			// Attempt to send the email
			$this->Emails->sendCustom($this->Html->ifSet($this->post['from']), $this->Html->ifSet($this->post['from_name']),
				$this->Html->ifSet($this->post['to']), $this->Html->ifSet($this->post['subject']),
				array('html'=>$this->Html->ifSet($this->post['html']), 'text'=>$this->Html->ifSet($this->post['text'])),
				null, null, null, null, array('to_client_id'=>$client->id, 'from_staff_id'=>$this->Session->read("blesta_staff_id")));

			if (($errors = $this->Emails->errors()))
				$this->setMessage("error", $errors);
			else {
				$this->flashMessage("message", Language::_("AdminClients.!success.email_sent", true));
				$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
			}
			$vars = (object)$this->post;
		}

		// Default to use staff email as from address
		if (!isset($vars)) {
			$vars = new stdClass();

			$this->uses(array("Staff"));
			$staff = $this->Staff->get($this->Session->read("blesta_staff_id"));

			if ($staff) {
				$vars->from_name = $this->Html->concat(" ", $staff->first_name, $staff->last_name);
				$vars->from = $staff->email;
			}
		}

		$this->set("contacts", $this->Form->collapseObjectArray($this->Contacts->getList($client->id), array("first_name", "last_name", "email"), "email", " "));
		$this->set("vars", $vars);

		// Include WYSIWYG
		$this->Javascript->setFile("ckeditor/ckeditor.js", "head", VENDORWEBDIR);
		$this->Javascript->setFile("ckeditor/adapters/jquery.js", "head", VENDORWEBDIR);

		$this->view($this->view->fetch("admin_clients_email"));
	}

	/**
	 * Login as the client
	 */
	public function loginAsClient() {

		// Get client ID
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$this->Session->write("blesta_client_id", $client->id);
		$this->redirect($this->client_uri);
	}

	/**
	 * Logout as the client
	 */
	public function logoutAsClient() {

		$client_id = $this->Session->read("blesta_client_id");
		$this->Session->clear("blesta_client_id");

		if ($client_id)
			$this->redirect($this->base_uri . "clients/view/" . $client_id . "/");
		$this->redirect($this->base_uri . "clients/");
	}

	/**
	 * Merge clients together
	 */
	public function merge() {
		$this->uses(array("Users"));

		// Get client ID
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$vars = new stdClass();

		if (!empty($this->post)) {
			#
			# TODO: merge the clients
			#
			$vars = (object)$this->post;
		}

		$this->set("vars", $vars);

		$this->view($this->view->fetch("admin_clients_merge"));
	}

	/**
	 * Add Contact
	 */
	public function addContact() {
		$this->uses(array("Contacts", "Users", "Countries", "States"));
		$this->components(array("SettingsCollection"));
		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Get client
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$vars = new stdClass();

		// Set client settings
		$vars->country = $client->settings['country'];
		$vars->currency = $client->settings['default_currency'];
		$vars->language = $client->settings['language'];

		// Add contact
		$user_errors = false;
		$contact_errors = false;
		if (!empty($this->post)) {
			$this->Contacts->begin();
			$this->post['client_id'] = $client->id;

			$vars = $this->post;
			unset($vars['user_id']);

			// Set contact type to 'other' if contact type id is given
			if (is_numeric($this->post['contact_type'])) {
				$vars['contact_type_id'] = $this->post['contact_type'];
				$vars['contact_type'] = "other";
			}
			else
				$vars['contact_type_id'] = null;

			// Format any phone numbers
			$vars['numbers'] = $this->ArrayHelper->keyToNumeric($this->post['numbers']);
			$vars['permissions'] = isset($this->post['permissions']) ? $this->ArrayHelper->keyToNumeric($this->post['permissions']) : array();

			if (!empty($vars['enable_login'])) {
				$vars['confirm_password'] = $vars['new_password'];
				$vars['user_id'] = $this->Users->add($vars);
				$user_errors = $this->Users->errors();
			}

			// Create the contact
			$this->Contacts->add($vars);
			$contact_errors = $this->Contacts->errors();

			$errors = array_merge(($contact_errors ? $contact_errors : array()), ($user_errors ? $user_errors : array()));
			if (!empty($errors)) {
				$this->Contacts->rollback();
				// Error, reset vars
				$vars = (object)$this->post;
				$this->setMessage("error", $errors);
			}
			else {
				$this->Contacts->commit();
				// Success
				$this->flashMessage("message", Language::_("AdminClients.!success.contact_added", true));
				$this->redirect($this->base_uri . "clients/view/" . $this->Html->_($client->id, true) . "/");
			}
		}

		// Set all contact types besides 'primary' and 'other'
		$contact_types = $this->Contacts->getContactTypes();
		$contact_type_ids = $this->Form->collapseObjectArray($this->Contacts->getTypes($this->company_id), "real_name", "id");
		unset($contact_types['primary'], $contact_types['other']);

		$contact_types = $contact_types + $contact_type_ids;

		// Set states and countries for drop downs
		$this->set("countries", $this->Form->collapseObjectArray($this->Countries->getList(), array("name", "alt_name"), "alpha2", " - "));
		$this->set("states", $this->Form->collapseObjectArray($this->States->getList($vars->country), "name", "code"));
		$this->set("contact_types", $contact_types);
		$this->set("permissions", $this->Contacts->getPermissionOptions($this->company_id));
		$this->set("vars", $vars);

		// Set partial for phone numbers
		$partial_vars = array(
			'numbers'=>(isset($vars->numbers) ? $vars->numbers : array()),
			'number_types'=>$this->Contacts->getNumberTypes(),
			'number_locations'=>$this->Contacts->getNumberLocations()
		);
		$this->set("partial_phones", $this->partial("admin_clients_phones", $partial_vars));

		$this->view($this->view->fetch("admin_clients_addcontact"));
		if ($this->isAjax())
			return false;
	}

	/**
	 * Edit Contact
	 */
	public function editContact() {
		$this->uses(array("Contacts", "Users", "Countries", "States"));
		$this->components(array("SettingsCollection"));
		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Get client and contact, ensure they match
		if (!isset($this->get[0]) || !isset($this->get[1]) ||
			!($client = $this->Clients->get((int)$this->get[0])) ||
			!($contact = $this->Contacts->get((int)$this->get[1])) ||
			($client->id != $contact->client_id))
			$this->redirect($this->base_uri . "clients/");

		$user = false;
		if ($contact->user_id)
			$user = $this->Users->get($contact->user_id);

		$vars = array();

		// Edit contact
		$contact_errors = false;
		$user_errors = false;
		if (!empty($this->post)) {
			$this->Contacts->begin();
			$vars = $this->post;
			unset($vars['user_id']);

			// Set contact type to 'other' if contact type id is given
			if (is_numeric($this->post['contact_type'])) {
				$vars['contact_type_id'] = $this->post['contact_type'];
				$vars['contact_type'] = "other";
			}
			else
				$vars['contact_type_id'] = null;

			// Format the phone numbers
			$vars['numbers'] = $this->ArrayHelper->keyToNumeric($this->post['numbers']);
			$vars['permissions'] = isset($this->post['permissions']) ? $this->ArrayHelper->keyToNumeric($this->post['permissions']) : array();

			if (!empty($vars['enable_login'])) {
				$vars['confirm_password'] = $vars['new_password'];
				if ($contact->user_id) {
					if (empty($vars['confirm_password']))
						unset($vars['confirm_password']);

					$this->Users->edit($contact->user_id, $vars);
				}
				else {
					$vars['user_id'] = $this->Users->add($vars);
				}

				$user_errors = $this->Users->errors();
			}
			elseif ($contact->user_id) {
				$this->Users->delete($contact->user_id);
				$vars['user_id'] = null;
			}

			// Update the contact
			$this->Contacts->edit($contact->id, $vars);
			$contact_errors = $this->Contacts->errors();

			$errors = array_merge(($contact_errors ? $contact_errors : array()), ($user_errors ? $user_errors : array()));
			if (!empty($errors)) {
				$this->Contacts->rollback();
				// Error, reset vars
				$vars = (object)$this->post;
				$this->setMessage("error", $errors);
			}
			else {
				$this->Contacts->commit();
				// Success
				$this->flashMessage("message", Language::_("AdminClients.!success.contact_updated", true));
				$this->redirect($this->base_uri . "clients/view/" . $this->Html->_($client->id, true) . "/");
			}
		}

		// Set current contact
		if (empty($vars)) {
			$vars = (object)array_merge((array)$user, (array)$contact);

			// Set contact type if it is not a default type
			if (is_numeric($vars->contact_type_id))
				$vars->contact_type = $vars->contact_type_id;

			// Set contact phone numbers formatted for HTML
			$vars->numbers = $this->ArrayHelper->numericToKey($this->Contacts->getNumbers($contact->id));

			$vars->permissions = $this->ArrayHelper->numericToKey($this->Contacts->getPermissions($contact->id));
		}

		// Set all contact types besides 'primary' and 'other'
		$contact_types = $this->Contacts->getContactTypes();
		$contact_type_ids = $this->Form->collapseObjectArray($this->Contacts->getTypes($this->company_id), "real_name", "id");
		unset($contact_types['primary'], $contact_types['other']);

		$contact_types = $contact_types + $contact_type_ids;

		// Set states and countries for drop downs
		$this->set("contact_id", $contact->id);
		$this->set("client_id", $client->id);
		$this->set("countries", $this->Form->collapseObjectArray($this->Countries->getList(), array("name", "alt_name"), "alpha2", " - "));
		$this->set("states", $this->Form->collapseObjectArray($this->States->getList($vars->country), "name", "code"));
		$this->set("contact_types", $contact_types);
		$this->set("permissions", $this->Contacts->getPermissionOptions($this->company_id));
		$this->set("vars", $vars);
		$this->set("user", $user);

		// Set partial for phone numbers
		$partial_vars = array(
			'numbers'=>$vars->numbers,
			'number_types'=>$this->Contacts->getNumberTypes(),
			'number_locations'=>$this->Contacts->getNumberLocations()
		);
		$this->set("partial_phones", $this->partial("admin_clients_phones", $partial_vars));

		$this->view($this->view->fetch("admin_clients_editcontact"));

		if ($this->isAjax())
			return false;
	}

	/**
	 * Delete Contact
	 */
	public function deleteContact() {
		$this->uses(array("Contacts"));

		// Get client and contact
		if (!isset($this->get[0]) || !isset($this->get[1]) ||
			!($client = $this->Clients->get((int)$this->get[0])) ||
			!($contact = $this->Contacts->get((int)$this->get[1])) ||
			($client->id != $contact->client_id))
			$this->redirect($this->base_uri . "clients/");

		$this->Contacts->delete($contact->id);

		if (($errors = $this->Contacts->errors()))
			$this->flashMessage("error", $errors);
		else
			$this->flashMessage("message", Language::_("AdminClients.!success.contact_deleted", true, $contact->first_name, $contact->last_name));

		$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
	}

	/**
	 * Manage Payment Accounts
	 */
	public function accounts() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$this->uses(array("Accounts", "Contacts"));

		// Set the default account set for autodebiting to none
		$vars = (object)array("account_id"=>"none");

		// Set an account for autodebiting
		if (!empty($this->post)) {
			// Delete the debit account if set to none, or given invalid value
			if ($this->post['account_id'] == "none" || !is_numeric($this->post['account_id'])) {
				// Delete account, send message on success, ignore otherwise (there was nothing to delete)
				if ($this->Clients->deleteDebitAccount($client->id))
					$this->setMessage("message", Language::_("AdminClients.!success.accounts_deleted", true));
			}
			else {
				// Add the debit account
				$this->Clients->addDebitAccount($client->id, $this->post);

				if (($errors = $this->Clients->errors())) {
					// Error, reset vars
					$vars = (object)$this->post;
					$this->setMessage("error", $errors);
				}
				else {
					// Success, debit account added/updated
					$this->setMessage("message", Language::_("AdminClients.!success.accounts_updated", true));
				}
			}
		}

		// Get the current account set for autodebiting
		$client_account = $this->Clients->getDebitAccount($client->id);

		// Set the primary contact accounts
		$primary_contact = $this->Contacts->getAll($client->id, "primary");
		$accounts = array();

		if (!empty($primary_contact[0])) {
			$cc_account = $this->Accounts->getAllCc($primary_contact[0]->id);
			$ach_account = $this->Accounts->getAllAch($primary_contact[0]->id);

			$accounts = array_merge($cc_account, $ach_account);
		}

		// Set billing contact accounts
		$billing_contacts = $this->Contacts->getAll($client->id, "billing");
		for ($i=0, $num_billing_contacts=count($billing_contacts); $i<$num_billing_contacts; $i++) {
			$cc_account = $this->Accounts->getAllCc($billing_contacts[$i]->id);
			$ach_account = $this->Accounts->getAllAch($billing_contacts[$i]->id);

			$accounts = array_merge($accounts, $cc_account, $ach_account);
		}

		// Determine which account is currently set for autodebiting
		if (!empty($accounts) && $client_account) {
			for ($i=0, $num_accounts=count($accounts); $i<$num_accounts; $i++) {
				// Account ID and account type must be identical
				if (($accounts[$i]->id == $client_account->account_id) &&
					($accounts[$i]->account_type == $client_account->type)) {
					// This account is set to be autodebited
					$vars->account_id = $accounts[$i]->id;
					$vars->type = $accounts[$i]->account_type;
					break;
				}
			}
		}

		$this->set("account_types", $this->Accounts->getTypes());
		$this->set("ach_types", $this->Accounts->getAchTypes());
		$this->set("cc_types", $this->Accounts->getCcTypes());
		$this->set("accounts", $accounts);
		$this->set("client", $client);
		$this->set("vars", $vars);

		$this->view($this->view->fetch("admin_clients_accounts"));

		if ($this->isAjax())
			return false;
	}

	/**
	 * Add Credit Card account
	 */
	public function addCcAccount() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$this->uses(array("Accounts", "Contacts", "Countries", "States"));
		$this->components(array("SettingsCollection"));

		// Set default country
		$vars = new stdClass();
		$vars->country = (!empty($client->settings['country']) ? $client->settings['country'] : "");

		// Set warning if the CC payment type setting is not enabled
		if ($client->settings['payments_allowed_cc'] == "false") {
			Language::loadLang(array("navigation"));
			$this->setMessage("notice", Language::_("AdminClients.!notice.payment_type", true, Language::_("AdminClients.addCcAccount.text_cc", true), $this->Html->safe($this->base_uri . "settings/company/billing/acceptedtypes/"), Language::_("Navigation.getcompany.nav_billing_acceptedtypes", true)), false, array('preserve_tags' => true));
		}

		// Create a CC account
		if (!empty($this->post)) {
			// Fetch the contact we're about to set the payment account for
			$temp_contact_id = (isset($this->post['contact_id']) ? $this->post['contact_id'] : 0);
			$contact = $this->Contacts->get($temp_contact_id);

			// Set contact ID to create this account for (default to the client's contact ID)
			if (($temp_contact_id == 0) || !$contact || ($contact->client_id != $client->id))
				$this->post['contact_id'] = $client->contact_id;

			// Concatenate the expiration date to the form 'yyyymm'
			$this->post['expiration'] = $this->post['expiration_year'] . $this->post['expiration_month'];

			// Create the account
			$this->Accounts->addCc($this->post);

			if (($errors = $this->Accounts->errors())) {
				// Error, reset vars
				$this->post['contact_id'] = ($temp_contact_id == 0 ? "none" : $temp_contact_id);
				$vars = (object)$this->post;
				$this->setMessage("error", $errors);
			}
			else {
				// Success, account created
				$this->flashMessage("message", Language::_("AdminClients.!success.addccaccount_added", true));
				$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");
			}
		}


		// Set the contact info partial to the view
		$this->setContactView($vars, $client);
		// Set the CC info partial to the view
		$this->setCcView($vars, $client);

		$this->view($this->view->fetch("admin_clients_addccaccount"));

		if ($this->isAjax())
			return false;
	}

	/**
	 * Add ACH account
	 */
	public function addAchAccount() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$this->uses(array("Accounts", "Contacts", "Countries", "States"));
		$this->components(array("SettingsCollection"));

		// Set default country
		$vars = new stdClass();
		$vars->country = (!empty($client->settings['country']) ? $client->settings['country'] : "");

		// Set warning if the CC payment type setting is not enabled
		if ($client->settings['payments_allowed_ach'] == "false") {
			Language::loadLang(array("navigation"));
			$this->setMessage("notice", Language::_("AdminClients.!notice.payment_type", true, Language::_("AdminClients.addAchAccount.text_ach", true), $this->Html->safe($this->base_uri . "settings/company/billing/acceptedtypes/"), Language::_("Navigation.getcompany.nav_billing_acceptedtypes", true)), false, array('preserve_tags' => true));
		}

		// Create a ACH account
		if (!empty($this->post)) {
			// Fetch the contact we're about to set the payment account for
			$contact = $this->Contacts->get((isset($this->post['contact_id']) ? $this->post['contact_id'] : 0));

			// Set contact ID to create this account for (default to the client's contact ID)
			if ($this->post['contact_id'] == "none" || !is_numeric($this->post['contact_id']) ||
				!$contact || ($contact->client_id != $client->id))
				$this->post['contact_id'] = $client->contact_id;

			// Create the account
			$this->Accounts->addAch($this->post);

			if (($errors = $this->Accounts->errors())) {
				// Error, reset vars
				$vars = (object)$this->post;
				$this->setMessage("error", $errors);
			}
			else {
				// Success, account created
				$this->flashMessage("message", Language::_("AdminClients.!success.addachaccount_added", true));
				$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");
			}
		}

		// Set the contact info partial to the view
		$this->setContactView($vars, $client);
		// Set the ACH info partial to the view
		$this->setAchView($vars, $client);

		$this->view($this->view->fetch("admin_clients_addachaccount"));

		if ($this->isAjax())
			return false;
	}

	/**
	 * Edit a CC account
	 */
	public function editCcAccount() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$this->uses(array("Accounts", "Contacts", "Countries", "States"));
		$this->components(array("SettingsCollection"));

		// Ensure a valid CC account ID has been given and belongs to this client
		if (!isset($this->get[1]) || !($account = $this->Accounts->getCc((int)$this->get[1])) || $account->client_id != $client->id || $account->status != "active")
			$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");

		// Set warning if the CC payment type setting is not enabled
		if ($client->settings['payments_allowed_cc'] == "false") {
			Language::loadLang(array("navigation"));
			$this->setMessage("notice", Language::_("AdminClients.!notice.payment_type", true, Language::_("AdminClients.addCcAccount.text_cc", true), $this->Html->safe($this->base_uri . "settings/company/billing/acceptedtypes/"), Language::_("Navigation.getcompany.nav_billing_acceptedtypes", true)), false, array('preserve_tags' => true));
		}

		$vars = array();

		// Edit the CC account
		if (!empty($this->post)) {

			// Concatenate the expiration date to the form 'yyyymm'
			$this->post['expiration'] = $this->post['expiration_year'] . $this->post['expiration_month'];

			// Update the account
			$this->Accounts->editCc($account->id, $this->post);

			if (($errors = $this->Accounts->errors())) {
				// Error, reset vars
				$vars = (object)$this->post;
				$vars->gateway_id = $account->gateway_id;
				$this->setMessage("error", $errors);
			}
			else {
				// Success, account updated
				$this->flashMessage("message", Language::_("AdminClients.!success.editccaccount_updated", true));
				$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");
			}
		}

		// Set current account
		if (empty($vars)) {
			$vars = $account;

			// Parse out the expiration date for the CC# (yyyymm)
			$vars->expiration_month = substr($vars->expiration, -2);
			$vars->expiration_year = substr($vars->expiration, 0, 4);
		}

		// Set the contact info partial to the view
		$this->setContactView($vars, $client, true);
		// Set the CC info partial to the view
		$this->setCcView($vars, $client, true);

		$this->view($this->view->fetch("admin_clients_editccaccount"));

		if ($this->isAjax())
			return false;
	}

	/**
	 * Edit an ACH account
	 */
	public function editAchAccount() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$this->uses(array("Accounts", "Contacts", "Countries", "States"));
		$this->components(array("SettingsCollection"));

		// Ensure a valid ACH account ID has been given and belongs to this client
		if (!isset($this->get[1]) || !($account = $this->Accounts->getAch((int)$this->get[1])) || $account->client_id != $client->id || $account->status != "active")
			$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");

		// Set warning if the CC payment type setting is not enabled
		if ($client->settings['payments_allowed_ach'] == "false") {
			Language::loadLang(array("navigation"));
			$this->setMessage("notice", Language::_("AdminClients.!notice.payment_type", true, Language::_("AdminClients.addAchAccount.text_ach", true), $this->base_uri . "settings/company/billing/acceptedtypes/", Language::_("Navigation.getcompany.nav_billing_acceptedtypes", true)), false, array('preserve_tags' => true));
		}

		$vars = array();

		// Edit the ACH account
		if (!empty($this->post)) {

			// Update the account
			$this->Accounts->editAch($account->id,  $this->post);

			if (($errors = $this->Accounts->errors())) {
				// Error, reset vars
				$vars = (object)$this->post;
				$vars->gateway_id = $account->gateway_id;
				$this->setMessage("error", $errors);
			}
			else {
				// Success, account updated
				$this->flashMessage("message", Language::_("AdminClients.!success.editachaccount_updated", true));
				$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");
			}
		}

		// Set current account
		if (empty($vars)) {
			$vars = $account;
		}

		// Set the contact info partial to the view
		$this->setContactView($vars, $client, true);
		// Set the ACH info partial to the view
		$this->setAchView($vars, $client, true);

		$this->view($this->view->fetch("admin_clients_editachaccount"));

		if ($this->isAjax())
			return false;
	}

	/**
	 * Delete a CC account
	 */
	public function deleteCcAccount() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$this->uses(array("Accounts"));

		// Ensure a valid CC account ID has been given and belongs to this client
		if (!isset($this->get[1]) || !($account = $this->Accounts->getCc((int)$this->get[1])) || $account->client_id != $client->id || $account->status != "active")
			$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");

		$this->Accounts->deleteCc($account->id);
		// Success, account deleted
		$this->flashMessage("message", Language::_("AdminClients.!success.deleteccaccount_deleted", true));
		$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");
	}

	/**
	 * Delete an ACH account
	 */
	public function deleteAchAccount() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$this->uses(array("Accounts"));

		// Ensure a valid ACH account ID has been given and belongs to this client
		if (!isset($this->get[1]) || !($account = $this->Accounts->getAch((int)$this->get[1])) || $account->client_id != $client->id || $account->status != "active")
			$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");

		$this->Accounts->deleteAch($account->id);
		// Success, account deleted
		$this->flashMessage("message", Language::_("AdminClients.!success.deleteachaccount_deleted", true));
		$this->redirect($this->base_uri . "clients/accounts/" . $client->id . "/");
	}

	/**
	 * Renders a form to enter a passphrase for decrypting a card and returns the
	 * card on success
	 */
	public function showcard() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			exit;

		$this->uses(array("Accounts","Contacts","Users"));
		$this->components(array("SettingsCollection"));

		// Ensure a valid CC account ID has been given and belongs to this client
		if (!isset($this->get[1]) || !($account = $this->Accounts->getCc((int)$this->get[1])) || $this->Contacts->get($account->contact_id)->client_id != $client->id)
			exit;

		// Check whether a passphrase is required or not
		$temp = $this->SettingsCollection->fetchSetting(null, $this->company_id, "private_key_passphrase");
		$passphrase_required = (isset($temp['value']) && $temp['value'] != "");
		unset($temp);

		// Set whether passphrase is required
		$this->set("passphrase_required", $passphrase_required);

		if (!empty($this->post)) {

			$response = new stdClass();
			$error = null;

			// If passphrase is required, decrypt using passphrase
			if ($passphrase_required)
				$response->account = $this->Accounts->getCc($account->id, true, $this->post['passphrase'], $this->Session->read("blesta_staff_id"));
			// If passphrase is not required, require staff to enter account password and verify
			else {
				if ($this->Users->auth($this->Session->read("blesta_id"), array('password'=>$this->post['passphrase'])))
					$response->account = $this->Accounts->getCc($account->id, true, null, $this->Session->read("blesta_staff_id"));
				$error = $this->Users->errors();
			}

			// If decryption was unsuccessful, display the appropriate error
			if (!isset($response->account->number) || $response->account->number === false) {
				if ($passphrase_required)
					$error = Language::_("AdminClients.showcard.!error.passphrase", true);
				else
					$error = Language::_("AdminClients.showcard.!error.password", true);
			}

			if ($error) {
				$this->setMessage("error", $error);
				$response->view = $this->view->fetch("admin_clients_showcard");
			}

			// JSON encode the AJAX response
			$this->outputAsJson($response);
			return false;
		}

		echo $this->view->fetch("admin_clients_showcard");
		return false;
	}

	/**
	 * Renders a form to enter a passphrase for decrypting a bank account and routing number and returns the
	 * values on success
	 */
	public function showaccount() {
		// Ensure a valid client has been given
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			exit;

		$this->uses(array("Accounts","Contacts","Users"));
		$this->components(array("SettingsCollection"));

		// Ensure a valid ACH account ID has been given and belongs to this client
		if (!isset($this->get[1]) || !($account = $this->Accounts->getAch((int)$this->get[1])) || $this->Contacts->get($account->contact_id)->client_id != $client->id)
			exit;

		// Check whether a passphrase is required or not
		$temp = $this->SettingsCollection->fetchSetting(null, $this->company_id, "private_key_passphrase");
		$passphrase_required = (isset($temp['value']) && $temp['value'] != "");
		unset($temp);

		// Set whether passphrase is required
		$this->set("passphrase_required", $passphrase_required);

		if (!empty($this->post)) {

			$response = new stdClass();
			$error = null;

			// If passphrase is required, decrypt using passphrase
			if ($passphrase_required)
				$response->account = $this->Accounts->getAch($account->id, true, $this->post['passphrase'], $this->Session->read("blesta_staff_id"));
			// If passphrase is not required, require staff to enter account password and verify
			else {
				if ($this->Users->auth($this->Session->read("blesta_id"), array('password'=>$this->post['passphrase'])))
					$response->account = $this->Accounts->getAch($account->id, true, null, $this->Session->read("blesta_staff_id"));
				$error = $this->Users->errors();
			}

			// If decryption was unsuccessful, display the appropriate error
			if (!isset($response->account->account) || $response->account->account === false) {
				if ($passphrase_required)
					$error = Language::_("AdminClients.showaccount.!error.passphrase", true);
				else
					$error = Language::_("AdminClients.showaccount.!error.password", true);
			}

			if ($error) {
				$this->setMessage("error", $error);
				$response->view = $this->view->fetch("admin_clients_showaccount");
			}

			// JSON encode the AJAX response
			$this->outputAsJson($response);
			return false;
		}

		echo $this->view->fetch("admin_clients_showaccount");
		return false;
	}

	/**
	 * Processes a payment for this client
	 */
	public function makePayment() {
		$this->uses(array("Accounts", "Contacts", "Countries", "Currencies", "Invoices", "States", "Transactions"));
		$this->components(array("SettingsCollection"));

		// Get client ID
		if (!isset($this->get[0]) || !($client = $this->Clients->get($this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// If an invoice ID is set, fetch the invoice and ensure it is active and belongs to this client
		if (isset($this->get[1]) && !(($invoice = $this->Invoices->get($this->get[1])) && ($invoice->status == "active" || $invoice->status == "proforma") && $invoice->client_id == $client->id && $invoice->date_closed == null))
			$this->redirect($this->base_uri . "clients/view/" . $this->get[0] . "/");

		$step = isset($this->post['step']) ? $this->post['step'] : 1;

		$this->set("client", $client);

		if (isset($this->get[1]) && $invoice)
			$this->set("invoice", $invoice);

		$vars = new stdClass();
		$vars->country = (!empty($client->settings['country']) ? $client->settings['country'] : "");

		if (isset($this->post['vars'])) {
			$vars = (object)array_merge((array)unserialize(base64_decode($this->post['vars'])), (array)$vars);
			unset($this->post['vars']);
		}

		// Allow POST to override any $vars
		foreach ($this->post as $field => $value) {
			if (isset($vars->$field) || property_exists($vars, $field))
				unset($vars->$field);
		}

		// Default the given invoice to selected, if not already set, use 'credit'
		// as the basis for reference on whether the invoice selection screen has been previously submitted
		if (isset($this->get[1]) && !empty($this->post) && !isset($this->post['credit']) && !isset($this->post['invoice_id']))
			$this->post['invoice_id'][] = $this->get[1];

		$vars = (object)array_merge($this->post, (array)$vars);

		switch ($step) {
			// Verify payment details / Store payment account
			default:
			case "1":

				if (!empty($this->post)) {
					if (!isset($this->post['pay_with'])) {
						$this->flashMessage("error", Language::_("AdminClients.!error.pay_with.required", true));
						$this->redirect($this->base_uri . "clients/makepayment/" . $this->get[0] . "/");
					}

					if ($this->post['pay_with'] == "details") {
						// Fetch the contact we're about to set the payment account for
						$contact = $this->Contacts->get((isset($vars->contact_id) ? $vars->contact_id : 0));

						if ($vars->contact_id == "none" || !$contact || ($contact->client_id != $client->id))
							$vars->contact_id = $client->contact_id;

						// Attempt to save the account, then set it as the account to use
						if (isset($this->post['save_details']) && $this->post['save_details'] == "true") {
							if ($this->post['payment_type'] == "ach") {
								$account_id = $this->Accounts->addAch((array)$vars);

								// Assign the newly created payment account as the account to use for this payment
								if ($account_id) {
									$vars->payment_account = "ach_" . $account_id;
									$vars->pay_with = "account";
								}
							}
							elseif ($this->post['payment_type'] == "cc") {
								$vars->expiration = $vars->expiration_year . $vars->expiration_month;
								// will automatically determine card type
								unset($vars->type);
								$account_id = $this->Accounts->addCc((array)$vars);

								// Assign the newly created payment account as the account to use for this payment
								if ($account_id) {
									$vars->payment_account = "cc_" . $account_id;
									$vars->pay_with = "account";
								}
							}
						}
						// Verify the payment account details entered were correct, since we're not storing them
						else {
							$vars_arr = (array)$vars;
							if ($this->post['payment_type'] == "ach")
								$this->Accounts->verifyAch($vars_arr);
							elseif ($this->post['payment_type'] == "cc") {
								$vars->expiration = $vars->expiration_year . $vars->expiration_month;
								// will automatically determine card type
								unset($vars->type);
								$vars_arr = (array)$vars;
								$this->Accounts->verifyCc($vars_arr);
							}

							if (isset($vars_arr['type']))
								$vars->type = $vars_arr['type'];
							unset($vars_arr);
						}
					}

					if (($errors = $this->Accounts->errors()))
						$this->setMessage("error", $errors);
					else {
						$vars->email_receipt = "true";
						$step = "2";
					}
				}
				break;
			// Verify payment amounts
			case "2":
				if (!empty($this->post) && count($this->post) > 2) {
					if (!isset($this->post['invoice_id']))
						unset($vars->invoice_id);
					if (!isset($this->post['email_receipt']))
						unset($vars->email_receipt);

					// Single invoice
					if (isset($this->get[1]) && $invoice)
						$vars->currency = $invoice->currency;
					else
						$vars->currency = $this->post['currency'];

					// Verify payment amounts, ensure that amounts entered do no exceed total due on invoice
					if (isset($vars->invoice_id)) {
						$apply_amounts = array('amounts'=>array());
						foreach ($vars->invoice_id as $inv_id) {
							if (isset($vars->applyamount[$inv_id])) {
								$apply_amounts['amounts'][] = array(
									'invoice_id'=>$inv_id,
									'amount'=>$this->CurrencyFormat->cast($vars->applyamount[$inv_id], $vars->currency)
								);
							}
						}

						$this->Transactions->verifyApply($apply_amounts, false);
					}

					if (($errors = $this->Transactions->errors()))
						$this->setMessage("error", $errors);
					else
						$step = "3";
				}
				break;
			// Execute payment
			case "3":
				if (!empty($this->post)) {

					$total = $this->CurrencyFormat->cast($vars->credit, $vars->currency);
					$apply_amounts = array();

					if (isset($vars->invoice_id)) {
						foreach ($vars->invoice_id as $inv_id) {
							// If an amount was set for the selected invoice, calculate that value
							if (isset($vars->applyamount[$inv_id])) {
								$apply_amounts[$inv_id] = $this->CurrencyFormat->cast($vars->applyamount[$inv_id], $vars->currency);
								$total += $apply_amounts[$inv_id];
							}
						}
					}

					$this->uses(array("Payments"));

					$options = array(
						'invoices'=>$apply_amounts,
						'staff_id'=>$this->Session->read("blesta_staff_id"),
						'email_receipt'=>isset($vars->email_receipt) ? $vars->email_receipt : "false"
					);

					if ($vars->pay_with == "account") {
						$account_info = null;
						list($type, $account_id) = explode("_", $vars->payment_account, 2);
					}
					else {
						$type = $vars->payment_type;
						$account_id = null;
						$account_info = array(
							'first_name'=>$vars->first_name,
							'last_name'=>$vars->last_name,
							'address1'=>$vars->address1,
							'address2'=>$vars->address2,
							'city'=>$vars->city,
							'state'=>$vars->state,
							'country'=>$vars->country,
							'zip'=>$vars->zip
						);

						if ($type == "ach") {
							$account_info['account_number'] = $vars->account;
							$account_info['routing_number'] = $vars->routing;
							$account_info['type'] = $vars->type;
						}
						elseif ($type == "cc") {
							$account_info['card_number'] = $vars->number;
							$account_info['card_exp'] = $vars->expiration_year . $vars->expiration_month;
							$account_info['card_security_code'] = $vars->security_code;
						}
					}

					// Process the payment
					$transaction = $this->Payments->processPayment($client->id, $type, $total, $vars->currency, $account_info, $account_id, $options);

					if (($errors = $this->Payments->errors())) {
						// Unset the last4 so that the view doesn't block out non-stored payment accounts
						unset($vars->last4);
						$this->setMessage("error", $errors);
						$step = "1";
					}
					else {
						$this->flashMessage("message", Language::_("AdminClients.!success.makepayment_processed", true, $this->CurrencyFormat->format($transaction->amount, $transaction->currency), $transaction->transaction_id));
						$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
					}
				}
				break;
		}

		switch ($step) {
			case "1":
				// Fetch the auto-debit payment account (if set), so we can identify it
				$autodebit = $this->Clients->getDebitAccount($client->id);

				// Get ACH payment types
				$ach_types = $this->Accounts->getAchTypes();
				// Get CC payment types
				$cc_types = $this->Accounts->getCcTypes();

				// Set the payment types allowed
				$transaction_types = $this->Transactions->transactionTypeNames();
				$payment_types = array();
				if ($client->settings['payments_allowed_ach'] == "true")
					$payment_types['ach'] = $transaction_types['ach'];
				if ($client->settings['payments_allowed_cc'] == "true")
					$payment_types['cc'] = $transaction_types['cc'];


				// Set available payment accounts
				$payment_accounts = array();

				// Only allow CC payment accounts if enabled
				if (isset($payment_types['cc'])) {
					$cc = $this->Accounts->getAllCcByClient($client->id);
					if ($cc)
						$payment_accounts[] = array('value'=>"optgroup", 'name'=>Language::_("AdminClients.makepayment.field_paymentaccount_cc", true));

					foreach ((array)$cc as $account) {
						$is_autodebit = false;
						if ($autodebit && $autodebit->type == "cc" && $autodebit->account_id == $account->id) {
							$is_autodebit = true;
							$vars->payment_account = "cc_" . $account->id;
						}
						$lang_define = ($is_autodebit ? "AdminClients.makepayment.field_paymentaccount_autodebit" : "AdminClients.makepayment.field_paymentaccount");
						$payment_accounts["cc_" . $account->id] = Language::_($lang_define, true, $account->first_name, $account->last_name, $cc_types[$account->type], $account->last4);
					}
				}

				// Only allow ACH payment accounts if enabled
				if (isset($payment_types['ach'])) {
					$ach = $this->Accounts->getAllAchByClient($client->id);
					if ($ach)
						$payment_accounts[] = array('value'=>"optgroup", 'name'=>Language::_("AdminClients.makepayment.field_paymentaccount_ach", true));

					foreach ((array)$ach as $account) {
						$is_autodebit = false;
						if ($autodebit && $autodebit->type == "ach" && $autodebit->account_id == $account->id) {
							$is_autodebit = true;
							$vars->payment_account = "ach_" . $account->id;
						}
						$lang_define = ($is_autodebit ? "AdminClients.makepayment.field_paymentaccount_autodebit" : "AdminClients.makepayment.field_paymentaccount");
						$payment_accounts["ach_" . $account->id] = Language::_($lang_define, true, $account->first_name, $account->last_name, $ach_types[$account->type], $account->last4);
					}
				}
				$this->set("payment_accounts", $payment_accounts);
				$this->set("require_passphrase", !empty($client->settings['private_key_passphrase']));

				// Set the contact info partial to the view
				$this->setContactView($vars, $client);
				// Set the CC info partial to the view
				$this->setCcView($vars, $client, false, true);
				// Set the ACH info partial to the view
				$this->setAchView($vars, $client, false, true);

				$this->set("payment_types", $payment_types);

				if (isset($invoice))
					$this->set("invoice", $invoice);

				break;
			case "2":
				$this->action = $this->action . "amount";

				if (!isset($vars->currency))
					$vars->currency = $client->settings['default_currency'];

				// Get all invoices open for this client (to be paid)
				$invoices = ((isset($this->get[1]) && $invoice) ? array($invoice) : $this->Invoices->getAll($client->id, "open", array('date_due'=>"ASC"), $vars->currency));
				$this->set("invoice_info", $this->partial("admin_clients_makepaymentinvoices", array('vars'=>$vars, 'invoices'=>$invoices)));

				// All currencies available
				$this->set("currencies", $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code"));
				break;
			case "3":
				$this->action = $this->action . "confirm";
				$total = $this->CurrencyFormat->cast($vars->credit, $vars->currency);
				$invoices = array();
				$apply_amounts = array();

				if (isset($vars->invoice_id)) {
					for ($i=0, $j=0; $i<count($vars->invoice_id); $i++) {
						// If an amount was set for the selected invoice, calculate that value
						if (isset($vars->applyamount[$vars->invoice_id[$i]])) {
							$apply_amounts[$j] = $this->CurrencyFormat->cast($vars->applyamount[$vars->invoice_id[$i]], $vars->currency);
							$total += $apply_amounts[$j];
							$j++;
						}

						$invoice =  $this->Invoices->get($vars->invoice_id[$i]);
						if ($invoice && $invoice->client_id == $client->id)
							$invoices[] = $invoice;
					}
				}

				// Set the payment account being used if one exists
				if ($vars->pay_with == "account") {
					list($type, $account_id) = explode("_", $vars->payment_account, 2);

					if ($type == "cc")
						$this->set("account", $this->Accounts->getCc($account_id));
					elseif ($type == "ach")
						$this->set("account", $this->Accounts->getAch($account_id));

					$this->set("account_type", $type);
					$this->set("account_id", $account_id);
				}
				else {
					if ($vars->payment_type == "ach")
						$vars->last4 = substr($vars->account, -4);
					elseif ($vars->payment_type == "cc")
						$vars->last4 = substr($vars->number, -4);
					$this->set("account_type", $vars->payment_type);
					$this->set("account", $vars);
				}

				$this->set("vars", $vars);
				$this->set("invoices", $invoices);
				$this->set("currency", $vars->currency);
				$this->set("apply_amounts", $apply_amounts);
				$this->set("total", $total);
				$this->set("account_types", $this->Accounts->getTypes());
				$this->set("ach_types", $this->Accounts->getAchTypes());
				$this->set("cc_types", $this->Accounts->getCcTypes());

				break;
		}

		$this->set("vars", $vars);
		$this->set("step", $step);

		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync();
		return $this->renderClientView($this->controller . "_" . $this->action);
	}

	/**
	 * Fetches a table of invoices for the given currency
	 */
	public function makePaymentInvoices() {
		$this->uses(array("Invoices"));

		if (!isset($this->get[0]) || !($client = $this->Clients->get($this->get[0], false)))
			return false;

		$vars = array();
		if (isset($this->post['currency'])) {
			$vars['invoices'] = $this->Invoices->getAll($client->id, "open", array('date_due'=>"ASC"), $this->post['currency']);
		}
		$vars['vars'] = (object)$this->post;

		$this->outputAsJson(array('content'=>$this->partial('admin_clients_makepaymentinvoices', $vars)));
		return false;
	}

	/**
	 * Manually record a payment for this client (i.e. record payment by check)
	 */
	public function recordPayment() {

		$this->uses(array("Currencies", "Emails", "Invoices", "Transactions", "GatewayManager"));
		$this->components(array("SettingsCollection"));
		$step = isset($this->post['step']) ? $this->post['step'] : 1;

		// Get client ID
		if (!isset($this->get[0]) || !($client = $this->Clients->get($this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// If an invoice ID is set, fetch the invoice and ensure it is active and belongs to this client
		if (isset($this->get[1]) && !(($invoice = $this->Invoices->get($this->get[1])) && ($invoice->status == "active" || $invoice->status == "proforma") && $invoice->client_id == $client->id))
			$this->redirect($this->base_uri . "clients/" . $this->get[0] . "/");

		$vars = new stdClass();

		if (isset($this->post['vars'])) {
			$vars = (object)array_merge((array)unserialize(base64_decode($this->post['vars'])), (array)$vars);
			unset($this->post['vars']);
		}

		// Allow POST to override any $vars
		foreach ($this->post as $field => $value) {
			if (isset($vars->$field) || property_exists($vars, $field))
				unset($vars->$field);
		}

		// Default the given invoice to selected, if not already set, use 'credit'
		// as the basis for reference on whether the invoice selection screen has been previously submitted
		if (isset($this->get[1]) && !isset($this->post['credit']) && !isset($this->post['invoice_id']))
			$this->post['invoice_id'][] = $this->get[1];

		$transaction_types = $this->Transactions->transactionTypeNames();
		$vars = (object)array_merge($this->post, (array)$vars);

		switch ($step) {
			default:
			case "1":

				if (!empty($this->post) && count($this->post) > 2) {
					$vars->currency = $this->post['currency'];

                    // Make sure that invoices are selected when attempting to apply a credit
                    $use_credit = (isset($vars->payment_type) && $vars->payment_type == "credit");
                    if ($use_credit) {
                        $invoices_selected = false;
                        $invoice_ids = (isset($vars->invoice_id) ? $vars->invoice_id : array());
                        foreach ($invoice_ids as $inv_id) {
                            if (isset($vars->applyamount[$inv_id])) {
                                $invoices_selected = true;
                                break;
                            }
                        }

                        // An invoice must be selected so that credits can be applied
                        if (!$invoices_selected) {
                            $this->setMessage("error", Language::_("AdminClients.!error.invoice_credits.required", true));
                            break;
                        }
                    }

					// Verify payment amounts, ensure that amounts entered do not exceed total due on invoice
					if (isset($vars->invoice_id)) {
						$apply_amounts = array('amounts'=>array());
						foreach ($vars->invoice_id as $inv_id) {
							if (isset($vars->applyamount[$inv_id])) {
								$apply_amounts['amounts'][] = array(
									'invoice_id'=>$inv_id,
									'amount'=>$this->CurrencyFormat->cast($vars->applyamount[$inv_id], $vars->currency)
								);
							}
						}

                        // Verify the the amount specified, or the credit amount
                        $amount = (isset($vars->amount) ? $vars->amount : "");
                        $amount = ($use_credit ? $this->getCreditPaymentAmount($client->id, $vars) : $amount);
						$this->Transactions->verifyApply($apply_amounts, false, $amount);
					}

					if (($errors = $this->Transactions->errors()))
						$this->setMessage("error", $errors);
					else
						$step = "2";
				}

				break;
			case "2":

				if (!empty($this->post)) {
					// Set apply amounts for invoices if given
					if (isset($vars->invoice_id)) {
						$apply_amounts = array('amounts'=>array());
						foreach ($vars->invoice_id as $inv_id) {
							if (isset($vars->applyamount[$inv_id])) {
								$apply_amounts['amounts'][] = array(
									'invoice_id'=>$inv_id,
									'amount'=>$this->CurrencyFormat->cast($vars->applyamount[$inv_id], $vars->currency)
								);
							}
						}
					}

                    // Apply credits, or record a manual payment
                    if (isset($vars->payment_type) && $vars->payment_type == "credit") {
                        if (isset($apply_amounts) && !empty($apply_amounts['amounts'])) {
                            // Apply credits
                            $this->Transactions->applyFromCredits($client->id, $vars->currency, $apply_amounts['amounts']);

                            if (($errors = $this->Transactions->errors())) {
                                $this->setMessage("error", $errors);
                                $step = "1";
                            }
                            else {
                                $this->flashMessage("message", Language::_("AdminClients.!success.recordpayment_credits", true));
                                $this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
                            }
                        }
                    }
                    else {
                        // Translate the transaction type into the type and transaction type ID suitable for the Transactions model
                        $payment_types = $this->Form->collapseObjectArray($this->Transactions->getTypes(), "id", "name");

                        if (isset($payment_types[$vars->transaction_type])) {
                            $type = "other";
                            $transaction_type_id = $payment_types[$vars->transaction_type];
                        }
                        else {
                            $type = $vars->transaction_type;
                            $transaction_type_id = null;
                        }

                        // Build the transaction for recording
                        $transaction = array(
                            'client_id'=>$client->id,
                            'amount'=>$vars->amount,
                            'currency'=>$vars->currency,
                            'type'=>$type,
                            'transaction_type_id'=>$transaction_type_id,
                            'transaction_id'=>$vars->transaction_id,
                            'gateway_id' => isset($vars->gateway_id) ? $vars->gateway_id : null,
                            'date_added'=>$vars->date_received
                        );

                        // Record the transactions
                        $transaction_id = $this->Transactions->add($transaction);
                        $errors = $this->Transactions->errors();

                        // Apply transaction amounts if given
                        if (!$errors && !empty($apply_amounts)) {
                            // Apply the transaction to the selected invoices
                            $this->Transactions->apply($transaction_id, $apply_amounts);
                            $errors = $this->Transactions->errors();
                        }

                        if ($errors) {
                            $this->setMessage("error", $errors);
                            $step = "1";
                        }
                        else {
                            $transaction = $this->Transactions->get($transaction_id);
                            $amount = $this->CurrencyFormat->format($transaction->amount, $transaction->currency);

                            // If set to email the client, send the email
                            if (isset($vars->email_receipt) && $vars->email_receipt == "true") {
                                $tags = array(
                                    'contact'=>$client,
                                    'amount'=>$amount,
                                    'transaction_id'=>$transaction->transaction_id,
                                    'payment_type'=>$transaction_types[$vars->transaction_type],
                                    'date_added'=>$this->Date->cast($transaction->date_added, "date_time")
                                );

                                $this->Emails->send("payment_manual_approved", $this->company_id, $client->settings['language'], $client->email, $tags, null, null, null, array('to_client_id'=>$client->id, 'from_staff_id'=>$this->Session->read("blesta_staff_id")));
                            }

                            $this->flashMessage("message", Language::_("AdminClients.!success.recordpayment_processed", true, $amount));
                            $this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
                        }
                    }
				}

				break;
		}

		switch ($step) {
			case "1":
				if (!isset($vars->currency))
					$vars->currency = $client->settings['default_currency'];

				// All currencies available
				$this->set("currencies", $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code"));
				// Get all invoices open for this client (to be paid)
				$this->set("invoice_info", $this->partial("admin_clients_makepaymentinvoices", array('vars'=>$vars, 'invoices'=>$this->Invoices->getAll($client->id, "open", array('date_due'=>"ASC"), $vars->currency))));

				if (isset($invoice))
					$this->set("invoice", $invoice);
				break;
			case "2";
				$this->action = $this->action . "confirm";

                // Set the the amount specified, or the credit amount
                $total = (isset($vars->amount) ? $vars->amount : "");
                if (isset($vars->payment_type) && $vars->payment_type == "credit") {
                    // Set the amount we're applying from the credit
                    if (isset($apply_amounts['amounts'])) {
                        $total = 0;
                        foreach ($apply_amounts['amounts'] as $amount)
                            $total += $amount['amount'];
                    }
                    else
                        $total = $this->getCreditPaymentAmount($client->id, $vars);
                }
				$this->set("total", $total);
				break;
		}

		$this->set("vars", $vars);
		$this->set("step", $step);
		$this->set("transaction_types", $transaction_types);
		$this->set("nonmerchant_gateways", $this->GatewayManager->getAll($this->company_id, "nonmerchant"));
		$this->set("merchant_gateways", $this->GatewayManager->getAll($this->company_id, "merchant"));
		$this->set("currency", $vars->currency);
		$this->set("client", $client);
        $this->set("record_payment_fields", $this->getRecordCreditFields($client->id, $vars->currency, $vars));

		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync();
		return $this->renderClientView($this->controller . "_" . $this->action);
	}

    /**
     * Fetches the total credit amount available for the client in the given currency and payment type
     * @see AdminClients::recordPayment()
     *
     * @param int $client_id The ID of the client
     * @param stdClass An stdClass object representing variable input, including:
     *  - currency The currency that the credit should be in
     *  - payment_type The payment type. If not a value of "credit", 0 will be returned
     * @return float The total amount of credit available
     */
    private function getCreditPaymentAmount($client_id, stdClass $vars) {
        if (!isset($this->Transactions))
            $this->uses(array("Transactions"));

        if (!empty($vars->currency) && isset($vars->payment_type) && $vars->payment_type == "credit")
            return $this->Transactions->getTotalCredit($client_id, $vars->currency);

        return 0;
    }

    /**
     * AJAX Fetches a partial containing the record payment/credit fields
     * @see AdminClients::recordPayment()
     *
     * @param int $client_id The client ID
     * @param string $currency The currency to fetch credits in
     * @param stdClass $vars An stdClass object representing input vars
     * @return string A partial of the fields
     */
    public function getRecordCreditFields($client_id = null, $currency = null, $vars = null) {
        $return = ($client_id !== null);
        $client_id = ($client_id !== null ? $client_id : (isset($this->get[0]) ? $this->get[0] : null));
        $client = $this->Clients->get($client_id);
        $currency = ($currency !== null ? $currency : (isset($this->post['currency']) ? $this->post['currency'] : null));

        // Ensure a valid client was given
        if ((!$return && !$this->isAjax()) || !$client) {
            if (!$return) {
                header($this->server_protocol . " 401 Unauthorized");
                exit();
            }
            return $this->partial("admin_clients_recordpayment_credit", array());
		}

        if (!isset($this->Transactions))
            $this->uses(array("Transactions"));

        if (!$currency)
            $currency = $client->settings['default_currency'];

        $vars = array(
            'credit' => $this->Transactions->getTotalCredit($client->id, $currency),
            'currency' => $currency,
            'vars' => (object)array_merge((array)$vars, (!empty($this->post) ? $this->post : array()))
        );

        $fields = $this->partial("admin_clients_recordpayment_credit", $vars);

        if ($return)
            return $fields;

        // JSON encode the AJAX response
		$this->outputAsJson(array('content' => $fields));
		return false;
    }


	/**
	 * Sets the contact partial view
	 * @see AdminClients::makePayment(), AdminClients::addAchAccount(), AdminClients::addCcAccount(), AdminClients::editAchAccount(), AdminClients::editCcAccount()
	 *
	 * @param stdClass $vars The input vars object for use in the view
	 * @param stdClass $client The client object whose contacts to use
	 * @param boolean $edit True if this is an edit, false otherwise
	 */
	private function setContactView(stdClass $vars, stdClass $client, $edit=false) {

		$contacts = array();

		if (!$edit) {
			// Set an option for no contact
			$no_contact = array(
				(object)array(
					'id'=>"none",
					'first_name'=>Language::_("AdminClients.setcontactview.text_none", true),
					'last_name'=>""
				)
			);

			// Set all contacts whose info can be prepopulated (primary or billing only)
			$contacts = array_merge($this->Contacts->getAll($client->id, "primary"), $this->Contacts->getAll($client->id, "billing"));
			$contacts = array_merge($no_contact, $contacts);
		}

		// Set partial for contact info
		$contact_info = array(
			'js_contacts' => $this->Json->encode($contacts),
			'contacts' => $this->Form->collapseObjectArray($contacts, array("first_name", "last_name"), "id", " "),
			'countries' => $this->Form->collapseObjectArray($this->Countries->getList(), array("name", "alt_name"), "alpha2", " - "),
			'states' => $this->Form->collapseObjectArray($this->States->getList($vars->country), "name", "code"),
			'vars' => $vars,
			'edit' => $edit
		);
		$this->set("contact_info", $this->partial("admin_clients_account_contactinfo", $contact_info));
	}

	/**
	 * Sets the ACH partial view
	 * @see AdminClients::makePayment(), AdminClients::addAchAccount(), AdminClients::editAchAccount()
	 *
	 * @param stdClass $vars The input vars object for use in the view
	 * @param stdClass $client The client object whose contacts to use
	 * @param boolean $edit True if this is an edit, false otherwise
	 * @param boolean $save_account True to offer an option to save these payment details, false otherwise
	 */
	private function setAchView(stdClass $vars, stdClass $client, $edit=false, $save_account=false) {
		// Set partial for ACH info
		$ach_info = array(
			'types' => $this->Accounts->getAchTypes(),
			'vars' => $vars,
			'edit' => $edit,
			'client' => $client,
			'save_account' => $save_account
		);
		$this->set("ach_info", $this->partial("admin_clients_account_achinfo", $ach_info));
	}

	/**
	 * Sets the CC partial view
	 * @see AdminClients::makePayment(), AdminClients::addCcAccount(), AdminClients::editCcAccount()
	 *
	 * @param stdClass $vars The input vars object for use in the view
	 * @param stdClass $client The client object whose contacts to use
	 * @param boolean $edit True if this is an edit, false otherwise
	 * @param boolean $save_account True to offer an option to save these payment details, false otherwise
	 */
	private function setCcView(stdClass $vars, stdClass $client, $edit=false, $save_account=false) {
		// Set available credit card expiration dates
		$years = $this->Date->getYears(date("Y"), date("Y") + 10, "Y", "Y");

		// Set the card year in case of an old, expired, card
		if (!empty($vars->expiration_year) && !array_key_exists($vars->expiration_year, $years) && preg_match("/^[0-9]{4}$/", $vars->expiration_year)) {
			$card_year = array($vars->expiration_year => $vars->expiration_year);

			if ((int)$vars->expiration_year < reset($years))
				$years = $card_year + $years;
			elseif ((int)$vars->expiration_year > end($years))
				$years += $card_year;
		}

		$expiration = array(
			// Get months with full name (e.g. "January")
			'months' => $this->Date->getMonths(1, 12, "m", "F"),
			// Sets years from the current year to 10 years in the future
			'years' => $years
		);

		// Set partial for CC info
		$cc_info = array(
			'expiration' => $expiration,
			'vars' => $vars,
			'edit' => $edit,
			'client' => $client,
			'save_account' => $save_account
		);
		$this->set("cc_info", $this->partial("admin_clients_account_ccinfo", $cc_info));
	}

	/**
	 * AJAX Fetches the currency amounts for the client profile sidebar
	 */
	public function getCurrencyAmounts() {
		// Ensure a valid client was given
		if (!$this->isAjax() || !isset($this->get[0]) || !($client = $this->Clients->get($this->get[0]))) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}

		$this->uses(array("Currencies", "Invoices", "Transactions"));

		$currency_code = $client->settings['default_currency'];
		if (isset($this->get[1]) && ($currency = $this->Currencies->get($this->get[1], $this->company_id)))
			$currency_code = $currency->code;

		// Fetch the amounts
		$amounts = array(
			'total_credit' => array(
				'lang' => Language::_("AdminClients.getcurrencyamounts.text_total_credits", true),
				'amount' => $this->CurrencyFormat->format($this->Transactions->getTotalCredit($client->id, $currency_code), $currency_code)
			),
			'total_due' => array(
				'lang' => Language::_("AdminClients.getcurrencyamounts.text_total_due", true),
				'amount' => $this->CurrencyFormat->format($this->Invoices->amountDue($client->id, $currency_code), $currency_code)
			)
		);

		// Build the vars
		$vars = array(
			'selected_currency' => $currency_code,
			'currencies' => array_unique(array_merge($this->Clients->usedCurrencies($client->id), array($client->settings['default_currency']))),
			'amounts' => $amounts
		);

		// Set the partial for currency amounts
		$response = $this->partial("admin_clients_getcurrencyamounts", $vars);

		// JSON encode the AJAX response
		$this->outputAsJson($response);
		return false;
	}

	/**
	 * Create invoice
	 */
	public function createInvoice() {
		$this->uses(array("Currencies", "Invoices"));
		$this->components(array("SettingsCollection"));
		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Ensure we have a client ID to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		$vars = array();

		// Create an invoice
		if (!empty($this->post)) {
			$vars = $this->post;
			$vars['client_id'] = $client->id;

			// Format the line items
			$vars['lines'] = $this->ArrayHelper->keyToNumeric($vars['lines']);


			// Edit if saving with an existing invoice ID
			if (isset($vars['invoice_id']) && $vars['invoice_id'] > 0 && ($invoice = $this->Invoices->get($vars['invoice_id'])) &&
				$invoice->client_id == $client->id) {
				$invoice_id = $vars['invoice_id'];
				$this->Invoices->edit($invoice_id, $vars);
			}
			// Attempt to save the invoice
			else {
                // Remove empty line items when saving drafts
                if (isset($vars['status']) && isset($vars['lines']))
                    $vars['lines'] = $this->removeEmptyLineItems($vars['status'], $vars['lines']);

				$invoice_id = $this->Invoices->add($vars);
			}


			if (($errors = $this->Invoices->errors())) {
				// Error, reset vars
				$vars = (object)$this->post;
				$vars->line_items = $this->ArrayHelper->numericToKey($this->post['lines']);

				// Set line items to array of objects
				for ($i=0, $num_lines=count($vars->line_items); $i<$num_lines; $i++)
					$vars->line_items[$i] = (object)$vars->line_items[$i];

				$this->setMessage("error", $errors);
			}
			else {
				// Success
				if (!$this->isAjax()) {
					$invoice = $this->Invoices->get($invoice_id);

					if ($vars['status'] == "draft") {
						$this->flashMessage("message", Language::_("AdminClients.!success.draftinvoice_added", true, $invoice->id_code));
						$this->redirect($this->base_uri . "clients/editinvoice/" . $client->id . "/" . $invoice_id . "/");
					}
					else {
						$this->flashMessage("message", Language::_("AdminClients.!success.invoice_added", true, $invoice->id_code));
						$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
					}
				}
			}
		}

		// If this was an ajax request, send the response back
		if ($this->isAjax()) {
			$result = array('success'=>(!isset($errors) || !$errors), 'invoice_id'=>$invoice_id);

			// If successful, return the entire invoice
			if ($result['success'])
				$result['invoice'] = $this->Invoices->get($invoice_id);
			$this->outputAsJson($result);
			return false;
		}

		// Set initial default field values
		if (empty($vars)) {
			$vars = new stdClass();
			$vars->delivery = $client->settings['inv_method'];
			// Set the renew date by default
			$vars->date_due = $this->Date->format("Y-m-d", strtotime("+" . $client->settings['inv_days_before_renewal'] . " days"));

			// Set default currency
			$vars->currency = $client->settings['default_currency'];
		}

		// Set the pricing periods
		$pricing_periods = $this->Invoices->getPricingPeriods();

		$this->set("client", $client);
		$this->set("delivery_methods", $this->Invoices->getDeliveryMethods($client->id));
		$this->set("periods", $pricing_periods);
		$this->set("vars", $vars);
		// Set currencies
		$this->set("currencies", $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code"));

		$this->view($this->view->fetch("admin_clients_createinvoice"));

		if ($this->isAjax())
			return false;
	}

	/**
	 * Edit invoice
	 */
	public function editInvoice() {
		$this->uses(array("Currencies", "Invoices"));
		$this->components(array("SettingsCollection"));
		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Ensure we have a client to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Ensure we have a invoice to load, and that it belongs to this client
		if (!isset($this->get[1]) || !($invoice = $this->Invoices->get((int)$this->get[1])) ||
			($invoice->client_id != $client->id))
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");

		$vars = array();

		// Edit an invoice
		if (!empty($this->post)) {
			$vars = $this->post;
			$vars['client_id'] = $client->id;

			// Structure line items
			$vars['lines'] = $this->ArrayHelper->keyToNumeric($vars['lines']);

            // Remove empty line items when saving drafts
            if (isset($vars['status']) && isset($vars['lines']))
                $vars['lines'] = $this->removeEmptyLineItems($vars['status'], $vars['lines']);

			// Edit the invoice
			$this->Invoices->edit($invoice->id, $vars);

			if (($errors = $this->Invoices->errors())) {
				// Error, reset vars
				$vars = clone $invoice;

				foreach ($this->post as $key => $value) {
					$vars->$key = $value;
				}

				$vars->line_items = $this->ArrayHelper->numericToKey($this->post['lines']);

				// Set line items to array of objects
				for ($i=0, $num_lines=count($vars->line_items); $i<$num_lines; $i++)
					$vars->line_items[$i] = (object)$vars->line_items[$i];

				$this->setMessage("error", $errors);
			}
			else {
				// Success
				if (!$this->isAjax()) {
					// Set the success message to either invoice edited, draft edited, or draft created as invoice
					// Assume invoice edited
					$success_message = Language::_("AdminClients.!success.invoice_updated", true, $invoice->id_code);

					// Check whether a draft was edited, or created as an invoice
					switch ($vars['status']) {
						case "draft":
							// Draft saved as draft
							$success_message = Language::_("AdminClients.!success.draftinvoice_updated", true, $invoice->id_code);
							break;
						case "active":
							if ($invoice->status == "draft") {
								// Draft saved as new invoice
								$updated_invoice = $this->Invoices->get($invoice->id);
								$success_message = Language::_("AdminClients.!success.draftinvoice_created", true, $invoice->id_code, $updated_invoice->id_code);
							}
							break;
					}

					$this->flashMessage("message", $success_message);
					$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
				}
			}
		}

		// If this was an ajax request, send the response back
		if ($this->isAjax()) {
			$result = array('success'=>(!isset($errors) || !$errors));

			// If successful, return the entire invoice
			if ($result['success'])
				$result['invoice'] = $this->Invoices->get($invoice->id);
			$this->outputAsJson($result);
			return false;
		}

		// Set initial invoice
		if (empty($vars)) {
			// Set recurring invoice meta data
			if ($invoice->meta) {
				foreach ($invoice->meta as $i => $meta) {
					if ($meta->key == "recur") {
						$meta->value = unserialize(base64_decode($meta->value));

						foreach ($meta->value as $key => $value)
							$invoice->$key = $value;

						break;
					}
				}
			}
			$vars = clone $invoice;

			// Extract all tax rules applied to this invoice
			$tax_rules = array();
			foreach ($vars->line_items as &$item) {
				// Set this item as not taxable
				$item->tax = "false";

				if (!empty($item->taxes)) {
					// Set this item as taxable
					$item->tax = "true";

					foreach ($item->taxes as $tax)
						$tax_rules[$tax->level] = $tax->id;
				}
			}
			// If there are tax rules applied check if they'll be replaced if this invoice is updated
			if (!empty($tax_rules)) {
				$cur_tax_rules = $this->Invoices->getTaxRules($client->id);

				foreach ($cur_tax_rules as $tax) {
					if (!isset($tax_rules[$tax->level]) || $tax_rules[$tax->level] != $tax->id) {
						$this->setMessage("notice", Language::_("AdminClients.!notice.invoice_tax_rules_differ", true));
						break;
					}
				}
			}

			// Set delivery methods
			$delivery_methods = $this->Invoices->getDelivery($invoice->id, false);
			$delivery_methods = $this->ArrayHelper->keyToNumeric($delivery_methods, false);
			$vars->delivery = (!empty($delivery_methods['method']) ? $delivery_methods['method'] : "");
		}

		// Format dates
		$vars->date_billed = $this->Date->cast($vars->date_billed, "Y-m-d");
		$vars->date_due = $this->Date->cast($vars->date_due, "Y-m-d");

		$pricing_periods = $this->Invoices->getPricingPeriods();

		$this->set("client", $client);
		$this->set("delivery_methods", $this->Invoices->getDeliveryMethods($client->id));
		$this->set("periods", $pricing_periods);
		$this->set("vars", $vars);
		$this->set("invoice", $invoice);
		$this->set("currencies", $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code"));
		$this->structure->set("page_title", Language::_("AdminClients.editinvoice.page_title", true, $client->id_code, $invoice->id_code));

		$this->view($this->view->fetch("admin_clients_editinvoice"));
	}

    /**
     * Removes empty line items from an invoice so that it can be auto-saved without error, when possible
     * @see AdminClients::createInvoice(), AdminClients::editInvoice()
     *
     * @param string $status The status of the invoice. Only 'draft' line items are changed
     * @param array $lines A list of invoice line items
     * @return array A numerically-indexed array of line items given, minus those that have no description
     */
    private function removeEmptyLineItems($status, array $lines=array()) {
        // Remove blank line items so that we can continue to save a draft
        if ($status == "draft" && !empty($lines)) {
            foreach ($lines as $index => $line) {
                if (isset($line['description']) && empty($line['description']))
                    unset($lines[$index]);
            }
            $lines = array_values($lines);
        }

        return $lines;
    }

	/**
	 * Edit a recurring invoice
	 */
	public function editRecurInvoice() {
		$this->uses(array("Currencies", "Invoices"));
		$this->components(array("SettingsCollection"));
		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Ensure we have a client to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Ensure we have a invoice to load, and that it belongs to this client
		if (!isset($this->get[1]) || !($invoice = $this->Invoices->getRecurring((int)$this->get[1])) ||
			($invoice->client_id != $client->id))
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");

		$vars = array();

		// Edit an invoice
		if (!empty($this->post)) {
			$vars = $this->post;
			$vars['client_id'] = $client->id;
			$vars['duration'] = ($vars['duration'] == "indefinitely" ? null : $vars['duration_time']);

			// Structure line items
			$vars['lines'] = $this->ArrayHelper->keyToNumeric($vars['lines']);

			// Edit the invoice
			$this->Invoices->editRecurring($invoice->id, $vars);

			if (($errors = $this->Invoices->errors())) {
				// Error, reset vars
				$vars = $invoice;

				foreach ($this->post as $key => $value) {
					$vars->$key = $value;
				}
				$vars->line_items = $this->ArrayHelper->numericToKey($this->post['lines']);

				// Set line items to array of objects
				for ($i=0, $num_lines=count($vars->line_items); $i<$num_lines; $i++)
					$vars->line_items[$i] = (object)$vars->line_items[$i];

				$this->setMessage("error", $errors);
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminClients.!success.recurinvoice_updated", true, $invoice->id));
				$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
			}
		}

		// Set initial invoice
		if (empty($vars)) {
			$invoice->duration_time = $invoice->duration;
			$invoice->duration = ($invoice->duration > 0 ? "times" : "indefinitely");
			$vars = $invoice;

			// Set delivery methods
			$delivery_methods = $this->Invoices->getRecurringDelivery($invoice->id);
			$delivery_methods = $this->ArrayHelper->keyToNumeric($delivery_methods, false);
			$vars->delivery = (!empty($delivery_methods['method']) ? $delivery_methods['method'] : "");
		}

		// Format dates
		$vars->date_renews = $this->Date->cast($vars->date_renews, "Y-m-d");

		$pricing_periods = $this->Invoices->getPricingPeriods();

		$this->set("client", $client);
		$this->set("delivery_methods", $this->Invoices->getDeliveryMethods($client->id));
		$this->set("periods", $pricing_periods);
		$this->set("vars", $vars);
		$this->set("currencies", $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code"));
		$this->structure->set("page_title", Language::_("AdminClients.editrecurinvoice.page_title", true, $client->id_code, $invoice->id));

		if ($this->isAjax())
			return false;

		$this->view($this->view->fetch("admin_clients_editrecurinvoice"));
	}

	/**
	 * Sums line items and returns the sub total, total, and tax amount based on currency and company settings
	 * for the given set of data and tax rules that apply to each. Outputs a JSON encoded array including:
	 * 	-subtotal The decimal format for the subtotal
	 * 	-subtotal_formatted The currency format for the subtotal
	 * 	-tax The decimal format for the tax
	 * 	-tax_formatted The currency format for the tax
	 * 	-total The decimal format for the tax
	 * 	-total_formatted The currency format for the total
	 */
	public function calcLineTotals() {
		// This is an AJAX only method
		if (!$this->isAjax())
			return false;

		$client_id = isset($this->get[0]) ? $this->get[0] : null;

		// Require a client ID
		if (!$client_id)
			$this->redirect($this->base_uri . "clients/");

		$this->uses(array("Invoices"));
		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Reformat lines for use by our model
		$this->post['lines'] = $this->ArrayHelper->keyToNumeric($this->post['lines']);

		$this->outputAsJson($this->Invoices->calcLineTotals($client_id, $this->post));
		return false;
	}

	/**
	 * Deletes a draft invoice
	 */
	public function deleteDraftInvoice() {
		$this->uses(array("Invoices"));
		// Ensure we have a client to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Ensure we have a invoice to load, and that it belongs to this client
		if (!isset($this->get[1]) || !($invoice = $this->Invoices->get((int)$this->get[1])) ||
			($invoice->client_id != $client->id))
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");

		// Delete the invoice
		$this->Invoices->deleteDraft($invoice->id);

		if (($errors = $this->Invoices->errors())) {
			// Error, invoice was not a draft invoice
			$this->flashMessage("error", $errors);
		}
		else {
			// Success, draft deleted
			$this->flashMessage("message", Language::_("AdminClients.!success.deletedraftinvoice_deleted", true));
		}

		$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
	}

	/**
	 * Delete recurring invoice
	 */
	public function deleteRecurInvoice() {
		$this->uses(array("Invoices"));

		// Ensure the invoice exists
		if (!isset($this->get[0]) || !($invoice = $this->Invoices->getRecurring($this->get[0])) || !($client = $this->Clients->get($invoice->client_id)))
			$this->redirect($this->base_uri . "clients/view/");

		$this->Invoices->deleteRecurring($this->get[0]);

		$this->flashMessage("message", Language::_("AdminClients.!success.recurinvoice_deleted", true));
		$this->redirect($this->base_uri . "clients/view/" . $invoice->client_id);
	}

	/**
	 * Streams the given invoice to the browser
	 */
	public function viewInvoice() {
		$this->uses(array("Invoices"));

		// Ensure we have an invoice to load, and that it belongs to this client
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])) ||
			!isset($this->get[1]) || !($invoice = $this->Invoices->get((int)$this->get[1])) ||
			($invoice->client_id != $client->id))
			$this->redirect($this->base_uri . "clients/");

		// Download the invoice in the admin's language
		$this->components(array("InvoiceDelivery"));
		$this->InvoiceDelivery->downloadInvoices(array($invoice->id), array('language' => Configure::get("Blesta.language")));
		exit;
	}

	/**
	 * Edit a Transaction
	 */
	public function editTransaction() {
		$this->uses(array("Transactions"));

		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Ensure we have a transaction to load, and that it belongs to this client
		if (!isset($this->get[1]) || !($transaction = $this->Transactions->get((int)$this->get[1])) ||
			($transaction->client_id != $client->id))
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");

		$vars = new stdClass();

		if (!empty($this->post)) {
			// If set to, attempt to process the void/return through the gateway
			if ($transaction->gateway_id && isset($this->post['status']) && ($this->post['status'] == "void" || $this->post['status'] == "refunded") && isset($this->post['process_remotely'])) {
				$this->uses(array("Payments"));

				switch ($this->post['status']) {
					case "void":
						$this->Payments->voidPayment($client->id, $transaction->id, array('staff_id'=>$this->Session->read("blesta_staff_id")));
						break;
					case "refunded":
						$this->Payments->refundPayment($client->id, $transaction->id, null, array('staff_id'=>$this->Session->read("blesta_staff_id")));
						break;
				}

				if (($errors = $this->Payments->errors()))
					$this->setMessage("error", $errors);
				else {
					// Success
					$this->flashMessage("message", Language::_("AdminClients.!success.edittransaction_updated", true));
					$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
				}
			}
			else {
				$this->Transactions->edit($transaction->id, $this->post, $this->Session->read("blesta_staff_id"));

				if (($errors = $this->Transactions->errors()))
					$this->setMessage("error", $errors);
				else {
					// Success
					$this->flashMessage("message", Language::_("AdminClients.!success.edittransaction_updated", true));
					$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
				}
			}

			$vars = (object)$this->post;
		}
		else
			$vars = $transaction;

		$applied = $this->Transactions->getApplied($this->get[1]);

		if ($applied)
			$this->setMessage("notice", Language::_("AdminClients.!notice.transactions_already_applied", true));

		$this->set("transaction", $transaction);
		$this->set("applied", $applied);
		$this->set("vars", $vars);
		// Holds the name of all of the transaction types
		$this->set("transaction_types", $this->Transactions->transactionTypeNames());
		// Holds the name of all of the transaction status values
		$this->set("transaction_status", $this->Transactions->transactionStatusNames());
		$this->view($this->view->fetch("admin_clients_edittransaction"));
	}

	/**
	 * Unapplies a transaction from the given invoice
	 */
	public function unapplyTransaction() {
		$this->uses(array("Transactions"));

		if (!isset($this->get[0]) || !isset($this->get[1]) || !($transaction = $this->Transactions->get($this->get[0])) ||
			!($client = $this->Clients->get($transaction->client_id)))
			$this->redirect($this->base_uri . "clients/");

		$this->Transactions->unApply($transaction->id, array($this->get[1]));

		if (($errors = $this->Transactions->errors()))
			$this->flashMessage("error", $errors);
		else
			$this->flashMessage("message", Language::_("AdminClients.!success.transaction_unapplied", true));

		$this->redirect($this->base_uri . "clients/edittransaction/" . $client->id . "/" . $transaction->id . "/");
	}

	/**
	 * Sets Restricted Packages
	 */
	public function packages() {
		$this->uses(array("Packages"));

		// Ensure we have a client to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Save restricted package access
		if (!empty($this->post)) {
			// Set restricted package access
			$package_ids = isset($this->post['package_ids']) ? array_values($this->post['package_ids']) : array();
			$this->Clients->setRestrictedPackages($client->id, $package_ids);

			if (($errors = $this->Clients->errors())) {
				// Error, reset vars
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminClients.!success.packages_restricted", true));
				$this->redirect($this->base_uri . "clients/packages/" . $client->id . "/");
			}
		}

		// Set currently restricted package access
		if (empty($vars)) {
			$vars = new stdClass();
			$vars->package_ids = $this->Form->collapseObjectArray($this->Clients->getRestrictedPackages($client->id), "package_id", "package_id");
		}

		$this->set("vars", $vars);
		$this->set("packages", $this->Packages->getAll($this->company_id, array('name'=>"ASC"), "restricted"));

		$this->view($this->view->fetch("admin_clients_packages"));
	}

	/**
	 * Add a service
	 */
	public function addService() {
		$this->uses(array("Services", "Packages", "PackageGroups", "ModuleManager"));
		$step = "basic";

		// Ensure we have a client to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Convert group_package parameter and redirect
		if (isset($this->get['group_package'])) {
			list($this->get[1], $this->get[2]) = explode("_", $this->get['group_package']);

			$params = null;
			if (isset($this->get['parent_service_id']))
				$params = "/?parent_service_id=" . $this->get['parent_service_id'];

			$this->redirect($this->base_uri . "clients/addservice/" . $client->id . "/" . $this->get[1] . "/" . $this->get[2] . $params);
		}

		// If package selected, request service info
		if (isset($this->get[1]) && isset($this->get[2]) && ($package = $this->Packages->get((int)$this->get[2])) &&
			$package->company_id == $this->company_id) {

			// Get the package group to use
			$package_group = $this->PackageGroups->get((int)$this->get[1]);

			$order_info = isset($this->post['order_info']) ? unserialize(base64_decode($this->post['order_info'])) : null;
			$this->post = array_merge((array)$order_info, $this->post);
			unset($this->post['order_info']);

			if (isset($order_info['step']))
				$step = $order_info['step'];

			if (isset($this->post['step']) && $this->post['step'] == "edit")
				$step = $this->post['step'];

			if (!empty($this->post))
				$step = $this->processServiceStep($step, $package, $package_group, $client);

			$this->renderServiceStep($step, $package, $package_group, $client);
		}
		// List all packages available
		else {
			$this->listPackages($client->id);
		}

		$this->view($this->view->fetch("admin_clients_addservice"));
	}

	/**
	 * Edit service
	 */
	public function editService() {

		$this->uses(array("Currencies", "Invoices", "Services", "ServiceChanges", "Packages", "PackageOptions", "ModuleManager", "Coupons"));

		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Ensure we have a client to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Ensure we have a service
		if (!isset($this->get[1]) || !($service = $this->Services->get((int)$this->get[1])) || $service->client_id != $client->id)
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");

		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);
		$statuses = $this->Services->getStatusTypes();

		$vars = (object)$this->ArrayHelper->numericToKey($service->fields, "key", "value");
		$vars->pricing_id = $service->pricing_id;
		$vars->qty = $service->qty;
		$vars->date_canceled = $this->Date->cast(strtotime("+1 day", strtotime(date("c"))), "Y-m-d");

		if ($service->coupon_id && ($coupon = $this->Coupons->get($service->coupon_id)))
			$vars->coupon_code = $coupon->code;

		// Determine whether a recurring coupon applies to this service
		$recurring_coupon = false;
		if ($service->coupon_id && $service->date_renews) {
			$recurring_coupon = $this->Coupons->getRecurring($service->coupon_id, $service->package_pricing->currency, $service->date_renews . "Z");
		}

        // Set price override fields
        $vars->price_override = ($service->override_price !== null && $service->override_currency !== null ? "true" : "false");
        $vars->override_price = $service->override_price;
        $vars->override_currency = $service->override_currency;
        if (empty($service->override_currency) && isset($service->package_pricing->currency))
            $vars->override_currency = $service->package_pricing->currency;

		// Set any alternative module rows that the service may be changed to
		$module_row_name = $module->moduleRowName();
		$module_row_ids = array();

		// Alternative module rows must belong to the same group
		$module_rows = $this->ModuleManager->getRows($package->module_id, $package->module_group);
		if ($package->module_group !== null) {
			$module_rows = $this->ModuleManager->getRows($package->module_id, $package->module_group);

			if (count($module_rows) > 1) {
				$module_row_meta_key = $module->moduleRowMetaKey();
				foreach ($module_rows as $index => $row) {
					// Set the current module row ID
					if ($service->module_row_id == $row->id)
						$vars->module_row_id = $row->id;
					// Set module row fields
					if (property_exists($row, "meta") && property_exists($row->meta, $module_row_meta_key))
						$module_row_ids[$row->id] = $row->meta->{$module_row_meta_key};
				}
			}
		}

		// Set whether to allow the renew date to be changed. i.e. not if the service is a one-time service
		$allow_renew_date = true;
		if (isset($service->package_pricing->period) && $service->package_pricing->period == "onetime")
			$allow_renew_date = false;

		// If date canceled is set, set cancellation fields
		if ($service->date_canceled && $service->status != "canceled") {
			$vars->action = "schedule_cancel";
			$vars->cancel = ($service->date_canceled == $service->date_renews ? "term" : "date");

			//if ($vars->cancel == "date")
			//	$vars->date_canceled = $this->Date->cast($service->date_canceled, "Y-m-d");
		}
		
		// Retrieve all current pending service changes for this service
		$queued_changes = $this->getQueuedServiceChanges($service->id);
		// Determine whether to queue the service change or process it immediately
		$queue_service_changes = ($client->settings['process_paid_service_changes'] == "true");

		// Detect module refresh fields
		$refresh_fields = isset($this->post['refresh_fields']) && $this->post['refresh_fields'] == "true";

		// Add pending/in_review
		if ($service->status == "pending" || $service->status == "in_review") {

			if (!empty($this->post)) {
				// Set unchecked checkboxes
				if (!isset($this->post['use_module']))
					$this->post['use_module'] = "false";
				
				// Force status of in_review to not be changed. It must be done from a plugin instead
				if ($service->status == "in_review") {
					$this->post['status'] = "in_review";
				}

				if (!$refresh_fields) {
					// Always set config options so that they can be removed if no longer valid
					$this->post['configoptions'] = (isset($this->post['configoptions']) ? $this->post['configoptions'] : array());

					// Update the pending service
					$this->Services->edit($service->id, $this->post, false, (isset($this->post['notify_order']) && $this->post['notify_order'] == "true"));

					if (($error = $this->Services->errors()))
						$this->setMessage("error", $error);
					else {
						$this->flashMessage("message", Language::_("AdminClients.!success.service_" . ($service->status == "in_review" ? "edited" : "added"), true));
						$this->redirect($this->base_uri . "clients/view/" . $client->id);
					}
				}

				$vars = (object)$this->post;
				$vars->notify_order = (isset($vars->notify_order) ? $vars->notify_order : "false");
			}

			$service_fields = $module->getAdminAddFields($package, $vars);
		}
		// Suspend/Unsuspend/Cancel/Change Package&Module Options
		else {

			if (!$refresh_fields && !empty($this->post) && isset($this->post['section']) && $service->status != "canceled") {
				// Set staff ID for logging (un)suspension
				$this->post['staff_id'] = $this->Session->read("blesta_staff_id");

				// Do not use the module if not set
				if (!isset($this->post['use_module']))
					$this->post['use_module'] = "false";

				switch ($this->post['section']) {
					case "action":
						switch ($this->post['action']) {
							case "suspend":
								$this->Services->suspend($service->id, $this->post);
								break;
							case "unsuspend":
								$this->Services->unsuspend($service->id, $this->post);
								break;
							case "cancel":
								// Cancel right now
								$this->post['date_canceled'] = date("c");
								$this->Services->cancel($service->id, $this->post);
								break;
							case "schedule_cancel":

								if (isset($this->post['cancel']) && $this->post['cancel'] == "term")
									$this->post['date_canceled'] = "end_of_term";

								// Remove scheduled cancellation
								if (isset($this->post['cancel']) && $this->post['cancel'] == "none")
									$this->Services->unCancel($service->id);
								// Process cancellation
								else
									$this->Services->cancel($service->id, $this->post);
								break;
							case "change_renew":
								// Do not attempt to change the renew date of a one-time service
								if ($allow_renew_date) {
									unset($this->post['date_canceled'], $this->post['cancel']);
									$data = $this->post;

									// Set all config options for any proration that may occur
									$data = array_merge($data, $this->PackageOptions->formatServiceOptions($service->options));

									$this->Services->edit($service->id, $data, true);
								}
								break;
						}
						break;
					default:
					case "information":
						// Module row to change to must be a valid row ID
						if (isset($this->post['module_row_id']) && !array_key_exists($this->post['module_row_id'], $module_row_ids))
							break;
					case "package":
                        // Set any price overrides for this service
                        $data = $this->post;
                        if (isset($this->post['price_override']) && $this->post['price_override'] == "true") {
                            $data['override_price'] = (!empty($this->post['override_price']) ? $this->post['override_price'] : null);
                            $data['override_currency'] = (!empty($this->post['override_price']) && !empty($this->post['override_currency']) ? $this->post['override_currency'] : null);

                            // Cannot change package/term
                            unset($data['pricing_id']);
                        }
                        else {
                            // Reset price overrides
                            $data['override_price'] = null;
                            $data['override_currency'] = null;
                        }

						$data['coupon_id'] = $this->getCouponId($this->post['coupon_code']);

						// Always set config options so that they can be removed if no longer valid
						$data['configoptions'] = (isset($data['configoptions']) ? $data['configoptions'] : array());

						// Cancel any pending service change
						$this->cancelServiceChanges($service->id);
						
						// Queue the service change. It must have an invoice, so it must be prorated,
						// otherwise the service change cannot be queued
						if ($queue_service_changes && isset($data['prorate']) && $data['prorate'] == "true") {
							// Determine the pricing currency
							$pricing = $service->package_pricing;
							if (isset($data['pricing_id']) && ($package = $this->Packages->getByPricingId($data['pricing_id']))) {
								foreach ($package->pricing as $price) {
									if ($price->id == $data['pricing_id']) {
										$pricing = $price;
										break;
									}
								}
							}
							
							// Queue the service change
							$items = $this->ServiceChanges->getItems($service->id, $data);
							$totals = $this->Invoices->getItemTotals($items['items'], $items['discounts'], $items['taxes']);
							$result = $this->updateServiceChange($service, $data, $totals['items'], $pricing->currency);
							
							// Set any errors
							if ($result['errors']) {
								$error = $result['errors'];
							}
							elseif ($totals['totals']->total < 0 && $client->settings['client_prorate_credits'] == "true") {
								// Create a credit if the amount invoiced is less than 0
								$transaction_id = $this->createCredit($client->id, abs($totals['totals']->total), $pricing->currency);
							}
						}
						else {
							$this->Services->edit($service->id, $data);
						}
						
                        break;
				}

				if (isset($error) || ($error = $this->Services->errors()))
					$this->setMessage("error", $error);
				else {
					$this->flashMessage("message", Language::_("AdminClients.!success.service_edited", true));
					$this->redirect($this->base_uri . "clients/view/" . $client->id);
				}
			}
			$vars = (object)array_merge((array)$vars, $this->post);

			$service_fields = $module->getAdminEditFields($package, $vars);
		}

		// Populate module service fields
		$fields = $service_fields->getFields();
		$html = $service_fields->getHtml();
		$compatible_packages = $this->Packages->getCompatiblePackages($package->id, $package->module_id, $service->parent_service_id ? "addon" : "standard");

		// If no compatible packages are available, the package itself is the only compatible package
		if (empty($compatible_packages)) {
			$compatible_packages = array($package);
		}

		$terms = array();
		foreach ($compatible_packages as $pack) {
			$terms['package_' . $pack->id] = array('name' => $pack->name, 'value' => "optgroup");
			$terms = $terms + $this->getPackageTerms($pack);
		}

		$actions = array('' => Language::_("AppController.select.please", true)) + $this->Services->getActions($service->status);
		// Remove the option to change the renew date
		if (!$allow_renew_date)
			unset($actions['change_renew']);

		// Get tabs
		$admin_tabs = $module->getAdminTabs($package);

		$tabs = array(
			array(
				'name' => Language::_("AdminClients.editservice.tab_basic", true),
				'attributes' => array('href' => $this->base_uri . "clients/editservice/" . $client->id . "/" . $service->id . "/", 'class' => "ajax"),
				'current' => true
			)
		);

		// Set tabs only if the service has not been canceled
		if ($service->status != "canceled") {
			foreach ($admin_tabs as $action => $name) {
				$tabs[] = array(
					'name' => $name,
					'attributes' => array('href' => $this->base_uri . "clients/servicetab/" . $client->id . "/" . $service->id . "/" . $action . "/", 'class' => "ajax")
				);
			}
		}

		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;
		$this->set("periods", $periods);

		// Set addon packages
		$package_options = array();
		$package_attributes = array();
		if ($service->package_group_id)
			list($package_options, $package_attributes) = $this->listPackages($client->id, $service->package_group_id, true);

        // Set the expected service renewal price
        $service->renewal_price = $this->Services->getRenewalPrice($service->id);

		$module_name = $module->getName();
        $currencies = $this->Currencies->getAll($this->company_id);
		$this->set("form", $this->partial("admin_clients_editservice_" . ($service->status == "pending" || $service->status == "in_review" ? "pending" : "basic"), compact("currencies", "periods", "service", "package", "module_name", "fields", "html", "actions", "terms", "vars", "package_options", "package_attributes", "module_row_ids", "module_row_name", "statuses", "recurring_coupon")));

		$this->set("service", $service);
		$this->set("package", $package);
		$this->set("module_name", $module->getName());
		$this->set("tabs", $tabs);

		// Show an activation message regarding the service in review
		$notice_messages = array();
		if ($service->status == "in_review") {
			$notice_messages['in_review'] = array(Language::_("AdminClients.!notice.service_in_review", true, $statuses['in_review'], $statuses['pending']));
		}

		// Display a notice regarding this service having queued service changes
		if (!empty($queued_changes)) {
			$notice_messages['queued_service_change'] = array(Language::_("AdminClients.!notice.queued_service_change", true));
		}
		
		if (!empty($notice_messages)) {
			$this->setMessage("notice", $notice_messages);
		}
		
		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : false);
		$this->view($this->view->fetch("admin_clients_editservice"));
	}
	
	/**
	 * Retrieves a list of pending service changes queued
	 *
	 * @param int $service_id The ID of the service whose queued service changes to fetch
	 * @return array An array of all pending service changes
	 */
	private function getQueuedServiceChanges($service_id) {
		$this->uses(array("ServiceChanges"));
		return $this->ServiceChanges->getAll("pending", $service_id);
	}
	
	/**
	 * Cancel any pending queued service changes
	 *
	 * @param int $service_id The Id of the service whose pending service changes to cancel
	 */
	private function cancelServiceChanges($service_id) {
		$this->uses(array("Invoices", "ServiceChanges", "Transactions"));
		
		// Cancel any pending service changes
		$queued_changes = $this->getQueuedServiceChanges($service_id);
		foreach ($queued_changes as $change) {
			// Fetch payments applied to the invoice
			$transactions = $this->Transactions->getApplied(null, $change->invoice_id);
			
			// Unapply payments from the invoice
			foreach ($transactions as $transaction) {
				$this->Transactions->unapply($transaction->id, array($change->invoice_id));
			}
			
			// Void the invoice
			$this->Invoices->edit($change->invoice_id, array('status' => "void"));
			
			// Cancel the service change
			$this->ServiceChanges->edit($change->id, array('status' => "canceled"));
		}
	}
	/**
	 * Updates a service to remove any pending service changes, and to add a new one
	 *
	 * @param stdClass $service An stdClass object representing the service
	 * @param array $vars An array of input data to include for the queued service
	 * @param array $items An array of items, each including:
	 * 	- description The item description
	 * 	- price The item unit price
	 * 	- qty The quantity of the item
	 * 	- discounts An array af discounts
	 * 	- taxes An array of taxes
	 * @param string $currency The ISO 4217 currency code
	 * @return array An array containing:
	 * 	- invoice_id The invoice ID created
	 * 	- service_change_id The service change ID created
	 * 	- errors An array of any errors
	 */
	private function updateServiceChange($service, array $vars, array $items, $currency) {
		// Cancel any pending service changes
		$this->cancelServiceChanges($service->id);
		
		// Queue the new service change
		return $this->queueServiceChange($service->client_id, $service->id, $currency, $items, $vars);
	}
	
	/**
	 * Queue's a service change by creating an invoice for it and queuing it for later processing
	 *
	 * @param int $client_id The ID of the client
	 * @param int $service_id The ID of the service being queued
	 * @param string $currency The ISO 4217 currency code
	 * @param array $items An array of items, each including:
	 * 	- description The item description
	 * 	- price The item unit price
	 * 	- qty The quantity of the item
	 * 	- discounts An array af discounts
	 * 	- taxes An array of taxes
	 * @param array $vars An array of all data to queue to successfully update a service
	 * @return array An array of queue info, including:
	 * 	- invoice_id The ID of the invoice, if created
	 * 	- service_change_id The ID of the service change, if created
	 * 	- errors An array of errors
	 */
	private function queueServiceChange($client_id, $service_id, $currency, array $items, array $vars) {
		$this->uses(array("Invoices", "ServiceChanges"));
		
		// Set invoice delivery method
		$delivery = array();
		if (($client = $this->Clients->get($client_id)) && isset($client->settings['inv_method'])) {
			$delivery[] = $client->settings['inv_method'];
		}
		
		// Invoice and queue the service change
		$invoice_vars = array(
			'client_id' => $client_id,
			'date_billed' => date("c"),
			'date_due' => date("c"),
			'currency' => $currency,
			'lines' => $this->Invoices->makeLinesFromItems(array('items' => $items)),
			'delivery' => $delivery
		);
		
		// Create the invoice 
		$invoice_id = $this->Invoices->add($invoice_vars);
		$change_id = null;
		$errors = $this->Invoices->errors();
		
		// Queue the service change
		if (empty($errors)) {
			unset($vars['prorate']);
			$change_vars = array('data' => $vars);
			$change_id = $this->ServiceChanges->add($service_id, $invoice_id, $change_vars);
			$errors = $this->ServiceChanges->errors();
		}
		
		return array(
			'invoice_id' => $invoice_id,
			'service_change_id' => $change_id,
			'errors' => $errors
		);
	}
	
	/**
	 * Creates an in-house credit for the client
	 *
	 * @param int $client_id The ID of the client to credit
	 * @param float $amount The amount to credit
	 * @param string $currency The ISO 4217 currency code for the credit
	 * @return int $transaction_id The ID of the transaction for this credit
	 */
	private function createCredit($client_id, $amount, $currency) {
		$this->uses(array("Transactions"));
		
		// Apply the credit to the client account
		$vars = array(
			'client_id' => $client_id,
			'amount' => $amount,
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
	 * Deletes a pending service
	 */
	public function deleteService() {
		$this->uses(array("Services"));

		// Ensure we have a client to load
		if (!isset($this->post['client_id']) || !($client = $this->Clients->get((int)$this->post['client_id'], false)))
			$this->redirect($this->base_uri . "clients/");

		// Ensure we have a pending service
		if (!isset($this->post['id']) || !($service = $this->Services->get((int)$this->post['id'])) ||
			$service->client_id != $client->id)
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");

		// Set URI to be redirected to
		$redirect_uri = (isset($this->post['redirect_uri']) ? $this->post['redirect_uri'] : $this->base_uri . "clients/view/" . $client->id . "/");

		// Delete the service
		$this->Services->delete($service->id);

		if (($errors = $this->Services->errors()))
			$this->flashMessage("error", $errors);
		else
			$this->flashMessage("message", Language::_("AdminClients.!success.service_deleted", true));

		$this->redirect($redirect_uri);
	}

	/**
	 * Service tab request
	 */
	public function serviceTab() {

		$this->uses(array("Services", "Packages", "ModuleManager"));

		// Ensure we have a client to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Ensure we have a service
		if (!isset($this->get[1]) || !($service = $this->Services->get((int)$this->get[1])) || $service->client_id != $client->id || !isset($this->get[2]))
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");

		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);
		$module->base_uri = $this->base_uri;

		$method = $this->get[2];

		// Load/process the tab request
		$tab_view = "";
		if (is_callable(array($module, $method))) {
			// Set the module row used for this service
			$module->setModuleRow($module->getModuleRow($service->module_row_id));

		 	$tab_view = $module->{$method}($package, $service, $this->get, $this->post, $this->files);

			if (($errors = $module->errors()))
				$this->setMessage("error", $errors);
			elseif (!empty($this->post))
				$this->setMessage("success", Language::_("AdminClients.!success.service_tab", true));
		}

		$this->set("tab_view", $tab_view);

		// Get tabs
		$admin_tabs = $module->getAdminTabs($package);

		$tabs = array(
			array(
				'name' => Language::_("AdminClients.editservice.tab_basic", true),
				'attributes' => array('href' => $this->base_uri . "clients/editservice/" . $client->id . "/" . $service->id . "/", 'class' => "ajax")
			)
		);
		foreach ($admin_tabs as $action => $name) {
			$tabs[] = array(
				'name' => $name,
				'attributes' => array('href' => $this->base_uri . "clients/servicetab/" . $client->id . "/" . $service->id . "/" . $action . "/", 'class' => "ajax"),
				'current' => strtolower($action) == strtolower($method)
			);
		}

		$this->set("service", $service);
		$this->set("package", $package);
		$this->set("tabs", $tabs);

		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : false);
		$this->view($this->view->fetch("admin_clients_servicetab"));
	}

	/**
	 * Service Info
	 */
	public function serviceInfo() {

		$this->uses(array("Services", "Packages", "ModuleManager"));

		// Ensure we have a client to load
		if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "clients/");

		// Ensure we have a service
		if (!isset($this->get[1]) || !($service = $this->Services->get((int)$this->get[1])) || $service->client_id != $client->id)
			$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");

		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);

		if ($module) {
			$module->base_uri = $this->base_uri;
			$module->setModuleRow($module->getModuleRow($service->module_row_id));
			$this->set("content", $module->getAdminServiceInfo($service, $package));
		}

		// Set any addon services
		$this->set("services", $this->Services->getAllChildren($service->id));

		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;
		$this->set("periods", $periods);
		$this->set("client", $client);

		echo $this->outputAsJson($this->view->fetch("admin_clients_serviceinfo"));
		return false;
	}

	/**
	 * Fetch all packages options for the given pricing ID and optional service ID
	 */
	public function packageOptions() {
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "clients/");

		$this->uses(array("Services", "Packages", "PackageOptions"));

		$package = $this->Packages->getByPricingId($this->get[0]);

		if (!$package)
			return false;

		$pricing = null;
		foreach ($package->pricing as $pricing) {
			if ($pricing->id == $this->get[0])
				break;
		}

		$vars = array();
		if (isset($this->get['service_id'])) {
			$options = $this->Services->getOptions($this->get['service_id']);
			$vars = array_merge($vars, $this->PackageOptions->formatServiceOptions($options));
		}

		$vars = (object)array_merge($this->get, $vars);

		$package_options = $this->PackageOptions->getFields($pricing->package_id, $pricing->term, $pricing->period, $pricing->currency, $vars);

		$this->set("fields", $package_options->getFields());

		echo $this->outputAsJson($this->view->fetch("admin_clients_packageoptions"));
		return false;
	}

	/**
	 * Process the requested step
	 *
	 * @param string $step The add services step to process
	 * @param stdClass $package A stdClass object representing the primary package being ordered
	 * @param stdClass $package_group A stdClass object representing the package group of the primary package being ordered
	 * @param stdClass $client A stdClass object representing the client for which the service is being added
	 * @return string The step to render
	 */
	private function processServiceStep($step, $package, $package_group, $client) {

		// Detect module refresh fields
		$refresh_fields = isset($this->post['refresh_fields']) && $this->post['refresh_fields'] == "true";

		switch ($step) {
			case "edit":
				$this->post = $this->post['item'];
				return "basic";
			default:
			case "basic":
				$item = array(
					'parent_service_id' => isset($this->post['parent_service_id']) ? $this->post['parent_service_id'] : null,
					'package_group_id' => $package_group->id,
					'pricing_id' => $this->post['pricing_id'],
					'module_row_id' => isset($this->post['module_row_id']) ? $this->post['module_row_id'] : null,
					'qty' => isset($this->post['qty']) ? $this->post['qty'] : 1,
					'client_id' => $client->id
				);

				// Reset notify order if not given
				if (!isset($this->post['notify_order']) || $this->post['notify_order'] != "true")
					$this->post['notify_order'] = "false";

				$this->post['item'] = array_merge($item, $this->post);
				unset($this->post['item']['addon']);

				if ($refresh_fields)
					return "basic";

				// Verify fields look correct in order to proceed
				$this->Services->validateService($package, $this->post['item']);
				if (($errors = $this->Services->errors())) {
					$this->setMessage("error", $errors);
					return "basic";
				}

				// Queue any addons
				$addons = array();
				// Display addon-step if any addons to add
				if (isset($this->post['addon']) && !empty($this->post['addon'])) {
					foreach ($this->post['addon'] as $group => $addon) {
						if ($addon['id'] == "")
							continue;

						$addons[] = array(
							'package_group_id' => $group,
							'package_id' => $addon['id']
						);
					}
				}

				unset($this->post['addon']);
				$this->post['queue'] = $addons;

				if (!empty($this->post['queue']))
					return "addon";

				// Display confirmation if no addons available
				return "confirm";

			case "addon":
				$addon_package = $this->Packages->get($this->post['queue'][0]['package_id']);

				$item = array(
					'parent_service_id' => null,
					'package_group_id' => $this->post['package_group_id'],
					'package_id' => $addon_package->id,
					'pricing_id' => $this->post['pricing_id'],
					'module_row_id' => isset($this->post['module_row_id']) ? $this->post['module_row_id'] : null,
					'qty' => isset($this->post['qty']) ? $this->post['qty'] : 1,
					'client_id' => $client->id
				);
				$this->post['queue'][0] = array_merge($item, $this->post);
				unset($this->post['queue'][0]['item'], $this->post['queue'][0]['queue']);

				if ($refresh_fields)
					return "addon";

				// Verify addon looks correct in order to proceed
				$this->Services->validateService($addon_package, $this->post['queue'][0]);
				if (($errors = $this->Services->errors())) {
					$this->setMessage("error", $errors);
				}
				else {
					$item = array_shift($this->post['queue']);
					if (!isset($this->post['item']['addons']))
						$this->post['item']['addons'] = array();
					$this->post['item']['addons'][] = $item;
				}

				// Display confirmation if no more addons to evaluate
				if (!isset($this->post['queue']) || empty($this->post['queue']))
					return "confirm";

				// Render next addon or same if error occurred
				return "addon";

			case "confirm":
				// Add services if not saving coupon...
				if (!array_key_exists("set_coupon", $this->post)) {
					$this->createService(
						array(
							'client_id' => $client->id,
							'coupon' => isset($this->post['coupon_code']) ? $this->post['coupon_code'] : null,
							'invoice_method' => $this->post['invoice_method'],
							'invoice_id' => isset($this->post['invoice_id']) ? $this->post['invoice_id'] : null,
							'notify_order' => isset($this->post['notify_order']) ? $this->post['notify_order'] : null
						),
						$this->post['item']
					);

					if (($errors = $this->Services->errors()))
						$this->setMessage("error", $errors);
					else {
						$this->flashMessage("message", Language::_("AdminClients.!success.service_added", true));
						$this->redirect($this->base_uri . "clients/view/" . $client->id . "/");
					}
				}
				return "confirm";

		}

		return $step;
	}

	/**
	 * Create a service and its related addons and create or append an invoice for said services
	 *
	 * @param array $details An array of service information including:
	 * 	- client_id The ID of the client to add the service item form
	 * 	- coupon An coupon code used
	 * 	- invoice_method 'none', 'create', 'append'
	 * 	- invoice_id The invoice ID to append to (if invoice_method is 'append')
	 * @param array $item An array of service item info including:
	 *	- parent_service_id The ID of the service this service is a child of (optional)
	 *	- package_group_id The ID of the package group this service was added from (optional)
	 *	- pricing_id The package pricing schedule ID for this service
	 *	- module_row_id The module row to add the service under (optional, default module will decide)
	 *	- use_module Whether or not to use the module when provisioning
	 *	- status The stauts of the service (active, canceled, pending, suspend, in_review)
	 *	- addons An array of addon items each including:
	 *		- package_group_id The ID of the package group this service was added from (optional)
	 *		- pricing_id The package pricing schedule ID for this service
	 *		- module_row_id The module row to add the service under (optional, default module will decide)
	 *		- use_module Whether or not to use the module when provisioning
	 *		- qty The quanity consumed by this service (optional, default 1)
	 *		- configoptions An array of key/value pair where each key is a package option ID and each value is its value
	 *		- * Any other service field data to pass to the module
	 *	- qty The quanity consumed by this service (optional, default 1)
	 *	- configoptions An array of key/value pair where each key is a package option ID and each value is its value
	 *	- * Any other service field data to pass to the module
	 */
	private function createService($details, $item) {
		$this->uses(array("Clients", "Invoices", "Services"));

		$currency = $this->Clients->getSetting($details['client_id'], "default_currency");
		$currency = $currency->value;
		$service_ids = array();
		$package_ids = array();
		$coupon_id = null;
		$addons = isset($item['addons']) ? $item['addons'] : array();
		unset($item['addons']);
		$items = array($item);
		foreach ($addons as $addon)
			$items[] = $addon;

		foreach ($items as $index => $item) {
			$package = $this->Packages->getByPricingId($item['pricing_id']);
			$package_ids[] = $package->id;

			// Set the currency to the currency of the selected base package
			if ($index == 0) {
				foreach ($package->pricing as $pricing) {
					if ($pricing->id == $item['pricing_id']) {
						$currency = $pricing->currency;
						break;
					}
				}
			}
		}

		if (isset($details['coupon'])) {
			$coupon_id = $this->getCouponId($details['coupon']);
		}

		$parent_service_id = null;
		$status = isset($items[0]['status']) ? $items[0]['status'] : "pending";
		$item_count = count($items);
		foreach ($items as $item) {

			if (!array_key_exists("parent_service_id", $item) || $item['parent_service_id'] == null)
				$item['parent_service_id'] = $parent_service_id;

			// Unset any fields that may adversely affect the Services::add() call
			unset($item['date_added'], $item['date_renews'],
				$item['date_last_renewed'], $item['date_suspended'],
				$item['date_canceled'], $item['notify_order'],
				$item['invoice_id'], $item['invoice_method']);

			$item['coupon_id'] = $coupon_id;
			$item['status'] = ($item_count > 1 && isset($item['parent_service_id']) ? ($status == "active" ? "pending" : $status) : $status);
			$item['client_id'] = $details['client_id'];
			$item['use_module'] = isset($item['use_module']) ? $item['use_module'] : "false";

			$notify = isset($details['notify_order']) && $details['notify_order'] == "true" && $item['status'] === "active";
			$service_id = $this->Services->add($item, $package_ids, $notify);

			if ($parent_service_id === null)
				$parent_service_id = $service_id;

			$service_ids[] = $service_id;
		}

		if (!empty($service_ids)) {
			if ($details['invoice_method'] == "create")
				$this->Invoices->createFromServices($details['client_id'], $service_ids, $currency, date("c"));
			elseif ($details['invoice_method'] == "append")
				$this->Invoices->appendServices($details['invoice_id'], $service_ids);
		}
	}

	/**
	 * Fetches the coupon ID for a given coupon code and package ID
	 *
	 * @param string $coupon_code The coupon code
	 * @return mixed The coupon ID if it exists, 0 if it does not exist, or null if no coupon code was given
	 */
	private function getCouponId($coupon_code) {
		$this->uses(array("Coupons"));
		$coupon_id = null;
		$coupon_code = trim($coupon_code);
		
		if ($coupon_code !== "") {
			if (($coupon = $this->Coupons->getByCode($coupon_code))) {
				$coupon_id = $coupon->id;
			}
			else {
				$coupon_id = 0;
			}
		}
		
		return $coupon_id;
	}

	/**
	 * Render each add service step
	 *
	 * @param string $step The add services step to render
	 * @param stdClass $package A stdClass object representing the primary package being ordered
	 * @param stdClass $package_group A stdClass object representing the package group of the primary package being ordered
	 * @param stdClass $client A stdClass object representing the client for which the service is being added
	 */
	private function renderServiceStep($step, $package, $package_group, $client) {
		$this->uses(array("PackageOptions"));

		if (!isset($this->Invoices))
			$this->uses(array("Invoices"));

		$this->post['step'] = $step;

		switch ($step) {
			default:
			case "basic":
				$terms = $this->getPackageTerms($package);

				$vars = new stdClass();
				// Default notify order to being checked
				$vars->notify_order = "true";
				$vars->use_module = "true";

				if (!empty($this->post)) {
					$vars = (object)$this->post;

					// Reset use_module if not given, or do not use the module if the status is not active
					if ((count($this->post) != 1 && !isset($vars->use_module)) ||
						(isset($vars->status) && $vars->status != "active"))
						$vars->use_module = "false";
				}

				$module = $this->ModuleManager->initModule($package->module_id, $this->company_id);
				if (!$module)
					return;
				$service_fields = $module->getAdminAddFields($package, $vars);
				$fields = $service_fields->getFields();

				$html = $service_fields->getHtml();
				$module_name = $module->getName();

				$this->set("package", $package);

				// Remove the In Review status from being a selectable status
				$status = $this->Services->getStatusTypes();
				unset($status['in_review']);

				$invoices = $this->Form->collapseObjectArray($this->Invoices->getAll($client->id, "open", array('date_due'=>"desc")), "id_code", "id");

				// Get all add-on groups (child "addon" groups for this package group)
				// And all packages in the group
				$addon_groups = $this->Packages->getAllAddonGroups($package_group->id);

				foreach($addon_groups as &$addon_group)
					$addon_group->packages = $this->Packages->getAllPackagesByGroup($addon_group->id);

				$parent_service_id = isset($this->get['parent_service_id']) ? $this->get['parent_service_id'] : null;

				$this->set("form", $this->partial("admin_clients_addservice_basic", compact("package", "fields", "html", "status", "module_name", "terms", "invoices", "addon_groups", "vars", "parent_service_id")));
				break;
			case "addon":
				$vars = (object)$this->post['queue'][0];

				$package = $this->Packages->get($this->post['queue'][0]['package_id']);
				$terms = $this->getPackageTerms($package);

				$module = $this->ModuleManager->initModule($package->module_id, $this->company_id);
				if (!$module)
					return;
				$module_name = $module->getName();

				$service_fields = $module->getAdminAddFields($package, $vars);
				$fields = $service_fields->getFields();
				$html = $service_fields->getHtml();

				// Set continuous post data
				unset($this->post['qty']);
				$order_info = base64_encode(serialize($this->post));

				$this->set("package", $package);
				$this->set("form", $this->partial("admin_clients_addservice_addon", compact("package", "fields", "html", "terms", "vars", "module_name", "order_info")));
				break;
			case "confirm":

				$vars = new stdClass();
				if (!empty($this->post))
					$vars = (object)$this->post;

				$addons = array();
				$item = (object)$vars->item;

				if (isset($item->addons))
					$addons = $item->addons;

				$package->terms = $this->getPackageTerms($package);

				// Get the package items
				$pricings = array(
					array(
						'pricing_id' => $item->pricing_id,
						'qty' => isset($item->qty) ? $item->qty : 1,
						'fees' => array("setup"),
						'configoptions' => isset($item->configoptions) ? $item->configoptions : array()
					)
				);

				// Determine the currency of the selected pricing
				$currency = null;
				foreach ($package->pricing as $pricing) {
					if ($pricing->id == $item->pricing_id) {
						$currency = $pricing->currency;
						break;
					}
				}

				$items = $this->Packages->getPackageItems($pricings, $client->id, $currency);

				foreach ($items as &$package_item) {
					$package_item->item = $item;
					$package_item->package_group = $package_group;
					$package_item->package_options = $this->PackageOptions->getByPackageId($package->id);
					$package_item->term = $this->getPackageTerm($package_item->pricing, $package_item->start_date, $package_item->end_date);

					foreach ($package_item->config_options as &$config_option) {
						$config_option->term = $this->getOptionTerm($package_item->pricing, $config_option->option->id, $config_option->value, $config_option->pricing->price, $package_item->start_date, $package_item->end_date);
					}
				}

				// Get the addons
				$addon_pricings = array();
				$addon_item_list = array();
				foreach ($addons as $key => &$addon) {
					$addon = (object)$addon;
					$package_group = $this->PackageGroups->get($addon->package_group_id);

					$addon_pricing = array(
						'qty' => isset($addon->qty) ? $addon->qty : 1,
						'pricing_id' => $addon->pricing_id,
						'fees' => array("setup"),
						'configoptions' => isset($addon->configoptions) ? $addon->configoptions : array()
					);

					$addon_pricings[] = $addon_pricing;
					$addon_items = $this->Packages->getPackageItems(array($addon_pricing), $client->id, $currency);
					foreach ($addon_items as &$addon_item) {
						$addon_item->item = $addon;
						$addon_item->package->terms = $this->getPackageTerms($addon_item->package);
						$addon_item->package_group = $package_group;
						$addon_item->package_options = $this->PackageOptions->getByPackageId($addon_item->package->id);
						$addon_item->term = $this->getPackageTerm($addon_item->pricing, $addon_item->start_date, $addon_item->end_date);

						foreach ($addon_item->config_options as &$config_option) {
							$config_option->term = $this->getOptionTerm($addon_item->pricing, $config_option->option->id, $config_option->value, $config_option->pricing->price, $addon_item->start_date, $addon_item->end_date);
						}
					}
					unset($addon_item);
					$addon_item_list += $addon_items;
				}

				$addons = $addon_item_list;
				$packages = array_merge($pricings, (isset($addon_pricings) ? $addon_pricings : array()));
				unset($addon_item_list, $addon_pricings, $pricings);

				$invoice = null;
				if ($vars->invoice_method == "append")
					$invoice = $this->Invoices->get($vars->invoice_id);

				$status = $this->Services->getStatusTypes();

				$coupon = isset($vars->coupon_code) ? $vars->coupon_code : null;
				$line_totals = $this->Packages->calcLineTotals($client->id, $packages, $coupon, null, null, $currency);

				// Remove the "set_coupon" flag
				unset($this->post['set_coupon']);

				// Set continuous post data
				$order_info = base64_encode(serialize($this->post));

				$this->set("package", $package);
				$this->set("form", $this->partial("admin_clients_addservice_confirm", compact("items", "package_group", "addons", "order_info", "addon_groups", "line_totals", "vars", "status", "invoice")));
				break;

		}

		// Set continuous post data
		$this->post['order_info'] = base64_encode(serialize($this->post));
	}

	/**
	 * Returns an array of all pricing terms for the given package
	 *
	 * @param stdClass $package A stdClass object representing the package to fetch the terms for
	 * @return array An array of key/value pairs where the key is the package pricing ID and the value is a string representing the price, term, and period.
	 */
	private function getPackageTerms(stdClass $package) {
		$singular_periods = $this->Packages->getPricingPeriods();
		$plural_periods = $this->Packages->getPricingPeriods(true);
		$terms = array();
		if (isset($package->pricing) && !empty($package->pricing)) {
			foreach ($package->pricing as $price) {

				if ($price->period == "onetime")
					$term = "AdminClients.addservice.term_onetime";
				else
					$term = "AdminClients.addservice.term";

				$terms[$price->id] = Language::_($term, true, $price->term, $price->term != 1 ? $plural_periods[$price->period] : $singular_periods[$price->period], $this->CurrencyFormat->format($price->price, $price->currency));
			}
		}
		return $terms;
	}

	/**
	 * Returns the pricing term for the given package pricing
	 *
	 * @param stdClass $package_pricing A stdClass object representing the package pricing
	 * @param string $start_date The pricing term start date (optional)
	 * @param string $end_date The pricing term end date (optional)
	 * @return string The formatted pricing term
	 */
	private function getPackageTerm(stdClass $package_pricing, $start_date = null, $end_date = null) {
		if (!isset($this->SettingsCollection))
			$this->components(array("SettingsCollection"));

		$singular_periods = $this->Packages->getPricingPeriods();
		$plural_periods = $this->Packages->getPricingPeriods(true);

		if ($package_pricing->period == "onetime")
			$term = "AdminClients.addservice.term_onetime";
		elseif ($start_date && $end_date)
			$term = "AdminClients.addservice.term_dated";
		else
			$term = "AdminClients.addservice.term";

		if ($term == "AdminClients.addservice.term_dated") {
			$date_format = $this->SettingsCollection->fetchSetting(null, $this->company_id, "date_format");
			$date_format = $date_format['value'];
			return Language::_($term, true, $package_pricing->term, $package_pricing->term != 1 ? $plural_periods[$package_pricing->period] : $singular_periods[$package_pricing->period], $this->CurrencyFormat->format($package_pricing->price, $package_pricing->currency), $this->Date->cast($start_date, $date_format), $this->Date->cast($end_date, $date_format));
		}
		return Language::_($term, true, $package_pricing->term, $package_pricing->term != 1 ? $plural_periods[$package_pricing->period] : $singular_periods[$package_pricing->period], $this->CurrencyFormat->format($package_pricing->price, $package_pricing->currency));
	}

	/**
	 * Returns the pricing term for the given option ID and value
	 *
	 * @param stdClass $package_pricing The package pricing
	 * @param int $option_id The package option ID
	 * @param object $value An stdClass object representing the package option value
	 * @param float $amount The amount to set for the pricing term (optional, defaults to the term's price)
	 * @param string $start_date The pricing term start date (optional)
	 * @param string $end_date The pricing term end date (optional)
	 * @return string The formatted pricing term
	 */
	private function getOptionTerm($package_pricing, $option_id, $value, $amount = null, $start_date = null, $end_date = null) {
		if (!isset($this->PackageOptions))
			$this->uses(array("PackageOptions"));
		if (!isset($this->SettingsCollection))
			$this->components(array("SettingsCollection"));

		$singular_periods = $this->Packages->getPricingPeriods();
		$plural_periods = $this->Packages->getPricingPeriods(true);

		if ($value && ($price = $this->PackageOptions->getValuePrice($value->id, $package_pricing->term, $package_pricing->period, $package_pricing->currency))) {
			if ($price->period == "onetime")
				$term = "AdminClients.addservice.term_onetime";
			elseif ($start_date && $end_date)
				$term = "AdminClients.addservice.term_dated";
			else
				$term = "AdminClients.addservice.term";

			$amount = ($amount !== null && $amount >= 0 ? $amount : $price->price);

			if ($term == "AdminClients.addservice.term_dated") {
				$date_format = $this->SettingsCollection->fetchSetting(null, $this->company_id, "date_format");
				$date_format = $date_format['value'];
				return Language::_($term, true, $price->term, $price->term != 1 ? $plural_periods[$price->period] : $singular_periods[$price->period], $this->CurrencyFormat->format($amount, $price->currency), $this->Date->cast($start_date, $date_format), $this->Date->cast($end_date, $date_format));
			}
			return Language::_($term, true, $price->term, $price->term != 1 ? $plural_periods[$price->period] : $singular_periods[$price->period], $this->CurrencyFormat->format($amount, $price->currency));
		}

		return null;
	}

	/**
	 * List all packages available to the client
	 *
	 * @param int $client_id The ID of the client whose available packages will be listed
	 * @param int $parent_group_id The ID of the parent group to list packages for
	 * @param boolean $return True to return the package options and package attributes arrays, false to set them in the view
	 * @return array An array of package options and package attributes arrays if $return is true
	 */
	private function listPackages($client_id = null, $parent_group_id = null, $return = false) {
		$this->uses(array("Packages", "PackageGroups"));

		// Get restricted packages available to the client
		$restricted_package_ids = array();
		if ($client_id)
			$restricted_package_ids = $this->Form->collapseObjectArray($this->Clients->getRestrictedPackages($client_id), "package_id", "package_id");

		$packages = array();
		if ($parent_group_id)
			$package_groups = $this->Packages->getAllAddonGroups($parent_group_id);
		else
			$package_groups = $this->PackageGroups->getAll($this->company_id, "standard");
		foreach ($package_groups as $package_group) {
			$temp_packages = $this->Packages->getAllPackagesByGroup($package_group->id);
			$groups = array();
			foreach ($temp_packages as $package) {
				if (!isset($groups[$package_group->id])) {
					$packages[$package->status][] = array('name' => $package_group->name, 'value' => "optgroup");
					$groups[$package_group->id] = true;
				}

				$attributes = array('name' => $package->name, 'value' => $package_group->id . "_" . $package->id);

				// Disable any restricted packages if they are not available to the client
				if ($client_id && $package->status == "restricted" && !array_key_exists($package->id, $restricted_package_ids))
					$attributes['temp_attr'] = array('disabled' => "disabled");

				$packages[$package->status][] = $attributes;
			}
		}

		$package_options = array();
		$package_attributes = array();
		foreach ($packages as $status => $packs) {
			$package_options[] = array('name' => Language::_("AdminClients.addservice.status." . $status, true), 'value' => $status);
			$package_attributes[$status] = array('class' => $status, 'disabled' => "disabled");

			foreach ($packs as $key => $package) {
				// Disable any restricted packages if they are not available to the client
				if (array_key_exists("temp_attr", $package)) {
					$package_attributes[$package['value']] = $package['temp_attr'];
					unset($package['temp_attr']);
				}

				$package_options[] = $package;
			}
		}

		if ($return)
			return array($package_options, $package_attributes);

		$this->set("package_options", $package_options);
		$this->set("package_attributes", $package_attributes);
	}

	/**
	 * AJAX Fetch all states belonging to a given country (json encoded ajax request)
	 */
	public function getStates() {
		$this->uses(array("States"));
		// Prepend "all" option to state listing
		$states = array();
		if (isset($this->get[0]))
			$states = (array)$this->Form->collapseObjectArray($this->States->getList($this->get[0]), "name", "code");

		echo $this->Json->encode($states);
		return false;
	}

	/**
	 * Render the given Client view element
	 *
	 * @param string $view The view to render
	 * @param boolean $content_only True to only return the content, false to render it
	 * @return mixed boolean false if this is an ajax request that can not be rendered within a structure, string containing the content to be rendered, or void if the content is rendered automatically
	 */
	private function renderClientView($view, $content_only=false) {

		$data = $this->view->fetch($view);

		// Return data to be set
		if ($content_only)
			return $data;

		// Render data for an ajax request
		if ($this->isAjax()) {
			echo $data;
			return false;
		}
		// Set data to be displayed with the AdminClients::view()
		else
			$this->view($data);
	}
}
?>