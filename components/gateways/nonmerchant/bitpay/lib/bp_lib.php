<?php
/**
 * Bitpay Gateway Library
 *
 * This library define some of the core functionality
 * for the bitpay
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.bitpay.lib
 * @author Phillips Data, Inc.
 * @author Nirays Technologies
 * @copyright Copyright (c) 2014, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://nirays.com/ Nirays
 */
class BpLib extends Bitpay {
    /**
     * @var string The version of this gateway
     */
    private $bpOptions  = array(
		'apiKey' 			=> '',
		'verifyPos' 		=> true,
		'notificationEmail' => '',
		'notificationURL' 	=> '',
		'redirectURL' 		=> '',
		'currency' 			=> 'USD',
		'physical' 			=> 'false',
		'fullNotifications' => 'true',
		'transactionSpeed' 	=> 'medium');
	
	/**
     * Construct a Bitpay Gateway lib
     */
    public function __construct() {
        
    }
	
	/**
	* Used for creating Invoice
	* @param string $orderId: Used to display an orderID to the buyer. In the account summary view, this value is used to 
	* identify a ledger entry if present.
	*
	* @param $price: by default, $price is expressed in the currency you set in bp_options.php.  The currency can be 
	* changed in $options.
	*
	* @param string $posData: this field is included in status updates or requests to get an invoice.  It is intended to be used by
	* the merchant to uniquely identify an order associated with an invoice in their system.  Aside from that, Bit-Pay does
	* not use the data in this field.  The data in this field can be anything that is meaningful to the merchant.
	*
	* $options keys can include any of: 
	* ('itemDesc', 'itemCode', 'notificationEmail', 'notificationURL', 'redirectURL', 'apiKey'
	*		'currency', 'physical', 'fullNotifications', 'transactionSpeed', 'buyerName', 
	*		'buyerAddress1', 'buyerAddress2', 'buyerCity', 'buyerState', 'buyerZip', 'buyerEmail', 'buyerPhone')
	* If a given option is not provided here, the value of that option will default to what is found in bp_options.php
	* (see api documentation for information on these options).
	*/
	public function bpCreateInvoice($orderId, $price, $posData, $options = array()) {	

		$options = array_merge($this->bpOptions, $options);	// $options override any options found in bp_options.php
		
		$pos = array('posData' => $posData);
		if ($this->bpOptions['verifyPos'])
			$pos['hash'] = $this->bpHash(serialize($posData), $options['apiKey']);
		$options['posData'] = json_encode($pos);
		
		$options['orderID'] = $orderId;
		$options['price'] = $price;
		
		$postOptions = array('orderID', 'itemDesc', 'itemCode', 'notificationEmail', 'notificationURL', 'redirectURL', 
			'posData', 'price', 'currency', 'physical', 'fullNotifications', 'transactionSpeed', 'buyerName', 
			'buyerAddress1', 'buyerAddress2', 'buyerCity', 'buyerState', 'buyerZip', 'buyerEmail', 'buyerPhone');
		foreach($postOptions as $o)
			if (array_key_exists($o, $options))
				$post[$o] = $options[$o];
		$post = json_encode($post);
		
		$response = $this->bpCurl($this->bpGetInvoiceURL(), $options['apiKey'], $post);

		return $response;
	}

    /**
     * To get the invoice URL
     * @return string Invoice URL
     */
    public function  bpGetInvoiceURL(){
        return "https://bitpay.com/api/invoice/";
    }

	/**
	* Call from your notification handler to convert $_POST data to an object containing invoice data
    *
    * @param bool $apiKey to use the existing API key or not
    * @return mixed|string json object which contain the result.
    */
    public function bpVerifyNotification($apiKey = false) {

		if (!$apiKey)
			$apiKey = $this->bpOptions['apiKey'];		

		$post = file_get_contents("php://input");
		if (!$post)
			return 'No post data';
			
		$json = json_decode($post, true);
		
		if (is_string($json))
			return $json; // error

		if (!array_key_exists('posData', $json)) 
			return 'no posData';
			
		$posData = json_decode($json['posData'], true);
		if($this->bpOptions['verifyPos'] and $posData['hash'] != $this->bpHash(serialize($posData['posData']), $apiKey))
			return 'authentication failed (bad hash)';
		$json['posData'] = $posData['posData'];
			
		return $json;
	}

    /**
     * This helps to get invoice
     *
     * @param string $invoiceId The invoice id
     * @param bool $apiKey to use new api key or not
     * @return array|mixed array that has invoice details
     */
    public function bpGetInvoice($invoiceId, $apiKey=false) {

		if (!$apiKey)
			$apiKey = $this->bpOptions['apiKey'];		

		$response = $this->bpCurl('https://bitpay.com/api/invoice/'.$invoiceId, $apiKey);
		if (is_string($response))
			return $response; // error
		$response['posData'] = json_decode($response['posData'], true);
		$response['posData'] = $response['posData']['posData'];

		return $response;	
	}

    /**
     * Generates a keyed hash.
     *
     * @param $data
     * @param $key
     * @return string
     */
    private function bpHash($data, $key) {
		$hmac = base64_encode(hash_hmac('sha256', $data, $key, TRUE));
		return strtr($hmac, array('+' => '-', '/' => '_', '=' => ''));
	}

    /**
     * Curl helper function
     *
     * @param $url Url to send
     * @param $apiKey api key to be used.
     * @param bool $post post data to be send
     * @return array|mixed result returned
     */
    private function bpCurl($url, $apiKey, $post = false) {
			
		$curl = curl_init($url);
		$length = 0;
		if ($post) {	
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
			$length = strlen($post);
		}
		
		$uname = base64_encode($apiKey);
		$header = array(
			'Content-Type: application/json',
			"Content-Length: $length",
			"Authorization: Basic $uname",
			);

		curl_setopt($curl, CURLOPT_PORT, 443);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1); // verify certificate
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // check existence of CN and verify that it matches hostname
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
			
		$responseString = curl_exec($curl);
		
		if($responseString == false) {
			$response = array('error' => curl_error($curl));
		} else {
			$response = json_decode($responseString, true);
			if (!$response)
				$response = array('error' => 'invalid json: '.$responseString);
		}
		curl_close($curl);
		return $response;
	}
}
?>
