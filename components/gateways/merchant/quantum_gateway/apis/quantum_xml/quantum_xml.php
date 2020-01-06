<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "quantum_xml_response.php";
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "commands" . DIRECTORY_SEPARATOR . "quantum_xml_customers.php";
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "commands" . DIRECTORY_SEPARATOR . "quantum_xml_transactions.php";

/**
 * Quantum XML Requester API
 *
 * @copyright Copyright (c) 2014, Phillips Data, Inc.
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @package quantum_xml
 */
class QuantumXml {

	const LIVE_URL = "https://api.sandbox.namecheap.com/xml.response";

	/**
	 * @var string The gateway login to connect as
	 */
	private $login;
	/**
	 * @var string The key to use when connecting
	 */
	private $key;
	/**
	 * @var array An array representing the last request made
	 */
	private $last_request = array('url' => null, 'args' => null);
	
	/**
	 * Sets the connection details
	 *
	 * @param string $login The gateway login to connect as
	 * @param string $key The key to use when connecting
	 * @param string $username The username to execute an API command using
	 */
	public function __construct($login, $key) {
		$this->login = $login;
		$this->key = $key;
	}
	
	/**
	 * Submits a request to the API
	 *
	 * @param string $command The command to submit
	 * @param array $args An array of key/value pair arguments to submit to the given API command
	 * @return NamecheapResponse The response object
	 */
	public function submit($command, array $args = array()) {

		$url = self::LIVE_URL;
		
		$args = array_merge(array('RequestType' => $command), $args);
		
		$data = array(
			'QGWRequest' => array(
				'Authentication' => array(
					'GatewayLogin' => $this->login,
					'GatewayKey' => $this->key
				),
				'Request' => $args
			)
		);
		
		$this->last_request = array(
			'url' => $url,
			'args' => $data
		);
		
		$element = new SimpleXMLElement();
		$element = $this->arrayToXml($args, $element);
		
		$dom = dom_import_simplexml($element);
		$xml = $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$response = curl_exec($ch);
		curl_close($ch);
		
		return new QuantumXmlResponse($response);
	}
	
	/**
	 * Converts an array to XML using SimpleXML
	 *
	 * @param array $arr An array of key/value pairs
	 * @param SimpleXmlElement $element A SimpleXMLElement
	 * @return SimpleXmlElement $element The new element
	 */
	private function arrayToXml(array $arr, SimpleXMLElement $element) {
		foreach ($arr as $key => $value) {
			if (is_array($value)) {
				if (!is_numeric($key)) {
					$child = $element->addChild($key);
					$this->arrayToXml($value, $child);
				}
				else {
					$parent = $element->xpath('parent::*');
					$child = $element->addChild($parent->getName());
					$this->arrayToXml($value, $child);
				}
			}
			else
				$element->addChild($key)->value = $value;
		}
		return $element;
	}
	
	/**
	 * Returns the details of the last request made
	 *
	 * @return array An array containg:
	 * 	- url The URL of the last request
	 * 	- args The paramters passed to the URL
	 */
	public function lastRequest() {
		return $this->last_request;
	}
}
?>