<?php
/**
 * Client portal services controller
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ClientServices extends ClientController {

	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();

		// Load models, language
		$this->uses(array("Clients", "Packages", "Services"));
	}

	/**
	 * List services
	 */
	public function index() {
		$status = (isset($this->get[0]) ? $this->get[0] : "active");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_added");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");

		$services = $this->Services->getList($this->client->id, $status, $page, array($sort => $order), false);
		$total_results = $this->Services->getListCount($this->client->id, $status, false);

		// Set the number of services of each type, not including children
		$status_count = array(
			'active' => $this->Services->getStatusCount($this->client->id, "active", false),
			'canceled' => $this->Services->getStatusCount($this->client->id, "canceled", false),
			'pending' => $this->Services->getStatusCount($this->client->id, "pending", false),
			'suspended' => $this->Services->getStatusCount($this->client->id, "suspended", false),
		);

		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;

        // Set the expected service renewal price
        foreach ($services as $service) {
            $service->renewal_price = $this->Services->getRenewalPrice($service->id);
        }

		$this->set("periods", $periods);
		$this->set("client", $this->client);
		$this->set("status", $status);
		$this->set("services", $services);
		$this->set("status_count", $status_count);
		$this->set("widget_state", isset($this->widgets_state['services']) ? $this->widgets_state['services'] : null);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));

		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination_client"), array(
				'total_results' => $total_results,
				'uri'=>$this->Html->safe($this->base_uri . "services/index/" . $status . "/[p]/"),
				'params'=>array('sort'=>$sort,'order'=>$order)
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));

		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : (isset($this->get[1]) || isset($this->get['sort'])));
	}

	/**
	 * Manage service
	 */
	public function manage() {

		$this->uses(array("Coupons", "ModuleManager"));

		// Ensure we have a service
		if (!($service = $this->Services->get((int)$this->get[0])) || $service->client_id != $this->client->id)
			$this->redirect($this->base_uri);

		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);
		$module->base_uri = $this->base_uri;

		$method = isset($this->get[1]) ? $this->get[1] : null;

		// Set sidebar tabs
		$this->buildTabs($service, $package, $module, $method);

		// Get tabs
		$client_tabs = $module->getClientTabs($package);

		$tab_view = null;
		// Load/process the tab request
		if ($method && key_exists($method, $client_tabs) && is_callable(array($module, $method))) {
			// Disallow clients from viewing module tabs if the service is suspended/canceled
			if (in_array($service->status, array("suspended", "canceled"))) {
				$statuses = $this->Services->getStatusTypes();
				$this->flashMessage("error", Language::_("ClientServices.!error.module_tab_unavailable", true, $statuses[$service->status]));
				$this->redirect($this->base_uri . "services/manage/" . $service->id);
			}
			
			// Set the module row used for this service
			$module->setModuleRow($module->getModuleRow($service->module_row_id));

		 	$tab_view = $module->{$method}($package, $service, $this->get, $this->post, $this->files);

			if (($errors = $module->errors()))
				$this->setMessage("error", $errors);
			elseif (!empty($this->post))
				$this->setMessage("success", Language::_("ClientServices.!success.manage.tab_updated", true));
		}

		$this->set("tab_view", $tab_view);

		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;

		// Set whether the client can cancel a service
		// First check whether the service is already canceled
		if (!$service->date_canceled || ($service->date_canceled && strtotime($this->Date->cast($service->date_canceled, "date_time")) > strtotime(date("c")))) {
			// Service has not already been canceled, check whether the setting is enabled for clients to cancel services
			$client_cancel_service = $this->client->settings['clients_cancel_services'] == "true";
		}
		else {
			// Service is already canceled, can't cancel it again
			$client_cancel_service = false;
		}

        // Set whether the client can change the service term
        $client_change_service_term = isset($this->client->settings['client_change_service_term']) && $this->client->settings['client_change_service_term'] == "true";
        $alternate_service_terms = array();
        if ($client_change_service_term && isset($service->package_pricing->id) && isset($service->package_pricing->period) && $service->package_pricing->period != "onetime")
            $alternate_service_terms = $this->getPackageTerms($package, array($service->package_pricing->id));

		// Set whether the client can upgrade the service to another package in the same group
		$client_change_service_package = isset($this->client->settings['client_change_service_package']) && $this->client->settings['client_change_service_package'] == "true";
		if ($client_change_service_package) {
			$upgradable_packages = $this->getUpgradablePackages($package, ($service->parent_service_id ? "addon" : "standard"));
			$client_change_service_package = (!empty($upgradable_packages));
		}

		// Set whether the any config options are available to be added/updated
		$available_options = $this->getAvailableOptions($service);

		// Determine whether a recurring coupon applies to this service
		$recurring_coupon = false;
		if ($service->coupon_id && $service->date_renews) {
			$recurring_coupon = $this->Coupons->getRecurring($service->coupon_id, $service->package_pricing->currency, $service->date_renews . "Z");
		}

        // Set the expected service renewal price
        $service->renewal_price = $this->Services->getRenewalPrice($service->id);

		// Display a notice regarding this service having queued service changes
		$queued_changes = $this->pendingServiceChanges($service->id);
		if (!empty($queued_changes) && $this->queueServiceChanges()) {
			$this->setMessage("notice", Language::_("ClientServices.!notice.queued_service_change", true));
		}
		
		// Set partial for the service information box
		$service_params = array(
			'periods' => $periods,
			'service' => $service,
			'next_invoice_date' => $this->Services->getNextInvoiceDate($service->id),
			'client_cancel_service' => $client_cancel_service,
            'client_change_service_term' => $client_change_service_term,
			'client_change_service_package' => $client_change_service_package,
            'alternate_service_terms' => $alternate_service_terms,
			'available_config_options' => (!empty($available_options)),
			'recurring_coupon' => $recurring_coupon,
			'queued_service_change' => !empty($queued_changes)
		);
		$this->set("service_infobox", $this->partial("client_services_service_infobox", $service_params));

		$this->set("service", $service);
		$this->set("package", $package);

		// Display a notice regarding the service being suspended/canceled
		$error_notice = array();
		if (!empty($service->date_suspended))
			$error_notice[] = Language::_("ClientServices.manage.text_date_suspended", true, $this->Date->cast($service->date_suspended));
		if (!empty($service->date_canceled)) {
			$scheduled = false;
			if ($this->Date->toTime($this->Date->cast($service->date_canceled)) > $this->Date->toTime($this->Date->cast(date("c"))))
				$scheduled = true;
			$error_notice[] = Language::_("ClientServices.manage.text_date_" . ($scheduled ? "to_cancel" : "canceled"), true, $this->Date->cast($service->date_canceled));
		}
		if (!empty($error_notice))
			$this->setMessage("error", array('notice' => $error_notice));
		
		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : false);
	}

	/**
	 * Fetches a list of package options that are addable or editable for this service
	 *
	 * @param stdClass $service An stdClass object representing the service
	 * @return array An array of all addable and editable package options for the service
	 */
	private function getAvailableOptions($service) {
		// Fetch the package options that can be added or updated
		return $this->getSettableOptions($service->package->id, $service->package_pricing->term, $service->package_pricing->period, $service->package_pricing->currency, $service->options, $this->getSelectedOptionIds($service));
	}

	/**
	 * Fetches a set of available package option IDs for the given service that the user can add or update
	 * @see ClientServices::getAvailableOptions
	 *
	 * @param stdClass $service An stdClass object representing the service
	 * @return array A key/value array of available options where each key is the option ID
	 */
	private function getSelectedOptionIds($service) {
		$this->uses(array("PackageOptions"));

		// Create a list of option IDs currently set
		$option_ids = array();
		foreach ($service->options as $option) {
			$option_ids[] = $option->option_id;
		}

		// Fetch addable package options that don't currently exist
		$filters = array('addable' => 1, 'disallow' => $option_ids);
		$options = $this->PackageOptions->getAllByPackageId($service->package->id, $service->package_pricing->term, $service->package_pricing->period, $service->package_pricing->currency, null, $filters);

		// Key each addable option by ID
		$available_option_ids = array();
		foreach ($options as $option) {
			$available_option_ids[$option->id] = "";
		}

		return $available_option_ids;
	}

    /**
	 * Returns an array of all pricing terms for the given package that optionally recur and do not match the given pricing IDs
	 *
	 * @param stdClass $package A stdClass object representing the package to fetch the terms for
	 * @param array $pricing_ids An array of pricing IDs to exclude (optional)
	 * @param mixed $service An stdClass object representing the service (optional)
	 * @param boolean $remove_non_recurring_terms True to include only package terms that recur, or false to include all (optional, default true)
	 * @param boolean $match_periods True to only set terms that match the period set for the $service (i.e. recurring periods -> recurring periods OR one-time -> one-time) $remove_non_recurring_terms should be set to false when this is true (optional, default false)
	 * @return array An array of key/value pairs where the key is the package pricing ID and the value is a string representing the price, term, and period.
	 */
	private function getPackageTerms(stdClass $package, array $pricing_ids=array(), $service=null, $remove_non_recurring_terms=true, $match_periods=false) {
		$singular_periods = $this->Packages->getPricingPeriods();
		$plural_periods = $this->Packages->getPricingPeriods(true);
		$terms = array();
		if (isset($package->pricing) && !empty($package->pricing)) {
			foreach ($package->pricing as $price) {
                // Ignore non-recurring terms, and exclude the given pricing IDs
				if (($remove_non_recurring_terms && $price->period == "onetime") || in_array($price->id, $pricing_ids))
                    continue;

				// Check that the service period matches this term's period (i.e. recurring -> recurring OR one-time -> one-time)
				if ($match_periods && $service && !($service->package_pricing->period == $price->period || ($price->period != "onetime" && $service->package_pricing->period != "onetime")))
					continue;

                // Set the package pricing with to the service override values
                $amount = $price->price;
                $currency = $price->currency;
                if ($service && $service->pricing_id == $price->id && !empty($service->override_price) && !empty($service->override_currency)) {
                    $amount = $service->override_price;
                    $currency = $service->override_currency;
                }

				$terms[$price->id] = Language::_("ClientServices.get_package_terms.term", true, $price->term, $price->term != 1 ? $plural_periods[$price->period] : $singular_periods[$price->period], $this->CurrencyFormat->format($amount, $currency));
				if ($price->period == "onetime")
					$terms[$price->id] = Language::_("ClientServices.get_package_terms.term_onetime", true, $price->term != 1 ? $plural_periods[$price->period] : $singular_periods[$price->period], $this->CurrencyFormat->format($amount, $currency));
			}
		}
		return $terms;
	}

	/**
	 * Returns an array of packages that can be upgraded/downgraded from the same package group
	 *
	 * @param stdClass $package The package from which to fetch other upgradable packages
	 * @param string $type The type of package group ("standard" or "addon")
	 * @return array An array of stdClass objects representing packages in the same group
	 */
	private function getUpgradablePackages($package, $type) {
		if (!$package || empty($package->module_id))
			return array();

		$packages = $this->Packages->getCompatiblePackages($package->id, $package->module_id, $type);

		$restricted_packages = $this->Clients->getRestrictedPackages($this->client->id);
		$restricted_package_ids = array();
		foreach ($restricted_packages as $package_ids)
			$restricted_package_ids[] = $package_ids->package_id;

		foreach ($packages as $index => $temp_package) {
			// Remove unavailable restricted packages
			if ($temp_package->status == "inactive" || ($temp_package->status == "restricted" && !in_array($temp_package->id, $restricted_package_ids))) {
				unset($packages[$index]);
				continue;
			}

			// Remove the given package from the list since you cannot upgrade/downgrade to the identical package
			if ($package->id == $temp_package->id)
				unset($packages[$index]);
		}

		return array_values($packages);
	}

	/**
	 * Cancel Service
	 */
	public function cancel() {
		$this->uses(array("Currencies", "Users"));

		$client_can_cancel_service = isset($this->client->settings['clients_cancel_services']) && $this->client->settings['clients_cancel_services'] == "true";

		// Ensure we have a service that belongs to the client and is not currently canceled or suspended
		if (!$client_can_cancel_service || !($service = $this->Services->get((int)$this->get[0]))
			|| $service->client_id != $this->client->id
			|| in_array($service->status, array("canceled", "suspended"))) {
			if ($this->isAjax())
				exit();
			$this->redirect($this->base_uri);
		}

		if (!empty($this->post)) {
			$data = $this->post;

			// Verify that client's password is correct, set $errors otherwise
			if ($this->Users->auth($this->Session->read("blesta_id"), array('password' => $this->post['password']))) {
				// Cancel the service
				switch ($this->post['cancel']) {
					case "now":
						$this->Services->cancel($service->id, $data);
						break;
					case "term":
						// Cancel at end of service term
						$data['date_canceled'] = "end_of_term";
						$this->Services->cancel($service->id, $data);
						break;
					default:
						// Do not cancel
						$this->Services->unCancel($service->id);
						break;
				}
			}
			else {
				$errors = array('password' => array('mismatch' => Language::_("ClientServices.!error.password_mismatch", true)));
			}

			if (!empty($errors) || ($errors = $this->Services->errors())) {
				$this->flashMessage("error", $errors);
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("ClientServices.!success.service_" . ($this->post['cancel'] == "term" ? "schedule_" : ($this->post['cancel'] == "" ? "not_" : "")) . "canceled", true));
			}

			$this->redirect($this->base_uri . "services/manage/" . $service->id . "/");
		}

		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;

		// Set whether the client can cancel a service
		// First check whether the service is already canceled
		if (!$service->date_canceled || ($service->date_canceled && strtotime($this->Date->cast($service->date_canceled, "date_time")) > strtotime(date("c")))) {
			// Service has not already been canceled, check whether the setting is enabled for clients to cancel services
			$client_cancel_service = $this->client->settings['clients_cancel_services'] == "true";
		}
		else {
			// Service is already canceled, can't cancel it again
			$client_cancel_service = false;
		}

		// Set the cancellation to be at the end of the term
		if (!isset($vars))
			$vars = (object)array('cancel' => "");

		// Set the confirmation message for canceling the service
		$cancel_messages = array(
			'now' => Language::_("ClientServices.cancel.confirm_cancel_now", true),
			'term' => Language::_("ClientServices.cancel.confirm_cancel", true)
		);
		if (isset($service->package_pricing->cancel_fee) && $service->package_pricing->cancel_fee > 0) {
			// Get the client settings
			$client_settings = $this->SettingsCollection->fetchClientSettings($service->client_id);

			// Get the pricing info
			if ($client_settings['default_currency'] != $service->package_pricing->currency)
				$pricing_info = $this->Services->getPricingInfo($service->id, $client_settings['default_currency']);
			else
				$pricing_info = $this->Services->getPricingInfo($service->id);

			// Set the formatted cancellation fee and confirmation message
			if ($pricing_info) {
				$cancellation_fee = $this->Currencies->toCurrency($pricing_info->cancel_fee, $pricing_info->currency, $this->company_id);

				$cancel_messages['now'] = Language::_("ClientServices.cancel.confirm_cancel_now", true) . " " . Language::_("ClientServices.cancel.confirm_cancel_now_fee", true, $cancellation_fee);

				if ($pricing_info->tax)
					$cancel_messages['now'] = Language::_("ClientServices.cancel.confirm_cancel_now", true) . " " . Language::_("ClientServices.cancel.confirm_cancel_now_fee_tax", true, $cancellation_fee);
			}
		}

		foreach ($cancel_messages as $key => $message) {
			$cancel_messages[$key] = $this->setMessage("notice", $message, true);
		}

		$this->set("service", $service);
		$this->set("package", $this->Packages->get($service->package->id));
		$this->set("vars", $vars);
		$this->set("confirm_cancel_messages", $cancel_messages);

		echo $this->view->fetch("client_services_cancel");
		return false;
	}

	/**
	 * Change service term
	 */
	public function changeTerm() {
		$client_can_change_service_term = isset($this->client->settings['client_change_service_term']) && $this->client->settings['client_change_service_term'] == "true";

		// Ensure we have a service with alternate package terms available to change to
		if (!$client_can_change_service_term || !($service = $this->Services->get((int)$this->get[0])) || $service->client_id != $this->client->id ||
            $service->status != "active" || !empty($service->date_canceled) ||
			($service->package_pricing->period == "onetime") || !($package = $this->Packages->get($service->package->id)) ||
            !($terms = $this->getPackageTerms($package, array(), $service)) || empty($terms)) {
			$this->redirect($this->base_uri);
		}
		
		// Changes may not be made to the service while a pending change currently exists
		$queued_changes = $this->pendingServiceChanges($service->id);
		if (!empty($queued_changes) && $this->queueServiceChanges()) {
			$this->redirect($this->base_uri . "services/manage/" . $service->id . "/");
		}

		$this->uses(array("ModuleManager"));

		// Remove any change term session information
		$this->Session->clear("client_update_service");

        // Remove current term
        $current_term = (isset($terms[$service->package_pricing->id]) ? $terms[$service->package_pricing->id] : "");
        unset($terms[$service->package_pricing->id]);

        // Update the term
        if (!empty($this->post)) {
			// Redirect if no valid term pricing ID was given
			if (empty($this->post['pricing_id']) || !array_key_exists($this->post['pricing_id'], $terms))
				$this->redirect($this->base_uri . "services/changeterm/" . $service->id);

			// Remove override prices
			$vars = $this->post;
			$vars['override_price'] = null;
			$vars['override_currency'] = null;

			// Continue to the review step
			$data = array('service_id' => $service->id, 'vars' => $vars, 'type' => "service_term");
			$this->Session->write("client_update_service", $data);
			$this->redirect($this->base_uri . "services/review/" . $service->id);
        }

		$this->set("package", $package);
        $this->set("service", $service);
        $this->set("terms", array('' => Language::_("AppController.select.please", true)) + $terms);
        $this->set("current_term", $current_term);

		// Set sidebar tabs
		$module = $this->ModuleManager->initModule($service->package->module_id);
		$module->base_uri = $this->base_uri;
		$this->buildTabs($service, $package, $module, "changeterm");

		// Load slider JS/CSS
		$this->Javascript->setFile("bootstrap-slider.js");
	}

	/**
	 * Upgrades or downgrades a service by changing the package
	 */
	public function upgrade() {
		// Set whether the client can upgrade the service to another package in the same group
		$client_change_service_package = isset($this->client->settings['client_change_service_package']) && $this->client->settings['client_change_service_package'] == "true";
		$service = null;
		$upgradable_packages = array();

		// Fetch the service and any upgradable packages
		if ($client_change_service_package && isset($this->get[0]))
			$service = $this->Services->get((int)$this->get[0]);
		if ($client_change_service_package && $service)
			$upgradable_packages = $this->getUpgradablePackages($service->package, ($service->parent_service_id ? "addon" : "standard"));

		// Ensure we have a valid service with packages that can be upgraded
		if (!$client_change_service_package || !$service || $service->client_id != $this->client->id ||
			$service->status != "active" || !empty($service->date_canceled) || empty($upgradable_packages)) {
			$this->redirect($this->base_uri);
		}
		
		// Changes may not be made to the service while a pending change currently exists
		$queued_changes = $this->pendingServiceChanges($service->id);
		if (!empty($queued_changes) && $this->queueServiceChanges()) {
			$this->redirect($this->base_uri . "services/manage/" . $service->id . "/");
		}

		// Remove any upgrade package session information
		$this->Session->clear("client_update_service");

		// Determine whether invoices for this service remain unpaid
		$this->uses(array("Invoices", "ModuleManager"));
		$unpaid_invoices = $this->Invoices->getAllWithService($service->id, $this->client->id, "open");

		// Build the list of upgradable package terms
		$terms = array();
		foreach ($upgradable_packages as $pack) {
			$group_terms = $this->getPackageTerms($pack, array(), $service, false, true);
			if (!empty($group_terms)) {
				$terms['package_' . $pack->id] = array('name' => $pack->name, 'value' => "optgroup");
				$terms += $group_terms;
			}
		}

		if (!empty($this->post)) {
			// Disallow upgrade if the current service has not been paid
			if (!empty($unpaid_invoices)) {
				$this->flashMessage("error", Language::_("ClientServices.!error.invoices_upgrade_package", true));
				$this->redirect($this->base_uri . "services/manage/" . $service->id . "/");
			}

			// Redirect if no valid term pricing ID was given
			if (empty($this->post['pricing_id']) || !array_key_exists($this->post['pricing_id'], $terms))
				$this->redirect($this->base_uri . "services/upgrade/" . $service->id);

			// Remove override prices
			$vars = $this->post;
			$vars['override_price'] = null;
			$vars['override_currency'] = null;

			// Continue to the review step
			$data = array('service_id' => $service->id, 'vars' => $vars, 'type' => "service_package");
			$this->Session->write("client_update_service", $data);
			$this->redirect($this->base_uri . "services/review/" . $service->id);
		}

		// Set the current package and term
		$singular_periods = $this->Packages->getPricingPeriods();
		$plural_periods = $this->Packages->getPricingPeriods(true);
		$amount = $service->package_pricing->price;
		$currency = $service->package_pricing->currency;
		if (!empty($service->override_price) && !empty($service->override_currency)) {
			$amount = $service->override_price;
			$currency = $service->override_currency;
		}
		$current_term = Language::_("ClientServices.upgrade.current_package", true, $service->package->name, $service->package_pricing->term, $service->package_pricing->term != 1 ? $plural_periods[$service->package_pricing->period] : $singular_periods[$service->package_pricing->period], $this->CurrencyFormat->format($amount, $currency));
		if ($service->package_pricing->period == "onetime")
			$current_term = Language::_("ClientServices.upgrade.current_package_onetime", true, $service->package->name, $service->package_pricing->term != 1 ? $plural_periods[$service->package_pricing->period] : $singular_periods[$service->package_pricing->period], $this->CurrencyFormat->format($amount, $currency));

		$this->set("service", $service);
		$this->set("terms", array('' => Language::_("AppController.select.please", true)) + $terms);
		$this->set("current_term", $current_term);
		$this->set("unpaid_invoices", $unpaid_invoices);

		// Set sidebar tabs
		$module = $this->ModuleManager->initModule($service->package->module_id);
		$module->base_uri = $this->base_uri;
		$this->buildTabs($service, $service->package, $module, "changeterm");

		// Load slider JS/CSS
		$this->Javascript->setFile("bootstrap-slider.js");
	}

	/**
	 * List Addons
	 */
	public function addons() {
		// Determine whether a service is given and may have addons
		if (!isset($this->get[0]) || !($service = $this->Services->get((int)$this->get[0])) || $service->client_id != $this->client->id)
			$this->redirect($this->base_uri);

		// Fetch addons
		$addon_services = $this->Services->getAllChildren($service->id);
		$available_addons = $this->getAddonPackages($service->package_group_id);

		// Must have addons available to view this page
		if (empty($addon_services) && empty($available_addons))
			$this->redirect($this->base_uri . "services/manage/" . $service->id . "/");

		// Fetch the package and module
		$this->uses(array("ModuleManager"));
		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);
		$module->base_uri = $this->base_uri;

		// Set sidebar tabs
		$this->buildTabs($service, $package, $module, "addons");

		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;
		$this->set("periods", $periods);
		$this->set("statuses", $this->Services->getStatusTypes());
		$this->set("services", $addon_services);
		$this->set("service", $service);
		$this->set("package", $this->Packages->get($service->package->id));
		$this->set("client_can_create_addons", (!empty($available_addons) && (isset($this->client->settings['client_create_addons']) && $this->client->settings['client_create_addons'] == "true")));
	}

	/**
	 * Create an addon
	 */
	public function addAddon() {
		// Ensure a valid service was given
		$client_can_create_addon = (isset($this->client->settings['client_create_addons']) && $this->client->settings['client_create_addons'] == "true");
		if (!$client_can_create_addon || !isset($this->get[0]) || !($service = $this->Services->get((int)$this->get[0])) || $service->client_id != $this->client->id ||
			!($available_addons = $this->getAddonPackages($service->package_group_id)))
			$this->redirect($this->base_uri);

		// Fetch the package and module
		$this->uses(array("Invoices", "ModuleManager"));
		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);
		$module->base_uri = $this->base_uri;

		$package_group_id = (isset($this->get['package_group_id']) ? $this->get['package_group_id'] : "");
		$addon_pricing_id = (isset($this->get['pricing_id']) ? $this->get['pricing_id'] : "");

		// Detect module refresh fields
		$refresh_fields = isset($this->post['refresh_fields']) && $this->post['refresh_fields'] == "true";

		// Go to configure service options
		if (!empty($package_group_id) && !empty($addon_pricing_id)) {
			// Determine whether the addon is valid
			$fields = $this->validateAddon($service->id, $package_group_id, $addon_pricing_id);
			if (!$fields['valid']) {
				$this->setMessage("error", Language::_("ClientServices.!error.addon_invalid", true));
			}
			elseif (!$refresh_fields && !empty($this->post)) {
				$data = $this->post;

				// Set pricing term selected
				$data['package_id'] = $fields['package']->id;
				$data['pricing_id'] = $fields['pricing']->id;
				$data['parent_service_id'] = $fields['parent_service']->id;
				$data['package_group_id'] = $fields['package_group']->id;
				$data['client_id'] = $this->client->id;
				$data['status'] = "pending";
				$data['use_module'] = "true";

				// Unset any fields that may adversely affect the Services::add() call
				unset($data['override_price'], $data['override_currency'], $data['date_added'], $data['date_renews'], $data['date_last_renewed'],
                    $data['date_suspended'], $data['date_canceled'], $data['notify_order'], $data['invoice_id'], $data['invoice_method'],
                    $data['coupon_id']);

				// Attempt to add the addon
				if (isset($data['qty']))
					$data['qty'] = (int)$data['qty'];

				// Verify fields look correct in order to proceed
				$this->Services->validateService($fields['package'], $data);
				if (($errors = $this->Services->errors())) {
					$this->setMessage("error", $errors);
				}
				else {
					// Add addon
					$service_id = $this->Services->add($data, array('package_id' => $fields['package']->id));

					if (($errors = $this->Services->errors()))
						$this->setMessage("error", $errors);
					else {
						// Create the invoice
						$invoice_id = $this->Invoices->createFromServices($this->client->id, array($service_id), $fields['currency'], date("c"));

						// Redirect the client to pay the invoice
						if ($invoice_id) {
							$this->flashMessage("message", Language::_("ClientServices.!success.addon_service_created", true));
							$this->redirect($this->base_uri . "pay/method/" . $invoice_id . "/");
						}
					}
				}
			}

			// Set the configurable options partial for the selected addon
			$data = array_merge($this->post, array('addon' => $package_group_id . "_" . $addon_pricing_id));
			if (!empty($fields['pricing']) && ($addon_options = $this->getAddonOptions($fields, true, $data))) {
				$this->set("addon_options", $addon_options);
				$vars = (object)$data;
			}
			else
				$vars = (object)array('addon' => "");
		}

		// Set sidebar tabs
		$this->buildTabs($service, $package, $module, "addons");

		$this->set("module", $module->getModule());
		$this->set("package", $package);
		$this->set("service", $service);
		$this->set("addons", $this->getAddonPackageList($available_addons));
		$this->set("vars", (isset($vars) ? $vars : new stdClass()));

		// Load slider JS/CSS
		$this->Javascript->setFile("bootstrap-slider.js");
	}

	/**
	 * AJAX - Retrieves the configurable options for a given addon
	 *
	 * @param array $fields A list of addon fields (optional)
	 * @param boolean $return True to return this partial view, false to output as json (optional, default false)
	 * @param array $vars A list of input vars (optional)
	 * @return mixed False if addon fields are invalid; a partial view if $return is true; otherwise null
	 */
	public function getAddonOptions($fields = array(), $return = false, $vars = array()) {
		if (!$this->isAjax() && !$return) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}

		// Validate the addon
		if (!empty($this->get['package_group_id']) && !empty($this->get['pricing_id']) && !empty($this->get[0])) {
			$this->uses(array("PackageOptions"));

			// Determine whether the addon is valid
			$fields = $this->validateAddon((int)$this->get[0], (int)$this->get['package_group_id'], (int)$this->get['pricing_id']);
			if (!$fields['valid'])
				return false;
		}

		// Build the partial
		if (!empty($fields) && $fields['valid'] && $fields['module']) {
			// Set the module service fields and package option fields
			$vars = (object)$vars;
			$service_fields = $fields['module']->getClientAddFields($fields['package'], $vars);
			$package_options = $this->PackageOptions->getFields($fields['pricing']->package_id, $fields['pricing']->term, $fields['pricing']->period, $fields['pricing']->currency, $vars, null, array('addable' => 1));

			$vars = array(
				'module' => $fields['module']->getModule(),
				'fields' => $service_fields->getFields(),
				'html' => $service_fields->getHtml(),
				'package_options' => $this->partial("client_services_package_options", array('fields' => $package_options->getFields()))
			);

			$partial = $this->partial("client_services_configure_addon", $vars);
			if ($return)
				return $partial;
			$this->outputAsJson($partial);
		}

		return false;
	}

	/**
	 * Validates that the given data is valid for a client
	 * @see ClientServices::addAddon(), ClientServices::getAddonOptions()
	 *
	 * @param int $service_id The ID of the parent service to which the addon is to be assigned
	 * @param int $package_group_id The ID of the package group
	 * @param int $pricing_id The ID of the addon's package pricing
	 * @return array An array of fields including:
	 * 	- valid True if the addon is valid; false otherwise
	 * 	- parent_service An stdClass object representing the parent service of the addon (optional, only if valid is true)
	 * 	- package An stdClass object representing the addon package (optional, only if valid is true)
	 * 	- pricing An stdClass object representing the package pricing term (optional, only if valid is true)
	 * 	- package_group An stdClass object representing the addon package group (optional, only if valid is true)
	 * 	- module An stdClass object representing the module (optional, only if valid is true)
	 * 	- currency The currency code
	 */
	private function validateAddon($service_id, $package_group_id, $price_id) {
		$this->uses(array("ModuleManager", "PackageGroups"));

		// Ensure a valid addon was given
		if (!($parent_service = $this->Services->get((int)$service_id)) || $parent_service->client_id != $this->client->id ||
			!($available_addons = $this->getAddonPackages($parent_service->package_group_id, true)) ||
			!($package = $this->Packages->getByPricingId((int)$price_id)) ||
			!($package_group = $this->PackageGroups->get((int)$package_group_id)) || $package_group->company_id != $this->company_id) {
			return array('valid' => false);
		}

		// Confirm that the given package is an available addon
		$valid = false;
		$addon_groups = (isset($available_addons[$package_group->id]) ? $available_addons[$package_group->id] : array());
		$currency = $this->Clients->getSetting($this->client->id, "default_currency");
		$currency = $currency->value;
		$pricing = null;
		foreach ($addon_groups->addons as $addon) {
			if ($addon->id == $package->id) {
				$valid = true;

				// Fetch the pricing
				$pricing = $this->getPricing($package->pricing, (int)$price_id);
				$currency = ($pricing ? $pricing->currency : $currency);
				break;
			}
		}

		// Ensure a valid module exists
		$module = $this->ModuleManager->initModule($package->module_id, $this->company_id);
		if (!$module)
			$valid = false;

		return compact("valid", "parent_service", "package", "pricing", "package_group", "module", "currency");
	}

	/**
	 * Service Info
	 */
	public function serviceInfo() {

		$this->uses(array("ModuleManager"));

		// Ensure we have a service
		if (!($service = $this->Services->get((int)$this->get[0])) || $service->client_id != $this->client->id)
			$this->redirect($this->base_uri);

		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);

		if ($module) {
			$module->base_uri = $this->base_uri;
			$module->setModuleRow($module->getModuleRow($service->module_row_id));
			$this->set("content", $module->getClientServiceInfo($service, $package));
		}

		// Set any addon services
		$this->set("services", $this->Services->getAllChildren($service->id));

		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;
		$this->set("periods", $periods);
		$this->set("statuses", $this->Services->getStatusTypes());

		echo $this->outputAsJson($this->view->fetch("client_services_serviceinfo"));
		return false;
	}

	/**
	 * Format module tabs into an array of tabs
	 *
	 * @param array $tabs An array of tab data
	 * @return array An array of module tabs
	 */
	private function formatModuleTabs($tabs, $service, $method) {
		$module_tabs = array();
		foreach ($tabs as $action => $link) {
			if (!is_array($link))
				$link = array('name' => $link);
			if (!isset($link['icon']))
				$link['icon'] = "fa fa-cog";
			if (!isset($link['href'])) {
				$link['href'] = $this->base_uri . "services/manage/" . $service->id . "/" . $action . "/";
				$link['class'] = "ajax";
			}

			$module_tabs[] = array(
				'name' => $link['name'],
				'attributes' => array('href' => $link['href'], 'class' => isset($link['class']) ? $link['class'] : ""),
				'current' => strtolower($action) == strtolower($method),
				'icon' => $link['icon']
			);
		}
		return $module_tabs;
	}

	/**
	 * Retrieves a list of name/value pairs for addon packages
	 *
	 * @param array $addon_packages A list of package groups containing addon packages available to the client
	 * @return array A list of name/value pairs
	 */
	private function getAddonPackageList(array $addons) {
		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;

		$addon_packages = array('' => Language::_("AppController.select.please", true));
		foreach ($addons as $package_group) {
			foreach ($package_group->addons as $addon) {
				// Add addon package name
				$addon_packages[] = array('name' => $addon->name, 'value' => "optgroup");

				foreach ($addon->pricing as $price) {
					// Set term
					$period_singular = (isset($periods[$price->period]) ? $periods[$price->period] : "");
					$period_plural = (isset($periods[$price->period . "_plural"]) ? $periods[$price->period . "_plural"] : "");

					if ($this->Html->ifSet($price->period) == "onetime")
						$term = $period_singular;
					else {
						$term = (isset($price->term) ? $price->term : "");
						$term = Language::_("ClientServices.addaddon.term", true, $term, ($term == 1 ? $period_singular : $period_plural));
					}

					// Set price, setup fee
					$cost = $this->CurrencyFormat->format($this->Html->ifSet($price->price), $this->Html->ifSet($price->currency), array('code' => false));
					$name = Language::_("ClientServices.addaddon.term_price", true, $term, $cost);
					if ($price->setup_fee > 0) {
						$setup_fee = $this->CurrencyFormat->format($price->setup_fee, $price->currency, array('code' => false));
						$name = Language::_("ClientServices.addaddon.term_price_setupfee", true, $term, $cost, $setup_fee);
					}

					// Add addon package
					$addon_packages[] = array('name' => $name, 'value' => $package_group->id . "_" . $price->id);
				}
			}
		}
		return $addon_packages;
	}

	/**
	 * Retrieves a list of all addon packages available to the client in the given package group
	 *
	 * @param int $parent_group_id The ID of the parent group to list packages for
	 * @return array An array of addon package groups containing an array of addon packages
	 */
	private function getAddonPackages($parent_group_id) {
		$this->uses(array("Packages"));

		$packages = array();
		$package_groups = $this->Packages->getAllAddonGroups($parent_group_id);

		foreach ($package_groups as $package_group) {
			$temp_packages = $this->Packages->getAllPackagesByGroup($package_group->id);
			$restricted_packages = $this->Clients->getRestrictedPackages($this->client->id);

			foreach ($temp_packages as $package) {
				// Check whether the client has access to this package
				if ($package->status == "inactive")
					continue;
				elseif ($package->status == "restricted") {
					$available = false;
					foreach ($restricted_packages as $restricted) {
						if ($restricted->package_id == $package->id) {
							$available = true;
							break;
						}
					}

					if (!$available)
						continue;
				}

				// Add the addon package to the list
				if (!isset($packages[$package_group->id])) {
					$packages[$package_group->id] = $package_group;
					$packages[$package_group->id]->addons = array();
				}
				$packages[$package_group->id]->addons[] = $package;
			}
		}

		return $packages;
	}

	/**
	 * Builds and sets the sidebar tabs for the service management views
	 *
	 * @param stdClass $service An stdClass object representing the service
	 * @param stdClass $package An stdClass object representing the package used by the service
	 * @param stdClass $module An stdClass object representing the module the package uses
	 * @param string $method The method to call on the module, if any
	 */
	private function buildTabs($service, $package, $module, $method) {
		// Service information tab
		$tabs = array(
			array(
				'name' => Language::_("ClientServices.manage.tab_service_info", true),
				'attributes' => array('href' => $this->base_uri . "services/manage/" . $service->id . "/", 'class' => "ajax"),
				'current' => empty($method),
				'icon' => "fa fa-info-circle"
			)
		);

		// Determine whether addons are accessible
		$has_addons = $this->Services->hasChildren($service->id);
		if (!$has_addons) {
			$available_addons = $this->getAddonPackages($service->package_group_id);
			$has_addons = !empty($available_addons);
		}

		if ($has_addons) {
			$tabs[] = array(
				'name' => Language::_("ClientServices.manage.tab_addons", true),
				'attributes' => array('href' => $this->base_uri . "services/addons/" . $service->id . "/", 'class' => "ajax"),
				'current' => ($method == "addons"),
				'icon' => "fa fa-plus-circle"
			);
		}

		// Get tabs
		$client_tabs = $module->getClientTabs($package);
		$tabs = array_merge($tabs, $this->formatModuleTabs($client_tabs, $service, $method));

		// Return to dashboard
		$tabs[] = array(
			'name' => Language::_("ClientServices.manage.tab_service_return", true),
			'attributes' => array('href' => $this->base_uri),
			'current' => false,
			'icon' => "fa fa-arrow-left"
		);

		$this->set("tabs", $this->partial("client_services_tabs", array('tabs' => $tabs)));
	}

	/**
	 * AJAX Fetch all package options for the given pricing ID and service ID
	 */
	public function packageOptions() {
		$this->uses(array("Services", "Packages", "PackageOptions"));

		// Ensure we have a valid pricing ID and service ID
		if (!isset($this->get[0]) || !isset($this->get[1]) || !($service = $this->Services->get((int)$this->get[0])) ||
			$service->client_id != $this->client->id || !($package = $this->Packages->getByPricingId((int)$this->get[1]))) {
			if ($this->isAjax()) {
				header($this->server_protocol . " 401 Unauthorized");
				exit();
			}
			$this->redirect($this->base_uri);
		}

		// Determine the selected pricing
		$pricing = $this->getPricing($package->pricing, (int)$this->get[1]);

		$vars = (object)$this->PackageOptions->formatServiceOptions($service->options);

		// Fetch only editable package options that are already set
		$add_fields = false;
		$edit_fields = false;
		if ($pricing) {
			$filters = array('editable' => 1, 'allow' => (isset($vars->configoptions) ? array_keys($vars->configoptions) : array()));
			$edit_fields = $this->getPackageOptionFields($pricing->package_id, $pricing->term, $pricing->period, $pricing->currency, $vars, null, $filters);

			// Fetch only addable package options that are not already set
			$filters = array('addable' => 1, 'disallow' => (isset($vars->configoptions) ? array_keys($vars->configoptions) : array()));
			$add_fields = $this->getPackageOptionFields($pricing->package_id, $pricing->term, $pricing->period, $pricing->currency, $vars, null, $filters);
		}

		// Set the partial to include the fields into
		$package_options = $this->partial("client_services_manage_package_options", array('add_fields' => $add_fields, 'edit_fields' => $edit_fields));

		echo $this->outputAsJson($package_options);
		return false;
	}

	/**
	 * Builds a partial template for the package options
	 *
	 * @param int $package_id The ID of the package whose package options to fetch
	 * @param int $term The package option pricing term
	 * @param string $period The package option pricing period
	 * @param string $currency The ISO 4217 currency code for this pricing
	 * @param stdClass $vars An stdClass object containing input fields
	 * @param string $convert_currency The ISO 4217 currency code to convert the pricing to
	 * @param array $options An array of filtering options (optional):
	 * 	- addable Set to 1 to only include options that are addable by clients; 0 to only include options that are NOT addable by clients; otherwise every option is included
	 * 	- editable Set to 1 to only include options that are editable by clients; 0 to only include options that are NOT editable by clients; otherwise every option is included
	 * 	- allow An array of option IDs to include (i.e. white-list). An empty array would return no options. Not setting this 'option_ids' key will allow any option
	 * 	- disallow An array of option IDs not to include (i.e. black-list). An empty array would allow all options.
	 * @return mixed The partial template, or boolean false if no fields are available
	 */
	private function getPackageOptionFields($package_id, $term, $period, $currency, $vars, $convert_currency = null, array $options = null) {
		// Fetch only editable package options that are already set
		$package_options = $this->PackageOptions->getFields($package_id, $term, $period, $currency, $vars, $convert_currency, $options);
		$option_fields = $package_options->getFields();
		return (!empty($option_fields) ? $this->partial("client_services_package_options", array('fields' => $option_fields)) : false);
	}

	/**
	 * Builds a list of package options for a service that may be upgraded/downgraded
	 */
	public function manageOptions() {
		// Determine whether a valid service is given and whether available options exist to be managed
		if (!isset($this->get[0]) || !($service = $this->Services->get((int)$this->get[0])) || $service->client_id != $this->client->id ||
			!($available_options = $this->getAvailableOptions($service)))
			$this->redirect($this->base_uri);
		
		// Changes may not be made to the service while a pending change currently exists
		$queued_changes = $this->pendingServiceChanges($service->id);
		if (!empty($queued_changes) && $this->queueServiceChanges()) {
			$this->redirect($this->base_uri . "services/manage/" . $service->id . "/");
		}

		// Remove any config option session information
		$this->Session->clear("client_update_service");

		$this->uses(array("ModuleManager", "PackageOptions"));

		// Save the selected config options for the review step
		if (!empty($this->post)) {
			$options = (isset($this->post['configoptions']) ? $this->post['configoptions'] : array());

			// Redirect if no options were given
			if (empty($options))
				$this->redirect($this->base_uri . "services/manageoptions/" . $service->id);

			$this->Session->write("client_update_service", array('service_id' => $service->id, 'vars' => array('configoptions' => (array)$options), 'type' => "config_options"));
			$this->redirect($this->base_uri . "services/review/" . $service->id);
		}

		// Set sidebar tabs
		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);
		$module->base_uri = $this->base_uri;
		$this->buildTabs($service, $package, $module, null);

		// Fetch all of the package options
		$vars = (object)$this->PackageOptions->formatServiceOptions($service->options);

		// Fetch only editable package options that are already set
		$filters = array('editable' => 1, 'allow' => (isset($vars->configoptions) ? array_keys($vars->configoptions) : array()));
		$edit_fields = $this->getPackageOptionFields($package->id, $service->package_pricing->term, $service->package_pricing->period, $service->package_pricing->currency, $vars, null, $filters);

		// Fetch only addable package options that are not already set
		$filters = array('addable' => 1, 'disallow' => (isset($vars->configoptions) ? array_keys($vars->configoptions) : array()));
		$add_fields = $this->getPackageOptionFields($package->id, $service->package_pricing->term, $service->package_pricing->period, $service->package_pricing->currency, $vars, null, $filters);

		$this->set("service", $service);
		$this->set("package", $package);
		$this->set("module", $module);
		$this->set("available_options", (!empty($edit_fields) || !empty($add_fields)));
		$this->set("package_options",  $this->partial("client_services_manage_package_options", array('add_fields' => $add_fields, 'edit_fields' => $edit_fields, 'show_no_options_message' => true)));

		// Load slider JS/CSS
		$this->Javascript->setFile("bootstrap-slider.js");
	}

	/**
	 * Review page for updating package and package options
	 */
	public function review() {
		// Determine whether a valid service is given
		if (!isset($this->get[0]) || !($service = $this->Services->get((int)$this->get[0])) || $service->client_id != $this->client->id ||
			!($data = $this->Session->read("client_update_service")))
			$this->redirect($this->base_uri);

		// Redirect if the service doesn't match the session info
		if (!isset($data['service_id']) || $data['service_id'] != $service->id)
			$this->redirect($this->base_uri . "services/manage/" . $service->id);

		$this->uses(array("Invoices", "ModuleManager", "PackageOptions", "ServiceChanges"));

		// Fetch all of the input data
		$vars = (isset($data['vars']) ? $data['vars'] : array());
		$selected_options = (isset($vars['configoptions']) ? (array)$vars['configoptions'] : array());

		// Fetch the pricing to use for all settable options
		$pricing_id = (isset($vars['pricing_id']) ? $vars['pricing_id'] : null);
		if ($pricing_id && ($new_package = $this->Packages->getByPricingId($pricing_id))) {
			$pricing = $this->getPricing($new_package->pricing, $pricing_id);
		}

		// Fetch options that the client can set
		$new_package_id = (isset($new_package) && $new_package ? $new_package->id : $service->package->id);
		$pricing = (isset($pricing) ? $pricing : $service->package_pricing);
		$settable_options = $this->getSettableOptions($new_package_id, $pricing->term, $pricing->period, $pricing->currency, $service->options, $selected_options);
		$options = array();
		foreach ($settable_options as $option) {
			if (array_key_exists($option->id, $selected_options)) {
				$options[$option->id] = $selected_options[$option->id];
			}
		}

		// Fetch existing options to maintain between the current service and new package
		$matching_options = $this->getCurrentMatchingOptions($new_package_id, $pricing->term, $pricing->period, $pricing->currency, $service->options);
		$options += $matching_options;
		
		// Include any price overrides, if given
		$override_fields = array("override_price", "override_currency");
		$overrides = array();
		foreach ($override_fields as $override) {
			if (isset($service->{$override})) {
				$overrides[$override] = $service->{$override};
			}
		}
		
		// Include the current service module fields (to pass any module error checking)
		$vars = array();
		foreach ($service->fields as $field) {
			$vars[$field->key] = $field->value;
		}
		$vars = array_merge(
			$vars,
			$overrides,
			array('pricing_id' => $pricing->id, 'configoptions' => $options, 'use_module' => "true")
		);
		
		// Determine the items/totals
		$items = $this->ServiceChanges->getItems($service->id, $vars);
		$totals = $this->Invoices->getItemTotals($items['items'], $items['discounts'], $items['taxes']);

		if (!empty($this->post)) {
			// Queue the service change
			if ($this->queueServiceChanges()) {
				$result = $this->queueServiceChange($totals['items'], $service->id, $pricing->currency, $vars);
				
				// Display any errors
				if ($result['errors']) {
					$this->setMessage("error", $errors);
				}
				else {
					// Clear the service change session info
					$this->Session->clear("client_update_service");
					
					// Redirect
					$invoice = $this->Invoices->get($result['invoice_id']);
					if ($invoice->due > 0) {
						$this->flashMessage("message", Language::_("ClientServices.!success.service_queue_pay", true));
						$this->redirect($this->base_uri . "pay/method/" . $result['invoice_id'] . "/");
					}
					else {
						// Add a credit for the negative amount that was removed
						$allow_credit = $this->SettingsCollection->fetchClientSetting($this->client->id, $this->Clients, "client_prorate_credits");
						$allow_credit = (isset($allow_credit['value']) && $allow_credit['value'] == "true");
						if ($totals['totals']->total < 0 && $allow_credit) {
							$transaction_id = $this->createCredit(abs($totals['totals']->total), $pricing->currency);
						}
						
						$this->flashMessage("message", Language::_("ClientServices.!success.service_queue", true));
						$this->redirect($this->base_uri . "services/manage/" . $service->id . "/");
					}
				}
			}
			else {
				// Update the service (change package, term, and/or config options) now
				$vars['prorate'] = "true";
				$this->Services->edit($service->id, $vars);
				
				if (($errors = $this->Services->errors())) {
					$this->setMessage("error", $errors);
				}
				else {
					// Clear the service change session info
					$this->Session->clear("client_update_service");
					$action_type = (isset($data['type']) ? $data['type'] : "");
					$this->flashMessage("message", Language::_("ClientServices.!success." . $action_type . "_updated", true));
					$this->redirect($this->base_uri . "services/manage/" . $service->id . "/");
				}
			}
		}

		// Set sidebar tabs
		$package = $this->Packages->get($service->package->id);
		$module = $this->ModuleManager->initModule($service->package->module_id);
		$module->base_uri = $this->base_uri;
		$this->buildTabs($service, $package, $module, null);

		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;

		$this->set("periods", $periods);
		$this->set("service", $service);
		$this->set("package", $package);
		$this->set("module", $module);
		$this->set("review", $this->formatServiceReview($service, $options, $pricing_id));
		$this->set("totals", $this->totals($totals, $pricing->currency));
	}
	
	/**
	 * AJAX updates totals for input data changed for a service
	 */
	public function updateTotals() {
		if (!$this->isAjax()) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}
		
		$this->uses(array("Invoices"));
		
		// Only allow the pricing ID and config options to be provided
		$vars = array_intersect_key($this->post, array_flip(array("pricing_id", "configoptions")));
		
		// Determine the currency to be used
		$pricing_id = (isset($vars['pricing_id']) ? $vars['pricing_id'] : null);
		if ($pricing_id && ($package = $this->Packages->getByPricingId((int)$pricing_id))) {
			$pricing = $this->getPricing($package->pricing, (int)$pricing_id);
			$currency = $pricing->currency;
		}
		
		#
		# TODO: remove the following proration check that removes the totals box
		# 	when Services::getItemsFromData supports proration and proration after cutoff
		#
		// Don't show any totals if proration is set on the package because the totals
		// don't yet consider proration
		if (empty($package) || $package->prorata_day !== null) {
			echo $this->outputAsJson("");
			return false;
		}
		
		// Determine the items/totals
		$items = $this->Services->getItemsFromData($this->client->id, $vars);
		$totals = $this->Invoices->getItemTotals($items['items'], $items['discounts'], $items['taxes']);
		
		// Default to client's currency
		if (empty($currency)) {
			$currency = $this->Clients->getSetting($this->client->id, "default_currency");
			$currency = $currency->value;
		}
		
		echo $this->outputAsJson($this->totals($totals, $currency));
		return false;
	}

	/**
	 * Builds and returns the totals partial
	 *
	 * @param array $items An array of items including their totals|
	 * @param string $currency The ISO 4217 currency code
	 * @return string The totals partial template
	 */
	private function totals(array $items, $currency) {
		return $this->partial("client_services_totals", array('items' => $items, 'currency' => $currency));
	}
	
	/**
	 * Creates an in house credit for the client
	 *
	 * @param float $amount The amount to credit
	 * @param string $currency The ISO 4217 currency code for the credit
	 * @return int $transaction_id The ID of the transaction for this credit
	 */
	private function createCredit($amount, $currency) {
		$this->uses(array("Transactions"));
		
		// Apply the credit to the client account
		$vars = array(
			'client_id' => $this->client->id,
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
	 * Queue's a service change by creating an invoice for it and queuing it for later processing
	 *
	 * @param array $items An array of formatting items including:
	 * 	- items An array of items
	 * 	- discounts An array of discounts
	 * 	- taxes An array of taxes
	 * @param int $service_id The ID of the service being queued
	 * @param string $currency The ISO 4217 currency code
	 * @param array $vars An array of all data to queue to successfully update a service
	 * @return array An array of queue info, including:
	 * 	- invoice_id The ID of the invoice, if created
	 * 	- service_change_id The ID of the service change, if created
	 * 	- errors An array of errors
	 */
	private function queueServiceChange(array $items, $service_id, $currency, array $vars) {
		// Invoice and queue the service change
		$invoice_vars = array(
			'client_id' => $this->client->id,
			'date_billed' => date("c"),
			'date_due' => date("c"),
			'currency' => $currency,
			'lines' => $this->Invoices->makeLinesFromItems(array('items' => $items))
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
	 * Retrieves a list of service options that can be set by the client for the given package and term information
	 *
	 * @param int $package_id The ID of the package whose options to use
	 * @param int $term The pricing term
	 * @param string $period The pricing period
	 * @param string $currency The ISO 4217 pricing currency code
	 * @param array $current_options An array of current service options
	 * @param array $selected_options A key/value list of option IDs and their selected values
	 * @return array An array of stdClass objects representing each package option field that can be set by the client
	 */
	private function getSettableOptions($package_id, $term, $period, $currency, array $current_options, array $selected_options = array()) {
		$this->uses(array("PackageOptions"));

		// Fetch all available and current options
		$options = $this->PackageOptions->getAllByPackageId($package_id, $term, $period, $currency);

		// Re-key each current option by ID
		$edit_options = array();
		foreach ($current_options as $current_option) {
			$edit_options[$current_option->option_id] = $current_option;
		}
		unset($current_options, $current_option);

		// Determine all package options that can be set by the client
		$available_options = array();
		
		foreach ($options as $option) {
			// Set editable options
			if (array_key_exists($option->id, $edit_options) && $option->editable == "1") {
				$available_options[] = $option;
			}

			// Set addable options
			if (!array_key_exists($option->id, $edit_options) && $option->addable == "1" && array_key_exists($option->id, $selected_options)) {
				$available_options[] = $option;
			}
		}

		return $available_options;
	}
	
	/**
	 * Retrieves a list of current options that match those available from the given package and term information
	 *
	 * @param int $package_id The ID of the package whose options to use
	 * @param int $term The pricing term
	 * @param string $period The pricing period
	 * @param string $currency The ISO 4217 pricing currency code
	 * @param array $current_options An array of current service options
	 * @return array A key/value array where the key is the option ID and the value is the selected option value
	 */
	private function getCurrentMatchingOptions($package_id, $term, $period, $currency, array $current_options) {
		$this->uses(array("PackageOptions"));
		
		// Fetch all available and package options
		$options = $this->PackageOptions->getAllByPackageId($package_id, $term, $period, $currency);
		
		// Re-key each option and option value
		$package_options = array();
		foreach ($options as $option) {
			$package_options[$option->id] = array();
			foreach ($option->values as $value) {
				$package_options[$option->id][$value->id] = $value->value;
			}
		}
		unset($options, $option);
		
		// Re-key each current option by ID
		$edit_options = array();
		foreach ($current_options as $current_option) {
			$edit_options[$current_option->option_id] = $current_option;
		}
		unset($current_options, $current_option);
		
		// Check whether each current option is an available package option
		$available_options = array();
		foreach ($edit_options as $option_id => $option) {
			$quantity = ($option->option_type == "quantity");
			
			// Existing option is an available package option
			if (array_key_exists($option_id, $package_options)) {
				// Quantity options can be included
				if ($quantity) {
					$available_options[$option_id] = $option->qty;
				}
				elseif (in_array($option->option_value, $package_options[$option_id])) {
					$available_options[$option_id] = $option->option_value;
				}
			}
		}
		
		return $available_options;
	}

	/**
	 * Formats package/term and package options into separate sections representing their current and new values/pricing
	 *
	 * @param stdClass $service An stdClass object representing the service
	 * @param array $option_values A key/value array of the new service option IDs and their values
	 * @param int $pricing_id The new pricing ID (optional)
	 * @return array A formatted array of all options
	 */
	private function formatServiceReview($service, array $option_values = array(), $pricing_id = null) {
		$formatted_package = (object)array('current' => null, 'new' => null);

		// Fetch the current package and its pricing info
		$package = $this->Packages->get($service->package->id);
		$package->pricing = $this->getPricing($package->pricing, $service->pricing_id);
		$formatted_package->current = $package;

		// Fetch the new package and its pricing info
		if ($pricing_id) {
			$package = $this->Packages->getByPricingId($pricing_id);
			$package->pricing = $this->getPricing($package->pricing, $pricing_id);
			$formatted_package->new = $package;
		}

		// Fetch the formatted config options
		$formatted_options = $this->formatReviewOptions($service, $option_values, $pricing_id);

		return (object)array(
			'packages' => $formatted_package,
			'config_options' => $formatted_options
		);
	}

	/**
	 * Formats package options and their values into categories for current and new
	 * @see ClientServices::formatServiceReview
	 *
	 * @param stdClass $service An stdClass object representing the service
	 * @param array $option_values A key/value array of the new service option IDs and their values
	 * @param int $pricing_id The new pricing ID (optional)
	 * @return array A formatted array of all options
	 */
	private function formatReviewOptions($service, array $option_values = array(), $pricing_id = null) {
		if (!isset($this->PackageOptions))
			$this->uses(array("PackageOptions"));

		$formatted_options = array();

		// Fetch the current package options
		$current_values = $this->PackageOptions->formatServiceOptions($service->options);
		$current_values = (array_key_exists("configoptions", $current_values) ? $current_values['configoptions'] : array());

		// Fetch all of the possible options
		$all_options = $this->PackageOptions->getByPackageId($service->package->id);

		$pricing = null;
		// Fetch all possible options for the new pricing
		if ($pricing_id && ($new_package = $this->Packages->getByPricingId($pricing_id))) {
			$new_options = $this->PackageOptions->getByPackageId($new_package->id);

			// Key all options by ID
			$option_ids = array();
			foreach ($all_options as $option) {
				$option_ids[$option->id] = null;
			}

			// Combine all options with the new package options
			foreach ($new_options as $option) {
				if (!array_key_exists($option->id, $option_ids))
					$all_options[] = $option;
			}
			unset($new_options, $option);

			// Set the new package pricing information
			$pricing = $this->getPricing($new_package->pricing, $pricing_id);
		}

		// Set the new pricing as the current service pricing if not set
		$pricing = ($pricing ? $pricing : $service->package_pricing);

		// Match the available package options with the given options
		$i = 0;
		foreach ($all_options as $package_option) {

			if (array_key_exists($package_option->id, $option_values) || array_key_exists($package_option->id, $current_values)) {
				$formatted_options[$i] = $package_option;
				$formatted_options[$i]->new_value = false;
				$formatted_options[$i]->current_value = false;

				// Fetch the new option value
				if (array_key_exists($package_option->id, $option_values)) {
					$formatted_options[$i]->new_value = $this->PackageOptions->getValue($package_option->id, $option_values[$package_option->id]);

					if ($formatted_options[$i]->new_value) {
						// Set the selected value
						$formatted_options[$i]->new_value->selected_value = $option_values[$package_option->id];

						// Set pricing
						$formatted_options[$i]->new_value->pricing = $this->PackageOptions->getValuePrice($formatted_options[$i]->new_value->id, $pricing->term, $pricing->period, $pricing->currency);
					}
				}

				// Fetch the current option value
				if (array_key_exists($package_option->id, $current_values)) {
					$formatted_options[$i]->current_value = $this->PackageOptions->getValue($package_option->id, $current_values[$package_option->id]);

					if ($formatted_options[$i]->current_value) {
						// Set the selected value
						$formatted_options[$i]->current_value->selected_value = $current_values[$package_option->id];

						// Set pricing
						$formatted_options[$i]->current_value->pricing = $this->PackageOptions->getValuePrice($formatted_options[$i]->current_value->id, $service->package_pricing->term, $service->package_pricing->period, $service->package_pricing->currency);
					}
				}

				$i++;
			}
		}

		// Remove any options/values that should not be shown
		foreach ($formatted_options as $i => &$option) {
			// If there is no new or current value, remove the option entirely
			if (!$option->new_value && !$option->current_value) {
				unset($formatted_options[$i]);
				continue;
			}

			// Remove any quantity options that are not being set, or remove the value if being changed to a quantity of 0
			if ($option->type == "quantity") {
				// If no current value exists, and the new value is a quantity of 0, remove the option entirely
				if (!$option->current_value && $option->new_value && $option->new_value->selected_value == "0") {
					unset($formatted_options[$i]);
				}
				// If a current value exists, and the new value is a quantity of 0, only remove the new value
				elseif ($option->current_value && $option->new_value && $option->new_value->selected_value == "0") {
					$option->new_value = false;
				}
			}
		}

		return array_values($formatted_options);
	}
	
	/**
	 * Retrieves a list of pending service changes for the given service
	 *
	 * @param int $service_id The ID of the service
	 * @return array An array of all pending service changes for the given service
	 */
	private function pendingServiceChanges($service_id) {
		if (!isset($this->ServiceChanges)) {
			$this->uses(array("ServiceChanges"));
		}
		
		return $this->ServiceChanges->getAll("pending", $service_id);
	}
	
	/**
	 * Determines whether queuing service changes is enabled
	 *
	 * @return boolean True if queuing service changes is enabled, or false otherwise
	 */
	private function queueServiceChanges() {
		// Determine whether to queue the service change or process it immediately
		$queue = $this->SettingsCollection->fetchClientSetting($this->client->id, $this->Clients, "process_paid_service_changes");
		$queue = (isset($queue['value']) ? $queue['value'] : null);
		
		return ($queue == "true");
	}

	/**
	 * Retrieves the matching pricing information from the given pricings
	 *
	 * @param array $pricings An array of stdClass objects representing each pricing
	 * @param int $pricing_id The ID of the pricing to retrieve from the list
	 * @return mixed An stdClass object representing the pricing information, or null if not found
	 */
	private function getPricing(array $pricings, $pricing_id) {
		$pricing = null;
		foreach ($pricings as $price) {
			if ($price->id == $pricing_id) {
				$pricing = $price;
				break;
			}
		}

		return $pricing;
	}
}
?>