<?php
/**
 * PostalMethods service for physically mailing letters
 *
 * @package blesta
 * @subpackage blesta.components.delivery.postal_methods
 * @copyright Copyright (c) 2011, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

class PostalMethods {
	/**
	 * @var string The URL to submit requests to
	 */
	private static $url = "https://api.postalmethods.com/2009-09-09/PostalWS.asmx";
	/**
	 * @var string The account API key
	 */
	private $api_key;
	/**
	 * @var array An array of a file's bitstream data and file type
	 */
	private $file = array();
	/**
	 * @var string An optional description to associate with this letter
	 */
	private $description;
	/**
	 * @var boolean Whether or not documents should be mailed, or simply simulated
	 */
	private $test_mode;
	/**
	 * @var array A list of address fields to send to
	 */
	private $to_address;
	/**
	 * @var boolean Whether or not to include a reply envelope
	 */
	private $include_reply_envelope = false;
	/**
	 * @var boolean Whether or not the bottom-third of the letter should be perforated
	 */
	private $perforate_document = false;
	/**
	 * @var array An array of available file types accepted by Postal Methods
	 * NOTE: These file types are acceptable when the address is inside of the document
	 */
	private $available_file_types = array("DOC", "DOCX", "PDF", "HTML");
	
	
	/**
	 * Constructs a new PostalMethods component
	 */
	public function __construct() {
		Loader::loadHelpers($this, array("Xml"));
		Loader::loadComponents($this, array("Input"));
		// Set all vars to default values
		$this->resetAll();
	}
	
	/**
	 * Sets the Postal Methods API Key required for making requests
	 *
	 * @param string $api_key The Postal Methods API key
	 */
	public function setApiKey($api_key) {
		$this->api_key = $api_key;
	}
	
	/**
	 * Sets whether Postal Methods should physically mail the document, or simulate it
	 *
	 * @param boolean $test_mode False to physically mail documents, true to only simulate it
	 */
	public function setTestMode($test_mode) {
		$this->test_mode = $test_mode;
	}
	
	/**
	 * Sets the file and file type of the file to be mailed
	 *
	 * @param array $file A key=>value array of the file and it's extension
	 * -file The bitstream of the file to send in binary
	 * -type The type of file this is (i.e. HTML, DOC, DOCX, or PDF), (optional, default PDF)
	 */
	public function setFile(array $file) {
		if (!empty($file['file'])) {
			$this->file['file'] = base64_encode($file['file']);
			
			// Set the file type
			if (!empty($file['type']) && in_array(strtoupper($file['type']), $this->getFileTypes()))
				$this->file['type'] = strtoupper($file['type']);
			else
				$this->file['type'] = "PDF";
		}
	}
	
	/**
	 * Sets the outside address
	 *
	 * @param array $address A list of attributes attributes including:
	 * 	-name The name of the recipient
	 * 	-company The company name
	 * 	-address1 Address1
	 * 	-address2 Address2
	 * 	-city The city
	 * 	-state The ISO 3166-2 subdivision code
	 * 	-zip The postal code
	 * 	-country_code The ISO 3166-1 alpha3 country code
	 */
	public function setToAddress(array $address) {
		$this->to_address = array(
			'AttentionLine1' => (isset($address['name']) ? $address['name'] : ""),
			'Company' => (isset($address['company']) ? $address['company'] : ""),
			'Address1' => (isset($address['address1']) ? $address['address1'] : ""),
			'Address2' => (isset($address['address2']) ? $address['address2'] : ""),
			'City' => (isset($address['city']) ? $address['city'] : ""),
			'State' => (isset($address['state']) ? $address['state'] : ""),
			'PostalCode' => (isset($address['zip']) ? $address['zip'] : ""),
			'Country' => (isset($address['country_code']) ? $address['country_code'] : "")
		);
	}
	
	/**
	 * Sets a reply envelope to be included in the mail
	 *
	 * @param boolean $include_reply_envelope True to include a reply envelope in the mail, false to not include a reply envelope
	 * @notes An address must be explicitly set in order to include a reply envelope
	 * @see PostalMethods::setToAddress()
	 */
	public function setReplyEnvelope($include_reply_envelope) {
		$this->include_reply_envelope = $include_reply_envelope;
	}
	
	/**
	 * Sets whether the bottom-third of the letter sent to PostalMethods should
	 * be perforated
	 *
	 * @param boolean $perforated True to have the bottom-third of the letter perforated, false to not perforate the letter
	 * @notes An address must be explicitly set in order to have this letter perforated
	 * @see PostalMethods::setToAddress()
	 */
	public function setPerforated($perforated) {
		$this->perforate_document = $perforated;
	}
	
	/**
	 * Sets a description to associate with this letter in the PostalMethods account
	 *
	 * @param string $description A description to associate with this letter. Limit 100 characters
	 */
	public function setDescription($description) {
		$this->description = substr($description, 0, 100);
	}
	
	/**
	 * Retrieves a list of available file types accepted by Postal Methods
	 *
	 * @return array A numerically-indexed array of available file types
	 */
	public function getFileTypes() {
		return $this->available_file_types;
	}
	
	/**
	 * Resets all settings back to default except for the account username and password
	 */
	public function resetAll() {
		$this->test_mode = true;
		$this->file = array();
		$this->description = null;
		$this->to_address = null;
		$this->perforate_document = false;
		$this->include_reply_envelope = false;
	}
	
	/**
	 * Sends the document to Postal Methods for mailing
	 */
	public function send() {
		// Load the HTTP component, if not already loaded
		if (!isset($this->Http)) {
			Loader::loadComponents($this, array("Net"));
			$this->Http = $this->Net->create("Http");
		}
		
		$file = "";
		$file_type = "";
		
		if (!empty($this->file)) {
			$file = $this->file['file'];
			$file_type = $this->file['type'];
		}
		
		// Set the action based on whether we're sending along an address and return envelope
		$action = "SendLetter";
		if ($this->to_address != null) {
			$action = "SendLetterAndAddress";
			
			// Reply envelopes/Perforation require an advanced letter (and to address)
			if ($this->include_reply_envelope || $this->perforate_document)
				$action = "SendLetterAdvanced";
		}
		
		// Build the XML
		$xml = $this->buildXml($action);
		
		$this->Http->setHeaders(
			array(
				"User-Agent: NuSOAP/0.7.3 (1.114)",
				"Content-Type: text/xml; charset=UTF-8",
				"SOAPAction: \"PostalMethods/" . $action . "\""
			)
		);
		
		$response = $this->Http->post(self::$url, $xml);
		
		// Parse the response and set any errors
		$this->parseResponse($response);
	}
	
	/**
	 * Parses the SOAP response from PostalMethods, sets any errors that may have been generated
	 *
	 * @param string $response The SOAP response from PostalMethods
	 * @return boolean true on success, false on error
	 */
	private function parseResponse($response) {
		// Attempt to parse the response
		$response_code = -1;
		
		try {
			// Remove "xmlns" attribute since it cannot be parsed by simplexml
			$response = str_replace('xmlns="PostalMethods"', "", $response);
			
			// Create an XML parser
			$xml = simplexml_load_string($response);
			
			if (is_object($xml)) {
				// Set default code to null
				$temp = array(null);
				
				// Check the response for the response code
				$children = $xml->children("soap", true)->Body->children("", true);
				
				// Simple letter
				if (isset($children->SendLetterResponse))
					$temp = (array)$children->SendLetterResponse->SendLetterResult;
				// Simple Letter with address provided rather than displayed on invoice
				elseif (isset($children->SendLetterAndAddressResponse->SendLetterAndAddressResult))
					$temp = (array)$children->SendLetterAndAddressResponse->SendLetterAndAddressResult;
				// Advanced letter (e.g. reply envelope, perforation..)
				elseif (isset($children->SendLetterAdvancedResponse->SendLetterAdvancedResult))
					$temp = (array)$children->SendLetterAdvancedResponse->SendLetterAdvancedResult;
				
				$response_code = $temp[0];
			}
		}
		catch(Exception $e) {
			// Error, invalid XML response
		}
		
		// Set error if response code is invalid (we expect a positive transaction id value)
		if ($response_code <= 0) {
			$this->Input->setErrors(array(
				'PostalMethods'=>array(
					'response'=>$response_code
				)
			));
		}
		
		if ($response_code > 0)
			return true;
		return false;
	}
	
	/**
	 * Builds the data to be sent to Postal Methods
	 */
	private function buildXml($action="SendLetter") {
		$file = "";
		$file_type = "";
		
		if (!empty($this->file)) {
			$file = $this->file['file'];
			$file_type = $this->file['type'];
		}
		
		// Set the production or development mode based on test mode
		$work_mode = "Development";
		if (!$this->test_mode)
			$work_mode = "Production";
		
		$address_xml = "";
		$xml = "";
		switch ($action) {
			case "SendLetterAndAddress":
				$address_xml = $this->buildAddressXml($this->to_address);
			case "SendLetter":
				$account_xml =<<<EOT
			<APIKey>{$this->api_key}</APIKey>
			<MyDescription>{$this->description}</MyDescription>
			<FileExtension>{$file_type}</FileExtension>
			<FileBinaryData>{$file}</FileBinaryData>
			<WorkMode>{$work_mode}</WorkMode>
EOT;
				$xml = $account_xml . $address_xml;
				break;
			case "SendLetterAdvanced":
				$settings = array('file'=>$file, 'file_type'=>$file_type, 'work_mode'=>$work_mode);
				$xml = $this->buildAdvancedLetter($settings);
				break;
		}
	
		// Create the XML
		$xml_request =<<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
	<soap:Body xmlns="PostalMethods">
		<{$action}>
			{$xml}
		</{$action}>
	</soap:Body>
</soap:Envelope>
EOT;

		return $xml_request;
	}
	
	/**
	 * Builds the XML for the advanced letter type
	 *
	 * @param array $settings A list of input settings including:
	 * 	-file The file
	 * 	-file_type The file type
	 * 	-work_mode The work/test mode (Production or Development)
	 * @return string The formatted XML
	 */
	private function buildAdvancedLetter(array $settings) {
		// Set address
		$recipient_address = $this->buildAddressXml($this->to_address);
		
		// Set additional settings
		$reply_envelope = ($this->include_reply_envelope ? "<ReplyEnvelope>Env9ReplySingleWindow</ReplyEnvelope>" : null);
		$perforate = ($this->perforate_document ? "<Perforation>BottomThird</Perforation>" : null);
		
		$xml =<<<EOT
			<APIKey>{$this->api_key}</APIKey>
			<MyDescription>{$this->description}</MyDescription>
			<Files>
				<File>
					<FileExtension>{$settings['file_type']}</FileExtension>
					<FileBinaryData>{$settings['file']}</FileBinaryData>
				</File>
			</Files>
			<Addresses>
				<RecipientAddress>
					{$recipient_address}
				</RecipientAddress>
				<ReplyAddress>
					<Contact>
					
					</Contact>
				</ReplyAddress>
			</Addresses>
			<Settings>
				{$reply_envelope}
				{$perforate}
				<WorkMode>{$settings['work_mode']}</WorkMode>
			</Settings>
EOT;
		return $xml;
	}
	
	/**
	 * Builds the XML for addresses given the address key=>value pairs as XML fields
	 *
	 * @return string The formatted XML
	 */
	private function buildAddressXml(array $address) {
		$address_xml = "";
		foreach ($address as $key => $value) {
			$address_xml .= "<" . $key . ">" . $value . "</" . $key . ">\n";
		}
		
		return $address_xml;
	}
	
	/**
	 * Returns all errors set in this object
	 *
	 * @return array An array of error info
	 */
	public function errors() {
		return $this->Input->errors();
	}
}
?>