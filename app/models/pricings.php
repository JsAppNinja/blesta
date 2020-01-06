<?php
/**
 * Pricing management
 * 
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Pricings extends AppModel {
	
	/**
	 * Initialize Pricings
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("pricings"));
	}
	
	/**
	 * Fetches the pricing
	 *
	 * @param int $pricing_id The ID of the pricing to fetch
	 * @return mixed A stdClass object representing the pricing, false if no such pricing exists
	 */
	public function get($pricing_id) {
		return $this->Record->select()->from("pricings")->
			where("pricings.id", "=", $pricing_id)->fetch();
	}
	
	/**
	 * Fetches all pricing for a given company
	 *
	 * @param int $company_id The company ID
	 * @return array An array of stdClass objects representing each pricing
	 */
	public function getAll($company_id) {
		return $this->Record->select()->from("pricings")->
			where("pricings.company_id", "=", $company_id)->fetchAll();
	}
	
	/**
	 * Fetches a list of pricing for a given company
	 * 
	 * @param int $company_id The company ID to fetch pricing for
	 * @param int $page The page to return results for
	 * @param array $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return array An array of objects, each representing a pricing
	 */
	public function getList($company_id, $page=1, array $order_by=array('period'=>"asc", 'term' => "asc")) {
		return $this->Record->select()->from("pricings")->
			where("pricings.company_id", "=", $company_id)->
			order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->
			fetchAll();
	}
	
	/**
	 * Return the total number of pricings returned from Pricings::getList(),
	 * useful in constructing pagination for the getList() method.
	 *
	 * @param int $company_id The company ID to fetch pricings for
	 * @return int The total number of pricings
	 * @see Pricings::getList()
	 */
	public function getListCount($company_id) {
		return $this->Record->select()->from("pricings")->
			where("pricings.company_id", "=", $company_id)->numResults();
	}
	
	/**
	 * Adds a pricing for the given company
	 *
	 * @param array $vars An array of pricing info including:
	 *  - company_id The ID of the company to add the pricing for
	 * 	- term The term as an integer 1-65535 (optional, default 1)
	 * 	- period The period, 'day', 'week', 'month', 'year', 'onetime' (optional, default 'month')
	 * 	- price The price of this term (optional, default 0.00)
	 * 	- setup_fee The setup fee for this pricing (optional, default 0.00)
	 * 	- cancel_fee The cancelation fee for this pricing (optional, default 0.00)
	 * 	- currency The ISO 4217 currency code for this pricing (optional, default USD)
	 * @return int The ID of the pricing record added, void on error
	 */
	public function add(array $vars) {
		if (!isset($vars['company_id']))
			$vars['company_id'] = Configure::get("Blesta.company_id");
			
		$this->Input->setRules($this->getRules($vars));
		
		if ($this->Input->validates($vars)) {
			$fields = array("company_id", "term", "period", "price", "setup_fee", "cancel_fee", "currency");
			$this->Record->insert("pricings", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Updates a pricing
	 *
	 * @param int $pricing_id The ID of the pricing to edit
	 * @param array $vars An array of pricing info including:
	 * 	- term The term as an integer 1-65535 (optional, default 1)
	 * 	- period The period, 'day', 'week', 'month', 'year', 'onetime' (optional, default 'month')
	 * 	- price The price of this term (optional, default 0.00)
	 * 	- setup_fee The setup fee for this pricing (optional, default 0.00)
	 * 	- cancel_fee The cancelation fee for this pricing (optional, default 0.00)
	 * 	- currency The ISO 4217 currency code for this pricing (optional, default USD)
	 */
	public function edit($pricing_id, array $vars) {
		$this->Input->setRules($this->getRules($vars));
		
		if ($this->Input->validates($vars)) {
			$fields = array("term", "period", "price", "setup_fee", "cancel_fee", "currency");
			$this->Record->where("pricings.id", "=", $pricing_id)->
				update("pricings", $vars, $fields);
		}
	}
	
	/**
	 * Permanently removes a pricing from the system. 
	 *
	 * @param int $pricing_id The pricing ID to delete
	 */
	public function delete($pricing_id) {
		$this->Record->from("pricings")->
			where("pricings.id", "=", $pricing_id)->delete();
	}
	
	/**
	 * Validates that the term is valid for the period. That is, the term must be > 0
	 * if the period is something other than "onetime".
	 *
	 * @param int $term The Term to validate
	 * @param string $period The period to validate the term against
	 * @return boolean True if validated, false otherwise
	 */
	public function validateTerm($term, $period) {
		if ($period == "onetime")
			return true;
		return $term > 0;
	}
	
	/**
	 * Validates the pricing 'period' field type
	 *
	 * @param string $period The period type
	 * @return boolean True if validated, false otherwise
	 */
	public function validatePeriod($period) {
		$periods = $this->getPeriods();
		
		if (isset($periods[$period]))
			return true;
		return false;
	}
	
	/**
	 * Formats the pricing term
	 *
	 * @param int $term The term length
	 * @param string $period The period of this term
	 * @return mixed The term formatted in accordance to the period, if possible
	 */
	public function formatTerm($term, $period) {
		if ($period == "onetime")
			return 0;
		return $term;
	}
	
	/**
	 * Retrieves a list of pricing periods
	 *
	 * @param boolean $plural True to return language for plural periods, false for singular
	 * @return array Key=>value pairs of pricing periods
	 */
	public function getPeriods($plural=false) {
		
		$type = "";
		if ($plural)
			$type = "_plural";
		
		return array(
			'day' => $this->_("Pricings.getPeriods.day" . $type),
			'week' => $this->_("Pricings.getPeriods.week" . $type),
			'month' => $this->_("Pricings.getPeriods.month" . $type),
			'year' => $this->_("Pricings.getPeriods.year" . $type),
			'onetime' => $this->_("Pricings.getPeriods.onetime" . $type)
		);
	}
	
	/**
	 * Fetches the rules for adding/editing pricing
	 *
	 * @return array The pricing rules
	 */
	private function getRules($vars) {
		$rules = array(
			'term' => array(
				'format' => array(
					'if_set' => true,
					'pre_format' => array(array($this, "formatTerm"), array('_linked'=>"period")),
					'rule' => "is_numeric",
					'message' => $this->_("Pricings.!error.term.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 5),
					'message' => $this->_("Pricings.!error.term.length")
				),
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateTerm"), array('_linked'=>"period")),
					'message' => $this->_("Pricings.!error.term.valid")
				)
			),
			'period' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validatePeriod")),
					'message' => $this->_("Pricings.!error.period.format")
				)
			),
			'price' => array(
				'format' => array(
					'if_set' => true,
					'pre_format' => array(array($this, "currencyToDecimal"), array('_linked'=>"currency"), 4),
					'rule' => "is_numeric",
					'message' => $this->_("Pricings.!error.price.format")
				)
			),
			'setup_fee' => array(
				'format' => array(
					'if_set' => true,
					'pre_format'=>array(array($this, "currencyToDecimal"), array('_linked'=>"currency"), 4),
					'rule' => "is_numeric",
					'message' => $this->_("Pricings.!error.setup_fee.format")
				)
			),
			'cancel_fee' => array(
				'format' => array(
					'if_set' => true,
					'pre_format'=>array(array($this, "currencyToDecimal"), array('_linked'=>"currency"), 4),
					'rule' => "is_numeric",
					'message' => $this->_("Pricings.!error.cancel_fee.format")
				)
			),
			'currency' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("matches", "/^(.*){3}$/"),
					'message' => $this->_("Pricings.!error.currency.format")
				)
			)
		);
	}
}
?>