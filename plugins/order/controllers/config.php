<?php
/**
 * Order System configuration controller
 *
 * @package blesta
 * @subpackage blesta.plugins.order.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Config extends OrderFormController {
	
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
	 * Configure the service
	 */
	public function index() {
		$this->uses(array("ModuleManager"));
		
		// Obtain the pricing ID and package group ID of the item to order
		$item = null;
		
		// Flag whether the item came from the queue
		$queue_index = null;
		
		// Handle multiple items
		if (isset($this->post['pricing_id']) && is_array($this->post['pricing_id']) &&
			isset($this->post['group_id']) && is_array($this->post['group_id'])) {
			
			$vars = $this->post;
			unset($vars['pricing_id'], $vars['group_id']);
			
			foreach ($this->post['pricing_id'] as $key => $pricing_id) {
				$item = array(
					'pricing_id' => $pricing_id,
					'group_id' => $this->post['group_id'][$key]
				);
				
				if (isset($this->post['meta'][$key]))
					$item = array_merge($item, $this->post['meta'][$key]);
				$index = $this->SessionCart->enqueue($item);
				
				if ($queue_index === null)
					$queue_index = $index;
			}
			
			// Redirect to configure the first queued item
			if (!$this->isAjax())
				$this->redirect($this->base_uri . "order/config/index/" . $this->order_form->label . "/?q_item=" . $queue_index);
		}
		// Fetch the item from the cart if it already exists (allows editing existing item in cart)
		elseif (isset($this->get['item']))
			$item = $this->SessionCart->getItem($this->get['item']);
		// Handle single item
		elseif (isset($this->post['pricing_id']) && isset($this->post['group_id']) && !isset($this->get['q_item']))
			$item = $this->SessionCart->prequeueItem($this->post);
		elseif (isset($this->get['pricing_id']) && isset($this->get['group_id']))
			$item = $this->SessionCart->prequeueItem($this->get);
		else
			$queue_index = isset($this->get['q_item']) ? $this->get['q_item'] : 0;
			
		// Fetch an item from the queue
		if ($queue_index !== null)
			$item = $this->SessionCart->checkQueue($queue_index);
		
		// Ensure we have an item
		if (!$item)
			$this->handleError(Language::_("Config.!error.invalid_pricing_id", true), $this->base_uri . "order/main/index/" . $this->order_form->label);
		
		// If not a valid item, redirect away and set error
		if (!$this->isValidItem($item)) {
			if ($queue_index)
				$this->SessionCart->dequeue($queue_index);
				
			$this->handleError(Language::_("Config.!error.invalid_pricing_id", true), $this->base_uri . "order/main/index/" . $this->order_form->label);
		}
		
		$currency = $this->SessionCart->getData("currency");
		
		$package = $this->updatePackagePricing($this->Packages->getByPricingId($item['pricing_id']), $currency);
		
		$module = $this->ModuleManager->initModule($package->module_id, $this->company_id);
		
		// Ensure a valid module
		if (!$module)
			$this->handleError(Language::_("Config.!error.invalid_module", true), $this->base_uri . "order/main/index/" . $this->order_form->label);
		
		$vars = (object)$item;
		// Attempt to add the item to the cart
		if (!empty($this->post)) {
			if (isset($this->post['qty']))
				$this->post['qty'] = (int)$this->post['qty'];
			
			// Detect module refresh fields
			$refresh_fields = isset($this->post['refresh_fields']) && $this->post['refresh_fields'] == "true";
			
			// Verify fields look correct in order to proceed
			$this->Services->validateService($package, $this->post);
			if (!$refresh_fields && ($errors = $this->Services->errors())) {
				$this->handleError($errors);
			}
			elseif (!$refresh_fields) {
				// Add item to cart
				$item = array_merge($item, $this->post);
				unset($item['addon'], $item['submit']);
				
				if (isset($this->get['item'])) {
					$item_index = $this->get['item'];
					$this->SessionCart->updateItem($item_index, $item);
				}
				else {
					$item_index = $this->SessionCart->addItem($item);
					
					// If item came from the queue, dequeue
					if ($queue_index !== null)
						$this->SessionCart->dequeue($queue_index);
				}
				
				if (isset($this->post['addon'])) {
					// Remove any existing addons
					$this->removeAddons($item);
					
					$item = $this->SessionCart->getItem($item_index);
					
					$addon_queue = array();
					foreach ($this->post['addon'] as $addon_group_id => $addon) {
						// Queue addon items for configuration
						if (array_key_exists('pricing_id', $addon) && !empty($addon['pricing_id'])) {
							$addon_item = array(
								'pricing_id' => $addon['pricing_id'],
								'group_id' => $addon_group_id,
								'uuid' => uniqid()
							);
							$addon_queue[] = $addon_item['uuid'];
							$this->SessionCart->enqueue($addon_item);
						}
					}
					// Link the addons to this item
					$item['addons'] = $addon_queue;
					$this->SessionCart->updateItem($item_index, $item);
				}			
				
				$next_uri = $this->base_uri . "order/cart/index/" . $this->order_form->label;
				$empty_queue = $this->SessionCart->isEmptyQueue();
				// Process next queue item
				if (!$empty_queue)
					$next_uri = $this->base_uri . "order/config/index/" . $this->order_form->label . "?q_item=0";
				// Custom redirect
				else {
					$uri = $this->order_type->redirectRequest($this->controller . "." . $this->action, array('item_index' => $item_index));
					$next_uri = $uri != "" ? $uri : $next_uri;
				}
				
				if ($this->isAjax()) {
					$this->outputAsJson(array('empty_queue' => $empty_queue, 'next_uri' => $next_uri));
					exit;
				}
				else
					$this->redirect($next_uri);
			}
			
			$vars = (object)$this->post;
		}
		
		// Get all add-on groups (child "addon" groups for this package group)
		// And all packages in the group
		$addon_groups = $this->Packages->getAllAddonGroups($item['group_id']);
		
		foreach ($addon_groups as &$addon_group)
			$addon_group->packages = $this->updatePackagePricing($this->Packages->getAllPackagesByGroup($addon_group->id, "active"), $currency);
		
		$service_fields = $module->getClientAddFields($package, $vars);
		$fields = $service_fields->getFields();

		$html = $service_fields->getHtml();
		$module_name = $module->getName();
		
		// Get service name
		$service_name = $this->ModuleManager->moduleRpc($package->module_id, "getPackageServiceName", array($package, (array)$vars));

		$this->set("periods", $this->getPricingPeriods());
		$this->set(compact("vars", "item", "package", "addon_groups", "service_fields", "fields", "html", "module_name", "currency", "service_name"));
		
		return $this->renderView();
	}
	
	/**
	 * Preconfiguration of the service
	 */
	public function preConfig() {
		
		// Only allow this step if the order type requires it
		if (!$this->order_type->requiresPreConfig())
			$this->redirect($this->base_uri . "order/");

		$this->get['base_uri'] = $this->base_uri;

		$content = $this->order_type->handleRequest($this->get, $this->post, $this->files);
		
		if (($errors = $this->order_type->errors())) {
			$this->handleError($errors);
		}
		
		$this->set("content", $content);
		$this->set("vars", (object)$this->post);
		
		// Render the view from the order type for this template
		$this->view->setView(null, "templates" . DS . $this->order_form->template . DS . "types" . DS . $this->order_form->type);
		
		return $this->renderView();
	}
	
	/**
	 * Fetch all packages options for the given pricing ID and optional service ID
	 */
	public function packageOptions() {
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "order/");
		
		$this->uses(array("Packages", "PackageOptions"));
		
		$package = $this->Packages->getByPricingId($this->get[1]);
		
		if (!$package)
			return false;
		
		$pricing = null;
		foreach ($package->pricing as $pricing) {
			if ($pricing->id == $this->get[1])
				break;
		}

		$vars = (object)$this->get;
		$currency = $this->SessionCart->getData("currency");
		
		$package_options = $this->PackageOptions->getFields($pricing->package_id, $pricing->term, $pricing->period, $pricing->currency, $vars, $currency, array('addable' => 1));
		
		$this->set("fields", $package_options->getFields());
		
		echo $this->outputAsJson($this->view->fetch("config_packageoptions"));
		return false;
	}
	
	/**
	 * Queue management
	 */
	public function queue() {
		if (isset($this->get[1])) {
			switch ($this->get[1]) {
				case "add":
					return $this->enqueueItem();
				case "remove":
					return $this->dequeueItem();
				case "empty":
					return $this->emptyQueue();
			}
		}
		return false;
	}

	/**
	 * Enqueue an item
	 */		
	private function enqueueItem() {
		if (!empty($this->post)) {
			$index = $this->SessionCart->enqueue($this->post);
			$this->outputAsJson(array('index' => $index, 'empty_queue' => $this->SessionCart->isEmptyQueue()));
		}
		return false;
	}

	/**
	 * Dequeue an item
	 */	
	private function dequeueItem() {
		if (!empty($this->post)) {
			$item = $this->SessionCart->dequeue($this->post['index']);
			$this->outputAsJson(array('item' => $item, 'empty_queue' => $this->SessionCart->isEmptyQueue()));
		}
		return false;
	}
	
	/**
	 * Empty the queue
	 */
	private function emptyQueue() {
		if (!empty($this->post)) {
			$this->SessionCart->setData("queue", null);
			$this->outputAsJson(array('empty_queue' => $this->SessionCart->isEmptyQueue()));
		}
		return false;
	}
}
?>