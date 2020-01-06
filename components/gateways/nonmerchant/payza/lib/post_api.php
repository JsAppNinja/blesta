<?php
/**
 * 
 * PostApi - Helper function
 * 
 * A class to facilitate the posting of parameter string to URL and to  
 * return the response
 * 
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY
 * OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT
 * LIMITED TO THE IMPLIED WARRANTIES OF FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.payza.lib
 * @author Phillips Data, Inc.
 * @author Nirays Technologies
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://nirays.com/ Nirays
 */

class PostApi
{

	/**
     * Construct a new PostApi Helper object
     */
    public function __construct() {
    }

    /**
     * PostApi::send()
     * 
     * Sends the URL encoded post string to the URL 
     * using cURL and retrieves the response.
     * 
     * @return string The response from the URL.
     */
    public function send($url, $dataToSend) {
        $response = '';
		
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToSend);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
    }
}
?>