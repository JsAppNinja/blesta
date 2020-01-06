<?php
/**
 * Order Type abstract class that all order types must extend.
 *
 * An order type may request special configuration options when being used to
 * create an order form. The order type is invoked during each step of the order
 * process and may intervene by settings errors, or altering user submitted
 * data.
 *
 * @package blesta
 * @subpackage blesta.plugins.order.lib
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
abstract class OrderType {
	/**
	 * @var SessionCart The SessionCart used by the order form
	 */
	protected $cart;
	/**
	 * @var stdClass The order form currently in use
	 */
	protected $order_form;
	/**
	 * @var string The base URI
	 */
	public $base_uri;
	
	/**
	 * Returns the name of this order type
	 *
	 * @return string The common name of this order type
	 */
	abstract public function getName();

	/**
	 * Returns the name and URL for the authors of this order type
	 *
	 * @return array The name and URL of the authors of this order type
	 */
	abstract public function getAuthors();
	
	/**
	 * Create and return the view content required to modify the custom settings of this order form
	 *
	 * @param array $vars An array of order form data (including meta data unique to this form type) to be updated for this order form
	 * @return string HTML content containing the fields to update the meta data for this order form
	 */
	public function getSettings(array $vars = null) {
		return null;
	}
	
	/**
	 * Validates the given data (settings) to be updated for this order form
	 *
	 * @param array $vars An array of order form data (including meta data unique to this form type) to be updated for this order form
	 * @return array The order form data to be updated in the database for this order form, or reset into the form on failure
	 */
	public function editSettings(array $vars) {
		return $vars;
	}
	
	/**
	 * Determines whether or not the order type requires the perConfig step of
	 * the order process to be invoked.
	 *
	 * @return boolean If true will invoke the preConfig step before selecting a package, false to continue to the next step
	 */
	public function requiresPreConfig() {
		return false;
	}
	
	/**
	 * Set whether or not signup is allowed with an empty cart
	 *
	 * @deprecated since version 1.3.0
	 * @return boolean True if the order type requires the cart be non-empty before allowing account signup, false otherwise
	 */
	public function requriesItemsOnSignup() {
		return true;
	}
	
	/**
	 * Determines whether or not the order type supports multiple package groups or just a single package group
	 *
	 * @return mixed If true will allow multiple package groups to be selected, false allows just a single package group, null will not allow package selection
	 */
	public function supportsMultipleGroups() {
		return true;
	}
	
	/**
	 * Determines whether or not the order type supports accepting payments
	 *
	 * @return boolean If true will allow currencies and gateways to be selected for the order type
	 */
	public function supportsPayments() {
		return true;
	}
	
	/**
	 * Sets the SessionCart being used by the order form
	 *
	 * @param SessionCart $cart The session cart being used by the order form
	 */
	public function setCart(SessionCart $cart) {
		$this->cart = $cart;
	}
	
	/**
	 * Sets the order form in use
	 * 
	 * @param stdClass $order_form The order form currently being used
	 */
	public function setOrderForm($order_form) {
		$this->order_form = $order_form;
	}
	
	/**
	 * Handle an HTTP request. This allows an order template to execute custom code
	 * for the order type being used, allowing tighter integration between the order type and the template.
	 * This can be useful for supporting AJAX requests and the like. May set Input errors.
	 *
	 * @param array $get All GET request parameters
	 * @param array $post All POST request parameters
	 * @param array $files All FILES request parameters
	 * @return string HTML content to render (if any)
	 */
	public function handleRequest(array $get = null, array $post = null, array $files = null) {

	}
	
	/**
	 * Notifies the order type that the given action is complete, and allows
	 * the otder type to modify the URI the user is redirected to
	 *
	 * @param string $action The controller.action completed
	 * @param array $params An array of optional key/value pairs specific to the given action
	 * @return string The URI to redirec to, null to redirect to the default URI
	 */
	public function redirectRequest($action, array $params = null) {
		return null;
	}
	
	/**
	 * Returns all package groups that are valid for this order form
	 *
	 * @return A numerically indexed array of package group IDs
	 */
	public function getGroupIds() {
		$group_ids = array();
		if (!$this->order_form)
			return $group_ids;
		
		foreach ($this->order_form->groups as $group) {
			$group_ids[] = $group->package_group_id;
		}
		return $group_ids;
	}
	
	/**
	 * Return all validation errors encountered
	 *
	 * @return mixed Boolean false if no errors encountered, an array of errors otherwise
	 */
	public function errors() {
		if (isset($this->Input) && is_object($this->Input) && $this->Input instanceof Input)
			return $this->Input->errors();
	}
	
	/**
	 * Updates all given packages with pricing for the given currency. Evaluates
	 * the company setting to determine if package pricing can be converted based
	 * on currency conversion, or whether the package can only be offered in the
	 * configured currency. If the package pricing can not be converted automatically
	 * it will be removed.
	 *
	 * @param mixed An array of stdClass objects each representing a package, or a stdClass object representing a package
	 * @param string $currency The ISO 4217 currency code to update to
	 * @return array An array of stdClass objects each representing a package
	 */
	protected function updatePackagePricing($packages, $currency) {
		Loader::loadModels($this, array("Companies"));
		
		$multi_currency_pricing = $this->Companies->getSetting(Configure::get("Blesta.company_id"), "multi_currency_pricing");
		$allow_conversion = true;
		
		if ($multi_currency_pricing->value == "package")
			$allow_conversion = false;
			
		if (is_object($packages))
			$packages = $this->convertPackagePrice($packages, $currency, $allow_conversion);
		else {
			foreach ($packages as &$package) {
				$package = $this->convertPackagePrice($package, $currency, $allow_conversion);
			}
		}
		
		return $packages;
	}
	
	/**
	 * Convert pricing for the given package and currency
	 *
	 * @param stdClass $package A stdClass object representing a package
	 * @param string $currency The ISO 4217 currency code to update to
	 * @param boolean $allow_conversion True to allow conversion, false otherwise
	 * @return stdClass A stdClass object representing a package
	 */
	protected function convertPackagePrice($package, $currency, $allow_conversion) {
		Loader::loadModels($this, array("Packages"));
		
		$all_pricing = array();
		foreach ($package->pricing as $pricing) {
			
			$converted = false;
			if ($pricing->currency != $currency)
				$converted = true;
			
			$pricing = $this->Packages->convertPricing($pricing, $currency, $allow_conversion);
			if ($pricing) {
				if (!$converted) {
					$all_pricing[$pricing->term . $pricing->period] = $pricing;
				}
				elseif (!array_key_exists($pricing->term . $pricing->period, $all_pricing)) {
					$all_pricing[$pricing->term . $pricing->period] = $pricing;
				}
			}
		}
		
		$package->pricing = array_values($all_pricing);
		return $package;
	}
}
?>