<?php
/**
 * Presenter for pricing information to be converted into a common format
 *
 * @uses Proration Requires the Proration class be autoloaded
 * @package blesta
 * @subpackage blesta.components.json
 * @copyright Copyright (c) 2015, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class PricingPresenter {
	/**
	 * @var string The date to prorate from
	 */
	private $prorate_start_date;
	/**
	 * @var string The date to prorate to
	 */
	private $prorate_end_date;
	/**
	 * @var string The prorate type ('add' or 'remove')
	 */
	private $prorate_type;
	
	/**
	 * Sets taxes, coupons, and settings necessary for formatting data
	 * 
	 * @param array $settings An array containing all client and company settings as key/value pairs
	 * @param array $tax_rules An array of stdClass objects representing each tax rule that applies, containing:
	 * 	- id The tax ID
	 * 	- company_id The company Id
	 * 	- level The tax level
	 * 	- name The name of the tax
	 * 	- amount The tax amount
	 * 	- type The tax type (inclusive, exclusive)
	 * 	- status The tax status
	 * @param array $coupons An array of stdClass objects representing each coupon that applies, containing:
	 * 	- id The coupon ID
	 * 	- code The coupon code
	 * 	- used_qty The number of times the coupon has been used
	 * 	- max_qty The max number of coupon uses
	 * 	- start_date The date the coupon begins
	 * 	- end_date The date the coupon ends
	 * 	- status The coupon status
	 * 	- type The type of coupon as it applies to packages (exclusive, inclusive)
	 * 	- recurring 1 or 0, whether the coupon applies to recurring services
	 * 	- limit_recurring 1 or 0, whether the coupon limitations apply to recurring services
	 * 	- apply_package_options 1 or 0, whether the coupon applies to a service's package options
	 * 	- amounts An array of stdClass objects representing each coupon amount, containing:
	 * 		- coupon_id The coupon ID
	 * 		- currency The coupon amount currency
	 * 		- amount The coupon amount
	 * 		- type The coupon amount type (percent, amount)
	 * 	- packages An array of stdClass objects representing each assigned coupon package, containing:
	 * 		- coupon_id The coupon ID
	 * 		- package_id The assigned package ID
	 * @param array $options An array of custom options
	 * 	- recur Boolean true/false. Whether the pricing items are recurring, or if they are being added for the first time (default false)
	 */
	public function __construct(array $settings, array $tax_rules = array(), array $coupons = array(), array $options = array()) {
		// Load language for descriptions
		Language::loadLang("pricing_presenter", null, dirname(__FILE__) . DS . "language" . DS);
		
		// Load Date helper
		Loader::loadHelpers($this, array("CurrencyFormat", "Date"));
		$this->Date->setTimezone(Configure::get("Blesta.company_timezone"), "UTC");
		
		$this->settings = $settings;
		$this->tax_rules = $tax_rules;
		$this->coupons = $coupons;
		$this->options = $options;
	}
	
	/**
	 * Resets prorate fields
	 */
	private function reset() {
		$this->prorate_type = null;
		$this->prorate_start_date = null;
		$this->prorate_end_date = null;
	}
	
	/**
	 * Formats service pricing for a service and its service options
	 *
	 * @param stdClass $service An stdClass object representing the service, including:
	 * 	- name The service's name
	 * 	- qty The service quantity
	 * 	- override_price The service's override price
	 * 	- override_currency The service's override currency
	 * 	- date_renews The service renew date
	 * 	- options An array of service options, each option an stdClass object containing:
	 * 		- id The option value ID
	 * 		- service_id The service ID
	 * 		- option_pricing_id The option's pricing ID
	 * 		- qty The option quantity
	 * 		- option_value The option value
	 * 		- option_value_name The option value name
	 * 		- option_id The option's ID
	 * 		- option_label The option's label
	 * 		- option_name The name of the option
	 * 		- option_type The type of option
	 * 		- option_pricing_term The option's pricing term
	 * 		- option_pricing_period The option's pricing period
	 * 		- option_pricing_price The option's pricing price
	 * 		- option_pricing_setup_fee The option's pricing setup fee
	 * 		- option_pricing_currency The option's pricing currency
	 * 	- package_pricing An stdClass object representing the service's package pricing, including:
	 * 		- id The package pricing ID
	 * 		- package_id The package ID
	 * 		- term The pricing term
	 * 		- period The pricing period
	 * 		- setup_fee The pricing setup fee
	 * 		- cancel_fee The pricing cancelation fee
	 * 		- currency The pricing currency
	 * 	- package An stdClass object representing the service's package, including:
	 * 		- name The package name
	 * 		- taxable 1 or 0, whether the package is taxable
	 * 		- prorata_day The package pro rata day
	 * 		- prorata_cutoff The package pro rata cutoff day
	 * @return array An array of formatted service items
	 */
	public function formatService($service) {
		// Fetch the pricing of the current service and its package options
		$service_price = $this->getServicePriceByService($service);
		$option_prices = $this->getOptionPricesByService($service);
		
		// Mark items that are discountable
		$service_prices = $this->markDiscounts(array($service_price), $service->package, $service->date_renews);
		$option_prices = $this->markDiscounts($option_prices, $service->package, $service->date_renews, array('package_options' => true));
		
		// Mark items that are taxable
		$items = $this->markTaxes(array_merge($service_prices, $option_prices), $service->package);
		
		// Remove setup/cancel fees
		foreach ($items as $key => $item) {
			unset($items[$key]['setup'], $items[$key]['cancel']);
		}
		
		return $this->build($items);
	}
	
	/**
	 * Formats service pricing for a package and its package options
	 *
	 * @param array $vars An array of input data, including:
	 * 	- pricing_id The ID of the selected package pricing
	 * 	- override_price The new override price
	 * 	- override_currency The new override currency
	 *	- configoptions An array of config options where each key is the option ID and the value is the selected option value
	 * @param stdClass $package An stdClass object representing the package selected for use, including:
	 * 	- id The package ID
	 * 	- name The package's name
	 * 	- taxable 1 or 0, whether the package is taxable
	 * 	- prorata_day The package pro rata day
	 * 	- prorata_cutoff The package pro rata cutoff day
	 * @param stdClass $package_pricing An stdClass object representing the selected package pricing, including:
	 * 	- id The package pricing ID
	 * 	- package_id The package ID
	 * 	- term The pricing term
	 * 	- period The pricing period
	 * 	- setup_fee The pricing setup fee
	 * 	- cancel_fee The pricing cancelation fee
	 * 	- currency The pricing currency
	 * @param array $package_options An array of stdClass objects representing all package options, each including:
	 * 	- id The option ID
	 * 	- label The option label
	 * 	- name The option name
	 * 	- type The option type
	 * 	- values An array of stdClass objects representing each option value, including:
	 * 		- id The option value ID
	 * 		- option_id The option ID
	 * 		- value The option value
	 * 		- min The minimum value
	 * 		- max The maximum value
	 * 		- step The step value
	 * 		- pricing An array whose first index contains an stdClass object representing the option value pricing, including:
	 * 			- id The option value pricing ID
	 * 			- pricing_id The pricing ID
	 * 			- option_value_id The option value ID
	 * 			- term The pricing term
	 * 			- period The pricing period
	 * 			- price The option value price
	 * 			- setup_fee The option value setup fee
	 * 			- cancel_fee The option value cancelation fee
	 * 			- currency The option value currency
	 * @return array An array of formatted service items
	 */
	public function formatServiceData(array $vars, $package, $package_pricing, array $package_options) {
		// Fetch the pricing of the selected package and its package options
		$new_service_price = $this->getServicePrice($vars, $package, $package_pricing);
		$new_option_prices = $this->getOptionPrices($vars, $package_options);
		
		// Mark new items that are discountable
		$service_prices = $this->markDiscounts(array($new_service_price), $package);
		$option_prices = $this->markDiscounts($new_option_prices, $package, null, array('package_options' => true));
		
		// Mark items that are taxable
		$new_items = $this->markTaxes(array_merge($service_prices, $option_prices), $package);
		
		// Remove setup/cancel fees
		foreach ($new_items as $key => $item) {
			// Remove setup fees unless > 0
			if ($new_items[$key]['setup']['price'] <= 0) {
				unset($new_items[$key]['setup']);
			}
			unset($new_items[$key]['cancel']);
			
			// Remove the item if it has no quantity
			if ($item['item']['qty'] == 0) {
				unset($new_items[$key]);
			}
		}
		
		return $this->build(array_values($new_items));
	}
	
	/**
	 * Formats service pricing changes given the current service and input to change the service to
	 * 
	 * @param stdClass $service An stdClass object representing the original service, including:
	 * 	- name The service's name
	 * 	- qty The service quantity
	 * 	- override_price The service's override price
	 * 	- override_currency The service's override currency
	 * 	- date_renews The service renew date
	 * 	- options An array of service options, each option an stdClass object containing:
	 * 		- id The option value ID
	 * 		- service_id The service ID
	 * 		- option_pricing_id The option's pricing ID
	 * 		- qty The option quantity
	 * 		- option_value The option value
	 * 		- option_value_name The option value name
	 * 		- option_id The option's ID
	 * 		- option_label The option's label
	 * 		- option_name The name of the option
	 * 		- option_type The type of option
	 * 		- option_pricing_term The option's pricing term
	 * 		- option_pricing_period The option's pricing period
	 * 		- option_pricing_price The option's pricing price
	 * 		- option_pricing_setup_fee The option's pricing setup fee
	 * 		- option_pricing_currency The option's pricing currency
	 * 	- package_pricing An stdClass object representing the service's package pricing, including:
	 * 		- id The package pricing ID
	 * 		- package_id The package ID
	 * 		- term The pricing term
	 * 		- period The pricing period
	 * 		- setup_fee The pricing setup fee
	 * 		- cancel_fee The pricing cancelation fee
	 * 		- currency The pricing currency
	 * 	- package An stdClass object representing the service's package, including:
	 * 		- name The package name
	 * 		- taxable 1 or 0, whether the package is taxable
	 * 		- prorata_day The package pro rata day
	 * 		- prorata_cutoff The package pro rata cutoff day
	 * @param array $vars An array of input data, including:
	 * 	- pricing_id The ID of the selected package pricing
	 * 	- override_price The new override price
	 * 	- override_currency The new override currency
	 *	- configoptions An array of new config options where each key is the option ID and the value is the selected option value
	 * @param stdClass $new_package An stdClass object representing the new package being changed to, including:
	 * 	- id The package ID
	 * 	- name The package's name
	 * 	- taxable 1 or 0, whether the package is taxable
	 * 	- prorata_day The package pro rata day
	 * 	- prorata_cutoff The package pro rata cutoff day
	 * @param stdClass $new_package_pricing An stdClass object representing the new service's package pricing, including:
	 * 	- id The package pricing ID
	 * 	- package_id The package ID
	 * 	- term The pricing term
	 * 	- period The pricing period
	 * 	- setup_fee The pricing setup fee
	 * 	- cancel_fee The pricing cancelation fee
	 * 	- currency The pricing currency
	 * @param array $new_package_options An array of stdClass objects representing all new service package options, each including:
	 * 	- id The option ID
	 * 	- label The option label
	 * 	- name The option name
	 * 	- type The option type
	 * 	- values An array of stdClass objects representing each option value, including:
	 * 		- id The option value ID
	 * 		- option_id The option ID
	 * 		- value The option value
	 * 		- min The minimum value
	 * 		- max The maximum value
	 * 		- step The step value
	 * 		- pricing An array whose first index contains an stdClass object representing the option value pricing, including:
	 * 			- id The option value pricing ID
	 * 			- pricing_id The pricing ID
	 * 			- option_value_id The option value ID
	 * 			- term The pricing term
	 * 			- period The pricing period
	 * 			- price The option value price
	 * 			- setup_fee The option value setup fee
	 * 			- cancel_fee The option value cancelation fee
	 * 			- currency The option value currency
	 * @return array An array of formatted items for the service, its options; the new package, and its options
	 */
	public function formatServiceChange($service, array $vars, $new_package, $new_package_pricing, array $new_package_options) {
		// Set prorate dates
		$this->prorate_start_date = date("c");
		$this->prorate_end_date = ($service->date_renews ? $service->date_renews . "Z" : null);
		// Existing service is being removed
		$this->prorate_type = "remove";
		
		// Fetch the pricing of the current service and its package options
		$service_price = $this->getServicePriceByService($service);
		$option_prices = $this->getOptionPricesByService($service);
		
		// Mark items that were discountable before (i.e. undo the discount)
		// This offsets new item discounts to avoid otherwise discounting too much
		$service_prices = $this->markDiscounts(array($service_price), $service->package);
		$option_prices = $this->markDiscounts($option_prices, $service->package, null, array('package_options' => true));
		
		// Mark items that were taxable
		$old_items = $this->markTaxes(array_merge($service_prices, $option_prices), $service->package);
		
		// Set negative pricing for old items removed
		$old_items = $this->negativePricing($old_items);
		
		// Remove setup/cancel fees from all old items
		foreach ($old_items as $key => $item) {
			unset($old_items[$key]['setup'], $old_items[$key]['cancel']);
		}
		
		// New input data is being added
		$this->prorate_type = "add";
		
		// Fetch the pricing of the selected package and its package options
		$new_service_price = $this->getServicePrice($vars, $new_package, $new_package_pricing, $service->name);
		$new_option_prices = $this->getOptionPrices($vars, $new_package_options, $service);
		
		// Remove options with quantity 0
		foreach ($new_option_prices as $key => $option_price) {
			if ($option_price['item']['qty'] == 0) {
				unset($new_option_prices[$key]);
			}
		}
		
		// Mark new items that are discountable
		$service_prices = $this->markDiscounts(array($new_service_price), $new_package);
		$option_prices = $this->markDiscounts($new_option_prices, $new_package, null, array('package_options' => true));
		
		// Mark items that are taxable
		$new_items = $this->markTaxes(array_merge($service_prices, $option_prices), $new_package);
		
		// Remove setup/cancel fees
		foreach ($new_items as $key => $item) {
			// Remove setup fees unless marked as new and > 0
			if (!array_key_exists("new", $item) || !$item['new'] || $new_items[$key]['setup']['price'] <= 0) {
				unset($new_items[$key]['setup']);
			}
			unset($new_items[$key]['cancel']);
		}
		
		// Reset prorate fields
		$this->reset();
		
		return $this->build(array_merge($old_items, $new_items));
	}
	
	/**
	 * Updates the given items of a single package to include tax rule IDs that apply to them
	 * 
	 * @param array An array of items
	 * @param stdClass $package An stdClass object representing the package from which all given items are based
	 */
	private function markTaxes(array $items, $package) {
		$tax_enabled = (isset($this->settings['enable_tax']) && $this->settings['enable_tax'] == "true");
		$tax_exempt = (isset($this->settings['tax_exempt']) && $this->settings['tax_exempt'] == "true");
		$setup_fee_taxable = (isset($this->settings['setup_fee_tax']) && $this->settings['setup_fee_tax'] == "true");
		$cancel_fee_taxable = (isset($this->settings['cancelation_fee_tax']) && $this->settings['cancelation_fee_tax'] == "true");
		$cascade_tax = (isset($this->settings['cascade_tax']) && $this->settings['cascade_tax'] == "true");

		// Determine whether tax applies
		if ($tax_enabled && !$tax_exempt && $package->taxable == "1") {
			$group = 0;
			$taxes = array();
			
			// Set the taxes that apply
			foreach ($this->tax_rules as $tax_rule) {
				if (!isset($taxes[$group])) {
					$taxes[$group] = array();
				}
				
				$taxes[$group][] = $tax_rule->id;
				
				// Group taxes separately if not cascading them
				if (!$cascade_tax) {
					$group++;
				}
			}
			
			foreach ($items as &$item) {
				// Set item tax
				$item['item']['tax'] = $taxes;
				
				// Set setup fee tax
				if ($setup_fee_taxable) {
					$item['setup']['tax'] = $taxes;
				}
				
				// Set cancel fee tax
				if ($cancel_fee_taxable) {
					$item['cancel']['tax'] = $taxes;
				}
			}
		}
		
		return $items;
	}
	
	/**
	 * Updates the given items of a single package to include coupon IDs that apply to them
	 *
	 * @param array $items An array of items and their pricing. Either the service item itself, or its package options (not both)
	 * @param stdClass $package The package from which the given items are based
	 * @param string $date The date at which the coupons must apply (optional, default null for the current date)
	 * @param array $options An array of options
	 * 	- package_options Boolean true/false. Whether the given items are package options or not (default false)
	 */
	private function markDiscounts(array $items, $package, $date = null, array $options = array()) {
		// Default date to now
		$date = ($date ? $date : date("c"));
		$apply_to_options = (isset($options['package_options']) ? $options['package_options'] : false);
		$coupons = $this->couponsApply($package, $date);
		
		// Remove coupons that apply if they do not support package options that are given
		if ($apply_to_options) {
			foreach ($coupons as $key => $coupon) {
				if ($coupon->apply_package_options != "1") {
					unset($coupons[$key]);
				}
			}
		}
		
		// Set only the coupon IDs that apply
		$coupon_ids = array();
		foreach ($coupons as $coupon) {
			$coupon_ids[] = $coupon->id;
		}
		
		// Set the coupons that apply to each item
		foreach ($items as &$item) {
			$item['item']['coupons'] = $coupon_ids;
			$item['setup']['coupons'] = $coupon_ids;
		}
		
		return $items;
	}
	
	/**
	 * Retrieves a list of coupons that apply to the given package
	 *
	 * @param stdClass $package An stdClass object representing the package
	 * @param string $date The coupon apply date
	 * @return array An array of coupons that apply to the package
	 */
	private function couponsApply($package, $date) {
		$coupons = array();
		
		foreach ($this->coupons as $coupon) {
			// Ensure the coupon is active
			if (!$this->couponActive($coupon, $date)) {
				continue;
			}
			
			// Ensure the coupon applies to the given package
			foreach ($coupon->packages as $coupon_package) {
				if ($package->id == $coupon_package->package_id) {
					$coupons[] = $coupon;
					break;
				}
			}
		}
		
		return $coupons;
	}
	
	/**
	 * Determines whether the given coupon is active and available for use
	 *
	 * @param stdClass $coupon An stdClass object representing the coupon
	 * @param string $date The coupon apply date
	 */
	private function couponActive($coupon, $date) {
		$active = false;
		
		if ($coupon && $coupon->status == "active") {
			// Validate recurring requirements
			if (isset($this->options['recur']) && $this->options['recur']) {
				// Coupon must recur
				$active = $this->couponRecurs($coupon, $date);
			}
			else {
				// Coupon must pass its limitations
				$active = $this->couponLimits($coupon, $date);
			}
		}
		
		return $active;
	}
	
	/**
	 * Determines whether the coupon meets its set limitations
	 *
	 * @param stdClass $coupon An stdClass object representing the coupon
	 * @param string $date The coupon apply date
	 */
	private function couponLimits($coupon, $date) {
		$valid = true;
		$date_time = $this->Date->toTime($date);
		
		// Max quantity may be 0 for unlimited uses, otherwise it must be larger than the used quantity to apply
		$coupon_qty_reached = ($coupon->max_qty == "0" ? false : $coupon->used_qty >= $coupon->max_qty);
		
		if ($coupon_qty_reached ||
			$date_time < $this->Date->toTime($coupon->start_date . "Z") ||
			$date_time > $this->Date->toTime($coupon->end_date . "Z")) {
			$valid = false;
		}
		
		return $valid;
	}
	
	/**
	 * Determines whether the coupon meets its recurring limitations
	 *
	 * @param stdClass $coupon An stdClass object representing the coupon
	 * @param string $date The coupon apply date
	 */
	private function couponRecurs($coupon, $date) {
		$recurs = false;
		
		if ($coupon->recurring == "1") {
			if ($coupon->limit_recurring != "1") {
				// No limitations on the recurring coupon
				$recurs = true;
			}
			else {
				// Coupon must pass its limitations
				$recurs = $this->couponLimits($coupon, $date);
			}
		}
		
		return $recurs;
	}
	
	/**
	 * Retrieves the coupon amount for the given coupon and currency
	 *
	 * @param int $coupon_id The ID of the coupon
	 * @param string $currency The ISO 4217 currency code
	 * @return mixed An stdClass object representing the coupon amount, or false if none exist
	 */
	private function couponAmount($coupon_id, $currency) {
		if (($coupon = $this->coupon($coupon_id))) {
			foreach ($coupon->amounts as $coupon_amount) {
				if ($coupon_amount->currency == $currency) {
					return $coupon_amount;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Retrieves the coupon matching the given ID
	 *
	 * @param int $coupon_id The ID of the coupon
	 * @return mixed An stdClass object representing the coupon, or false if none exist
	 */
	private function coupon($coupon_id) {
		foreach ($this->coupons as $coupon) {
			if ($coupon->id == $coupon_id) {
				return $coupon;
			}
		}
		
		return false;
	}
	
	/**
	 * Retrieves the tax rule for the given tax ID
	 *
	 * @param int $tax_id The ID of the tax rule
	 * @return mixed An stdClass object representing the tax rule, or false if none exist
	 */
	private function taxAmount($tax_id) {
		foreach ($this->tax_rules as $tax) {
			if ($tax->id == $tax_id) {
				return $tax;
			}
		}
		
		return false;
	}
	
	/**
	 * Combines all completed items into a formatted list of items, discounts, and taxes
	 *
	 * @param array $items A list of items
	 * @return array An array containing
	 * 	- items A formatted list of item pricing
	 * 	- discounts A formatted list of discount amounts
	 * 	- taxes A formatted list of tax amounts
	 */
	private function build(array $items) {
		// Create a final list of items including all setup fees and cancel fees
		$items = $this->buildItems($items);
		
		$formatted_items = array(
			'items' => array(),
			'discounts' => $this->buildDiscounts($items),
			'taxes' => $this->buildTaxes($items)
		);
		
		// Format item fields after discounts/taxes are built
		$fields = array("price", "qty", "description");
		foreach ($items as $key => $item) {
			$formatted_items['items'][$key] = array_intersect_key($item, array_flip($fields));
		}
		
		return $formatted_items;
	}
	
	/**
	 * Builds additional items from setup fees and cancel fees
	 * @see ::build
	 *
	 * @param array $items A list of items
	 * @return array A list of all items including items for setup fees and cancel fees
	 */
	private function buildItems(array $items) {
		$lines = array();
		
		foreach ($items as $item) {
			$lines[] = $item['item'];
			
			if (!empty($item['setup'])) {
				$lines[] = $item['setup'];
			}
			
			if (!empty($item['cancel'])) {
				$lines[] = $item['cancel'];
			}
		}
		
		return $lines;
	}
	
	/**
	 * Builds the taxes for all items
	 * @see ::build
	 *
	 * @param array $items A list of items
	 * @return array A list of taxes by group
	 */
	private function buildTaxes(array $items) {
		$all_taxes = array();
		$tax_groups = array();
		
		foreach ($items as $index => $item) {
			// Skip items without tax
			if (empty($item['tax'])) {
				continue;
			}
			
			foreach ($item['tax'] as $group => $taxes) {
				foreach ($taxes as $tax_id) {
					// Create a tax for each new tax rule
					if (!isset($all_taxes[$tax_id]) && ($tax = $this->taxAmount($tax_id))) {
						$all_taxes[$tax_id] = array(
							'amount' => $tax->amount,
							'type' => $tax->type,
							'description' => Language::_("PricingPresenter.description.tax", true, $tax->name, $tax->amount)
						);
					}
					
					// If no valid tax set, skip it
					if (empty($all_taxes[$tax_id])) {
						continue;
					}
					
					// Separate the taxes by group
					if (!array_key_exists($group, $tax_groups)) {
						$tax_groups[$group] = array();
					}
					
					// Set the tax that applies into this tax group
					if (!array_key_exists($tax_id, $tax_groups[$group])) {
						$tax_groups[$group][$tax_id] = $all_taxes[$tax_id];
						$tax_groups[$group][$tax_id]['apply'] = array();
					}
					
					// Mark this tax rule as applying to this item
					$tax_groups[$group][$tax_id]['apply'][] = $index;
				}
			}
		}
		
		return array_values($tax_groups);
	}
	
	/**
	 * Builds the discounts for all items
	 * @see ::build
	 *
	 * @param array $items A list of items
	 * @return array A list of discounts
	 */
	private function buildDiscounts(array $items) {
		$discounts = array();
		
		foreach ($items as $index => $item) {
			// Skip items with no coupons set
			if (empty($item['coupons'])) {
				continue;
			}
			
			foreach ($item['coupons'] as $coupon_id) {
				// Mark the coupon discount as applying to this item
				if (isset($discounts[$coupon_id])) {
					$discounts[$coupon_id]['apply'][] = $index;
					continue;
				}
				
				// Set a new discount
				if (($coupon = $this->coupon($coupon_id)) &&
					($coupon_amount = $this->couponAmount($coupon_id, $item['currency']))) {
					// Set the coupon description
					$description = $this->descriptionDiscount(
						$coupon->code,
						$coupon_amount->type,
						$coupon_amount->amount,
						$coupon_amount->currency
					);
					
					$discounts[$coupon_id] = array(
						'amount' => $coupon_amount->amount,
						'type' => $coupon_amount->type,
						'description' => $description,
						'apply' => array($index)
					);
				}
			}
		}
		
		return array_values($discounts);
	}
	
	/**
	 * Builds the description for a discount coupon
	 *
	 * @param string $code The coupon code
	 * @param string $type The coupon type (percent, amount)
	 * @param float $amount The coupon amount
	 * @param string $currency The ISO4217 currency code
	 * @return string The coupon description
	 */
	private function descriptionDiscount($code, $type, $amount, $currency) {
		// Set the coupon description
		$percent = ($type == "percent");
		$amount = (
			$percent
			? $amount
			: $this->CurrencyFormat->format($amount, $currency)
		);
		
		return Language::_(
			"PricingPresenter.description.coupon." . ($percent ? "percent" : "amount"),
			true,
			$code,
			$amount
		);
	}
	
	/**
	 * Updates each item to set all prices to negative values
	 *
	 * @param array $items An array of items
	 */
	private function negativePricing(array $items) {
		foreach ($items as &$item) {
			if (array_key_exists("item", $item) && $item['item']['price'] > 0) {
				$item['item']['price'] *= -1;
			}
			
			if (array_key_exists("setup", $item) && $item['setup']['price'] > 0) {
				$item['setup']['price'] *= -1;
			}
			
			if (array_key_exists("cancel", $item) && $item['cancel']['price'] > 0) {
				$item['cancel']['price'] *= -1;
			}
		}
		
		return $items;
	}
	
	/**
	 * Retrieves the price to prorate a value given the date, term, and period to prorate from
	 *
	 * @param float $amount The total cost for the given term and period
	 * @param string $start_date The start date to calculate the price from
	 * @param int $term The term length
	 * @param string $period The period type (e.g. "month", "year")
	 * @param int $pro_rata_day The day of the month to prorate to
	 * @param boolean $allow_all_recurring_periods True to allow all recurring periods, or false to limit to "month" and "year" only (optional, default false)
	 * @param string $prorate_date The date to prorate to. Setting this value will ignore the $pro_rata_day (optional)
	 * @return float The prorate price
	 */
	private function getProratePrice($amount, $start_date, $term, $period, $pro_rata_day, $allow_all_recurring_periods=false, $prorate_date = null) {
		// Return the given amount if invalid proration values were given
		if ((!is_numeric($pro_rata_day) && empty($prorate_date)) || $period == "onetime" ||
			(!$allow_all_recurring_periods && !in_array($period, array("month", "year")))) {
			return $amount;
		}
		
		// Convert date to UTC
		$start_date = $this->Date->cast($start_date, "c");
		
		// Determine the prorate price
		$Proration = $this->proration($start_date, $pro_rata_day, $term, $period);
		$Proration->setTimezone(Configure::get("Blesta.company_timezone"));
		
		// Set the prorate date if given
		if ($prorate_date) {
			$Proration->setProrateDate($this->Date->cast($prorate_date, "c"));
		}
		
		// Set periods available for proration
		if ($allow_all_recurring_periods) {
			$periods = array(Proration::PERIOD_DAY, Proration::PERIOD_WEEK, Proration::PERIOD_MONTH, Proration::PERIOD_YEAR);
			$Proration->setProratablePeriods($periods);
		}
		
		return $Proration->proratePrice($amount);
	}
	
	/**
	 * Creates a new instance of the Proration class
	 *
	 * @param string $start_date The proration start date
	 * @param int $pro_rata_day The day of the month to prorate to
	 * @param int $term The term length
	 * @param string $period The pricing period
	 * @return Proration A new instance of Proration
	 */
	private function proration($start_date, $pro_rata_day, $term, $period) {
		return new Proration($start_date, $pro_rata_day, $term, $period);
	}
	
	/**
	 * Builds the fields set for an item
	 *
	 * @param float $price The item price
	 * @param int $quantity The item quantity
	 * @param string $description The item description
	 * @param string $currency The ISO 4217 currency code
	 */
	private function itemFields($price, $quantity, $description, $currency) {
		return array(
			'price' => $price,
			'qty' => $quantity,
			'description' => $description,
			'currency' => $currency
		);
	}
	
	/**
	 * Fetches the transaction of the given term
	 *
	 * @param string $term The term language
	 * @param mixed $... Additional arguments to pass as variables to the language
	 * @return string The translated language definition
	 */
	private function buildDescription($term) {
		$args = func_get_args();
		unset($args[0]);
		$args = array_merge(array($term, true), $args);
		
		return call_user_func_array(array("Language", "_"), $args);
	}
	
	/**
	 * Retrieves the descriptions for a service item
	 *
	 * @param string $package_name The name of the package
	 * @param string $service_name The name of the service (optional)
	 * @param string $start_date The UTC start date (optional)
	 * @param string $end_date The UTC end date (optional)
	 */
	private function serviceDescription($package_name, $service_name = null, $start_date = null, $end_date = null) {
		// Set base language term
		$terms = array(
			'item' => "PricingPresenter.description.package",
			'setup' => "PricingPresenter.description.package.setup_fee",
			'cancel' => "PricingPresenter.description.package.cancel_fee"
		);
		$vars = array($package_name);
		
		// Set service language term
		if (!empty($service_name)) {
			$terms['item'] = "PricingPresenter.description.service";
			$terms['setup'] = "PricingPresenter.description.service.setup_fee";
			$terms['cancel'] = "PricingPresenter.description.service.cancel_fee";
			$vars = array($package_name, $service_name);
		}
		
		// Set prorated service language term
		if ($start_date && $end_date) {
			$this->Date->setTimezone("UTC", Configure::get("Blesta.company_timezone"));
			$start_date = $this->Date->cast($start_date, $this->settings['date_format']);
			$end_date = $this->Date->cast($end_date, $this->settings['date_format']);
			$this->Date->setTimezone(Configure::get("Blesta.company_timezone"), "UTC");
			
			$terms['item'] = "PricingPresenter.description.package.prorate." . $this->prorate_type;
			$vars = array($package_name, $start_date, $end_date);
			
			// Set service language term
			if (!empty($service_name)) {
				$terms['item'] = "PricingPresenter.description.service.prorate." . $this->prorate_type;
				$vars = array($package_name, $service_name, $start_date, $end_date);
			}
		}
		
		$item_vars = array_merge(array($terms['item']), $vars);
		$setup_vars = array_merge(array($terms['setup']), $vars);
		$cancel_vars = array_merge(array($terms['cancel']), $vars);
		
		return array(
			'item' => call_user_func_array(array(&$this, "buildDescription"), $item_vars),
			'setup' => call_user_func_array(array(&$this, "buildDescription"), $setup_vars),
			'cancel' => call_user_func_array(array(&$this, "buildDescription"), $cancel_vars)
		);
	}
	
	/**
	 * Retrieves the descriptions for a service option
	 *
	 * @param string $label The option label
	 * @param string $value The option value name
	 * @param int $qty The option quantity (optional)
	 * @param string $start_date The UTC start date (optional)
	 * @param string $end_date The UTC end date (optional)
	 */
	private function serviceOptionDescription($label, $value, $qty = null, $start_date = null, $end_date = null) {
		// Set base language term
		$terms = array(
			'item' => "PricingPresenter.description.service.option",
			'setup' => "PricingPresenter.description.service.option.setup_fee",
		);
		$vars = array($label, $value);
		
		// Set service option quantity language term
		if ($qty) {
			$terms['item'] = "PricingPresenter.description.service.option.qty";
			$vars = array($label, $qty, $value);
		}
		
		// Set prorated service option language term
		if ($start_date && $end_date) {
			$this->Date->setTimezone("UTC", Configure::get("Blesta.company_timezone"));
			$start_date = $this->Date->cast($start_date, $this->settings['date_format']);
			$end_date = $this->Date->cast($end_date, $this->settings['date_format']);
			$this->Date->setTimezone(Configure::get("Blesta.company_timezone"), "UTC");
			
			$terms['item'] = "PricingPresenter.description.service.option.prorate." . $this->prorate_type;
			$vars = array($label, $value, $start_date, $end_date);
			
			// Set service option quantity language term
			if ($qty) {
				$terms['item'] = "PricingPresenter.description.service.option.qty.prorate." . $this->prorate_type;
				$vars = array($label, $qty, $value, $start_date, $end_date);
			}
		}
		
		$item_vars = array_merge(array($terms['item']), $vars);
		
		// Prefix service options with the nested identifier
		$lang_prefix = $this->buildDescription("PricingPresenter.nested_identifier");
		
		return array(
			'item' => $lang_prefix . call_user_func_array(array(&$this, "buildDescription"), $item_vars),
			'setup' => $lang_prefix . $this->buildDescription($terms['setup'], $label, $value),
			'cancel' => ""
		);
	}
	
	/**
	 * Retrieves the service price form the given package pricing
	 *
	 * @param array An array of input data
	 * @param stdClass $package An stdClass object representing the new package
	 * @param stdClass $package_pricing An stdClass object representing the package pricing
	 * @param string $service_name The name of the service (optional)
	 * @param stdClass An stdClass object representing a package's pricing information
	 */
	private function getServicePrice(array $vars, $package, $package_pricing, $service_name = null) {
		$currency = $package_pricing->currency;
		$price = $package_pricing->price;
		
		// Set the price override, if any
		if (array_key_exists("override_price", $vars) && array_key_exists("override_currency", $vars) &&
			is_numeric($vars['override_price']) && $vars['override_currency'] !== null) {
			$currency = $vars['override_currency'];
			$price = $vars['override_price'];
		}
		
		// Prorate the item price
		if ($this->prorate_start_date && $this->prorate_end_date) {
			$price = $this->getProratePrice(
				$price,
				$this->prorate_start_date,
				$package_pricing->term,
				$package_pricing->period,
				$package->prorata_day,
				true,
				$this->prorate_end_date
			);
		}
		
		$descriptions = $this->serviceDescription($package->name, $service_name, $this->prorate_start_date, $this->prorate_end_date);
		
		return array(
			'item' => $this->itemFields($price, 1, $descriptions['item'], $currency),
			'setup' => $this->itemFields($package_pricing->setup_fee, 1, $descriptions['setup'], $currency),
			'cancel' => $this->itemFields($package_pricing->cancel_fee, 1, $descriptions['cancel'], $currency)
		);
	}
	
	/**
     * Retrieves the service price
     *
     * @param stdClass $service An stdClass object representing the service
     * @return array An array of service pricing information
     */
    private function getServicePriceByService($service) {
        $currency = $service->package_pricing->currency;
        $price = $service->package_pricing->price;

        // Set the service override price, if any
        if (is_numeric($service->override_price) && $service->override_currency !== null) {
            $currency = $service->override_currency;
            $price = $service->override_price;
        }
		
		// Prorate the item price
		if ($this->prorate_start_date && $this->prorate_end_date) {
			$price = $this->getProratePrice(
				$price,
				$this->prorate_start_date,
				$service->package_pricing->term,
				$service->package_pricing->period,
				$service->package->prorata_day,
				true,
				$this->prorate_end_date
			);
		}
		
		$descriptions = $this->serviceDescription($service->package->name, $service->name, $this->prorate_start_date, $this->prorate_end_date);
		
        return array(
			'item' => $this->itemFields($price, $service->qty, $descriptions['item'], $currency),
			'setup' => $this->itemFields($service->package_pricing->setup_fee, 1, $descriptions['setup'], $currency),
			'cancel' => $this->itemFields($service->package_pricing->cancel_fee, 1, $descriptions['cancel'], $currency),
        );
    }
	
	/**
     * Retrieves a list of service option prices from the given input
     *
     * @param array An array of input data
     * @param array $package_options An array of stdClass objects representing all package options
     * @param stdClass $service An stdClass object representing the current service, if any (optional)
     * @return array An array of package option pricing information
     */
    private function getOptionPrices(array $vars, array $package_options, $service = null) {
		// Key package options by ID
		$options = array();
		foreach ($package_options as $option) {
			$options[$option->id] = $option;
		}
		unset($package_options);
		
		// Set an array of existing option IDs
		$existing_options = array();
		$prorata_day = null;
		
		if ($service) {
			$prorata_day = $service->package->prorata_day;
			foreach ($service->options as $option) {
				$existing_options[] = $option->option_id;
			}
		}
		
		// Build each option pricing
        $prices = array();
		$vars['configoptions'] = (isset($vars['configoptions']) ? (array)$vars['configoptions'] : array());
        foreach ($vars['configoptions'] as $option_id => $value) {
			// Find the package option that matches the selected option
			if (array_key_exists($option_id, $options) && property_exists($options[$option_id], "values")) {
				$option_label = $options[$option_id]->label;
				
				// Find the package option value that matches the selected option
				foreach ($options[$option_id]->values as $option_value) {
					// Include the value if it is a quantity type, or the value matches the selected value
					$quantity_type = ($options[$option_id]->type == "quantity");
					$option_value_name = $option_value->name;
					
					if (($quantity_type || $value == $option_value->value) &&
						property_exists($option_value, "pricing") && !empty($option_value->pricing[0])) {
						$pricing = $option_value->pricing[0];
						$price = $pricing->price;
						$qty_val = ($quantity_type && !is_scalar($value) ? 0 : $value);
						$quantity = ($quantity_type ? $qty_val : 1);
						
						// Prorate the item price
						if ($this->prorate_start_date && $this->prorate_end_date) {
							$price = $this->getProratePrice(
								$price,
								$this->prorate_start_date,
								$pricing->term,
								$pricing->period,
								$prorata_day,
								true,
								$this->prorate_end_date
							);
						}
						
						$qty = ($quantity_type ? $quantity : null);
						$descriptions = $this->serviceOptionDescription($option_label, $option_value_name, $qty, $this->prorate_start_date, $this->prorate_end_date);
						
						$prices[] = array(
							'item' => $this->itemFields($price, $quantity, $descriptions['item'], $pricing->currency),
							'setup' => $this->itemFields($pricing->setup_fee, 1, $descriptions['setup'], $pricing->currency),
							'cancel' => $this->itemFields($pricing->cancel_fee, 1, $descriptions['cancel'], $pricing->currency),
							'new' => !in_array($option_id, $existing_options),
						);
						
						break;
					}
				}
			}
		}

        return $prices;
	}
	
	/**
     * Retrieves a list of service option prices from the given service
     *
     * @param stdClass $service An stdClass object representing the service
     * @param array $vars An array of input vars containing configoptions (optional)
     * @param array $package_options An array of stdClass objects representing all package options (optional)
     * @return array An array of service option pricing information
     */
    private function getOptionPricesByService($service) {
		// Build each option pricing
        $prices = array();
        foreach ($service->options as $service_option) {
			// Set pricing
			$price = $service_option->option_pricing_price;
			$setup_fee = $service_option->option_pricing_setup_fee;
			$currency = $service_option->option_pricing_currency;
			$quantity_type = ($service_option->option_type == "quantity");
			
			// Prorate the item price
			if ($this->prorate_start_date && $this->prorate_end_date) {
				$price = $this->getProratePrice(
					$price,
					$this->prorate_start_date,
					$service_option->option_pricing_term,
					$service_option->option_pricing_period,
					$service->package->prorata_day,
					true,
					$this->prorate_end_date
				);
			}
			
			$qty = ($quantity_type ? $service_option->qty  : null);
			$descriptions = $this->serviceOptionDescription($service_option->option_label, $service_option->option_value_name, $qty, $this->prorate_start_date, $this->prorate_end_date);
			
			$prices[] = array(
				'item' => $this->itemFields($price, $service_option->qty, $descriptions['item'], $currency),
				'setup' => $this->itemFields($setup_fee, 1, $descriptions['setup'], $currency),
				'cancel' => $this->itemFields(0, 1, $descriptions['cancel'], $currency),
			);
		}

        return $prices;
    }
}
