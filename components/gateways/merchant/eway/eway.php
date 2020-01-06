<?php
/**
 * eWay Credit Card processing gateway. Supports onsite payment processing for
 * Credit Cards.
 *
 * The eWAY API can be found at: http://www.eway.com.au/developers/api.html
 *
 * @package blesta
 * @subpackage blesta.components.gateways.eway
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Eway extends MerchantGateway implements MerchantCc {
	/**
	 * @var string The version of this gateway
	 */
	private static $version = "1.1.1";
	/**
	 * @var string The authors of this gateway
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	/**
	 * @var array An array of meta data for this gateway
	 */
	private $meta;
	/**
	 * @var array A list of test account data for TEST MODE ONLY
	 */
	private $test_account = array(
		'customer_id'=>"87654321",
		'credit_card_number'=>"4444333322221111"
	);
	/**
	 * @var array An array of test and live API URLs
	 */
	private $api_urls = array(
		'test' => array(
			'process' => "https://www.eway.com.au/gateway/xmltest/testpage.asp",
			'refund' => "https://www.eway.com.au/gateway/xmltest/refund_test.asp"
		),
		'live' => array(
			'process' => "https://www.eway.com.au/gateway/xmlpayment.asp",
			'refund' => "https://www.eway.com.au/gateway/xmlpaymentrefund.asp"
		)
	);
	
	/**
	 * Construct a new merchant gateway
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("eway", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Attempt to install this gateway
	 */
	public function install() {
		// Ensure the the system has the libxml extension
		if (!extension_loaded("libxml")) {
			$errors = array(
				'libxml' => array(
					'required' => Language::_("Eway.!error.libxml_required", true)
				)
			);
			$this->Input->setErrors($errors);
		}
	}
	
	/**
	 * Returns the name of this gateway
	 *
	 * @return string The common name of this gateway
	 */
	public function getName() {
		return Language::_("Eway.name", true);
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
		return array("AUD");
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
		$this->view->setDefaultView("components" . DS . "gateways" . DS . "merchant" . DS . "eway" . DS);
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
			'customer_id'=>array(
				'empty'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Eway.!error.customer_id.empty", true)
				),
				'length'=>array(
					'rule'=>array("maxLength", 8),
					'message'=>Language::_("Eway.!error.customer_id.length", true)
				)
			),
			'developer_mode'=>array(
				'valid'=>array(
					'if_set'=>true,
					'rule'=>array("in_array", array("true", "false")),
					'message'=>Language::_("Eway.!error.developer_mode.valid", true)
				)
			),
			'test_mode'=>array(
				'valid'=>array(
					'if_set'=>true,
					'rule'=>array("in_array", array("true", "false")),
					'message'=>Language::_("Eway.!error.test_mode.valid", true)
				)
			)
		);
		
		// Set checkbox if not set
		if (!isset($meta['developer_mode']))
			$meta['developer_mode'] = "false";
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
		return array("customer_id", "refund_password");
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
		$action = "process";
		return $this->processTransaction($this->getRequestUrl($action), $this->getFields($action, null, $amount, $card_info));
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
		// Gateway does not support this action
		$this->Input->setErrors($this->getCommonError("unsupported"));
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
		// Gateway does not support this action
		$this->Input->setErrors($this->getCommonError("unsupported"));
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
		// Gateway does not support this action
		$this->Input->setErrors($this->getCommonError("unsupported"));
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
		// We cannot process a refund without a refund password
		if (empty($this->meta['refund_password'])) {
			$this->Input->setErrors(array('refund_password'=>array('empty'=>Language::_("Eway.!error.refund_password.empty", true))));
		}
		else {
			// Refund this payment transaction
			$action = "refund";
			$result = $this->processTransaction($this->getRequestUrl($action), $this->getFields($action, $transaction_id, $amount));
			
			// An approved refunded transaction should have a status of refunded
			if ($result['status'] == "approved")
				$result['status'] = "refunded";
			
			return $result;
		}
	}
	
	/**
	 * Constructs the XML and fields to be sent to eWAY
	 *
	 * @param string $transaction_type The type of transaction to perform ("process", "refund")
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
	 * @return array A list of fields and their XML including:
	 * 	- fields A list of fields to be sent
	 * 	- xml The list of fields in XML format
	 */
	private function getFields($transaction_type, $transaction_id=null, $amount=null, array $card_info=null) {
		// Create a list of all possible parameters
		$params = array(
			'ewayCustomerID' => $this->ifSet($this->meta['customer_id']),
			'ewayTotalAmount' => (is_numeric($amount) ? 100*$amount : $amount), // Amount must be in cents
			'ewayCustomerFirstName' => $this->ifSet($card_info['first_name']),
			'ewayCustomerLastName' => $this->ifSet($card_info['last_name']),
			'ewayCardHoldersName' => $this->ifSet($card_info['first_name']) . " " . $this->ifSet($card_info['last_name']),
			'ewayCustomerAddress' => $this->ifSet($card_info['address1']),
			'ewayCustomerPostcode' => $this->ifSet($card_info['zip']),
			'ewayCardNumber' => $this->ifSet($card_info['card_number']),
			'ewayCardExpiryYear' => substr($this->ifSet($card_info['card_exp']), 2, 2),
			'ewayCardExpiryMonth' => substr($this->ifSet($card_info['card_exp']), 4, 2),
			'ewayCVN' => $this->ifSet($card_info['card_security_code']),
			'ewayOriginalTrxnNumber' => $transaction_id,
			'ewayTrxnNumber' => $transaction_id,
			'ewayRefundPassword' => $this->ifSet($this->meta['refund_password']),
			'ewayCustomerEmail' => "",
			'ewayCustomerInvoiceDescription' => "",
			'ewayCustomerInvoiceRef' => "",
			'ewayOption1' => "",
			'ewayOption2' => "",
			'ewayOption3' => ""
		);
		$required_fields = array();
		
		// Use the eWAY test account details if test mode is enabled
		if ($this->ifSet($this->meta['test_mode']) == "true") {
			$params['ewayCustomerID'] = $this->test_account['customer_id'];
			$params['ewayCardNumber'] = $this->test_account['credit_card_number'];
		}
		
		// Set which fields are required to be sent
		switch ($transaction_type) {
			case "process":
				$required_fields = array(
					'ewayCustomerID','ewayTotalAmount','ewayCustomerFirstName',
					'ewayCustomerLastName','ewayCustomerEmail','ewayCustomerAddress',
					'ewayCustomerPostcode','ewayCustomerInvoiceDescription',
					'ewayCustomerInvoiceRef','ewayCardHoldersName','ewayCardNumber',
					'ewayCardExpiryMonth','ewayCardExpiryYear','ewayTrxnNumber',
					'ewayOption1','ewayOption2','ewayOption3','ewayCVN'
				);
				break;
			case "refund":
				$required_fields = array(
					'ewayCustomerID','ewayTotalAmount','ewayOriginalTrxnNumber',
					'ewayCardExpiryMonth','ewayCardExpiryYear','ewayRefundPassword',
					'ewayOption1','ewayOption2','ewayOption3',
				);
				break;
		}
		
		// Remove the fields that are not required
		foreach ($params as $key=>$value) {
			if (!in_array($key, $required_fields))
				unset($params[$key]);
		}
		
		// Build the XML
		return array(
			'fields'=>$params,
			'xml'=>$this->buildXml(array('ewaygateway'=>$params)) // Set the top-node
		);
	}
	
	/**
	 * Builds the XML request to be sent to eWAY
	 * 
	 * @param array $fields A list of fields to pass to eWAY
	 * @return string The constructed XML
	 */
	private function buildXml(array $fields) {
		
		// Load the XML component if not already loaded
		if (!isset($this->Xml))
			Loader::loadHelpers($this, array("Xml"));
		
		return $this->Xml->makeXml($fields);
	}
	
	/**
	 * Processes a transaction
	 *
	 * @param string The URL to post to
	 * @param array A list of fields and xml including:
	 * 	- fields A list of fields used to construct the XML
	 * 	- xml The XML constructed from fields
	 * @return array A list of response key=>value pairs including:
	 * 	- status (approved, declined, or error)
	 * 	- reference_id
	 * 	- transaction_id
	 * 	- message
	 */
	private function processTransaction($url, array $fields) {
		
		// Load the HTTP component, if not already loaded
		if (!isset($this->Http)) {
			Loader::loadComponents($this, array("Net"));
			$this->Http = $this->Net->create("Http");
		}
		
		// Submit the request
		$this->Http->setHeader("Content-Type: text/xml");
		$response = $this->Http->post($url, $this->ifSet($fields['xml']));
		
		// Parse the response
		$response = $this->parseResponse($response);
		
		// Log the transaction (with the parsed response and unbuilt request fields)
		$this->logRequest($this->ifSet($fields['fields']), $response, $url);
		
		// Set the response status
		$response_status = $this->getTransactionStatus($response);
		$status = $response_status['status'];
		
		// Set general error if status is error
		if ($status == "error")
			$this->Input->setErrors($this->getCommonError("general"));
		
		return array(
			'status' => $status,
			'reference_id' => $this->ifSet($response['ewayTrxnReference']),
			'transaction_id' => $this->ifSet($response['ewayTrxnNumber']),
			'message' => $response_status['message']
		);
	}
	
	/**
	 * Retrieves the transaction status (approved, declined, error) based on the
	 * response from the gateway
	 *
	 * @param array $response A list of key/value pairs representing the response from the gateway
	 * @return array The transaction status, including:
	 * 	- status (approved, declined, or error)
	 * 	- message The response message
	 */
	private function getTransactionStatus(array $response) {
		// Assume status is an error
		$status = array(
			'status' => "error",
			'message' => $this->ifSet($response['ewayTrxnError'])
		);
		
		// Check the response status
		if (isset($response['ewayTrxnStatus'])) {
			if (strtolower($response['ewayTrxnStatus']) == "true")
				$status['status'] = "approved";
			else
				$status['status'] = "declined";
		}
		
		return $status;
	}
	
	/**
	 * Parses the response from the gateway into an associative array
	 *
	 * @param string $response The response from the gateway
	 * @return array A list of key/value pairs representing the response from the gateway
	 */
	private function parseResponse($response) {
		$options = array();
		
		// Attempt to parse the response
		try {
			// Create an XML parser
			$xml = new SimpleXMLElement($response);
			
			// Parse the XML for response keys/values
			$options = array();
			foreach ($xml->children() as $name=>$value) {
				$options[$name] = (string)$value;
			}
		}
		catch(Exception $e) {
			// Error, invalid XML
			$options['error'] = Language::_("Eway.!error.invalid_xml", true);
		}
		
		return $options;
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
			'ewayCustomerID',
			'ewayRefundPassword',
			'ewayCardNumber',
			'ewayCardExpiryYear',
			'ewayCardExpiryMonth',
			'ewayCVN'
		);
		
		// Determine response status from gateway
		$response_status = $this->getTransactionStatus($response);
		$success = ($response_status['status'] == "approved");
		
		// Log data sent to the gateway
		$this->log($url, serialize($this->maskData($params, $mask_fields)), "input", true);
		
		// Log response from the gateway
		$this->log($url, serialize($this->maskData($response, $mask_fields)), "output", $success);
	}
	
	/**
	 * Retrieves the API URL to post to based on the action
	 *
	 * @param string $transaction_type The type of transaction to perform ("process", "refund")
	 * @return string The URL to post to
	 */
	private function getRequestUrl($transaction_type) {
		$url = "";
		
		// Use live mode only if developer AND test mode are not set
		$test_mode = "test";
		if ($this->ifSet($this->meta['developer_mode']) == "false" && $this->ifSet($this->meta['test_mode']) == "false")
			$test_mode = "live";
		
		switch ($transaction_type) {
			case "process":
			case "refund":
				$url = $this->api_urls[$test_mode][$transaction_type];
				break;
		}
		
		return $url;
	}
}
?>