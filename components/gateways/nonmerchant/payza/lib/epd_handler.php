<?php
/**
 * 
 * EPD Handler
 * 
 * A class which facilitates the interaction with Payza's 
 * EPD Version 2 Transactions. This allows the user to perform 
 * more secure transactions
 * 
 * 
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY
 * OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT
 * LIMITED TO THE IMPLIED WARRANTIES OF FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * @author Payza
 * @copyright 2011
 */

class EDPHandler
{
    /**
     * The exact URL of the RefundAPI
     */
    private $EDP_Url = '';

    /**
     * Your Payza token for handshake with EDP server
     */
    private $token = '';

    /**
     * EDPHandler::__construct()
     * 
     * Constructs a EDPHandler object
     * 
     * @param string $token for EPD transaction
     */
    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * EDPHandler::setEDP_Url()
     * 
     * Sets the $url variable
     * 
     * @param string $newUrl New url address.
     */
    public function setEDP_Url($newUrl = '') {
        $this->EPD_Url = $newUrl;
    }

    /**
     * EDPHandler::getEDP_Url()
     * 
     * Returns the url variable
     * 
     * @return string A variable containing a URL address.
     */
    public function getEDP_Url() {
        return $this->EPD_Url;
    }
	
    /**
     * EDPHandler::setToken()
     * 
     * Sets the $token variable
     * 
     * @param string $token as new token
     */
    public function setToken($token = '') {
        $this->token = $token;
    }

    /**
     * EDPHandler::getToken()
     * 
     * Returns the token variable
     * 
     * @param string $token as new token
     */
    public function getToken() {
        return $this->token;
    }	
	
    /**
     * EDPHandler::send()
     * 
     * Sends the url encoded post string to the Payza EDP handler URL
     * See PostApi->send($url, $dataToSend) for details
     * 
     * @return string The response from the RefundAPI.
     */
    public function send() {
		Loader::load(dirname(__FILE__) . DS . "post_api.php");
		$util = new PostApi();
		return $util->send($this->getEDP_Url(), $this->getToken());
    }
}
?>