<?php
/**
 * BluePay Credit Card processing gateway. Supports onsite
 * payment processing for Credit Cards and ACH.
 *
 * The BluePay API can be found at: https://secure.assurebuy.com/BluePay/BluePay_bp20post/Bluepay20post.txt
 *
 * @package blesta
 * @subpackage blesta.components.gateways.blue_pay
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class BluePay extends MerchantGateway implements MerchantCc, MerchantAch {
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
	private $base_url = "https://secure.bluepay.com/interfaces/bp20post";
	
	/**
	 * Construct a new merchant gateway
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("blue_pay", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this gateway
	 *
	 * @return string The common name of this gateway
	 */
	public function getName() {
		return Language::_("Blue_pay.name", true);
	}
	
	/**
	 * Returns the URL to the signup page for this gateway.
	 *
	 * @return string The URL to the signup page if one exists, null otherwise
	 */
	public function getSignupUrl() {
		return "http://www.bluepay.com/blesta-partner-page";
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
		$this->view->setDefaultView("components" . DS . "gateways" . DS . "merchant" . DS . "blue_pay" . DS);
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
			'account_id'=>array(
				'empty'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Blue_pay.!error.account_id.empty", true)
				)
			),
			'secret_key'=>array(
				'empty'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Blue_pay.!error.secret_key.empty", true)
				)
			),
			'test_mode'=>array(
				'valid'=>array(
					'if_set'=>true,
					'rule'=>array("in_array", array("true", "false")),
					'message'=>Language::_("Blue_pay.!error.test_mode.valid", true)
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
		return array("account_id", "secret_key");
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
		return $this->processTransaction($this->getCcParams("SALE", null, $amount, $card_info));
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
		return $this->processTransaction($this->getCcParams("AUTH", null, $amount, $card_info));
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
		return $this->processTransaction($this->getCcParams("CAPTURE", $transaction_id, $amount));
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
	 * 	-status The status of the transaction (approved, declined, void, pending, error, refunded, returned)
	 * 	-reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	-transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	-message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refundCc($reference_id, $transaction_id, $amount) {
		// Refund this payment transaction
		$result = $this->processTransaction($this->getCcParams("REFUND", $transaction_id, $amount));
		
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
		// Retrieve the tamper proof seal
		$tps = $this->getTps($transaction_type, $transaction_id, $amount, $this->ifSet($card_info['first_name']), $this->ifSet($card_info['card_number']));
		$tps_def = $tps['tps_def'];
		$tps = $tps['tps'];
		
		// Set required transaction fields
		$charge_params = $this->getRequiredParams("CREDIT", $transaction_type, $tps, $tps_def);
		
		// Set additional transaction-type specific fields
		$params = array();
		switch ($transaction_type) {
			case "SALE":
			case "AUTH":
				// Card expiration date is in mmyy format
				$card_expiration = substr($this->ifSet($card_info['card_exp']), 4, 2) . substr($this->ifSet($card_info['card_exp']), 2, 2);
				
				$params = array(
					'AMOUNT' => $amount,
					'PAYMENT_ACCOUNT' => $this->ifSet($card_info['card_number']),
					'CARD_CVV2' => $this->ifSet($card_info['card_security_code']),
					'CARD_EXPIRE' => $card_expiration,
					'NAME1' => $this->ifSet($card_info['first_name']),
					'NAME2' => $this->ifSet($card_info['last_name']),
					'ADDR1' => $this->ifSet($card_info['address1']),
					'ADDR2' => $this->ifSet($card_info['address2']),
					'CITY' => $this->ifSet($card_info['city']),
					'STATE' => $this->ifSet($card_info['state']['code']),
					'COUNTRY' => $this->ifSet($card_info['country']['alpha2']),
					'ZIP' => $this->ifSet($card_info['zip']),
					'DUPLICATE_OVERRIDE' => "1" // ignore what BluePay assumes are duplicates (they are very likely not)
				);
				break;
			case "REFUND":
			case "CAPTURE":
				$params = array('MASTER_ID' => $transaction_id, 'AMOUNT' => $amount);
				break;
			case "VOID":
				$params = array('MASTER_ID' => $transaction_id);
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
	 * 	-first_name The first name on the account
	 * 	-last_name The last name on the account
	 * 	-account_number The bank account number
	 * 	-routing_number The bank account routing number
	 * 	-type The bank account type (checking or savings)
	 * 	-address1 The address 1 line of the card holder
	 * 	-address2 The address 2 line of the card holder
	 * 	-city The city of the card holder
	 * 	-state An array of state info including:
	 * 		-code The 2 or 3-character state code
	 * 		-name The local name of the country
	 * 	-country An array of country info including:
	 * 		-alpha2 The 2-character country code
	 * 		-alpha3 The 3-character country code
	 * 		-name The english name of the country
	 * 		-alt_name The local name of the country
	 * 	-zip The zip/postal code of the account holder
	 * @return array A key=>value list of all transaction fields
	 */
	private function getAchParams($transaction_type, $transaction_id=null, $amount=null, array $account_info=null) {
		// Set the payment account for this ACH
		$payment_account = null;
		if ($account_info != null)
			$payment_account = ($this->ifSet($account_info['type'], "savings") == "checking" ? "C" : "S") . ":" . $this->ifSet($account_info['routing_number']) . ":" . $this->ifSet($account_info['account_number']);
		
		// Retrieve the tamper proof seal
		$tps = $this->getTps($transaction_type, $transaction_id, $amount, $this->ifSet($account_info['first_name']), $payment_account);
		$tps_def = $tps['tps_def'];
		$tps = $tps['tps'];
		
		// Set required transaction fields
		$charge_params = $this->getRequiredParams("ACH", $transaction_type, $tps, $tps_def);
		
		// Set additional transaction-type specific fields
		switch ($transaction_type) {
			case "SALE":
				$params = array(
					'AMOUNT' => $amount,
					'PAYMENT_ACCOUNT' => $payment_account,
					'DOC_TYPE' => "WEB", // the documentation for this ACH transaction. (PPD, CCD, TEL, WEB, ARC)
					'NAME1' => $this->ifSet($account_info['first_name']),
					'NAME2' => $this->ifSet($account_info['last_name']),
					'ADDR1' => $this->ifSet($account_info['address1']),
					'ADDR2' => $this->ifSet($account_info['address2']),
					'CITY' => $this->ifSet($account_info['city']),
					'STATE' => $this->ifSet($account_info['state']['code']),
					'COUNTRY' => $this->ifSet($account_info['country']['alpha2']),
					'ZIP' => $this->ifSet($account_info['zip'])
				);
				break;
			case "REFUND":
				$params = array('MASTER_ID' => $transaction_id, 'AMOUNT' => $amount);
				break;
			case "VOID":
				$params = array('MASTER_ID' => $transaction_id);
				break;
		}
		
		return array_merge($charge_params, $params);
	}
	
	/**
	 * Retrieves a list of required fields shared by CC and ACH transactions
	 *
	 * @param string $payment_type The payment type of this transaction (CREDIT or ACH)
	 * @param string $transaction_type The type of transaction to process (SALE, AUTH, REFUND, CAPTURE, VOID, UPDATE, CREDIT, AGG)
	 * @param string $tps The tamper proof seal
	 * @param string $tps_def The fields used in the construction of the tamper proof seal
	 * @return array A list of key=>value pairs representing the required transaction parameters
	 */
	private function getRequiredParams($payment_type, $transaction_type, $tps, $tps_def) {
		// Set required transaction fields
		return array(
			'ACCOUNT_ID' => $this->ifSet($this->meta['account_id']),
			'TPS_DEF' => $tps_def,
			'TAMPER_PROOF_SEAL' => $tps,
			'TRANS_TYPE' => $transaction_type,
			'PAYMENT_TYPE' => $payment_type,
			'MODE' => ($this->ifSet($this->meta['test_mode'], "true") == "false" ? "LIVE" : "TEST")
		);
	}
	
	/**
	 * Process an ACH transaction
	 *
	 * @param array $account_info An array of bank account info including:
	 * 	-first_name The first name on the account
	 * 	-last_name The last name on the account
	 * 	-account_number The bank account number
	 * 	-routing_number The bank account routing number
	 * 	-type The bank account type (checking or savings)
	 * 	-address1 The address 1 line of the card holder
	 * 	-address2 The address 2 line of the card holder
	 * 	-city The city of the card holder
	 * 	-state An array of state info including:
	 * 		-code The 2 or 3-character state code
	 * 		-name The local name of the country
	 * 	-country An array of country info including:
	 * 		-alpha2 The 2-character country code
	 * 		-alpha3 The 3-character country code
	 * 		-name The english name of the country
	 * 		-alt_name The local name of the country
	 * 	-zip The zip/postal code of the account holder
	 * @param float $amount The amount to debit this account
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	-id The ID of the invoice being processed
	 * 	-amount The amount being processed for this invoice (which is included in $amount)
	 * @return array An array of transaction data including:
	 * 	-status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	-reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	-transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	-message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function processAch(array $account_info, $amount, array $invoice_amounts=null) {
		// Attempt to process this sale transaction
		return $this->processTransaction($this->getAchParams("SALE", null, $amount, $account_info));
	}
	
	/**
	 * Void an ACH transaction
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @return array An array of transaction data including:
	 * 	-status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	-reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	-transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	-message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
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
	 * 	-status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	-reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	-transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	-message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refundAch($reference_id, $transaction_id, $amount) {
		// Attempt to refund this transaction
		$result = $this->processTransaction($this->getAchParams("REFUND", $transaction_id, $amount));
		
		// An approved refunded transaction should have a status of refunded
		if ($result['status'] == "approved")
			$result['status'] = "refunded";
		
		return $result;
	}

	/**
	 * Retrieves the tamper proof seal and fields included in the TPS
	 *
	 * @param string $transaction_type The type of transaction this seal is for (SALE, AUTH, REFUND, CAPTURE, VOID, UPDATE, CREDIT, AGG)
	 * @param int $transaction_id The transaction ID of a previous transaction (optional, required for REFUND or CAPTURE)
	 * @param float $amount The total amount of the transaction (optional)
	 * $param string $customer_first_name The customer's first name (optional)
	 * @param mixed $payment_account The customer's credit card number if CC, or the ACH account info in the form of "C:bank_routing_number:customer_account_number" (optional)
	 * @return array The tamper proof seal including:
	 * 	-tps The tamper proof seal
	 * 	-tps_def The fields included in the tamper proof seal
	 */
	private function getTps($transaction_type, $transaction_id=null, $amount=null, $customer_first_name=null, $payment_account=null) {
		// Build the tamper proof seal
		$tps_def = "ACCOUNT_ID TRANS_TYPE";
		
		// Set the fields we're passing
		if ($amount != null)
			$tps_def .= " AMOUNT";
		if ($transaction_id != null)
			$tps_def .= " MASTER_ID";
		if ($customer_first_name != null)
			$tps_def .= " NAME1";
		if ($payment_account != null)
			$tps_def .= " PAYMENT_ACCOUNT";
		
		return array(
			'tps' => md5($this->ifSet($this->meta['secret_key']) . $this->ifSet($this->meta['account_id']) .
						$transaction_type . $amount . $transaction_id . $customer_first_name . $payment_account),
			'tps_def' => $tps_def
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
		$response = $this->Http->post($this->base_url, http_build_query($fields));
		
		// Parse the response string
		parse_str($response, $output);
		
		// Log the request
		$this->logRequest($fields, $output);
		
		// Set the transaction status
		$status = "error";
		if (isset($output['STATUS'])) {
			if ($output['STATUS'] == "1")
				$status = "approved";
			elseif ($output['STATUS'] == "0")
				$status = "declined";
		}
		
		// Set general error if status is error
		if ($status == "error")
			$this->Input->setErrors($this->getCommonError("general"));
		
		return array(
			'status' => $status,
			'reference_id' => null,
			'transaction_id' => $this->ifSet($output['TRANS_ID']),
			'message' => $this->ifSet($output['MESSAGE'])
		);
	}
	
	/**
	 * Log the request
	 *
	 * @param array The input parameters sent to the gateway
	 * @param array The response from the gateway
	 */
	private function logRequest($params, $response) {
		$mask_fields = array(
			'ACCOUNT_ID',
			'PAYMENT_ACCOUNT', // credit card number
			'CARD_CVV2',
			'CARD_EXPIRE'
		);
		
		// Determine success/failure (1 is approved/success, 0 declined, E and all else error)
		$success = false;
		if ($this->ifSet($response['STATUS'], "0") == "1")
			$success = true;
		
		// Log data sent to the gateway
		$this->log($this->base_url, serialize($this->maskData($params, $mask_fields)), "input", true);
		
		// Log response from the gateway
		$this->log($this->base_url, serialize($this->maskData($response, $mask_fields)), "output", $success);
	}
}
?>