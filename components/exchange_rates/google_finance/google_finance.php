<?php
/**
 * Google Finance Currency Exchange Rate Processor
 *
 * @package blesta
 * @subpackage blesta.components.exchange_rates.google_finance
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class GoogleFinance extends ExchangeRate {

	/**
	 * @var string The URL to the currency exchange rate resource
	 */
	private static $url = "http://rate-exchange.appspot.com/currency";
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
		Loader::loadComponents($this, array("Json"));
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
		$params = array(
			'from' => $currency_from,
			'to' => $currency_to
		);
		
		$this->Http->open();
		$this->Http->setTimeout(self::$timeout);
		$response = $this->Http->get(self::$url . "?" . http_build_query($params));
		$this->Http->close();
		
		if ($response && ($response = $this->Json->decode($response))) {
			if (!isset($response->rate))
				return false;
			
			return array(
				'rate' => preg_replace("/[^0-9\.]/", "", $response->rate),
				'updated' => date("Y-m-d H:i:s")
			);
		}
		
		return false;
	}
}
?>