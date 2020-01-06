<?php
/**
 * Invoice Delivery component
 *
 * Consolidates invoice creation and delivery. Supports email, interfax, and postalmethods.
 *
 * @package blesta
 * @subpackage blesta.components.invoice_delivery
 * @copyright Copyright (c) 2011, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class InvoiceDelivery {
	/**
	 * @var string The language code to use for all email correspondance
	 */
	private $language;
	
	/**
	 * @var object An object representing the company being processed
	 */
	private $company;
	
	/**
	 * @var array Company settings
	 */
	private $company_settings;
	
	/**
	 * @var array An array of stdClass objects representing invoices
	 */
	private $invoices;
	
	/**
	 * Initialize the Invoice Delivery object
	 */
	public function __construct() {
		Loader::loadComponents($this, array("Input", "Delivery", "InvoiceTemplates"));
		Loader::loadModels($this, array("Invoices", "Clients", "Contacts", "Companies", "Countries", "Transactions"));
		Loader::loadHelpers($this, array("CurrencyFormat", "Date"));
		
		Language::loadLang("invoice_delivery", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Delivers a set of invoices using the given delivery method. All invoices are compiled together into a single document.
	 *
	 * @param array $invoice_ids An array of invoice IDs to deliver
	 * @param string $delivery_method The delivery method (email, interfax, postalmethods)
	 * @param mixed $deliver_to The destination of the invoices, a string or array of email addresses or fax numbers (optional, can not override invoice postal address)
	 * @param string $from_staff_id The ID of the staff member this invoice is to be delivered from (optional)
	 * @param array $options An array of additional options to pass and may include:
	 * 	- base_client_url The base URL to the client interface
	 * 	- email_template The email template name to use (optional)
	 * 	- email_tags An array of key/value tag replacements (optional)
	 * 	- language The language to use (optional, defaults to the invoice client's language, or the system's language otherwise)
	 */
	public function deliverInvoices(array $invoice_ids, $delivery_method, $deliver_to=null, $from_staff_id=null, array $options=null) {
		
		switch ($delivery_method) {
			case "email":
				// Fetch all invoices request and build them together into a single document
				$invoices = $this->getInvoices($invoice_ids, true);
				$document = $this->buildInvoices($invoices, true, $options);
				
				// Ensure we have an invoice document before continuing
				if (!$document)
					return;
				
				if (!isset($this->Emails))
					Loader::loadModels($this, array("Emails"));
				
				$temp_dir = $this->company_settings['temp_dir'];
				$inv_path = $temp_dir . "invoice-" . $this->company->id . "-" . time();
				file_put_contents($inv_path, $document->fetch());
				
				// Set the attachment name and extension, either "invoices.ext" or the specific invoice ID
				$attachment_name = ((count($invoices) > 1) ? "invoices" : $invoices[0]->id_code) . "." . $document->getFileExtension($this->company_settings['inv_mimetype']);
				
				$attachments = array(
					array(
						'path' => $inv_path,
						'name' => $attachment_name,
						'encoding' => "base64",
						'type' => $this->company_settings['inv_mimetype']
					)
				);
				
				// Set the payment URL and the autodebit date of this invoice if applicable
				$payment_urls = array();
				foreach ($invoices as &$invoice) {
					$hash = $this->Invoices->createPayHash($invoice->client_id, $invoice->id);
					$invoice->payment_url = (isset($options['base_client_url']) ? $options['base_client_url'] : null) . "pay/method/" . $invoice->id . "/?sid=" . rawurlencode($this->Invoices->systemEncrypt('c=' . $invoice->client_id . '|h=' . $hash));
					
					// Set a new "autodebit_date" and "autodebit_date_formatted" value for each invoice
					$invoice->autodebit_date = "";
					if (($autodebit_date = $this->Invoices->getAutodebitDate($invoice->id))) {
						$invoice->autodebit_date = $autodebit_date;
						$invoice->autodebit_date_formatted = $this->Date->cast($autodebit_date, $invoice->client->settings['date_format']);
					}
				}
				
				// Set default tags
				$tags = array(
					'contact' => $invoices[0]->billing,
					'invoices' => $invoices,
					'autodebit' => ($invoices[0]->client->settings['autodebit'] == "true"),
					'client_url' => isset($options['base_client_url']) ? $options['base_client_url'] : null
				);
				
				// If only one invoice is set, create a new invoice tag to contain it
				if (count($invoices) === 1) {
					$tags['invoice'] = $invoices[0];
				}
				
				// Replace tags with those given
				if (isset($options['email_tags']))
					$tags = $options['email_tags'];			
				
				// Set "invoices" tag to those that were built
				if (isset($options['set_built_invoices']) && $options['set_built_invoices'] == true)
					$tags['invoices'] = $invoices;
				
				// Set the default email template
				if (!isset($options['email_template']))
					$options['email_template'] = "invoice_delivery_unpaid";
				
				$this->Emails->send($options['email_template'], Configure::get("Blesta.company_id"), $this->language, $deliver_to, $tags, null, null, $attachments, array('to_client_id'=>$invoices[0]->client->id, 'from_staff_id'=>$from_staff_id));
				
				// Remove the temp invoice file
				@unlink($inv_path);
				break;
			case "interfax":
				// Fetch all invoices request and build them together into a single document
				$invoices = $this->getInvoices($invoice_ids, true);
				$document = $this->buildInvoices($invoices, true, $options);
				
				// Ensure we have an invoice document before continuing
				if (!$document)
					return;
				
				if (!isset($this->Interfax)) {
					$this->Interfax = $this->Delivery->create("Interfax");
					
					// Ensure the the system has the libxml extension
					if (!extension_loaded("libxml")) {
						unset($this->Interfax);
						
						$errors = array(
							'libxml' => array(
								'required' => Language::_("InvoiceDelivery.!error.libxml_required", true)
							)
						);
						$this->Input->setErrors($errors);
						return;
					}
				}
				
				$this->Interfax->setAccount($this->company_settings['interfax_username'], $this->company_settings['interfax_password']);
				$this->Interfax->setNumbers($deliver_to);
				$this->Interfax->setPageSize($this->company_settings['inv_paper_size']);
				$this->Interfax->setContacts($invoices[0]->billing->first_name . " " . $invoices[0]->billing->last_name);
				$this->Interfax->setFile(array(array('file' => $document->fetch(), 'type' => $document->getFileExtension($this->company_settings['inv_mimetype']))));
				$this->Interfax->setCallerId($this->company->name);
				$this->Interfax->setSubject(Language::_("InvoiceDelivery.deliverinvoices.interfax_subject", true, $invoices[0]->id_code));
				$this->Interfax->send();
				
				if (($errors = $this->Interfax->errors()))
					$this->Input->setErrors($errors);
				break;
			case "postalmethods":
				// Fetch all invoices, grouped by client
				$invoices = $this->getInvoices($invoice_ids);
				
				if (!isset($this->PostalMethods)) {
					$this->PostalMethods = $this->Delivery->create("PostalMethods");
					
					// Ensure the the system has the libxml extension
					if (!extension_loaded("libxml")) {
						unset($this->PostalMethods);
						
						$errors = array(
							'libxml' => array(
								'required' => Language::_("InvoiceDelivery.!error.libxml_required", true)
							)
						);
						$this->Input->setErrors($errors);
						return;
					}
				}
				
				// Build and send one document per client
				foreach ($invoices as $invoice_set) {
					// Build the document without address information (postalmethods will add their own)
					$document = $this->buildInvoices($invoice_set, false, $options);
					
					// Ensure we have an invoice document before continuing
					if (!$document)
						continue;
					
					$testmode = ("true" == $this->company_settings['postalmethods_testmode']) ? true : false;
					$address = array(
						'name' => $invoice_set[0]->billing->first_name . " " . $invoice_set[0]->billing->last_name,
						'company' => $invoice_set[0]->billing->company,
						'address1' => $invoice_set[0]->billing->address1,
						'address2' => $invoice_set[0]->billing->address2,
						'city' => $invoice_set[0]->billing->city,
						'state' => $invoice_set[0]->billing->state, // The ISO 3166-2 subdivision code
						'zip' => $invoice_set[0]->billing->zip,
						'country_code' => $invoice_set[0]->billing->country->alpha2 // The ISO 3166-1 alpha3 country code
					);
					
					// Send invoices via PostalMethods
					//$this->PostalMethods->setAccount($username, $password);
					$this->PostalMethods->setApiKey($this->company_settings['postalmethods_apikey']);
					$this->PostalMethods->setTestMode($testmode);
					$this->PostalMethods->setDescription(Language::_("InvoiceDelivery.deliverinvoices.postalmethods_description", true, $invoice_set[0]->id_code));
					$this->PostalMethods->setToAddress($address);
					
					// Include a reply envelope
					if ("true" == $this->company_settings['postalmethods_replyenvelope'])
						$this->PostalMethods->setReplyEnvelope(true);
					
					$this->PostalMethods->setFile(array('file' => $document->fetch(), 'type' => $document->getFileExtension($this->company_settings['inv_mimetype'])));
					$this->PostalMethods->send();
					
					if (($errors = $this->PostalMethods->errors()))
						$this->Input->setErrors($errors);
				}
				break;
		}
	}
	
	/**
	 * Offers for download a set of invoices. All invoices are compiled together into a single document.
	 *
	 * @param array $invoice_ids A numerically-indexed array of invoice IDs from which to download
	 * @param array $options An array of options including (optional):
	 * 	- language The language to use (optional, defaults to the invoice client's language, or the system's language otherwise)
	 */
	public function downloadInvoices(array $invoice_ids, array $options = null) {
		$invoices = $this->getInvoices($invoice_ids, true);
		$this->buildInvoices($invoices, true, $options)->download();
	}
	
	/**
	 * Returns an errors raised
	 *
	 * @return array An array of errors, boolean false if no errors were set
	 */
	public function errors() {
		return $this->Input->errors();
	}
	
	/**
	 * Fetches invoices and groups them by client ID
	 *
	 * @param array $invoice_ids An array of invoice ID numbers to fetch
	 * @param boolean $merge True to merge all invoices together in single large array, false to keep invoices divided by client ID
	 * @return array An array of stdClass invoice object grouped by client ID (if $merge is true 1st index is numeric, otherwise 1st index is client ID, 2nd index is numeric)
	 */
	private function getInvoices(array $invoice_ids, $merge=false) {
		
		$invoices = array();
		for ($i=0, $num_invoices=count($invoice_ids); $i<$num_invoices; $i++) {
			$invoice = $this->Invoices->get($invoice_ids[$i]);
			
			if ($invoice) {
				if (!isset($invoices[$invoice->client_id]))
					$invoices[$invoice->client_id] = array();
					
				$invoices[$invoice->client_id][] = $invoice;
			}
		}
		
		// Squish the multi-dimensional array into a single dimension
		if ($merge) {
			$all_invoices = array();
			foreach ($invoices as $client_id => $invoice_set) {
				$all_invoices = array_merge($all_invoices, $invoice_set);
			}
			$invoices = $all_invoices;
		}
		
		return $invoices;
	}
	
	/**
	 * Takes an array of invoices and constructs a single document object containing
	 * all invoice data (e.g. can create a single PDF containing multiple invoices).
	 *
	 * @param array A numerically indexed array of stdClass objects each representing an invoice
	 * @param boolean $include_address True to include address information on the invoices, false otherwise
	 * @param array $options An array of options including (optional):
	 * 	- language The language to use (optional, defaults to the invoice client's language, or the system's language otherwise)
	 * @return object The object containing the build invoices
	 */
	private function buildInvoices(array $invoices, $include_address=true, array $options=null) {
		if (!isset($this->SettingsCollection))
			Loader::loadComponents($this, array("SettingsCollection"));
		
		$company_id = Configure::get("Blesta.company_id");
		$this->CurrencyFormat->setCompany(Configure::get("Blesta.company_id"));
		
		$client_id = null;
		$transaction_types = $this->Transactions->transactionTypeNames();
		for ($i=0, $num_invoices=count($invoices); $i<$num_invoices; $i++) {
			
			if ($client_id != $invoices[$i]->client_id) {
				$client_id = $invoices[$i]->client_id;
				
				// Fetch the contact to which invoices should be addressed
				$client = $this->Clients->get($client_id);
				if (!($billing = $this->Contacts->get((int)$client->settings['inv_address_to'])) || $billing->client_id != $client_id)
					$billing = $this->Contacts->get($client->contact_id);
				$country = $this->Countries->get($billing->country);
				
				$this->language = $client->settings['language'];
			}
			
			$invoices[$i]->billing = $billing;
			$invoices[$i]->billing->country = $country;
			$invoices[$i]->client = $client;
			
			// Set applied transactions
			$invoices[$i]->applied_transactions = $this->Transactions->getApplied(null, $invoices[$i]->id);
			foreach ($invoices[$i]->applied_transactions as &$applied_transaction) {
				$applied_transaction->type_real_name = $transaction_types[($applied_transaction->type_name != "" ? $applied_transaction->type_name : $applied_transaction->type)];
			}
		}
		
		if (!$this->company || $this->company->id != $company_id) {
			$this->company = $this->Companies->get($company_id);
			$this->company_settings = $this->SettingsCollection->fetchSettings($this->Companies, $this->company->id);
			$this->Date->setTimezone("UTC", $this->company_settings['timezone']);
		}
		
		// Set the invoice attachments
		$document = null;
		
		// Set a 'global' language for all invoices in the document
		$language = ($options && isset($options['language']) ? $options['language'] : null);
		
		try {
			$meta = array(
				'paper_size' => $this->company_settings['inv_paper_size'],
				'background' => $this->company_settings['inv_background'],
				'logo' => $this->company_settings['inv_logo'],
				'company_name' => $this->company->name,
				'company_address' => $this->company->address,
				'tax_id' => $this->company_settings['tax_id'],
				'terms' => $this->company_settings['inv_terms'],
				'display_logo' => $this->company_settings['inv_display_logo'],
				'display_paid_watermark' => $this->company_settings['inv_display_paid_watermark'],
				'display_companyinfo' => $this->company_settings['inv_display_companyinfo'],
				'display_payments' => $this->company_settings['inv_display_payments'],
				'display_due_date_draft' => $this->company_settings['inv_display_due_date_draft'],
				'display_due_date_proforma' => $this->company_settings['inv_display_due_date_proforma'],
				'display_due_date_inv' => $this->company_settings['inv_display_due_date_inv'],
				'settings' => $this->company_settings,
				'language' => $language
			);
		
			$document = $this->InvoiceTemplates->create($this->company_settings['inv_template']);
			$document->setMeta($meta);
			$document->setCurrency($this->CurrencyFormat);
			$document->setDate($this->Date);
			$document->setMimeType($this->company_settings['inv_mimetype']);
			$document->includeAddress($include_address);
			$document->makeDocument($invoices);
		}
		catch (Exception $e) {
			$this->Input->setErrors(array('InvoiceTemplates' => array('create' => $e->getMessage())));
		}
		return $document;
	}
}
?>