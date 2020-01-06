<?php
/**
 * PayPal Payflow Pro Credit Card processing gateway. Supports onsite
 * payment processing for Credit Cards.
 *
 * The PayPal Payflow Pro API can be found at: https://cms.paypal.com/cms_content/US/en_US/files/developer/PayflowGateway_Guide.pdf
 *
 * @package blesta
 * @subpackage blesta.components.gateways.payflow
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Payflow extends MerchantGateway implements MerchantCc {
	/**
	 * @var string The version of this gateway
	 */
	private static $version = "1.0.2";
	/**
	 * @var string The authors of this gateway
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	/**
	 * @var array An array of meta data for this gateway
	 */
	private $meta;
	/**
	 * @var string The live URL for API requests
	 */
	private $live_url = "https://payflowpro.paypal.com";
	/**
	 * @var string The test URL for API requests
	 */
	private $test_url = "https://pilot-payflowpro.paypal.com";
	
	/**
	 * Construct a new merchant gateway
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("payflow", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this gateway
	 *
	 * @return string The common name of this gateway
	 */
	public function getName() {
		return Language::_("Payflow.name", true);
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
		return array("AUD", "CAD", "CHF", "CZK", "DKK", "EUR", "GBP", "HKD",
			"HUF", "ILS", "JPY", "MXN", "NOK", "NZD", "PHP", "PLN", "SGD",
			"SEK", "TWD", "THB", "USD"
		);
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
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("settings", "default");
		$this->view->setDefaultView("components" . DS . "gateways" . DS . "merchant" . DS . "payflow" . DS);
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
			'user'=>array(
				'empty'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Payflow.!error.user.empty", true)
				)
			),
			'vendor'=>array(
				'empty'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Payflow.!error.vendor.empty", true)
				)
			),
			'partner'=>array(
				'empty'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Payflow.!error.partner.empty", true)
				)
			),
			'password'=>array(
				'format'=>array(
					'rule'=>array("betweenLength", 6, 32),
					'message'=>Language::_("Payflow.!error.password.format", true)
				)
			),
			'test_mode'=>array(
				'valid'=>array(
					'if_set'=>true,
					'rule'=>array("in_array", array("true", "false")),
					'message'=>Language::_("Payflow.!error.test_mode.valid", true)
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
		return array("user", "vendor", "partner", "password");
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
	 * Used to determine whether this gateway can be configured for autodebiting accounts
	 *
	 * @return boolean True if the customer must be present (e.g. in the case of credit card customer must enter security code), false otherwise
	 */
	public function requiresCustomerPresent() {
		return false;
	}
	
	
	/**
	 * Charge a credit card
	 *
	 * @param array $card_info An array of credit card info including:
	 * 	-first_name The first name on the card
	 * 	-last_name The last name on the card
	 * 	-card_number The card number
	 * 	-card_exp The card expiration date in yyyymm format
	 * 	-card_security_code The 3 or 4 digit security code of the card (if available)
	 * 	-type The credit card type
	 * 	-address1 The address 1 line of the card holder
	 * 	-address2 The address 2 line of the card holder
	 * 	-city The city of the card holder
	 * 	-state An array of state info including:
	 * 		-code The 2 or 3-character state code
	 * 		-name The local name of the state
	 * 	-country An array of country info including:
	 * 		-alpha2 The 2-character country code
	 * 		-alpha3 The 3-character country code
	 * 		-name The english name of the country
	 * 		-alt_name The local name of the country
	 * 	-zip The zip/postal code of the card holder
	 * @param float $amount The amount to charge this card
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	-id The ID of the invoice being processed
	 * 	-amount The amount being processed for this invoice (which is included in $amount)
	 * @return array An array of transaction data including:
	 * 	-status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	-reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	-transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	-message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function processCc(array $card_info, $amount, array $invoice_amounts=null) {
		// Attempt to process this sale transaction
		return $this->processTransaction($this->getCcParams("S", null, $amount, $card_info));
	}
	
	/**
	 * Authorize a credit card
	 * 
	 * @param array $card_info An array of credit card info including:
	 * 	-first_name The first name on the card
	 * 	-last_name The last name on the card
	 * 	-card_number The card number
	 * 	-card_exp The card expiration date in yyyymm format
	 * 	-card_security_code The 3 or 4 digit security code of the card (if available)
	 * 	-type The credit card type
	 * 	-address1 The address 1 line of the card holder
	 * 	-address2 The address 2 line of the card holder
	 * 	-city The city of the card holder
	 * 	-state An array of state info including:
	 * 		-code The 2 or 3-character state code
	 * 		-name The local name of the country
	 * 	-country An array of country info including:
	 * 		-alpha2 The 2-character country code
	 * 		-alpha3 The 3-cahracter country code
	 * 		-name The english name of the country
	 * 		-alt_name The local name of the country
	 * 	-zip The zip/postal code of the card holder
	 * @param float $amount The amount to charge this card
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	-id The ID of the invoice being processed
	 * 	-amount The amount being processed for this invoice (which is included in $amount)
	 * @return array An array of transaction data including:
	 * 	-status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	-reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	-transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	-message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function authorizeCc(array $card_info, $amount, array $invoice_amounts=null) {
		// Authorize this transaction
		return $this->processTransaction($this->getCcParams("A", null, $amount, $card_info));
	}
	
	/**
	 * Capture the funds of a previously authorized credit card
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @param float $amount The amount to capture on this card
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	-id The ID of the invoice being processed
	 * 	-amount The amount being processed for this invoice (which is included in $amount)
	 * @return array An array of transaction data including:
	 * 	-status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	-reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	-transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	-message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function captureCc($reference_id, $transaction_id, $amount, array $invoice_amounts=null) {
		// Capture this payment transaction
		return $this->processTransaction($this->getCcParams("D", $transaction_id, $amount));
	}
	
	/**
	 * Void a credit card charge
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @return array An array of transaction data including:
	 * 	-status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	-reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	-transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	-message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function voidCc($reference_id, $transaction_id) {
		// Void this payment transaction
		$result = $this->processTransaction($this->getCcParams("V", $transaction_id));
		
		// An approved voided transaction should have a status of void
		if ($result['status'] == "approved")
			$result['status'] = "void";
		
		return $result;
	}
	
	/**
	 * Refund a credit card charge
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @param float $amount The amount to refund this card
	 * @return array An array of transaction data including:
	 * 	-status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	-reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	-transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	-message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refundCc($reference_id, $transaction_id, $amount) {
		// Refund this payment transaction
		$result = $this->processTransaction($this->getCcParams("C", $transaction_id, $amount));
		
		// An approved refunded transaction should have a status of refunded
		if ($result['status'] == "approved")
			$result['status'] = "refunded";
		
		return $result;
	}
	
	/**
	 * Sets the parameters for credit card transactions
	 *
	 * @param string $transaction_type The type of transaction to process ("S" = Sale, "C" = Credit, "A" = Authorization, "D" = Delayed Capture, "V" = Void)
	 * @param int $transaction_id The ID of a previous transaction if available
	 * @param float $amount The amount to charge this card
	 * @param array $card_info An array of credit card info including:
	 * 	-first_name The first name on the card
	 * 	-last_name The last name on the card
	 * 	-card_number The card number
	 * 	-card_exp The card expiration date in yyyymm format
	 * 	-card_security_code The 3 or 4 digit security code of the card (if available)
	 * 	-type The credit card type
	 * 	-address1 The address 1 line of the card holder
	 * 	-address2 The address 2 line of the card holder
	 * 	-city The city of the card holder
	 * 	-state An array of state info including:
	 * 		-code The 2 or 3-character state code
	 * 		-name The local name of the state
	 * 	-country An array of country info including:
	 * 		-alpha2 The 2-character country code
	 * 		-alpha3 The 3-character country code
	 * 		-name The english name of the country
	 * 		-alt_name The local name of the country
	 * 	-zip The zip/postal code of the card holder
	 * @return array A key=>value list of all transaction fields
	 */
	private function getCcParams($transaction_type, $transaction_id=null, $amount=null, array $card_info=null) {
		// Set required transaction fields
		$charge_params = array(
			'TENDER' => "C", // CC transaction
			'TRXTYPE' => $transaction_type
		);
		$charge_params = array_merge($this->getRequiredParams(), $charge_params);
		
		// Set additional transaction-type specific fields
		$params = array();
		switch ($transaction_type) {
			case "S": // Sale
			case "A": // Authorization
				// Card expiration date is in mmyy format
				$card_expiration = substr($this->ifSet($card_info['card_exp']), 4, 2) . substr($this->ifSet($card_info['card_exp']), 2, 2);
				
				$params = array(
					'ACCT' => $this->ifSet($card_info['card_number']),
					'EXPDATE' => $card_expiration,
					'AMT' => $amount,
					'CURRENCY' => $this->currency,
					'CVV2' => $this->ifSet($card_info['card_security_code']),
					'BILLTOFIRSTNAME' => $this->ifSet($card_info['first_name']),
					'BILLTOLASTNAME' => $this->ifSet($card_info['last_name']),
					'BILLTOSTREET' => $this->ifSet($card_info['address1']),
					'BILLTOCITY' => $this->ifSet($card_info['city']),
					'BILLTOSTATE' => $this->ifSet($card_info['state']['code']),
					'BILLTOZIP' => $this->ifSet($card_info['zip']),
					'BILLTOCOUNTRY' => $this->ifSet($card_info['country']['alpha2'])
				);
				break;
			case "C": // Credit/Refund
			case "D": // Delayed Capture
				$params = array('ORIGID' => $transaction_id, 'AMT' => $amount);
				break;
			case "V": // Void
				$params = array('ORIGID' => $transaction_id);
				break;
		}
		
		return array_merge($charge_params, $params);
	}
	
	/**
	 * Retrieves a list of the required fields for transactions
	 *
	 * @return array A list of key/value pairs representing parameters and their values
	 */
	private function getRequiredParams() {
		return array(
			'USER' => $this->ifSet($this->meta['user']),
			'VENDOR' => $this->ifSet($this->meta['vendor']),
			'PARTNER' => $this->ifSet($this->meta['partner']),
			'PWD' => $this->ifSet($this->meta['password'])
		);
	}
	
	/**
	 * Processes a transaction
	 *
	 * @param array $fields An array of key=>value pairs to process
	 * @return array A list of response key=>value pairs including:
	 * 	-status (approved, declined, or error)
	 * 	-reference_id
	 * 	-transaction_id
	 * 	-message
	 */
	private function processTransaction($fields) {
		
		// Load the HTTP component, if not already loaded
		if (!isset($this->Http)) {
			Loader::loadComponents($this, array("Net"));
			$this->Http = $this->Net->create("Http");
		}
		
		// Submit the request
		$url = ($this->ifSet($this->meta['test_mode'], "true") == "false" ? $this->live_url : $this->test_url);
		$response = $this->Http->post($url, $this->buildRequest($fields));
		
		// Parse the response
		$response = $this->parseResponse($response);
		
		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($fields, $response, $url);
		
		// Set the transaction status
		$status = "error";
		if (isset($response['RESULT'])) {
			if ($response['RESULT'] == "0")
				$status = "approved";
			elseif ($response['RESULT'] == "12")
				$status = "declined";
		}
		
		// Set general error if status is error
		if ($status == "error")
			$this->Input->setErrors($this->getCommonError("general"));
		
		return array(
			'status' => $status,
			'reference_id' => null,
			'transaction_id' => $this->ifSet($response['PNREF']),
			'message' => $this->ifSet($response['RESPMSG'])
		);
	}
	
	/**
	 * Builds the transaction request given key/value pairs
	 *
	 * @param array A list of key/value pairs to pass to the transaction
	 * @return string A formatted transaction request
	 */
	private function buildRequest($fields) {
		// Create a list of name/value pairs in the format NAME[5]=value&NAME2[6]=value2
		
		$request = "";
		$i = 0;
		foreach ($fields as $key=>$val) {
			// No quotes may exist in the data
			$name = str_replace("\"", "", $key);
			$value = str_replace("\"", "", $val);
			
			$request .= ($i++ > 0 ? "&" : "") . $name . "[" . strlen(utf8_decode($value)) . "]" . "=" . $value;
		}
		
		return $request;
	}
	
	/**
	 * Parses the response from the gateway into an associative array
	 *
	 * @param string $response The response from the gateway
	 * @param array A list of key/value pairs representing the response from the gateway
	 */
	private function parseResponse($response) {
		// Split the response
		$response = explode("&", $response);
		
		$result = array();
		// Split keys/values
		foreach ($response as $value) {
			$values = explode("=", $value, 2);
			
			// Save the key/value pair
			if ($values)
				$result[$this->ifSet($values[0])] = $this->ifSet($values[1]);
		}
		
		return $result;
	}
	
	/**
	 * Log the request
	 *
	 * @param array The input parameters sent to the gateway
	 * @param array The response from the gateway
	 * @param string $url The URL of the request was sent to
	 */
	private function logRequest($params, $response, $url) {
		$mask_fields = array(
			'USER',
			'VENDOR',
			'PARTNER',
			'PWD',
			'ACCT', // CC number
			'CVV2',
			'EXPDATE'
		);
		
		// Success is 0, Declined is 12, anything else is separate error
		$success = false;
		if ($this->ifSet($response['RESULT']) == "0")
			$success = true;
		
		// Log data sent to the gateway
		$this->log($url, serialize($this->maskData($params, $mask_fields)), "input", true);
		
		// Log response from the gateway
		$this->log($url, serialize($this->maskData($response, $mask_fields)), "output", $success);
	}
}
?>