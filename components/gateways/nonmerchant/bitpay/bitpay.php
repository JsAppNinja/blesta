<?php
/**
 * Bitpay Gateway
 *
 * Allows users to pay via Bitcoin
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.bitpay
 * @author Phillips Data, Inc.
 * @author Nirays Technologies
 * @copyright Copyright (c) 2014, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://nirays.com/ Nirays
 */
class Bitpay extends NonmerchantGateway {
    /**
     * @var string The version of this gateway
     */
    private static $version = "1.0.2";
    /**
     * @var string The authors of this gateway
     */
    private static $authors = array(
		array('name' => "Phillips Data, Inc.", 'url' => "http://www.blesta.com"),
		array('name' => "Nirays Technologies", 'url' => "http://nirays.com")
	);
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;
	
    /**
     * Construct a new merchant gateway
     */
    public function __construct() {
         
        // Load components required by this gateway
        Loader::loadComponents($this, array("Input"));       

        // Load components required by this gateway
        Loader::loadModels($this, array("Clients"));  

        // Load the language required by this gateway
        Language::loadLang("bitpay", null, dirname(__FILE__) . DS . "language" . DS);      
    }
	
    /**
     * Returns the name of this gateway
     *
     * @return string The common name of this gateway
     */
    public function getName() {
        return Language::_("Bitpay.name", true);
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
        return array("AED", "AFN", "ALL", "AMD", "ANG", "AOA", "ARS", "AUD",
            "AWG", "AZN", "BAM", "BBD", "BDT", "BGN", "BHD", "BIF", "BMD", "BND",
            "BOB", "BRL", "BSD", "BTC", "BTN", "BWP", "BYR", "BZD", "CAD", "CDF",
            "CHF", "CLP", "CNY", "COP", "CRC", "CUC", "CUP", "CVE", "CZK", "DJF",
            "DKK", "DOP", "DZD", "EGP", "ERN", "ETB", "EUR", "FJD", "FKP", "GBP",
            "GEL", "GGP", "GHS", "GIP", "GMD", "GNF", "GTQ", "GYD", "HKD", "HNL",
            "HRK", "HTG", "HUF", "IDR", "ILS", "IMP", "INR", "IQD", "IRR", "ISK",
            "JEP", "JMD", "JOD", "JPY", "KES", "KGS", "KHR", "KMF", "KPW", "KRW",
            "KWD", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "LTL", "LVL",
            "LYD", "MAD", "MDL", "MGA", "MKD", "MMK", "MNT", "MOP", "MRO", "MUR",
            "MVR", "MWK", "MXN", "MYR", "MZN", "NAD", "NGN", "NIO", "NOK", "NPR",
            "NZD", "OMR", "PAB", "PEN", "PGK", "PHP", "PKR", "PLN", "PYG", "QAR",
            "RON", "RSD", "RUB", "RWF", "SAR", "SBD", "SCR", "SDG", "SEK", "SGD",
            "SHP", "SLL", "SOS", "SPL", "SRD", "STD", "SVC", "SYP", "SZL", "THB",
            "TJS", "TMT", "TND", "TOP", "TRY", "TTD", "TVD", "TWD", "TZS", "UAH",
            "UGX", "USD", "UYU", "UZS", "VEF", "VND", "VUV", "WST", "XAF", "XCD",
            "XDR", "XOF", "XPF", "YER", "ZAR", "ZMW", "ZWD");
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
        $select_options = array(
            'high' => Language::_("Bitpay.transaction.speed.high", true),
            'medium' => Language::_("Bitpay.transaction.speed.medium", true),
            'low' => Language::_("Bitpay.transaction.speed.low", true)
            );      
        $this->view->set("meta", $meta);
        $this->view->set("select_options", $select_options);
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
            'api_key' => array(
                'valid' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("Bitpay.!error.api_key.valid", true)
                )
            ),
			'transaction_speed' => array(
				'valid' => array(
					'rule' => array("in_array", array("high", "medium", "low")),
					'message' => Language::_("Bitpay.!error.transaction_speed.valid", true)
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
        return array("api_key");
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
        
        $client = $this->Clients->get($contact_info['client_id']);
        //Load bitpay library methods
        Loader::load(dirname(__FILE__) . DS . "lib" . DS . "bp_lib.php");
        $util = new BpLib();

        $amount = round($amount, 2);// Force 2-decimal places only
        $orderId = $this->ifSet($contact_info['client_id']) . "-" . time();
        $posData = $this->serializeInvoices($invoice_amounts);
        $redirect_url = $this->ifSet($options['return_url']);
		$query = parse_url($redirect_url, PHP_URL_QUERY);
		$currency = $this->ifSet($this->currency);
		// Returns a string if the URL has parameters or NULL if not
		if( $query ) $redirect_url .= "&";
		else $redirect_url .= "?";
		// The redirection url for the gateway.
		$redirect_url .= "price=$amount&posData=$posData&currency=$currency";
		// The ststus update is given to the gateway by this url.
        $notificationURL = Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") . "/bitpay/?client_id=".$this->ifSet($contact_info['client_id']);
        $options_invoice = array(
            'apiKey'=>  $this->ifSet($this->meta['api_key']),
            'orderId' => $orderId,
            'notificationURL'=> $notificationURL,
            'notificationEmail'=>  $this->ifSet($client->email),
            'redirectURL'=>  $redirect_url,
            'currency'=> $currency,
            'transactionSpeed'=> $this->ifSet($this->meta['transaction_speed']),
            'posData' =>  $posData,
            'itemDesc' => $this->ifSet($options['description']),
            'physical' => false,
            'buyerName'=>$this->ifSet($contact_info['first_name']) . " " . $this->ifSet($contact_info['last_name']),
            'buyerAddress1'=>$this->ifSet($contact_info['address1']),
            'buyerAddress2'=>$this->ifSet($contact_info['address2']),
            'buyerCity'=>$this->ifSet($contact_info['city']),
            'buyerState'=>$this->ifSet($contact_info['state']['name']),
            'buyerZip'=>$this->ifSet($contact_info['zip']),
            'buyerCountry'=>$this->ifSet($contact_info['country']['name']),
            'buyerEmail'=>$this->ifSet($contact_info['email']),
            'buyerPhone'=> ""
        );

        foreach(array('buyerName', 'buyerAddress1', 'buyerAddress2', 'buyerCity',
                    'buyerState', 'buyerZip', 'buyerCountry', 'buyerPhone', 'buyerEmail') as $trunc) {
            // api specifies max 100-char len
            $options_invoice[$trunc] = substr($options_invoice[$trunc], 0, 100);
        }       

        // Check if post is empty
        if (!empty($_POST)) {
            // URL used
            $url = $util->bpGetInvoiceURL();
            // Log input used for creating invoice
            $this->log($url,serialize($this->maskData($options_invoice,array("apiKey"))),"input",true);			
            //Creates invoice and get the response from bitpay api
            $response = $util->bpCreateInvoice($orderId, $amount, $posData, $options_invoice);
            // Result status and redirect url
            $status = false;
            $url_created = $url;
            // Log errors and set
            if (isset($response['url'])){
                $url_created = $response['url'];
                $status = true;
            }
            else {
                $this->Input->setErrors($this->getCommonError("invalid"));
            }

            $this->log($url_created, serialize($this->maskData($response,array("apiKey"))),"output",$status);
            // Redirection
            if($status)
                header('Location:' . $url_created);
        }

        $this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, array("Form", "Html"));
       
        $this->view->set("response",false);
        
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
     */
    public function validate(array $get, array $post) {

        //Load bitpay library methods
        Loader::load(dirname(__FILE__) . DS . "lib" . DS . "bp_lib.php");
        $util = new BpLib();

        //Validates bitpay response and get status of payment
        $response = $util->bpVerifyNotification($this->ifSet($this->meta['api_key']));
		//return status
        $return_status = false;
		// Invoice variable
		$invoices = array();
		//Initial status
		$status = "declined";
        //Set default error message in case no error message is returned from gateway
        if(is_string($response)) {
            $this->Input->setErrors(array('transaction' => array('response' => Language::_("Bitpay.!error.failed.response", true)))); 
        }
        // Log bitpay message          
        if(isset($response['error'])) {            
            $this->Input->setErrors(array('transaction' => array('response' => Language::_("Bitpay.!error.failed.response", true))));
        }

        // Log successful response
        if(isset($response['status'])) {         
            switch ($response['status']) {

                //For low and medium transaction speeds, the order status is set to "Order Received". The customer receives
                //an initial email stating that the transaction has been paid.
                case 'paid':
                    $return_status = true;
                    $status="pending";
                    break;

                //For low and medium transaction speeds, the order status will not change. For high transaction speed, the order
                //status is set to "Order Received" here. For all speeds, an email will be sent stating that the transaction has
                //been confirmed.
                case 'confirmed':
                    //display initial "thank you" if transaction speed is high, as the 'paid' status is skipped on high speed
                    $return_status = true;
                    $status="pending";
                    break;

                //The purchase receipt email is sent upon the invoice status changing to "complete", and the order
                //status is changed to Accepted Payment
                case 'complete':
                    $status="approved";
                    $return_status = true;
                    break;

                case 'invalid':
                    $this->Input->setErrors(array('invalid' => array('response' => Language::_("Bitpay.!error.payment.invalid", true))));
                    $status="declined";
                    $return_status = false;
                    break;

                case 'expired':
                    $this->Input->setErrors(array('invalid' => array('response' => Language::_("Bitpay.!error.payment.expired", true))));
                    $status="declined";
                    $return_status = false;
                    break;
            }
            $invoices = $this->deSerializeInvoices($this->ifSet($response['posData']));
        }
		
		$this->log($this->ifSet($_SERVER['REQUEST_URI']),serialize($this->maskData($response,array("apiKey"))),"output",$return_status);
		return array(		
			'client_id' =>$this->ifSet( $get['client_id']),
			'amount' => $this->ifSet($response['price']),
			'currency' => $this->ifSet($response['currency']),
			'invoices' => $this->ifSet($invoices),
			'status' => $status,
			'transaction_id' =>$this->ifSet($response['id']),
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
     */
    public function success(array $get, array $post) {
		$invoices = Array();      
        if(isset($get['posData'])){
            $invoices = $this->deSerializeInvoices($get['posData']);
        }        
        return array(
                'client_id' =>$this->ifSet( $get['client_id']),
                'amount' => $this->ifSet($get['price']),
                'currency' => $this->ifSet($get['currency']),
                'invoices' => $this->ifSet($invoices),
                'status' => 'approved',
                'transaction_id' => null,
                'parent_transaction_id' => null
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
     * Refund a payment
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param float $amount The amount to refund this card
     * @param string $notes Notes about the refund that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
     * 	- transaction_id The ID returned by the remote gateway to identify this transaction
     * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
     */
    public function refund($reference_id, $transaction_id, $amount, $notes=null) {
        $this->Input->setErrors($this->getCommonError("unsupported"));
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
     * Deserializes a string of invoice info into an array
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @return array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function deSerializeInvoices($str) {
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