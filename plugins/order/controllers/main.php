<?php
/**
 * Order System main controller
 *
 * @package blesta
 * @subpackage blesta.plugins.order.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Main extends OrderFormController {
	
	/**
	 * Set temp coupon if given
	 */
	public function preAction() {
		parent::preAction();
		
		// If a coupon was given, save it for use later
		if (!empty($this->get['coupon']) && $this->order_form->allow_coupons == "1")
			$this->SessionCart->setData("temp_coupon", $this->get['coupon']);
	}
	
	/**
	 * List packages groups/packages
	 *
	 */
	public function index() {
		$this->helpers(array("TextParser"));
		$parser_syntax = "markdown";
		
		// If pricing ID and group ID set, redirect to configure this item
		if (array_key_exists("pricing_id", $this->post) && array_key_exists("group_id", $this->post))
			$this->redirect($this->base_uri . "order/config/index/" . $this->order_form->label . "/?" . http_build_query($this->post));

		// If the order type require pre config then redirect directly to preconfig
		if ($this->order_type->requiresPreConfig() && (!isset($this->get['skip']) || $this->get['skip'] == "false"))
			$this->redirect($this->base_uri . "order/config/preconfig/" . $this->order_form->label);
		
		$package_groups = array();
		$packages = array();
		$currency = $this->SessionCart->getData("currency");

		// If only one available group, redirect to package listing for that single group
		if (count($this->order_form->groups) == 1) {
			$this->redirect($this->base_uri . "order/main/packages/" . $this->order_form->label . "/?group_id=" . $this->order_form->groups[0]->package_group_id);
		}
		// If no package groups available, redirect to config or cart
		if (count($this->order_form->groups) == 0) {
			$redirect_uri = $this->base_uri . "order/config/index/" . $this->order_form->label . "/";
			if ($this->SessionCart->isEmptyQueue())
				$redirect_uri = $this->base_uri . "order/cart/index/" . $this->order_form->label . "/";
			$this->redirect($redirect_uri);
		}
		
		foreach ($this->order_form->groups as $group) {
			// Fetch the package group details
			$package_groups[$group->package_group_id] = $this->PackageGroups->get($group->package_group_id);
			
			// Fetch all packages for this group
			$packages[$group->package_group_id] = $this->Packages->getAllPackagesByGroup($group->package_group_id, "active");
			
			// Update package pricing for the selected currency
			$packages[$group->package_group_id] = $this->updatePackagePricing($packages[$group->package_group_id], $currency);
		}
		
		$summary = $this->getSummary();
		$cart = $summary['cart'];
		$totals = $summary['totals'];
		
		$this->set("periods", $this->getPricingPeriods());
		$this->set(compact("package_groups", "packages", "parser_syntax", "currency", "cart", "totals"));
	}
	
	/**
	 * List packages for a specific group
	 */
	public function packages() {
		$this->helpers(array("TextParser"));
		$parser_syntax = "markdown";
		
		// If pricing ID and group ID set, redirect to configure this item
		if (array_key_exists("pricing_id", $this->post) && array_key_exists("group_id", $this->post))
			$this->redirect($this->base_uri . "order/config/index/" . $this->order_form->label . "/?" . http_build_query($this->post));
		if (!array_key_exists("group_id", $this->get))
			$this->redirect($this->base_uri . "order/main/index/" . $this->order_form->label);
		if (array_key_exists("pricing_id", $this->get))
			$this->set("pricing_id", $this->get['pricing_id']);
		elseif (array_key_exists("package_id", $this->get))
			$this->set("package_id", $this->get['package_id']);
		
		$package_group_id = $this->get['group_id'];
		$package_group = false;
		$packages = array();
		$currency = $this->SessionCart->getData("currency");
		
		foreach ($this->order_form->groups as $group) {
			if ($group->package_group_id == $package_group_id) {
				// Fetch the package group details
				$package_group = $this->PackageGroups->get($group->package_group_id);
				
				// Fetch all packages for this group
				$packages = $this->Packages->getAllPackagesByGroup($group->package_group_id, "active");
				
				// Update package pricing for the selected currency
				$packages = $this->updatePackagePricing($packages, $currency);			
			}
		}
		
		if (!$package_group)
			$this->redirect($this->base_uri . "order/main/index/" . $this->order_form->label);
		
		$summary = $this->getSummary();
		$cart = $summary['cart'];
		$totals = $summary['totals'];
		
		$this->set("periods", $this->getPricingPeriods());
		$this->set(compact("package_group", "packages", "parser_syntax", "currency", "cart", "totals"));
	}
}
?>