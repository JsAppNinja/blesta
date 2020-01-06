<?php
/**
 * vCard component that creates vCard-formatted address book data
 * 
 * @package blesta
 * @subpackage blesta.components.vcard
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
Loader::load(VENDORDIR . "vcard" . DS . "class_vcard.php");

class VCard {
	
	/**
	 * @var A class_vcard instance
	 */
	private $vCard;
	
	/**
	 * Set default vCard data
	 */
	public function __construct() {
		$this->vCard = new class_vcard();
	}
	
	/**
	 * Creates a vCard with the given data
	 *
	 * @param array $data A list of fields to set in the vCard, including: 
	 * @param boolean $stream True to stream the vCard for download (optional)
	 * @param string $file_name The name of the file to stream (optional, required if $stream is true)
	 * @return string A string representing the vCard
	 */
	public function create(array $data, $stream=true, $file_name=null) {
		// Merge data with defaults
		$this->vCard->data = array_merge($this->vCard->data, $data);
		
		// Build the vCard
		$this->vCard->build();
		
		// Stream out the vCard for download
		if ($stream && ($file_name != null)) {
			$this->vCard->filename = $file_name;
			$this->vCard->download();
		}
		
		return $this->vCard->card;
	}
}
?>