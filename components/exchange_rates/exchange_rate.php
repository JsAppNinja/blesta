<?php
/**
 * Abstract class that all Currency Exchange Rate Processors must extend
 *
 * @package blesta
 * @subpackage blesta.components.exchange_rates
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
abstract class ExchangeRate {

	/**
	 * Initializes the exchange rate processor
	 *
	 * @param Http $Http The Http component to more easily facilitate HTTP requests to fetch data
	 */
	abstract public function __construct(Http $Http);

	/**
	 * Fetches the exchange rate from currency A to currency B using the given amount
	 *
	 * @param string $currency_from The ISO 4217 currency code to convert from
	 * @param string $currency_to The ISO 4217 currency code to convert to
	 * @param float $amount The amount to convert
	 * @return mixed (boolean) false on error or an array containing the exchange rate information including:
	 * 	-rate
	 * 	-updated The date/time of the last update in YYYY-MM-DD HH:MM:SS format in UTC time
	 */
	abstract public function getRate($currency_from, $currency_to, $amount=1.0);

}
?>