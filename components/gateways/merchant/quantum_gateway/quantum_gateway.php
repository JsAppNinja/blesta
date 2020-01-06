<?php
/**
 * QuantumGateway Credit Card processing gateway. Supports onsite
 * payment processing for Credit Cards and ACH.
 *
 * The QuantumGateway API can be found at: http://www.quantumgateway.com/files/QGW-Non-Interactive_API.pdf
 *
 * @package blesta
 * @subpackage blesta.components.gateways.quantum_gateway
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @notes See your QuantumGateway control panel for test mode options
 */
class QuantumGateway extends MerchantGateway implements MerchantCc, MerchantAch {
	/**
	 * @var string The version of this gateway
	 */
	private static $version = "1.0.1";
	/**
	 * @var string The authors of this gateway
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	/**
	 * @var array An array of meta data for this gateway
	 */
	private $meta;
	/**
	 * @var string The base URL of API requests
	 */
	private $base_url = "https://secure.quantumgateway.com/cgi/tqgwdbe.php";
	/**
	 * @var char The response separator
	 */
	private $delimiter = "|";
	
	/**
	 * Construct a new merchant gateway
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("quantum_gateway", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this gateway
	 *
	 * @return string The common name of this gateway
	 */
	public function getName() {
		return Language::_("Quantum_gateway.name", true);
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
		return array("USD");
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
		$this->view->setDefaultView("components" . DS . "gateways" . DS . "merchant" . DS . "quantum_gateway" . DS);
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
			'gateway_login'=>array(
				'empty'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Quantum_gateway.!error.gateway_login.empty", true)
				)
			),
			'restrict_key'=>array(
				'empty'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Quantum_gateway.!error.restrict_key.empty", true)
				)
			),
			'maxmind'=>array(
				'valid'=>array(
					'if_set'=>true,
					'rule'=>array("in_array", array("true", "false")),
					'message'=>Language::_("Quantum_gateway.!error.maxmind.valid", true)
				)
			)
		);
		
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
		return array("gateway_login", "restrict_key");
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
	 * 	- first_name The first name on the card
	 * 	- last_name The last name on the card
	 * 	- card_number The card number
	 * 	- card_exp The card expiration date in yyyymm format
	 * 	- card_security_code The 3 or 4 digit security code of the card (if available)
	 * 	- type The credit card type
	 * 	- address1 The address 1 line of the card holder
	 * 	- address2 The address 2 line of the card holder
	 * 	- city The city of the card holder
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the state
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-character country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the card holder
	 * @param float $amount The amount to charge this card
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	- id The ID of the invoice being processed
	 * 	- amount The amount being processed for this invoice (which is included in $amount)
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function processCc(array $card_info, $amount, array $invoice_amounts=null) {
		// Attempt to process this sale transaction
		$transaction = $this->processTransaction($this->getCcParams("SALES", null, $amount, $card_info));
		
		// Save the last 4 of the CC number (for potential use with refunds)
		$transaction['reference_id'] = substr($this->ifSet($card_info['card_number']), -4);
		
		return $transaction;
	}
	
	/**
	 * Authorize a credit card
	 * 
	 * @param array $card_info An array of credit card info including:
	 * 	- first_name The first name on the card
	 * 	- last_name The last name on the card
	 * 	- card_number The card number
	 * 	- card_exp The card expiration date in yyyymm format
	 * 	- card_security_code The 3 or 4 digit security code of the card (if available)
	 * 	- type The credit card type
	 * 	- address1 The address 1 line of the card holder
	 * 	- address2 The address 2 line of the card holder
	 * 	- city The city of the card holder
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the country
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-cahracter country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the card holder
	 * @param float $amount The amount to charge this card
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	- id The ID of the invoice being processed
	 * 	- amount The amount being processed for this invoice (which is included in $amount)
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function authorizeCc(array $card_info, $amount, array $invoice_amounts=null) {
		// Authorize this transaction
		$transaction = $this->processTransaction($this->getCcParams("AUTH_ONLY", null, $amount, $card_info));
		
		// Save the last 4 of the CC number (for potential use with refunds)
		$transaction['reference_id'] = substr($this->ifSet($card_info['card_number']), -4);
		
		return $transaction;
	}
	
	/**
	 * Capture the funds of a previously authorized credit card
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @param float $amount The amount to capture on this card
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	- id The ID of the invoice being processed
	 * 	- amount The amount being processed for this invoice (which is included in $amount)
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function captureCc($reference_id, $transaction_id, $amount, array $invoice_amounts=null) {
		// Capture this payment transaction
		$transaction = $this->processTransaction($this->getCcParams("AUTH_CAPTURE", $transaction_id, $amount));
		
		// Keep the same reference ID as used with the authorize
		$transaction['reference_id'] = $reference_id;
		
		return $transaction;
	}
	
	/**
	 * Void a credit card charge
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function voidCc($reference_id, $transaction_id) {
		// Void this payment transaction
		$result = $this->processTransaction($this->getCcParams("VOID", $transaction_id));
		
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
	 * 	- status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refundCc($reference_id, $transaction_id, $amount) {
		// Set the last4 of the CC (reference_id) required for a refund
		$params = array_merge(array('ccnum'=>$reference_id), $this->getCcParams("RETURN", $transaction_id, $amount));
		
		// Refund this payment transaction
		$result = $this->processTransaction($params);
		
		// An approved refunded transaction should have a status of refunded
		if ($result['status'] == "approved")
			$result['status'] = "refunded";
		
		return $result;
	}
	
	/**
	 * Sets the parameters for credit card transactions
	 *
	 * @param string $transaction_type The type of transaction to process (SALE, AUTH, REFUND, CAPTURE, VOID, UPDATE, CREDIT, AGG)
	 * @param int $transaction_id The ID of a previous transaction if available
	 * @param float $amount The amount to charge this card
	 * @param array $card_info An array of credit card info including:
	 * 	- first_name The first name on the card
	 * 	- last_name The last name on the card
	 * 	- card_number The card number
	 * 	- card_exp The card expiration date in yyyymm format
	 * 	- card_security_code The 3 or 4 digit security code of the card (if available)
	 * 	- type The credit card type
	 * 	- address1 The address 1 line of the card holder
	 * 	- address2 The address 2 line of the card holder
	 * 	- city The city of the card holder
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the state
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-character country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the card holder
	 * @return array A key=>value list of all transaction fields
	 */
	private function getCcParams($transaction_type, $transaction_id=null, $amount=null, array $card_info=null) {
		// Set required transaction fields
		$charge_params = $this->getRequiredParams("CC", $transaction_type);
		
		switch ($transaction_type) {
			case "SALES":
			case "AUTH_ONLY":
				$params = array(
					'ccnum' => $this->ifSet($card_info['card_number']),
					'ccmo' => substr($this->ifSet($card_info['card_exp']), 4, 2),
					'ccyr' => substr($this->ifSet($card_info['card_exp']), 2, 2),
					'BADDR1' => $this->ifSet($card_info['address1']),
					'BZIP1' => $this->ifSet($card_info['zip']),
					'BNAME' => $this->ifSet($card_info['first_name']) . " " . $this->ifSet($card_info['last_name']),
					'CVVtype' => "0", // Not passing CVV2
					'amount' => $amount
				);
				break;
			case "AUTH_CAPTURE":
			case "RETURN":
				$params = array('transID' => $transaction_id, 'amount' => $amount);
				break;
			case "VOID":
				$params = array('transID' => $transaction_id);
				break;
		}
		
		return array_merge($charge_params, $params);
	}
	
	/**
	 * Sets the parameters for ACH transactions
	 *
	 * @param string $transaction_type The type of transaction to process (SALE, AUTH, REFUND, CAPTURE, VOID, UPDATE, CREDIT, AGG)
	 * @param int $transaction_id The ID of a previous transaction if available
	 * @param float $amount The amount to charge this card
	 * @param array $account_info An array of bank account info including:
	 * 	- first_name The first name on the account
	 * 	- last_name The last name on the account
	 * 	- account_number The bank account number
	 * 	- routing_number The bank account routing number
	 * 	- type The bank account type (checking or savings)
	 * 	- address1 The address 1 line of the card holder
	 * 	- address2 The address 2 line of the card holder
	 * 	- city The city of the card holder
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the country
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-character country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the account holder
	 * @return array A key=>value list of all transaction fields
	 */
	private function getAchParams($transaction_type, $transaction_id=null, $amount=null, array $account_info=null) {
		// Set required transaction fields
		$charge_params = $this->getRequiredParams("EFT", $transaction_type);
		
		// Set additional transaction-type specific fields
		$params = array();
		switch ($transaction_type) {
			case "SALES":
				$params = array(
					'aba' => $this->ifSet($account_info['routing_number']),
					'checkacct' => $this->ifSet($account_info['account_number']),
					'BADDR1' => $this->ifSet($account_info['address1']),
					'BZIP1' => $this->ifSet($account_info['zip']),
					'BNAME' => $this->ifSet($account_info['first_name']) . " " . $this->ifSet($account_info['last_name']),
					'amount' => $amount
				);
				break;
			case "RETURN":
				$params = array('transID' => $transaction_id, 'amount' => $amount);
				break;
			case "VOID":
				$params = array('transID' => $transaction_id);
				break;
		}
		
		return array_merge($charge_params, $params);
	}
	
	/**
	 * Retrieves a list of required fields shared by CC and ACH transactions
	 *
	 * @param string $payment_type The payment type of this transaction (CC or EFT)
	 * @param string $transaction_type The type of transaction to process (CREDIT, SALES, AUTH_CAPTURE, AUTH_ONLY, RETURN, VOID, PREVIOUS_SALE)
	 * @return array A list of key=>value pairs representing the required transaction parameters
	 */
	private function getRequiredParams($payment_type, $transaction_type) {
		// Set required and default transaction fields
		return array(
			'gwlogin' => $this->ifSet($this->meta['gateway_login']),
			'RestrictKey' => $this->ifSet($this->meta['restrict_key']),
			'trans_type' => $transaction_type,
			'trans_method' => $payment_type,
			'override_email_customer' => "N", // Don't send customer an email
			'override_trans_email' => "N", // Don't send customer an email
			'Dsep' => $this->delimiter,
			'MAXMIND' => ($this->ifSet($this->meta['maxmind'], "false") == "true" ? "1" : "2") // 1 to use maxmind, 2 to not
		);
	}
	
	/**
	 * Process an ACH transaction
	 *
	 * @param array $account_info An array of bank account info including:
	 * 	- first_name The first name on the account
	 * 	- last_name The last name on the account
	 * 	- account_number The bank account number
	 * 	- routing_number The bank account routing number
	 * 	- type The bank account type (checking or savings)
	 * 	- address1 The address 1 line of the card holder
	 * 	- address2 The address 2 line of the card holder
	 * 	- city The city of the card holder
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the country
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-character country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the account holder
	 * @param float $amount The amount to debit this account
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	- id The ID of the invoice being processed
	 * 	- amount The amount being processed for this invoice (which is included in $amount)
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function processAch(array $account_info, $amount, array $invoice_amounts=null) {
		// Attempt to process this sale transaction
		return $this->processTransaction($this->getAchParams("SALES", null, $amount, $account_info));
	}
	
	/**
	 * Void an ACH transaction
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function voidAch($reference_id, $transaction_id) {
		// Attempt to void this transaction
		$result = $this->processTransaction($this->getAchParams("VOID", $transaction_id));
		
		// An approved voided transaction should have a status of void
		if ($result['status'] == "approved")
			$result['status'] = "void";
		
		return $result;
	}
	
	/**
	 * Refund an ACH transaction
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @param float $amount The amount to refund this account
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refundAch($reference_id, $transaction_id, $amount) {
		// Gateway does not support this action
		$this->Input->setErrors($this->getCommonError("unsupported"));
	}
	
	/**
	 * Processes a transaction
	 *
	 * @param array $fields An array of key=>value pairs to process
	 * @return array A list of response key=>value pairs including:
	 * 	- status (approved, declined, or error)
	 * 	- reference_id
	 * 	- transaction_id
	 * 	- message
	 */
	private function processTransaction($fields) {
		
		// Load the HTTP component, if not already loaded
		if (!isset($this->Http)) {
			Loader::loadComponents($this, array("Net"));
			$this->Http = $this->Net->create("Http");
		}
		
		// Submit the request
		$response = $this->Http->post($this->base_url, http_build_query($fields));
		
		// Parse the response
		$response = $this->parseResponse($response);
		
		// Log the transaction (with the parsed response)
		$this->logRequest($fields, $response);
		
		// Set the status
		$status = "error";
		if ($response['status'] == "APPROVED")
			$status = "approved";
		if ($response['status'] == "DECLINED")
			$status = "declined";
		
		// Set an error, if any
		if ($status == "error")
			$this->Input->setErrors($this->getCommonError("general"));
		
		return array(
			'status' => $status,
			'reference_id' => null,
			'transaction_id' => $this->ifSet($response['transaction_id']),
			'message' => $this->ifSet($output['decline_reason'])
		);
	}
	
	/**
	 * Log the request
	 *
	 * @param array The input parameters sent to the gateway
	 * @param array The response from the gateway
	 */
	private function logRequest($params, $response) {
		// Mask any specific fields
		$mask_fields = array(
			'gwlogin',
			'RestrictKey',
			'ccnum', // CC number
			'ccmo', // CC expiration month
			'ccyr', // CC expiration year
			'aba', // routing number
			'checkacct', // checking account number
			'CVV2' // CVV2 (not used)
		);
		
		// Determine success/failure (APPROVED, DECLINED)
		$success = false;
		if ($this->ifSet($response['status']) == "APPROVED")
			$success = true;
		
		// Log data sent to the gateway
		$this->log($this->base_url, serialize($this->maskData($params, $mask_fields)), "input", true);
		
		// Log response from the gateway
		$this->log($this->base_url, serialize($this->maskData($response, $mask_fields)), "output", $success);
	}
	
	/**
	 * Parse the response and return an associative array containing the key=>value pairs
	 *
	 * @param string $response The response from the gateway
	 * @return array An array of key=>value pairs representing the sample response values
	 */
	private function parseResponse($response) {
		$output = explode($this->delimiter, $response);
		
		// Remove quotes
		foreach ($output as &$value) {
			$value = str_replace("\"", "", trim($value));
		}
		
		// These fields are expected responses
		$result = array(
			'status' => $this->ifSet($output[0]),
			'auth_code' => $this->ifSet($output[1]),
			'transaction_id' => $this->ifSet($output[2]),
			'avr_response' => $this->ifSet($output[3]),
			'cvv_response' => $this->ifSet($output[4]),
			'max_score' => $this->ifSet($output[5])
		);
		
		// Optional responses
		$optional_results = array();
		if ($this->ifSet($output[6], false))
			$optional_results['decline_reason'] = $output[6];
		if ($this->ifSet($result[7], false))
			$optional_results['error_code'] = $output[7];
		
		return array_merge($result, $optional_results);
	}
}
?>