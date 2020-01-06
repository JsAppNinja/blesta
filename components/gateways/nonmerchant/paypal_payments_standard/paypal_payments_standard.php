<?php
/**
 * PayPal Payments Standard
 *
 * The PayPal Payments Standard API can be found at: https://merchant.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/howto_html_wp_standard_overview
 * PayPal IPN reference: https://merchant.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_admin_IPNIntro
 * PayPal API reference: https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/howto_api_reference
 *
 * @package blesta
 * @subpackage blesta.components.gateways.paypal_payments_subscription
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class PaypalPaymentsStandard extends NonmerchantGateway {
	/**
	 * @var string The version of this gateway
	 */
	private static $version = "1.2.3";
	/**
	 * @var string The authors of this gateway
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	/**
	 * @var array An array of meta data for this gateway
	 */
	private $meta;
	/**
	 * @var string The URL to post payments to
	 */
	private $paypal_url = "https://www.paypal.com/cgi-bin/webscr";
	/**
	 * @var string The URL to post payments to in developer mode
	 */
	private $paypal_dev_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
	/**
	 * @var string The URL to use when communicating with the PayPal API
	 */
	private $paypal_api_url = "https://api-3t.paypal.com/nvp";
	/**
	 * @var string The URL to use when communicating with the PayPal API in developer mode
	 */
	private $paypal_api_dev_url = "https://api-3t.sandbox.paypal.com/nvp";
	
	
	/**
	 * Construct a new merchant gateway
	 */
	public function __construct() {
		
		// Load components required by this gateway
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this gateway
		Language::loadLang("paypal_payments_standard", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this gateway
	 *
	 * @return string The common name of this gateway
	 */
	public function getName() {
		return Language::_("PaypalPaymentsStandard.name", true);
	}
	
	/**
	 * Returns the version of this gateway
	 *
	 * @return string The current version of this gateway
	 */
	public function getVersion() {
		return self::$version;
	}

	/**
	 * Returns the name and URL for the authors of this gateway
	 *
	 * @return array The name and URL of the authors of this gateway
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Return all currencies supported by this gateway
	 *
	 * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this gateway supports
	 */
	public function getCurrencies() {
		return array("AUD", "BRL", "CAD", "CZK", "DKK", "EUR", "HKD", "HUF", "ILS", "JPY",
			"MYR", "MXN", "NOK", "NZD", "PHP", "PLN", "GBP", "SGD", "SEK", "CHF",
			"TWD", "THB", "TRY", "USD");
	}
	
	/**
	 * Sets the currency code to be used for all subsequent payments
	 *
	 * @param string $currency The ISO 4217 currency code to be used for subsequent payments
	 */
	public function setCurrency($currency) {
		$this->currency = $currency;
	}
	
	/**
	 * Create and return the view content required to modify the settings of this gateway
	 *
	 * @param array $meta An array of meta (settings) data belonging to this gateway
	 * @return string HTML content containing the fields to update the meta data for this gateway
	 */
	public function getSettings(array $meta=null) {
		$this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("meta", $meta);
		
		return $this->view->fetch();
	}
	
	/**
	 * Validates the given meta (settings) data to be updated for this gateway
	 *
	 * @param array $meta An array of meta (settings) data to be updated for this gateway
	 * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
	 */
	public function editSettings(array $meta) {
		// Verify meta data is valid
		$rules = array(
			'account_id' => array(
				'valid' => array(
					'rule' => array("isEmail", false),
					'message' => Language::_("PaypalPaymentsStandard.!error.account_id.valid", true)
				)
			),
			'dev_mode'=>array(
				'valid'=>array(
					'if_set'=>true,
					'rule'=>array("in_array", array("true", "false")),
					'message'=>Language::_("PaypalPaymentsStandard.!error.dev_mode.valid", true)
				)
			)
		);

		// Set checkbox if not set
		if (!isset($meta['dev_mode']))
			$meta['dev_mode'] = "false";
		
		$this->Input->setRules($rules);
		
		// Validate the given meta data to ensure it meets the requirements
		$this->Input->validates($meta);
		// Return the meta data, no changes required regardless of success or failure for this gateway
		return $meta;
	}
	
	/**
	 * Returns an array of all fields to encrypt when storing in the database
	 *
	 * @return array An array of the field names to encrypt when storing in the database
	 */
	public function encryptableFields() {
		return array("account_id", "api_username", "api_password", "api_signature");
	}
	
	/**
	 * Sets the meta data for this particular gateway
	 *
	 * @param array $meta An array of meta data to set for this gateway
	 */
	public function setMeta(array $meta=null) {
		$this->meta = $meta;
	}
	
	/**
	 * Returns all HTML markup required to render an authorization and capture payment form
	 *
	 * @param array $contact_info An array of contact info including:
	 * 	- id The contact ID
	 * 	- client_id The ID of the client this contact belongs to
	 * 	- user_id The user ID this contact belongs to (if any)
	 * 	- contact_type The type of contact
	 * 	- contact_type_id The ID of the contact type
	 * 	- first_name The first name on the contact
	 * 	- last_name The last name on the contact
	 * 	- title The title of the contact
	 * 	- company The company name of the contact
	 * 	- address1 The address 1 line of the contact
	 * 	- address2 The address 2 line of the contact
	 * 	- city The city of the contact
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the country
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-cahracter country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the contact
	 * @param float $amount The amount to charge this contact
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	- id The ID of the invoice being processed
	 * 	- amount The amount being processed for this invoice (which is included in $amount)
	 * @param array $options An array of options including:
	 * 	- description The Description of the charge
	 * 	- return_url The URL to redirect users to after a successful payment
	 * 	- recur An array of recurring info including:
	 * 		- start_date The date/time in UTC that the recurring payment begins
	 * 		- amount The amount to recur
	 * 		- term The term to recur
	 * 		- period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment
	 * @return mixed A string of HTML markup required to render an authorization and capture payment form, or an array of HTML markup
	 */
	public function buildProcess(array $contact_info, $amount, array $invoice_amounts=null, array $options=null) {
		
		// Force 2-decimal places only
		$amount = round($amount, 2);
		if (isset($options['recur']['amount']))
			$options['recur']['amount'] = round($options['recur']['amount'], 2);
		
		$post_to = $this->paypal_url;
		if ($this->ifSet($this->meta['dev_mode']) == "true")
			$post_to = $this->paypal_dev_url;

		// An array of key/value hidden fields to set for the payment form
		$fields = array(
			'cmd' => "_xclick",
			'business' => $this->ifSet($this->meta['account_id']),
			'page_style' => $this->ifSet($this->meta['page_style'], "primary"),
			'item_name' => $this->ifSet($options['description']),
			'amount' => $amount,
			'currency_code' => $this->currency,
			'notify_url' => Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") ."/paypal_payments_standard/?client_id=" . $this->ifSet($contact_info['client_id']),
			'return' => $this->ifSet($options['return_url']),
			'rm' => "2", // redirect back using POST
			'no_note' => "1", // no buyer notes
			'no_shipping' => "1", // no buyer shipping info
			'first_name' => $this->ifSet($contact_info['first_name']),
			'last_name' => $this->ifSet($contact_info['last_name']),
			'address1' => $this->ifSet($contact_info['address1']),
			'address2' => $this->ifSet($contact_info['address2']),
			'city' => $this->ifSet($contact_info['city']),
			'country' => $this->ifSet($contact_info['country']['alpha2']),
			'zip' => $this->ifSet($contact_info['zip']),
			'charset' => "utf-8",
			'bn' => "PhillipsData_SP"
		);
		
		// Set state if US
		if ($this->ifSet($contact_info['country']['alpha2']) == "US")
			$fields['state'] = $this->ifSet($contact_info['state']['code']);

		// Set all invoices to pay
		if (isset($invoice_amounts) && is_array($invoice_amounts))
			$fields['custom'] = $this->serializeInvoices($invoice_amounts);
		
		// Build recurring payment fields
		$recurring_fields = array();
		if ($this->ifSet($options['recur']) && $this->ifSet($options['recur']['amount']) > 0) {
			$recurring_fields = $fields;
			unset($recurring_fields['amount']);
			
			$t3 = null;
			// PayPal calls 'term' 'period' and 'period' 'term'...
			switch ($this->ifSet($options['recur']['period'])) {
				case "day":
					$t3 = "D";
					break;
				case "week":
					$t3 = "W";
					break;
				case "month":
					$t3 = "M";
					break;
				case "year";
					$t3 = "Y";
					break;
			}
			
			$recurring_fields['cmd'] = "_xclick-subscriptions";
			$recurring_fields['a1'] = $amount;
			
			// Calculate days until recurring payment begins. Set initial term
			// to differ from future term iff start_date is set and is set to
			// a future date
			$day_diff = 0;
			if ($this->ifSet($options['recur']['start_date']) &&
				($day_diff = floor((strtotime($options['recur']['start_date']) - time())/(60*60*24))) > 0) {
				
				$recurring_fields['p1'] = $day_diff;
				$recurring_fields['t1'] = "D";
			}
			else {
				$recurring_fields['p1'] = $this->ifSet($options['recur']['term']);
				$recurring_fields['t1'] = $t3;
			}
			$recurring_fields['a3'] = $this->ifSet($options['recur']['amount']);
			$recurring_fields['p3'] = $this->ifSet($options['recur']['term']);
			$recurring_fields['t3'] = $t3;
			$recurring_fields['custom'] = null;
			$recurring_fields['modify'] = $this->ifSet($this->meta['modify']) == "true" ? 1 : 0;
			$recurring_fields['src'] = "1"; // recur payments
			
			
			// Can't allow recurring field if prorated term is more than 90 days out
			if ($day_diff > 90)
				$recurring_fields = array();
			
			// Can't allow recurring field if the period is not valid (e.g. one-time)
			if ($t3 === null)
				$recurring_fields = array();
		}
		
		$regular_btn = $this->buildForm($post_to, $fields, false);
		$recurring_btn = null;
		if (!empty($recurring_fields))
			$recurring_btn = $this->buildForm($post_to, $recurring_fields, true);

		switch ($this->ifSet($this->meta['pay_type'])) {
			case "both":
				if ($recurring_btn)
					return array($regular_btn, $recurring_btn);
				return $regular_btn;
			case "subscribe":
				return $recurring_btn;
			case "onetime":
				return $regular_btn;
		}
		return null;
	}
	
	/**
	 * Builds the HTML form
	 *
	 * @param string $post_to The URL to post to
	 * @param array $fields An array of key/value input fields to set in the form
	 * @param boolean $recurring True if this is a recurring payment request, false otherwise
	 * @return string The HTML form
	 */
	private function buildForm($post_to, $fields, $recurring=false) {
		$this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("post_to", $post_to);
		$this->view->set("fields", $fields);
		$this->view->set("recurring", $recurring);
		
		return $this->view->fetch();
	}
	
	/**
	 * Validates the incoming POST/GET response from the gateway to ensure it is
	 * legitimate and can be trusted.
	 *
	 * @param array $get The GET data for this request
	 * @param array $post The POST data for this request
	 * @return array An array of transaction data, sets any errors using Input if the data fails to validate
	 *  - client_id The ID of the client that attempted the payment
	 *  - amount The amount of the payment
	 *  - currency The currency of the payment
	 *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 * 	- parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction (in the case of refunds)
	 */
	public function validate(array $get, array $post) {

		if (!isset($this->Http)) {
			Loader::loadComponents($this, array("Net"));
			$this->Http = $this->Net->create("Http");
		}
		
		$client_id = $this->ifSet($get['client_id']);
		if (!$client_id) {
			$client_id = $this->clientIdFromEmail($this->ifSet($post['payer_email']));
		}
		
		$url = $this->paypal_url;
		if ($this->meta['dev_mode'] == "true")
			$url = $this->paypal_dev_url;
		
		// Build data to post-back to the gateway for confirmation
		$confirm_post = array_merge(array('cmd'=>"_notify-validate"), $post);
		
		// Log request received
		$this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($post), "output", true);
		
		// Confirm data with the gateway
		$response = $this->Http->post($url, http_build_query($confirm_post));
		
		// Log post-back sent
		$this->log($url, serialize($confirm_post), "input", true);
		unset($confirm_post);
		
		
		// Ensure payment is verified, and validate that the business is valid
		// and matches that configured for this gateway, to prevent payments
		// being recognized that were delivered to a different account
		$account_id = strtolower($this->ifSet($this->meta['account_id']));
		
		if ($response != "VERIFIED" || (
			strtolower($this->ifSet($post['business'])) != $account_id &&
			strtolower($this->ifSet($post['receiver_email'])) != $account_id)) {
			
			if ($response === "INVALID") {
				$this->Input->setErrors($this->getCommonError("invalid"));
				
				// Log error response
				$this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($response), "output", false);
				return;
			}
		}
		
		// Capture the IPN status, or reject it if invalid
		$status = "error";
		switch (strtolower($this->ifSet($post['payment_status']))) {
			case "completed":
				$status = "approved";
				break;
			case "pending":
				$status = "pending";
				break;
			case "refunded":
				$status = "refunded";
				break;
			default:
				// Log request received, even though not necessarily processed
				$verified = ($response === "VERIFIED");
				$this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($post['payment_status']), "output", $verified);
				
				if ($verified) {
					return;
				}
		}
		
		return array(
			'client_id' => $client_id,
			'amount' => $this->ifSet($post['mc_gross']),
			'currency' => $this->ifSet($post['mc_currency']),
			'status' => $status,
			'reference_id' => null,
			'transaction_id' => $this->ifSet($post['txn_id']),
			'parent_transaction_id' => $this->ifSet($post['parent_txn_id']),
			'invoices' => $this->unserializeInvoices($this->ifSet($post['custom']))
		);
	}
	
	/**
	 * Returns data regarding a success transaction. This method is invoked when
	 * a client returns from the non-merchant gateway's web site back to Blesta.
	 *
	 * @param array $get The GET data for this request
	 * @param array $post The POST data for this request
	 * @return array An array of transaction data, may set errors using Input if the data appears invalid
	 *  - client_id The ID of the client that attempted the payment
	 *  - amount The amount of the payment
	 *  - currency The currency of the payment
	 *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 * 	- parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
	 */
	public function success(array $get, array $post) {
		$client_id = $this->ifSet($get['client_id']);

		if (!$client_id) {
			$client_id = $this->clientIdFromEmail($this->ifSet($post['payer_email']));
		}

		return array(
			'client_id' => $client_id,
			'amount' => $this->ifSet($post['mc_gross']),
			'currency' => $this->ifSet($post['mc_currency']),
			'invoices' => $this->unserializeInvoices($this->ifSet($post['custom'])),
			'status' => "approved", // we wouldn't be here if it weren't, right?
			'transaction_id' => $this->ifSet($post['txn_id'])
		);		
	}
	
	/**
	 * Refund a payment
	 *
	 * @param string $reference_id The reference ID for the previously submitted transaction
	 * @param string $transaction_id The transaction ID for the previously submitted transaction
	 * @param float $amount The amount to refund this transaction
	 * @param string $notes Notes about the refund that may be sent to the client by the gateway
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refund($reference_id, $transaction_id, $amount, $notes=null) {

		$post_to = $this->getApiUrl();

		// Process the refund
		$params = array(
			'METHOD' => "RefundTransaction",
			'TRANSACTIONID' => $transaction_id,
			'REFUNDTYPE' => "Full",
			//'AMT' => $amount,
			'CURRENCYCODE' => $this->currency,
			'NOTE' => $notes
		);
		$response = $this->sendApi($post_to, $params);
		
		// If no response from gateway, set error and return
		if (!is_array($response)) {
			$this->Input->setErrors($this->getCommonError("general"));
			return;
		}
		
		$status = strtolower($this->ifSet($response['ACK']));
		
		if ($status == "success" || $status == "successwithwarning") {

			// Log the successful response
			$this->log($post_to, serialize($response), "output", true);

			return array(
				'status' => "refunded",
				'transaction_id' => $this->ifSet($response['REFUNDTRANSACTIONID']),
			);
		}
		else {
			$this->Input->setErrors($this->getCommonError("general"));
			
			// Log the unsuccessful response
			$this->log($post_to, serialize($response), "output", false);
		}
	}
	
	/**
	 * Submits the API request, returns the result. Automatically sets
	 * authentication parameters.
	 *
	 * @param string $post_to The API URL to submit the request to
	 * @param array An array of name/value pairs to send to the API
	 * @return array An array of name/value response pairs from the API
	 */
	private function sendApi($post_to, array $params) {
		if (!isset($this->Http)) {
			Loader::loadComponents($this, array("Net"));
			$this->Http = $this->Net->create("Http");
		}
			
		$params['USER'] = $this->ifSet($this->meta['api_username']);
		$params['PWD'] = $this->ifSet($this->meta['api_password']);
		$params['SIGNATURE'] = $this->ifSet($this->meta['api_signature']);
		$params['VERSION'] = "51.0";
		
		// make POST request to $post_to, log data sent and received
		$response = array();
		parse_str($this->Http->post($post_to, http_build_query($params)), $response);
		
		// Log data sent
		$this->log($post_to, serialize($this->maskData($params, array("USER", "PWD", "SIGNATURE"))), "input", true);
		
		return $response;
	}
	
	/**
	 * Returns the URL to use for the API
	 *
	 * @return string The URL to use for the API
	 */
	private function getApiUrl() {
		$post_to = $this->paypal_api_url;
		if ($this->ifSet($this->meta['dev_mode']) == "true")
			$post_to = $this->paypal_api_dev_url;
		return $post_to;
	}
	
	/**
	 * Serializes an array of invoice info into a string
	 * 
	 * @param array A numerically indexed array invoices info including:
	 *  - id The ID of the invoice
	 *  - amount The amount relating to the invoice
	 * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
	 */
	private function serializeInvoices(array $invoices) {
		$str = "";
		foreach ($invoices as $i => $invoice)
			$str .= ($i > 0 ? "|" : "") . $invoice['id'] . "=" . $invoice['amount'];
		return $str;
	}

	/**
	 * Unserializes a string of invoice info into an array
	 *
	 * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
	 * @return array A numerically indexed array invoices info including:
	 *  - id The ID of the invoice
	 *  - amount The amount relating to the invoice
	 */	
	private function unserializeInvoices($str) {
		$invoices = array();
		$temp = explode("|", $str);
		foreach ($temp as $pair) {
			$pairs = explode("=", $pair, 2);
			if (count($pairs) != 2)
				continue;
			$invoices[] = array('id' => $pairs[0], 'amount' => $pairs[1]);
		}
		return $invoices;
	}
}
?>