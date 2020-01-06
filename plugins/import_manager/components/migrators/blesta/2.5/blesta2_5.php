<?php
require_once dirname(__FILE__) . DS . ".." . DS . "blesta_migrator.php";

/**
 * Blesta 2.5 Migrator
 *
 * @package blesta
 * @subpackage blesta.plugins.import_manager.components.migrators.blesta
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Blesta2_5 extends BlestaMigrator {
	/**
	 * @var array An array of settings
	 */
	private $settings;

	/**
	 * Construct
	 *
	 * @param Record $local The database connection object to the local server
	 */
	public function __construct(Record $local) {
		parent::__construct($local);

		set_time_limit(60*60*15); // 15 minutes
		
		Language::loadLang(array("blesta2_5"), null, dirname(__FILE__) . DS . "language" . DS);
		
		Loader::loadModels($this, array("Companies"));
	}

	/**
	 * Processes settings (validating input). Sets any necessary input errors
	 *
	 * @param array $vars An array of key/value input pairs
	 */
	public function processSettings(array $vars = null) {
		
		$rules = array(
			'host' => array(
				'valid' => array(
					'rule' => "isEmpty",
					'negate' => "true",
					'message' => Language::_("Blesta2_5.!error.host.invalid", true)
				)
			),
			'database' => array(
				'valid' => array(
					'rule' => "isEmpty",
					'negate' => "true",
					'message' => Language::_("Blesta2_5.!error.database.invalid", true)
				)
			),
			'user' => array(
				'valid' => array(
					'rule' => "isEmpty",
					'negate' => "true",
					'message' => Language::_("Blesta2_5.!error.user.invalid", true)
				)
			),
			'pass' => array(
				'valid' => array(
					'rule' => "isEmpty",
					'negate' => "true",
					'message' => Language::_("Blesta2_5.!error.pass.invalid", true)
				)
			),
			'key' => array(
				'valid' => array(
					'rule' => array("betweenLength", 16, 32),
					'message' => Language::_("Blesta2_5.!error.key.invalid", true)
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if (!$this->Input->validates($vars))
			return;
		
		$this->settings = $vars;
		
		$default = array(
			'driver' => "mysql",
			'host' => null,
			'database' => null,
			'user' => null,
			'pass' => null,
			'persistent' => false,
			//'charset_query' => "SET NAMES 'utf8'",
			'options' => array()
		);
		$db_info = array_merge($default, $vars);

		try {
			$this->remote = new Record($db_info);
		}
		catch (Exception $e) {
			$this->Input->setErrors(array(array($e->getMessage())));
			return;
		}
	}
	
	/**
	 * Processes configuration (validating input). Sets any necessary input errors
	 *
	 * @param array $vars An array of key/value input pairs
	 */	
	public function processConfiguration(array $vars = null) {
		// Set mapping for packages (remote ID => local ID)
		if (isset($vars['create_packages']) && $vars['create_packages'] == "false") {
			$this->mappings['packages'] = array();
			foreach ($vars['remote_packages'] as $i => $package_id)
				$this->mappings['packages'][$package_id] = $vars['local_packages'][$i] == "" ? null : $vars['local_packages'][$i];
		}
	}

	/**
	 * Returns a view to handle settings
	 *
	 * @param array $vars An array of input key/value pairs
	 * @return string The HTML used to request input settings
	 */
	public function getSettings(array $vars) {
		$this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));
		$this->view->set("vars", (object)$vars);
		
		Loader::loadHelpers($this, array("Html", "Form"));
		
		return $this->view->fetch();
	}
	
	/**
	 * Returns a view to configuration run after settings but before import
	 *
	 * @param array $vars An array of input key/value pairs
	 * @return string The HTML used to request input settings, return null to bypass
	 */
	public function getConfiguration(array $vars) {
		$this->view = $this->makeView("configuration", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));
		$this->view->set("vars", (object)$vars);
		
		Loader::loadHelpers($this, array("Html", "Form"));
		Loader::loadModels($this, array("Packages"));
		
		if ($this->remote) {
			$this->view->set("remote_packages", $this->remote->select(array('packages.p_id' => "id", 'packages.p_name' => "name"))->from("packages")->order(array('name' => "ASC"))->fetchAll());
			$this->view->set("local_packages", $this->Packages->getAll(Configure::get("Blesta.company_id"), array('name' => "ASC"), null, "standard"));
		}
		
		return $this->view->fetch();
	}
	
	/**
	 * Runs the import, sets any Input errors encountered
	 */
	public function import() {
		$actions = array(
			"importStaff", // works
			"importClients", // works
			"importContacts", // works
			"importTaxes", // works
			"importCurrencies", // works
			"importInvoices", // works
			"importTransactions", // works
			"importPackages", // works
			"importCoupons", // works
			"importServices", // works
			"importSupportDepartments", // works
			"importSupportTickets", // works
			"importMisc" // works
		);
		
		$this->default_currency = "USD";
		$temp = $this->remote->select()->from("settings")->where("settings.s_name", "=", "currency")->fetch();
		if ($temp)
			$this->default_currency = $temp->s_value;
			
		$this->default_timezone = "UTC";
		$temp = $this->remote->select()->from("settings")->where("settings.s_name", "=", "timezone")->fetch();
		if ($temp) {
			$this->default_timezone = $temp->s_value;
			Configure::set("Blesta.company_timezone", $this->default_timezone);
		}

		foreach ($actions as $action) {
			
			// Only import packages if no mappings exist
			if ($action == "importPackages" && isset($this->mappings['packages']))
				continue;
			
			try {
				//echo $action . "\n";
				$this->{$action}();
			}
			catch (Exception $e) {
				//echo $e->getMessage();
				//echo $e->getTraceAsString();
				//continue;
				
				$this->Input->setErrors(array(array($e->getMessage())));
			}
			
			//if (($errors = $this->errors()))
			//	return;
		}
	}
	
	/**
	 * Import staff
	 */
	private function importStaff() {
		Loader::loadModels($this, array("StaffGroups"));
		Loader::loadModels($this, array("Users"));

		// Create "Support" staff group (no permissions)
		$staff_group = array(
			'company_id' => Configure::get("Blesta.company_id"),
			'name' => "Support",
			'permission_group' => array(),
			'permission' => array()
		);
		$this->StaffGroups->add($staff_group);
		
		$staff_groups = $this->StaffGroups->getAll(Configure::get("Blesta.company_id"));
		
		$groups = array();
		foreach ($staff_groups as $group) {
			if ($group->name == "Administrators") {
				$groups[0] = $group->id;
				$groups[1] = $group->id;
			}
			elseif ($group->name == "Billing") {
				$groups[2] = $group->id;
			}
			elseif ($group->name == "Support") {
				$groups[3] = $group->id;
			}
		}
		
		// admins
		$admins = $this->remote->select()->from("admins")->getStatement();
		
		foreach ($admins as $admin) {
			$this->Users->begin();

			try {
				$vars = array(
					'username' => $admin->a_email,
					'password' => $admin->a_pass,
					'two_factor_mode' => $admin->a_otp_mode,
					'two_factor_key' => $admin->a_otp_pin,
					'two_factor_pin' => $admin->a_otp_key,
					'date_added' => $this->Companies->dateToUtc(date("c"))
				);
				
				$this->local->insert("users", $vars);
				$user_id = $this->local->lastInsertId();
				
				$vars = array(
					'user_id' => $user_id,
					'first_name' => $admin->a_fname,
					'last_name' => $admin->a_lname,
					'email' => $admin->a_email,
					'email_mobile' => $admin->a_email2,
					'status' => $admin->a_active == "1" ? "active" : "inactive",
					'groups' => isset($groups[$admin->a_xslvl]) ? array($groups[$admin->a_xslvl]) : null
				);
				
				$staff_id = $this->addStaff($vars, $admin->a_id);
				
				if ($staff_id)
					$this->Users->commit();
				else
					$this->Users->rollback();
			}
			catch (Exception $e) {
				$this->local->reset();
				$this->Users->rollback();
			}
		}
	}
	
	/**
	 * Import clients
	 */	
	private function importClients() {
		if (!isset($this->Clients))
			Loader::loadModels($this, array("Clients", "ClientGroups"));
			
		$client_groups = $this->ClientGroups->getAll(Configure::get("Blesta.company_id"));
		$client_group_id = $client_groups[0]->id;

		$clients = $this->remote->select(array("users.*", "countries.c_2char"))->from("users")->
			leftJoin("countries", "countries.c_3char", "=", "users.u_country", false)->
			getStatement();
		
		$client_status = array("inactive", "active", "fraud");
		
		foreach ($clients as $client) {
			// Create the user
			$vars = array(
				'username' => $client->u_email,
				'password' => $client->u_pass,
				'date_added' => $this->Companies->dateToUtc($client->u_signdate)
			);
			try {
				$username_type = "email";
				// Attempt with email as username
				$this->local->insert("users", $vars);
			}
			catch (Exception $e) {
				$username_type = "username";
				// If unable to add with email as username, use client ID as username
				$vars['username'] = $client->u_id;
				$this->local->reset();
				$this->local->insert("users", $vars);
			}
			
			$user_id = $this->local->lastInsertId();
			$this->mappings['users'][$client->u_id] = $user_id;
			
			// Create the client
			$vars = array(
				'id_format' => "{num}",
				'id_value' => $client->u_id,
				'user_id' => $user_id,
				'client_group_id' => $client_group_id,
				'status' => $client_status[$client->u_active]
			);
			$this->local->insert("clients", $vars);
			
			$client_id = $this->local->lastInsertId();
			$this->mappings['clients'][$client->u_id] = $client_id;
			
			// Create the primary contact
			$vars = array(
				'client_id' => $client_id,
				'contact_type' => "primary",
				'first_name' => $client->u_fname,
				'last_name' => $client->u_lname,
				'company' => $client->u_cname != "" ? $client->u_cname : null,
				'email' => $client->u_email,
				'address1' => $client->u_address,
				'address2' => $client->u_address2 != "" ? $client->u_address2 : null,
				'city' => $client->u_city,
				'state' => $client->u_state != "" ? $client->u_state : null,
				'zip' => $client->u_zip != "" ? $client->u_zip : null,
				'country' => $client->c_2char != "" ? $client->c_2char : null,
				'date_added' => $this->Companies->dateToUtc($client->u_signdate)
			);
			$this->local->insert("contacts", $vars);
			$contact_id = $this->local->lastInsertId();
			$this->mappings['primary_contacts'][$client->u_id] = $contact_id;

			// Add client settings
			$settings = array(
				'autodebit' => $client->u_autod == 1 ? "true" : "false",
				'autosuspend' => $client->u_suspendable == 1 ? "true" : "false",
				'default_currency' => trim($client->u_currency) != "" ? $client->u_currency : $this->default_currency,
				'inv_address_to' => $contact_id,
				'inv_method' => $client->u_imethod == "paper" ? "paper" : "email",
				'language' => str_replace("-", "_", $client->u_lang),
				'tax_exempt' => $client->u_taxexempt == 1 ? "true" : "false",
				'tax_id' => $client->u_taxid != "" ? $client->u_taxid : null,
				'username_type' => $username_type
			);
			$this->Clients->setSettings($client_id, $settings);
			
			// Add contact numbers
			$numbers = array('phone' => $client->u_phone, 'fax' => $client->u_fax);
			foreach ($numbers as $type => $number) {
				if ($number == "")
					continue;
				$vars = array(
					'contact_id' => $contact_id,
					'number' => $number,
					'type' => $type,
					'location' => "home"
				);
				$this->local->insert("contact_numbers", $vars);
			}
			
			// Add client notes
			if ($client->u_notes != "") {
				$title = wordwrap($client->u_notes, 32, "\n", true);
				if (strpos($title, "\n") > 0)
					$title = substr($title, 0, strpos($title, "\n"));
				
				$vars = array(
					'client_id' => $client_id,
					'staff_id' => 1, // always staff ID 1
					'title' => $title,
					'description' => $client->u_notes,
					'stickied' => 1,
					'date_added' => $this->Companies->dateToUtc(date("c")),
					'date_updated' => $this->Companies->dateToUtc(date("c"))
				);
				$this->local->insert("client_notes", $vars);
			}
			
			// Add client login
			if ($client->u_lastseen != "0000-00-00 00:00:00" && $client->u_lastip != "") {
				$vars = array(
					'user_id' => $this->mappings['users'][$client->u_id],
					'ip_address' => $client->u_lastip,
					'company_id' => Configure::get("Blesta.company_id"),
					'date_added' => $this->Companies->dateToUtc($client->u_lastseen),
					'date_updated' => $this->Companies->dateToUtc($client->u_lastseen),
					'result' => "success"
				);
				
				$this->local->insert("log_users", $vars);
			}
		}
		
		// Custom client fields
		$custom_fields = $this->remote->select()->from("custom_fields")->getStatement();
		
		foreach ($custom_fields as $field) {
			$vars = array(
				'client_group_id' => $client_group_id,
				'name' => $field->c_name,
				'is_lang' => 0,
				'type' => $field->c_type,
				'values' => $field->c_values,
				'regex' => $field->c_regex == "" ? null : "/" . $field->c_regex . "/",
				'show_client' => $field->c_showclient,
				'encrypted' => 0
			);
			
			$field_id = $this->Clients->addCustomField($vars);
			
			$this->mappings['custom_fields'][$field->c_id] = $field_id;
		}
		
		// Custom client values
		$custom_values = $this->remote->select()->from("custom_values")->getStatement();
		
		foreach ($custom_values as $value) {
			if (!isset($this->mappings['clients'][$value->c_uid]))
				continue;
			$this->Clients->setCustomField($this->mappings['custom_fields'][$value->c_cid], $this->mappings['clients'][$value->c_uid], $value->c_value);
		}		
	}
	
	/**
	 * Import contacts
	 */
	private function importContacts() {
		
		if (!isset($this->Contacts))
			Loader::loadModels($this, array("Contacts"));
		if (!isset($this->Accounts))
			Loader::loadModels($this, array("Accounts"));
			
		
		// Initialize crypto (AES in ECB)
		Loader::loadComponents($this, array("Security"));
		$aes = $this->Security->create("Crypt", "AES", array(1)); // 1 = CRYPT_AES_MODE_ECB
		$aes->setKey($this->settings['key']);
		$aes->disablePadding();
		
		$contacts = $this->remote->select(array("contacts.*", "users.u_fname", "users.u_lname", "users.u_cid", "countries.c_2char"))->from("contacts")->
			innerJoin("users", "users.u_id", "=", "contacts.c_uid", false)->
			leftJoin("countries", "countries.c_3char", "=", "contacts.c_country", false)->
			getStatement();
		
		// Add the 'technical' contact type
		$vars = array(
			'name' => "Technical",
			'is_lang' => 0,
			'company_id' => Configure::get("Blesta.company_id")
		);
		$technical_type_id = $this->Contacts->addType($vars);
		
		foreach ($contacts as $contact) {
			if (!isset($this->mappings['clients'][$contact->c_uid]))
				continue;
			
			// If type is technical OR first/last name differ from client ADD AS CONTACT
			if ($contact->c_type == "technical" || ($contact->c_fname . " " . $contact->c_lname != $contact->u_fname . " " . $contact->u_lname)) {
				
				$numbers = array();
				foreach (array('phone' => $contact->c_phone, 'fax' => $contact->c_fax) as $type => $number) {
					if ($number != "")
						$numbers[] = array('number' => $number, 'type' => $type, 'location' => "home");
				}
				
				$vars = array(
					'client_id' => $this->mappings['clients'][$contact->c_uid],
					'contact_type' => $contact->c_type == "billing" ? "billing" : "other",
					'contact_type_id' => $contact->c_type == "billing" ? null : $technical_type_id,
					'first_name' => $contact->c_fname,
					'last_name' => $contact->c_lname,
					'company' => $contact->c_cname != "" ? $contact->c_cname : null,
					'email' => $contact->c_email,
					'address1' => $contact->c_address != "" ? $contact->c_address : null,
					'address2' => $contact->c_address2 != "" ? $contact->c_address2 : null,
					'city' => $contact->c_city != "" ? $contact->c_city : null,
					'state' => $contact->c_state != "" ? $contact->c_state : null,
					'zip' => $contact->c_zip != "" ? $contact->c_zip : null,
					'country' => $contact->c_2char != "" ? $contact->c_2char : null,
					'numbers' => $numbers
				);
				
				$this->addContact($vars, $contact->c_id);
			}
			
			// Add the payment account
			if ($contact->c_ccno != "" && isset($this->mappings['primary_contacts'][$contact->c_uid])) {
				$vars = array(
					'contact_id' => $this->mappings['primary_contacts'][$contact->c_uid],
					'first_name' => $contact->c_fname,
					'last_name' => $contact->c_lname,
					'address1' => $contact->c_address != "" ? $contact->c_address : null,
					'address2' => $contact->c_address2 != "" ? $contact->c_address2 : null,
					'city' => $contact->c_city != "" ? $contact->c_city : null,
					'state' => $contact->c_state != "" ? $contact->c_state : null,
					'zip' => $contact->c_zip != "" ? $contact->c_zip : null,
					'country' => $contact->c_2char != "" ? $contact->c_2char : null,
					'number' => $aes->decrypt(base64_decode($contact->c_ccno)),
					'expiration' => "20" . substr($contact->c_ccexp, 2, 2) . substr($contact->c_ccexp, 0, 2)
				);
				
				$account_id = $this->Accounts->addCc($vars);
				
				// Set account for autodebit
				if ($account_id && $contact->u_cid == $contact->c_id) {
					$vars = array(
						'client_id' => $this->mappings['clients'][$contact->c_uid],
						'account_id' => $account_id,
						'type' => "cc"
					);
					$this->local->insert("client_account", $vars);
				}
			}
		}
	}
	
	/**
	 * Import taxes
	 */
	private function importTaxes() {
		$fields = array("taxrules.*", "countries.c_2char");
		$taxrules = $this->remote->select($fields)->from("taxrules")->
			leftJoin("countries", "countries.c_3char", "=", "taxrules.t_country", false)->getStatement();
		
		foreach ($taxrules as $tax) {
			$vars = array(
				'company_id' => Configure::get("Blesta.company_id"),
				'level' => $tax->t_level,
				'name' => $tax->t_name,
				'amount' => $tax->t_amount,
				'type' => $tax->t_type == "sales" ? "inclusive" : "exclusive",
				'country' => $tax->c_2char,
				'state' => $tax->t_state == "" ? null : $tax->t_state,
				'status' => $tax->t_disabled == 1 ? "inactive" : "active"
			);
			$this->addTax($vars, $tax->t_id);
		}
	}
	
	/**
	 * Import currencies
	 */
	private function importCurrencies() {
		$currencies = $this->remote->select()->from("currencies")->getStatement();
		
		foreach ($currencies as $currency) {
			$vars = array(
				'code' => $currency->c_code,
				'company_id' => Configure::get("Blesta.company_id"),
				'format' => $this->getCurrencyFormat($currency->c_format),
				'prefix' => $currency->c_prefix,
				'suffix' => $currency->c_suffix,
				'exchange_rate' => $currency->c_xrate,
				'exchange_updated' => $currency->c_xupdated == "0000-00-00 00:00:00" ? null : $this->Companies->dateToUtc($currency->c_xupdated, "c"),
			);
			
			$currency = $this->local->select()->from("currencies")->
				where("code", "=", $vars['code'])->
				where("company_id", "=", $vars['company_id'])->fetch();
			if ($currency) 
				return;
			$this->addCurrency($vars, $currency->c_code);
		}
	}

	/**
	 * Import invoices
	 */
	private function importInvoices() {
		Loader::loadModels($this, array("Invoices"));
		
		$num_results = $this->remote->select()->from("invoices")->
			leftJoin("linvoices", "linvoices.l_iid", "=", "invoices.i_id", false)->
			order(array("invoices.i_id" => "ASC"))->numResults();
			
		$invoices = $this->remote->select()->from("invoices")->
			leftJoin("linvoices", "linvoices.l_iid", "=", "invoices.i_id", false)->
			order(array("invoices.i_id" => "ASC"))->getStatement();
		
		$invoice_id = null;
		$local_invoice_id = null;
		$order = 0;
		foreach ($invoices as $invoice) {
			
			if (!isset($this->mappings['clients'][$invoice->i_uid]))
				continue;
			
			// Add the invoice
			if ($invoice_id != $invoice->i_id) {
				
				// Set totals
				if ($local_invoice_id > 0) {
					$subtotal = $this->Invoices->getSubtotal($local_invoice_id);
					$total = $this->Invoices->getTotal($local_invoice_id);
					$paid = $this->Invoices->getPaid($local_invoice_id);
					
					// Update totals
					$this->local->where("id", "=", $local_invoice_id)->
						update("invoices", array('subtotal' => $subtotal, 'total' => $total, 'paid' => $paid));
				}
				
				$vars = array(
					'id_format' => "{num}",
					'id_value' => $invoice->i_id,
					'client_id' => $this->mappings['clients'][$invoice->i_uid],
					'date_billed' => $this->Companies->dateToUtc($invoice->i_dateb),
					'date_due' => $this->Companies->dateToUtc($invoice->i_dated),
					'date_closed' => $invoice->i_dater == "0000-00-00" ? null : $this->Companies->dateToUtc($invoice->i_dater),
					'date_autodebit' => null,
					'status' => 'active',
					'previous_due' => $invoice->i_previous,
					'currency' => $invoice->i_currency,
					'note_public' => $invoice->i_public_notes,
					'note_private' => $invoice->i_notes,
				);

				// Manually add the invoice so we can set the correct tax IDs and invoice ID
				$this->local->insert("invoices", $vars);
				$local_invoice_id = $this->local->lastInsertId();
				
				$this->mappings['invoices'][$invoice->i_id] = $local_invoice_id;
				$order = 0;
				$invoice_id = $invoice->i_id;
				
				// Set delivery
				$vars = array(
					'invoice_id' => $local_invoice_id,
					'method' => $invoice->i_type,
					'date_sent' => ($invoice->i_status == "sent" ? $this->Companies->dateToUtc($invoice->i_dateb) : null)
				);
				$this->local->insert("invoice_delivery", $vars);
			}
			
			// Add line item
			$vars = array(
				'invoice_id' => $local_invoice_id,
				'service_id' => $invoice->l_sid > 0 && isset($this->mappings['services'][$invoice->l_sid]) ? $this->mappings['services'][$invoice->l_sid] : null,
				'description' => ($invoice->l_name == "" ? " " : $invoice->l_name),
				'qty' => 1,
				'amount' => ($invoice->l_price == "" ? 0.00 : $invoice->l_price),
				'order' => $order++
			);
			$this->local->insert("invoice_lines", $vars);
			$line_id = $this->local->lastInsertId();
			
			$taxes = array();
			if ($invoice->l_tid > 0 && isset($this->mappings['taxes'][$invoice->l_tid]))
				$taxes[] = array('tax_id' => $this->mappings['taxes'][$invoice->l_tid], 'cascade' => $invoice->l_cascadetax);
			if ($invoice->l_tid2 > 0 && isset($this->mappings['taxes'][$invoice->l_tid2]))
				$taxes[] = array('tax_id' => $this->mappings['taxes'][$invoice->l_tid2], 'cascade' => $invoice->l_cascadetax);
			
			foreach ($taxes as $tax) {
				$vars = $tax;
				$vars['line_id'] = $line_id;
				$this->local->insert("invoice_line_taxes", $vars);
			}
		}
		
		if ($local_invoice_id > 0) {
			$subtotal = $this->Invoices->getSubtotal($local_invoice_id);
			$total = $this->Invoices->getTotal($local_invoice_id);
			$paid = $this->Invoices->getPaid($local_invoice_id);
			
			// Update totals
			$this->local->where("id", "=", $local_invoice_id)->
				update("invoices", array('subtotal' => $subtotal, 'total' => $total, 'paid' => $paid));
		}
	}
	
	/**
	 * Import transactions
	 */
	private function importTransactions() {
		/*
		if (!isset($this->Transactions))
			Loader::loadModels($this, array("Transactions"));
		*/
		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));
		
		$transactions = $this->remote->select()->from("received")->getStatement();
		
		foreach ($transactions as $transaction) {
 
			if (!isset($this->mappings['clients'][$transaction->r_uid]))
				continue;
			
			$vars = array(
				'client_id' => $this->mappings['clients'][$transaction->r_uid],
				'amount' => $transaction->r_amount,
				'currency' => $transaction->r_currency,
				'type' => $this->getTransactionType($transaction->r_type),
				'transaction_type_id' => $this->getTransactionTypeId($transaction->r_type),
				'transaction_id' => $transaction->r_transid,
				'status' => $this->getTransactionStatus($transaction->r_status),
				'date_added' => $this->Companies->dateToUtc($transaction->r_date, "c")
			);
	 
			$this->addTransaction($vars, $transaction->r_id);
		}		
		
		$applied = $this->remote->select()->from("applied")->getStatement();
		foreach ($applied as $apply) {
			if (!isset($this->mappings['transactions'][$apply->a_rid]))
				continue;
			if (!isset($this->mappings['invoices'][$apply->a_iid]))
				continue;
			
			$transaction_id = $this->mappings['transactions'][$apply->a_rid];
			
			/*
			$vars = array(
				'amounts' => array(
					array(
						'invoice_id' => $this->mappings['invoices'][$apply->a_iid],
						'amount' => $apply->a_amount
					)
				),
				'date' => $this->Companies->dateToUtc($apply->a_datetime, "c")
			);
			$this->Transactions->apply($transaction_id, $vars);
			*/
			
			$vars = array(
				'transaction_id' => $transaction_id,
				'invoice_id' => $this->mappings['invoices'][$apply->a_iid],
				'amount' => $apply->a_amount,
				'date' => $this->Companies->dateToUtc($apply->a_datetime)
			);
			$this->local->duplicate("amount", "=", "amount + '" . ((float)$apply->a_amount) . "'", false)->insert("transaction_applied", $vars);
			
			// Update paid total
			$paid = $this->Invoices->getPaid($this->mappings['invoices'][$apply->a_iid]);
			$this->local->where("id", "=", $this->mappings['invoices'][$apply->a_iid])->
				update("invoices", array('paid' => $paid));
		}
	}
	
	/**
	 * Import packages
	 */
	private function importPackages() {
		Loader::loadModels($this, array("ModuleManager", "PackageGroups"));
		
		Configure::load("none", dirname(__FILE__) . DS . "config" . DS);
		
		// Add imported package group
		$vars = array(
			'company_id' => Configure::get("Blesta.company_id"),
			'name' => "Imported",
			'type' => "standard"
		);
		$package_group_id = $this->PackageGroups->add($vars);
		
		
		// Get all packages
		$packages = $this->remote->select()->from("packages")->fetchAll();
		
		// Import packages, modules, module rows
		foreach ($packages as $package) {
			$package = $this->parseRemotePackages($package);
			
			// Use module map if one is defined, fallback to "none" mapping
			$module_type = $package->module;
			Configure::load($package->module, dirname(__FILE__) . DS . "config" . DS);
			
			if (!is_array(Configure::get($module_type . ".map")))
				$module_type = "none";
				
			$mapping = Configure::get($module_type . ".map");
			
			// Import modules, module rows
			if (!isset($this->mappings['modules'][$package->module])) {
				
				// Install the module if not already installed
				if (!$this->ModuleManager->isInstalled($mapping['module'], Configure::get("Blesta.company_id"))) {
					$vars = array(
						'company_id' => Configure::get("Blesta.company_id"),
						'class' => $mapping['module']
					);
					try {
						$module_id = $this->ModuleManager->add($vars);
					}
					catch (Exception $e) {
						continue;
					}
					$this->mappings['modules'][$package->module] = $module_id;
				}
				// Find the module if already installed
				else {
					$module = $this->local->select()->from("modules")->
						where("class", "=", $mapping['module'])->
						where("company_id", "=", Configure::get("Blesta.company_id"))->fetch();
					$module_id = $module->id;
					$this->mappings['modules'][$package->module] = $module_id;
				}
				
				try {
					$empty = !(boolean)$this->remote->select()->from("m_" . $package->module)->fetch();

					if ($empty) {
						$this->local->insert("module_rows", array('module_id' => $module_id));
						$module_row_id = $this->local->lastInsertId();
						$this->mappings['module_rows'][$package->module][0] = $module_row_id;
						
						// Module row meta
						if (isset($mapping['module_row_meta'])) {
							foreach ($mapping['module_row_meta'] as $meta_row) {
								$vars = (array)$meta_row;
								$vars['value'] = null;
								$vars['module_row_id'] = $module_row_id;
								if (is_object($meta_row->value)) {
									if (isset($meta_row->value->package))
										$vars['value'] = $package->{$meta_row->value->package};
								}
								else
									$vars['value'] = $meta_row->value;
								
								if (isset($meta_row->callback))
									$vars['value'] = call_user_func_array($meta_row->callback, array($vars['value']));
								
								if ($vars['serialized'] == 1)
									$vars['value'] = serialize($vars['value']);
								if ($vars['encrypted'] == 1)
									$vars['value'] = $this->ModuleManager->systemEncrypt($vars['value']);
								
								$this->local->insert("module_row_meta", $vars, array("module_row_id", "key", "value", "serialized", "encrypted"));
							}
						}
					}
					else {
						$module_rows = $this->remote->select()->from("m_" . $package->module)->getStatement();
						foreach ($module_rows as $module_row) {
							// Module row
							$this->local->insert("module_rows", array('module_id' => $module_id));
							$module_row_id = $this->local->lastInsertId();
							$this->mappings['module_rows'][$package->module][$module_row->m_id] = $module_row_id;
							
							// Module row meta
							if (isset($mapping['module_row_meta'])) {
								foreach ($mapping['module_row_meta'] as $meta_row) {
									$vars = (array)$meta_row;
									$vars['value'] = null;
									$vars['module_row_id'] = $module_row_id;
									if (is_object($meta_row->value)) {
										if (isset($meta_row->value->module))
											$vars['value'] = $module_row->{"m_" . $meta_row->value->module};
										elseif (isset($meta_row->value->package))
											$vars['value'] = $package->{$meta_row->value->package};
									}
									else
										$vars['value'] = $meta_row->value;
									
									if (isset($meta_row->callback))
										$vars['value'] = call_user_func_array($meta_row->callback, array($vars['value']));
									
									if ($vars['serialized'] == 1)
										$vars['value'] = serialize($vars['value']);
									if ($vars['encrypted'] == 1)
										$vars['value'] = $this->ModuleManager->systemEncrypt($vars['value']);
									
									$this->local->insert("module_row_meta", $vars, array("module_row_id", "key", "value", "serialized", "encrypted"));
								}
							}
						}
					}
				}
				catch (Exception $e) {
					// Module is no longer installed remotely...
					$this->local->reset();
				}
			}
			
			$vars = array(
				'id_format' => "{num}",
				'id_value' => $package->id,
				'module_id' => $this->mappings['modules'][$package->module],
				'name' => $package->name,
				'description' => $package->description,
				'description_html' => $package->description_html,
				'qty' => $package->qty,
				'module_row' => !isset($this->mappings['module_rows'][$package->module][$package->module_row]) ? 0 : $this->mappings['module_rows'][$package->module][$package->module_row],
				'module_group' => null,
				'taxable' => $package->taxable,
				'status' => $package->status,
				'company_id' => Configure::get("Blesta.company_id")
			);
			
			// Add the package
			$this->local->insert("packages", $vars);
			$this->mappings['packages'][$package->id] = $this->local->lastInsertId();
			
			// Assign group
			$this->local->insert("package_group", array('package_id' => $this->mappings['packages'][$package->id], 'package_group_id' => $package_group_id));
			
			// Add package pricing
			foreach ($package->pricing as $price) {
				if (version_compare(BLESTA_VERSION, "3.1.0-b1", ">=")) {
					$vars = $price;
					$vars['company_id'] = Configure::get("Blesta.company_id");
					
					$this->local->insert("pricings", $vars);
					$pricing_id = $this->local->lastInsertId();
					
					$this->local->insert("package_pricing",
						array(
							'package_id' => $this->mappings['packages'][$package->id],
							'pricing_id' => $pricing_id
						)
					);
					$pricing_id = $this->local->lastInsertId();
				}
				else {
					$vars = $price;
					$vars['package_id'] = $this->mappings['packages'][$package->id];
					$this->local->insert("package_pricing", $vars);
					$pricing_id = $this->local->lastInsertId();
				}
			}
	
			// Add package emails
			foreach ($package->email_content as $email) {
				$vars = $email;
				
				if (isset($mapping['package_tags']) && is_array($mapping['package_tags'])) {
					$vars['html'] = str_replace(array_keys($mapping['package_tags']), array_values($mapping['package_tags']), $vars['html']);
					$vars['text'] = str_replace(array_keys($mapping['package_tags']), array_values($mapping['package_tags']), $vars['text']);
				}
				
				$vars['package_id'] = $this->mappings['packages'][$package->id];
				$this->local->insert("package_emails", $vars);
			}
			
			// Add package meta
			if (isset($mapping['package_meta'])) {
				foreach ($mapping['package_meta'] as $meta) {
					$vars = (array)$meta;
					$vars['package_id'] = $this->mappings['packages'][$package->id];
					$vars['value'] = null;
					
					if (is_object($meta->value)) {
						if (isset($meta->value->package)) {
							$vars['value'] = $package->{$meta->value->package};
						}
					}
					else
						$vars['value'] = $meta->value;

					if (isset($meta->callback))
						$vars['value'] = call_user_func_array($meta->callback, array($vars['value']));

					if ($vars['serialized'] == 1)
						$vars['value'] = serialize($vars['value']);
					if ($vars['encrypted'] == 1)
						$vars['value'] = $this->ModuleManager->systemEncrypt($vars['value']);
					
					$this->local->insert("package_meta", $vars, array("package_id", "key", "value", "serialized", "encrypted"));
				}
			}
		}

		// Import package control
		if (isset($this->mappings['clients'])) {
			$package_control = $this->remote->select()->from("package_control")->getStatement();
			foreach ($package_control as $control) {
				
				if (!isset($this->mappings['packages'][$control->p_pid]) || !isset($this->mappings['clients'][$control->p_uid]))
					continue;
				
				$vars = array(
					'client_id' => $this->mappings['clients'][$control->p_uid],
					'package_id' => $this->mappings['packages'][$control->p_pid]
				);
				$this->local->insert("client_packages", $vars);
			}
		}
	}
	
	/**
	 * Import coupons
	 */
	private function importCoupons() {
		Loader::loadModels($this, array("Currencies"));
		$curencies = $this->Currencies->getAll(Configure::get("Blesta.company_id"));
		
		// Get all packages each coupon applies to
		$packages = $this->remote->select()->from("coupons_for")->getStatement();
		$package_ids = array();
		
		foreach ($packages as $package) {
			if (!isset($this->mappings['packages'][$package->c_pid]))
				continue;
			$package_ids[$package->c_cid][] = $this->mappings['packages'][$package->c_pid];
		}
		
		// Add each coupon
		$coupons = $this->remote->select()->from("coupons")->getStatement();
		foreach ($coupons as $coupon) {
			
			// Each coupon must be added for each currency
			$amounts = array();
			foreach ($curencies as $currency) {
				$amounts[] = array(
					'currency' => $currency->code,
					'amount' => $coupon->c_discount,
					'type' => "percent"
				);
			}
			
			$vars = array(
				'code' => $coupon->c_code,
				'company_id' => Configure::get("Blesta.company_id"),
				'used_qty' => $coupon->c_used,
				'max_qty' => $coupon->c_max,
				'start_date' => $coupon->c_start == "0000-00-00 00:00:00" ? null : $this->Companies->dateToUtc($coupon->c_start, "c"),
				'end_date' => $coupon->c_end == "0000-00-00 00:00:00" ? null : $this->Companies->dateToUtc($coupon->c_end, "c"),
				'status' => $coupon->c_active == 1 ? "active" : "inactive",
				'type' => "exclusive",
				'packages' => isset($package_ids[$coupon->c_id]) ? $package_ids[$coupon->c_id] : null,
				'amounts' => $amounts
			);
			$this->addCoupon($vars, $coupon->c_id);
		}
	}
	
	/**
	 * Import services
	 */
	private function importServices() {
		
		#
		# TODO: Handle services_pending?
		#
		
		$inv_days = 0;
		$temp = $this->remote->select()->from("settings")->where("settings.s_name", "=", "invdatesec")->fetch();
		if ($temp) {
			$inv_days = (int)($temp->s_value/(60*60*24));
		}
		
		if (!isset($this->Services))
			Loader::loadModels($this, array("Services", "Packages"));
		
		// Fetch all packages
		$remote_packages = array();
		$all_packages = $this->remote->select(array('p_id' => "id", 'p_instantact' => "instantact", 'p_modrow' => "module_row"))->
			from("packages")->getStatement();
		foreach ($all_packages as $remote_package) {
			$temp = explode(",", $remote_package->instantact);
			$remote_package->instantact = null;
			$remote_package->module = trim($temp[1]);
			if (isset($temp[2]))
				$remote_package->instantact = trim($temp[2]);
			
			$remote_packages[$remote_package->id] = $remote_package;
		}
		
		Configure::load("none", dirname(__FILE__) . DS . "config" . DS);
		
		foreach ($this->mappings['packages'] as $remote_id => $local_id) {
			if (!$local_id || !isset($remote_packages[$remote_id]))
				continue;
				
			// Use module map if one is defined, fallback to "none" mapping
			$module_type = $remote_packages[$remote_id]->module;
			Configure::load($remote_packages[$remote_id]->module, dirname(__FILE__) . DS . "config" . DS);
			
			if (!is_array(Configure::get($module_type . ".map")))
				$module_type = "none";				
			
			$remote_services = $this->remote->select()->from("services")->
				innerJoin("packages", "services.s_pid", "=", "packages.p_id", false)->
				leftJoin("m_" . $remote_packages[$remote_id]->module, "m_" . $remote_packages[$remote_id]->module . ".m_id", "=", "services.s_mid", false)->
				where("packages.p_id", "=", $remote_id)->getStatement();

			$mapping = Configure::get($module_type . ".map");
			
			$local_package = $this->Packages->get($local_id);
			
			foreach ($remote_services as $remote_service) {

				if (!isset($this->mappings['clients'][$remote_service->s_uid]))
					continue;

				if ($local_package->module_row > 0)
					$module_row_id = $local_package->module_row;
				else
					$module_row_id = $this->getModuleRowId($remote_service, isset($mapping['module_row_key']) ? $mapping['module_row_key'] : null, $local_package->module_id, $remote_packages[$remote_id]->module);
				$pricing_id = null;
				$term = null;
				$period = null;
				
				foreach ($local_package->pricing as $price) {
					if ($remote_service->s_term > 0) {
						if ($price->period == "month" && $price->term == $remote_service->s_term) {
							$term = $price->term;
							$period = $price->period;
							$pricing_id = $price->id;
							break;
						}
					}
					elseif ($price->period == "onetime") {
						$pricing_id = $price->id;
						break;
					}
				}
				
				// Create the pricing since it doesn't already exist
				if ($pricing_id == null) {
					
					$temp = explode(",", $remote_service->p_prices);
					$terms = array();
					foreach ($temp as $parts) {
						$pieces = explode("-", $parts);
						if (count($pieces) == 2)
							$terms[$pieces[0]] = $pieces[1];
					}
					$temp = explode(",", $remote_service->p_setupfees);
					$setup = array();
					foreach ($temp as $parts) {
						$pieces = explode("-", $parts);
						if (count($pieces) == 2)
							$setup[$parts[0]] = $parts[1];
					}
					
					$term = $remote_service->s_term > 0 ? $remote_service->s_term : 0;
					$period = $remote_service->s_term > 0 ? "month" : "onetime";
					
					$price = array(
						'term' => $term,
						'period' => $period,
						'price' => isset($terms[$remote_service->s_term]) ? $terms[$remote_service->s_term] : "0.0000",
						'setup_fee' => isset($setup[$remote_service->s_term]) ? $setup[$remote_service->s_term] : "0.0000",
						'currency' => $this->default_currency
					);
					if (version_compare(BLESTA_VERSION, "3.1.0-b1", ">=")) {
						$vars = $price;
						$vars['company_id'] = Configure::get("Blesta.company_id");
						
						$this->local->insert("pricings", $vars);
						$pricing_id = $this->local->lastInsertId();
						
						$this->local->insert("package_pricing",
							array(
								'package_id' => $local_id,
								'pricing_id' => $pricing_id
							)
						);
						$pricing_id = $this->local->lastInsertId();
					}
					else {
						$vars = $price;
						$vars['package_id'] = $local_id;
						$this->local->insert("package_pricing", $vars);
						$pricing_id = $this->local->lastInsertId();
					}
				}
				
				$status = ($remote_service->s_dated != "0000-00-00" ? "canceled" : ($remote_service->s_datep != "0000-00-00" ? "suspended" : "active"));
				
				// Adjust renew date as versions < 3 do not bump renew dates when invoiced, but when the renew date lapses
				$renew_date = $this->Companies->dateToUtc($remote_service->s_dater . " 00:00:00");
				if ($status != "canceled" && $remote_service->s_dater != "0000-00-00" && $renew_date <= $this->Companies->dateToUtc(strtotime("+" . $inv_days . " days"))) {
					$renew_date = $this->Services->getNextRenewDate($renew_date, $term, $period, "Y-m-d H:i:s");
				}
				
				$vars = array(
					'parent_service_id' => null,
					'package_group_id' => null,
					'id_format' => "{num}",
					'id_value' => $remote_service->s_id,
					'pricing_id' => $pricing_id,
					'client_id' => $this->mappings['clients'][$remote_service->s_uid],
					'module_row_id' => $module_row_id,
					'coupon_id' => null,
					'qty' => 1,
					'status' => $status,
					'date_added' => $this->Companies->dateToUtc($remote_service->s_dates . " 00:00:00"),
					'date_renews' => $remote_service->s_dater == "0000-00-00" ? null : $renew_date,
					'date_last_renewed' => null,
					'date_suspended' => $remote_service->s_datep == "0000-00-00" ? null : $this->Companies->dateToUtc($remote_service->s_datep . " 00:00:00"),
					'date_canceled' => $remote_service->s_dated == "0000-00-00" ? null : $this->Companies->dateToUtc($remote_service->s_dated . " 00:00:00")
					
				);
				$this->local->insert("services", $vars);
				$service_id = $this->local->lastInsertId();
				$this->mappings['services'][$remote_service->s_id] = $service_id;

				$vars = array();
				foreach ($mapping['service_fields'] as $key => $field) {
					$value = $remote_service->{"s_" . $key};
					
					if (!is_object($field))
						continue;
					
					if (isset($field->callback))
						$value = call_user_func_array($field->callback, array($value));
					
					if ($field->serialized > 0)
						$value = serialize($value);
					if ($field->encrypted > 0)
						$value = $this->Services->systemEncrypt($value);
					
					$vars = array(
						'service_id' => $service_id,
						'key' => $field->key,
						'value' => $value,
						'serialized' => $field->serialized,
						'encrypted' => $field->encrypted
					);
					$this->local->insert("service_fields", $vars);
				}
			}
		}
	}
	
	/**
	 * Import support departments
	 */
	private function importSupportDepartments() {
		Loader::loadModels($this, array("PluginManager"));

		if (!$this->PluginManager->isInstalled("support_manager", Configure::get("Blesta.company_id")))
			$this->PluginManager->add(array('dir' => "support_manager", 'company_id' => Configure::get("Blesta.company_id")));
		
		Loader::loadModels($this, array("SupportManager.SupportManagerDepartments", "SupportManager.SupportManagerResponses", "SupportManager.SupportManagerStaff"));
		
		$priorities = array(
			'high',
			'medium',
			'low'
		);
		
		$departments = $this->remote->select()->from("departments")->getStatement();
		foreach ($departments as $department) {
			$vars = array(
				'company_id' => Configure::get("Blesta.company_id"),
				'name' => $department->d_name,
				'description' => $department->d_desc,
				'email' => $department->d_email,
				'method' => $department->d_method == "piping" ? "pipe" : $department->d_method,
				'default_priority' => $priorities[$department->d_priority],
				'host' => $department->d_host,
				'user' => $department->d_username,
				'pass' => $department->d_password,
				'port' => $department->d_port,
				'security' => 'none',
				'mark_messages' => "deleted",
				'clients_only' => $department->d_visible == 1 ? 1 : 0,
				'status' => $department->d_visible == 1 ? "visible" : "hidden",
			);
			
			$this->local->insert("support_departments", $vars);
			$this->mappings['support_departments'][$department->d_id] = $this->local->lastInsertId();
		}
		
		$admins = $this->remote->select()->from("admin_departments")->order(array('a_aid' => "ASC"))->getStatement();
		//$last_admin_id = null;

		foreach ($admins as $admin) {
			if (!isset($this->mappings['staff'][$admin->a_aid]))
				continue;
			
			// Add support_staff
			$vars = array(
				'department_id' => $this->mappings['support_departments'][$admin->a_did],
				'staff_id' => $this->mappings['staff'][$admin->a_aid]
			);
			$this->local->insert("support_staff_departments", $vars);
		}
		
		$admins = $this->remote->select()->from("admin_departments")->group(array('a_aid'))->getStatement();
		foreach ($admins as $admin) {
			if (!isset($this->mappings['staff'][$admin->a_aid]))
				continue;
			
			// Add schedules
			$days = array("sun", "mon", "tue", "wed", "thu", "fri", "sat");
			foreach ($days as $day) {
				$vars = array(
					'staff_id' => $this->mappings['staff'][$admin->a_aid],
					'company_id' => Configure::get("Blesta.company_id"),
					'day' => $day,
					'start_time' => "00:00:00",
					'end_time' => "00:00:00"
				);
				try {
					$this->local->insert("support_staff_schedules", $vars);
				}
				catch (Exception $e) {
					$this->local->reset();
				}
			}
			
			// Add notices
			$keys = array("mobile_ticket_emails", "ticket_emails");
			foreach ($keys as $key) {
				$vars = array(
					'key' => $key,
					'company_id' => Configure::get("Blesta.company_id"),
					'staff_id' => $this->mappings['staff'][$admin->a_aid],
					'value' => serialize(array('emergency' => "true", 'critical' => "true", 'high' => "true", 'medium' => "true", 'low' => "true"))
				);
				try {
					$this->local->insert("support_staff_settings", $vars);
				}
				catch (Exception $e) {
					$this->local->reset();
				}
			}
		}
		
		// Create default category
		$vars = array(
			'company_id' => Configure::get("Blesta.company_id"),
			'parent_id' => null,
			'name' => "Default"
		);
		$category = $this->SupportManagerResponses->addCategory($vars);
		
		$responses = $this->remote->select()->from("responses")->getStatement();
		foreach ($responses as $response) {
			$vars = array(
				'category_id' => $category->id,
				'name' => $response->r_name,
				'details' => $response->r_message
			);
			$this->SupportManagerResponses->add($vars);
		}
	}
	
	/**
	 * Import support tickets
	 */
	private function importSupportTickets() {
		// ltickets, tickets, tickets_attachments
		
		Loader::loadModels($this, array("SupportManager.SupportManagerTickets"));
		Loader::loadComponents($this, array("SettingsCollection"));
		
		$priorities = array(
			'high',
			'medium',
			'low'
		);
		
		// SELECT `temp`.*, `ltickets`.`l_aid`, `ltickets`.`l_updated` FROM (SELECT `tickets`.*, MIN(`ltickets`.`l_id`) AS `l_id`
		// FROM `tickets` INNER JOIN `ltickets` ON `ltickets`.`l_tid`=`tickets`.`t_id` GROUP BY `tickets`.`t_id`) AS temp
		// INNER JOIN `ltickets` ON `ltickets`.`l_id`=`temp`.`l_id`
		$subquery_sql = $this->remote->select(array("tickets.*", 'MIN(ltickets.l_id)' => "l_id"))->
			from("tickets")->innerJoin("ltickets", "ltickets.l_tid", "=", "tickets.t_id", false)->
			group(array("tickets.t_id"))->
			get();
		$values = $this->remote->values;
		$this->remote->reset();
		
		$tickets = $this->remote->select(array("temp.*", "ltickets.l_aid", "ltickets.l_updated"))->
			appendValues($values)->
			from(array($subquery_sql => 'temp'))->
			innerJoin("ltickets", "ltickets.l_id", "=", "temp.l_id", false)->
			getStatement();

		foreach ($tickets as $ticket) {
			$vars = array(
				'code' => $ticket->t_id,
				'department_id' => isset($this->mappings['support_departments'][$ticket->t_department]) ? $this->mappings['support_departments'][$ticket->t_department] : 0,//$this->mappings['support_departments'][$ticket->t_department],
				'staff_id' => $ticket->l_aid > 0 && isset($this->mappings['staff'][$ticket->l_aid]) ? $this->mappings['staff'][$ticket->l_aid] : null,
				'service_id' => null,
				'client_id' => $ticket->t_uid > 0 && isset($this->mappings['clients'][$ticket->t_uid]) ? $this->mappings['clients'][$ticket->t_uid] : null,
				'email' => $ticket->t_email != "" ? $ticket->t_email : null,
				'summary' => $ticket->t_area,
				'priority' => $priorities[$ticket->t_priority],
				'status' => $ticket->t_closed == "0000-00-00 00:00:00" ? "open" : "closed",
				'date_added' => $this->Companies->dateToUtc($ticket->l_updated),
				'date_closed' => $ticket->t_closed == "0000-00-00 00:00:00" ? null : $this->Companies->dateToUtc($ticket->t_closed),
			);

			$this->local->insert("support_tickets", $vars);
			$this->mappings['support_tickets'][$ticket->t_id] = $this->local->lastInsertId();
		}
		
		// Ticket replies/attachments
		$replies = $this->remote->select()->from("ltickets")->
			leftJoin("tickets_attachments", "tickets_attachments.t_lid", "=", "ltickets.l_id", false)->
			order(array('ltickets.l_id' => "ASC"))->getStatement();
		
		$reply_id = null;
		$local_reply_id = null;

		$temp = $this->SettingsCollection->fetchSetting(null, Configure::get("Blesta.company_id"), "uploads_dir");
		$upload_path = $temp['value'] . Configure::get("Blesta.company_id") . DS . "support_manager_files" . DS;
		
		foreach ($replies as $reply) {
			if (!isset($this->mappings['support_tickets'][$reply->l_tid]))
				continue;
			
			if ($reply->l_id != $reply_id) {
				// Add reply
				$vars = array(
					'ticket_id' => $this->mappings['support_tickets'][$reply->l_tid],
					'staff_id' => $reply->l_aid > 0 && isset($this->mappings['staff'][$reply->l_aid]) ? $this->mappings['staff'][$reply->l_aid] : null,
					'type' => "reply",
					'details' => $reply->l_body,
					'date_added' => $this->Companies->dateToUtc($reply->l_updated)
				);
				$this->local->insert("support_replies", $vars);
				
				$local_reply_id = $this->local->lastInsertId();
			}
			
			// Add attachemnt if one is set
			if ($local_reply_id && $reply->t_name != "") {
				// Record attachments
				$vars = array(
					'reply_id' => $local_reply_id,
					'name' => $reply->t_name,
					'file_name' => $upload_path . $reply->t_filename
				);
				$this->local->insert("support_attachments", $vars);
			}
			
			$reply_id = $reply->l_id;
		}
	}
	
	/**
	 * Import miscellaneous
	 */
	private function importMisc() {
		if (!isset($this->Settings))
			Loader::loadModels($this, array("Settings"));
			
		#
		# TODO: Set gateways
		#
		
		
		#
		# TODO: Set gateways_currencies
		#
		
		
		// Mail log
		$email = $this->remote->select()->from("maillog")->getStatement();
		foreach ($email as $message) {
			$vars = array(
				'company_id' => Configure::get("Blesta.company_id"),
				'to_client_id' => $message->m_uid > 0 && isset($this->mappings['clients'][$message->m_uid]) ? $this->mappings['clients'][$message->m_uid] : null,
				'from_staff_id' => $message->m_aid > 0 && isset($this->mappings['staff'][$message->m_aid]) ? $this->mappings['staff'][$message->m_aid] : null,
				'to_address' => $message->m_recipient,
				'from_address' => "uknown@localhost",
				'from_name' => "unknown@localhost",
				'cc_address' => null,
				'subject' => $message->m_subject,
				'body_text' => $message->m_body,
				'body_html' => $message->m_html,
				'sent' => 1,
				'error' => null,
				'date_sent' => $this->Companies->dateToUtc($message->m_date)
			);
			$this->local->insert("log_emails", $vars);
		}
	
		// Company
		$company = $this->remote->select()->from("settings_company")->fetch();
		$vars = array(
			'name' => $company->s_name,
			'address' => $company->s_address
		);
		$this->local->where("companies.id", "=", Configure::get("Blesta.company_id"))->update("companies", $vars);
		
		$this->Companies->setSetting(Configure::get("Blesta.company_id"), "inv_terms", $company->s_invterms);
		$this->Companies->setSetting(Configure::get("Blesta.company_id"), "inv_display_logo", $company->s_use == "logo" ? "true" : "false");
		
		// Settings
		$settings = $this->remote->select()->from("settings")->getStatement();
		foreach ($settings as $setting) {
			$type = "company";
			$key = null;
			$value = $setting->s_value;
			
			if ($setting->s_name == "remotekey") {
				$type = "system";
				$key = "cron_key";
			}
			elseif ($setting->s_name == "invdatesec") {
				$key = "inv_days_before_renewal";
				$value = (int)($value/(60*60*24));
			}
			elseif ($setting->s_name == "invoicemethod") {
				$key = "client_set_invoice";
				
				// Add paper as delivery option
				$delivery_methods = unserialize(base64_decode($this->Companies->getSetting(Configure::get("Blesta.company_id"), "delivery_methods")->value));
				if (!in_array("paper", $delivery_methods))
					$delivery_methods[] = "paper";
				$this->Companies->setSetting(Configure::get("Blesta.company_id"), "delivery_methods", base64_encode(serialize($delivery_methods)));
			}
			elseif ($setting->s_name == "ftpuser") {
				$type = "system";
				$key = "ftp_username";
				
				// Enable SFTP
				$this->Settings->setSetting("ftp_port", 22);
			}
			elseif ($setting->s_name == "ftppass") {
				$type = "system";
				$key = "ftp_password";
			}
			elseif ($setting->s_name == "ftphost") {
				$type = "system";
				$key = "ftp_host";
			}
			elseif ($setting->s_name == "ftpdest") {
				$type = "system";
				$key = "ftp_path";
			}
			elseif ($setting->s_name == "ftpfreq") {
				$type = "system";
				$key = "ftp_rate";
			}
			elseif ($setting->s_name == "invdatesec") {
				$key = "autodebit_days_before_due";
				$value = (int)($value/(60*60*24));
			}
			elseif ($setting->s_name == "latenotice1") {
				$key = "notice1";
				$value = (int)($value/(60*60*24));
			}
			elseif ($setting->s_name == "latenotice2") {
				$key = "notice2";
				$value = (int)($value/(60*60*24));
			}
			elseif ($setting->s_name == "latenotice3") {
				$key = "notice3";
				$value = (int)($value/(60*60*24));
			}
			elseif ($setting->s_name == "suspendservices") {
				$key = "suspend_services_days_after_due";
				$value = (int)($value/(60*60*24));
			}
			elseif ($setting->s_name == "autobackoff") {
				$key = "autodebit_backoff";
			}
			elseif ($setting->s_name == "currency") {
				$key = "default_currency";
			}
			elseif ($setting->s_name == "currencycode") {
				$key = "show_currency_code";
			}
			elseif ($setting->s_name == "language") {
				$key = "language";
				$value = str_replace(array("-", ".php"), array("_", ""), $value);
			}
			elseif ($setting->s_name == "comfydate") {
				$key = "date_format";
			}
			elseif ($setting->s_name == "comfytime") {
				$key = "datetime_format";
			}
			elseif ($setting->s_name == "createservices") {
				// Order plugin setting...
			}
			elseif ($setting->s_name == "antifraud") {
				// Order plugin setting...
			}
			elseif ($setting->s_name == "compoundtax") {
				// Not used
			}
			elseif ($setting->s_name == "apiallaccess") {
				// Not used
			}
			elseif ($setting->s_name == "apitrustedips") {
				// Not used
			}
			elseif ($setting->s_name == "maintenancemode") {
				$key = "maintenance_mode";
			}
			elseif ($setting->s_name == "maintenancereason") {
				$key = "maintenance_reason";
			}
			elseif ($setting->s_name == "smtpuser") {
				$key = "smtp_user";
			}
			elseif ($setting->s_name == "smtphost") {
				$key = "smtp_host";
			}
			elseif ($setting->s_name == "smtpport") {
				$key = "smtp_port";
			}
			elseif ($setting->s_name == "smtppass") {
				$key = "smtp_password";
			}
			elseif ($setting->s_name == "mailer") {
				$key = "mail_delivery";
			}
			elseif ($setting->s_name == "pipeform") {
				// Not used
			}
			elseif ($setting->s_name == "enabletax") {
				$key = "enable_tax";
			}
			elseif ($setting->s_name == "taxid") {
				$key = "tax_id";
			}
			elseif ($setting->s_name == "ticketnotify") {
				// Not used
			}
			elseif ($setting->s_name == "ticketdays") {
				// Not used
			}
			elseif ($setting->s_name == "ticketresponse") {
				// Not used
			}
			elseif ($setting->s_name == "allowclientlang") {
				$key = "client_set_lang";
			}
			elseif ($setting->s_name == "modulelogdays") {
				$type = "system";
				$key = "log_days";
			}
			elseif ($setting->s_name == "enablehtml") {
				$key = "html_email";
			}
			elseif ($setting->s_name == "defaultcountry") {
				$key = "country";
				$country = $this->local->select(array("alpha2"))->from("countries")->where("alpha3", "=", $value)->fetch();
				$value = $country->alpha2;
			}
			elseif ($setting->s_name == "multicurrency") {
				// Always enabled, set pricing type
				$key = "multi_currency_pricing";
				$value = "exchange_rate";
			}
			elseif ($setting->s_name == "autoexchange") {
				$key = "exchange_rates_auto_update";
				
				// Set exchange rate processor
				$this->Companies->setSetting(Configure::get("Blesta.company_id"), "exchange_rates_processor", "google_finance");
			}
			elseif ($setting->s_name == "exchangepadding") {
				$key = "exchange_rates_padding";
			}
			elseif ($setting->s_name == "timezone") {
				$key = "timezone";
			}
			elseif ($setting->s_name == "clientcurrency") {
				$key = "client_set_currency";
			}
			elseif ($setting->s_name == "ticketattachments") {
				// Not used
			}
			elseif ($setting->s_name == "ticketattachsize") {
				// Not used
			}
			elseif ($setting->s_name == "invoicesuspended") {
				$key = "inv_suspended_services";
			}
			elseif ($setting->s_name == "calendarday") {
				$key = "calendar_begins";
				$value = ($value == 7 ? "sunday" : "monday");
			}
			elseif ($setting->s_name == "cancelservices") {				
				$key = "clients_cancel_services";
			}
			elseif ($setting->s_name == "cascadetax") {				
				$key = "cascade_tax";
			}
			elseif ($setting->s_name == "pipeadminreplies") {
				// Not used
			}
			elseif ($setting->s_name == "taxsetupfee") {				
				$key = "setup_fee_tax";
			}			
			
			if (!$key)
				continue;
			
			if ($type == "company")
				$this->Companies->setSetting(Configure::get("Blesta.company_id"), $key, $value);
			else
				$this->Settings->setSetting($key, $value);
		}
	}
	
	/**
	 * Returns the proper currency format based on currency format ID
	 *
	 * @param int $format_id The 2.5 currency format ID
	 * @return string The currency format
	 */
	private function getCurrencyFormat($format_id) {
		switch ($format_id) {
			default:
			case 1:
				return "#,###.##";
			case 2:
				return "#.###,##";
			case 3:
				return "# ###.##";
			case 4:
				return "# ###,##";
			case 5:
				return "#,##,###.##";
			case 6:
				return "# ###";
			case 7:
				return "#.###";
			case 8:
				return "#,###";
		}
	}
	
	/**
	 * Returns the transaction type
	 *
	 * @param string $type The version 2 transaction type
	 * @return string The transaction type
	 */
	private function getTransactionType($type) {
		switch ($type) {
			case "credit":
				return "cc";
			default:
				return "other";
		}
	}

	/**
	 * Returns the transaction type ID
	 *
	 * @param string $type The version 2 transaction type
	 * @return string The transaction type ID
	 */	
	private function getTransactionTypeId($type) {
		static $trans_types = null;
		
		if (!isset($this->Transactions))
			Loader::loadModels($this, array("Transactions"));
		
		if ($trans_types == null)
			$trans_types = $this->Transactions->getTypes();
		
		switch ($type) {
			default:
			case "other":
			case "credit":
				return null;
			case "cash":
			case "check":
				foreach ($trans_types as $trans_type) {
					if ($trans_type->name == $type)
						return $trans_type->id;
				}
			case "inhousecredit":
				foreach ($trans_types as $trans_type) {
					if ($trans_type->name == "in_house_credit")
						return $trans_type->id;
				}
			case "moneyorder":
				foreach ($trans_types as $trans_type) {
					if ($trans_type->name == "money_order")
						return $trans_type->id;
				}
		}
		return null;
	}
	
	/**
	 * Returns the transaction status
	 *
	 * @param string $status The version 2 transaction status
	 * @return string The transaction status
	 */
	private function getTransactionStatus($status) {
		switch ($status) {
			case "approved":
			case "declined":
			case "error":
			case "pending":
				return $status;
			case "voided":
				return "void";
			default:
			case "no response":
				return "error";
		}
	}
	
	/**
	 * Returns the local module row ID used for the remote service
	 *
	 * @param stdClass $remote_service A stdClass object representing the remote service
	 * @param string $field The module row field name for the remote service that uniquely identifies the module row
	 * @param int $local_module_id The local ID of the module
	 * @param string $remote_module The name of the module on the remote server
	 * @return int The local module row ID for the service
	 */
	private function getModuleRowId($remote_service, $field, $local_module_id, $remote_module) {
		$module_row = false;
		if ($field) {
			$module_row = $this->local->select(array("module_rows.*"))->from("module_rows")->
				innerJoin("module_row_meta", "module_row_meta.module_row_id", "=", "module_rows.id", false)->
				where("module_row_meta.value", "=", $remote_service->{"m_" . $field})->
				where("module_rows.module_id", "=", $local_module_id)->fetch();
		}
		// If no field, attempt to look up module row based on module name, since
		// the universal module uses the module name to create module rows
		else {
			$module_row = $this->local->select(array("module_rows.*"))->from("module_rows")->
				innerJoin("modules", "modules.id", "=", "module_rows.module_id", false)->
				on("module_row_meta.module_row_id", "=", "module_rows.id", false)->
				innerJoin("module_row_meta", "module_row_meta.key", "=", "name")->
				where("modules.class", "=", "universal_module")->
				where("module_row_meta.value", "=", $remote_module)->
				where("module_rows.module_id", "=", $local_module_id)->fetch();
			
		}
		if ($module_row) {
			return $module_row->id;
		}
		else {
			$module_row = $this->local->select(array("module_rows.*"))->from("module_rows")->
				where("module_rows.module_id", "=", $local_module_id)->fetch();
			return $module_row->id;
		}
	}
	
	/**
	 * Parses the remote package by unserializing data
	 *
	 * @param stdClass $remote_package The remote package
	 * @return stdClass The parsed remote package
	 */
	private function parseRemotePackages($remote_package) {
		$remote_package->id = $remote_package->p_id;
		$remote_package->name = $remote_package->p_name;
		$remote_package->prices = $remote_package->p_prices;
		$remote_package->setupfees = $remote_package->p_setupfees;
		$remote_package->notes = $remote_package->p_notes;
		$remote_package->welcome = $remote_package->p_welcome;
		$remote_package->description = $remote_package->p_notes;
		$remote_package->description_html = $remote_package->p_notes;
		$remote_package->qty = null;
		$remote_package->module_row = $remote_package->p_modrow;
		$remote_package->modrow = $remote_package->p_modrow;
		$remote_package->taxable = $remote_package->p_taxable;
		$remote_package->status = $remote_package->p_status == "1" ? "active" : ($remote_package->p_status == "0" ? "inactive" : "restricted");
		$remote_package->email_content = array(
			array(
				'lang' => "en_us",
				'html' => null,
				'text' => $remote_package->p_welcome
			)
		);
		
		// Parse instantact
		$temp = explode(",", $remote_package->p_instantact);
		$remote_package->instantact = null;
		$remote_package->module = trim($temp[1]);
		if (isset($temp[2]))
			$remote_package->instantact = trim($temp[2]);
		
		// Parse prices
		$temp = explode(",", $remote_package->p_prices);
		$terms = array();
		foreach ($temp as $parts) {
			$pieces = explode("-", $parts);
			if (count($pieces) == 2)
				$terms[$pieces[0]] = $pieces[1];
		}
		$temp = explode(",", $remote_package->p_setupfees);
		$setup = array();
		foreach ($temp as $parts) {
			$pieces = explode("-", $parts);
			if (count($pieces) == 2)
				$setup[$parts[0]] = $parts[1];
		}
		
		foreach ($terms as $term => $price) {
			$remote_package->pricing[] = array(
				'term' => $term,
				'period' => $term == 0 ? "onetime" : "month",
				'price' => $price,
				'setup_fee' => isset($setup[$term]) ? $setup[$term] : '0.0000',
				'currency' => $this->default_currency
			);
		}
		
		return $remote_package;
	}
}
?>