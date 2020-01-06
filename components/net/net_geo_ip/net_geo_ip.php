<?php
/**
 * NetGeoIP component that wraps Maxmind's GeoIP system. Requires mbstring
 * extension to be enabled with PHP (due to poor coding standards on MaxMind's
 * part).
 * 
 * @package blesta
 * @subpackage blesta.components.net.net_geo_ip
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class NetGeoIp {
	/**
	 * @var GeoIP The GeoIP database object
	 */
	private $database;
	
	/**
	 * Create a new GeoIP instance using the given database file
	 *
	 * @param string $database_file The full path to the database file
	 */
	public function __construct($database_file) {
		$library_path = VENDORDIR . "maxmind" . DS . "geoip";
		
		if (!function_exists("geoip_db_avail")) {
			set_include_path(get_include_path() . PATH_SEPARATOR . $library_path);
			
			// Load library
			Loader::load($library_path . DS . "geoipcity.inc");
		
			// Open the database file
			$this->database = geoip_open($database_file, GEOIP_STANDARD);
		}
	}
	
	/**
	 * Attempts to close the open connection to the database file
	 */
	public function __destruct() {
		try {
			if ($this->database)
				geoip_close($this->database);
		}
		catch (Exception $e) {
			// could not close the GeoIP database
		}
	}
	
	/**
	 * Returns the 2-character country code where the IP resides
	 *
	 * @param string $ip The IP address to lookup, if null will use user's IP
	 * @return string The 2-character country code where the IP resides
	 * @see NetGeoIp::getLocation()
	 */
	public function getCountryCode($ip=null) {
		if (!$this->database)
			return geoip_country_code_by_name($this->currentIp($ip));
		return geoip_country_code_by_addr($this->database, $this->currentIp($ip));
	}
	
	/**
	 * Returns the name of the country where the IP resides
	 *
	 * @param string $ip The IP address to lookup, if null will use user's IP
	 * @return string The name of the country where the IP resides
	 * @see NetGeoIp::getLocation()
	 */
	public function getCountryName($ip=null) {
		if (!$this->database)
			return geoip_country_name_by_name($this->currentIp($ip));
		return geoip_country_name_by_addr($this->database, $this->currentIp($ip));
	}
	
	/**
	 * Fetches an array of information about the location of the IP address,
	 * including longitude and latitude.
	 *
	 * @param string $ip The IP address to lookup, if null will use user's IP
	 * @return array An array of information about the location of the IP address
	 */
	public function getLocation($ip=null) {
		if (!$this->database)
			$locations = (array) geoip_record_by_name($this->currentIp($ip));
		else
			$locations = (array) geoip_record_by_addr($this->database, $this->currentIp($ip));
		
		// UTF-8 encode the retrieved ISO 8859-1 strings
		foreach ($locations as $key => &$value)
			$value = utf8_encode($value);
		
		return $locations;
	}
	
	/**
	 * Get the region (e.g. state) of the given IP address.
	 *
	 * @param string $ip The IP address to lookup, if null will use user's IP
	 * @return string The region the IP address resides in
	 * @see NetGeoIp::getLocation()
	 */
	public function getRegion($ip=null) {
		if (!$this->database)
			return geoip_region_by_name($this->currentIp($ip));
		return geoip_region_by_addr($this->database, $this->currentIp($ip));
	}

	/**
	 * Get the organization or ISP that owns the IP address. Requires a premium
	 * database.
	 *
	 * @param string $ip The IP address to lookup, if null will use user's IP
	 * @return string The oraganization the IP address belongs to
	 */	
	public function getOrg($ip=null) {
		if (!$this->database)
			return geoip_org_by_name($this->currentIp($ip));
		return geoip_org_by_addr($this->database, $this->currentIp($ip));
	}
	
	/**
	 * Returns the currently set IP address
	 *
	 * @param string $ip If non-null, will be the returned value, else will use user's IP
	 * @return string The IP given in $ip, or the user's IP if $ip was null.
	 */
	private function currentIp($ip=null) {
		if ($ip !== null)
			return $ip;
		
		return $_SERVER['REMOTE_ADDR'];
	}
}
?>