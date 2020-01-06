<?php
/**
 * 2Checkout
 *
 * The 2Checkout API documentation can be found at: https://www.2checkout.com/documentation/api/
 * 2Checkout INS: https://www.2checkout.com/static/va/documentation/INS/index.html
 *
 * Configure 2Checkout Account->Site Management Settings as follows:
 * 		Demo Setting: Parameter
 * 		Direct Return: Direct Return (Your URL)
 * 		Approved URL: This will be overwritten by this gateway
 * Configure 2Checkout Account->User Management Settings as follows (for API access):
 * 		Grant a user access to the APi and "API Updating"
 *	
 * @package blesta
 * @subpackage blesta.components.gateways._2checkout
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class _2checkout extends NonmerchantGateway {
	/**
	 * @var string The version of this gateway
	 */
	private static $version = "1.1.1";
	/**
	 * @var string The authors of this gateway
	 */
	private static $authors = array(array('name' => "Phillips Data, Inc.", 'url' => "http://www.blesta.com"));
	/**
	 * @var array An array of meta data for this gateway
	 */
	private $meta;
	/**
	 * @var string The URL to post payments to
	 */
	private $_2checkout_url = "https://www.2checkout.com/checkout/purchase";
	/**
	 * @var string The URL to post refunds to
	 */
	private $_2checkout_refund_url = "https://www.2checkout.com/api/sales/refund_invoice";
	
	/**
	 * Construct a new merchant gateway
	 */
	public function __construct() {
		
		// Load components required by this gateway
		Loader::loadComponents($this, array("Input", "Json"));
		
		// Load the language required by this gateway
		Language::loadLang("_2checkout", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this gateway
	 *
	 * @return string The common name of this gateway
	 */
	public function getName() {
		return Language::_("_2Checkout.name", true);
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
		return array("AED", "ARS", "AUD", "BRL", "CAD", "CHF", "DKK", "EUR",
			"GBP", "HKD", "ILS", "INR", "JPY", "LTL", "MXN", "MYR", "NOK",
			"NZD", "PHP", "RON", "RUB", "SEK", "SGD", "TRY", "USD", "ZAR");
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
			'vendor_id' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("_2Checkout.!error.vendor_id.empty", true)
				)
			),
			'secret_word' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("_2Checkout.!error.secret_word.empty", true)
				)
			),
			'test_mode'=>array(
				'valid'=>array(
					'if_set'=>true,
					'rule'=>array("in_array", array("true", "false")),
					'message'=>Language::_("_2Checkout.!error.test_mode.valid", true)
				)
			)
		);
		
		// Set checkbox if not set
		if (!isset($meta['test_mode']))
			$meta['test_mode'] = "false";
		
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
		return array("vendor_id", "secret_word");
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
	 * 		- amount The amount to recur
	 * 		- term The term to recur
	 * 		- period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment
	 * @return string HTML markup required to render an authorization and capture payment form
	 */
	public function buildProcess(array $contact_info, $amount, array $invoice_amounts=null, array $options=null) {
		
		// Force 2-decimal places only
		$amount = round($amount, 2);
		if (isset($options['recur']['amount']))
			$options['recur']['amount'] = round($options['recur']['amount'], 2);
		
		$this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		
		// A list of key/value hidden fields to set for the payment form
		$fields = array(
		// Set account/invoice info to use later
			'client_id' => $this->ifSet($contact_info['client_id']),
			'invoices' => base64_encode(serialize($invoice_amounts)),
			'currency_code' => $this->currency,
		// Set required fields
			'sid' => $this->ifSet($this->meta['vendor_id']),
			'cart_order_id' => $this->ifSet($contact_info['client_id']) . "-" . time(),
			'total' => $amount,
			'pay_method' => "CC", // default to credit card option
			'x_Receipt_Link_URL' => Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") . "/_2checkout/",
		// Pre-populate billing information
			'card_holder_name' => $this->Html->concat(" ", $this->ifSet($contact_info['first_name']), $this->ifSet($contact_info['last_name'])),
			'street_address' => $this->ifSet($contact_info['address1']),
			'street_address2' => $this->ifSet($contact_info['address2']),
			'city' => $this->ifSet($contact_info['city']),
			'state' => $this->ifSet($contact_info['state']['code']),
			'zip' => $this->ifSet($contact_info['zip']),
			'country' => $this->ifSet($contact_info['country']['alpha3'])
		);
		
		// Set contact email address and phone number
		if ($this->ifSet($contact_info['id'], false)) {
			Loader::loadModels($this, array("Contacts"));
			if (($contact = $this->Contacts->get($contact_info['id']))) {
				$fields['email'] = $contact->email;
				
				// Set a phone number, if one exists
				$contact_numbers = $this->Contacts->getNumbers($contact_info['id'], "phone");
				if (isset($contact_numbers[0]) && !empty($contact_numbers[0]->number))
					$fields['phone'] = preg_replace("/[^0-9]/", "", $contact_numbers[0]->number);
			}
		}
		
		// Set test mode
		if ($this->ifSet($this->meta['test_mode']) == "true")
			$fields['demo'] = "Y";
		
		$this->view->set("post_to", $this->_2checkout_url);
		$this->view->set("fields", $fields);
		
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
		
		// Order number to verify key must be "1" if demo mode is set
		$order_number = ($this->ifSet($post['demo']) == "Y") ? "1" : $this->ifSet($post['order_number']);
		
		// Validate the response is as expected
		$rules = array(
			'key' => array(
				'valid' => array(
					'rule' => array("compares", "==", strtoupper(md5($this->ifSet($this->meta['secret_word']) . $this->ifSet($this->meta['vendor_id']) . $order_number . $this->ifSet($post['total'])))),
					'message' => Language::_("_2Checkout.!error.key.valid", true)
				)
			),
			'credit_card_processed' => array(
				'completed' => array(
					'rule' => array("compares", "==", "Y"),
					'message' => Language::_("_2Checkout.!error.credit_card_processed.completed", true)
				)
			),
			'sid' => array(
				'valid' => array(
					'rule' => array("compares", "==", $this->ifSet($this->meta['vendor_id'])),
					'message' => Language::_("_2Checkout.!error.sid.valid", true)
				)
			)
		);
		
		$this->Input->setRules($rules);
		$success = $this->Input->validates($post);
		
		// Log the response
		$this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($post), "output", $success);
		
		if (!$success)
			return;
		
		return array(
			'client_id' => $this->ifSet($post['client_id']),
			'amount' => $this->ifSet($post['total']),
			'currency' => $this->ifSet($post['currency_code']),
			'invoices' => unserialize(base64_decode($this->ifSet($post['invoices']))),
			'status' => "approved",
			'reference_id' => null,
			'transaction_id' => $this->ifSet($post['order_number']),
			'parent_transaction_id' => null
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
		
		return array(
			'client_id' => $this->ifSet($post['client_id']),
			'amount' => $this->ifSet($post['total']),
			'currency' => $this->ifSet($post['currency_code']),
			'invoices' => unserialize(base64_decode($this->ifSet($post['invoices']))),
			'status' => "approved",
			'transaction_id' => $this->ifSet($post['order_number']),
			'parent_transaction_id' => null
		);
	}
	
	/**
	 * Captures a previously authorized payment
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts=null) {
		// This method is unsupported
		$this->Input->setErrors($this->getCommonError("unsupported"));
	}
	
	/**
	 * Void a payment or authorization
	 *
	 * @param string $reference_id The reference ID for the previously submitted transaction
	 * @param string $transaction_id The transaction ID for the previously submitted transaction
	 * @param string $notes Notes about the void that may be sent to the client by the gateway
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function void($reference_id, $transaction_id, $notes=null) {
		// This method is unsupported
		$this->Input->setErrors($this->getCommonError("unsupported"));
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
		
		$post_to = $this->_2checkout_refund_url;
		
		$params = array(
			'sale_id' => $transaction_id,
			// Category is the reason for the refund and must be a value in the domain: [1-6]U[8-17]
			'category' => 13, // 13 = Service refunded at sellers request
			'comment' => str_replace(array(">", "<"), "", $notes) // comment cannot contain '>' or '<'
		);
		
		// Set a default comment since the field is required
		if (empty($params['comment'])) {
			Loader::loadHelpers($this, array("CurrencyFormat"));
			$params['comment'] = Language::_("2Checkout.refund.comment", true, $this->CurrencyFormat->cast($amount, $this->currency));
		}
		
		// Attempt a refund
		if (!($response = $this->sendApi($post_to, $params)))
			return;
		
		return array(
			'status' => "refunded",
			'transaction_id' => null
		);
	}
	
	/**
	 * Submits the API request, returns the result. Automatically sets
	 * authentication parameters.
	 *
	 * @param string $post_to The API URL to submit the request to
	 * @param array An array of name/value pairs to send to the API
	 * @return mixed An array of name/value response pairs from the API if successful, false otherwise
	 */
	private function sendApi($post_to, array $params) {
		if (!isset($this->Http)) {
			Loader::loadComponents($this, array("Net"));
			$this->Http = $this->Net->create("Http");
		}
		
		// Make POST request to $post_to, log data sent and received
		$this->Http->setHeader("Accept: application/json");
		$this->Http->setHeader("Authorization: Basic " . base64_encode($this->ifSet($this->meta['api_username']) . ":" . $this->ifSet($this->meta['api_password'])));
		$response = $this->Http->post($post_to, $params);
		
		// Log data sent
		$this->log($post_to, serialize($this->maskData($params, array("username", "password"))), "input", true);
		
		$response = (array)$this->Json->decode($response);
		
		// Log the response
		if (strtolower($this->ifSet($response['response_code'])) == "ok") {
			// Log the successful response
			$this->log($post_to, serialize($response), "output", true);
			
			return $response;
		}
		else {
			$this->Input->setErrors($this->getCommonError("general"));
			
			// Log the unsuccessful response
			$this->log($post_to, serialize($response), "output", false);
		}
		
		return false;
	}
}
?>