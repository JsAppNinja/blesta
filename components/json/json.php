<?php
/**
 * Wrapper for JSON encode/decode functions. Uses Services_JSON if json functions
 * are not built in to this PHP installation.
 *
 * @package blesta
 * @subpackage blesta.components.json
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Json {
	
	/**
	 * Performs JSON encode. Attempts to use built in PHP json_encode if available.
	 *
	 * @param mixed $val The value to encode
	 * @return string The encoded data, in JSON format
	 */
	public function encode($val) {
		if (function_exists("json_encode"))
			return json_encode($val);
			
		Loader::load(VENDORDIR . "json" . DS . "json.php");
		
		$json = new Services_JSON();
		return $json->encode($val);
	}
	
	/**
	 * Performs JSON decode. Attempts to use built in PHP json_decode if available.
	 *
	 * @param string $val The value to decode
	 * @param boolean $assoc True to return value as an associative array, false otherwise.
	 * @return mixed The decoded value in PHP format.
	 */
	public function decode($val, $assoc=false) {
		if (function_exists("json_decode"))
			return json_decode($val, $assoc);
			
		Loader::load(VENDORDIR . "json" . DS . "json.php");
		
		if ($assoc)
			$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		else
			$json = new Services_JSON();
		return $json->decode($val);
	}
}
?>