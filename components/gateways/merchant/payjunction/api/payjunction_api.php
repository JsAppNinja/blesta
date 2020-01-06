<?php
/**
 * PayJunction Credit Card and ACH payment processing gateway.
 *
 * Documentation on the REST API can be found at: http://developer.payjunction.com/documentation
 *
 * @package blesta
 * @subpackage blesta.components.gateways.merchant.payjunction.api
 * @author Phillips Data, Inc.
 * @author Nirays Technologies
 * @copyright Copyright (c) 2014, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://nirays.com/ Nirays Technologies
 */

class PayJunctionApi {
	/**
	 * @var string The live URL for API requests
	 */
	private $url = "https://api.payjunction.com/transactions/";
	/**
	 * @var string The test URL for API requests
	 */
	private $test_url = "https://api.payjunctionlabs.com/transactions/";	
	/**
	 * Initializes the request parameter
	 *
	 * @param string $user_name The PayJunction username
	 * @param string $password The PayJunction password
	 * @param string $API_key The PayJunction API key
	 * @param boolean $test_mode If true, will submit the request as a test transaction
	 */
	public function __construct($user_name,$password,$api_key,$test_mode=false) {
		
		//Set authorization parameters username,password,apikey required for each request
		$this->user_name = $user_name;
		$this->password = $password;
		$this->api_key = $api_key;		

		//Set Url for the request
  		if($test_mode == "true")
  			$this->url = $this->test_url;
	}

	/**
	 * Returns the URL used in the requests
	 *
	 * @return string The URL of the requests
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * Processes a transaction
	 *
	 * @param array $fields An array of payment parameters send to the gateway
	 * @param string $transaction_id The transaction ID for the previously authorized transaction (if any)
	 * @param string $request The type of request POST,GET,PUT etc 
	 * @return string The response from the gateway as a JSON string or null (if error)
	 */
	public function processTransaction($fields,$transaction_id=null,$request=null){
	
		//Set login information
		$login = $this->user_name.':'.$this->password;	

		if(!$request) 
			$request = "POST";
		
		$post_data = http_build_query($fields);

		//Set API key and headers
		$key = 'X-PJ-Application-Key: '.$this->api_key;
		$headers = array('Accept: application/json',$key);

		//Set the url for special request such as refund, void based on a previous transaction
  		if(isset($transaction_id)){
  			$this->url.=$transaction_id;
  		}

		$curl = curl_init();
		curl_setopt_array($curl, 
			array(
				CURLOPT_URL 			=> $this->url,
				CURLOPT_USERPWD         => $login,
				CURLOPT_HTTPHEADER		=> $headers,
				CURLOPT_VERBOSE 		=> 1,	
			    CURLOPT_SSL_VERIFYPEER 	=> 1,
				CURLOPT_SSL_VERIFYHOST 	=> 2,		
				CURLOPT_CONNECTTIMEOUT	=> 60, //try to connect for x seconds
				CURLOPT_TIMEOUT 		=> 70, //whole operation must be finished in under x seconds
				CURLOPT_RETURNTRANSFER 	=> true,				
				CURLOPT_POST 			=> true,
				CURLOPT_CUSTOMREQUEST 	=> $request,
				CURLOPT_POSTFIELDS 		=> $post_data
			)
		);	

		// Returns the response from gateway
		return curl_exec($curl);	
	}
}
?>