<?php
/**
 * Payza payment gateway
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.payza
 * @author Phillips Data, Inc.
 * @author Nirays Technologies
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://nirays.com/ Nirays
 */

class Payza extends NonmerchantGateway {

    /**
     * @var string The version of this gateway
     */
    private static $version = "1.0.2";

    /**
     * @var string The authors of this gateway
     */
    private static $authors = array(
		array('name' => "Phillips Data, Inc.", 'url' => "http://www.blesta.com"),
		array('name' => "Nirays Technologies.", 'url' => "http://nirays.com")
	);

    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * @var string The sandbox URLs to post payments to
     */
    private $payza_sandbox_url = "https://sandbox.Payza.com/sandbox/payprocess.aspx";
	private $payza_sandbox_epd_url = "https://sandbox.payza.com/sandbox/epd2.ashx";

	/**
     * @var string The URLs to post payments to
     */
    private $payza_url = "https://secure.payza.com/checkout";
	private $payza_epd_url = "https://secure.payza.com/epd2.ashx";
	
	/**
     * @var string Sandbox and production URLs for refund
     */
	private $sandbox_refund_api_url = 'https://sandbox.payza.com/api/api.svc/RefundTransaction';		
	private $refund_api_url = 'https://api.payza.com/svc/api.svc/RefundTransaction';
	
    /**
     * Construct a new non-merchant gateway
     */
    public function __construct() {
        // Load components required by this gateway
        Loader::loadComponents($this, array("Input"));

        // Load components required by this gateway
        Loader::loadModels($this, array("Clients"));

        // Load the language required by this gateway
        Language::loadLang("payza", null, dirname(__FILE__) . DS . "language" . DS);
    }

    /**
     * Returns the name of this gateway
     *
     * @return string The common name of this gateway
     */
    public function getName() {
        return Language::_("Payza.name", true);
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
        return array("AUD", "BGN", "CAD", "CHF", "CZK", "DKK", "EEK", "EUR", "GBP", "HKD",
			"HUF", "LTL", "MYR", "MKD", "NOK", "NZD", "PLN", "RON", "SEK", "SGD", "USD", "ZAR"
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
            'merchant_id' => array(
                'valid' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("Payza.!error.merchant_id.valid", true)
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
        return array("merchant_id", "api_security_code");
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
     * Returns all HTML mark-up required to render an authorization and capture payment form
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
     * 		- alpha3 The 3-character country code
     * 		- name The English name of the country
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
     * 		- period The recurring period (day, week, month, year, one-time) used in conjunction with term in order to determine the next recurring payment
     * @return mixed A string of HTML mark-up required to render an authorization and capture payment form, or an array of HTML markup
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts=null, array $options=null) {
        $client = $this->Clients->get($contact_info['client_id']);

        // Force 2-decimal places only
        $amount = round($amount, 2);

		//redirection URL
		$redirect_url = Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") . "/payza/".$this->ifSet($contact_info['client_id']);

        $order_id = $this->ifSet($contact_info['client_id']) . "-" . time();
        $merchant_id =  $this->ifSet($this->meta['merchant_id']);

        // Filling the request parameters
        $fields = array(
                'ap_merchant' => $merchant_id,
                'ap_purchasetype' => "item",
                'ap_itemname' => $order_id,
                'ap_returnurl' => $redirect_url,
				'ap_cancelurl' => $redirect_url,
				'ap_currency' => $this->currency,
                'ap_amount' => $amount,				
				'ap_custfirstname' => $this->ifSet($contact_info['first_name']),
				'ap_custlastname' => $this->ifSet($contact_info['last_name']),
				'ap_custaddress' =>	$this->ifSet($contact_info['address1']) . ' ' . $this->ifSet($contact_info['address2']),
				'ap_custcity' => $this->ifSet($contact_info['city']),
				'ap_custstate' => $this->ifSet($contact_info['state']['name']),
				'ap_custcountry' =>	$this->ifSet($contact_info['country']['name']),
				'ap_custzip' =>	$this->ifSet($contact_info['zip']),
				'ap_custemailaddress' => $this->ifSet($client->email),
				//apc_1 : Custom parameter to pass invoice details				
				'apc_1' => $this->serializeInvoices($invoice_amounts), 
				//apc_2 : Custom parameter to pass client id
				'apc_2' => $contact_info['client_id'],				
				); 
				
 
        $this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));
        Loader::loadHelpers($this, array("Form", "Html"));
		
		//Sets sandbox or production URL based on "test_mode" parameter value.
		$this->view->set("post_to", ($this->ifSet($this->meta['test_mode']) == "true" ? $this->payza_sandbox_url : $this->payza_url));
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
		return $this->handleEPDv2($get, $post);
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
		Loader::load(dirname(__FILE__) . DS . "lib" . DS . "epd_handler.php");
		
        // Get the token from Payza
        $token = urlencode($this->ifSet($get['token']));
		
        // Request Status
        $request_status = false;
        if(strlen($token) > 0)
            $request_status = true;
		
        // Prepend the identifier string "token="
        $token = "token=" . $token;

        // Handler for Payza's EPD V2
        $util = new EDPHandler($token);

        // Select sandbox or production URL based on settings
        $util->setEDP_Url($this->getEDP_Url());

        // Gets response from Payza's EPD V2
        $response = $util->send();

        //url decode the received response from Payza's EPD V2
        $response = urldecode($response);
		
        //Get response parameters in to an array
        parse_str($response, $info);
		return array(
            'client_id' => $this->ifSet($info['apc_2']),
            'amount' => $this->ifSet($info['ap_amount']),
            'currency' => $this->ifSet($info['ap_currency']),
			//Serialized invoice numbers
            'invoices' => $this->deserializeInvoices($this->ifSet($info['apc_1'])),
            'reference_id' => $this->ifSet($info['ap_referencenumber']),
            'status' => "approved",
            'transaction_id' => $this->ifSet($info['ap_itemname']),
            'parent_transaction_id' => null
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
		Loader::load(dirname(__FILE__) . DS . "lib" . DS . "refund_api_client.php");
		
		$user_name =  $this->ifSet($this->meta['merchant_id']);
        $password = $this->ifSet($this->meta['api_security_code']);
		
        // Status of the transaction
        $status = "declined";
		
        // Creating Refund API Client util
        $util = new RefundAPIClient($user_name, $password);
		
		// Alert and return if Payza api security password is not set
		if(empty($password)) {
			 $this->Input->setErrors(
					array(
						'refund' => array(
							'api_password_missing' => Language::_("Payza.!error.security.response", true)
						)
					)
				);
			return;
		}
        $util->setRefundUrl($this->getRefundAPI_Url());
        $test_mode = '0';
        if ($this->ifSet($this->meta['test_mode']) == "true"){
            $test_mode = '1';
        }

        // Build Parameter to send and send refund request to Payza
		$request = $util->buildPostVariables($reference_id,$test_mode);
		$response = $util->send();
        // Log the request
        $this->logRequest($util->getRefundUrl(),$request,$response);

        // URL decode and create an array.
		parse_str($response, $response_array);
		
		// Check the status STATUS code 100 = success
		if(is_array($response_array) && isset($response_array['RETURNCODE']) && $response_array['RETURNCODE'] == "100") {
            $status = "refunded";
		}
		else {
            // No response from Payza
			$this->Input->setErrors($this->getCommonError("general"));
		}

        return array(
            'status' => $status,
            'transaction_id' => $reference_id,
        );
	}

    /**
     * Captures a previously authorized payment
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction.
     * @param $amount The amount.
     * @param array $invoice_amounts
     * @return array An array of transaction data including:
     * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
     * 	- transaction_id The ID returned by the remote gateway to identify this transaction
     * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
     */
    public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts=null) {
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
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }

    /**
     * This is the handle to validate the response from Payza.
     * @param array $get get parameter
     * @param array $post post parameter
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
    private function handleEPDv2(array $get, array $post) {
        Loader::load(dirname(__FILE__) . DS . "lib" . DS . "epd_handler.php");

        // Get the token from Payza
        $token = urlencode($this->ifSet($get['token']));
		
        // Request Status
        $request_status = false;
        if(strlen($token) > 0)
            $request_status = true;
		
        // Prepend the identifier string "token="
        $token = "token=" . $token;

        // Transaction status
        $status = "declined";

		// Handler for Payza's EPD V2
        $util = new EDPHandler($token);

        // Select sandbox or production URL based on settings
        $util->setEDP_Url($this->getEDP_Url());

        // Gets response from Payza's EPD
        $response = $util->send();

        // Response status
        $response_status = false;
		
        // Log request
        $this->log($util->getEDP_Url(), serialize($util->getToken()), "input", $request_status);
        if(isset($response) && strlen($response) > 0) {
			// Invalid token
            if(urldecode($response) == "INVALID TOKEN") {
                $this->Input->setErrors(
                    array(
                        'token' => array(
                            'response' => Language::_("Payza.!error.invalid_token.response", true)
                        )
                    )
                );
                $this->log($util->getEDP_Url(), serialize($this->maskSensitiveData($response,"ap_securitycode")), "output", $response_status);
				return;
            }
            else {
                // URL decode the received response from Payza's EPD V2
                $response = urldecode($response);
				
				// Get response parameters into an array
				parse_str($response, $info);

                //setting information about the transaction from the EPD information array
                $client_id = $this->ifSet($info['apc_2']);
                $transaction_status = $this->ifSet($info['ap_status']);
                $reference_number = $this->ifSet($info['ap_referencenumber']);
                $currency = $this->ifSet($info['ap_currency']);
                $amount = $this->ifSet($info['ap_amount']);
				//Item name is the unique value created to identify a transaction number in Payza
                $transaction_id = $this->ifSet($info['ap_itemname']); 
				//apc_1 : Serialized invoice numbers
                $invoices = $this->deserializeInvoices($this->ifSet($info['apc_1'])); 
                
				// Check status
				if($transaction_status == "Success"){
					$status = "approved";
					$response_status = true;
				}
            }
        }
        else {
			// Error, no response
			$this->Input->setErrors($this->getCommonError("general"));
            $this->log($util->getEDP_Url(), serialize($this->maskSensitiveData($response,"ap_securitycode")), "output", $response_status);
			return;
        }
		
        $this->log($util->getEDP_Url(), serialize($this->maskSensitiveData($response,"ap_securitycode")), "output", $response_status);
		
        return array(
            'client_id' =>$client_id,
            'amount' => $amount,
            'currency' => $currency,
            'invoices' => $invoices,
            'status' => $status,
            'reference_id' => $reference_number,
            'transaction_id' => $transaction_id,
            'parent_transaction_id' => null
        );
    }

    /**
     * Gets the EPD V2 url for Payza
     *
     * @return string The payza epd v2 url based on the mode
     */
    private function getEDP_Url() {
		return ($this->ifSet($this->meta['test_mode']) == "true") ? $this->payza_sandbox_epd_url : $this->payza_epd_url;
    }

    /**
     * Gets the refund API url for Payza
     *
     * @return string The payza epd v2 url based on the mode
     */
    private function getRefundAPI_Url() {
		return ($this->ifSet($this->meta['test_mode']) == "true") ? $this->sandbox_refund_api_url : $this->refund_api_url;
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
            $str .= ($i > 0 ? "-" : "") . $invoice['id'] . "_" . $invoice['amount'];

        return $str;
    }

    /**
     * Deserializes a string of invoice info into an array
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @return array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function deserializeInvoices($str) {
        $invoices = array();
        $temp = explode("-", $str);

        foreach ($temp as $pair) {
            $pairs = explode("_", $pair, 2);
            if (count($pairs) != 2)
                continue;
            $invoices[] = array('id' => $pairs[0], 'amount' => $pairs[1]);
        }
        return $invoices;
    }

    /**
     * Log the request
     *
     * @param string $url The URL of the API request to log
     * @param array The input parameters sent to the gateway
     * @param array The response from the gateway
     */
    private function logRequest($url, $params, $response) {
        //URL decode and create an array.
        parse_str($response, $response_array);

        // Determine success or failure for the response
        $success = false;
        if(is_array($response_array) && isset($response_array['RETURNCODE']) && $response_array['RETURNCODE'] == "100")
            $success = true;

        // Mask the password.
        // Log data sent to the gateway
        $this->log($url, serialize($this->maskSensitiveData($params,"PASSWORD")), "input", true);

        // Log response from the gateway
        $this->log($url, serialize($response), "output", $success);
    }

    /**
     * Masks each string listed in $mask_field that also appears in $data, such
     * that sensitive information is redacted.
     *
     * @param string $data data to be masked
     * @param string $mask_field A String that need to be masked
     * @return string The $data with fields masked as necessary
     */
    private function maskSensitiveData($data, $mask_field) {
        return preg_replace("/(".$mask_field."=[^&]*)/", $mask_field."=***",$data);
    }
}
?>
