<?php
/**
 * PayJunction Credit Card & ACH processing gateway
 *
 * @package blesta
 * @subpackage blesta.components.gateways.merchant.payjunction
 * @author Phillips Data, Inc.
 * @author Nirays Technologies
 * @copyright Copyright (c) 2014, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://nirays.com/ Nirays Technologies
 */
class Payjunction extends MerchantGateway implements MerchantCc, MerchantAch {
	/**
	 * @var string The version of this gateway
	 */
	private static $version = "1.0.2";
	/**
	 * @var string The authors of this gateway
	 */
	private static $authors = array(
		array('name' => "Phillips Data, Inc.", 'url' => "http://www.blesta.com"),
		array('name' => "Nirays Technologies", 'url' => "http://www.nirays.com")
	);
	/**
	 * @var array An array of meta data for this gateway
	 */
	private $meta;
	/**
	 * Construct PayJunction merchant gateway
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("payjunction", null, dirname(__FILE__) . DS . "language" . DS);		
	}

	/**
	 * Returns the name of this gateway
	 *
	 * @return string The common name of this gateway
	 */
	public function getName() {
		return Language::_("PayJunction.name", true);
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
			'api_key'=>array(
				'empty'=>array(
					'rule'=>array("isEmpty"),
					'negate'=>true,
					'message'=>Language::_("PayJunction.!error.api_key.empty", true)
				)
			),
			'user_name'=>array(
				'empty'=>array(
					'rule'=>array("isEmpty"),
					'negate'=>true,
					'message'=>Language::_("PayJunction.!error.user_name.empty", true)
				)
			),
			'password'=>array(
				'empty'=>array(
					'rule'=>array("isEmpty"),
					'negate'=>true,
					'message'=>Language::_("PayJunction.!error.password.empty", true)
				)
			),
			'test_mode'=>array(
				'valid'=>array(
					'if_set'=>true,
					'rule'=>array("in_array", array("true", "false")),
					'message'=>Language::_("PayJunction.!error.test_mode.valid", true)
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
		return array("api_key","user_name","password");
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

		//Load PayJunction API
		$this->loadApi();			

		// Attempt to process this sale transaction
		$params = $this->getCcParams("SALES", null, $amount, $card_info);

		// Process the transaction and get the response		
		$response = $this->parseResponse($this->PayJunctionApi->processTransaction($params));	

		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($this->PayJunctionApi->getUrl(), $params, $response);

		return $this->returnResult($this->getStatus($response), $response);
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
		//Load PayJunction API
		$this->loadApi();

		// Authorize this transaction
		$params = $this->getCcParams("AUTH_ONLY", null, $amount, $card_info);
		$params['status'] = "HOLD";
		
		// Process the transaction and get the response		
		$response = $this->parseResponse($this->PayJunctionApi->processTransaction($params));

		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($this->PayJunctionApi->getUrl(), $params, $response);

		return $this->returnResult($this->getStatus($response), $response);
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

		//Load PayJunction API
		$this->loadApi();

		// Capture this payment transaction
		$params = $this->getCcParams("CAPTURE", $transaction_id, $amount);	

		// Process the transaction and get the response		
		$response = $this->parseResponse($this->PayJunctionApi->processTransaction($params, $transaction_id, "PUT"));	

		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($this->PayJunctionApi->getUrl(), $params, $response);

		return $this->returnResult($this->getStatus($response), $response);
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

		//Load PayJunction API
		$this->loadApi();

		// Void this payment transaction
		$params = $this->getCcParams("VOID");

		// Process the transaction and get the response		
		$response = $this->parseResponse($this->PayJunctionApi->processTransaction($params, $transaction_id, "PUT"));	

		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($this->PayJunctionApi->getUrl(), $params, $response);	

		return $this->returnResult($this->getStatus($response), $response, "void");
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

		//Load PayJunction API
		$this->loadApi();

		// Refund this payment transaction			
		$params = $this->getCcParams("REFUND", $transaction_id, $amount);		
		
		// Process the transaction and get the response		
		$response = $this->parseResponse($this->PayJunctionApi->processTransaction($params));

		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($this->PayJunctionApi->getUrl(), $params, $response);		

		// Return array 		
		return $this->returnResult($this->getStatus($response), $response, "refund");
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

		//Load PayJunction API
		$this->loadApi();

		// Set required transaction fields		
		switch ($transaction_type) {
			case "SALES":
			case "AUTH_ONLY":				
				$params = array(															
					'cardNumber' => $this->ifSet($card_info['card_number']),
					'cardExpMonth' => substr($this->ifSet($card_info['card_exp']), 4, 2),
					'cardExpYear' => substr($this->ifSet($card_info['card_exp']), 2, 2),
					'billingAddress' => $this->ifSet($card_info['address1']),
					'billingCountry' =>  $this->ifSet($card_info['country']['alpha2']),
					'billingState' => $this->ifSet($card_info['state']['code']),
					'billingZip' => $this->ifSet($card_info['zip']),
					'billingFirstName' => $this->ifSet($card_info['first_name']),
					'billingLastName' => $this->ifSet($card_info['last_name']),
					'cardCvv' => $this->ifSet($card_info['card_security_code']),
					'amountBase' => $amount,
					'action' => "CHARGE"
				);
				break;
			case "REFUND":
				$params = array('action' => "REFUND", 'amountBase' =>$amount, 'transactionId' =>$transaction_id);
				break;
			case "CAPTURE":	
				$params = array('status' => "CAPTURE", 'amountBase' => $amount);
				break;
			case "VOID":			
				$params = array('status' => "VOID");
				break;
		}		
		return $params;
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

		//Load PayJunction API
		$this->loadApi();

		// Set transaction-type specific fields
		$params = array();
		switch ($transaction_type) {
			case "SALES":
				$params = array(									
					'achRoutingNumber' => $this->ifSet($account_info['routing_number']),
					'achAccountNumber' => $this->ifSet($account_info['account_number']) ,
					'achAccountType' => $this->ifSet($account_info['type']),
					'achType' => 'PPD',
					'billingAddress' => $this->ifSet($account_info['address1']),
					'billingCountry' =>  $this->ifSet($account_info['country']['alpha2']),
					'billingState' => $this->ifSet($account_info['state']['code']),
					'billingZip' => $this->ifSet($account_info['zip']),
					'billingFirstName' => $this->ifSet($account_info['first_name']),
					'billingLastName' => $this->ifSet($account_info['last_name']),
					'amountBase' => $amount,
					'action' => "CHARGE"
				);
				break;
			case "REFUND":
				$params = array('action' => $transaction_type, 'amountBase' =>$amount, 'transactionId' =>$transaction_id);
				break;
			case "VOID":
				$params = array('status' => "VOID");
				break;
		}		
		return $params;
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
		
		//Load PayJunction API
		$this->loadApi();

		// Attempt to process this sale transaction
		$params = $this->getAchParams("SALES", null, $amount, $account_info);

		// Process the transaction and get the response		
		$response = $this->parseResponse($this->PayJunctionApi->processTransaction($params));

		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($this->PayJunctionApi->getUrl(), $params, $this->ifSet($response));

		return $this->returnResult($this->getStatus($response), $response);
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

		//Load PayJunction API
		$this->loadApi();

		// Void this payment transaction
		$params = $this->getCcParams("VOID");

		// Process the transaction and get the response		
		$response = $this->parseResponse($this->PayJunctionApi->processTransaction($params, $transaction_id, "PUT"));		

		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($this->PayJunctionApi->getUrl(), $params, $this->ifSet($response));
		
		return $this->returnResult($this->getStatus($response), $response, "void");
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

		//Load PayJunction API
		$this->loadApi();

		// Refund this payment transaction			
		$params = $this->getCcParams("REFUND", $transaction_id, $amount);		
		
		// Process the transaction and get the response		
		$response = $this->parseResponse($this->PayJunctionApi->processTransaction($params));	

		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($this->PayJunctionApi->getUrl(), $params, $this->ifSet($response));	
		
		return $this->returnResult($this->getStatus($response), $response, "refund");
	}
	
	/**
	 * Return the result after processing the transaction for Blesta
	 *
	 * @param string $status A string showing status of transaction
	 * @param array $response An array containing parsed response
	 * @param string $type A string showing type of transaction
	 * @return array A list of response key=>value pairs including:
	 * 	- status (approved, declined, refunded, void or error)
	 * 	- reference_id
	 * 	- transaction_id
	 * 	- message
	 */
	private function returnResult($status, $response, $type=null) {

		switch ($type) {
			case 'void':
				if($status == "approved")
					$status = 'void';
				break;
			case 'refund':
				if($status == "approved")
					$status = 'refunded';
				break;			
		}

		// Return array 
		return array(
			'status' => $status,
			'reference_id' => null,
			'transaction_id' => $this->ifSet($response['transactionId']),
			'message' => $this->ifSet($response['response']['message'])
		);
	}
	
	/**
	 * Log the request
	 *
	 * @param string $url The url to which request is sent
	 * @param array $params The request parameters send to the gateway
	 * @param array  $response The response from the gateway
	 */
	private function logRequest($url,$params, $response) {

		// Mask any specific fields
		$mask_fields = array(
			'achRoutingNumber',
			'achAccountNumber',
			'cardNumber', // CC number
			'cardExpMonth', // CC expiration month
			'cardExpYear', // CC expiration year	
			'cardCvv', // CVV2 (not used)
			'lastFour'
		);
		
		// Determine success/failure (APPROVED, DECLINED)
		$success = false;
		if ($this->ifSet($response['response']['approved']) == true)
			$success = true;
		
		// Log data sent to the gateway
		$this->log($url, serialize($this->maskData($params, $mask_fields)), "input", true);
		
		// Log response from the gateway
		$this->log($url, serialize($this->maskDataRecursive($response, $mask_fields)), "output", $success);
	}

	/**
	 * Loads the PayJunction API
	 *
	 */
	private function loadApi() {

		//Load the files required		
		Loader::load(dirname(__FILE__) . DS . "api" . DS . "payjunction_api.php");
		$this->PayJunctionApi = new PayJunctionApi($this->meta['user_name'], $this->meta['password'], $this->meta['api_key'] , $this->meta['test_mode']);			
	}

	/**
	 * Set the status of the transaction
	 *
	 * @param array $response The response from gateway
	 * @return string $status The status of the response - approved, declined
	 */
	private function getStatus($response){
		$status = "error";
		if (isset($response['response']['approved'])) {
			$status = ($response['response']['approved'] ? "approved" : "declined");
			
			// Authorized, but not yet captured
			if ($response['response']['approved'] && isset($response['status']) && strtoupper($response['status']) == "HOLD")
				$status = "pending";
		}
		return $status;	
	}

	/**
	 * Parses the response 	
	 *
	 * @param string $output The JSON response obtained from the gateway
	 * @return array $response The parsed response 
	 */
	private function parseResponse($output){	

		$response = '';	

		//Parse the json string	
		if($output)
		$response = json_decode($output,true);	

		$status = $this->getStatus($response);
		
		// Set general error if status is error
		if ($status=='error'){			
			$this->Input->setErrors($this->getCommonError("general"));		
		}
		return $response;				
	}
}
?>