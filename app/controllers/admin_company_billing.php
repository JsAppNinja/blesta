<?php
/**
 * Admin Company Billing Settings
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminCompanyBilling extends AppController {

	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();

		// Require login
		$this->requireLogin();

		$this->uses(array("Companies", "Navigation"));
		$this->components(array("SettingsCollection"));

		Language::loadLang("admin_company_billing");

		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getCompany($this->base_uri))));
	}

	/**
	 * Billing settings page
	 */
	public function index() {
		$this->redirect($this->base_uri . "settings/company/billing/invoices/");
	}

	/**
	 * Billing/Payment Invoice and Charge Settings page
	 */
	public function invoices() {
		// Set a notice message if any client group has client group settings applied
		if ($this->clientGroupSettingsExist())
			$this->setMessage("notice", Language::_("AdminCompanyBilling.!notice.group_settings", true));

		// Update Invoice and Charge settings
		if (!empty($this->post)) {
			// Set checkbox settings if not given
			$checkboxes = array("autodebit", "client_set_invoice", "inv_suspended_services", "clients_cancel_services",
				"client_create_addons", "auto_apply_credits", "auto_paid_pending_services", "client_change_service_term",
				"client_prorate_credits", "client_change_service_package", "show_client_tax_id",
				"process_paid_service_changes", "inv_group_services");
			foreach ($checkboxes as $field) {
				if (empty($this->post[$field]))
					$this->post[$field] = "false";
			}

			$fields = array_merge(array("inv_days_before_renewal", "autodebit_days_before_due",
				"suspend_services_days_after_due", "autodebit_attempts", "cancel_service_changes_days"), $checkboxes);
			$this->Companies->setSettings($this->company_id, $this->post, $fields);

			$this->setMessage("message", Language::_("AdminCompanyBilling.!success.invoices_updated", true));
		}

		// Set invoice days and autodebit days drop down options
		$invoice_days = array(Language::_("AdminCompanyBilling.invoices.text_sameday", true));
		$autodebit_days = array(Language::_("AdminCompanyBilling.invoices.text_sameday", true));
		$suspend_days = array('never' => Language::_("AdminCompanyBilling.invoices.text_never", true));
		$autodebit_attempts = array();

		for ($i=1; $i<=Configure::get("Blesta.invoice_renewal_max_days"); $i++)
			$invoice_days[$i] = Language::_("AdminCompanyBilling.invoices.text_day" . (($i == 1) ? "" : "s"), true, $i);
		for ($i=1; $i<=Configure::get("Blesta.autodebit_before_due_max_days"); $i++)
			$autodebit_days[$i] = Language::_("AdminCompanyBilling.invoices.text_day" . (($i == 1) ? "" : "s"), true, $i);
		for ($i=1; $i<=Configure::get("Blesta.suspend_services_after_due_max_days"); $i++)
			$suspend_days[$i] = Language::_("AdminCompanyBilling.invoices.text_day" . (($i == 1) ? "" : "s"), true, $i);
		for ($i=1; $i<=30; $i++)
			$autodebit_attempts[$i] = $i;

		// Set variables for the partial billing form template
		$form_fields = array(
			'vars' => $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id),
			'invoice_days' => $invoice_days,
			'autodebit_days' => $autodebit_days,
			'suspend_days' => $suspend_days,
			'autodebit_attempts' => $autodebit_attempts,
			'service_change_days' => $autodebit_attempts
		);

		// Load the partial form template for this page
		$invoice_form = $this->partial("admin_company_billing_invoices_form", $form_fields);

		$this->set("invoice_form", $invoice_form);
	}

	/**
	 * Billing/Payment Invoice Customization Settings page
	 */
	public function customization() {

		$this->uses(array("InvoiceTemplateManager", "Languages", "Invoices"));
		$this->components(array("Upload"));

		$vars = array();

		if (!empty($this->post)) {
			// Set checkbox settings if not given
			$checkboxes = array(
				"inv_display_logo",
				"inv_display_companyinfo",
				"inv_display_paid_watermark",
				"inv_display_payments",
				"inv_display_due_date_draft",
				"inv_display_due_date_inv",
				"inv_display_due_date_proforma",
			);
			foreach ($checkboxes as $checkbox) {
				if (!isset($this->post[$checkbox]))
					$this->post[$checkbox] = "false";
			}

			$temp = $this->post['inv_mimetype'];
			$this->post['inv_mimetype'] = isset($this->post['inv_mimetype'][$this->post['inv_template']]) ? $this->post['inv_mimetype'][$this->post['inv_template']] : null;

			$this->Companies->validateCustomization($this->post);
			if (!($errors = $this->Companies->errors())) {

				// Remove inv_logo if set to do so
				if (isset($this->post['remove_inv_logo']) && $this->post['remove_inv_logo'] == "true") {
					$inv_logo = $this->SettingsCollection->fetchSetting($this->Companies, $this->company_id, "inv_logo");
					if (isset($inv_logo['value']) && file_exists($inv_logo['value'])) {
						unlink($inv_logo['value']);
						$this->post['inv_logo'] = "";
					}
				}
				// Remove non-setting post fields
				unset($this->post['remove_inv_logo']);

				// Remove inv_background if set to do so
				if (isset($this->post['remove_inv_background']) && $this->post['remove_inv_background'] == "true") {
					$inv_background = $this->SettingsCollection->fetchSetting($this->Companies, $this->company_id, "inv_background");
					if (isset($inv_background['value']) && file_exists($inv_background['value'])) {
						unlink($inv_background['value']);
						$this->post['inv_background'] = "";
					}
				}
				// Remove non-setting post fields
				unset($this->post['remove_inv_background']);

				// Handle file uploads
				if (isset($this->files) && !empty($this->files)) {
					$temp = $this->SettingsCollection->fetchSetting($this->Companies, $this->company_id, "uploads_dir");
					$upload_path = $temp['value'] . $this->company_id . DS . "invoices" . DS;

					$this->Upload->setFiles($this->files);
					// Create the upload path if it doesn't already exists
					$this->Upload->createUploadPath($upload_path);
					$this->Upload->setUploadPath($upload_path);

					if (!($errors = $this->Upload->errors())) {
						$expected_files = array("inv_logo", "inv_background");
						// Will overwrite existing file, which is exactly what we want
						$this->Upload->writeFiles($expected_files, true, $expected_files);
						$data = $this->Upload->getUploadData();

						foreach ($expected_files as $file) {
							if (isset($data[$file]))
								$this->post[$file] = $data[$file]['full_path'];
						}

						$errors = $this->Upload->errors();
					}
				}

				if (!$errors) {
					$fields = array("inv_format", "inv_draft_format", "inv_start",
						"inv_increment", "inv_pad_size", "inv_pad_str", "inv_terms",
						"inv_paper_size", "inv_template", "inv_mimetype",
						"inv_display_logo", "inv_display_companyinfo", "inv_display_paid_watermark",
						"inv_display_payments", "inv_logo", "inv_background",
						"inv_type", "inv_proforma_format", "inv_proforma_start",
						"inv_display_due_date_draft", "inv_display_due_date_inv",
						"inv_display_due_date_proforma"
					);
					foreach ($this->post as $key => $value) {
						if (strpos($key, "inv_font_") !== false)
							$fields[] = $key;
					}
					unset($key);

					$this->Companies->setSettings($this->company_id, $this->post, $fields);
					$this->setMessage("message", Language::_("AdminCompanyBilling.!success.customization_updated", true));
				}
			}

			if ($errors) {
				$this->setMessage("error", $errors);
				$vars = $this->post;
			}
		}

		// Set initial settings
		if (empty($vars))
			$vars = $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id);

		$this->set("vars", $vars);
		$this->set("inv_types", $this->Invoices->getTypes());
		$this->set("templates", $this->InvoiceTemplateManager->getAll());
		$this->set("paper_sizes", $this->InvoiceTemplateManager->getPaperSizes());
		$this->set("fonts", $this->InvoiceTemplateManager->getPdfFonts());
		$this->set("languages", $this->Languages->getAll($this->company_id));
	}

	/**
	 * Billing/Payment Notices Settings page
	 */
	public function notices() {
		// Set a notice message if any client group has client group settings applied
		if ($this->clientGroupSettingsExist())
			$this->setMessage("notice", Language::_("AdminCompanyBilling.!notice.group_settings", true));

		$this->uses(array("EmailGroups"));

		// Update Notice settings
		if (!empty($this->post)) {
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

			// Set missing checkboxes
			if (empty($this->post['send_payment_notices']))
				$this->post['send_payment_notices'] = "false";

			$fields = array("notice1", "notice2", "notice3", "notice_pending_autodebit", "send_payment_notices");
			$this->Companies->setSettings($this->company_id, $this->post, $fields);

			$this->setMessage("message", Language::_("AdminCompanyBilling.!success.notices_updated", true));
		}

		// Set the day range for notices in days
		$notice_range = array();
		$notice_range[] = Language::_("AdminCompanyBilling.notices.text_duedate", true);
		for ($i=1; $i<=Configure::get("Blesta.payment_notices_max_days"); $i++)
			$notice_range[$i] = Language::_("AdminCompanyBilling.notices.text_day" . (($i == 1) ? "" : "s"), true, $i);

		$notice_range = array_merge(array('disabled'=>Language::_("AdminCompanyBilling.notices.text_disabled", true)), $notice_range);

		// Get the email group IDs of each notice in order to link to it
		$email_group_actions = array("invoice_notice_first", "invoice_notice_second",
			"invoice_notice_third", "auto_debit_pending");
		$email_groups = array();
		foreach ($email_group_actions as $action)
			$email_groups[$action] = ($email_group = $this->EmailGroups->getByAction($action)) ? $email_group->id : null;

		// Set variables for the partial billing form template
		$form_fields = array(
			'vars' => $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id),
			'notice_range' => $notice_range,
			'email_templates' => $email_groups
		);

		// Load the partial form template for this page
		$notice_form = $this->partial("admin_company_billing_notices_form", $form_fields);

		$this->set("notice_form", $notice_form);
	}

	/**
	 * Accepted Payment Types for gateways
	 */
	public function acceptedTypes() {
		// Update accepted payment type settings
		if (!empty($this->post)) {
			// Set empty checkboxes
			if (empty($this->post['payments_allowed_cc']))
				$this->post['payments_allowed_cc'] = "false";
			if (empty($this->post['payments_allowed_ach']))
				$this->post['payments_allowed_ach'] = "false";

			// Do not save this placeholder value as a setting
			unset($this->post['update']);

			// Update settings
			$fields = array("payments_allowed_cc", "payments_allowed_ach");
			$this->Companies->setSettings($this->company_id, $this->post, $fields);

			$this->setMessage("message", Language::_("AdminCompanyBilling.!success.acceptedtypes_updated", true));
		}

		$this->set("vars", $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id));
	}

	/**
	 * Accepted invoice delivery methods
	 */
	public function deliveryMethods() {
		$this->uses(array("Invoices"));

		// Update accepted delivery methods
		if (!empty($this->post)) {
			// Set empty checkboxes
			if (empty($this->post['postalmethods_testmode']))
				$this->post['postalmethods_testmode'] = "false";
			if (empty($this->post['postalmethods_replyenvelope']))
				$this->post['postalmethods_replyenvelope'] = "false";

			// Always ensure email and paper are available (can not be disabled)
			$this->post['delivery_methods'][] = "email";
			$this->post['delivery_methods'] = base64_encode(serialize($this->post['delivery_methods']));

			// Update settings
			$fields = array("delivery_methods", "interfax_username", "interfax_password",
				"postalmethods_apikey", "postalmethods_testmode", "postalmethods_replyenvelope");
			$this->Companies->setSettings($this->company_id, $this->post, $fields);

			$this->setMessage("message", Language::_("AdminCompanyBilling.!success.deliverymethods_updated", true));
		}

		// Set all delivery methods available
		$vars = array_merge((array)$this->SettingsCollection->fetchSettings($this->Companies, $this->company_id), (array)$this->Invoices->getDeliveryMethods(null));

		$this->set("delivery_methods", $this->Invoices->getDeliveryMethods(null, null, false));
		$this->set("vars", $vars);
	}

	/**
	 * Billing/Payment Coupons page
	 */
	public function coupons() {
		$this->uses(array("Coupons"));

		// Set current page of results
		$page = (isset($this->get[0]) ? (int)$this->get[0] : 1);

		// Get the default currency
		$default_currency = $this->SettingsCollection->fetchSetting($this->Companies, $this->company_id, "default_currency");
		$default_currency = isset($default_currency['value']) ? $default_currency['value'] : "";

		// Get all coupons
		$coupons = $this->Coupons->getList($this->company_id, $page);
		$total_results = $this->Coupons->getListCount($this->company_id);

		$this->set("coupons", $coupons);
		$this->set("default_currency", $default_currency);

		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "settings/company/billing/coupons/[p]/",
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));

		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[0]));
	}

	/**
	 * Add a coupon
	 */
	public function addCoupon() {
		$this->uses(array("Coupons", "Currencies", "Packages"));

		// Set the default currency for discount options
		$company_settings = $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id);

		$default_currency = $company_settings['default_currency'];
		$vars = new stdClass();
		$vars->amounts = array(
			'currency' => array(
				$default_currency
			)
		);

		// Create coupon
		if (!empty($this->post)) {
			// Set empty fields to default values
			if (empty($this->post['max_qty']))
				$this->post['max_qty'] = "0";
			if (empty($this->post['status']))
				$this->post['status'] = "inactive";
			if (empty($this->post['apply_package_options']))
				$this->post['apply_package_options'] = "0";

			// Format coupon amounts for insertion
			$vars = $this->post;
			$amounts = array();
			if (!empty($this->post['amounts'])) {
				// Set all row amounts
				for ($i=0; $i<count($this->post['amounts']['currency']); $i++) {
					$amounts[$i]['currency'] = $this->post['amounts']['currency'][$i];
					$amounts[$i]['type'] = $this->post['amounts']['type'][$i];
					$amounts[$i]['amount'] = $this->post['amounts']['amount'][$i];
				}
				$vars['amounts'] = $amounts;
			}

			// Update coupon dates to encompase the entire day
			if (!empty($vars['start_date']))
				$vars['start_date'] .= " 00:00:00";
			if (!empty($vars['end_date']))
				$vars['end_date'] .= " 23:59:59";

			// Set company ID
			$vars['company_id'] = $this->company_id;

			// Add a coupon
			$this->Coupons->add($vars);

			if (($errors = $this->Coupons->errors())) {
				// Error, reset vars
				$vars = (object)$this->post;
				$this->setMessage("error", $errors);
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminCompanyBilling.!success.coupon_created", true));
				$this->redirect($this->base_uri . "settings/company/billing/coupons/");
			}
		}

		// Set package groups
		$package_groups = $this->Form->collapseObjectArray($this->Packages->getAllGroups($this->company_id), "name", "id");
		$all = array('' => Language::_("AdminCompanyBilling.addcoupon.text_all", true));
		$package_groups = $all + $package_groups;
		$package_attributes = array();
		$packages = $this->Packages->getAll($this->company_id);

		// Build the package option attributes
		foreach ($packages as $package) {
			$groups = $this->Packages->getAllGroups($this->company_id, $package->id);

			$group_ids = array();
			foreach ($groups as $group)
				$group_ids[] = "group_" . $group->id;

			if (!empty($group_ids))
				$package_attributes[$package->id] = array('class'=>implode(' ', $group_ids));
		}

		// Set the selected assigned packages
		if (!empty($vars->packages)) {
			$temp_packages = array_flip($vars->packages);
			$assigned_packages = array();

			// Find any assigned packages from the list of packages, and set them
			for ($i=0, $num_packages=count($packages); $i<$num_packages; $i++) {
				if (isset($temp_packages[$packages[$i]->id])) {
					// Set an assigned package
					$assigned_packages[$packages[$i]->id] = $packages[$i]->name;
					// Remove it from available packages
					unset($packages[$i]);
				}
			}
			$vars->packages = $assigned_packages;
		}

		$this->set("types", $this->Coupons->getAmountTypes());
		$this->set("currencies", $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code"));
		$this->set("package_groups", $package_groups);
		$this->set("packages", $this->Form->collapseObjectArray($packages, "name", "id"));
		$this->set("package_attributes", $package_attributes);

		$this->set("vars", $vars);

		$this->Javascript->setFile("date.min.js");
		$this->Javascript->setFile("jquery.datePicker.min.js");
		$this->Javascript->setInline("Date.firstDayOfWeek=" . ($company_settings['calendar_begins'] == "sunday" ? 0 : 1) . ";");
	}

	/**
	 * Edit a coupon
	 */
	public function editCoupon() {
		$this->uses(array("Coupons", "Currencies", "Packages"));
		$this->helpers(array("DataStructure"));
		$this->components(array("SettingsCollection"));

		// Create array helper
		$this->ArrayHelper = $this->DataStructure->create("Array");

		if (!isset($this->get[0]) || !($coupon = $this->Coupons->get((int)$this->get[0])))
			$this->redirect($this->base_uri . "settings/company/billing/coupons/");

		// Get the company settings
		$company_settings = $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id);

		$vars = array();

		// Edit coupon
		if (!empty($this->post)) {
			// Set empty fields to default values
			if (empty($this->post['max_qty']))
				$this->post['max_qty'] = "0";
			if (empty($this->post['status']))
				$this->post['status'] = "inactive";
			if (empty($this->post['apply_package_options']))
				$this->post['apply_package_options'] = "0";

			// Format coupon amounts for insertion
			$vars = $this->post;
			$amounts = array();
			if (!empty($this->post['amounts'])) {
				// Set all row amounts
				for ($i=0; $i<count($this->post['amounts']['currency']); $i++) {
					$amounts[$i]['currency'] = $this->post['amounts']['currency'][$i];
					$amounts[$i]['type'] = $this->post['amounts']['type'][$i];
					$amounts[$i]['amount'] = $this->post['amounts']['amount'][$i];
				}
				$vars['amounts'] = $amounts;
			}

			// Update coupon dates to encompass the entire day
			if (!empty($vars['start_date']))
				$vars['start_date'] .= " 00:00:00";
			if (!empty($vars['end_date']))
				$vars['end_date'] .= " 23:59:59";

			// Set company ID
			$vars['company_id'] = $this->company_id;

			// Edit a coupon
			$this->Coupons->edit($coupon->id, $vars);

			if (($errors = $this->Coupons->errors())) {
				// Error, reset vars
				$vars = (object)$this->post;
				$this->setMessage("error", $errors);
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminCompanyBilling.!success.coupon_updated", true));
				$this->redirect($this->base_uri . "settings/company/billing/coupons/");
			}
		}

		// Set current coupon and format amounts
		if (empty($vars)) {
			$vars = $coupon;
			$vars->amounts = $this->ArrayHelper->numericToKey($vars->amounts);

			// Update packages to only the package id
			foreach ($vars->packages as &$package) {
				$package = $package->package_id;
			}
			unset($package);
		}

		// Set package groups
		$package_groups = $this->Form->collapseObjectArray($this->Packages->getAllGroups($this->company_id), "name", "id");
		$all = array('' => Language::_("AdminCompanyBilling.editcoupon.text_all", true));
		$package_groups = $all + $package_groups;
		$package_attributes = array();
		$packages = $this->Packages->getAll($this->company_id);


		// Build the package option attributes
		foreach ($packages as $package) {
			$groups = $this->Packages->getAllGroups($this->company_id, $package->id);

			$group_ids = array();
			foreach ($groups as $group)
				$group_ids[] = "group_" . $group->id;

			if (!empty($group_ids))
				$package_attributes[$package->id] = array('class'=>implode(' ', $group_ids));
		}

		// Set the selected assigned packages
		if (!empty($vars->packages)) {
			$temp_packages = array_flip($vars->packages);
			$assigned_packages = array();

			// Find any assigned packages from the list of packages, and set them
			for ($i=0, $num_packages=count($packages); $i<$num_packages; $i++) {
				if (isset($temp_packages[$packages[$i]->id])) {
					// Set an assigned package
					$assigned_packages[$packages[$i]->id] = $packages[$i]->name;
					// Remove it from available packages
					unset($packages[$i]);
				}
			}
			$vars->packages = $assigned_packages;
		}

		// Do not format dates (again) after an error
		if (empty($errors)) {
			// Format dates
			$vars->start_date = $this->Date->cast($vars->start_date, "Y-m-d");
			$vars->end_date = $this->Date->cast($vars->end_date, "Y-m-d");
		}

		$this->set("types", $this->Coupons->getAmountTypes());
		$this->set("currencies", $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code"));
		$this->set("package_groups", $package_groups);
		$this->set("packages", $this->Form->collapseObjectArray($packages, "name", "id"));
		$this->set("package_attributes", $package_attributes);

		$this->set("vars", $vars);
		$this->set("coupon", $coupon);

		$this->Javascript->setFile("date.min.js");
		$this->Javascript->setFile("jquery.datePicker.min.js");
		$this->Javascript->setInline("Date.firstDayOfWeek=" . ($company_settings['calendar_begins'] == "sunday" ? 0 : 1) . ";");
	}

	/**
	 * Deletes a coupon
	 */
	public function deleteCoupon() {
		$this->uses(array("Coupons"));

		// Redirect if invalid coupon was given
		if (!isset($this->post['id']) || !($coupon = $this->Coupons->get((int)$this->post['id'])))
			$this->redirect($this->base_uri . "settings/company/billing/coupons");

		// Delete the coupon
		$this->Coupons->delete($coupon->id);

		$this->flashMessage("message", Language::_("AdminCompanyBilling.!success.coupon_deleted", true));
		$this->redirect($this->base_uri . "settings/company/billing/coupons/");
	}

	/**
	 * Checks if any client group has client-group-specific settings attached to it
	 *
	 * @return boolean True if a client group exists with client-group-specific settings for this company, false otherwise
	 */
	private function clientGroupSettingsExist() {
		$this->uses(array("ClientGroups"));

		// Get a list of all Client Groups belonging to this company
		$client_groups = $this->ClientGroups->getAll($this->company_id);

		// Check if any client groups have client-group-specific settings
		if (!empty($client_groups) && is_array($client_groups)) {
			$num_groups = count($client_groups);
			for ($i=0; $i<$num_groups; $i++) {
				// Fetch settings for a client group, ignoring any setting inheritence
				$settings = $this->SettingsCollection->fetchClientGroupSettings($client_groups[$i]->id, $this->ClientGroups, true);

				// If any client group settings exist, return
				if (!empty($settings)) {
					return true;
				}
			}
		}
		return false;
	}
}
