<?php
/**
 * Yahoo Finance Currency Exchange Rate Processor
 *
 * @package blesta
 * @subpackage blesta.components.exchange_rates.yahoo_finance
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class YahooFinance extends ExchangeRate {

	/**
	 * @var string The URL to the currency exchange rate resource
	 */
	private static $url = "http://download.finance.yahoo.com/d/quotes.csv";
	/**
	 * @var int The maximum number of seconds to wait for a response
	 */
	private static $timeout = 30;

	/**
	 * Initializes the exchange rate processor
	 *
	 * @param Http $Http The Http component to more easily facilitate HTTP requests to fetch data
	 */
	public function __construct(Http $Http) {
		$this->Http = $Http;
	}
	
	/**
	 * Fetches the exchange rate from currency A to currency B using the given amount
	 *
	 * @param string $currency_from The ISO 4217 currency code to convert from
	 * @param string $currency_to The ISO 4217 currency code to convert to
	 * @param float $amount The amount to convert
	 * @return mixed (boolean) false on error or an array containing the exchange rate information including:
	 * 	-rate The exchange rate for the supplied amount
	 * 	-updated The date/time of the last update in YYYY-MM-DD HH:MM:SS format in UTC time
	 */
	public function getRate($currency_from, $currency_to, $amount=1.0) {
		
		// Load Date helper if not set. Yahoo returns updated date in US Eastern time, we need it in UTC
		if (!isset($this->Date))
			Loader::loadHelpers($this, array("Date"=>array(null, "America/New_York", "UTC")));
		
		$this->Http->open();
		$this->Http->setTimeout(self::$timeout);
		$response = $this->Http->get(self::$url . "?e=.csv&f=sl1d1t1&s=" . $currency_from . $currency_to . "=X");
		$this->Http->close();
		
		if ($response) {
			$response = explode(",", $response);
			
			if (count($response) != 4)
				return false;
			
			foreach ($response as &$field)
				$field = trim(trim($field), '"');
			
			return array(
				'rate'=>$amount*$response[1],
				'updated'=>$this->Date->format("Y-m-d H:i:s", $response[2] . " " . $response[3])
			);
		}
		
		return false;
	}
}
?>