<?php
/**
 * Package Option management
 * 
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class PackageOptions extends AppModel {
	
	/**
	 * Initialize PackageOptions
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("package_options"));
	}
	
	/**
	 * Fetches all package option values (and subsequent pricing) for the given package option
	 *
	 * @param int $option_id The ID of the option whose values to retrieve
	 * @return array A list of package option values, including package option pricings
	 */
	public function getValues($option_id) {
		$values = $this->Record->select()->from("package_option_values")->
			where("option_id", "=", $option_id)->
			order(array('order' => "ASC"))->
			fetchAll();
		
		// Fetch the pricing info for each package option value
		foreach ($values as &$value) {
			$value->pricing = $this->getValuePricing($value->id);
		}
		
		return $values;
	}
	
	/**
	 * Fetches all package option groups associated with the given package option
	 *
	 * @param int $option_id The ID of the option whose groups to retrieve
	 * @return array A list of package option groups this option value is assigned to
	 */
	public function getGroups($option_id) {
		$fields = array("package_option_groups.*");
		return $this->Record->select($fields)->from("package_options")->
			innerJoin("package_option_group", "package_option_group.option_id", "=", "package_options.id", false)->
			innerJoin("package_option_groups", "package_option_groups.id", "=", "package_option_group.option_group_id", false)->
			where("package_options.id", "=", $option_id)->
			order(array('package_option_group.order' => "ASC"))->
			fetchAll();
	}
	
	/**
	 * Fetches the package option
	 *
	 * @param int $option_id The ID of the package option to fetch
	 * @return mixed A stdClass object representing the package option, false if no such option exists
	 */
	public function get($option_id) {
		$option = $this->Record->select()->from("package_options")->where("id", "=", $option_id)->fetch();
		
		if ($option) {
			$option->values = $this->getValues($option_id);
			$option->groups = $this->getGroups($option_id);
		}
		
		return $option;
	}
	
	/**
	 * Fetches the package options for the given package ID
	 *
	 * @param int $package_id The ID of the package to fetch options for
	 * @return array An array of stdClass objects, each representing a package option
	 */
	public function getByPackageId($package_id) {
		$fields = array("package_options.*");
		$options = $this->Record->select($fields)->from("package_option")->
			innerJoin("package_option_groups", "package_option_groups.id", "=", "package_option.option_group_id", false)->
			innerJoin("package_option_group", "package_option_group.option_group_id", "=", "package_option.option_group_id", false)->
			innerJoin("package_options", "package_options.id", "=", "package_option_group.option_id", false)->
			where("package_option.package_id", "=", $package_id)->
			order(array('package_option_groups.name' => "ASC", 'package_option_group.order' => "ASC"))->
			fetchAll();
			
		foreach ($options as &$option) {
			$option->values = $this->getValues($option->id);
		}
		return $options;
	}
	
	/**
	 * Fetches a package option for a specific option pricing ID.
	 * Only option pricing and values associated with the given option pricing ID will be retrieved.
	 *
	 * @param int $option_pricing_id The ID of the package option pricing value whose package option to fetch
	 * @return mixed An stdClass object representing the package option, or false if none exist
	 */
	public function getByPricingId($option_pricing_id) {
		// Fetch the option
		$fields = array("package_options.*", "package_option_pricing.option_value_id");
		$option = $this->Record->select($fields)->from("package_option_pricing")->
			innerJoin("package_option_values", "package_option_values.id", "=", "package_option_pricing.option_value_id", false)->
			innerJoin("package_options", "package_options.id", "=", "package_option_values.option_id", false)->
			where("package_option_pricing.id", "=", $option_pricing_id)->fetch();
		
		if ($option) {
			// Fetch the specific package option value
			$option->value = $this->Record->select()->from("package_option_values")->
				where("id", "=", $option->option_value_id)->
				fetch();
			
			if ($option->value)
				$option->value->pricing = $this->getValuePricingById($option_pricing_id);
		}
		
		return $option;
	}
	
	/**
	 * Fetches all package option for a given company
	 *
	 * @param int $company_id The company ID
	 * @return array An array of stdClass objects representing each package option
	 */
	public function getAll($company_id) {
		return $this->getOptions($company_id)->fetchAll();
	}
	
	/**
	 * Fetches a list of all package options for a given company
	 * 
	 * @param int $company_id The company ID to fetch package options from
	 * @param int $page The page to return results for
	 * @param array $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return array An array of objects, each representing a package option
	 */
	public function getList($company_id, $page=1, array $order_by=array('name'=>"asc")) {
		$this->Record = $this->getOptions($company_id);
		
		if ($order_by)
			$this->Record->order($order_by);
		
		return $this->Record->limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Returns the total number of packages returned from PackageOptions::getList(),
	 * useful in constructing pagination for the getList() method.
	 *
	 * @param int $company_id The company ID to fetch package options from
	 * @return int The total number of package options
	 * @see PackageOptions::getList()
	 */
	public function getListCount($company_id) {
		return $this->getOptions($company_id)->numResults();
	}
	
	/**
	 * Partially-constructs the Record object for fetching package options
	 *
	 * @param int $company_id The company ID to fetch package options from
	 * @return Record A partially-constructed Record object
	 */
	private function getOptions($company_id) {
		$fields = array("package_options.*");
		
		return $this->Record->select($fields)->from("package_options")->
			where("package_options.company_id", "=", $company_id);
	}
	
	/**
	 * Removes data from input vars that may have adverse effects on adding/editing data
	 *
	 * @param array $vars An array of package option info
	 * @return array An array of package option info without fields that are not to be set
	 */
	private function removeOptionVars(array $vars) {
		// Pricing may not have a cancel fee
		if (array_key_exists("values", $vars)) {
			foreach ($vars['values'] as $index_value => $value) {
				if (array_key_exists("pricing", $vars['values'][$index_value]) && is_array($vars['values'][$index_value]['pricing'])) {
					foreach ($vars['values'][$index_value]['pricing'] as $index => $price) {
						unset($vars['values'][$index_value]['pricing'][$index]['cancel_fee']);
					}
				}
			}
		}
		
		return $vars;
	}
	
	/**
	 * Formats option values
	 *
	 * @param array $vars An array of input containing:
	 * 	- type The type of package option
	 * 	- values An array of option values and pricing
	 * @return array An array of all given option values
	 */
	private function formatValues(array $vars) {
		$values = array();
		
		if (isset($vars['values']) && is_array($vars['values'])) {
			
			foreach ($vars['values'] as $value) {
				$temp_val = $value;
				
				// Each quantity value must be null
				if (isset($vars['type']) && $vars['type'] == "quantity") {
					$temp_val['value'] = null;
					// The max value may be null (unlimited) if blank
					$temp_val['max'] = (isset($temp_val['max']) && $temp_val['max'] != "" ? $temp_val['max'] : null);
				}
				else {
					// Each non-quantity type must have a non-null value, and null min/max/step
					$temp_val['value'] = (isset($temp_val['value']) ? $temp_val['value'] : "");
					$temp_val['min'] = null;
					$temp_val['max'] = null;
					$temp_val['step'] = null;
				}
				
				$values[] = $temp_val;
			}
		}
		
		return $values;
	}
	
	/**
	 * Adds a package option for the given company
	 *
	 * @param array $vars An array of package option info including:
	 * 	- company_id The ID of the company to assign the option to
	 * 	- label The label displayed for this option
	 * 	- name The field name for this option
	 * 	- type The field type for this option, one of:
	 * 		- select
	 * 		- checkbox
	 * 		- radio
	 * 		- quantity
	 * 	- addable 1 if the option is addable by a client
	 * 	- editable 1 if the option is editable by a client
	 * 	- values A numerically indexed array of value info including:
	 * 		- name The name of the package option value (display name)
	 * 		- value The value of the package option (optional, default null)
	 * 		- min The minimum value if type is 'quantity'
	 * 		- max The maximum value if type is 'quantity', null for unlimited quantity
	 * 		- step The step value if type is 'quantity'
	 * 		- pricing A numerically indexed array of pricing info including:
	 * 			- term The term as an integer 1-65535 (optional, default 1)
	 * 			- period The period, 'day', 'week', 'month', 'year', 'onetime' (optional, default 'month')
	 * 			- price The price of this term (optional, default 0.00)
	 * 			- setup_fee The setup fee for this package (optional, default 0.00)
	 * 			- currency The ISO 4217 currency code for this pricing (optional, default USD)
	 * 	- groups An array of package option group IDs that the option belongs to (optional)
	 * @return int The package option ID, void on error
	 */
	public function add(array $vars) {
		// Remove any pricing cancel fee. One cannot be set
		$vars = $this->removeOptionVars($vars);
		// Format option values
		$vars['values'] = $this->formatValues($vars);
		
		$this->Input->setRules($this->getRules($vars));
		
		if ($this->Input->validates($vars)) {
			$this->Record->begin();
			
			// Add the package option
			$fields = array("company_id", "label", "name", "type", "addable", "editable");
			$this->Record->insert("package_options", $vars, $fields);
			$option_id = $this->Record->lastInsertId();
			
			// Add package option values and pricing
			$this->addOptionValues($option_id, $vars['company_id'], (isset($vars['values']) ? $vars['values'] : array()));
			
			// Assign package option groups
			$this->addOptionGroups($option_id, (isset($vars['groups']) ? $vars['groups'] : array()));
			
			$this->Record->commit();
			
			return $option_id;
		}
	}
	
	/**
	 * Updates a package option
	 *
	 * @param array $vars An array of package option info including:
	 * 	- label The label displayed for this option
	 * 	- name The field name for this option
	 * 	- type The field type for this option, one of:
	 * 		- select
	 * 		- checkbox
	 * 		- radio
	 * 		- quantity
	 * 	- addable 1 if the option is addable by a client
	 * 	- editable 1 if the option is editable by a client
	 * 	- values A numerically indexed array of value info including:
	 * 		- id The ID of the package option value to update. If the ID is not given, the option value will be added. (optional, required for edit)
	 * 		- name The name of the package option value (display name). If the 'name' is empty or not given, the option value will be deleted
	 * 		- value The value of the package option (optional, default null)
	 * 		- min The minimum value if type is 'quantity'
	 * 		- max The maximum value if type is 'quantity', null for unlimited quantity
	 * 		- step The step value if type is 'quantity'
	 * 		- pricing A numerically indexed array of pricing info including:
	 * 			- id The package option pricing ID to update. If the ID is not given, the pricing will be added. (optional, required for edit)
	 * 			- term The term as an integer 1-65535 (optional, default 1). If the term is not given along with an ID, the pricing will be deleted
	 * 			- period The period, 'day', 'week', 'month', 'year', 'onetime' (optional, default 'month')
	 * 			- price The price of this term (optional, default 0.00)
	 * 			- setup_fee The setup fee for this package (optional, default 0.00)
	 * 			- currency The ISO 4217 currency code for this pricing (optional, default USD)
	 * 	- groups An array of package option group IDs that the option belongs to (optional)
	 * @return int The package option ID, void on error
	 */
	public function edit($option_id, array $vars) {
		// Remove any pricing cancel fee. One cannot be set
		$vars = $this->removeOptionVars($vars);
		$vars['option_id'] = $option_id;
		// Format option values
		$vars['values'] = $this->formatValues($vars);
		
		$this->Input->setRules($this->getRules($vars, true));
		
		if ($this->Input->validates($vars)) {
			// Fetch the option to set pricing based on its company ID
			$option = $this->get($option_id);
			$vars['company_id'] = $option->company_id;
			
			// Determine option groups
			$groups = (isset($vars['groups']) ? (array)$vars['groups'] : array());
			$current_groups = $this->getGroups($option_id);
			$remove_groups = array();
			foreach ($current_groups as $group) {
				if (!in_array($group->id, $groups)) {
					$remove_groups[] = $group->id;
				}
			}
			
			$this->Record->begin();
			
			// Add the package option
			$fields = array("label", "name", "type", "addable", "editable");
			$this->Record->where("id", "=", $option_id)->update("package_options", $vars, $fields);
			
			// Add/update/delete package option values and pricing
			$this->addOptionValues($option_id, $vars['company_id'], (isset($vars['values']) ? $vars['values'] : array()));
			
			// Assign package option groups
			$this->addOptionGroups($option_id, $groups);
			
			// Remove package option groups no longer assigned
			if (!empty($remove_groups)) {
				$this->removeFromGroup($option_id, $remove_groups);
			}
			
			$this->Record->commit();
			
			return $option_id;
		}
	}
	
	/**
	 * Permanently removes a package option from the system
	 *
	 * @param int $option_group_id The package option ID to delete
	 */
	public function delete($option_id) {
		// Delete the package option, its values, and pricing
		$this->Record->from("package_options")->
			leftJoin("package_option_values", "package_options.id", "=", "package_option_values.option_id", false)->
			leftJoin("package_option_pricing", "package_option_pricing.option_value_id", "=", "package_option_values.id", false)->
			leftJoin("pricings", "pricings.id", "=", "package_option_pricing.pricing_id", false)->
			leftJoin("package_option_group", "package_option_group.option_id", "=", "package_options.id", false)->
			where("package_options.id", "=", $option_id)->
			delete(array("package_options.*", "package_option_group.*", "package_option_values.*", "package_option_pricing.*", "pricings.*"));
	}
	
	/**
	 * Deletes all package option values and associated pricing for the given package option
	 *
	 * @param int $option_id The ID of the package option whose values and pricing to delete
	 * @param int $value_id The ID of the package option value whose value and pricing to delete (optional)
	 */
	public function deleteOptionValues($option_id, $value_id = null) {
		$this->Record->from("package_option_values")->
			leftJoin("package_option_pricing", "package_option_pricing.option_value_id", "=", "package_option_values.id", false)->
			leftJoin("pricings", "pricings.id", "=", "package_option_pricing.pricing_id", false)->
			where("package_option_values.option_id", "=", $option_id);
		
		if ($value_id)
			$this->Record->where("package_option_values.id", "=", $value_id);
		
		$this->Record->delete(array("package_option_values.*", "package_option_pricing.*", "pricings.*"));
	}
	
	/**
	 * Deletes a single package option pricing
	 *
	 * @param int $pricing_id The package option pricing ID to delete
	 */
	private function deleteOptionPricing($pricing_id) {
		$this->Record->from("package_option_pricing")->
			leftJoin("pricings", "pricings.id", "=", "package_option_pricing.pricing_id", false)->
			where("package_option_pricing.id", "=", $pricing_id)->
			delete(array("package_option_pricing.*", "pricings.*"));
	}
	
	/**
	 * Removes the given package option from the given package option groups
	 *
	 * @param int $option_id The ID of the package option
	 * @param array $option_groups A numerically-indexed array of package option group IDs of the package option groups that this option should no longer be assigned to
	 */
	public function removeFromGroup($option_id, array $option_groups) {
		$this->Record->from("package_option_group")->
			where("option_id", "=", $option_id)->
			where("option_group_id", "in", $option_groups)->
			delete(array("package_option_group.*"));
	}

	/**
	 * Save the package options values for the given option in the provided order
	 *
	 * @param int $option_id The ID of the option to order values for
	 * @param array $value_ids A numerically indexed array of value IDs
	 */	
	public function orderValues($option_id, array $value_ids) {
		for ($i=0; $i<count($value_ids); $i++) {
			$this->Record->where("id", "=", $value_ids[$i])->
				where("option_id", "=", $option_id)->
				update("package_option_values", array('order' => $i));
		}
	}
	
	/**
	 * Adds/updates/deletes package option values (and subsequent pricing) for a package option
	 *
	 * @param int $option_id The ID of the package option to add the values to
	 * @param int $company_id The ID of the company the groups must belong to
	 * @param array $values A list of option values and pricing
	 * @see PackageOptions::add(), PackageOptions::edit()
	 */
	private function addOptionValues($option_id, $company_id, array $values) {
		$num_values = count($values);
		$order = 0;
		for ($i=0; $i<$num_values; $i++) {
			
			// Delete the value if no name is given
			if (!empty($values[$i]['id']) && empty($values[$i]['name'])) {
				$this->deleteOptionValues($option_id, $values[$i]['id']);
				continue;
			}
			
			// Add the package option value
			$fields = array("option_id", "name", "value", "order", "min", "max", "step");
			
			$values[$i]['option_id'] = $option_id;
			$values[$i]['order'] = $order;
			
			// Add or update the package option value
			if (!empty($values[$i]['id'])) {
				$this->Record->where("id", "=", $values[$i]['id'])->update("package_option_values", $values[$i], $fields);
				$value_id = $values[$i]['id'];
			}
			else {
				$this->Record->insert("package_option_values", $values[$i], $fields);
				$value_id = $this->Record->lastInsertId();
			}
			
			// Add/update/delete package option pricing
			if ($value_id)
				$this->addOptionPricing($value_id, $company_id, (isset($values[$i]['pricing']) ? $values[$i]['pricing'] : array()));
			
			$order++;
		}
	}
	
	/**
	 * Adds/updates/deletes package option pricing for a specific package option value
	 *
	 * @param int $option_value_id The ID of the package option value to add pricing to
	 * @param int $company_id The ID of the company the groups must belong to
	 * @param array $pricing A list of pricing to add to the option value
	 * @see PackageOptions::add(), PackageOptions::edit(), PackageOptions::addOptionValues()
	 */
	private function addOptionPricing($option_value_id, $company_id, array $pricing) {
		if (!isset($this->Pricings))
			Loader::loadModels($this, array("Pricings"));
		
		// Add each price
		foreach ($pricing as &$price) {
			
			// Delete the pricing if no term is given (not set)
			if (!empty($price['id']) && !isset($price['term'])) {
				$this->deleteOptionPricing($price['id']);
				continue;
			}
			
			// Update the package option pricing if an ID is given
			if (!empty($price['id'])) {
				// Fetch the pricing ID to update and update it
				if (($pricing_info = $this->getValuePricingById($price['id']))) {
					$this->Pricings->edit($pricing_info->pricing_id, $price);
					continue;
				}
			}
			
			// Add the price
			$price['company_id'] = $company_id;
			$this->Pricings->add($price);
			$pricing_id = $this->Record->lastInsertId();
			
			// Associate the price as a package option price
			if ($pricing_id)
				$this->Record->insert("package_option_pricing", array('option_value_id' => $option_value_id, 'pricing_id' => $pricing_id));
		}
	}
	
	/**
	 * Associates the given package option with all of the given package option groups
	 *
	 * @param int $option_id The ID of the package option
	 * @param array $option_groups A list of package option group IDs to associate with this option
	 * @see PackageOptions::add(), PackageOptions::edit()
	 */
	private function addOptionGroups($option_id, array $option_groups) {
		
		// Associate the option with each of the option groups
		foreach ($option_groups as $option_group) {
			
			// Get max order within the selected group
			$option_order = $this->Record->select(array('MAX(order)' => "order"))->
				from("package_option_group")->
				where("option_group_id", "=", $option_group)->
				fetch();
			$order = 0;
			if ($option_order)
				$order = $option_order->order+1;
			
			$vars = array('option_id' => $option_id, 'option_group_id' => $option_group, 'order' => $order);
			$this->Record->duplicate("order", "=", "order", false)->insert("package_option_group", $vars);
		}
	}
	
	/**
	 * Retrieves a list of package option types and their language definitions
	 *
	 * @return array A key/value list of types and their language
	 */
	public function getTypes() {
		return array(
			'checkbox' => $this->_("PackageOptions.gettypes.checkbox"),
			'radio' => $this->_("PackageOptions.gettypes.radio"),
			'select' => $this->_("PackageOptions.gettypes.select"),
			'quantity' => $this->_("PackageOptions.gettypes.quantity")
		);
	}
	
	/**
	 * Retrieves a list of package pricing periods
	 *
	 * @param boolean $plural True to return language for plural periods, false for singular
	 * @return array Key=>value pairs of package pricing periods
	 */
	public function getPricingPeriods($plural=false) {
		if (!isset($this->Pricings))
			Loader::loadModels($this, array("Pricings"));
		return $this->Pricings->getPeriods($plural);
	}
	
	/**
	 * Fetch pricing for the given package option value
	 *
	 * @param int $value_id The ID of the value to fetch
	 * @return array An array of stdClass object each representing a pricing
	 */
	public function getValuePricing($value_id) {
		$fields = array("package_option_pricing.id", "package_option_pricing.pricing_id",
			"package_option_pricing.option_value_id", "pricings.term",
			"pricings.period", "pricings.price", "pricings.setup_fee",
			"pricings.cancel_fee", "pricings.currency");
		$this->Record->select($fields)->from("package_option_pricing")->
			innerJoin("pricings", "pricings.id", "=", "package_option_pricing.pricing_id", false)->
			where("package_option_pricing.option_value_id", "=", $value_id);
		
		return $this->Record->fetchAll();
	}
	
	/**
	 * Fetch pricing for the given package option pricing ID
	 *
	 * @param int $pricing_id The ID of option pricing
	 * @return mixed A stdClass object representing the value pricing, false otherwise
	 */
	public function getValuePricingById($pricing_id) {
		$fields = array("package_option_pricing.id", "package_option_pricing.pricing_id",
			"package_option_pricing.option_value_id", "pricings.term",
			"pricings.period", "pricings.price", "pricings.setup_fee",
			"pricings.cancel_fee", "pricings.currency");
		return $this->Record->select($fields)->from("package_option_pricing")->
			innerJoin("pricings", "pricings.id", "=", "package_option_pricing.pricing_id", false)->
			where("package_option_pricing.id", "=", $pricing_id)->fetch();
	}
	
	/**
	 * Fetches the value based on the given option_id and value
	 *
	 * @param int $option_id The ID of the option to fetch the value for
	 * @param string $value The value to fetch
	 * @return mixed A stdClass object representing the value, false if not such value exists
	 */
	public function getValue($option_id, $value) {
		return $this->Record->select()->from("package_option_values")->
			where("option_id", "=", $option_id)->
			open()->
				where("value", "=", $value)->
				orWhere("value", "=", null)->
			close()->fetch();
	}
	
	/**
	 * Fetches pricing for the given option with the given value
	 *
	 * @param int $value_id The ID of the option to fetch
	 * @param int $term The term to fetch fields for
	 * @param string $period The period to fetch fields for
	 * @param string $currency The currency to fetch fields for
	 * @param string $convert_currency The currency to convert to (optional)
	 * @return mixed A stdClass object representing the value pricing, false if no such pricing exists
	 */
	public function getValuePrice($value_id, $term, $period, $currency, $convert_currency = null) {
		if (!isset($this->Currencies)) {
			Loader::loadHelpers($this, array("CurrencyFormat"));
			$this->Currencies = $this->CurrencyFormat->Currencies;
		}
		
		$pricing = $this->getValuePricing($value_id);
		if (!$pricing)
			return false;

		$match = false;
		foreach ($pricing as $price) {
			if ($price->term != $term || $price->period != $period || $price->currency != $currency)
				continue;
			$match = true;
			
			// Handle currency conversion here if given
			if ($convert_currency && $convert_currency != $currency) {
				$price->price = $this->Currencies->convert($price->price, $currency, $convert_currency, Configure::get("Blesta.company_id"));
				$price->setup_fee = $this->Currencies->convert($price->setup_fee, $currency, $convert_currency, Configure::get("Blesta.company_id"));
				$price->cancel_fee = $this->Currencies->convert($price->cancel_fee, $currency, $convert_currency, Configure::get("Blesta.company_id"));
				$price->currency = $convert_currency;
			}
			break;
		}
		if ($match)
			return $price;
		return false;
	}
	
	/**
	 * Fetches prorated pricing for the given option with the given value
	 *
	 * @deprecated since 3.5.0
	 * @see PackageOptions::getValueProrateAmount
	 *
	 * @param int $value_id The ID of the option to fetch
	 * @param int $prorate_days The number of days to prorate the amount for
	 * @param int $term The term to fetch fields for
	 * @param string $period The period to fetch fields for
	 * @param string $currency The currency to fetch fields for
	 * @param string $convert_currency The currency to convert to (optional)
	 * @return mixed A stdClass object representing the value pricing, false if no such pricing exists
	 */
	public function getValueProratePrice($value_id, $prorate_days, $term, $period, $currency, $convert_currency = null) {
		$price = $this->getValuePrice($value_id, $term, $period, $currency, $convert_currency);
		
		if (!isset($this->Packages))
			Loader::loadModels($this, array("Packages"));
		
		if ($price && isset($price->price))
			$price->price = $this->Packages->getProrateAmount($price->price, $prorate_days, $term, $period);
		
		return $price;
	}
	
	/**
	 * Fetches prorated pricing for the given option with the given value
	 *
	 * @param int $value_id The ID of the option to fetch
	 * @param string $start_date The start date to prorate the price from
	 * @param int $term The term to fetch fields for
	 * @param string $period The period to fetch fields for
	 * @param int $pro_rata_day The day of the month to prorate to
	 * @param string $currency The currency to fetch fields for
	 * @param string $convert_currency The currency to convert to (optional)
	 * @return mixed A stdClass object representing the value pricing, false if no such pricing exists
	 */
	public function getValueProrateAmount($value_id, $start_date, $term, $period, $pro_rata_day, $currency, $convert_currency = null) {
		$price = $this->getValuePrice($value_id, $term, $period, $currency, $convert_currency);
		
		if (!isset($this->Packages))
			Loader::loadModels($this, array("Packages"));
		
		if ($price && isset($price->price))
			$price->price = $this->Packages->getProratePrice($price->price, $start_date, $term, $period, $pro_rata_day);
		
		return $price;
	}
	
	/**
	 * Retrieves all package options for a package given its term, period, and currency
	 * 
	 * @param int $package_id The ID of the package
	 * @param int $term The package term
	 * @param string $period The package period
	 * @param string $currency The pricing currency
	 * @param string $convert_currency The currency to convert to (optional, default null)
	 * @param array $options An array of key/value pairs for filtering options (optional, default null). May include:
	 * 	- addable Set to 1 to only include options that are addable by clients; 0 to only include options that are NOT addable by clients; otherwise every option is included
	 * 	- editable Set to 1 to only include options that are editable by clients; 0 to only include options that are NOT editable by clients; otherwise every option is included
	 * 	- allow An array of option IDs to include (i.e. white-list). An empty array would return no options. Not setting this 'option_ids' key will allow any option
	 * 	- disallow An array of option IDs not to include (i.e. black-list). An empty array would allow all options.
	 * @return array An array of package options including their values and pricing
	 */
	public function getAllByPackageId($package_id, $term, $period, $currency, $convert_currency = null, array $options = null) {
		// Rename options
		$filters = (array)$options;
		$white_list = (array_key_exists("allow", $filters));
		$black_list = (array_key_exists("disallow", $filters));
		
		$options = $this->getByPackageId($package_id);
		
		foreach ($options as $i => &$option) {
			// Skip any white-list options not given or any black-list options given
			if (($white_list && !in_array($option->id, $filters['allow'])) ||
				($black_list && in_array($option->id, $filters['disallow']))) {
				unset($options[$i]);
				continue;
			}
			
			// Remove addable/editable options that don't match the filtering criteria
			if ((array_key_exists("addable", $filters) && (($filters['addable'] == "1" && $option->addable != "1") || ($filters['addable'] == "0" && $option->addable != "0"))) ||
				(array_key_exists("editable", $filters) && (($filters['editable'] == "1" && $option->editable != "1") || ($filters['editable'] == "0" && $option->editable != "0")))) {
				unset($options[$i]);
				continue;
			}
			
			foreach ($option->values as $j => &$value) {
				$value_price = $this->getValuePrice($value->id, $term, $period, $currency, $convert_currency);
				if ($value_price)
					$value->pricing = array($value_price);
				else
					unset($option->values[$j]);
			}
			unset($value);
			$option->values = array_values($option->values);
			if (empty($option->values))
				unset($options[$i]);
		}
		
		return array_values($options);
	}
	
	/**
	 * Get option fields
	 * 
	 * @param int $package_id The ID of the package to fetch fields for
	 * @param int $term The term to fetch fields for
	 * @param string $period The period to fetch fields for
	 * @param string $currency The currency to fetch fields for
	 * @param $vars stdClass A stdClass object representing a set of post fields (optional, default null)
	 * @param string $convert_currency The currency to convert to (optional, default null)
	 * @param array $options An array of key/value pairs for filtering options (optional, default null). May include:
	 * 	- addable Set to 1 to only include options that are addable by clients; 0 to only include options that are NOT addable by clients; otherwise every option is included
	 * 	- editable Set to 1 to only include options that are editable by clients; 0 to only include options that are NOT editable by clients; otherwise every option is included
	 * 	- allow An array of option IDs to include (i.e. white-list). An empty array would return no fields. Not setting this 'option_ids' key will allow any option
	 * 	- disallow An array of option IDs not to include (i.e. black-list). An empty array would allow all options.
	 * @return ModuleFields A ModuleFields object, containg the fields to render
	 */
	public function getFields($package_id, $term, $period, $currency, $vars = null, $convert_currency = null, array $options = null) {
		if (!class_exists("ModuleFields")) {
			Loader::load(COMPONENTDIR . "modules" . DS . "module_field.php");
			Loader::load(COMPONENTDIR . "modules" . DS . "module_fields.php");
		}
		Loader::loadHelpers($this, array("CurrencyFormat"));
		$this->Currencies = $this->CurrencyFormat->Currencies;
		
		$fields = new ModuleFields();
		$options = $this->getAllByPackageId($package_id, $term, $period, $currency, $convert_currency, $options);
		
		foreach ($options as $option) {
			$field_name = "configoptions[" . $option->id . "]";
			$field_value = isset($vars->configoptions[$option->id]) ? $vars->configoptions[$option->id] : null;
			
			switch ($option->type) {
				case "checkbox":
					$value = $option->values[0];
					$pricing = $value->pricing[0];
					$id = "configoption_" . $option->id . "_" . $value->id;
					
					if ($pricing->setup_fee > 0)
						$field_label_name = Language::_("PackageOptions.getfields.label_checkbox_setup", true, $value->name, $this->CurrencyFormat->format($pricing->price, $pricing->currency), $this->CurrencyFormat->format($pricing->setup_fee, $pricing->currency));
					else
						$field_label_name = Language::_("PackageOptions.getfields.label_checkbox", true, $value->name, $this->CurrencyFormat->format($pricing->price, $pricing->currency));
					
					// Create label
					$field = $fields->label($option->label);
					// Create field label
					$field_label = $fields->label($field_label_name, $id);
					
					// Create field and attach to label
					$field->attach($fields->fieldCheckbox($field_name, $value->value, $field_value == $value->value, array('id' => $id), $field_label));
					// Set the label as a field
					$fields->setField($field);
					unset($value);
					break;
				case "radio":					
					// Create label
					$field = $fields->label($option->label);
					
					foreach ($option->values as $value) {
						$pricing = $value->pricing[0];
						$id = "configoption_" . $option->id . "_" . $value->id;
						
						if ($pricing->setup_fee > 0)
							$field_label_name = Language::_("PackageOptions.getfields.label_radio_setup", true, $value->name, $this->CurrencyFormat->format($pricing->price, $pricing->currency), $this->CurrencyFormat->format($pricing->setup_fee, $pricing->currency));
						else
							$field_label_name = Language::_("PackageOptions.getfields.label_radio", true, $value->name, $this->CurrencyFormat->format($pricing->price, $pricing->currency));
						
						// Create field label
						$field_label = $fields->label($field_label_name, $id);
						
						// Create field and attach to label
						$field->attach($fields->fieldRadio($field_name, $value->value, $field_value == $value->value, array('id' => $id), $field_label));
					}
					unset($value);
					
					// Set the label as a field
					$fields->setField($field);
					
					break;
				case "select":
					// Create label
					$id = "configoption_" . $option->id;
					$field = $fields->label($option->label, $id);
					
					$option_values = array();
					foreach ($option->values as $value) {
						$pricing = $value->pricing[0];
						
						if ($pricing->setup_fee > 0)
							$field_label_name = Language::_("PackageOptions.getfields.label_select_setup", true, $value->name, $this->CurrencyFormat->format($pricing->price, $pricing->currency), $this->CurrencyFormat->format($pricing->setup_fee, $pricing->currency));
						else
							$field_label_name = Language::_("PackageOptions.getfields.label_select", true, $value->name, $this->CurrencyFormat->format($pricing->price, $pricing->currency));
						
						$option_values[$value->value] = $field_label_name;
					}
					unset($value);
					$field->attach($fields->fieldSelect($field_name, $option_values, $field_value, array('id' => $id)));
					
					// Set the label as a field
					$fields->setField($field);
					break;
				case "quantity":
					$value = $option->values[0];
					$pricing = $value->pricing[0];
					$id = "configoption_" . $option->id . "_" . $value->id;
					
					// Set default value
					if ($field_value == "")
						$field_value = $value->min;
					
					if ($pricing->setup_fee > 0)
						$field_label_name = Language::_("PackageOptions.getfields.label_quantity_setup", true, $value->name, $this->CurrencyFormat->format($pricing->price, $pricing->currency), $this->CurrencyFormat->format($pricing->setup_fee, $pricing->currency));
					else
						$field_label_name = Language::_("PackageOptions.getfields.label_quantity", true, $value->name, $this->CurrencyFormat->format($pricing->price, $pricing->currency));
					
					// Create label
					$field = $fields->label($option->label, $id);
					// Create field label
					$field_label = $fields->label($field_label_name);
					
					// Create field and attach to label
					$field->attach(
						$fields->fieldText($field_name, (int)$field_value,
							array(
								'id' => $id, 'data-type' => "quantity", 'data-min' => $value->min,
								'data-max' => $value->max, 'data-step' => $value->step
							),
							$field_label
						)
					);
					// Set the label as a field
					$fields->setField($field);
					unset($value);
					break;
			}
		}
		return $fields;
	}
	
	/**
	 * Formats the pricing term
	 *
	 * @param int $term The term length
	 * @param string $period The period of this term
	 * @return mixed The term formatted in accordance to the period, if possible
	 */
	public function formatPricingTerm($term, $period) {
		if ($period == "onetime")
			return 0;
		return $term;
	}
	
	/**
	 * Formats options into key/value named pairs where each key is the
	 * option field name from a key/value pair array where each key is the
	 * option ID.
	 *
	 * @param array $options A key/value pair array where each key is the option ID
	 */
	public function formatOptions(array $options) {
		$formatted_options = array();
		
		foreach ($options as $option_id => $value) {
			$option = $this->get($option_id);
			if ($option)
				$formatted_options[$option->name] = $value;
		}
		
		return $formatted_options;
	}
	
	/**
	 * Formats options into configoption array elements where each key is the option ID and each value is the option's selected value
	 *
	 * @param array $options An array of stdClass objects, each representing a service option and its option_value
	 * @return array An array that contains configoptions and key/value pairs of option ID and the option's selected value
	 */
	public function formatServiceOptions(array $options) {
		$data = array();
		foreach ($options as $option)
			$data['configoptions'][$option->option_id] = $option->option_value != "" ? $option->option_value : $option->qty;
		return $data;
	}
	
	/**
	 * Validates that the term is valid for the period. That is, the term must be > 0
	 * if the period is something other than "onetime".
	 *
	 * @param int $term The Term to validate
	 * @param string $period The period to validate the term against
	 * @return boolean True if validated, false otherwise
	 */
	public function validatePricingTerm($term, $period) {
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
	public function validatePricingPeriod($period) {
		$periods = $this->getPricingPeriods();
		
		if (isset($periods[$period]))
			return true;
		return false;
	}
	
	/**
	 * Retrieves a list of rules for adding/editing package options
	 *
	 * @param array $vars A list of input vars used in validation
	 * @param boolean $edit True to fetch the edit rules, false for the add rules (optional, default false)
	 * @return array A list of rules
	 */
	private function getRules(array $vars, $edit = false) {
		$rules = array(
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("PackageOptions.!error.company_id.exists")
				)
			),
			'label' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PackageOptions.!error.label.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 128),
					'message' => $this->_("PackageOptions.!error.label.length")
				)
			),
			'name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PackageOptions.!error.name.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 128),
					'message' => $this->_("PackageOptions.!error.name.length")
				)
			),
			'type' => array(
				'valid' => array(
					'rule' => array("in_array", array_keys($this->getTypes())),
					'message' => $this->_("PackageOptions.!error.type.valid")
				)
			),
			'values' => array(
				'count' => array(
					'rule' => array(array($this, "validateOptionValueLimit"), $this->ifSet($vars['type'])),
					'message' => $this->_("PackageOptions.!error.values.count")
				),
				'select_value' => array(
					'rule' => array(array($this, "validateSelectTypeValues"), $this->ifSet($vars['type'])),
					'message' => $this->_("PackageOptions.!error.values.select_value")
				)
			),
			'values[][name]' => array(
				'empty' => array(
					'if_set' => true,
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PackageOptions.!error.values[][name].empty")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 128),
					'message' => $this->_("PackageOptions.!error.values[][name].length")
				)
			),
			'values[][value]' => array(
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 255),
					'message' => $this->_("PackageOptions.!error.values[][value].length")
				)
			),
			'values[][min]' => array(
				'valid' => array(
					'rule' => array(array($this, "validateValueMin"), $this->ifSet($vars['type'])),
					'message' => $this->_("PackageOptions.!error.values[][min].valid")
				)
			),
			'values[][max]' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateValueMax"), $this->ifSet($vars['type'])),
					'message' => $this->_("PackageOptions.!error.values[][max].valid")
				)
			),
			'values[][step]' => array(
				'valid' => array(
					'rule' => array(array($this, "validateValueStep"), $this->ifSet($vars['type'])),
					'message' => $this->_("PackageOptions.!error.values[][step].valid")
				)
			),
			'values[][pricing][][term]' => array(
				'format' => array(
					'if_set' => true,
					'pre_format' => array(array($this, "formatPricingTerm"), array('_linked'=>"values[][pricing][][period]")),
					'rule' => "is_numeric",
					'message' => $this->_("PackageOptions.!error.values[][pricing][][term].format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 5),
					'message' => $this->_("PackageOptions.!error.values[][pricing][][term].length")
				),
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validatePricingTerm"), array('_linked'=>"values[][pricing][][period]")),
					'message' => $this->_("PackageOptions.!error.values[][pricing][][term].valid")
				)
			),
			'values[][pricing][][period]' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validatePricingPeriod")),
					'message' => $this->_("PackageOptions.!error.values[][pricing][][period].format")
				)
			),
			'values[][pricing][][price]' => array(
				'format' => array(
					'if_set' => true,
					'pre_format' => array(array($this, "currencyToDecimal"), array('_linked'=>"values[][pricing][][currency]"), 4),
					'rule' => "is_numeric",
					'message' => $this->_("PackageOptions.!error.values[][pricing][][price].format")
				)
			),
			'values[][pricing][][setup_fee]' => array(
				'format' => array(
					'if_set' => true,
					'pre_format'=>array(array($this, "currencyToDecimal"), array('_linked'=>"values[][pricing][][currency]"), 4),
					'rule' => "is_numeric",
					'message' => $this->_("PackageOptions.!error.values[][pricing][][setup_fee].format")
				)
			),
			'values[][pricing][][currency]' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("matches", "/^(.*){3}$/"),
					'message' => $this->_("PackageOptions.!error.values[][pricing][][currency].format")
				)
			),
			'groups' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateGroupIds"), $this->ifSet($vars['company_id'], null)),
					'message' => $this->_("PackageOptions.!error.groups.exists")
				)
			)
		);
		
		if ($edit) {
			// Company ID may not be changed
			unset($rules['company_id']);
			
			// A valid package option is required
			$rules['option_id'] = array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "package_options"),
					'message' => $this->_("PackageOptions.!error.option_id.exists")
				)
			);
			
			// Validate any IDs that may have been given
			$rules['values[][id]'] = array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "package_option_values"),
					'message' => $this->_("PackageOptions.!error.values[][id].exists")	
				)
			);
			$rules['values[][pricing][][id]'] = array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "package_option_pricing"),
					'message' => $this->_("PackageOptions.!error.values[][pricing][][id].exists")	
				)
			);
		}
		
		return $rules;
	}
	
	/**
	 * Validates whether the given package option value given has a valid step set
	 *
	 * @param string $step The package option step value
	 * @param string $type The package option type
	 * @return boolean True if the package option value has a valid step set, or false otherwise
	 */
	public function validateValueStep($step, $type) {
		// The step for the quantity type must be at least 1
		if ($type == "quantity" && (empty($step) || $step < 1))
			return false;
		elseif ($type != "quantity" && $step !== null)
			return false;
		return true;
	}
	
	/**
	 * Validates whether the given package option value given has a valid minimum value
	 *
	 * @param string $min The package option minimum value
	 * @param string $type The package option type
	 * @return boolean True if the package option value has a valid minimum value set, or false otherwise
	 */
	public function validateValueMin($min, $type) {
		// The minimum quantity must be at least 0
		if ($type == "quantity" && $min < 0)
			return false;
		elseif ($type != "quantity" && $min !== null)
			return false;
		return true;
	}
	
	/**
	 * Validates whether the given package option value given has a valid maximum value
	 *
	 * @param string $max The package option maximum value
	 * @param string $type The package option type
	 * @return boolean True if the package option value has a valid maximum value set, or false otherwise
	 */
	public function validateValueMax($max, $type) {
		// The maximum quantity must be at least 1
		if ($type == "quantity" && ($max != null && $max < 1))
			return false;
		elseif ($type != "quantity" && $max !== null)
			return false;
		return true;
	}
	
	/**
	 * Validates whether the number of package option values is valid for the given package option type
	 *
	 * @param array $values A numerically-indexed array of package option values
	 * @param string $type The package option type
	 * @return boolean True if the number of package option values is valid for the given package option type, or false otherwise
	 */
	public function validateOptionValueLimit($values, $type) {
		// Checkbox and quantity types must have exactly 1 value
		if (in_array($type, array("checkbox", "quantity")) && (!is_array($values) || count($values) != 1))
			return false;
		return true;
	}
	
	/**
	 * Validates whether any of the given package option values contains invalid special characters
	 * for options of the 'select' type. An invalid character is determined to be one that is not equivalent
	 * to its HTML encoded version
	 *
	 * @param array $values A numerically-indexed array of package option values
	 * @param string $type The package option type
	 * @return boolean True if all package option values contain valid characters, or false otherwise
	 */
	public function validateSelectTypeValues($values, $type) {
		Loader::loadHelpers($this, array("Html"));
		
		// Only select option values are of concern because browsers don't decode them on POST,
		// so we can only allow characters that are
		if (in_array($type, array("select")) && is_array($values)) {
			foreach ($values as $value) {
				// Each value must be equivalent to its HTML-safe value
				if (is_scalar($value['value']) && $this->Html->safe($value['value']) != $value['value']) {
					return false;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Validates whether all of the given package option group IDs exist and belong to the given company
	 *
	 * @param array $groups An array of package option group IDs
	 * @param int $company_id The ID of the company the groups must belong to (optional, default null)
	 * @return boolean True if all of the given package option groups exist and belong to the given company, or false otherwise
	 */
	public function validateGroupIds($groups, $company_id = null) {
		// Groups may be empty
		if (empty($groups))
			return true;
		
		if (is_array($groups)) {
			// Check each group
			foreach ($groups as $group_id) {
				$this->Record->select(array("id"))->from("package_option_groups")->
					where("id", "=", $group_id);
				
				// Filter on company ID if given
				if ($company_id)
					$this->Record->where("company_id", "=", $company_id);
				
				$count = $this->Record->numResults();
				
				// This package option group doesn't exist
				if ($count <= 0)
					return false;
			}
		}
		else
			return false;
		
		return true;
	}
}
?>