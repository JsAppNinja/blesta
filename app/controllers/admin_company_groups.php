<?php
/**
 * Admin Company Client Group Settings
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminCompanyGroups extends AppController {

	/**
	 * @var array Whitelist of client group settings fields
	 */
	private $client_group_fields = array(
		"inv_days_before_renewal", "autodebit_days_before_due",
		"suspend_services_days_after_due", "autodebit_attempts",
		"client_set_invoice", "inv_suspended_services",
		"clients_cancel_services", "client_create_addons",
		"auto_apply_credits", "auto_paid_pending_services",
		"client_change_service_term", "client_change_service_package",
		"delivery_methods", "notice1", "notice2", "notice3",
		"notice_pending_autodebit", "send_payment_notices",
		"autodebit", "show_client_tax_id", "client_prorate_credits",
		"process_paid_service_changes", "cancel_service_changes_days",
		"inv_group_services"
	);

	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();

		// Require login
		$this->requireLogin();

		$this->uses(array("ClientGroups", "Invoices", "Navigation"));
		$this->helpers(array("DataStructure", "Color"));

		Language::loadLang("admin_company_groups");

		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getCompany($this->base_uri))));

		// Load the color picker
		$this->Javascript->setFile("colorpicker.min.js");
	}

	/**
	 * Client Group settings
	 */
	public function index() {
		// Set current page of results
		$page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "name");
		$order = (isset($this->get['order']) ? $this->get['order'] : "asc");

		// Get client groups
		$this->set("groups", $this->ClientGroups->getList($this->company_id, $page, array($sort=>$order)));
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));

		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $this->ClientGroups->getListCount($this->company_id),
				'uri'=>$this->base_uri . "settings/company/groups/index/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order)
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));

		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[0]) || isset($this->get['sort']));
	}

	/**
	 * Add a new Client Group
	 */
	public function add() {
		// Load language for Admin Company Billing settings we are using as partials
		// on this page
		Language::loadLang("admin_company_billing");

		$this->uses(array("EmailGroups"));

		$vars = array();

		// Create a client group
		if (!empty($this->post)) {
			// Always ensure email is available (can not be disabled)
			$this->post['delivery_methods'][] = "email";
			$temp_delivery_methods = $this->post['delivery_methods'];
			$this->post['delivery_methods'] = base64_encode(serialize($this->post['delivery_methods']));

			// Set checkbox setting values if not given
			$checkboxes = array("autodebit", "client_set_invoice", "inv_suspended_services", "clients_cancel_services",
				"client_create_addons", "auto_apply_credits", "auto_paid_pending_services", "client_change_service_term",
				"client_prorate_credits", "client_change_service_package", "send_payment_notices", "show_client_tax_id",
				"process_paid_service_changes", "inv_group_services");
			foreach ($checkboxes as $field) {
				if (empty($this->post[$field]))
					$this->post[$field] = "false";
			}

			// Set notice values based on type (before or after)
			if (!empty($this->post['notice1']) && is_numeric($this->post['notice1'])) {
				if (!empty($this->post['notice1_type']))
					$this->post['notice1'] *= $this->post['notice1_type'];
			}
			if (!empty($this->post['notice2']) && is_numeric($this->post['notice2'])) {
				if (!empty($this->post['notice2_type']))
					$this->post['notice2'] *= $this->post['notice2_type'];
			}
			if (!empty($this->post['notice3']) && is_numeric($this->post['notice3'])) {
				if (!empty($this->post['notice3_type']))
					$this->post['notice3'] *= $this->post['notice3_type'];
			}

			// Remove notice types from being added as settings
			unset($this->post['notice1_type'], $this->post['notice2_type'], $this->post['notice3_type']);

			// Set company ID for this group
			$this->post['company_id'] = $this->company_id;

			// Create group
			$client_group_id = $this->ClientGroups->add($this->post);

			if (($errors = $this->ClientGroups->errors())) {
				// Error, reset vars
				$this->setMessage("error", $errors);

				// Reset the posted delivery methods
				$this->post['delivery_methods'] = $temp_delivery_methods;

				$vars = (object)$this->post;
				$settings = $this->post;
			}
			else {
				// Success, redirect
				$this->flashMessage("message", Language::_("AdminCompanyGroups.!success.add_created", true, $this->post['name']));

				// Set Client Group Settings
				if (!isset($this->post['use_company_settings']) || $this->post['use_company_settings'] != "true") {
					$this->ClientGroups->setSettings($client_group_id, $this->post, $this->client_group_fields);
				}

				$this->redirect($this->base_uri . "settings/company/groups/");
			}
		}

		// Retrieve the options for each drop-down field
		$select_fields = $this->getSelectFields();

		if (empty($vars)) {
			$vars = new stdClass();
			$vars->use_company_settings = true;
		}

		// Fetch the company settings to use for this initial group
		if (!isset($settings) || (isset($vars->use_company_settings) && $vars->use_company_settings == "true")) {
			$settings = $this->getSettings($vars);
			$vars->delivery_methods = unserialize(base64_decode($settings['delivery_methods']));
		}

		// Set variables for the partial billing invoices form template
		$invoice_form_fields = array(
			'vars' => $settings,
			'invoice_days' => $select_fields->invoice_days,
			'autodebit_days' => $select_fields->autodebit_days,
			'suspend_days' => $select_fields->suspend_days,
			'autodebit_attempts' => $select_fields->autodebit_attempts,
			'service_change_days' => $select_fields->service_change_days
		);

		// Get the email group IDs of each notice in order to link to it
		$email_group_actions = array("invoice_notice_first", "invoice_notice_second",
			"invoice_notice_third", "auto_debit_pending");
		$email_groups = array();
		foreach ($email_group_actions as $action)
			$email_groups[$action] = ($email_group = $this->EmailGroups->getByAction($action)) ? $email_group->id : null;

		// Set variables for the partial billing form template
		$notice_form_fields = array(
			'vars' => $settings,
			'notice_range' => $select_fields->notice_range,
			'email_templates' => $email_groups
		);

		// Load the partial form templates for this page
		$notice_form = $this->partial("admin_company_billing_notices_form", $notice_form_fields);
		$invoice_form = $this->partial("admin_company_billing_invoices_form", $invoice_form_fields);

		$this->set("notice_form", $notice_form);
		$this->set("invoice_form", $invoice_form);
		$this->set("vars", $vars);
		$this->set("delivery_methods", $this->Invoices->getDeliveryMethods(null, null, false));
	}

	/**
	 * Edit a Client Group
	 */
	public function edit() {
		// Redirect if invalid client group ID given
		if (!isset($this->get[0]) || !($group = $this->ClientGroups->get((int)$this->get[0]))
			|| ($group->company_id != $this->company_id))
			$this->redirect($this->base_uri . "settings/company/groups/");

		$this->uses(array("EmailGroups"));

		// Load language for Admin Company Billing settings we are using as partials
		// on this page
		Language::loadLang("admin_company_billing");

		$vars = array();

		// Edit a client group
		if (!empty($this->post)) {
			// Always ensure email is available (can not be disabled)
			$this->post['delivery_methods'][] = "email";
			$temp_delivery_methods = $this->post['delivery_methods'];
			$this->post['delivery_methods'] = base64_encode(serialize($this->post['delivery_methods']));

			// Set checkbox setting values if not given
			$checkboxes = array("autodebit", "client_set_invoice", "inv_suspended_services", "clients_cancel_services",
				"client_create_addons", "auto_apply_credits", "auto_paid_pending_services", "client_change_service_term",
				"client_prorate_credits", "client_change_service_package", "send_payment_notices", "show_client_tax_id",
				"process_paid_service_changes", "inv_group_services");
			foreach ($checkboxes as $field) {
				if (empty($this->post[$field]))
					$this->post[$field] = "false";
			}

			// Set notice values based on type (before or after)
			if (!empty($this->post['notice1']) && is_numeric($this->post['notice1'])) {
				if (!empty($this->post['notice1_type']))
					$this->post['notice1'] *= $this->post['notice1_type'];
			}
			if (!empty($this->post['notice2']) && is_numeric($this->post['notice2'])) {
				if (!empty($this->post['notice2_type']))
					$this->post['notice2'] *= $this->post['notice2_type'];
			}
			if (!empty($this->post['notice3']) && is_numeric($this->post['notice3'])) {
				if (!empty($this->post['notice3_type']))
					$this->post['notice3'] *= $this->post['notice3_type'];
			}

			// Remove notice types from being added as settings
			unset($this->post['notice1_type'], $this->post['notice2_type'], $this->post['notice3_type']);

			// Edit this client group
			$this->post['company_id'] = $group->company_id;
			$this->ClientGroups->edit($group->id, $this->post);

			// Check for errors
			if (($errors = $this->ClientGroups->errors())) {
				// Error, reset vars and settings
				$this->setMessage("error", $errors);

				// Reset the posted delivery methods
				$this->post['delivery_methods'] = $temp_delivery_methods;

				$vars = (object)$this->post;
				$settings = $this->post;
				$use_company_settings = (isset($this->post['use_company_settings']) && $this->post['use_company_settings'] == "true");
			}
			else {
				// Success, update client group settings and redirect
				$this->flashMessage("message", Language::_("AdminCompanyGroups.!success.edit_updated", true, $this->post['name']));

				// Set Client Group Settings
				if (!isset($this->post['use_company_settings']) || $this->post['use_company_settings'] != "true") {
					$this->ClientGroups->setSettings($group->id, $this->post, $this->client_group_fields);
				}
				else {
					// Remove Client Group Settings
					$this->ClientGroups->unsetSettings($group->id);
				}

				$this->redirect($this->base_uri . "settings/company/groups/");
			}
		}

		// Set current group
		if (empty($vars)) {
			$vars = $group;
			$vars = (object)array_merge((array)$vars, (array)$this->Invoices->getDeliveryMethods(null, $group->id));
		}

		// Set the client group settings and delivery methods
		if (!isset($settings)) {
			$settings = $this->getSettings($vars, $group->id);
			$vars->delivery_methods = unserialize(base64_decode($settings['delivery_methods']));
		}

		// Overwrite the "use_company_settings" field (if there was an error)
		$vars->use_company_settings = (isset($use_company_settings) ? $use_company_settings : $vars->use_company_settings);

		// Retrieve the options for each drop-down field
		$select_fields = $this->getSelectFields();

		// Set variables for the partial billing invoices form template
		$invoice_form_fields = array(
			'vars' => $settings,
			'invoice_days' => $select_fields->invoice_days,
			'autodebit_days' => $select_fields->autodebit_days,
			'suspend_days' => $select_fields->suspend_days,
			'autodebit_attempts' => $select_fields->autodebit_attempts,
			'service_change_days' => $select_fields->service_change_days
		);

		// Get the email group IDs of each notice in order to link to it
		$email_group_actions = array("invoice_notice_first", "invoice_notice_second",
			"invoice_notice_third", "auto_debit_pending");
		$email_groups = array();
		foreach ($email_group_actions as $action)
			$email_groups[$action] = ($email_group = $this->EmailGroups->getByAction($action)) ? $email_group->id : null;

		// Set variables for the partial billing form template
		$notice_form_fields = array(
			'vars' => $settings,
			'notice_range' => $select_fields->notice_range,
			'email_templates' => $email_groups
		);

		// Load the partial form templates for this page
		$notice_form = $this->partial("admin_company_billing_notices_form", $notice_form_fields);
		$invoice_form = $this->partial("admin_company_billing_invoices_form", $invoice_form_fields);

		$this->set("notice_form", $notice_form);
		$this->set("invoice_form", $invoice_form);
		$this->set("vars", $vars);
		$this->set("delivery_methods", $this->Invoices->getDeliveryMethods(null, null, false));
	}

	/**
	 * Delete a Client Group
	 */
	public function delete() {
		// Redirect if invalid client group ID given
		if (!isset($this->post['id']) || !($group = $this->ClientGroups->get((int)$this->post['id']))
			|| ($group->company_id != $this->company_id))
			$this->redirect($this->base_uri . "settings/company/groups/");

		// Delete the client group
		if ($this->ClientGroups->delete($group->id) !== false) {
			// Successful delete
			$this->flashMessage("message", Language::_("AdminCompanyGroups.!success.delete_deleted", true, $group->name));
		}
		else {
			// Error, cannot delete default group
			$this->flashMessage("error", Language::_("AdminCompanyGroups.!error.delete_failed", true, $group->name));
		}

		$this->redirect($this->base_uri . "settings/company/groups/");
	}

	/**
	 * Retrieves the client group settings and includes a "use_company_settings" field
	 * in $vars, useful for add/edit client groups
	 *
	 * @param stdClass $vars An stdClass object (referenced)
	 * @param int $group_id The client group ID (optional)
	 * @return array A key=>value array containing all of the client group settings
	 * @see AdminCompanyGroups::add(), AdminCompanyGroups::edit()
	 */
	private function getSettings(stdClass &$vars, $group_id=null) {
		// Get the client group settings (along with those inherited)
		if ($group_id != null)
			$client_group_settings = $this->ClientGroups->getSettings($group_id);
		else {
			// Get just the company settings instead
			$this->uses(array("Companies"));
			$client_group_settings = $this->Companies->getSettings($this->company_id);
		}

		// Check if this group is using any client group settings
		$vars->use_company_settings = "true";
		foreach ($client_group_settings as $client_group_setting) {
			if ($client_group_setting->level == "client_group") {
				$vars->use_company_settings = "false";
				break;
			}
		}

		return $this->ArrayHelper->numericToKey($client_group_settings, "key", "value");
	}

	/**
	 * Retrieves a list of select fields used for AdminCompanyGroups::add() and AdminCompanyGroups::edit()
	 *
	 * @return stdClass An stdClass object containing the arrays:
	 * 	-invoice_days
	 * 	-notice_range
	 * 	-autodebit_days
	 * 	-autodebit_attempts
	 */
	private function getSelectFields() {
		$fields = new stdClass();

		// Set invoice days and autodebit days drop down options for invoice and charge options
		$fields->invoice_days = array(Language::_("AdminCompanyBilling.invoices.text_sameday", true));
		$fields->autodebit_days = array(Language::_("AdminCompanyBilling.invoices.text_sameday", true));
		$fields->suspend_days = array('never' => Language::_("AdminCompanyBilling.invoices.text_never", true));
		$fields->autodebit_attempts = array();

		for ($i=1; $i<=Configure::get("Blesta.invoice_renewal_max_days"); $i++)
			$fields->invoice_days[$i] = Language::_("AdminCompanyBilling.invoices.text_day" . (($i == 1) ? "" : "s"), true, $i);
		for ($i=1; $i<=Configure::get("Blesta.autodebit_before_due_max_days"); $i++)
			$fields->autodebit_days[$i] = Language::_("AdminCompanyBilling.invoices.text_day" . (($i == 1) ? "" : "s"), true, $i);
		for ($i=1; $i<=Configure::get("Blesta.suspend_services_after_due_max_days"); $i++)
			$fields->suspend_days[$i] = Language::_("AdminCompanyBilling.invoices.text_day" . (($i == 1) ? "" : "s"), true, $i);
		for ($i=1; $i<=30; $i++)
			$fields->autodebit_attempts[$i] = $i;

		$fields->service_change_days = $fields->autodebit_attempts;

		// Set the day range in drop down options for payment due notices
		$fields->notice_range = array();
		$fields->notice_range[] = Language::_("AdminCompanyBilling.notices.text_duedate", true);
		for ($i=1; $i<=Configure::get("Blesta.payment_notices_max_days"); $i++)
			$fields->notice_range[$i] = Language::_("AdminCompanyBilling.notices.text_day" . (($i == 1) ? "" : "s"), true, $i);
		$fields->notice_range = array_merge(array('disabled'=>Language::_("AdminCompanyBilling.notices.text_disabled", true)), $fields->notice_range);

		return $fields;
	}
}
