<?php
/**
 * 
 * RefundAPIClient
 * 
 * A class which facilitates the interaction with Payza's 
 * Refund API. RefundAPIClient class allows user to create 
 * the data to be sent to the API in the correct format and 
 * retrieve the response. 
 * 
 * 
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY
 * OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT
 * LIMITED TO THE IMPLIED WARRANTIES OF FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * @author Payza
 * @copyright 2010
 */

class RefundAPIClient
{
    /**
     * The exact URL of the RefundAPI
     */
    private $refundUrl = '';

    /**
     * Your Payza user name which is your email address
     */
    private $userName = '';

    /**
     * Your API password that is generated from your Payza account
     */
    private $apiPassword = '';

    /**
     * The data that will be sent to the RefundAPI
     */
    public $dataToSend = '';


    /**
     * RefundAPIClient::__construct()
     * 
     * Constructs a RefundAPIClient object
     * 
     * @param string $userName Your Payza user name.
     * @param string $password Your API password.
     */
    public function __construct($userName, $password) {
        $this->userName = $userName;
        $this->apiPassword = $password;
        $this->dataToSend = '';
    }


    /**
     * RefundAPIClient::setUrl()
     * 
     * Sets the $url variable
     * 
     * @param string $newUrl New url address.
     */
    public function setRefundUrl($newUrl = '') {
        $this->refundUrl = $newUrl;
    }


    /**
     * RefundAPIClient::getUrl()
     * 
     * Returns the url variable
     * 
     * @return string A variable containing a URL address.
     */
    public function getRefundUrl() {
        return $this->refundUrl;
    }

    /**
     * RefundAPIClient::setUserName()
     * 
     * Sets the $myUserName variable
     * 
     * @param string $newUserName
     */
    public function setUserName($newUser = '') {
        $this->userName = $newUser;
    }


    /**
     * RefundAPIClient::getUserName()
     * 
     * Returns the url variable
     * 
     * @return string A variable user name .
     */
    public function getUserName() {
        return $this->userName;
    }	
	
    /**
     * RefundAPIClient::setPassword()
     * 
     * Sets the $$apiPassword  variable
     * 
     * @param string $password
     */
    public function setApiPassword($password = '') {
        $this->$apiPassword  = $password;
    }


    /**
     * RefundAPIClient::getPassword()
     * 
     * Returns the $apiPassword  variable
     * 
     * @return string A variable password .
     */
    public function getApiPassword() {
        return $this->apiPassword;
    }	
	
    /**
     * RefundAPIClient::buildPostVariables()
     * 
     * Builds a URL encoded post string which contains the variables to be 
     * sent to the API in the correct format. 
     * 
     * @param int $transRefNum The reference number of the transaction to be refunded.
     * @param int $testMode Test mode status.
     * 
     * @return string The URL encoded post string
     */
    public function buildPostVariables($transRefNum, $testMode = '0') {
        $this->dataToSend = sprintf("USER=%s&PASSWORD=%s&TRANSACTIONREFERENCE=%s&TESTMODE=%s",
            urlencode($this->userName), urlencode($this->apiPassword), urlencode((string) $transRefNum), urlencode((string) $testMode));
        return $this->dataToSend;
    }


    /**
     * RefundAPIClient::send()
     * 
     * Sends the URL encoded post string to the RefundAPI 
     * See PostApi->send($url, $dataToSend) for details
     * 
     * @return string The response from the RefundAPI.
     */
    public function send() {
		Loader::load(dirname(__FILE__) . DS . "post_api.php");
		$util = new PostApi();
		return $util->send($this->getRefundUrl(), $this->dataToSend);
    }
}
?>