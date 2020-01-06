<?php
/**
 * Admin Billing Management
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminBilling extends AppController {
	
	/**
	 * Billing pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		// Require login
		$this->requireLogin();
		
		Language::loadLang(array("admin_billing"));
		
		// Set date picker
		$calendar_begins = $this->Companies->getSetting(Configure::get("Blesta.company_id"), "date_format");
		$this->Javascript->setFile("date.min.js");
		$this->Javascript->setFile("jquery.datePicker.min.js");
		$this->Javascript->setInline("Date.firstDayOfWeek=" . ($calendar_begins && $calendar_begins->value == "sunday" ? 0 : 1) . ";");
	}
	
	/**
	 * Billing overview
	 */
	public function index() {
		$this->uses(array("Invoices", "Staff"));
		$this->components(array("SettingsCollection"));
		$layout = $this->Staff->getSetting($this->Session->read("blesta_staff_id"), "billing_layout", $this->company_id);
		$layout = ($layout ? $layout->value : "layout1");
		
		// Check whether a passphrase is required or not for batch processing
		$temp = $this->SettingsCollection->fetchSetting(null, $this->company_id, "private_key_passphrase");
		$passphrase_required = (isset($temp['value']) && $temp['value'] != "");
		unset($temp);
		
		// Set all action items
		$actions = array(
			'printqueue' => array(
				'enabled' => false,
				'value' => $this->Invoices->getStatusCount(null, "to_print")
			),
			'batch' => array(
				'enabled' => false,
				'value' => $this->Invoices->getStatusCount(null, "to_autodebit")
			)
		);
		
		// Set whether to show the actions
		$show_actions = false;
		foreach ($actions as $key=>&$item) {
			if ($item['value'] > 0) {
				// Batch requires passphrase set
				if ($key == "batch" && ($passphrase_required == 1)) {
					$item['enabled'] = true;
					$show_actions = true;
				}
				else {
					$item['enabled'] = true;
					$show_actions = true;
				}
			}
		}
		
		$action_items = array(
			'show_actions' => $show_actions,
			'actions' => $actions
		);
		
		// Set the layout
		$this->set("content", $this->partial("admin_billing_" . $layout));
		$this->set("action_items", $action_items);
	}
	
	/**
	 * List invoices
	 */
	public function invoices() {
		$this->uses(array("Invoices"));
		
		// Set current page of results
		$status = (isset($this->get[0]) ? $this->get[0] : "open");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_billed");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");
		
		// Set the number of invoices of each type
		$status_count = array(
			'open' => $this->Invoices->getStatusCount(null),
			'closed' => $this->Invoices->getStatusCount(null, "closed"),
			'draft' => $this->Invoices->getStatusCount(null, "draft"),
			'void' => $this->Invoices->getStatusCount(null, "void"),
			'past_due' => $this->Invoices->getStatusCount(null, "past_due"),
			'recurring' => $this->Invoices->getRecurringCount(null),
			'pending' => $this->Invoices->getStatusCount(null, "pending")
		);
		
		if ($status == "recurring") {
			$sort = (isset($this->get['sort']) ? $this->get['sort'] : "id");
			$order = (isset($this->get['order']) ? $this->get['order'] : "desc");
			
			$invoices = $this->Invoices->getRecurringList(null, $page, array($sort => $order));
			$total_results = $this->Invoices->getRecurringListCount(null);
		}
		else {
			// Get invoices for the company
			$invoices = $this->Invoices->getList(null, $status, $page, array($sort => $order));
			$total_results = $this->Invoices->getListCount(null, $status);
		}
		
		$this->set("status", $status);
		$this->set("status_count", $status_count);
		$this->set("invoices", $invoices);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "billing/invoices/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order),
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[1]) || isset($this->get['sort']));
	}
	
	/**
	 * AJAX request for all transactions an invoice has applied
	 */
	public function invoiceApplied() {
		$this->uses(array("Clients","Invoices","Transactions"));
		
		$invoice = $this->Invoices->get($this->get[0]);
		
		// Ensure the invoice exists and this is an ajax request
		if (!$this->isAjax() || !$invoice) {
			header($this->server_protocol . " 403 Forbidden");
			exit();
		}
		
		$vars = array(
			'client'=>$this->Clients->get($invoice->client_id),
			'applied'=>$this->Transactions->getApplied(null, $this->get[0]),
			// Holds the name of all of the transaction types
			'transaction_types'=>$this->Transactions->transactionTypeNames()
		);
		
		// Send the template
		echo $this->partial("admin_billing_invoiceapplied", $vars);
		
		// Render without layout
		return false;
	}
	
	/**
	 * List transactions
	 */
	public function transactions() {
		
		$this->uses(array("Transactions"));
		
		// Set the number of transactions of each type
		$status_count = array(
			'approved' => $this->Transactions->getStatusCount(),
			'declined' => $this->Transactions->getStatusCount(null, "declined"),
			'void' => $this->Transactions->getStatusCount(null, "void"),
			'error' => $this->Transactions->getStatusCount(null, "error"),
			'pending' => $this->Transactions->getStatusCount(null, "pending"),
			'returned' => $this->Transactions->getStatusCount(null, "returned"),
			'refunded' => $this->Transactions->getStatusCount(null, "refunded")
		);
		
		$status = (isset($this->get[0]) ? $this->get[0] : "approved");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_added");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");
		
		$total_results = $this->Transactions->getListCount(null, $status);
		$this->set("transactions", $this->Transactions->getList(null, $status, $page, array($sort => $order)));
		$this->set("status", $status);
		$this->set("status_count", $status_count);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		// Holds the name of all of the transaction types
		$this->set("transaction_types", $this->Transactions->transactionTypeNames());
		// Holds the name of all of the transaction status values
		$this->set("transaction_status", $this->Transactions->transactionStatusNames());
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "billing/transactions/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order),
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[1]) || isset($this->get['sort']));
	}
	
	/**
	 * AJAX request for all invoices a transaction has been applied to
	 */
	public function transactionApplied() {
		$this->uses(array("Transactions"));
		
		$transaction = $this->Transactions->get($this->get[0]);
		
		// Ensure the transaction belongs to the client and this is an ajax request
		if (!$this->isAjax() || !$transaction) {
			header($this->server_protocol . " 403 Forbidden");
			exit();
		}
		
		$vars = array(
			'applied'=>$this->Transactions->getApplied($this->get[0])
		);
		
		// Send the template
		echo $this->partial("admin_billing_transactionapplied", $vars);
		
		// Render without layout
		return false;
	}
	
	/**
	 * Invoice Print Queue
	 */
	public function printQueue() {
		$this->uses(array("Invoices"));
		
		// Set vars to select all checkboxes
		$vars = new stdClass();
		$vars->print_all = 1;
		
		$status = (isset($this->get[0]) ? $this->get[0] : "to_print");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_due");
		$order = (isset($this->get['order']) ? $this->get['order'] : "asc");
		
		// Default to date sent order for already-printed invoices
		if ($status == "printed" && !isset($this->get[1]) && !isset($this->get['sort']) && !isset($this->get['order'])) {
			$sort = "delivery_date_sent";
			$order = "desc";
		}
		
		// Print invoices, or mark them printed
		if (!empty($this->post['print']) && is_array($this->post['print'])) {
			$invoice_ids = $this->post['print'];
			
			// Print invoices
			if ($this->post['mark_printed'] == "false") {
				$this->components(array("InvoiceDelivery"));
				$this->InvoiceDelivery->downloadInvoices($invoice_ids);
				exit;
			}
			elseif ($this->post['mark_printed'] == "true") {
				// Mark invoices printed
				$invoices_delivered = array();
				
				// Get the delivery info for each invoice, and mark them printed
				foreach ($invoice_ids as $invoice_id) {
					$invoice_delivery = $this->Invoices->getDelivery($invoice_id);
					
					// Update the invoice paper method as printed
					foreach ($invoice_delivery as $delivery) {
						if ($delivery->method == "paper") {
							// Mark invoice printed
							$this->Invoices->delivered($delivery->id, $this->company_id);
							break;
						}
					}
				}
				
				// Invoices marked printed
				$this->setMessage("message", Language::_("AdminBilling.!success.invoices_marked_printed", true));
			}
		}
		elseif (!empty($this->post) && empty($this->post['print'])) {
			// User attempted to print with no invoices selected
			$this->setMessage("error", Language::_("AdminBilling.!error.no_invoices_selected", true));
			
			// Set to not select any invoices
			$vars->print = array();
			$vars->print_all = 0;
		}
		
		// Set the number of invoices of each type
		$status_count = array(
			'to_print' => $this->Invoices->getStatusCount(null, "to_print"),
			'printed' => $this->Invoices->getStatusCount(null, "printed"),
		);
		
		// Get invoices for the company
		$invoices = $this->Invoices->getList(null, $status, $page, array($sort => $order));
		$total_results = $this->Invoices->getListCount(null, $status);
		
		$this->set("invoices", $invoices);
		$this->set("status_count", $status_count);
		$this->set("status", $status);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		$this->set("vars", $vars);
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "billing/printqueue/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order)
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[1]) || isset($this->get['sort']));
	}
	
	/**
	 * Batch process invoices
	 */
	public function batch() {
		$this->components(array("SettingsCollection"));
		
		// Fetch the passphrase value
		$passphrase = $this->SettingsCollection->fetchSetting(null, $this->company_id, "private_key_passphrase");
		$passphrase = (isset($passphrase['value']) ? $passphrase['value'] : null);
		
		$cron_key = $this->SettingsCollection->fetchSetting(null, $this->company_id, "cron_key");
		$cron_key = (isset($cron_key['value']) ? $cron_key['value'] : null);
		
		$this->set("batch_enabled", (!empty($passphrase)));
		$this->set("cron_key", $cron_key);
	}
	
	/**
	 * Verify the given passphrase is valid
	 */
	public function verifyPassphrase() {
		if (!$this->isAjax()) {
			header($this->server_protocol . " 403 Forbidden");
			exit();
		}
		
		$this->uses(array("Encryption"));
		
		$data = array('valid' => true);
		if (!$this->Encryption->verifyPassphrase($this->post['passphrase'])) {
			$data = array('valid' => false, 'message' => $this->setMessage("error", Language::_("AdminBilling.!error.invalid_passphrase", true), true));
		}
		
		$this->outputAsJson($data);
		return false;
	}
	
	/**
	 * List reports
	 *
	 * @deprecated since 3.5.0
	 */
	public function reports() {
		$this->redirect($this->base_uri . "reports");
	}
	
	/**
	 * List services
	 */
	public function services() {
		$this->uses(array("Packages", "Services"));
		
		if (!empty($this->post)) {
			if (($errors = $this->updateServices($this->post))) {
				$this->set("vars", (object)$this->post);
				$this->setMessage("error", $errors);
			} else {
				$term = "AdminBilling.!success.services_scheduled_";
				$term .= (isset($this->post['action_type']) && $this->post['action_type'] == "none" ? "uncancel" : "cancel");
				$this->setMessage("message", Language::_($term, true));
			}
		}
		
		// Set current page of results
		$status = (isset($this->get[0]) ? $this->get[0] : "active");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_added");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");
		
		// Set the number of packages of each type
		$status_count = array(
			'active' => $this->Services->getListCount(null, "active"),
			'canceled' => $this->Services->getListCount(null, "canceled"),
			'pending' => $this->Services->getListCount(null, "pending"),
			'suspended' => $this->Services->getListCount(null, "suspended"),
			'in_review' => $this->Services->getListCount(null, "in_review"),
			'scheduled_cancellation' => $this->Services->getListCount(null, "scheduled_cancellation")
		);
		
		// Build service actions
		$actions = array(
			'schedule_cancellation' => Language::_("AdminBilling.services.action.schedule_cancellation", true)
		);
		
		$this->set("status", $status);
		$this->set("status_count", $status_count);
		$this->set("services", $this->Services->getList(null, $status, $page, array($sort => $order)));
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		$this->set("actions", $actions);
		$total_results = $this->Services->getListCount(null, $status);
		
		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;
		$this->set("periods", $periods);
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "billing/services/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order),
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[1]) || isset($this->get['sort']));
	}
	
	/**
	 * Updates the given services to change their scheduled cancellation date
	 *
	 * @param array $data An array of POST data including:
	 * 	- service_ids An array of each service ID
	 * 	- action The action to perform, e.g. "schedule_cancelation"
	 * 	- action_type The type of action to perform, e.g. "term", "date"
	 * 	- date The cancel date if the action type is "date"
	 * @return mixed An array of errors, or false otherwise
	 */
	private function updateServices(array $data) {
		// Require authorization to update a client's service
		if (!$this->authorized("admin_clients", "editservice")) {
			$this->flashMessage("error", Language::_("AppController.!error.unauthorized_access", true));
			$this->redirect($this->base_uri . "billing/services/");
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
					$errors = array('error' => array('date' => Language::_("AdminBilling.!error.future_cancel_date", true)));
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
	 * AJAX 
	 */
	public function serviceInfo() {
		$this->uses(array("Services", "Packages", "ModuleManager"));
		
		// Ensure we have a service
		if (!isset($this->get[0]) || !($service = $this->Services->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "billing/services/");
		
		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);
		
		if ($module) {
			$module->base_uri = $this->base_uri;
			$module->setModuleRow($module->getModuleRow($service->module_row_id));
			$this->set("content", $module->getAdminServiceInfo($service, $package));
		}

		echo $this->outputAsJson($this->view->fetch("admin_billing_serviceinfo"));
		return false;
	}
	
	/**
	 * Export billing related data (transactions applied, transactions received,
	 * tax liability, invoice summary)
	 */
	public function export() {
		#
		# TODO: export
		#
	}
	
	/**
	 * Renders a box to select the dashboard layout to use, and sets it
	 */
	public function updateDashboard() {
		$billing_layout = null;
		$billing_layouts = array("layout1", "layout2", "layout3", "layout4");
		
		// Get the new dashboard layout if given
		if (isset($this->get[0]) && in_array($this->get[0], $billing_layouts))
			$billing_layout = $this->get[0];
		
		$this->uses(array("Staff"));
		// Ensure a valid staff member is set
		if (!($staff = $this->Staff->get($this->Session->read("blesta_staff_id"), $this->company_id)))
			$this->redirect($this->base_uri . "billing/");
			
		// Update dashboard layout
		if ($billing_layout != null) {
			// Update the billing dashboard layout
			$this->Staff->setSetting($staff->id, "billing_layout", $billing_layout);
			
			// Redirect to billing dashboard
			$this->redirect($this->base_uri . "billing/");
		}
		
		// Retrieve the current layout
		$current_layout = $this->Staff->getSetting($staff->id, "billing_layout", $this->company_id);
		
		// Set the default dashboard layout if one doesn't exist
		if (!$current_layout)
			$current_layout = $billing_layouts[0];
		else
			$current_layout = $current_layout->value;
		
		// Set all of the billing layouts
		$layouts = array();
		foreach ($billing_layouts as $layout) {
			$layouts[] = (object)array(
				'name' => $layout,
				'selected' => ($layout == $current_layout) ? true : false
			);
		}
		
		$this->set("layouts", $layouts);
		echo $this->view->fetch("admin_billing_updatedashboard");
		return false;
	}
	
	/**
	 * Enable/Disable widgets from appearing on the dashboard
	 */
	public function manageWidgets() {
		$this->uses(array("PluginManager", "Staff"));

		// Get all displayed widgets
		$active_widgets = $this->Staff->getBillingWidgetsState($this->Session->read("blesta_staff_id"), $this->company_id);
		
		if (!empty($this->post)) {
			
			if (is_array($this->post['widgets_on'])) {
				
				// If a widget isn't displayed it must be disabled
				foreach ($active_widgets as $key => $widget) {
					if (!in_array($key, $this->post['widgets_on']))
						$active_widgets[$key]['disabled'] = true;
				}
				
				// Set all widgets to be displayed
				foreach ($this->post['widgets_on'] as $key) {
					if (!isset($active_widgets[$key]))
						$active_widgets[$key] = array('open'=>true, 'section'=> "section1");
					else
						unset($active_widgets[$key]['disabled']);
				}

				// Update this staff member's billing widgets for this company
				$this->Staff->saveBillingWidgetsState($this->Session->read("blesta_staff_id"), $this->company_id, $active_widgets);
			}
			
			return false;
		}
		
		// Get all widgets installed for this location
		$installed_widgets = $this->PluginManager->getActions($this->company_id, "widget_staff_billing", true);
		
		$available_widgets = array();
		foreach ($installed_widgets as $widget) {
			$key = str_replace("/", "_", trim($widget->uri, "/"));
			$available_widgets[$key] = $this->PluginManager->get($widget->plugin_id, true);
		}
		
		// Move all currently displayed widgets from available to displayed
		$displayed_widgets = array();
		foreach ($active_widgets as $key => $widget) {
			if (isset($available_widgets[$key]) && !(isset($widget['disabled']) && $widget['disabled'])) {
				$displayed_widgets[$key] = $available_widgets[$key];
				unset($available_widgets[$key]);
			}
		}
		
		// All widgets available and not displayed
		$this->set("available_widgets", $available_widgets);
		// All widgets available and displayed
		$this->set("displayed_widgets", $displayed_widgets);
		
		echo $this->view->fetch("admin_billing_managewidgets");
		return false;
	}
}
?>