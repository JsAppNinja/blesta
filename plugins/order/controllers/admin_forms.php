<?php
/**
 * Order Forms controller
 *
 * @package blesta
 * @subpackage blesta.plugins.order.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminForms extends OrderController {

	/**
	 * @var string The base Order URL
	 */
	private $base_order_url;

	/**
	 * Pre Action
	 */
	public function preAction() {
		parent::preAction();
		
		$this->requireLogin();
		
		$this->uses(array("Order.OrderForms", "ClientGroups", "Companies"));
		
		// Restore structure view location of the admin portal
		$this->structure->setDefaultView(APPDIR);
		$this->structure->setView(null, $this->orig_structure_view);
		
		$company = $this->Companies->get($this->company_id);
		
		$this->base_order_url = "http" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off" ? "s" : "") .
			"://" . $company->hostname . WEBDIR . "order/main/index/";
		
		Language::loadLang("admin_forms", null, PLUGINDIR . "order" . DS . "language" . DS);
	}

	/**
	 * List order forms
	 */
	public function index() {
		
		$page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_added");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");
		
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		
		$order_forms = $this->OrderForms->getList($this->company_id, $page, array($sort => $order));
		$total_results = $this->OrderForms->getListCount($this->company_id);
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri' => $this->base_uri . "plugin/order/admin_forms/index/[p]/"
			)
		);
		$this->helpers(array("Pagination" => array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		$this->set("templates", $this->OrderForms->getTemplates());
		$this->set("types", $this->OrderForms->getTypes());
		$this->set("order_forms", $order_forms);
		$this->set("base_order_url", $this->base_order_url);
		
		return $this->renderAjaxWidgetIfAsync(isset($this->get[0]) || isset($this->get['sort']));
	}
	
	/**
	 * Add an order form
	 */
	public function add() {
		
		$vars = new stdClass();
		$vars->template = "wizard";
		$vars->template_style = "boxes";
		
		if (!empty($this->post)) {
			
			// Handle checkboxes that are unchecked
			if (!array_key_exists("allow_coupons", $this->post))
				$this->post['allow_coupons'] = "0";
			if (!array_key_exists("manual_review", $this->post))
				$this->post['manual_review'] = "0";
			if (!array_key_exists("require_ssl", $this->post))
				$this->post['require_ssl'] = "0";
			if (!array_key_exists("require_captcha", $this->post))
				$this->post['require_captcha'] = "0";
			if (!array_key_exists("require_tos", $this->post))
				$this->post['require_tos'] = "0";
				
			// Add the order form
			$this->OrderForms->add($this->post);
			
			if (!($errors = $this->OrderForms->errors())) {
				$this->flashMessage("message", Language::_("AdminForms.!success.form_added", true), null, false);
				$this->redirect($this->base_uri . "plugin/order/admin_forms/");
			}
			
			$vars = (object)$this->post;
			$this->setMessage("error", $errors, false, null, false);
		}
		
		$templates = $this->OrderForms->getTemplates();

		$this->set("types", $this->OrderForms->getTypes());
		$this->set("client_groups", $this->ClientGroups->getAll($this->company_id));
		$this->set("templates", $templates);
		$this->set("base_order_url", $this->base_order_url);
		$this->set("vars", $vars);
	}
	
	/**
	 * Edit an order form
	 */
	public function edit() {
		
		// Ensure order form exists
		if (!isset($this->get[0]) || !($order_form = $this->OrderForms->get($this->get[0])) || $order_form->company_id != $this->company_id)
			$this->redirect($this->base_uri . "plugin/order/admin_forms/");
		
		$vars = $order_form;
		
		if (!empty($this->post)) {
			
			// Handle checkboxes that are unchecked
			if (!array_key_exists("allow_coupons", $this->post))
				$this->post['allow_coupons'] = "0";
			if (!array_key_exists("manual_review", $this->post))
				$this->post['manual_review'] = "0";
			if (!array_key_exists("require_ssl", $this->post))
				$this->post['require_ssl'] = "0";
			if (!array_key_exists("require_captcha", $this->post))
				$this->post['require_captcha'] = "0";
			if (!array_key_exists("require_tos", $this->post))
				$this->post['require_tos'] = "0";
			if (!array_key_exists("currencies", $this->post))
				$this->post['currencies'] = array();
			if (!array_key_exists("gateways", $this->post))
				$this->post['gateways'] = array();
			if (!array_key_exists("groups", $this->post))
				$this->post['groups'] = array();
			if (!array_key_exists("meta", $this->post))
				$this->post['meta'] = array();
			
			// Edit the order form
			$this->OrderForms->edit($order_form->id, $this->post);
			
			if (!($errors = $this->OrderForms->errors())) {
				$this->flashMessage("message", Language::_("AdminForms.!success.form_edited", true), null, false);
				$this->redirect($this->base_uri . "plugin/order/admin_forms/edit/" . $order_form->id);
			}
			
			$vars = (object)$this->post;
			$this->setMessage("error", $errors, false, null, false);
		}
		
		$templates = $this->OrderForms->getTemplates();
		
		$this->set("types", $this->OrderForms->getTypes());
		$this->set("client_groups", $this->ClientGroups->getAll($this->company_id));
		$this->set("templates", $templates);
		$this->set("base_order_url", $this->base_order_url);
		$this->set("vars", $vars);
	}
	
	/**
	 * Sets an order form to inactive
	 */
	public function delete() {
		
		if (isset($this->post['id'])) {
			$this->OrderForms->delete($this->post['id']);
			
			if (($errors = $this->OrderForms->errors()))
				$this->flashMessage("error", $errors, null, false);
			else
				$this->flashMessage("message", Language::_("AdminForms.!success.form_delete", true), null, false);
		}
		
		$this->redirect($this->base_uri . "plugin/order/admin_forms/");
	}
	
	/**
	 * Returns the order form meta fields and package group sections in JSON format
	 */
	public function meta() {
		
		$vars = (object)$this->post;
		
		$order_type = $this->OrderForms->loadOrderType(isset($this->post['type']) ? $this->post['type'] : "general");
		$multi_group = $order_type->supportsMultipleGroups();
		$meta_fields = $order_type->getSettings($this->post);
		$gateways = null;
		$currencies = null;
		
		$package_groups = $this->availablePackageGroups($vars, $multi_group);
		
		if ($order_type->supportsPayments()) {
			$gateways = $this->getAvailableGateways();
			$currencies = $this->getAvailableCurrencies();
		}
		
		$this->outputAsJson(array('content' => $this->partial("admin_forms_meta", compact("multi_group", "meta_fields", "vars", "package_groups", "gateways", "currencies"))));
		return false;
	}
	
	/**
	 * Fetch all available package groups
	 *
	 * @param array $var An array of input vars
	 * @param boolean $multi_group True if the order type supports multiple package groups, false otherwise
	 */
	private function availablePackageGroups(&$vars, $multi_group = true) {
		$this->uses(array("Packages"));
		
		// Fetch all available package groups
		$package_groups = $this->Form->collapseObjectArray($this->Packages->getAllGroups($this->company_id, null, "standard"), "name", "id");
		
		// Set all selected package groups in assigned and unset all selected groups from available
		if (isset($vars->groups) && is_array($vars->groups)) {
			$selected = array();
			
			foreach ($package_groups as $id => $name) {
				if (in_array($id, $vars->groups)) {
					$selected[$id] = $name;
					if ($multi_group)
						unset($package_groups[$id]);
				}
			}
			
			$vars->groups = $selected;
		}
		
		return $package_groups;
	}
	
	/**
	 * Gets all available currencies
	 *
	 * @return array An array of stdClass objects representing currencies
	 */
	private function getAvailableCurrencies() {
		$this->uses(array("Currencies"));
		
		return $this->Currencies->getAll($this->company_id);
	}
	
	/**
	 * Gets all available gateways
	 *
	 * @return array An array of stdClass objects representing gateways
	 */
	private function getAvailableGateways() {
		$this->uses(array("GatewayManager"));
		
		return $this->GatewayManager->getAll($this->company_id);
	}
	
	
	/**
	 * Update order form settings
	 */
	public function settings() {
		$this->uses(array("Order.OrderSettings"));
		$this->helpers(array("DataStructure"));
		$this->ArrayHelper = $this->DataStructure->create("Array");
		
		if (!empty($this->post)) {
			$this->OrderSettings->setSettings($this->company_id, $this->post);
			
			$this->flashMessage("message", Language::_("AdminForms.!success.settings_saved", true), null, false);
			$this->redirect($this->base_uri . "plugin/order/admin_forms/");
		}
		
		$vars = $this->ArrayHelper->numericToKey($this->OrderSettings->getSettings($this->company_id), "key", "value");
		$this->set("vars", $vars);
		$this->set("order_forms", $this->OrderForms->getAll($this->company_id, "active", array('name' => "asc")));
		$this->set("antifraud", $this->OrderSettings->getAntifraud());
	}
	
	/**
	 * Return antifraud settings for the given antifraud type
	 */
	public function antifraudSettings() {
		$this->uses(array("Order.OrderSettings"));
		$this->helpers(array("DataStructure"));
		$this->ArrayHelper = $this->DataStructure->create("Array");
		$this->components(array("Order.Antifraud"));
		
		$settings = $this->ArrayHelper->numericToKey($this->OrderSettings->getSettings($this->company_id), "key", "value");
		
		try {
			if (!$this->isAjax() || !isset($this->get[0]) || !($antifraud = $this->Antifraud->create($this->get[0], array($settings))))
				return false;
		}
		catch (Exception $e) {
			return false;
		}
		
		$fields = $antifraud->getSettingFields((object)array_merge($settings, $this->post))->getFields();
		echo $this->partial("admin_forms_antifraudsettings", compact("fields"));
		return false;
	}
}
?>