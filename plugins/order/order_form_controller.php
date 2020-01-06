<?php
/**
 * Order Form Parent Controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.order
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class OrderFormController extends OrderController {
	
	/**
	 * @var stdClass The order form
	 */
	protected $order_form;
	/**
	 * @var object The order type object for the selected order form
	 */
	protected $order_type;
	/**
	 * @var stdClass A stdClass object representing the client, null if not logged in
	 */
	protected $client;
	/**
	 * @var string The string to prefix all custom client field IDs with
	 */
	protected $custom_field_prefix = "custom_field";
	/**
	 * @var string The cart name used for this order form
	 */
	protected $cart_name;
	
	/**
	 * Setup
	 */
	public function preAction() {
		parent::preAction();
		
		$this->Javascript->setFile("bootstrap-slider.js");
		
		$this->uses(array("Order.OrderSettings", "Order.OrderForms", "Companies", "Clients", "Currencies", "PackageGroups", "Packages", "PluginManager", "Services"));

		// Redirect if this plugin is not installed for this company
		if (!$this->PluginManager->isInstalled("order", $this->company_id))
			$this->redirect($this->client_uri);

		$default_form = $this->OrderSettings->getSetting($this->company_id, "default_form");
		
		$order_label = null;
		if ($default_form) {
			$this->order_form = $this->OrderForms->get($default_form->value);
			if ($this->order_form)
				$order_label = $this->order_form->label;
		}
		
		// Ensure that label always appears as a URI element
		if (isset($this->get[0])) {
			$order_label = $this->get[0];
			$this->order_form = null;
		}
		elseif ($order_label)
			$this->redirect($this->base_uri . "order/main/index/" . $order_label);
		
		if (!$this->order_form)
			$this->order_form = $this->OrderForms->getByLabel($this->company_id, $order_label);
		
		// If the order form doesn't exist or is inactive, redirect the client away
		if (!$this->order_form || $this->order_form->status != "active")
			$this->redirect($this->base_uri);

		// Ready the session cart for this order form
		$this->cart_name = $this->company_id . "-" . $order_label;
		$this->components(array('SessionCart' => array($this->cart_name, $this->Session)));

		// If the order form requires SSL redirect to HTTPS
		if ($this->order_form->require_ssl  && !(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off"))
			$this->redirect(str_replace("http://", "https://", $this->base_url) . ltrim(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null, "/"));
		
		// Auto load language for the template
		Language::loadLang("cart", null, PLUGINDIR . DS . "order" . DS . "language" . DS);
		Language::loadLang(array(Loader::fromCamelCase(get_class($this)), $this->order_form->type, "main", "summary"), null, PLUGINDIR . DS . "order" . DS . "views" . DS . "templates" . DS . $this->order_form->template . DS . "language" . DS);
		
		$this->view->setView(null, "templates" . DS . $this->order_form->template);
		if (($structure_dir = $this->getViewDir(null, true)) && substr($structure_dir, 0, 6) == "client")
			$this->structure->setDefaultView(APPDIR);
		$this->structure->setView(null, $structure_dir);

		$this->structure->set("outer_class", "order");
		$this->structure->set("custom_head",
			"<link href=\"" . Router::makeURI(str_replace("index.php/", "", WEBDIR) . $this->view->view_path . "views/" . $this->view->view) . "/css/order.css\" rel=\"stylesheet\" type=\"text/css\" />"
		);
		
		$this->view->setView(null, $this->getViewDir());
		
		$this->base_uri = WEBDIR;
		$this->view->base_uri = $this->base_uri;
		$this->structure->base_uri = $this->base_uri;
		
		$this->structure->set("page_title", $this->order_form->name);
		$this->structure->set("title", $this->order_form->name);
		
		// Load the order type
		$this->order_type = $this->loadOrderType($this->order_form->type);
		
		// Set the client info
		if ($this->Session->read("blesta_client_id")) {
			$this->client = $this->Clients->get($this->Session->read("blesta_client_id"));
			$this->view->set("client", $this->client);
			$this->structure->set("client", $this->client);
		}
		
		// Set the order form in the view and structure
		$this->view->set("order_form", $this->order_form);
		$this->structure->set("order_form", $this->order_form);
		
		$this->view->Json = $this->Json;
		$this->structure->Json = $this->Json;
		
		$this->view->set("is_ajax", $this->isAjax());
		
		// Set the currnecy to use for this order form
		$this->setCurrency();
	}
	
	/**
	 * Renders the current view as an AJAX response if this is an AJAX request
	 *
	 * @return boolean True to render the layout false otherwise
	 */
	protected function renderView() {
		if ($this->isAjax()) {
			$view = $this->controller . (!$this->action || $this->action == "index" ? "" : "_" . $this->action);
			$this->outputAsJson($this->view->fetch($view));
			return false;
		}
		
		return true;
	}
	
	/**
	 * Calculates the totals for the cart
	 *
	 * @param int $client_id The ID of the client to fetch totals for in lieu of $country and $state
	 * @param string $country The ISO 3166-1 alpha2 country code to fetch tax rules on in lieu of $client_id
	 * @param string $state 3166-2 alpha-numeric subdivision code to fetch tax rules on in lieu of $client_id
	 * @param
	 * @return array An array of pricing information including:
	 * 	- subtotal The total before discount, fees, and tax
	 * 	- discount The total savings
	 * 	- fees An array of fees requested including:
	 * 		- setup The setup fee
	 * 		- cancel The cancel fee
	 * 	- total The total after discount, fees, but before tax
	 * 	- total_w_tax The total after discount, fees, and tax
	 * 	- tax The total tax
	 * @see Packages::calcLineTotals()
	 */
	protected function calculateTotals($client_id = null, $country = null, $state = null, $cart = null) {
		if (!isset($this->Invoices))
			$this->uses(array("Invoices"));
		
		if ($cart == null)
			$cart = $this->SessionCart->get();

		$coupon = $this->SessionCart->getData("coupon");
		
		$tax_rules = null;
		if (!$client_id)
			$tax_rules = $this->Invoices->getTaxRulesByLocation($this->company_id, $country, $state);
		
		$vars = array();
		foreach ($cart['items'] as $item) {
			$vars[] = array(
				'pricing_id' => $item['pricing_id'],
				'qty' => isset($item['qty']) ? (int)$item['qty'] : 1,
				'fees' => array("setup"),
				'configoptions' => isset($item['configoptions']) ? $item['configoptions'] : array()
			);
			
			if (isset($item['addons'])) {
				foreach ($item['addons'] as $item) {
					$vars[] = array(
						'pricing_id' => $item['pricing_id'],
						'qty' => isset($item['qty']) ? (int)$item['qty'] : 1,
						'fees' => array("setup"),
						'configoptions' => isset($item['configoptions']) ? $item['configoptions'] : array()
					);
				}
			}
		}

		return $this->Packages->calcLineTotals($client_id, $vars, $coupon, $tax_rules, $this->order_form->client_group_id, $this->SessionCart->getData("currency"));
	}

	/**
	 * Set all pricing periods
	 */
	protected function getPricingPeriods() {
		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period => $lang)
			$periods[$period . "_plural"] = $lang;
		return $periods;
	}

	/**
	 * Load the order type required for this order form
	 *
	 * @param string $order_type The Order type for this order form
	 * @return object An OrderType object
	 */
	protected function loadOrderType($order_type) {
		Loader::load(PLUGINDIR . "order" . DS . "lib" . DS . "order_type.php");
		Loader::load(PLUGINDIR . "order" . DS . "lib" . DS . "order_types" . DS . $order_type . DS . "order_type_" . $order_type . ".php");
		$class_name = Loader::toCamelCase("order_type_" . $order_type);
		
		$order_type = new $class_name();
		$order_type->setOrderForm($this->order_form);
		$order_type->setCart($this->SessionCart);
		$order_type->base_uri = $this->base_uri;
		
		return $order_type;
	}
	
	/**
	 * Sets the ISO 4217 currency code to use for the order form
	 */
	protected function setCurrency() {
		
		// If user attempts to change currency, verify it can be set
		// Currency can only be changed if cart is empty
		if (isset($this->get['currency']) && $this->SessionCart->isEmptyCart()) {
			foreach ($this->order_form->currencies as $currency) {
				if ($currency->currency == $this->get['currency']) {
					$this->SessionCart->setData("currency", $currency->currency);
					break;
				}
			}
		}
		elseif (!isset($this->get['currency']) && $this->SessionCart->isEmptyCart()) {
			// If a queued item for the cart exists, verify and set its pricing currency for the order form
			$cart = $this->SessionCart->get();
			if ($this->SessionCart->isEmptyCart() && !$this->SessionCart->isEmptyQueue() && !$this->SessionCart->getData("currency") &&
				($queue_item = $this->SessionCart->checkQueue()) && count($cart['queue']) == 1) {
				$pricing_id = $queue_item['pricing_id'];
				
				// Fetch the package info for the selected pricing ID
				if (($package = $this->Packages->getByPricingId($pricing_id))) {
					// Find the matching pricing
					foreach ($package->pricing as $pricing) {
						// Set the queued pricing currency as the form currency
						if ($pricing->id == $pricing_id) {
							foreach ($this->order_form->currencies as $currency) {
								if ($currency->currency == $pricing->currency) {
									$this->SessionCart->setData("currency", $currency->currency);
									break;
								}
							}
							break;
						}
					}
				}
			}
		}
		
		// If no currency for this session, default to the company's default currency,
		// or the first available currency for the order form
		if ($this->SessionCart->getData("currency") == null) {
			$temp = $this->Companies->getSetting($this->company_id, "default_currency");
			if ($temp)
				$company_currency = $temp->value;
				
			foreach ($this->order_form->currencies as $currency) {
				if ($currency->currency == $company_currency) {
					$this->SessionCart->setData("currency", $currency->currency);
					break;
				}
			}
			
			if ($this->SessionCart->getData("currency") == null && isset($this->order_form->currencies[0]->currency))
				$this->SessionCart->setData("currency", $this->order_form->currencies[0]->currency);
		}
	}
	
	/**
	 * Returns the computed order summary as an array
	 *
	 * @param array $temp_items An array of items to appear as if they exist in the cart
	 * @param int $item_index The index of cart items that $temp_items replaces (if any)
	 * @return array An array of cart summary details
	 */
	protected function getSummary(array $temp_items = null, $item_index = null) {
		if (!isset($this->ModuleManager))
			$this->uses(array("ModuleManager"));
			
		$data = array();
		
		$client_id = null;
		$country = null;
		$state = null;
		
		if ($this->client)
			$client_id = $this->client->id;
		else {
			$user = $this->SessionCart->getItem("user");
			$country = isset($user['country']) ? $user['country'] : null;
			$state = isset($user['state']) ? $user['state'] : null;
		}
		
		$data['cart'] = $this->SessionCart->get();
		if ($temp_items != null) {
			if ($item_index !== null)
				unset($data['cart']['items'][$item_index]);
			$data['cart']['items'] = array_merge($data['cart']['items'], $temp_items);
		}
		
		// Fetch the items in the cart that are to be displayed
		$data['cart']['display_items'] = (isset($data['cart']) ? $this->getDisplayableCartItems($data['cart']) : array());
		
		foreach ($data['cart']['items'] as $index => &$item) {
			
			$package = $this->Packages->getByPricingId($item['pricing_id']);
			
			// Get service name
			$service_name = $this->ModuleManager->moduleRpc($package->module_id, "getPackageServiceName", array($package, $item));
			$item['service_name'] = $service_name;
			$item['package_group_id'] = $item['group_id'];
			$item['index'] = $index;
			$item['package_options'] = $this->getPackageOptions($package, $item);
			
			// Set pricing
			$package = $this->updatePackagePricing($package, $this->SessionCart->getData("currency"));
			
			$item += array('package' => $package);
		}
		
		// Merge addons into each cart item
		foreach ($data['cart']['items'] as &$item) {
			$addons = $this->getAddons($item, $data['cart']);
			unset($item['addons']);
			if (!empty($addons)) {
				$item['addons'] = array();
				foreach ($addons as $index) {
					$item['addons'][] = $data['cart']['items'][$index];
					unset($data['cart']['items'][$index]);
				}
			}
		}
		$data['cart']['items'] = array_values($data['cart']['items']);
		
		$data['totals'] = $this->calculateTotals($client_id, $country, $state, $data['cart']);
		
		return $data;
	}
	
	/**
	 * Fetches cart items that can be displayed, but which may not all technically be in the cart. These may contain additional items due to proration
	 * @see OrderFormController::getSummary()
	 *
	 * @param array $cart The cart, including items
	 * @return array A list of items that can be displayed from the cart
	 */
	private function getDisplayableCartItems($cart) {
		if (!isset($this->SettingsCollection))
			$this->components(array("SettingsCollection"));
		
		$client_id = null;
		if ($this->client)
			$client_id = $this->client->id;
		
		// Group addons with each item to maintain order
		$cart_items = (isset($cart['items']) ? $cart['items'] : array());
		foreach ($cart_items as $index => &$item) {
			if (isset($item['addons'])) {
				$addon_indices = $this->getAddons($item, $cart);
				$item['addons'] = array();
				foreach ($addon_indices as $addon_index) {
					$item['addons'][] = $cart_items[$addon_index];
					unset($cart_items[$addon_index]);
				}
			}
		}
		unset($index, $item, $addon_indices, $addon_index);
		
		// Set pricing info for each item
		$package_pricings = array();
		$temp_cart_items = array();
		$i = 0;
		foreach ($cart_items as $item) {
			$package_pricings[$i] = array(
				'pricing_id' => $item['pricing_id'],
				'qty' => (isset($item['qty']) ? $item['qty'] : 1),
				'fees' => array("setup"),
				'configoptions' => (isset($item['configoptions']) ? $item['configoptions'] : array())
			);
			$temp_cart_items[$i] = $item;
			$i++;
			
			// Include addon items
			if (isset($item['addons'])) {
				foreach ($item['addons'] as $addon) {
					$package_pricings[$i] = array(
						'pricing_id' => $addon['pricing_id'],
						'qty' => (isset($addon['qty']) ? $addon['qty'] : 1),
						'fees' => array("setup"),
						'configoptions' => (isset($addon['configoptions']) ? $addon['configoptions'] : array())
					);
					$temp_cart_items[$i] = $addon;
					$i++;
				}
			}
		}
		unset($i, $item, $addon, $cart_items);
		
		// Fetch a list of all the items
		$items = $this->Packages->getPackageItems($package_pricings, $client_id, $this->SessionCart->getData("currency"));
		
		// Set additional info for each item
		foreach ($items as &$item) {
			$item = (array)$item;
			
			// Reference the package item with the one in the cart
			$cart_item = $temp_cart_items[$item['index']];
			
			// Get service name
			$service_name = $this->ModuleManager->moduleRpc($item['package']->module_id, "getPackageServiceName", array($item['package'], $cart_item));
			$item['service_name'] = $service_name;
			$item['package_group_id'] = $cart_item['group_id'];
			
			// Set the date range of the service
			if (!empty($item['start_date']) && !empty($item['end_date'])) {
				$date_format = $this->SettingsCollection->fetchSetting(null, $this->company_id, "date_format");
				$date_format = $date_format['value'];
				$item['date_range'] = Language::_("Cart.index.date_range", true, $this->Date->cast($item['start_date'], $date_format), $this->Date->cast($item['end_date'], $date_format));
			}
			
			$item = array_merge($cart_item, $item);
		}
		unset($item, $temp_cart_items);
		
		// Remove each addon item from the list
		$addons = array();
		foreach ($items as $index => $item) {
			if (isset($item['uuid'])) {
				$addons[] = $item;
				unset($items[$index]);
			}
		}
		unset($index, $item);
		
		// Add the addons into each item to which it belongs
		foreach ($items as &$item) {
			$temp_addons = (isset($item['addons']) ? $item['addons'] : array());
			$item['addons'] = array();
			
			foreach ($addons as $index => $addon) {
				foreach ($temp_addons as $temp_addon) {
					// Add the addon to the item
					if ($addon['uuid'] == $temp_addon['uuid']) {
						$item['addons'][] = $addon;
						
						// Remove the addon from the list so as to not re-add it again (in the case of subsequent prorated items)
						unset($addons[$index]);
						break;
					}
				}
			}
		}
		
		return array_values($items);
	}
	
	/**
	 * Fetches all package options for the given package. Uses the given item to select and set pricing
	 *
	 * @param stdClass $package The package to fetch options for
	 * @param array $item An array of item info
	 * @retrun stdClass A stdClass object representing the package option and its price
	 */
	protected function getPackageOptions($package, $item) {
		if (!isset($this->PackageOptions))
			$this->uses(array("PackageOptions"));
			
		$package_options = $this->PackageOptions->getByPackageId($package->id);
		foreach ($package_options as $i => $option) {
			if (isset($item['configoptions']) && array_key_exists($option->id, $item['configoptions'])) {
				
				// Exclude quantity items if empty
				if ($option->type == "quantity" && empty($item['configoptions'][$option->id])) {
					unset($package_options[$i]);
					continue;
				}
				
				foreach ($package->pricing as $pricing) {
					if ($pricing->id == $item['pricing_id'])
						break;
				}
				$option->price = $this->getOptionPrice($pricing, $option->id, $item['configoptions'][$option->id]);
				$option->selected_value_name = isset($option->values[0]->name) ? $option->values[0]->name : null;
				
				if (isset($option->values)) {
					foreach ($option->values as $value) {
						if ($value->value == $item['configoptions'][$option->id]) {
							$option->selected_value_name = $value->name;
							break;
						}
					}
				}
			}
		}
		unset($option);
		
		return $package_options;
	}
	
	/**
	 * Returns the pricing term for the given option ID and value
	 *
	 * @param stdClass $package_pricing The package pricing
	 * @param int $option_id The package option ID
	 * @param string $value The package option value
	 * @return mixed A stdClass object representing the price if found, false otherwise
	 */
	protected function getOptionPrice($package_pricing, $option_id, $value) {
		if (!isset($this->PackageOptions))
			$this->uses(array("PackageOptions"));
			
		$singular_periods = $this->Packages->getPricingPeriods();
		$plural_periods = $this->Packages->getPricingPeriods(true);
		
		$value = $this->PackageOptions->getValue($option_id, $value);
		if ($value)
			return $this->PackageOptions->getValuePrice($value->id, $package_pricing->term, $package_pricing->period, $package_pricing->currency, $this->SessionCart->getData("currency"));
		
		return false;
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
		$multi_currency_pricing = $this->Companies->getSetting($this->company_id, "multi_currency_pricing");
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
	
	/**
	 * Removes all addon items for a given item
	 *
	 * @param array $item An item in the form of:
	 * 	- pricing_id The ID of the package pricing item to add
	 * 	- group_id The ID of the package group the item belongs to
	 * 	- addons An array of addons containing:
	 * 		- uuid The unique ID for each addon
	 */
	protected function removeAddons($item) {
		$indexes = $this->getAddons($item);
		$this->SessionCart->removeItems($indexes);
	}
	
	/**
	 * Fetches the cart index for each addon item associated with this item
	 *
	 * @param array $item An item in the form of:
	 * 	- pricing_id The ID of the package pricing item to add
	 * 	- group_id The ID of the package group the item belongs to
	 * @param array $cart The cart to use, else will pull from the session
	 * @return array An array of cart item indexes where the addon items live
	 */
	protected function getAddons($item, array $cart = null) {
		if (isset($item['addons'])) {
			$indexes = array();
			if ($cart === null)
				$cart = $this->SessionCart->get();

			if (!empty($cart['items'])) {
				foreach ($item['addons'] as $uuid) {
					foreach ($cart['items'] as $index => $cart_item) {
						if (isset($cart_item['uuid']) && $uuid == $cart_item['uuid']) {
							$indexes[] = $index;
							break;
						}
					}
				}
			}
			
			return $indexes;
		}
		return array();
	}
	
	/**
	 * Verifies if the given item is valid for this order form
	 *
	 * @param array $item An item in the form of:
	 * 	- pricing_id The ID of the package pricing item to add
	 * 	- group_id The ID of the package group the item belongs to
	 * @return boolean True if the item is valid for this order form, false otherwise
	 */
	protected function isValidItem($item) {
		if (!isset($item['pricing_id']) || !isset($item['group_id']))
			return false;
		
		$currency = $this->SessionCart->getData("currency");
		$multi_currency_pricing = $this->Companies->getSetting($this->company_id, "multi_currency_pricing");
		$allow_conversion = true;
		
		if ($multi_currency_pricing->value == "package")
			$allow_conversion = false;
			
		$item_group = $this->PackageGroups->get($item['group_id']);

		$valid_groups = $this->order_type->getGroupIds();

		foreach ($valid_groups as $group_id) {
			if ($item_group->type == "addon") {
				foreach ($item_group->parents as $parent_group) {
					if ($parent_group->id == $group_id)
						return true;
				}
			}
			elseif ($item_group->id == $group_id) {
				$packages = $this->Packages->getAllPackagesByGroup($group_id,  "active");
				
				foreach ($packages as $package) {
					foreach ($package->pricing as $pricing) {
						if ($pricing->id == $item['pricing_id'] && $this->Packages->convertPricing($pricing, $currency, $allow_conversion)) {
							return true;
						}
					}
				}
				break;
			}
		}

		return false;
	}
	
	/**
	 * Set view directories. Allows order template type views to override order template views.
	 * Also allows order templates to use own structure view.
	 */
	protected function getViewDir($view = null, $structure = false) {
		
		$base_dir = PLUGINDIR . "order" . DS . "views" . DS;
		
		if ($structure) {
			if (file_exists($base_dir . "templates" . DS . $this->order_form->template . DS . "types" . DS . $this->order_form->type . DS . $this->structure_view . ".pdt"))
				return "templates" . DS . $this->order_form->template . DS . "types" . DS . $this->order_form->type;
			elseif (file_exists($base_dir . "templates" . DS . $this->order_form->template . DS . $this->structure_view . ".pdt"))
				return "templates" . DS . $this->order_form->template;
			
			return "client" . DS . $this->layout;
		}
		else {
			if ($view == null)
				$view = $this->view->file;
			// Use the view file set for this view (if set)
			if (!$view) {
				// Auto-load the view file. These have the format of:
				// [controller_name]_[method_name] for all non-index methods
				$view = Loader::fromCamelCase(get_class($this)) .
					($this->action != null && $this->action != "index" ? "_" . strtolower($this->action) : "");
			}
			
			$template_type = "templates" . DS . $this->order_form->template . DS . "types" . DS . $this->order_form->type;
			if (file_exists($base_dir . $template_type . DS . $view . ".pdt")) {
				return $template_type;
			}
			
			return "templates" . DS . $this->order_form->template;
		}
	}
	
	/**
	 * Returns an array of payment options
	 *
	 * @param string $currency The currency to fetch payment options for
	 * @return array An array containing payment option details:
	 * 	- nonmerchant_gateways An array of stdClass objects representing nonmerchant_gateways
	 * 	- merchant_gateway A stdClass object representing the merchant gateway
	 * 	- payment_types An array of accepted merchant payment types
	 * 	- currency The currency
	 */
	protected function getPaymentOptions($currency = null) {
		if ($currency == null)
			$currency = $this->SessionCart->getData("currency");
		
		$this->uses(array("GatewayManager", "Transactions"));
		
		if (isset($this->client->settings))
			$settings = $this->client->settings;
		else {
			$this->components(array("SettingsCollection"));
			$settings = $this->SettingsCollection->fetchSettings(null, $this->company_id);
		}
		
		// Fetch merchant gateway for this currency
		$merchant_gateway = $this->GatewayManager->getInstalledMerchant($this->company_id, $currency, null, true);
		
		// Verify $merchant_gateway is enabled for this order form, if not, unset
		// Set all nonmerchant gateways available
		$valid_merchant_gateway = false;
		$nonmerchant_gateways = array();
		foreach ($this->order_form->gateways as $gateway) {
			
			if ($merchant_gateway && $gateway->gateway_id == $merchant_gateway->id) {
				$valid_merchant_gateway = true;
				continue;
			}
			
			$gw = $this->GatewayManager->getInstalledNonmerchant($this->company_id, null, $gateway->gateway_id, $currency);
			if ($gw)
				$nonmerchant_gateways[] = $gw;
		}
		
		if (!$valid_merchant_gateway)
			$merchant_gateway = null;
		
		// Set the payment types allowed
		$transaction_types = $this->Transactions->transactionTypeNames();
		$payment_types = array();
		if ($merchant_gateway) {
			if ((in_array("MerchantAch", $merchant_gateway->info['interfaces'])
				|| in_array("MerchantAchOffsite", $merchant_gateway->info['interfaces']))
				&& (!$settings || $settings['payments_allowed_ach'] == "true")) {
				$payment_types['ach'] = $transaction_types['ach'];
			}
			if ((in_array("MerchantCc", $merchant_gateway->info['interfaces'])
				|| in_array("MerchantCcOffsite", $merchant_gateway->info['interfaces']))
				&& (!$settings || $settings['payments_allowed_cc'] == "true")) {
				$payment_types['cc'] = $transaction_types['cc'];
			}
		}
		
		return compact("nonmerchant_gateways", "merchant_gateway", "payment_types", "currency");
	}
	
	/**
	 * Handles an error. If AJAX, will output the error as a JSON object with index 'error'.
	 * Else will flash the error and redirect
	 *
	 * @param mixed $error The error message
	 * @param redirect The URI to redirect to
	 */
	protected function handleError($error, $redirect = null) {
		if ($this->isAjax()) {
			$this->outputAsJson(array(
				'error' => $this->setMessage("error", $error, true, null, false)
			));
			exit;
		}
		elseif ($redirect != null) {
			$this->flashMessage("error", $error, null, false);
			$this->redirect($redirect);
		}
		else {
			$this->setMessage("error", $error, false, null, false);
		}
	}
	
	
	/**
	 * Checks whether the client user owns the client account
	 *
	 * @param stdClass $client The client
	 * @param Session $session The user's session
	 * @return bolean True if the client user owns the client account, false otherwise
	 */
	protected function isClientOwner($client, Session $session) {
		return (!$client || $client->user_id == $session->read("blesta_id") || $this->isStaffAsClient());
	}
}
?>