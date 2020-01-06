<?php
/**
 * Security factory that wraps PHPSecLib.
 * 
 * @package blesta
 * @subpackage blesta.components.security
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Security {
	
	/**
	 * Creates a new instance of the given PHPSecLib library
	 *
	 * @param string $lib The Library to load from (directory)
	 * @param string $class The class library file name (without .php extension)
	 * @param array $params Parameters to pass to the construtor (if any)
	 * @return object Returns an instance of the given class (where class name is $lib . "_" . $class)
	 */
	public static function create($lib, $class, array $params = array()) {
		
		// Set the include path to include this vendor library
		set_include_path(get_include_path() . PATH_SEPARATOR . VENDORDIR . "phpseclib");
		
		// Load the library requested
		Loader::load(VENDORDIR . "phpseclib" . DS . $lib . DS . $class . ".php");
		
		$reflect = new ReflectionClass($lib . "_" . $class);
		return $reflect->newInstanceArgs($params);
	}
}
?>