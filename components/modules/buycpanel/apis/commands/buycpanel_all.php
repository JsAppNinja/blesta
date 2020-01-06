<?php
/**
 * BuycPanel API request funnel
 *
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @package buycpanel.commands
 */
class BuycpanelAll {

	/**
	 * @var BuycpanelApi
	 */
	private $api;
    /**
     * @var string The base API URL
     */
    private $api_url = "https://www.buycpanel.com/api/";

	/**
	 * Sets the API to use for communication
	 *
	 * @param BuycpanelApi $api The API to use for communication
	 */
	public function __construct(BuycpanelApi $api) {
		$this->api = $api;
	}

	/**
	 * Returns the response from the BuycPanel API
	 *
	 * @param string $command The command to execute. One of:
	 *  - changeIp
	 *  - cancelIp
	 *  - exportIp
	 *  - orderIp
	 * @param array $vars An array of input params
	 * @return BuycPanelResponse
	 */
	public function __call($command, array $vars) {
		return $this->api->submit($this->getApiUrl($command), $vars[0]);
	}

    /**
     * Retrieves a list of available commands
     *
     * @return array A set of key/value pairs representing the API command and its API URL name
     */
    private function getCommands() {
        return array('changeIp' => "changeip", 'cancelIp' => "cancel", 'exportIp' => "export", 'orderIp' => "order");
    }

    /**
     * Retrieves the API url for the given command
     *
     * @return string The API URL to send requests to
     */
    private function getApiUrl($command) {
        $url = $this->api_url;
        $commands = $this->getCommands();

        if (array_key_exists($command, $commands))
            $url .= $commands[$command] . ".php";

        return $url;
    }
}
?>