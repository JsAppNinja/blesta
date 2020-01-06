<?php
/**
 * 
 * EPDTrigger
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
 * @author Siju
 * @copyright 2013
 */

class EDPHandler
{
    /**
     * The API's response variables
     */
    private $responseArray;

    /**
     * The exact URL of the RefundAPI
     */
    private $EDP_Url = '';

    /**
     * Your Payza token for handshake with EDP server
     */
    private $token = '';



    /**
     * The data that will be sent to the RefundAPI
     */
    public $dataToSend = '';


    /**
     * EDPHandler::__construct()
     * 
     * Constructs a EDPTrigger object
     * 
     * @param string $token for EPD transaction
     */
    public function __construct($token)
    {
        $this->token = $token;
    }


    /**
     * EDPHandler::setEDP_Url()
     * 
     * Sets the $url variable
     * 
     * @param string $newUrl New url address.
     */
    public function setEDP_Url($newUrl = '')
    {
        $this->EPD_Url = $newUrl;
    }


    /**
     * EDPHandler::getEDP_Url()
     * 
     * Returns the url variable
     * 
     * @return string A variable containing a URL address.
     */
    public function getEDP_Url()
    {
        return $this->EPD_Url;
    }
	
    /**
     * EDPHandler::setToken()
     * 
     * Sets the $token variable
     * 
     * @param string $token as new token
     */
    public function setToken($token = '')
    {
        $this->token = $token;
    }


    /**
     * EDPHandler::getToken()
     * 
     * Returns the token variable
     * 
     */
    public function getToken()
    {
        return $this->token;
    }	
	
    /**
     * EDPHandler::send()
     * 
     * Sends the URL encoded post string to the EDPHandler 
     * using cURL and retrieves the response.
     * 
     * @return string The response from the EDPHandler.
     */
    public function send()
    {
        $response = '';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->getEDP_Url());
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getToken());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }


    /**
     * EDPHandler::parseResponse()
     * 
     * Parses the encoded response from the RefundAPI
     * into an associative array.
     * 
     * @param string $input The string to be parsed by the function.
     */
    public function parseResponse($input)
    {
		parse_str($input, $this->responseArray);	
    }


    /**
     * EDPHandler::getResponse()
     * 
     * Returns the responseArray 
     * 
     * @return string An array containing the response variables.
     */
    public function getResponse()
    {
        return $this->responseArray;
    }


    /**
     * EDPHandler::__destruct()
     * 
     * Destructor of the EDPHandler object
     */
    public function __destruct()
    {
        unset($this->responseArray);
    }
}
?>