<?php
/**
 * Coupon management
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Coupons extends AppModel {
	
	/**
	 * Initialize Coupons
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("coupons"));
	}
	
	/**
	 * Fetches a list of all coupons for a given company
	 *
	 * @param int $company_id The ID of the company to fetch coupons for
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return mixed An array of stdClass objects representing coupons, false if no coupons found
	 */
	public function getList($company_id, $page=1, $order_by=array('code'=>"ASC")) {
		$this->Record = $this->getCoupons($company_id);
		
		// Fetch coupons
		$coupons = $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
		
		// Set each coupon currency and amount
		for ($i=0, $num_coupons=count($coupons); $i<$num_coupons; $i++)
			$coupons[$i]->discounts = $this->getCouponAmounts($coupons[$i]->id);
		
		return $coupons;
	}
	
	/**
	 * Returns the total number of coupons returned from Coupons::getList(),
	 * useful in constructing pagination for the getList() method.
	 *
	 * @param int $company_id The ID of the company to fetch coupons for
	 * @return int The total number of coupons
	 * @see Coupons::getList()
	 */
	public function getListCount($company_id) {
		$this->Record = $this->getCoupons($company_id);
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Partially constructs the query required by both Coupons::getList() and
	 * Coupons::getListCount()
	 *
	 * @param int $company_id The company ID to fetch coupons for
	 * @return Record The partially constructed query Record object
	 */
	private function getCoupons($company_id) {
		$fields = array("coupons.*");
		
		$this->Record->select($fields)->from("coupons")->
			where("coupons.company_id", "=", $company_id);
		
		return $this->Record;
	}
	
	/**
	 * Fetches a list of currencies and amounts associated with a given coupon
	 *
	 * @param int $coupon_id The ID of the coupon whose amounts to fetch
	 * @return array An array of objects representing each coupon amount
	 */
	public function getCouponAmounts($coupon_id) {
		$fields = array("coupon_amounts.currency", "coupon_amounts.amount", "coupon_amounts.type");
		
		return $this->Record->select($fields)->from("coupons")->
			innerJoin("coupon_amounts", "coupon_amounts.coupon_id", "=", "coupons.id", false)->
			where("coupons.id", "=", $coupon_id)->
			group("coupon_amounts.currency")->fetchAll();
	}
	
	/**
	 * Fetches a coupon using the given $coupon_id
	 * @param int $coupon_id The ID of the coupon to fetch
	 * @return mixed A stdClass object representing the coupon, false if no such coupon exists
	 */
	public function get($coupon_id) {
		#
		# TODO: set a "state" field that contains the state of this coupon
		# either active, pending, or inactive based on whether max qty was reached, the
		# end_date has passed, or the start_date hasn't yet been reached, etc.
		#
		$fields = array("coupons.*");
		
		$coupon = $this->Record->select($fields)->from("coupons")->
			where("coupons.id", "=", $coupon_id)->fetch();
			
		if ($coupon) {
			$coupon->amounts = $this->getAmounts($coupon->id);
			$coupon->packages = $this->getPackages($coupon->id);
		}
		
		return $coupon;
	}
	
	/**
	 * Retrieves a coupon by its code
	 *
	 * @param string $code The coupon code representing the coupon to fetch
	 * @return mixed A stdClass object representing the coupon, or false if no such coupon exists
	 */
	public function getByCode($code) {
		$coupon = $this->Record->select(array("coupons.*"))->from("coupons")->
			where("coupons.code", "=", trim($code))->
			where("coupons.company_id", "=", Configure::get("Blesta.company_id"))->
			fetch();
			
		if ($coupon) {
			$coupon->amounts = $this->getAmounts($coupon->id);
			$coupon->packages = $this->getPackages($coupon->id);
		}
		
		return $coupon;
	}
	
	/**
	 * Fetches a coupon using the given code or coupon ID. Only returns coupons
	 * that are active and capable of being used for the current company.
	 *
	 * @param string $code The coupon code to fetch
	 * @param string $coupon_id The coupon ID to fetch
	 * @param array An array of package IDs to attempt to use with the coupon. If the coupon is inclusive all packages configured for the coupon must be present.
	 * @return mixed A stdClass object representing the coupon, false if no such coupon exists of the coupon is no longer valid
	 */
	public function getForPackages($code=null, $coupon_id=null, array $packages=null) {
		$fields = array("coupons.*");
		
		$this->Record->select($fields)->from("coupons")->
			where("coupons.company_id", "=", Configure::get("Blesta.company_id"))->
			where("coupons.status", "=", "active")->
			open()->
				where("coupons.max_qty", "=", 0)->
				orWhere("coupons.max_qty", ">", "coupons.used_qty", false)->
			close()->
			open()->
				where("coupons.start_date", "<=", date("Y-m-d H:i:s"))->
				orWhere("coupons.start_date", "=", null)->
			close()->
			open()->
				where("coupons.end_date", ">", date("Y-m-d H:i:s"))->
				orWhere("coupons.end_date", "=", null)->
			close();
			
		if ($packages) {
			$this->Record->innerJoin("coupon_packages", "coupon_packages.coupon_id", "=", "coupons.id", false)->
				where("coupon_packages.package_id", "in", $packages)->
				group("coupons.id");
		}
		
		if ($coupon_id)
			$this->Record->where("coupons.id", "=", $coupon_id);
		else
			$this->Record->where("coupons.code", "=", trim($code));
		
		
		$coupon = $this->Record->fetch();
		
		
		if ($packages && $coupon) {
			$coupon->amounts = $this->getAmounts($coupon->id);
			$coupon->packages = $this->getPackages($coupon->id);
			
			// If the coupon is inclusive it requires that all packages be present in order to be used
			if ($coupon->type == "inclusive") {
				foreach ($coupon->packages as $package) {
					if (!in_array($package->package_id, (array)$packages)) {
						$coupon = false;
						break;
					}
				}
			}
		}
		
		return $coupon;
	}
	
	/**
	 * Retrieves the given coupon and the recurring coupon amount iff it recurs and applies to the given currency
	 * at the given date
	 *
	 * @param int $coupon_id The ID of the coupon to fetch
	 * @param string $currency The ISO 4217 currency code
	 * @param string $date The date at which to check that the coupon will apply
	 * @return mixed An stdClass object representing the coupon and the recurring amount, or false otherwise
	 */
	public function getRecurring($coupon_id, $currency, $date) {
		// Fetch the coupon
		$coupon = $this->get($coupon_id);
		$date = $this->Date->toTime($date);
		
		// Check that the coupon is active and recurring
		if ($coupon && $coupon->status == "active" && $coupon->recurring == "1") {
			
			// Determine whether the recurring coupon applies considering its set limitations
			$coupon_applies = true;
			if ($coupon->limit_recurring == "1") {
				// Max quantity may be 0 for unlimited uses, otherwise it must be larger than the used quantity to apply
				$coupon_qty_reached = ($coupon->max_qty == "0" ? false : $coupon->used_qty >= $coupon->max_qty);
				if ($coupon_qty_reached || $date < $this->Date->toTime($coupon->start_date) || $date > $this->Date->toTime($coupon->end_date)) {
					$coupon_applies = false;
				}
			}
			
			// Determine whether a coupon amount exists in the given currency
			$amount = null;
			foreach ($coupon->amounts as $coupon_amount) {
				if ($coupon_amount->currency == $currency) {
					$amount = $coupon_amount;
					break;
				}
			}
			
			// Return the coupon
			if ($coupon_applies && $amount) {
				$coupon->recurring_amount = $amount;
				return $coupon;
			}
		}
		
		return false;
	}
	
	/**
	 * Retrieves all packages associated with a given coupon
	 *
	 * @param int $coupon_id The coupon ID
	 * @return array An array of stdClass object representing package IDs
	 */
	private function getPackages($coupon_id) {
		$fields = array("coupon_id", "package_id");
		return $this->Record->select($fields)->from("coupon_packages")->
			where("coupon_id", "=", $coupon_id)->fetchAll();
	}

	/**
	 * Retrieves all packages associated with a given coupon
	 *
	 * @param int $coupon_id The coupon ID
	 * @return array An array of stdClass object representing coupon pricing
	 */
	private function getAmounts($coupon_id) {
		$fields = array("coupon_id", "currency", "amount", "type");
		return $this->Record->select($fields)->from("coupon_amounts")->
			where("coupon_id", "=", $coupon_id)->fetchAll();
	}
	
	/**
	 * Creates a new coupon
	 *
	 * @param array $vars An array of coupon information including:
	 * 	- code The coupon code
	 * 	- company_id The company ID this coupon belongs to
	 * 	- used_qty The number of times this coupon has been used (optional, default 0)
	 * 	- max_qty The maximum number of times this coupon can be used (optional, default 0 for unlimited)
	 * 	- start_date The date this coupon goes into effect (optional)
	 * 	- end_date The date this coupon is no longer effective (optional)
	 * 	- status The status of the coupon, 'active' or 'inactive' (optional, default 'active')
	 * 	- type The type of coupon, 'inclusive' must order all packages in package for coupon to apply, 'exclusive' no restriction on coupon application (optional, default 'exclusive')
	 * 	- recurring Allows the coupon to be applied every time the service renews, restrictions on start_date/end_date/used_qty/max_qty/type do not apply. If the coupon was applied originally, it will continue to be applied so long as status is 'active' and the package is still tied to this coupon.
	 * 	- limit_recurring Allows the coupon to be used again every time a renewing service that uses it renews (1 to increase the used quantity each time a renewing service renews, 0 for renewing services to use this coupon only once. optional, default 0)
	 * 	- packages A numerically indexed array containing package IDs this coupon applies to:
	 * 	- amounts An array of discounts for this coupon containing (only one per currency):
	 * 		- currency The ISO 4217 currency code
	 * 		- amount The amount of the discount
	 * 		- type The type of discount 'amount' a currency amount, 'percent' a percentage (optional, default 'percent')
	 * @return int The ID code for this coupon
	 */
	public function add(array $vars) {
		unset($vars['coupon_id']);
		$this->Input->setRules($this->getRules($vars));
		
		if ($this->Input->validates($vars)) {
			// Add coupon
			$fields = array("code", "company_id", "used_qty", "max_qty", "start_date",
				"end_date", "status", "type", "recurring", "limit_recurring", "apply_package_options"
			);
			$this->Record->insert("coupons", $vars, $fields);
			
			$coupon_id = $this->Record->lastInsertId();
			
			// Add coupon amounts
			$fields = array("coupon_id", "currency", "amount", "type");
			for ($i=0; $i<count($vars['amounts']); $i++) {
				$vars['amounts'][$i]['coupon_id'] = $coupon_id;
				$this->Record->insert("coupon_amounts", $vars['amounts'][$i], $fields);
			}
			
			// Add package IDs
			$fields = array("coupon_id", "package_id");
			for ($i=0; $i<count($vars['packages']); $i++) {
				$packages = array(
					'coupon_id' => $coupon_id,
					'package_id' => $vars['packages'][$i]
				);
				$this->Record->insert("coupon_packages", $packages, $fields);
			}
			
			return $coupon_id;
		}
	}
	
	/**
	 * Updates an existing coupon
	 *
	 * @param int $coupon_id The ID of the coupon to update
	 * @param array $vars An array of coupon information including:
	 * 	- code The coupon code
	 * 	- company_id The ID of the company this coupon belongs to
	 * 	- used_qty The number of times this coupon has been used (optional, default 0)
	 * 	- max_qty The maximum number of times this coupon can be used (optional, default 0 for unlimited)
	 * 	- start_date The date this coupon goes into effect (optional)
	 * 	- end_date The date this coupon is no longer effective (optional)
	 * 	- status The status of the coupon, 'active' or 'inactive' (optional, default 'active')
	 * 	- type The type of coupon, 'inclusive' must order all packages in package for coupon to apply, 'exclusive' no restriction on coupon application (optional, default 'exclusive')
	 * 	- recurring Allows the coupon to be applied every time the service renews, restrictions on start_date/end_date/used_qty/max_qty/type do not apply. If the coupon was applied originally, it will continue to be applied so long as status is 'active' and the package is still tied to this coupon.
	 * 	- limit_recurring Allows the coupon to be used again every time a renewing service that uses it renews (1 to increase the used quantity each time a renewing service renews, 0 for renewing services to use this coupon only once. optional, default 0)
	 * 	- packages A numerically indexed array containing package IDs this coupon applies to:
	 * 	- amounts An array of discounts for this coupon containing (only one per currency):
	 * 		- currency The ISO 4217 currency code
	 * 		- amount The amount of the discount
	 * 		- type The type of discount 'amount' a currency amount, 'percent' a percentage (optional, default 'percent')
	 * @return int The ID code for this coupon
	 */
	public function edit($coupon_id, array $vars) {
		$vars['coupon_id'] = $coupon_id;
		$rules = $this->getRules($vars, true);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Update coupon
			$fields = array("code", "company_id", "used_qty", "max_qty", "start_date",
				"end_date", "status", "type", "recurring", "limit_recurring", "apply_package_options"
			);
			
			$this->Record->where("id", "=", $coupon_id)->update("coupons", $vars, $fields);
			
			// Delete old coupon amounts
			$this->Record->from("coupon_amounts")->where("coupon_id", "=", $coupon_id)->delete();
			
			// Insert new coupon amounts
			$fields = array("coupon_id", "currency", "amount", "type");
			for ($i=0; $i<count($vars['amounts']); $i++) {
				$vars['amounts'][$i]['coupon_id'] = $coupon_id;
				$this->Record->insert("coupon_amounts", $vars['amounts'][$i], $fields);
			}
			
			// Delete old coupon package IDs
			$this->Record->from("coupon_packages")->where("coupon_id", "=", $coupon_id)->delete();
			
			// Insert new coupon package IDs
			$fields = array("coupon_id", "package_id");
			for ($i=0, $num_packages=count($vars['packages']); $i<$num_packages; $i++) {
				$packages = array(
					'coupon_id' => $coupon_id,
					'package_id' => $vars['packages'][$i]
				);
				$this->Record->insert("coupon_packages", $packages, $fields);
			}
			
			return $coupon_id;
		}
	}
	
	/**
	 * Permanently removes the coupon from the system
	 *
	 * @param int $coupon_id The ID of the coupon to delete
	 */
	public function delete($coupon_id) {
		// Delete from coupons, coupon_amounts, coupon_packages,
		$this->Record->from("coupons")->from("coupon_amounts")->from("coupon_packages")->
			where("coupons.id", "=", $coupon_id)->where("coupons.id", "=", "coupon_amounts.coupon_id", false)->
			where("coupons.id", "=", "coupon_packages.coupon_id", false)->delete(array("coupons.*", "coupon_amounts.*", "coupon_packages.*"));
		
		// Update services where coupon_id = $coupon_id, set to NULL
		$this->Record->set("services.coupon_id", "=", null, false)->where("services.coupon_id", "=", $coupon_id)->update("services");
	}
	
	/**
	 * Increments the used quantity on the given coupon
	 *
	 * @param int $coupon_id The ID of the coupon whose used quantity to increment
	 */
	public function incrementUsage($coupon_id) {
		if (($coupon = $this->get($coupon_id))) {
			$rules = array(
				'max_qty' => array(
					'exceeded' => array(
						'if_set' => true,
						'rule' => array("compares", ">", $coupon->used_qty),
						'message' => $this->_("Coupons.!error.max_qty.exceeded")
					)
				)
			);
			
			// Ignore validating the rule if the max quantity is 0 (unlimited)
			$vars = array('max_qty' => $coupon->max_qty);
			if ($coupon->max_qty == "0") {
				$vars = array();
			}
			
			$this->Input->setRules($rules);
			
			if ($this->Input->validates($vars)) {
				// Increment the used quantity
				$this->Record->where("id", "=", $coupon_id)->
					update("coupons", array('used_qty' => ($coupon->used_qty + 1)));
			}
		}
	}
	
	/**
	 * Retrieves a list of coupon types
	 *
	 * @return array Key=>value pairs of coupon types
	 */
	public function getTypes() {
		return array(
			'inclusive'=>$this->_("Coupons.getTypes.inclusive"),
			'exclusive'=>$this->_("Coupons.getTypes.exclusive")
		);
	}
	
	/**
	 * Retrieves a list of coupon amount types
	 *
	 * @return array Key=>value pairs of coupon amount types
	 */
	public function getAmountTypes() {
		return array(
			'amount'=>$this->_("Coupons.getAmountTypes.amount"),
			'percent'=>$this->_("Coupons.getAmountTypes.percent")
		);
	}
	
	/**
	 * Validates a coupon's 'status' field
	 *
	 * @param string $status The status to check
	 * @return boolean True if validated, false otherwise
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
	 * Validates a coupon's 'type' field
	 *
	 * @param string $type The type to check
	 * @return boolean True if validated, false otherwise
	 */
	public function validateType($type) {
		switch ($type) {
			case "inclusive":
			case "exclusive":
				return true;
		}
		return false;
	}
	
	/**
	 * Validates a coupon amount's 'type' field
	 *
	 * @param string $type The type to check
	 * @return boolean True if validated, false otherwise
	 */
	public function validateAmountType($type) {
		switch ($type) {
			case "amount":
			case "percent":
				return true;
		}
		return false;
	}
	
		
	/**
	 * Validates an array of coupon discounts to check for duplicate currencies.
	 *
	 * @param array $vars An indexed array of discount options including:
	 * 	- currency The currency code as defined in ISO 4217
	 * 	- type The type of discount ("amount" or "percent")
	 * 	- amount The numeric amount of this discount
	 * @return boolean True if each currency is unique, false otherwise
	 */
	public function validateAmountDuplicates(array $vars) {
		$num_discounts = count($vars);
		
		// Set all currencies
		$currencies = array();
		for ($i=0; $i<$num_discounts; $i++) {
			$currencies[] = $vars[$i]['currency'];
		}
		
		$num_currencies = count($currencies);
		$num_unique_currencies = count(array_unique($currencies));
		
		// Length should remain the same
		if ($num_currencies === $num_unique_currencies)
			return true;
		return false;
	}
	
	/**
	 * Validates that inclusive recurring coupons of discount type "Amount" do not exist.
	 * This prohibits the possibility of amount discounts being applied to each service
	 * with a recurring coupon.
	 *
	 * @param array $amounts An indexed array of discount options including:
	 * 	- currency The currency code as defined in ISO 4217
	 * 	- type The type of discount ("amount" or "percent")
	 * 	- amount The numeric amount of this discount
	 * @param string $recurring Whether this coupon is set to be recurring (1 or 0)
	 * @param string $type The coupon type ("inclusive" or "exclusive")
	 * @return boolean True if this coupon is not set as an inclusive recurring coupon that contains any discounts of type "Amount", or false otherwise
	 */
	public function validateInclusiveRecurring(array $amounts, $recurring, $type) {
		// Check for a recurring inclusive amount discount
		if ($recurring == "1" && $type == "inclusive") {
			foreach ($amounts as $amount) {
				if ($amount['type'] == "amount")
					return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Validates whether the given coupon code is currently in use
	 *
	 * @param string $coupon_code The coupon code to validate
	 * @param mixed $coupon_id The ID of the coupon the given coupon code must represent, or null to not require any
	 * @return boolean True if the given coupon code is unique, or false otherwise
	 */
	public function validateUniqueCode($coupon_code, $coupon_id = null) {
		$this->Record->select()->from("coupons")->
			where("code", "=", $coupon_code)->
			where("company_id", "=", Configure::get("Blesta.company_id"));
		
		if ($coupon_id) {
			$this->Record->where("id", "!=", $coupon_id);
		}
		
		return ($this->Record->numResults() <= 0);
	}
	
	/**
	 * Returns the rule set for adding/editing coupons
	 *
	 * @param array $vars A list of input vars
	 * @param boolean $edit True to get the edit rules, false for the add rules
	 * @return array Coupon rules
	 */
	private function getRules(array $vars, $edit=false) {
		// Get the discount types
		$discount_types = $this->getAmountTypes();
		
		$rules = array(
			// Coupon rules
			'code' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Coupons.!error.code.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 64),
					'message' => $this->_("Coupons.!error.code.length")
				),
				'unique' => array(
					'rule' => array(array($this, "validateUniqueCode"), $this->ifSet($vars['coupon_id'], null)),
					'message' => $this->_("Coupons.!error.code.unique")
				)
			),
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("Coupons.!error.company_id.exists")
				)
			),
			'used_qty' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Coupons.!error.used_qty.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 10),
					'message' => $this->_("Coupons.!error.used_qty.length")
				)
			),
			'max_qty' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Coupons.!error.max_qty.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 10),
					'message' => $this->_("Coupons.!error.max_qty.length")
				)
			),
			'start_date' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'message' => $this->_("Coupons.!error.start_date.format"),
					'post_format' => array(array($this, "dateToUtc"))
				)
			),
			'end_date' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'message' => $this->_("Coupons.!error.end_date.format"),
					'post_format' => array(array($this, "dateToUtc"))
				)
			),
			'status' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStatus")),
					'message' => $this->_("Coupons.!error.status.format")
				)
			),
			'type' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateType")),
					'message' => $this->_("Coupons.!error.type.format")
				)
			),
			'recurring' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Coupons.!error.recurring.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 1),
					'message' => $this->_("Coupons.!error.recurring.length")
				)
			),
			'limit_recurring' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Coupons.!error.limit_recurring.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 1),
					'message' => $this->_("Coupons.!error.limit_recurring.length")
				)
			),
			'apply_package_options' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("in_array", array(0, 1)),
					'message' => $this->_("Coupons.!error.apply_package_options.format")
				)
			),
			// Coupon Package rules
			'packages[]' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "packages"),
					'message' => $this->_("Coupons.!error.packages[].exists")
				)
			),
			// Coupon Amounts rules
			'amounts' => array(
				'duplicate' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateAmountDuplicates")),
					'message' => $this->_("Coupons.!error.amounts.exists")
				),
				'recurring' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateInclusiveRecurring"), $this->ifSet($vars['recurring']), $this->ifSet($vars['type'])),
					'message' => $this->_("Coupons.!error.amounts.recurring", $discount_types['amount'])
				)
			),
			'amounts[][currency]' => array(
				'length' => array(
					'if_set' => true,
					'rule' => array("matches", "/^(.*){3}$/"),
					'message' => $this->_("Coupons.!error.amounts[][currency].length")
				)
			),
			'amounts[][amount]' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Coupons.!error.amounts[][amount].format")
				)
			),
			'amounts[][type]' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateAmountType")),
					'message' => $this->_("Coupons.!error.amounts[][type].format")
				)
			)
		);
		
		// Set edit-specific rules
		if ($edit) {
			$rules['packages[]']['exists']['if_set'] = true;
			$rules['coupon_id'] = array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "coupons"),
					'message' => $this->_("Coupons.!error.coupon_id.exists")
				)
			);
		}
		
		return $rules;
	}
}
?>