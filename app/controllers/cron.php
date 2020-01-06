<?php
/**
 * The Cron controller. Handles all automated tasks that run via a cron job or
 * scheduled task.
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Cron extends AppController {

	/**
	 * @var array A list of cron task settings for the current company
	 */
	private $cron_tasks = array();
	/**
	 * @var string The passphrase used to process batch payments
	 */
	private $passphrase = null;

	/**
	 * Pre-action
	 */
	public function preAction() {
		// Set a specific company
		if (!empty($this->get['company_id'])) {
			$this->uses(array("Companies"));
			$company = $this->Companies->get((int)$this->get['company_id']);
		}

		// Set the default company if one was not provided
		if (empty($company)) {
			$company = $this->getCompany();
		}

		$this->primeCompany($company);

		// Set URLs
		$this->base_url = "http" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off" ? "s" : "") . "://" . $company->hostname . "/";

		$this->uses(array("CronTasks", "Logs"));
		$this->components(array("SettingsCollection"));
		Language::loadLang("cron");

		// If not being executed via command line, require a key
		if (!$this->is_cli) {
			$cron_key = $this->SettingsCollection->fetchSystemSetting(null, "cron_key");

			if (!isset($this->get['cron_key']) || $cron_key['value'] != $this->get['cron_key'])
				$this->redirect();
		}

		// Set passphrase if given
		if (isset($this->get['passphrase']))
			$this->passphrase = $this->get['passphrase'];

		// Override the memory limit, if given
		if (Configure::get("Blesta.cron_memory_limit"))
			ini_set("memory_limit", Configure::get("Blesta.cron_memory_limit"));
	}

	/**
	 * Runs the cron
	 */
	public function index() {
		// Run all tasks for all companies
		$companies = $this->Companies->getAll();
		$i = 0;
		$run_id = 0;
		$event = "";
		foreach ($companies as $company) {
			// Setup the company
			$this->primeCompany($company);

			// Set URLs
			$this->base_url = "http" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off" ? "s" : "") . "://" . $company->hostname . "/";

			// Load the language specific to this company
			$company_default_language = $this->SettingsCollection->fetchSetting($this->Companies, $this->company_id, "language");
			if (isset($company_default_language['value']))
				$company_default_language = $company_default_language['value'];
			Language::loadLang("cron", $company_default_language);

			// Remove the saved cron tasks and set the next company's
			$this->cron_tasks = array();
			// Save cron tasks for the current company, re-indexed by cron task key
			$cron_tasks = $this->CronTasks->getAllTaskRun();
			foreach($cron_tasks as $cron_task) {
				if ($cron_task->plugin_id !== null && !$cron_task->plugin_enabled)
					continue;
				$this->cron_tasks[$cron_task->key . $cron_task->plugin_dir] = $cron_task;
			}

			// Log this task has started
			$output = $this->setOutput(Language::_("Cron.index.attempt_all", true, $company->name));
			$cron_log_group = $this->createCronLogGroup();

			if (($errors = $this->logTaskStarted($run_id, $event, $cron_log_group, $this->Date->format("c"), $output))) {
				// Error, cron could not be logged (this should never happen)
				echo Language::_("Cron.!error.cron.failed", true);
			}

			// Run through all tasks
			$this->all($cron_log_group);

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.index.completed_all", true), $output);

			if (($errors = $this->logTaskCompleted($run_id, $event, $cron_log_group, $this->Date->format("c"), $output))) {
				// Error, cron could not be logged (this should never happen)
				echo Language::_("Cron.!error.cron.failed", true);
			}
		}

		// Remove data no longer needed
		unset($companies, $company, $this->cron_tasks);

		// Run all system tasks
		$this->allSystem();

		return false;
	}

	/**
	 * Run all cron tasks
	 */
	public function all($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// List of cron tasks in the order they will be run
		$tasks = array("createInvoices", "applyCredits", "autoDebitInvoices", "cardExpirationReminders",
			"deliverInvoices", "deliverReports", "addPaidPendingServices", "suspendServices",
			"unsuspendServices", "cancelScheduledServices", "processServiceChanges",
			"processRenewingServices", "paymentReminders", "updateExchangeRates", "pluginTasks", "cleanLogs"
		);

		// Run each cron tasks
		for ($i=0, $num_tasks=count($tasks); $i<$num_tasks; $i++) {
			try {
				call_user_func_array(array($this, $tasks[$i]), array($cron_log_group));
			}
			catch (Exception $e) {
				// Error running cron task
				echo Language::_("Cron.!error.task_execution.failed", true, $e->getMessage(), $e->getTraceAsString());
			}
		}

		return false;
	}

	/**
	 * Runs all system-level cron tasks
	 */
	private function allSystem() {
		// Run all system tasks
		$run_id = 0;
		$event = "";

		// Log this task has started
		$output = $this->setOutput(Language::_("Cron.index.attempt_all_system", true));
		$cron_log_group = $this->createCronLogGroup();

		if (($errors = $this->logTaskStarted($run_id, $event, $cron_log_group, $this->Date->format("c"), $output))) {
			// Error, cron could not be logged (this should never happen)
			echo Language::_("Cron.!error.cron.failed", true);
		}

		// List of cron tasks in the order they will be run
		$tasks = array("license", "amazonS3Backup", "sftpBackup");

		// Run each cron tasks
		for ($i=0, $num_tasks=count($tasks); $i<$num_tasks; $i++) {
			try {
				call_user_func_array(array($this, $tasks[$i]), array($cron_log_group));
			}
			catch (Exception $e) {
				// Error running cron task
				echo Language::_("Cron.!error.task_execution.failed", true, $e->getMessage(), $e->getTraceAsString());
			}
		}

		// Log this task has completed
		$output = $this->setOutput(Language::_("Cron.index.completed_all_system", true), $output);

		if (($errors = $this->logTaskCompleted($run_id, $event, $cron_log_group, $this->Date->format("c"), $output))) {
			// Error, cron could not be logged (this should never happen)
			echo Language::_("Cron.!error.cron.failed", true);
		}
	}

	/**
	 * Runs the create invoice task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function createInvoices($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("create_invoice");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.createinvoices.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("Clients", "Invoices", "Services"));

			// Create recurring invoices and set output
			$output = $this->setOutput($this->createRenewingServiceInvoices(), $output, false);

			// Create recurring invoices and set output
			$output = $this->setOutput($this->createRecurringInvoices(), $output, false);

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.createinvoices.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Create all recurring invoices
	 *
	 * @return string The output from attempting to create recurring invoices
	 */
	private function createRecurringInvoices() {
		$this->uses(array("ClientGroups", "Invoices"));

		// Set output
		$output = "";

		// Get all client groups
		$client_groups = $this->ClientGroups->getAll($this->company_id);

		foreach ($client_groups as $client_group) {
			// Get all recurring invoices set to renew for this client group
			$invoices = $this->Invoices->getAllRenewingRecurring($client_group->id);
			$clients = array();

			foreach ($invoices as $invoice) {

				// Get the client
				if (!isset($clients[$invoice->client_id]))
					$clients[$invoice->client_id] = $this->Clients->get($invoice->client_id);

				// Create a new recurring invoice (and possibly multiple)
				$invoice_created = $this->Invoices->addFromRecurring($invoice->id, $clients[$invoice->client_id]->settings);

				// Log success/error for only those that had invoices to create and succeeded or failed to do so
				if (($errors = $this->Invoices->errors()))
					$output = $this->setOutput(Language::_("Cron.createinvoices.recurring_invoice_failed", true, $invoice->id, $clients[$invoice->client_id]->id_code), $output);
				elseif ($invoice_created)
					$output = $this->setOutput(Language::_("Cron.createinvoices.recurring_invoice_success", true, $invoice->id, $clients[$invoice->client_id]->id_code), $output);
			}
		}

		// Return output
		return $output;
	}

	/**
	 * Create all renewing service invoices
	 *
	 * @return string The output from attempting to create all renewing service invoices
	 */
	private function createRenewingServiceInvoices() {
		$this->uses(array("ClientGroups", "Coupons", "Invoices", "Services", "PackageOptions"));

		// Set output
		$output = "";

		// Encompass the entire day
		$today_timestamp = $this->Date->toTime($this->Services->dateToUtc(date("Y-m-d 23:59:59", strtotime(date("c")))));

		// Get all client groups
		$client_groups = $this->ClientGroups->getAll($this->company_id);

		foreach ($client_groups as $client_group) {
			// Fetch all services ready to be renewed
			$services = $this->Services->getAllRenewing($client_group->id);
			// All services that failed to generate an invoice
			$failed_services = array();
			// Group services on invoices?
			$inv_group_services = true;
			if ($opt = $this->ClientGroups->getSetting($client_group->id, 'inv_group_services')) {
				$inv_group_services = $opt->value === 'false'
					? false
					: true;
			}
			unset($opt);

			// Go through each service and renew it as many times as necessary (to catch up)
			// and create all necessary invoices
			while (!empty($services)) {
				$invoice_services = array();

				// Setup an ordered list of services
				$i = 0;
				$inv_due_date = null;
				foreach ($services as $service) {
					// Skip services that failed invoice generation
					if (in_array($service->id, $failed_services))
						continue;

					$service->date_renews .= "Z";

					// The service date_renews is the same for all services in this loop
					if ($i++ == 0)
						$inv_due_date = $service->date_renews;

					// Calculate the next renew date (which gives us back UTC)
					$service->next_renew_date = $this->Services->getNextRenewDate($service->date_renews, $service->term, $service->period, "c");

					// Add the service to the list of services for this client to be included on the invoice
					if ($service->next_renew_date) {
						// Add the service to the list of those to be added per invoice
						if (!isset($invoice_services[$service->client_id]))
							$invoice_services[$service->client_id] = array();
						$invoice_services[$service->client_id][] = $service;
					}
				}
				unset($services, $service, $i);

				// If nothing to invoice, break out
				if (empty($invoice_services))
					break;

				// Generate an invoice for each client containing all renewing services
				foreach ($invoice_services as $client_id => $services) {
					// Fetch the currency to generate the invoice in
					$client_default_currency = $this->SettingsCollection->fetchClientSetting($client_id, null, "default_currency");
					$default_currency = (isset($client_default_currency['value']) ? $client_default_currency['value'] : null);

                    // Build individual invoices for each service
                    if (!$inv_group_services) {
                        // However, still group child services with their parents
                        $family_services = array();
                        foreach ($services as $service) {
                            // Create a group of services containing the parent and all children
                            if ($service->parent_service_id) {
                                if (!isset($family_services[$service->parent_service_id])) {
                                    $family_services[$service->parent_service_id] = array();
                                }
                                $family_services[$service->parent_service_id][] = $service;
                            } else {
                                if (!isset($family_services[$service->id])) {
                                    $family_services[$service->id] = array();
                                }
                                $family_services[$service->id] = array($service);
                            }
                        }

                        // Create the invoice for the service
                        foreach ($family_services as $service_group) {
                            $output .= $this->invoiceServices(
                                $client_id,
                                $service_group,
                                $default_currency,
                                $inv_due_date
                            );
                        }
                    } else {
                        // Create the invoice for all services
                        $output .= $this->invoiceServices(
							$client_id,
							$services,
							$default_currency,
							$inv_due_date
						);
                    }
				}

				// Re-fetch the services that need to be renewed to continue catching up
				$services = $this->Services->getAllRenewing($client_group->id);
			}
		}

		return $output;
	}

	/**
	 * Generate invoices for a client
	 *
	 * @param int $client_id
	 * @param array $services
	 * @param string $default_currency
	 * @param string $inv_due_date
     * @return string $output
	 */
	private function invoiceServices($client_id, $services, $default_currency, $inv_due_date)
	{
        $output = null;
		// Generate an invoice for the services
		$service_ids = array();
		foreach ($services as $service) {
			$service_ids[] = $service->id;
		}

		// Set a CSV of service IDs for the log
		$csv_service_ids = implode(", ", $service_ids);

		// Create the invoice for these renewing services
		$invoice_id = $this->Invoices->createFromServices($client_id, $service_ids, $default_currency, $inv_due_date, false, true);

		// Log the details
		if (($errors = $this->Invoices->errors())) {
			// Error, flag all service IDs that failed to generate an invoice
			$failed_services = array_unique(array_merge($failed_services, $service_ids));
			$output .= Language::_("Cron.createinvoices.service_invoice_error", true, print_r($errors, true), $client_id, $csv_service_ids);
		}
		else {
			// Success, invoice was created. Update service renew dates
			foreach ($services as $service) {
				$dates = array('date_renews' => $service->next_renew_date, 'date_last_renewed' => $service->date_renews);
				$this->Services->edit($service->id, $dates, true);
			}

			$output .= Language::_("Cron.createinvoices.service_invoice_success", true, $invoice_id, $client_id, $csv_service_ids);
		}
        return $output;
	}

	/**
	 * Runs the apply credits task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function applyCredits($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("apply_payments");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.applycredits.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("Clients", "ClientGroups", "Transactions"));

			// Get all client groups
			$client_groups = $this->ClientGroups->getAll($this->company_id);
			$applied_credits = false;

			foreach ($client_groups as $client_group) {
				// Ensure we can auto apply credits for this client group
				$apply_credits = $this->SettingsCollection->fetchClientGroupSetting($client_group->id, $this->ClientGroups, "auto_apply_credits");
				$apply_credits = (isset($apply_credits['value']) && $apply_credits['value'] != "true" ? false : true);

				if (!$apply_credits)
					continue;

				// Get each client in this group
				$clients = $this->Clients->getAll(null, $client_group->id);

				foreach ($clients as $client) {
					// Attempt to apply credits
					$amounts_applied = $this->Transactions->applyFromCredits($client->id);

					// Avoid the possibility of unapplicable transaction errors being erroneously re-used for other clients by
					// requring the applied amount to be set
					if ($amounts_applied !== null && ($errors = $this->Transactions->errors())) {
						$output = $this->setOutput(Language::_("Cron.applycredits.apply_failed", true, $client->id), $output);
					}
					elseif (!empty($amounts_applied)) {
						$applied_credits = true;

						foreach ($amounts_applied as $transaction_id => $amounts) {
							foreach ($amounts as $applied) {
								$output = $this->setOutput(Language::_("Cron.applycredits.apply_success", true, $transaction_id, $client->id, $applied['invoice_id'], $applied['amount']), $output);
							}
						}
					}
				}
			}

			// Log nothing applied
			if (!$applied_credits)
				$output = $this->setOutput(Language::_("Cron.applycredits.apply_none", true), $output);

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.applycredits.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Runs the autodebit invoices task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function autoDebitInvoices($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("autodebit");

		if ($this->isTimeToRun($cron_task) || $this->passphrase != "") {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.autodebitinvoices.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("Accounts", "ClientGroups", "Clients", "GatewayManager", "Invoices", "Payments"));
			$this->helpers(array("CurrencyFormat"));

			// Fetch all client groups
			$client_groups = $this->ClientGroups->getAll($this->company_id);

			// Send all autodebit invoices foreach client group
			foreach ($client_groups as $client_group) {
				// Get invoices to be autodebited for this group
				$invoices = $this->Invoices->getAllAutodebitableInvoices($client_group->id);

				// Create a list of clients and total amounts due for each currency
				$clients = $this->groupInvoices($invoices, "currency");

				// Autodebit cards
				$installed_gateways = array();
				foreach ($clients as $client_id => $currencies) {
					// Get the payment account to charge
					$client = $this->Clients->get($client_id, false);
					$debit_account = $this->Clients->getDebitAccount($client_id);
					$payment_account = false;
					if (!$debit_account)
						continue;

					if ($debit_account->type == "cc") {
						$payment_account = $this->Accounts->getCc($debit_account->account_id);
						// Only process locally stored accounts if passphrase set
						if ($this->passphrase != "" && $payment_account && $payment_account->number == "")
							$payment_account = false;
					}
					elseif ($debit_account->type == "ach") {
						$payment_account = $this->Accounts->getAch($debit_account->account_id);
						// Only process locally stored accounts if passphrase set
						if ($this->passphrase != "" && $payment_account && $payment_account->account == "")
							$payment_account = false;
					}

					if (!$payment_account)
						continue;

					// Charge each currency separately
					foreach ($currencies as $currency_code => $charges) {
						// Format amount due
						$amount_due = $this->CurrencyFormat->cast($charges['amount'], $currency_code);

						// Log attempt to charge
						$output = $this->setOutput(Language::_("Cron.autodebitinvoices.charge_attempt", true, $client->id_code, $this->CurrencyFormat->format($amount_due, $currency_code, array('code'=>"true"))), $output);

						// Set active gateway for use with the given currency
						if (!array_key_exists($currency_code, $installed_gateways))
							$installed_gateways[$currency_code] = $this->GatewayManager->getInstalledMerchant($this->company_id, $currency_code);

						// Process the payment
						if ($amount_due > 0 && $payment_account && $installed_gateways[$currency_code]) {
							$options = array(
								'invoices' => $charges['invoice_amounts'],
								'staff_id' => null,
								'email_receipt' => true,
								'passphrase' => $this->passphrase
							);
							// Process payment and send any necessary emails
							$this->Payments->processPayment($client_id, $debit_account->type, $amount_due, $currency_code, null, $payment_account->id, $options);

							// Log success/failure
							if (($errors = $this->Payments->errors()))
								$output = $this->setOutput(Language::_("Cron.autodebitinvoices.charge_failed", true), $output);
							else
								$output = $this->setOutput(Language::_("Cron.autodebitinvoices.charge_success", true), $output);
						}
						else {
							// Unable to process the charge
							$output = $this->setOutput(Language::_("Cron.autodebitinvoices.charge_failed", true), $output);
						}
					}
				}
			}

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.autodebitinvoices.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Runs the payment reminders task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function paymentReminders($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("payment_reminders");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.paymentreminders.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("Accounts", "ClientGroups", "Clients", "Contacts", "Emails", "Invoices"));

			// Get all open invoices
			$invoices = $this->Invoices->getAll();

			// Payment account types
			$ach_types = $this->Accounts->getAchTypes();
			$cc_types = $this->Accounts->getCcTypes();

			// Send a reminder regarding each invoice if now is the time to send
			// such a notice
			$clients = array();
			$client_autodebit_payment_accounts = array();
			$reminders = array('notice1'=>array(), 'notice2'=>array(), 'notice3'=>array());
			foreach ($invoices as $invoice) {
				if (!isset($clients[$invoice->client_id]))
					$clients[$invoice->client_id] = $this->Clients->get($invoice->client_id);
				$client = $clients[$invoice->client_id];

				foreach ($reminders as $action=>$list) {
					// Send the reminder
					if ($this->shouldSendReminder($invoice, $client, $action)) {
						// Get more information to use in the email
						if (!isset($client_autodebit_payment_accounts[$client->id]))
							$client_autodebit_payment_accounts[$client->id] = null;

						// Set the autodebit payment account (if any)
						if ($client->settings['autodebit'] == "true") {
							if (!$client_autodebit_payment_accounts[$client->id] && ($debit_account = $this->Clients->getDebitAccount($client->id))) {
								if ($debit_account->type == "cc") {
									$client_autodebit_payment_accounts[$client->id] = $this->Accounts->getCc($debit_account->account_id);
									// Set the account type (as a tag for the email)
									$client_autodebit_payment_accounts[$client->id]->account_type = isset($cc_types[$debit_account->type]) ? $cc_types[$debit_account->type] : $debit_account->type;
								}
								elseif ($debit_account->type == "ach") {
									$client_autodebit_payment_accounts[$client->id] = $this->Accounts->getAch($debit_account->account_id);
									// Set the account type (as a tag for the email)
									$client_autodebit_payment_accounts[$client->id]->account_type = isset($ach_types[$debit_account->type]) ? $ach_types[$debit_account->type] : $debit_account->type;
								}
							}
						}

						// Get all contacts that should receive this notice
						$contacts = $this->Contacts->getAll($client->id, "billing");
						if (empty($contacts))
							$contacts = $this->Contacts->getAll($client->id, "primary");

						// Send the payment notice and log success/failure
						foreach ($contacts as $contact) {
							if (($errors = $this->sendPaymentNotice($action, $client, $contact, $invoice, $client_autodebit_payment_accounts[$client->id])))
								$output = $this->setOutput(Language::_("Cron.paymentreminders.failed", true, $contact->first_name, $contact->last_name, $client->id_code, $invoice->id_code), $output);
							else
								$output = $this->setOutput(Language::_("Cron.paymentreminders.success", true, $contact->first_name, $contact->last_name, $client->id_code, $invoice->id_code), $output);
						}
					}
				}
			}

			// Remove data no longer needed
			unset($invoices, $reminders, $contacts);

			// Fetch all client groups
			$client_groups = $this->ClientGroups->getAll($this->company_id);

			// Send reminders regarding invoices set to be autodebited soon
			foreach ($client_groups as $client_group) {
				// Get all invoices set to be autodebited in the future for this group
				$invoices = $this->Invoices->getAllAutodebitableInvoices($client_group->id, true, "notice_pending_autodebit");

				// Send a notice regarding each invoice
				foreach ($invoices as $invoice) {
					if (!isset($clients[$invoice->client_id]))
						$clients[$invoice->client_id] = $this->Clients->get($invoice->client_id);
					$client = $clients[$invoice->client_id];

					// Skip clients that are not set to receive this notice
					if (isset($client->settings['send_payment_notices']) && $client->settings['send_payment_notices'] != "true")
						continue;

					// Get more information to use in the email
					if (!isset($client_autodebit_payment_accounts[$client->id]))
						$client_autodebit_payment_accounts[$client->id] = null;

					// Set the autodebit payment account (if any)
					if ($client->settings['autodebit'] == "true") {
						if (!$client_autodebit_payment_accounts[$client->id] && ($debit_account = $this->Clients->getDebitAccount($client->id))) {
							if ($debit_account->type == "cc") {
								$client_autodebit_payment_accounts[$client->id] = $this->Accounts->getCc($debit_account->account_id);
								// Set the account type (as a tag for the email)
								$client_autodebit_payment_accounts[$client->id]->account_type = isset($cc_types[$debit_account->type]) ? $cc_types[$debit_account->type] : $debit_account->type;
							}
							elseif ($debit_account->type == "ach") {
								$client_autodebit_payment_accounts[$client->id] = $this->Accounts->getAch($debit_account->account_id);
								// Set the account type (as a tag for the email)
								$client_autodebit_payment_accounts[$client->id]->account_type = isset($ach_types[$debit_account->type]) ? $ach_types[$debit_account->type] : $debit_account->type;
							}
						}
					}

					// Get all contacts that should receive this notice
					$contacts = $this->Contacts->getAll($client->id, "billing");
					if (empty($contacts))
						$contacts = $this->Contacts->getAll($client->id, "primary");

					// Send the autodebit notice and log success/failure
					foreach ($contacts as $contact) {
						if (($errors = $this->sendAutodebitNotice($client, $contact, $invoice, $client_autodebit_payment_accounts[$client->id])))
							$output = $this->setOutput(Language::_("Cron.paymentreminders.autodebit_failed", true, $contact->first_name, $contact->last_name, $client->id_code, $invoice->id_code), $output);
						else
							$output = $this->setOutput(Language::_("Cron.paymentreminders.autodebit_success", true, $contact->first_name, $contact->last_name, $client->id_code, $invoice->id_code), $output);
					}
				}
			}

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.paymentreminders.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Determines whether or not to send a payment reminder notice for an invoice
	 * @see Cron::paymentReminders()
	 *
	 * @param stdClass $invoice An invoice object
	 * @param stdClass $client The client object that the invoice belongs to
	 * @param string $action The email notice action
	 * @return boolean True if a reminder should be sent out for this invoice, false otherwise
	 */
	private function shouldSendReminder(stdClass $invoice, stdClass $client, $action) {
		// Ensure the settings allow for the client to receive this notice
		if (isset($client->settings[$action]) && is_numeric($client->settings[$action]) &&
			(!isset($client->settings['send_payment_notices']) || $client->settings['send_payment_notices'] == "true")) {
			if (!isset($this->Invoices))
				$this->uses(array("Invoices"));

			// Set today's date timestamp
			$todays_datetime = $this->Date->toTime($this->Date->format("Y-m-d", date("c")));

			// Set timestamp of when the reminder should be sent
			$days_from_due_date = (int)$client->settings[$action];
			$invoice_date = $this->Date->format("Y-m-d", $invoice->date_due . "Z");
			$invoice_reminder_datetime = $this->Date->toTime($invoice_date . ($days_from_due_date >= 0 ? " +" : " -") . abs($days_from_due_date) . " days");

			// Reminder should be sent for this invoice today
			if ($invoice_reminder_datetime == $todays_datetime)
				return true;
		}

		return false;
	}

	/**
	 * Sends an autodebit notice to the given contact regarding this invoice
	 * @see Cron::paymentReminders()
	 *
	 * @param stdClass $client The client object
	 * @param stdClass $contact The contact object representing one of the client's contacts
	 * @param stdClass $invoice The invoice to send a payment notice about, belonging to this client
	 * @param mixed An stdClass object representing the autodebit payment account for this client (if any) (optional)
	 * @return mixed An array of errors on failure, or false on success
	 */
	private function sendAutodebitNotice(stdClass $client, stdClass $contact, stdClass $invoice, $autodebit_account=null) {
		if (!isset($this->Emails))
			$this->uses(array("Emails"));
		if (!isset($this->CurrencyFormat))
			$this->helpers(array("CurrencyFormat"));

		// Build tags
		$autodebit_date = $this->Invoices->getAutodebitDate($invoice->id);

		// Format invoice fields
		$invoice->date_due_formatted = $this->Date->cast($invoice->date_due, $client->settings['date_format']);
		$invoice->date_billed_formatted = $this->Date->cast($invoice->date_billed, $client->settings['date_format']);
		$invoice->date_closed_formatted = $this->Date->cast($invoice->date_closed, $client->settings['date_format']);
		$invoice->date_autodebit_formatted = $this->Date->cast($invoice->date_autodebit, $client->settings['date_format']);

		// Set a hash for the payment URL
		$hash = $this->Invoices->createPayHash($client->id, $invoice->id);

		// Get the company hostname
		$hostname = isset(Configure::get("Blesta.company")->hostname) ? Configure::get("Blesta.company")->hostname : "";

		$tags = array(
			'contact' => $contact,
			'invoice' => $invoice,
			'payment_account' => $autodebit_account,
			'client_url' => $this->Html->safe($hostname . $this->client_uri),
			'payment_url' => $this->Html->safe($hostname . $this->client_uri . "pay/method/" . $invoice->id . "/?sid=" . rawurlencode($this->Clients->systemEncrypt('c=' . $client->id . '|h=' . substr($hash, -16)))),
			'autodebit_date' => $this->Date->cast(($autodebit_date ? $autodebit_date : ""), $client->settings['date_format']),
			'amount' => $this->CurrencyFormat->cast($invoice->due, $invoice->currency),
			'amount_formatted' => $this->CurrencyFormat->format($invoice->due, $invoice->currency)
		);

		// Send the email
		$this->Emails->send("auto_debit_pending", $this->company_id, $client->settings['language'], $contact->email, $tags, null, null, null, array('to_client_id'=>$client->id));

		return $this->Emails->errors();
	}

	/**
	 * Sends a payment notice to the given contact regarding this invoice
	 * @see Cron::paymentReminders()
	 *
	 * @param string $action The payment notice setting (i.e. one of "notice1", "notice2", "notice3")
	 * @param stdClass $client The client object
	 * @param stdClass $contact The contact object representing one of the client's contacts
	 * @param stdClass $invoice The invoice to send a payment notice about, belonging to this client
	 * @param mixed An stdClass object representing the autodebit payment account for this client (if any) (optional)
	 * @return mixed An array of errors on failure, or false on success
	 */
	private function sendPaymentNotice($action, stdClass $client, stdClass $contact, stdClass $invoice, $autodebit_account=null) {
		if (!isset($this->InvoiceDelivery)) {
			$this->components(array("InvoiceDelivery"));
		}

		// Determine the email template to send
		$email_group_action = null;
		switch ($action) {
			case "notice1":
				$email_group_action = "invoice_notice_first";
				break;
			case "notice2":
				$email_group_action = "invoice_notice_second";
				break;
			case "notice3":
				$email_group_action = "invoice_notice_third";
				break;
		}

		// Build tags
		$autodebit_date = $this->Invoices->getAutodebitDate($invoice->id);

		// Format invoice fields
		$invoice->date_due_formatted = $this->Date->cast($invoice->date_due, $client->settings['date_format']);
		$invoice->date_billed_formatted = $this->Date->cast($invoice->date_billed, $client->settings['date_format']);
		$invoice->date_closed_formatted = $this->Date->cast($invoice->date_closed, $client->settings['date_format']);
		$invoice->date_autodebit_formatted = $this->Date->cast($invoice->date_autodebit, $client->settings['date_format']);

		// Set a hash for the payment URL
		$hash = $this->Invoices->createPayHash($client->id, $invoice->id);

		// Get the company hostname
		$hostname = isset(Configure::get("Blesta.company")->hostname) ? Configure::get("Blesta.company")->hostname : "";

		$tags = array(
			'contact' => $contact,
			'invoice' => $invoice,
			'payment_account' => $autodebit_account,
			'client_url' => $this->Html->safe($hostname . $this->client_uri),
			'payment_url' => $this->Html->safe($hostname . $this->client_uri . "pay/method/" . $invoice->id . "/?sid=" . rawurlencode($this->Clients->systemEncrypt('c=' . $client->id . '|h=' . substr($hash, -16)))),
			'autodebit' => ($client->settings['autodebit'] == "true"),
			'autodebit_date' => $autodebit_date,
			'autodebit_date_formatted' => $this->Date->cast(($autodebit_date ? $autodebit_date : ""), $client->settings['date_format'])
		);

		$options = array(
			'email_template' => $email_group_action,
			'base_client_url' => $tags['client_url'],
			'email_tags' => $tags
		);

		// Deliver the invoices
		$this->InvoiceDelivery->deliverInvoices(array($invoice->id), "email", $contact->email, null, $options);

		return $this->InvoiceDelivery->errors();
	}

	/**
	 * Runs the card expiration reminder task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function cardExpirationReminders($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("card_expiration_reminders");

		// Run the cron task if enabled, and this is the 15th of the month
		if ($this->isCurrentDay(15) && $this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.cardexpirationreminders.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("Accounts", "Clients", "Contacts", "Emails"));
			$cc_accounts = $this->Accounts->getCardsExpireSoon($this->Date->format("c"));

			// Get the company hostname
			$hostname = isset(Configure::get("Blesta.company")->hostname) ? Configure::get("Blesta.company")->hostname : "";

			// Send an email to every contact regarding the payment account card expiration
			$card_types = $this->Accounts->getCcTypes();
			$emails_sent = null;
			foreach ($cc_accounts as $account) {
				// Get contact and client
				$contact = $this->Contacts->get($account->contact_id);
				$client = $this->Clients->get($contact->client_id);

				$tags = array(
					'contact' => $contact,
					'card_type' => (isset($card_types[$account->type]) ? $card_types[$account->type] : ""),
					'last_four' => $account->last4,
					'client_url' => $this->Html->safe($hostname . $this->client_uri)
				);
				$this->Emails->send("credit_card_expiration", $this->company_id, $client->settings['language'], $contact->email, $tags, null, null, null, array('to_client_id'=>$client->id));

				// Log success/error
				if (($errors = $this->Emails->errors()))
					$output = $this->setOutput(Language::_("Cron.cardexpirationreminders.failed", true, $contact->first_name, $contact->last_name, $client->id_code), $output);
				else
					$output = $this->setOutput(Language::_("Cron.cardexpirationreminders.success", true, $contact->first_name, $contact->last_name, $client->id_code), $output);
			}

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.cardexpirationreminders.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Runs the deliver invoices task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function deliverInvoices($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("deliver_invoices");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.deliverinvoices.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("Clients", "Invoices"));

			// Get enabled delivery methods that we may send invoices by
			$delivery_methods = $this->Invoices->getDeliveryMethods();

			// Get all invoices to be delivered
			$deliverable_invoices = $this->Invoices->getAll(null, "to_deliver", array('invoices.client_id'=>"ASC"));

			// Group all deliverable invoices
			$client_invoices = $this->groupInvoices($deliverable_invoices, "delivery_method");

			// Deliver the invoices
			$num_invoices = null;
			foreach ($client_invoices as $client_id => $invoice_status_types) {
				foreach ($invoice_status_types as $invoice_status_type=>$methods) {
					foreach ($methods as $delivery_method=>$invoice_ids) {
						// Only deliver invoices if this method is in use
						if (isset($delivery_methods[$delivery_method])) {
							// Get the client
							if (($client = $this->Clients->get($client_id))) {
								// Send the invoices to this client via this delivery method
								$errors = $this->sendInvoices($invoice_ids, $client, $delivery_method, $invoice_status_type);

								// Log success/error
								$num_invoices = count($invoice_ids);
								$delivery_method_name = Language::_("Cron.deliverinvoices.method_" . $delivery_method, true);
								if ($errors) {
									$error_message = "";
									foreach ($errors as $err) {
										foreach ($err as $message)
											$error_message = $message;
									}

									if ($num_invoices == 1)
										$output = $this->setOutput(Language::_("Cron.deliverinvoices.delivery_error_one", true, $client->id_code, $delivery_method_name, $error_message), $output);
									else
										$output = $this->setOutput(Language::_("Cron.deliverinvoices.delivery_error", true, $client->id_code, $delivery_method_name, $num_invoices, $error_message), $output);
								}
								else {
									if ($num_invoices == 1)
										$output = $this->setOutput(Language::_("Cron.deliverinvoices.delivery_success_one", true, $client->id_code, $delivery_method_name), $output);
									else
										$output = $this->setOutput(Language::_("Cron.deliverinvoices.delivery_success", true, $client->id_code, $delivery_method_name, $num_invoices), $output);
								}
							}
						}
					}
				}
			}

			// No invoices were sent
			if ($num_invoices === null)
				$output = $this->setOutput(Language::_("Cron.deliverinvoices.none", true), $output);

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.deliverinvoices.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Groups invoices into invoice IDs by client and $type
	 *
	 * @param array $invoices A list of stdClass objects representing invoices
	 * @param string $type The method to use to group the invoices by client (optional), including:
	 * 	- delivery_method Group the invoices by client ID and invoice delivery method
	 * 	- currency Group the invoices by client ID and invoice currency
	 * @param array $options A set of options that may be used for some types
	 * @return array A list of invoices grouped by client and delivery method
	 */
	private function groupInvoices(array $invoices, $type=null, array $options=null) {
		$this->uses(array("Invoices"));
		if (!isset($this->CurrencyFormat))
			$this->helpers(array("CurrencyFormat"));

		$grouped_invoices = array();
		$today = date("Y-m-d", strtotime(date("c")));

		// Group invoices
		foreach ($invoices as $invoice) {
			// Set a client group for invoices
			if (!isset($grouped_invoices[$invoice->client_id]))
				$grouped_invoices[$invoice->client_id] = array();

			switch ($type) {
				case "delivery_method":
					// Set the invoice type based on status
					$invoice_status_type = "unpaid";
					if ($invoice->date_closed != null)
						$invoice_status_type = "paid";

					// Get the invoice delivery methods of this invoice
					$delivery_methods = $this->Invoices->getDelivery($invoice->id);
					foreach ($delivery_methods as $method) {
						if ($method->date_sent == null && $method->method != "paper") {
							if (!isset($grouped_invoices[$invoice->client_id][$invoice_status_type][$method->method]))
								$grouped_invoices[$invoice->client_id][$invoice_status_type][$method->method] = array();
							$grouped_invoices[$invoice->client_id][$invoice_status_type][$method->method][] = $method->invoice_id;
						}
					}
					break;
				case "currency":
					$grouped_invoices[$invoice->client_id][] = $invoice;
					break;
			}
		}

		// Remove data no longer needed
		unset($invoices);

		// Sum the amounts of all invoices for each currency
		if ($type == "currency") {
			$clients = array();
			foreach ($grouped_invoices as $client_id=>$client_invoices) {
				foreach ($client_invoices as $client_invoice) {
					if (!isset($clients[$client_id][$client_invoice->currency]))
						$clients[$client_id][$client_invoice->currency] = array('amount'=>0, 'invoice_amounts'=>array());
					$clients[$client_id][$client_invoice->currency]['amount'] += $client_invoice->due;
					$clients[$client_id][$client_invoice->currency]['invoice_amounts'][$client_invoice->id] = $this->CurrencyFormat->cast($client_invoice->due, $client_invoice->currency);
				}
			}

			$grouped_invoices = $clients;
		}

		return $grouped_invoices;
	}

	/**
	 * Sends a group of invoices to a given client via the delivery method and
	 * marks each invoice delivered
	 *
	 * @param array $invoice_ids A list of invoice IDs belonging to the client
	 * @param stdClass An stdClass object representing the client
	 * @param string $delivery_method The delivery method to send the invoices with
	 * @param string $invoice_status_type The invoice status type indicating the type of invoice_ids given: (optional, default "unpaid")
	 * 	- paid
	 * 	- unpaid
	 * @return array An array of errors from attempting to deliver the invoices
	 */
	private function sendInvoices(array $invoice_ids, $client, $delivery_method, $invoice_status_type="unpaid") {
		$this->uses(array("Clients", "Contacts", "Invoices"));
		$this->components(array("InvoiceDelivery"));

		// Get all billing contacts to deliver the invoices to, or the primary if none exist
		$contacts = $this->Contacts->getAll($client->id, "billing");
		if (empty($contacts))
			$contacts = $this->Contacts->getAll($client->id, "primary");

		// Get the company hostname
		$hostname = isset(Configure::get("Blesta.company")->hostname) ? Configure::get("Blesta.company")->hostname : "";

		// Set the email template to use
		$email_template = "invoice_delivery_unpaid";
		if ($invoice_status_type == "paid")
			$email_template = "invoice_delivery_paid";

		// Deliver the invoices to each contact
		$errors = array();
		$delivered = 0;
		foreach ($contacts as $contact) {

			// Deliver the invoice to the contact's email or fax number
			$deliver_to = $contact->email;
			if ($delivery_method == "interfax") {
				$deliver_to = "";

				$fax_numbers = $this->Contacts->getNumbers($contact->id, "fax");
				if (!empty($fax_numbers[0]))
					$deliver_to = $fax_numbers[0]->number;
			}

			$options = array(
				'email_template' => $email_template,
				'base_client_url' => $this->Html->safe($hostname . $this->client_uri),
				'set_built_invoices' => true, // use the invoices that InvoiceDelivery::deliverInvoices() will build as the "invoices" tag
				'email_tags' => array(
					'contact' => $contact,
					'invoices' => "", // this tag will be populated in InvoiceDelivery::deliverInvoices()
					'autodebit' => ($client->settings['autodebit'] == "true"),
					'client_url' => $this->Html->safe($hostname . $this->client_uri)
				)
			);

			// Deliver the invoices
			$this->InvoiceDelivery->deliverInvoices($invoice_ids, $delivery_method, $deliver_to, null, $options);

			// Set errors
			$temp_errors = $this->InvoiceDelivery->errors();
			if (is_array($temp_errors))
				$errors = array_merge($errors, $temp_errors);
			else
				$delivered++;
		}

		// Mark each invoice as sent if it has been delivered
		if ($delivered > 0) {
			// Get each invoice delivery record that has not yet been marked sent
			$delivery_records = $this->Invoices->getAllDelivery($invoice_ids, $delivery_method, "unsent");

			// Mark each invoice sent
			foreach ($delivery_records as $delivery)
				$this->Invoices->delivered($delivery->id);
		}

		return $errors;
	}

	/**
	 * Runs the deliver reports task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function deliverReports($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("deliver_reports");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.deliverreports.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			// Load the ReportManager
			$this->uses(array("ReportManager"));

			// Deliver reports
			$output = $this->setOutput($this->deliverReportAgingInvoices(), $output, false);
			$output = $this->setOutput($this->deliverReportInvoiceCreation(), $output, false);
			$output = $this->setOutput($this->deliverReportTaxLiability(), $output, false);

			// Remove the ReportManager so it can be instantiated again for other companies with the correct company ID
			unset($this->ReportManager);

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.deliverreports.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Delivers the Aging Invoices report
	 *
	 *
	 * @return string The output from delivering the report
	 */
	private function deliverReportAgingInvoices() {
		$output = "";

		// Only deliver the aging invoices report on the first of the month
		if ($this->isCurrentDay(1)) {
			if (!isset($this->Staff))
				$this->uses(array("Staff"));

			$output = $this->setOutput(Language::_("Cron.deliverreports.aging_invoices.attempt", true), $output);

			// Fetch the report
			$vars = array('status' => "active");
			$path_to_file = $this->ReportManager->fetchAll("aging_invoices", $vars, "csv", "file");

			// Set attachment
			$attachments = array();
			if (file_exists($path_to_file)) {
				$attachments[] = array(
					'path' => $path_to_file,
					'name' => "aging_invoices_" . $this->Date->format("Y-m-d", date("c")) . ".csv"
				);
			}
			else
				$output = $this->setOutput(Language::_("Cron.deliverreports.aging_invoices.attachment_fail", true), $output);

			$tags = array('company' => Configure::get("Blesta.company"));
			$this->Staff->sendNotificationEmail("report_ar", $this->company_id, $tags, null, null, $attachments);

			if (($errors = $this->Staff->errors())) {
				// Error, failed to send
				$output = $this->setOutput(Language::_("Cron.deliverreports.aging_invoices.email_error", true), $output);
			}
			else {
				// Success, email sent
				$output = $this->setOutput(Language::_("Cron.deliverreports.aging_invoices.email_success", true), $output);
			}

			// Remove the temp file
			@unlink($path_to_file);
		}

		return $output;
	}

	/**
	 * Delivers the Tax Liability report
	 *
	 * @return string The output from delivering the report
	 */
	private function deliverReportTaxLiability() {
		$output = "";

		// Only deliver the tax liability report on the first of the month
		if ($this->isCurrentDay(1)) {
			if (!isset($this->Staff))
				$this->uses(array("Staff"));

			$output = $this->setOutput(Language::_("Cron.deliverreports.tax_liability.attempt", true), $output);

			// Fetch the report
			$last_month = strtotime(date("c") . " -1 day");
			$vars = array(
				'start_date' => date("Y-m-01", $last_month),
				'end_date' => date("Y-m-t", $last_month)
			);
			$path_to_file = $this->ReportManager->fetchAll("tax_liability", $vars, "csv", "file");

			// Set attachment
			$attachments = array();
			if (file_exists($path_to_file)) {
				$attachments[] = array(
					'path' => $path_to_file,
					'name' => "tax_liability_" . $this->Date->format("Y-m-d", date("c")) . ".csv"
				);
			}
			else
				$output = $this->setOutput(Language::_("Cron.deliverreports.tax_liability.attachment_fail", true), $output);

			$tags = array('company' => Configure::get("Blesta.company"));
			$this->Staff->sendNotificationEmail("report_tax_liability", $this->company_id, $tags, null, null, $attachments);

			if (($errors = $this->Staff->errors())) {
				// Error, failed to send
				$output = $this->setOutput(Language::_("Cron.deliverreports.tax_liability.email_error", true), $output);
			}
			else {
				// Success, email sent
				$output = $this->setOutput(Language::_("Cron.deliverreports.tax_liability.email_success", true), $output);
			}

			// Remove the temp file
			@unlink($path_to_file);
		}

		return $output;
	}

	/**
	 * Delivers the Invoice Creation report
	 *
	 * @return string The output from delivering the report
	 */
	private function deliverReportInvoiceCreation() {
		if (!isset($this->Staff))
			$this->uses(array("Staff"));

		$output = $this->setOutput(Language::_("Cron.deliverreports.invoice_creation.attempt", true));

		// Fetch the report
		$yesterday = date("Y-m-d", strtotime(date("c") . " -1 day"));
		$vars = array(
			'status' => "active",
			'start_date' => $yesterday,
			'end_date' => $yesterday
		);
		$path_to_file = $this->ReportManager->fetchAll("invoice_creation", $vars, "csv", "file");

		// Set attachment
		$attachments = array();
		if (file_exists($path_to_file)) {
			$attachments[] = array(
				'path' => $path_to_file,
				'name' => "invoice_creation_" . $yesterday . ".csv"
			);
		}
		else
			$output = $this->setOutput(Language::_("Cron.deliverreports.invoice_creation.attachment_fail", true), $output);

		$tags = array('company' => Configure::get("Blesta.company"));
		$this->Staff->sendNotificationEmail("report_invoice_creation", $this->company_id, $tags, null, null, $attachments);

		if (($errors = $this->Staff->errors())) {
			// Error, failed to send
			$output = $this->setOutput(Language::_("Cron.deliverreports.invoice_creation.email_error", true), $output);
		}
		else {
			// Success, email sent
			$output = $this->setOutput(Language::_("Cron.deliverreports.invoice_creation.email_success", true), $output);
		}

		// Remove the temp file
		@unlink($path_to_file);

		return $output;
	}

	/**
	 * Runs the process service changes task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function processServiceChanges($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("process_service_changes");

		// Run the task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.processservicechanges.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			// Process the service changes
			$this->runProcessServiceChanges($output);

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.processservicechanges.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}
	}

	/**
	 * Performs queued service change updates
	 * @see ::processServiceChanges
	 *
	 * @param string $output A reference to the combined output log data
	 */
	private function runProcessServiceChanges(&$output) {
		$this->uses(array("Clients", "Invoices", "Services", "ServiceChanges", "Transactions"));
		$statuses = $this->ServiceChanges->getStatuses();
		$client_group_settings = array();

		// Fetch all pending service changes
		$service_changes = $this->ServiceChanges->getAll("pending");

		foreach ($service_changes as $service_change) {
			// Check whether the associated invoice is closed and process it
			$invoice = $this->Invoices->get($service_change->invoice_id);
			if ($invoice) {
				// Skip processing the queued change if it is apart of another company.
				// Fetching a client filters on the current company automatically, so the client
				// must exist for us to process the queued change
				if (!($client = $this->Clients->get($invoice->client_id, false))) {
					continue;
				}

				// Fetch client group settings
				if (!isset($client_group_settings[$client->client_group_id])) {
					$client_group_settings[$client->client_group_id] = $this->SettingsCollection->fetchClientGroupSettings($client->client_group_id);
				}

				// Only process active services
				$service = $this->Services->get($service_change->service_id);
				if (!$service || $service->status != "active") {
					$output = $this->setOutput(Language::_("Cron.processservicechanges.service_inactive", true, $service_change->id), $output);
				}
				else {
					$this->processServiceChange($service_change, $invoice, $client_group_settings[$client->client_group_id], $statuses, $output);
				}
			}
			else {
				// No invoice for this service change. Mark it as error
				$this->ServiceChanges->edit(array('status' => "error"));
				$output = $this->setOutput(Language::_("Cron.processservicechanges.missing_invoice", true, $service_change->invoice_id, $service_change->id, $service_change->service_id, $statuses['error']), $output);
			}
		}
	}

	/**
	 * Processes a single service change
	 * @see ::runProcessServiceChanges
	 *
	 * @param stdClass $service_change An stdClass object representing the service change
	 * @param stdClass $invoice An stdClass object representing the invoice
	 * @param array $settings An array of client group settings
	 * @param array $statuses An array of service change statuses
	 * @param string $output A reference to the combined output log data
	 */
	private function processServiceChange($service_change, $invoice, $settings, $statuses, &$output) {
		$cancel_days = $settings['cancel_service_changes_days'];
		$cancel_date = $this->ServiceChanges->dateToUtc(date("c") . " -" . (int)$cancel_days . " days");

		// Process queued service changes if setting enables us to do so, and invoice is closed
		if ($settings['process_paid_service_changes'] == "true" && $invoice->date_closed !== null &&
			in_array($invoice->status, array("active", "proforma"))) {
			// Attempt to process the service change
			$this->ServiceChanges->process($service_change->id);

			// Log the result of the process
			$updated_change = $this->ServiceChanges->get($service_change->id);
			$output = $this->setOutput(Language::_("Cron.processservicechanges.process_result", true, $service_change->id, $statuses[$updated_change->status]), $output);
		}
		elseif (strtotime($cancel_date) > strtotime($invoice->date_due)) {
			// The service change expired and must be canceled
			$this->ServiceChanges->edit($service_change->id, array('status' => "canceled"));

			// Log that the change expired
			$updated_change = $this->ServiceChanges->get($service_change->id);
			$output = $this->setOutput(Language::_("Cron.processservicechanges.expired", true, $service_change->id, $statuses[$updated_change->status]), $output);
		}

		// Update the associated invoice only if it has been changed (completed or canceled)
		if (isset($updated_change) && !in_array($updated_change->status, array("pending", "error"))) {
			$this->updateServiceChangeInvoice($updated_change, $invoice);
		}
	}

	/**
	 * Updates the invoice for a service change
	 * @see ::processServiceChange
	 *
	 * @param stdClass $service_change An stdClass object representing the service change
	 * @param stdClass $invoice An stdClass object representing the invoice
	 */
	private function updateServiceChangeInvoice($service_change, $invoice) {
		// Set required invoice information to update
		$vars = array('status' => $invoice->status);

		// Update the invoice to associate it with the service
		if ($service_change->status == "completed") {
			$vars['lines'] = array();

			foreach ($invoice->line_items as $line) {
				$vars['lines'][] = array(
					'id' => $line->id,
					'invoice_id' => $line->invoice_id,
					'service_id' => $service_change->service_id,
					'description' => $line->description,
					'qty' => $line->qty,
					'amount' => $line->amount,
					'tax' => !empty($line->taxes)
				);
			}
		}
		else {
			// Fetch payments applied to the invoice
			$transactions = $this->Transactions->getApplied(null, $invoice->id);

			// Unapply payments from the invoice and void it
			foreach ($transactions as $transaction) {
				$this->Transactions->unapply($transaction->id, array($invoice->id));
			}

			// Void the invoice
			$vars['status'] = "void";
		}

		$this->Invoices->edit($invoice->id, $vars);
	}

	/**
	 * Runs the process renewing services task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function processRenewingServices($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("process_renewing_services");

		// Run the task if enabled
		if ($this->isTimeToRun($cron_task)) {
			$this->uses(array("Services"));

			// Get the last time this task has run
			$last_run = $this->Logs->getCronLastRun($cron_task->key, $cron_task->plugin_dir);

			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.processrenewingservices.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			// Only attempt service renewals if a previous task exists
			if ($last_run || $cron_task->date_enabled) {
				// Set the date as either the last run date or the last time that the cron task was enabled
				// This is to prevent service renewals when the cron task was disabled for a period of time
				$run_date = (isset($last_run->start_date) ? $last_run->start_date : $cron_task->date_enabled);
				if ($cron_task->date_enabled && $last_run) {
					if (strtotime($cron_task->date_enabled) >= strtotime($last_run->start_date))
						$run_date = $cron_task->date_enabled;
				}

				// Fetch all services since the last run date
				$services = $this->Services->getAllRenewablePaid($run_date . "Z");

				// Renew the services
				foreach ($services as $service) {
					$this->Services->renew($service->id);

					// Log success/error
					if (($errors = $this->Services->errors()))
						$output = $this->setOutput(Language::_("Cron.processrenewingservices.renew_error", true, $service->id_code, $service->client_id_code), $output);
					else
						$output = $this->setOutput(Language::_("Cron.processrenewingservices.renew_success", true, $service->id_code, $service->client_id_code), $output);
				}
			}

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.processrenewingservices.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Runs the suspend services task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function suspendServices($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("suspend_services");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.suspendservices.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("Clients", "ClientGroups", "Services"));

			// Get all client groups
			$client_groups = $this->ClientGroups->getAll($this->company_id);

			foreach ($client_groups as $client_group) {
				// Get the service suspension days
				$suspension_days = $this->ClientGroups->getSetting($client_group->id, "suspend_services_days_after_due");
				$suspendable = array();

				// Skip if we should not do any suspensions on this client group
				if ($suspension_days->value == "never")
					continue;

				// Set the date at which services should be suspended if the invoices are past due
				// and encompass the entire day
				$suspension_date = date("c", strtotime(date("c") . " -" . abs((int)$suspension_days->value) . " days"));

				// Get all services ready to be suspended
				$services = $this->Services->getAllPendingSuspension($client_group->id, $suspension_date);

				// Suspend the services
				foreach ($services as $service) {
					if (!isset($suspendable[$service->client_id])) {
						$suspendable[$service->client_id] = "false";
						$autosuspend = $this->Clients->getSetting($service->client_id, "autosuspend");
						$autosuspend_date = $this->Clients->getSetting($service->client_id, "autosuspend_date");

						if ($autosuspend)
							$suspendable[$service->client_id] = $autosuspend->value;

						if ($suspendable[$service->client_id] == "true" && $autosuspend_date) {
							$suspendable[$service->client_id] = strtotime($autosuspend_date->value) < time() ? "true" : "false";
						}
					}

					// Do not attempt to suspend services if autosuspend is disabled
					if ($suspendable[$service->client_id] == "false")
						continue;

					$this->Services->suspend($service->id, array('use_module' => "true", 'staff_id' => null));

					if (($errors = $this->Services->errors())) {
						// Send suspension error email
						$this->sendServiceErrorNoticeEmail("suspend", $service, $errors);

						$output = $this->setOutput(Language::_("Cron.suspendservices.suspend_error", true, $service->id, $service->client_id), $output);
					}
					else
						$output = $this->setOutput(Language::_("Cron.suspendservices.suspend_success", true, $service->id, $service->client_id), $output);
				}
			}

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.suspendservices.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Runs the unsuspend services task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function unsuspendServices($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("unsuspend_services");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.unsuspendservices.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("ClientGroups", "Services"));

			// Get all client groups
			$client_groups = $this->ClientGroups->getAll($this->company_id);

			foreach ($client_groups as $client_group) {
				// Get all services ready to be suspended
				$services = $this->Services->getAllPendingUnsuspension($client_group->id);

				// Suspend the services
				foreach ($services as $service) {
					$this->Services->unsuspend($service->id, array('use_module' => "true", 'staff_id' => null));

					if (($errors = $this->Services->errors())) {
						// Send the unsuspension error email
						$this->sendServiceErrorNoticeEmail("unsuspend", $service, $errors);

						$output = $this->setOutput(Language::_("Cron.unsuspendservices.unsuspend_error", true, $service->id, $service->client_id), $output);
					}
					else
						$output = $this->setOutput(Language::_("Cron.unsuspendservices.unsuspend_success", true, $service->id, $service->client_id), $output);
				}
			}

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.unsuspendservices.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Runs the cancel scheduled services task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function cancelScheduledServices($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("cancel_scheduled_services");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.cancelscheduledservices.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			// Get services pending cancelation
			$this->uses(array("Services"));
			$services = $this->Services->getAllPendingCancelation();

			// Cancel each service
			foreach ($services as $service) {
				$this->Services->cancel($service->id, array('date_canceled' => $this->Date->format("Y-m-d H:i:s", $service->date_canceled . "Z")));

				if (($errors = $this->Services->errors())) {
					// Send the cancellation error email
					$this->sendServiceErrorNoticeEmail("cancel", $service, $errors);

					$output = $this->setOutput(Language::_("Cron.cancelscheduledservices.cancel_error", true, $service->id_code, $service->client_id_code), $output);
				}
				else
					$output = $this->setOutput(Language::_("Cron.cancelscheduledservices.cancel_success", true, $service->id_code, $service->client_id_code), $output);
			}

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.cancelscheduledservices.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Runs the add paid pending services task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function addPaidPendingServices($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("provision_pending_services");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.addpaidpendingservices.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("ClientGroups", "Services"));

			// Get all client groups for this company
			$client_groups = $this->ClientGroups->getAll($this->company_id);

			foreach ($client_groups as $client_group) {
				// Determine whether we should auto provision paid pending services for this client group
				$provision_services = $this->SettingsCollection->fetchClientGroupSetting($client_group->id, $this->ClientGroups, "auto_paid_pending_services");
				$provision_services = (isset($provision_services['value']) && $provision_services['value'] == "true" ? true : false);

				if ($provision_services) {
					// Fetch all paid pending services for this client group
					$services = $this->Services->getAllPaidPending($client_group->id);

					foreach ($services as $service) {
						// Add service module fields
						$module_fields = array();
						foreach ($service->fields as $field)
							$module_fields[$field->key] = $field->value;

						// Change the status of the service to 'active'
						$this->Services->edit($service->id, array_merge($module_fields, array('status' => "active")), false, true);

						// Log the change
						if (($errors = $this->Services->errors())) {
							// Send the creation error email
							$this->sendServiceErrorNoticeEmail("create", $service, $errors);

							$output = $this->setOutput(Language::_("Cron.addpaidpendingservices.service_error", true, $service->id_code, $service->client_id_code), $output);
						}
						else
							$output = $this->setOutput(Language::_("Cron.addpaidpendingservices.service_success", true, $service->id_code, $service->client_id_code), $output);
					}
				}
			}

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.addpaidpendingservices.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}
	}

	/**
	 * Runs the update exchange rates task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function updateExchangeRates($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("exchange_rates");

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.updateexchangerates.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			// Update the exchange rates
			$this->uses(array("Currencies"));
			$this->Currencies->updateRates();

			// Check for errors
			$error_messages = "";
			if (($errors = $this->Currencies->errors())) {
				$error_messages = Language::_("Cron.updateexchangerates.failed", true);
				foreach ($errors as $error) {
					foreach ($error as $message)
						$error_messages = $this->Html->concat(" ", $error_messages, $message);
				}
			}
			$output = $this->setOutput((empty($error_messages) ? Language::_("Cron.updateexchangerates.success", true) : $error_messages), $output);

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.updateexchangerates.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		return false;
	}

	/**
	 * Runs all plugin tasks
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function pluginTasks($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Load the plugin factory if not already loaded
		if (!isset($this->Plugins))
			Loader::loadComponents($this, array("Plugins"));

		// Run all plugin tasks
		foreach ($this->cron_tasks as $cron_task) {
			if ($cron_task->plugin_id) {

				// Run the cron task if enabled
				if ($this->isTimeToRun($cron_task)) {

					// Log task has begun
					$cron_task_event = "";
					$output = $this->setOutput(Language::_("Cron.plugin.attempt", true, $cron_task->plugin_dir, $cron_task->key));
					$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

					$plugin = $this->Plugins->create($cron_task->plugin_dir);
					$plugin->cron($cron_task->key);

					// Log task has finished
					$output = $this->setOutput(Language::_("Cron.plugin.completed", true, $cron_task->plugin_dir, $cron_task->key), $output);
					$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
				}
			}
		}
	}

	/**
	 * Runs the clean-up logs task
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	public function cleanLogs($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("cleanup_logs");
		$start_date = $this->Date->format("c");

		// Get the date at which logs should be purged from the settings
		$company_settings = $this->SettingsCollection->fetchSettings(null, $this->company_id);

		// Run the cron task if enabled and the rotation policy (log_days) is valid
		if (is_numeric($company_settings['log_days']) && $this->isTimeToRun($cron_task)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.cleanlogs.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $start_date, $output);

			// Get the (local) date at which logs should be purged
			$past_date = $this->Date->format("Y-m-d H:i:s", strtotime($start_date . " -" . abs((int)$company_settings['log_days']) . " days"));

			// Delete the logs
			if (Configure::get("Blesta.auto_delete_gateway_logs"))
				$output = $this->setOutput(Language::_("Cron.cleanlogs.logs_gateway_deleted", true, $this->Logs->deleteGatewayLogs($past_date)), $output);
			if (Configure::get("Blesta.auto_delete_module_logs"))
				$output = $this->setOutput(Language::_("Cron.cleanlogs.logs_module_deleted", true, $this->Logs->deleteModuleLogs($past_date)), $output);
			if (Configure::get("Blesta.auto_delete_accountaccess_logs"))
				$output = $this->setOutput(Language::_("Cron.cleanlogs.logs_accountaccess_deleted", true, $this->Logs->deleteAccountAccessLogs($past_date)), $output);
			if (Configure::get("Blesta.auto_delete_contact_logs"))
				$output = $this->setOutput(Language::_("Cron.cleanlogs.logs_contact_deleted", true, $this->Logs->deleteContactLogs($past_date)), $output);
			if (Configure::get("Blesta.auto_delete_email_logs"))
				$output = $this->setOutput(Language::_("Cron.cleanlogs.logs_email_deleted", true, $this->Logs->deleteEmailLogs($past_date)), $output);
			if (Configure::get("Blesta.auto_delete_user_logs"))
				$output = $this->setOutput(Language::_("Cron.cleanlogs.logs_user_deleted", true, $this->Logs->deleteUserLogs($past_date)), $output);
			if (Configure::get("Blesta.auto_delete_transaction_logs"))
				$output = $this->setOutput(Language::_("Cron.cleanlogs.logs_transaction_deleted", true, $this->Logs->deleteTransactionLogs($past_date)), $output);

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.cleanlogs.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}

		// Delete old cron logs
		$date = $this->Date->format("Y-m-d H:i:s", strtotime($start_date . " -" . abs((int)Configure::get("Blesta.cron_log_retention_days")) . " days"));
		$this->Logs->deleteCronLogs($date);

		return false;
	}

	/**
	 * Runs the SFTP database backup task (system)
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	private function sftpBackup($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("backups_sftp", null, true);

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task, true)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.backups_sftp.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			try {
				$this->uses(array("Backup"));
				$this->Backup->sendBackup("ftp");

				if (($errors = $this->Backup->errors()) && isset($errors['ftp_failed']))
					$output = $this->setOutput($errors['ftp_failed'], $output);
				else
					$output = $this->setOutput(Language::_("Cron.backups_sftp.success", true), $output);
			}
			catch (Exception $e) {
				$output = $this->setOutput($e->getMessage(), $output);
			}

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.backups_sftp.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}
	}

	/**
	 * Runs the AmazonS3 database backup task (system)
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	private function amazonS3Backup($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("backups_amazons3", null, true);

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task, true)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.backups_amazons3.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			$this->uses(array("Backup"));
			$this->Backup->sendBackup("amazons3");

			if (($errors = $this->Backup->errors()) && isset($errors['amazons3_failed']))
				$output = $this->setOutput($errors['amazons3_failed'], $output);
			else
				$output = $this->setOutput(Language::_("Cron.backups_amazons3.success", true), $output);

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.backups_amazons3.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}
	}

	/**
	 * Runs the system task to call home and validate the license
	 *
	 * @param string $cron_log_group The cron log group that this event is apart of (optional, default null)
	 */
	private function license($cron_log_group=null) {
		// Create a cron log group if none given
		if (!$cron_log_group)
			$cron_log_group = $this->createCronLogGroup();

		// Get this cron task
		$cron_task = $this->getCronTask("license_validation", null, true);

		// Run the cron task if enabled
		if ($this->isTimeToRun($cron_task, true)) {
			// Log this task has begun
			$cron_task_event = "";
			$output = $this->setOutput(Language::_("Cron.license.attempt", true));
			$this->logTaskStarted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);

			// Fetch the license
			$this->uses(array("License"));
			$this->License->fetchLicense();

			// Log this task has completed
			$output = $this->setOutput(Language::_("Cron.license.completed", true), $output);
			$this->logTaskCompleted($cron_task->task_run_id, $cron_task_event, $cron_log_group, $this->Date->format("c"), $output);
		}
	}

	/**
	 * Sends the service error notice emails (e.g. (un)suspension, cancellation, creation) to staff
	 *
	 * @param string $type The type of email to send (i.e. "create", "cancel", "suspend" or "unsuspend")
	 * @param stdClass $service An object representing the service
	 * @param array $errors A list of errors returned by the module
	 */
	private function sendServiceErrorNoticeEmail($type, $service, $errors) {
		Loader::loadModels($this, array("Clients", "Contacts", "Packages", "Staff"));

		// Fetch the client
		if (($package = $this->Packages->getByPricingId($service->pricing_id)) && ($client = $this->Clients->get($service->client_id))) {
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
				'service' => $service,
				'client' => $client,
				'errors' => $errors
			);

			// Send the notification email
			$action = "service_suspension_error";
			switch ($type) {
				case "create":
					$action = "service_creation_error";
					break;
				case "cancel":
					$action = "service_cancel_error";
					break;
				case "unsuspend":
					$action = "service_unsuspension_error";
					break;
				case "suspend":
				default:
					break;
			}
			$this->Staff->sendNotificationEmail($action, $package->company_id, $tags);
		}
	}

	/**
	 * Determines whether a cron task is enabled and if it is time for the task to run or not
	 *
	 * @param stdClass $cron_task An stdClass object representing the cron task
	 * @param boolean $system True if the task is a system-level task, false if it is company-level (optional, default false)
	 * @return boolean True if this cron task can be run, false otherwise
	 */
	private function isTimeToRun($cron_task, $system=false) {
		if ($cron_task && $cron_task->enabled == "1") {
			// Get the last time this task was run
			$last_run = $this->Logs->getCronLastRun($cron_task->key, $cron_task->plugin_dir, $system);

			// Check if the task is currently running
			$is_running = ($last_run && $last_run->start_date != null && $last_run->end_date == null);

			// If the current task is running, check if its safe to start a new process
			if ($is_running) {
				if (strtotime($last_run->start_date) < strtotime(date("c") . "-6 hours"))
					return true;
				return false;
			}

			// Handle time
			if ($cron_task->type == "time") {

				// The current date rounded down to the nearest 5 minute interval in local time
				$rounded_time = $this->Date->format("H:i:s", date("H:i:s", floor($this->Date->toTime(date("c"))/(60*5))*(60*5)));
				$cron_task_time = $this->Date->format("H:i:s", $cron_task->time);

				// Convert last run time to local timezone
				if ($last_run) {
					$last_run_date = (int)preg_replace("/[^0-9]/", "", $this->Date->format("Y-m-d", $last_run->end_date));
					$day_after_last_run_date = (int)preg_replace("/[^0-9]/", "", $this->Date->format("Y-m-d", strtotime($last_run->end_date . " +1 day")));
					$current_date = (int)preg_replace("/[^0-9]/", "", $this->Date->format("Y-m-d"));

					// If task has not already run today and the interval has lapsed, allow the task to run
					return (($current_date > $day_after_last_run_date) || ($current_date > $last_run_date && $rounded_time >= $cron_task_time));
				}

				// Task has never run, just ensure the interval has lapsed
				return ($rounded_time >= $cron_task_time);
			}
			// Handle interval
			elseif ($cron_task->type == "interval") {
				// If never run, allow
				if (!$last_run)
					return true;

				// The last run date rounded down to the nearest 5 minute interval
				$last_run_date = date("c", floor($this->Date->toTime($last_run->start_date)/(60*5))*(60*5));

				// Ensure enough time has lapsed since the last run
				return (strtotime(date("c")) >= (strtotime($last_run_date) + ($cron_task->interval*60)));
			}
		}
		return false;
	}

	/**
	 * Determines whether today is the day of the month given
	 *
	 * @param int $day The day of the month to check (i.e. in range [1,31])
	 * @return boolean True if today is the current day given, false otherwise
	 */
	private function isCurrentDay($day) {
		return ($day == $this->Date->cast("c", "j"));
	}

	/**
	 * Logs to the cron that a task has started
	 *
	 * @param int $run_id The run ID of this event
	 * @param string $event The event to log
	 * @param string $cron_log_group The cron log group that this event is apart of
	 * @param string $start_date The start date of the task in Y-m-d H:i:s format
	 * @param string $output The output from running the task (optional)
	 * @return mixed An array of errors, or false if there are no errors
	 */
	private function logTaskStarted($run_id, $event, $cron_log_group, $start_date, $output=null) {
		$cron_event_log = array(
			'run_id' => $run_id,
			'event' => $event,
			'group' => $cron_log_group,
			'start_date' => $start_date,
			'output' => $output
		);

		// Log the cron event
		$this->Logs->addCron($cron_event_log);
		return $this->Logs->errors();
	}

	/**
	 * Logs to the cron that a current task has been completed
	 *
	 * @param int $run_id The run ID of this event
	 * @param string $event The event to log
	 * @param string $cron_log_group The cron log group that this event is apart of
	 * @param string $end_date The start date of the task in Y-m-d H:i:s format
	 * @param string $output The output from running the task
	 * @return mixed An array of errors, or false if there are no errors
	 */
	private function logTaskCompleted($run_id, $event, $cron_log_group, $end_date, $output) {
		$cron_event_log = array(
			'output' => $output,
			'end_date' => $end_date
		);

		// Update the cron event
		$this->Logs->updateCron($run_id, $cron_log_group, $event, $cron_event_log);
		return $this->Logs->errors();
	}

	/**
	 * Creates a cron log group
	 *
	 * @return string The cron log group
	 */
	private function createCronLogGroup() {
		return md5(microtime());
	}

	/**
	 * Retrieves a cron task
	 *
	 * @param string $cron_task_key The cron task key of the cron task to get
	 * @param string $plugin_dir The directory to the plugin this cron task is associated with
	 * @param boolean $system True if the task is a system-level task, false if it is company-level (optional, default false)
	 * @return mixed An stdClass representing the cron task, or false if none exist
	 */
	private function getCronTask($cron_task_key, $plugin_dir=null, $system=false) {
		$cron_task = new stdClass();

		// Use the task already set, if available
		if (isset($this->cron_tasks[$cron_task_key . $plugin_dir]))
			$cron_task = $this->cron_tasks[$cron_task_key . $plugin_dir];
		else
			$cron_task = $this->CronTasks->getTaskRunByKey($cron_task_key, $plugin_dir, $system);

		return $cron_task;
	}

	/**
	 * Sets output data
	 *
	 * @param string $new The new output data
	 * @param string $old The old output data, if any (optional, default "")
	 * @param boolean $echo True to output the new text
	 * @return string A concatenation of the old and new output
	 */
	private function setOutput($new, $old="", $echo=true) {
		if ($echo) {
			echo $new . ($this->is_cli ? "\n" : "<br />");
			@ob_flush();
			@flush();
			@ob_end_flush();
		}
		return $this->Html->concat(" ", $old, $new);
	}
}
