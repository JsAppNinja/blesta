<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "buycpanel_response.php";
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "commands" . DIRECTORY_SEPARATOR . "buycpanel_all.php";

/**
 * BuycPanel API processor
 *
 * Documentation on the BuycPanel API: https://helpdesk.buycpanel.com/index.php?/Knowledgebase/Article/View/27/0/building-your-own-buycpanel-licensing-system-using-our-api
 *
 * @copyright Copyright (c) 2014, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @package buycpanel
 */
class BuycpanelApi {
    /**
	 * @var string The user to connect as
	 */
	private $email;
	/**
	 * @var string The key to use when connecting
	 */
	private $key;
	/**
	 * @var boolean Whether or not to process in test mode
	 */
	private $test_mode;

	/**
	 * Sets the connection details
	 *
	 * @param string $email The BuycPanel account email address
	 * @param string $key The BuycPanel API key
	 * @param boolean $test_mode Whether or not to process in test mode (optional, default true)
	 */
	public function __construct($email, $key, $test_mode = true) {
		$this->email = $email;
		$this->key = $key;
		$this->test_mode = $test_mode;
	}

    /**
	 * Submits a request to the API
	 *
	 * @param string $url The API URL to send the request to
	 * @param array $args An array of key/value pair arguments to submit to the given API command
	 * @return BuycpanelResponse The response object
	 */
	public function submit($url, array $args = array()) {

		$vars = array(
            'login' => $this->email,
            'key' => $this->key
        );

        if ($this->test_mode)
            $vars['test'] = "1";

        $args = array_merge($args, $vars);

		$this->last_request = array(
			'url' => $url,
			'args' => $args
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url . "?" . http_build_query($args));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$response = curl_exec($ch);

		return new BuycpanelResponse($response);
	}

    /**
	 * Returns the details of the last request made
	 *
	 * @return array An array containing:
	 * 	- url The URL of the last request
	 * 	- args The paramters passed to the URL
	 */
	public function lastRequest() {
		return $this->last_request;
	}
}
?>