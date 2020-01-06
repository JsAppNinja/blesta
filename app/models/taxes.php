<?php
/**
 * Tax rule management
 * 
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Taxes extends AppModel {
	
	/**
	 * Initialize Taxes
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("taxes"));
	}
	
	/**
	 * Adds a tax rule to the system
	 *
	 * @param array $vars An array of tax info including:
	 *	- company_id The company ID this tax rule applies to.
	 *	- level The tax level this rule will be apart of.
	 *	- name The name of the tax rule (optional, default NULL)
	 *	- amount The tax amount (optional, default 0.00)
	 *	- type The tax type (optional, default 'exclusive')
	 *	- country The country this tax rule will apply to (optional, defalut NULL)
	 *	- state The state this tax rule will apply to (optional, default NULL)
	 *	- status The status of this tax rule (optional, default 'active')
	 * @return int The ID of the tax rule created, void on error
	 */
	public function add(array $vars) {
		$vars['status'] = (isset($vars['status']) ? $vars['status'] : "active");
		$this->Input->setRules($this->getRules());
		
		if ($this->Input->validates($vars)) {
			// Add a tax rule
			$fields = array("company_id", "level", "name", "amount", "type", "country", "state", "status");
			$this->Record->insert("taxes", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Updates a tax rule
	 *
	 * @param int $tax_id The tax ID
	 * @param array $vars An array of tax info including:
	 *	- company_id The company ID this tax rule applies to.
	 *	- level The tax level this rule will be apart of.
	 *	- name The name of the tax rule (optional, default NULL)
	 *	- amount The tax amount (optional, default 0.00)
	 *	- type The tax type (optional, default 'exclusive')
	 *	- country The country this tax rule will apply to (optional, default NULL)
	 *	- state The state tis tax rule will apply to (optional, default NULL)
	 *	- status The status of this tax rule (optional, default 'active')
	 * @return int The ID of the tax rule created, void on error
	 */
	public function edit($tax_id, array $vars) {
		$rules = $this->getRules();
		$rules['tax_id'] = array(
			'exists' => array(
				'rule' => array(array($this, "validateExists"), "id", "taxes"),
				'message' => $this->_("Taxes.!error.tax_id.exists")
			)
		);
		
		$this->Input->setRules($rules);
		
		$vars['tax_id'] = $tax_id;
		
		if ($this->Input->validates($vars)) {
			// Inactivate the old tax rule and add a new tax rule
			$this->delete($tax_id);
			
			if (!isset($vars['status']) || $vars['status'] != "inactive")
				$this->add($vars);
		}
	}
	
	/**
	 * Sets a tax to inactive
	 *
	 * @param int $tax_id The ID of the tax rule to mark deleted (inactive)
	 */
	public function delete($tax_id) {
		$vars = array('status' => "inactive");
		$this->Record->where("id", "=", $tax_id)->update("taxes", $vars, array("status"));
	}

	/**
	 * Fetches all tax types
	 *
	 * @return array A key=>value array of tax types
	 */
	public function getTaxTypes() {
		return array(
			'inclusive'=>$this->_("Taxes.getTaxTypes.inclusive"),
			'exclusive'=>$this->_("Taxes.getTaxTypes.exclusive")
		);
	}
	
	/**
	 * Fetches all tax levels
	 *
	 * @return array A key=>value array of tax levels
	 */
	public function getTaxLevels() {
		// Tax levels 1 and 2, respectively
		return array("1"=>1, "2"=>2);
	}
	
	/**
	 * Fetchas all status types
	 *
	 * @return array A key=>value array of tax statuses
	 */
	public function  getTaxStatus() {
		return array(
			'active'=>$this->_("Taxes.getTaxStatus.active"),
			'inactive'=>$this->_("Taxes.getTaxStatus.inactive")
		);
	}
	
	/**
	 * Fetches a tax
	 *
	 * @param int $tax_id The tax ID
	 * @return mixed A stdClass objects representing the tax, false if it does not exist
	 */
	public function get($tax_id) {
		return $this->Record->select()->from("taxes")->where("id", "=", $tax_id)->fetch();
	}
	
	/**
	 * Retrieves a list of all tax rules for a particular company
	 *
	 * @param int $company_id The company ID
	 * @return mixed An array of stdClass objects representing tax rules, or false if none exist
	 */
	public function getAll($company_id) {		
		$fields = array("id", "company_id", "level", "name", "amount", "type",
			"country", "state", "status"
		);
		
		// Get all tax rules
		$records = $this->Record->select($fields)->from("taxes")->where("company_id", "=", $company_id)->
			where("status", "=", "active")->
			order(array("level"=>"asc"))->fetchAll();
		
		$rules = array();
		
		// Sort tax rules by level
		if (is_array($records)) {
			$num_records = count($records);
			for ($i=0; $i<$num_records; $i++) {
				$rules['level_' . $records[$i]->level][] = $records[$i]; 
			}
		}
		
		return $rules;
	}
	
	/**
	 * Validates a tax's 'type' field
	 *
	 * @param string $type The type to check
	 * @return boolean True if the type is validated, false otherwise
	 */
	public function validateType($type) {
		switch ($type) {
			case "exclusive":
			case "inclusive":
				return true;
		}
		return false;
	}
	
	/**
	 * Validates a tax's 'status' field
	 *
	 * @param string $status The status to check
	 * @return boolean True if the status is validated, false otherwise
	 */
	public function validateStatus($status) {
		switch ($status) {
			case "active":
			case "inactive":
				return true;
		}
		return false;
	}
	
	/**
	 * Returns the rule set for adding/editing taxes
	 * 
	 * @return array Tax rules
	 */
	private function getRules() {
		$rules = array(
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("Taxes.!error.company_id.exists")
				)
			),
			'level' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Taxes.!error.level.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 2),
					'message' => $this->_("Taxes.!error.level.length")
				)
			),
			'name' => array(
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 64),
					'message' => $this->_("Taxes.!error.name.length")
				)
			),
			'amount' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Taxes.!error.amount.format")
				)
			),
			'type' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateType")),
					'message' => $this->_("Taxes.!error.type.format")
				)
			),
			'country' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "alpha2", "countries"),
					'message' => $this->_("Taxes.!error.country.valid")
				)
			),
			'state' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "code", "states"),
					'message' => $this->_("Taxes.!error.state.valid")
				)
			),
			'status' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStatus")),
					'message' => $this->_("Taxes.!error.status.format")
				)
			)
		);
		return $rules;
	}
}
?>