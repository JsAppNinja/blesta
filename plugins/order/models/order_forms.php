<?php
/**
 * Order Form Management
 *
 * @package blesta
 * @subpackage blesta.plugins.order.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class OrderForms extends OrderModel {

	/**
	 * Returns the total number of order forms for the given company
	 * 
	 * @param int $company_id The ID of the company to fetch order form count from
	 * @param int $page The page number of results to fetch
	 * @param array $order A key/value pair array of fields to order the results by
	 * @param string $status The status of the order forms to fetch:
	 * 	- "active" Only active order forms
	 * 	- "inactive" Only inactive order forms
	 * 	- null All order forms
	 * @return array An array of stdClass objects, each representing an order form
	 */	
	public function getList($company_id, $page = 1, array $order = array('id' => "desc"), $status = null) {
		$this->Record = $this->getOrderForm($status);
		return $this->Record->where("company_id", "=", $company_id)->order($order)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Returns the total number of order forms for the given company
	 * 
	 * @param int $company_id The ID of the company to fetch order form count from
	 * @param string $status The status of the order forms to fetch:
	 * 	- "active" Only active order forms
	 * 	- "inactive" Only inactive order forms
	 * 	- null All order forms
	 * @return int The total number of order forms for the given company
	 */
	public function getListCount($company_id, $status = null) {
		$this->Record = $this->getOrderForm($status);
		return $this->Record->where("company_id", "=", $company_id)->numResults();
	}

	/**
	 * Returns all order forms in the system for the given company
	 *
	 * @param int $company_id The ID of the company to fetch order forms for
	 * @param string $status The status of the order forms to fetch:
	 * 	- "active" Only active order forms
	 * 	- "inactive" Only inactive order forms
	 * 	- null All order forms
	 * @param array $order A key/value pair array of fields to order the results by
	 * @return array An array of stdClass objects, each representing an order form
	 */
	public function getAll($company_id, $status, array $order = array('id' => "desc")) {
		$this->Record = $this->getOrderForm($status);
		return $this->Record->where("company_id", "=", $company_id)->order($order)->
			fetchAll();
	}
	
	/**
	 * Fetches the order form with the given label for the given company
	 *
	 * @param int $company_id The ID of the company to fetch on
	 * @param int $label The label of the order form to fetch on
	 * @return mixed A stdClass object representing the order form, false if no such order form exists
	 */
	public function getByLabel($company_id, $label) {
		$this->Record = $this->getOrderForm();
		$form = $this->Record->where("order_forms.company_id", "=", $company_id)->
			where("order_forms.label", "=", $label)->fetch();
			
		if ($form) {
			$form->currencies = $this->getCurrencies($form->id);
			$form->gateways = $this->getGateways($form->id);
			$form->groups = $this->getGroups($form->id);
			$form->meta = $this->getMeta($form->id);
		}
		
		return $form;
	}
	
	/**
	 * Fetches the order form with the given ID
	 *
	 * @param int $order_form_id The ID of the order form to fetch on
	 * @return mixed A stdClass object representing the order form, false if no such order form exists
	 */
	public function get($order_form_id) {
		$this->Record = $this->getOrderForm();
		$form = $this->Record->where("order_forms.id", "=", $order_form_id)->fetch();
			
		if ($form) {
			$form->currencies = $this->getCurrencies($form->id);
			$form->gateways = $this->getGateways($form->id);
			$form->groups = $this->getGroups($form->id);
			$form->meta = $this->getMeta($form->id);
		}
		
		return $form;
	}
	
	/**
	 * Fetches all order types available to the plugin
	 *
	 * @return array An array of key/value pairs where each key is the order type and each value is the order type's name
	 */
	public function getTypes() {
		// Cache results in this object due to having to read/load from disk
		static $types = array();
		
		if (!empty($types))
			return $types;
		
		Loader::load(PLUGINDIR . "order" . DS . "lib" . DS . "order_type.php");
		
		$order_type_dir = PLUGINDIR . "order" . DS . "lib" . DS . "order_types";
		
		$dh = opendir($order_type_dir);
		
		while (($dir = readdir($dh)) !== false) {
			
			if (substr($dir, 0, 1) == "." || !file_exists($order_type_dir . DS . $dir . DS . "order_type_" . $dir . ".php"))
				continue;

			// Load the order type so we can fetch its name
			Loader::load($order_type_dir . DS . $dir . DS . "order_type_" . $dir . ".php");
			
			$class_name = Loader::toCamelCase("order_type_" . $dir);
			
			$order_type = new $class_name();
			$types[$dir] = $order_type->getName();
		}
		closedir($dh);
		
		return $types;
	}
	
	/**
	 * Fetches all templates available to the plugin and the order types they support
	 *
	 * @return array An array of key/value pairs where each key is the template directory and each value is an object representing the order template
	 */
	public function getTemplates() {
		// Cache results in this object due to having to read/load from disk		
		static $templates = array();
		
		if (!empty($templates))
			return $templates;
		if (!isset($this->Json))
			Loader::loadComponents($this, array("Json"));
		
		$templates_dir = PLUGINDIR . "order" . DS . "views" . DS . "templates" . DS;
		
		$dh = opendir($templates_dir);
		
		$i=0;
		while (($dir = readdir($dh)) !== false) {
			
			if (substr($dir, 0, 1) == "." || !is_dir($templates_dir . $dir))
				continue;
			
			$templates[$dir] = $this->Json->decode(file_get_contents($templates_dir . $dir . DS . "config.json"));
			$templates[$dir]->types = $this->getSupportedTypes($dir);
		}
		closedir($dh);
		ksort($templates);
		
		return $templates;
	}
	
	/**
	 * Returns all supported order type for the given template
	 *
	 * @param string $template The template to fetch all supported order types for
	 * @return array An array of supported order types
	 */
	public function getSupportedTypes($template) {
		
		$types = array();
		
		$types_dir = PLUGINDIR . "order" . DS . "views" . DS . "templates" . DS . $template . DS . "types";
		$dh = opendir($types_dir);
		
		if ($dh) {
			
			// All template types support the general type
			$types[] = "general";
			
			// Read all order types supported by this template
			while (($type = readdir($dh)) !== false) {
				
				if (substr($type, 0, 1) == "." || !is_dir($types_dir . DS . $type))
					continue;
				$types[] = $type;
			}
			closedir($dh);
		}
		return $types;
	}
	
	/**
	 * Add an order form
	 *
	 * @param array $vars An array of order form data including:
	 * 	- company_id (optional, defaults to current company ID)
	 *	- label The label used to access the order form
	 *	- name The name of the order form
	 *	- template The template to use for the order form
	 *	- template_style The template style to use for the order form
	 *	- type The type of order form
	 *	- client_group_id The default client group to assign clients to when ordering from this order form
	 *	- manual_review Whether or not to require all orders placed to be manually reviewed (default 0)
	 *	- allow_coupons Whether or not to allow coupons (default 0)
	 *	- require_ssl Whether or not to force secure connection (i.e. HTTPS) (default 0)
	 *	- require_captcha Whether or not to force captcha (default 0)
	 *	- require_tos Whether or not to require Terms of Service agreement (default 0)
	 *	- tos_url The URL to the terms of service agreement (optional)
	 *	- status The statuf ot he order form (active/inactive default active)
	 *	- meta An array of key/value pairs to assign to this order form (optional dependent upon the order type)
	 *	- groups An array of package group IDs to assign to this order form (optional dependent upon the order type)
	 *	- gateways An array of gateway ID to assign to this order form (optional dependent upon the order type)
	 *	- currencies An array of ISO 4217 currency codes to assign to this order form (optional dependent upon the order type)
	 * @return int The ID of the order form that was created, void on error
	 */
	public function add(array $vars) {
		Loader::loadModels($this, array("Order.OrderSettings"));
        
		if (!isset($vars['company_id']))
			$vars['company_id'] = Configure::get("Blesta.company_id");
		
		$vars['date_added'] = date("c");
		
		$order_type = $this->loadOrderType($vars['type']);
		
		// Run order form through order type settings
		$vars = $order_type->editSettings($vars);
		
		if (($errors = $order_type->errors())) {
			$this->Input->setErrors($errors);
			return;
		}
		
		$this->Input->setRules($this->getFormRules($vars));
		
		if ($this->Input->validates($vars)) {
			$fields = array("company_id", "label", "name", "template", "template_style", "type", "client_group_id",
				"manual_review", "allow_coupons", "require_ssl", "require_tos", "tos_url",
				"require_captcha", "status", "date_added");
			
			$this->Record->insert("order_forms", $vars, $fields);
			
			$order_form_id = $this->Record->lastInsertId();
			
			if (isset($vars['currencies']))
				$this->setCurrencies($order_form_id, $vars['currencies']);
			if (isset($vars['gateways']))
				$this->setGateways($order_form_id, $vars['gateways']);
			if (isset($vars['groups']))
				$this->setGroups($order_form_id, $vars['groups']);
			if (isset($vars['meta']))
				$this->setMeta($order_form_id, $vars['meta']);
			
            // Set this as the default order form if it's the only active one that exists
            $order_form = $this->get($order_form_id);
            if ($order_form && $order_form->status == "active" && ($this->getListCount($vars['company_id']) == 1)) {
                $default_form = $this->OrderSettings->getSetting($vars['company_id'], "default_form");

                if (!$default_form || empty($default_form->value))
                    $this->OrderSettings->setSetting($vars['company_id'], "default_form", $order_form->label);
            }
            
			return $order_form_id;
		}
	}
	
	/**
	 * Edit an order form
	 *
	 * @param int $order_form_id The ID of the order form to edit
	 * @param array $vars An array of order form data including:
	 * 	- company_id (optional, defaults to current company ID)
	 *	- label The label used to access the order form
	 *	- name The name of the order form
	 *	- template The template to use for the order form
	 *	- template_style The template style to use for the order form
	 *	- type The type of order form
	 *	- client_group_id The default client group to assign clients to when ordering from this order form
	 *	- manual_review Whether or not to require all orders placed to be manually reviewed (default 0)
	 *	- allow_coupons Whether or not to allow coupons (default 0)
	 *	- require_ssl Whether or not to force secure connection (i.e. HTTPS) (default 0)
	 *	- require_captcha Whether or not to force captcha (default 0)
	 *	- require_tos Whether or not to require Terms of Service agreement (default 0)
	 *	- tos_url The URL to the terms of service agreement (optional)
	 *	- status The statuf ot he order form (active/inactive default active)
	 *	- meta An array of key/value pairs to assign to this order form (optional dependent upon the order type)
	 *	- groups An array of package group IDs to assign to this order form (optional dependent upon the order type)
	 *	- gateways An array of gateway ID to assign to this order form (optional dependent upon the order type)
	 *	- currencies An array of ISO 4217 currency codes to assign to this order form (optional dependent upon the order type)
	 * @return int The ID of the order form that was updated, void on error
	 */
	public function edit($order_form_id, array $vars) {
		
		if (!isset($vars['company_id']))
			$vars['company_id'] = Configure::get("Blesta.company_id");
		
		$vars['order_id'] = $order_form_id;
		
		if (isset($vars['type'])) {
			$order_type = $this->loadOrderType($vars['type']);
			
			// Run order form through order type settings
			$vars = $order_type->editSettings($vars);
			
			if (($errors = $order_type->errors())) {
				$this->Input->setErrors($errors);
				return;
			}
		}
		
		$this->Input->setRules($this->getFormRules($vars, true));
		
		if ($this->Input->validates($vars)) {
			$fields = array("company_id", "label", "name", "template", "template_style", "type", "client_group_id",
				"manual_review", "allow_coupons", "require_ssl", "require_tos", "tos_url",
				"require_captcha", "status");
			
			$this->Record->where("id", "=", $order_form_id)->update("order_forms", $vars, $fields);
			
			if (isset($vars['currencies']))
				$this->setCurrencies($order_form_id, $vars['currencies']);
			if (isset($vars['gateways']))
				$this->setGateways($order_form_id, $vars['gateways']);
			if (isset($vars['groups']))
				$this->setGroups($order_form_id, $vars['groups']);
			if (isset($vars['meta']))
				$this->setMeta($order_form_id, $vars['meta']);
				
			return $order_form_id;
		}
	}
	
	/**
	 * Permanently deletes the given order form
	 *
	 * @param int $order_form_id The ID of the order form to delete
	 */
	public function delete($order_form_id) {
		
		// Check that no pending order exists for this order form
		$this->Input->setRules($this->getDeleteFormRules());
		
		$vars = array('order_form_id' => $order_form_id);
		if ($this->Input->validates($vars)) {
			$this->Record->from("order_forms")->
				leftJoin("order_form_currencies", "order_form_currencies.order_form_id", "=", "order_forms.id", false)->
				leftJoin("order_form_gateways", "order_form_gateways.order_form_id", "=", "order_forms.id", false)->
				leftJoin("order_form_groups", "order_form_groups.order_form_id", "=", "order_forms.id", false)->
				leftJoin("order_form_meta", "order_form_meta.order_form_id", "=", "order_forms.id", false)->
				where("order_forms.id", "=", $order_form_id)->
				where("order_forms.company_id", "=", Configure::get("Blesta.company_id"))->
				delete(array("order_forms.*", "order_form_currencies.*", "order_form_gateways.*", "order_form_groups.*", "order_form_meta.*"));
		}
	}
	
	/**
	 * Returns a partial order form query
	 *
	 * @param string $status The status of results to fetch, null to fetch all results
	 * @return Record A partially built order form query
	 */
	private function getOrderForm($status = null) {
		if ($status)
			$this->Record->where("status", "=", $status);
			
		return $this->Record->select()->from("order_forms");
	}
	
	/**
	 * Returns all currencies set for the given form ID
	 *
	 * @param int $form_id The ID of the form to fetch on
	 * @return array An array of stdClass objects containing currencies
	 */	
	private function getCurrencies($form_id) {
		return $this->Record->select()->from("order_form_currencies")->
			where("order_form_id", "=", $form_id)->fetchAll();
	}
	
	/**
	 * Returns all gateways set for the given form ID
	 *
	 * @param int $form_id The ID of the form to fetch on
	 * @return array An array of stdClass objects containing gateway IDs
	 */	
	private function getGateways($form_id) {
		return $this->Record->select()->from("order_form_gateways")->
			where("order_form_id", "=", $form_id)->fetchAll();
	}

	/**
	 * Returns all package groups set for the given form ID
	 *
	 * @param int $form_id The ID of the form to fetch on
	 * @return array An array of stdClass objects containing order form groups
	 */	
	private function getGroups($form_id) {
		return $this->Record->select()->from("order_form_groups")->
			where("order_form_id", "=", $form_id)->fetchAll();
	}
	
	/**
	 * Returns all meta fields set for the given form ID
	 *
	 * @param int $form_id The ID of the form to fetch on
	 * @return array An array of stdClass objects representing order form meta fields
	 */
	private function getMeta($form_id) {
		return $this->Record->select()->from("order_form_meta")->
			where("order_form_id", "=", $form_id)->fetchAll();
	}
	
	/**
	 * Sets currencies for the given order form
	 *
	 * @param int $form_id The order form ID to set currencies for
	 * @param array $currencies An array of currency codes to set for the order form
	 */
	private function setCurrencies($form_id, array $currencies) {
		// Remove old assigned currencies
		$this->Record->from("order_form_currencies")->where("order_form_id", "=", $form_id)->delete();
		
		// Add any new currencies
		foreach ($currencies as $currency) {
			$this->Record->insert("order_form_currencies", array('order_form_id' => $form_id, 'currency' => $currency));
		}
	}
	
	/**
	 * Sets gateways for the given order form
	 *
	 * @param int $form_id The order form ID to set gateways for
	 * @param array $gateways An array of gateway IDs to set for the order form
	 */
	private function setGateways($form_id, array $gateways) {
		// Remove old assigned gateways
		$this->Record->from("order_form_gateways")->where("order_form_id", "=", $form_id)->delete();
		
		// Add any new gateways
		foreach ($gateways as $gateway) {
			$this->Record->insert("order_form_gateways", array('order_form_id' => $form_id, 'gateway_id' => $gateway));
		}
	}
	
	/**
	 * Sets package groups for the given order form
	 *
	 * @param int $form_id The order form ID to set package groups for
	 * @param array $groups An array of package group IDs to set for the order form
	 */
	private function setGroups($form_id, array $groups) {
		// Remove old assigned package groups
		$this->Record->from("order_form_groups")->where("order_form_id", "=", $form_id)->delete();
		
		// Add any new package groups
		foreach ($groups as $group) {
			$this->Record->insert("order_form_groups", array('order_form_id' => $form_id, 'package_group_id' => $group));
		}
	}
	
	/**
	 * Sets meta fields for the given order form
	 *
	 * @param int $form_id The order form ID to set meta fields for
	 * @param array $meta An array of key/value pairs
	 */
	private function setMeta($form_id, array $meta) {
		// Remove old assigned meta fields
		$this->Record->from("order_form_meta")->where("order_form_id", "=", $form_id)->delete();
		
		// Add any new meta fields
		foreach ($meta as $key => $value) {
			$this->Record->insert("order_form_meta", array('order_form_id' => $form_id, 'key' => $key, 'value' => $value));
		}
	}
	
	/**
	 * Returns all validation rules for adding/editing forms
	 *
	 * @param array $vars An array of input key/value pairs
	 * @param boolean $edit True if this if an edit, false otherwise
	 * @return array An array of validation rules
	 */
	private function getFormRules($vars, $edit = false) {
		$rules = array(
			'name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'if_set' => $edit,
					'message' => $this->_("OrderForms.!error.name.empty")
				)
			),
			'label' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'if_set' => $edit,
					'message' => $this->_("OrderForms.!error.label.empty")
				),
				'unique' => array(
					'rule' => array(array($this, "validateUnique"), $edit ? $this->ifSet($vars['order_id']) : null),
					'if_set' => $edit,
					'message' => $this->_("OrderForms.!error.label.unique")
				)
			),
			'template' => array(
				'supported' => array(
					'rule' => array(array($this, "validateTemplate"), $this->ifSet($vars['type'])),
					'if_set' => $edit,
					'message' => $this->_("OrderForms.!error.template.supported")
				)
			),
			'client_group_id' => array(
				'valid' => array(
					'rule' => array(array($this, "validateClientGroup")),
					'if_set' => $edit,
					'message' => $this->_("OrderForms.!error.client_group_id.valid")
				)
			),
			'require_tos' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateTermsRequired"), $this->ifSet($vars['tos_url'])),
					'message' => $this->_("OrderForms.!error.require_tos.valid")
				)
			),
			'date_added' => array(
				'valid' => array(
					'rule' => "isDate",
					'pre_format' => array(array($this, "dateToUtc")),
					'if_set' => true,
					'message' => $this->_("OrderForms.!error.date_added.valid")
				)
			)
		);
		
		return $rules;
	}
	
	/**
	 * Returns all validation rules to check when deleting an order form
	 *
	 * @return array An array of validation rules
	 */
	private function getDeleteFormRules() {
		return array(
			'order_form_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validatePendingOrdersExists")),
					'negate' => true,
					'message' => $this->_("OrderForms.!error.order_form_id.exists")
				)
			)
		);
	}
	
	/**
	 * Validates whether or not there are any pending orders for the given order form
	 *
	 * @param int $order_form_id The order form ID to validate against
	 * @return boolean True if there are pending orders, false otherwise
	 */
	public function validatePendingOrdersExists($order_form_id) {
		return (boolean)$this->Record->select(array("id"))->from("orders")->
			where("order_form_id", "=", $order_form_id)->
			where("status", "=", "pending")->limit(1)->fetch();
	}
	
	/**
	 * Validates whether or not the terms of service URL is required
	 *
	 * @param string $require_tos Set to "1" if TOS is required, "0" otherwise
	 * @param string $tos_url The URL to the terms of service
	 * @return boolean true if $require_tos is not "1" or if $tos_url is non-empty, false otherwise
	 */
	public function validateTermsRequired($require_tos, $tos_url) {
		return ($require_tos != "1" || !empty($tos_url));
	}
	
	/**
	 * Validates whether or not the given
	 *
	 * @param string $label The label to validate
	 * @param int $order_form_id The current order form ID (if it already exists) to exclude from the check
	 * @return boolean True if the label is unique and does not exist, false otherwise
	 */
	public function validateUnique($label, $order_form_id = null) {
		$this->Record->select(array("order_forms.label"))->
			from("order_forms")->where("order_forms.label", "=", $label)->
			where("order_forms.company_id", "=", Configure::get("Blesta.company_id"));
			
		if ($order_form_id)
			$this->Record->where("order_forms.id", "!=", $order_form_id);
			
		return !(boolean)$this->Record->fetch();
	}
	
	/**
	 * Validates that the given order form template supports the given order form type
	 *
	 * @param string $template The order form template
	 * @param string $type The order form type
	 * @return boolean True if the template is supported, false otherwise
	 */
	public function validateTemplate($template, $type) {
		$types = $this->getSupportedTypes($template);
		
		return in_array($type, $types);
	}
	
	/**
	 * Validates that the given client group exists and is part of the current company
	 *
	 * @param int $client_group_id The ID of the client group to verify exists
	 * @return boolean True if the client group exists and is part of the current company, false otherwise
	 */
	public function validateClientGroup($client_group_id) {
		return $this->Record->select(array("id"))->from("client_groups")->
			where("id", "=", $client_group_id)->
			where("company_id", "=", Configure::get("Blesta.company_id"))->
			fetch();
	}
}
?>