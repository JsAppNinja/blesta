<?php
/**
 * Domain Name Order Type
 *
 * @package blesta
 * @subpackage blesta.plugins.order.lib.order_types
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class OrderTypeDomain extends OrderType {
	
	/**
	 * @var string The authors of this order type
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	
	/**
	 * Construct
	 */
	public function __construct() {
		Language::loadLang("order_type_domain", null, dirname(__FILE__) . DS . "language" . DS);
		
		Loader::loadComponents($this, array("Input"));
	}
	
	/**
	 * Returns the name of this order type
	 *
	 * @return string The common name of this order type
	 */
	public function getName() {
		return Language::_("OrderTypeDomain.name", true);
	}

	/**
	 * Returns the name and URL for the authors of this order type
	 *
	 * @return array The name and URL of the authors of this order type
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Create and return the view content required to modify the custom settings of this order form
	 *
	 * @param array $vars An array of order form data (including meta data unique to this form type) to be updated for this order form
	 * @return string HTML content containing the fields to update the meta data for this order form
	 */
	public function getSettings(array $vars = null) {
		$this->view = new View();
		$this->view->setDefaultView("plugins" . DS . "order" . DS . "lib" . DS . "order_types" . DS . "domain" . DS);
		$this->view->setView("settings", "default");
		
		Loader::loadHelpers($this, array("Html", "Form"));
		
		Loader::loadModels($this, array("Packages"));
		
		// Fetch all available package groups
		$package_groups = $this->Form->collapseObjectArray($this->Packages->getAllGroups(Configure::get("Blesta.company_id"), null, "standard"), "name", "id");
		$this->view->set("package_groups", $package_groups);
		
		$this->view->set("vars", (object)$vars);
		
		return $this->view->fetch();
	}
	
	/**
	 * Validates the given data (settings) to be updated for this order form
	 *
	 * @param array $vars An array of order form data (including meta data unique to this form type) to be updated for this order form
	 * @return array The order form data to be updated in the database for this order form, or reset into the form on failure
	 */
	public function editSettings(array $vars) {
		$rules = array(
			'template' => array(
				'valid' => array(
					'rule' => array(array($this, "validateTemplate"), isset($vars['groups']) ? $vars['groups'] : null),
					'message' => Language::_("OrderTypeDomain.!error.template.valid", true)
				)
			)
		);
		$this->Input->setRules($rules);
		if ($this->Input->validates($vars))
			return $vars;
	}
	
	/**
	 * Verify that the given template is valid
	 *
	 * @param string $template The template type used
	 * @param array $groups An array of package groups selected for the order form
	 */
	public function validateTemplate($template, $groups) {
		if ($template == "ajax" && empty($groups))
			return false;
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
	 * Sets the SessionCart being used by the order form
	 *
	 * @param SessionCart $cart The session cart being used by the order form
	 */
	public function setCart(SessionCart $cart) {
		parent::setCart($cart);
		$this->cart->setCallback("prequeueItem", array($this, "prequeueItem"));
		$this->cart->setCallback("addItem", array($this, "addItem"));
	}
	
	/**
	 * Determines whether or not the order type requires the perConfig step of
	 * the order process to be invoked.
	 *
	 * @return boolean If true will invoke the preConfig step before selecting a package, false to continue to the next step
	 */
	public function requiresPreConfig() {
		return true;
	}
	
	/**
	 * Handle an HTTP request. This allows an order template to execute custom code
	 * for the order type being used, allowing tighter integration between the order type and the template.
	 * This can be useful for supporting AJAX requests and the like.
	 *
	 * @param array $get All GET request parameters
	 * @param array $post All POST request parameters
	 * @param array $files All FILES request parameters
	 * @param stdClass $order_form The order form currently being used
	 * @return string HTML content to render (if any)
	 */
	public function handleRequest(array $get = null, array $post = null, array $files = null) {

		$this->view = new View();
		$this->view->setDefaultView("plugins" . DS . "order" . DS);
		$this->view->setView(null, "templates" . DS . $this->order_form->template . DS . "types" . DS . $this->order_form->type);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "WidgetClient", 'CurrencyFormat' => array(Configure::get("Blesta.company_id"))));

		$tlds = $this->getTlds();
		$domains = array();
		
		if (isset($post['domain'])) {
			if (!isset($post['tlds']))
				$post['tlds'] = array();
			
			$post['domain'] = strtolower($post['domain']);
			
			$sld = $this->getSld($post['domain']);
			
			$tld = str_replace($sld, "", $post['domain']);
			if ($tld != "" && !in_array($tld, $post['tlds']))
				$post['tlds'][] = $tld;
			
			foreach ($post['tlds'] as $tld) {
				$pack = $this->domainPackageGroup($sld . $tld, $tlds);
				if ($pack) {
					$pack[0] = $this->updatePackagePricing($pack[0], $this->cart->getData("currency"));
					
					$domains[$sld . $tld] = new stdClass();
					$domains[$sld . $tld]->package = $pack[0];
					$domains[$sld . $tld]->group = $pack[1];
				}
				
				$post['domains'][] = $sld . $tld;
			}
			
			// If no packages found, nothing to do...
			if (empty($domains)) {
				$this->Input->setErrors(array(
					'domain' => array(
						'invalid' => Language::_("OrderTypeDomain.!error.domain.invalid", true)
					)
				));
			}
			else {
				if (isset($post['transfer'])) {
					// DO not perform WHOIS check
					
					// Domain name blank
					if (empty($post['domain'])) {
						$this->Input->setErrors(array(
							'domain' => array(
								'empty' => Language::_("OrderTypeDomain.!error.domain.empty", true)
							)
						));
						$domains = array();
					}
				}
				else {
					Loader::loadModels($this, array("ModuleManager"));
					$availability = array();
					foreach ($domains as $domain => $pack) {
						$availability[$domain] = $this->ModuleManager->moduleRpc($pack->package->module_id, "checkAvailability", array($domain), $pack->package->module_row);
					}
					
					if (($errors = $this->ModuleManager->errors()))
						$this->Input->setErrors($errors);
					
					$this->view->set("availability", $availability);
				}
			}
			
			$this->view->set("periods", $this->getPricingPeriods());
		}

		$this->view->base_uri = $get['base_uri'];
		$this->view->set("order_form", $this->order_form);
		$this->view->set("domains", $domains);
		$this->view->set("tlds", $tlds);		
		$this->view->set("vars", (object)$post);
		
		return $this->view->fetch("lookup");
	}
	
	/**
	 * Notifies the order type that the given action is complete, and allows
	 * the other type to modify the URI the user is redirected to
	 *
	 * @param string $action The controller.action completed
	 * @param array $params An array of optional key/value pairs specific to the given action
	 * @return string The URI to redirec to, null to redirect to the default URI
	 */
	public function redirectRequest($action, array $params = null) {
		switch ($action) {
			case "config.index":
				$meta = $this->formatMeta($this->order_form->meta);
				$item = $this->cart->getItem($params['item_index']);

				if ($item && $item['group_id'] == $meta['domain_group'])
					return $this->base_uri . "plugin/order/main/index/" . $this->order_form->label . "/?skip=true";
				break;
		}
		return null;
	}
	
	/**
	 * Returns all package groups that are valid for this order form
	 *
	 * @return A numerically indexed array of package group IDs
	 */
	public function getGroupIds() {
		$group_ids = parent::getGroupIds();
		
		$meta = $this->formatMeta($this->order_form->meta);
		$group_ids[] = $meta['domain_group'];
		
		return $group_ids;
	}
	
	/**
	 * Handle the callback for the prequeueItem event
	 *
	 * @param EventObject $event The event triggered when an item is prequeued for the cart
	 */
	public function prequeueItem(EventObject $event) {
		$params = $event->getParams();
		if (isset($params['item'])) {
			$item = $params['item'];
			$item['domain'] = $this->cart->getData("domain");
			
			$event->setReturnVal($item);
		}
	}
	
	/**
	 * Handle the callback for theaddItem event
	 *
	 * @param EventObject $event The event triggered when an item is added to the cart
	 */
	public function addItem(EventObject $event) {
		$params = $event->getParams();
		$meta = $this->formatMeta($this->order_form->meta);

		if ($params['item'] && $params['item']['group_id'] == $meta['domain_group'] && isset($params['item']['domain']))
			$this->cart->setData("domain", $params['item']['domain']);
	}
	
	/**
	 * Set all pricing periods
	 */
	private function getPricingPeriods() {
		if (!isset($this->Packages))
			Loader::loadModels($this, array("Packages"));
			
		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period => $lang)
			$periods[$period . "_plural"] = $lang;
		return $periods;
	}
	
	/**
	 * Select the appropriate package to use for the given domain, then redirect to configure the package
	 *
	 * @param string $domain The domain
	 * @param array An array key/value pairs where each key is a TLD and each value is an array containing the package and group used for that TLD
	 * @return array An array containing the package group and package used for this, null if the TLD does not exist
	 */
	private function domainPackageGroup($domain, array $tlds) {		
		foreach ($tlds as $tld => $pack) {
			if (substr($domain, -strlen($tld)) == $tld) {
				return $pack;
			}
		}

		return null;
	}
	
	/**
	 * Fetches all TLDs support by the order form
	 *
	 * @return array An array key/value pairs where each key is a TLD and each value is an array containing the package and group used for that TLD
	 */
	private function getTlds() {
		if (!isset($this->Packages))
			Loader::loadModels($this, array("Packages"));
		
		$tlds = array();
		
		$meta = $this->formatMeta($this->order_form->meta);
		$group = new stdClass();
		$group->order_form_id = $this->order_form->id;
		$group->package_group_id = $meta['domain_group'];
		
		// Fetch all packages for this group
		$packages[$group->package_group_id] = $this->Packages->getAllPackagesByGroup($group->package_group_id);
		
		foreach ($packages[$group->package_group_id] as $package) {
			$package = $this->Packages->get($package->id);
			
			if ($package && $package->status == "active" && isset($package->meta->tlds)) {
				foreach ($package->meta->tlds as $tld) {
					if (isset($tlds[$tld]))
						continue;
					
					$tlds[$tld] = array($package, $group);
				}
			}
		}
		
		ksort($tlds);
		
		return $tlds;
	}
	
	/**
	 * Returns the SLD for the given domain (ignoring www. as a subdomain)
	 *
	 * @param string $domain The domain to find the SLD of
	 * @return string The SLD of $domain
	 */
	private function getSld($domain) {
		preg_match("/^(www\.)*(.*)\.(.*)/i", $domain, $matches);
		
		return isset($matches[2]) ? $matches[2] : $domain;
	}
	
	/**
	 * Format meta into key/value array
	 *
	 * @param array An array of stdClass object representing meta fields
	 * @return array An array of key/value pairs
	 */
	private function formatMeta($meta) {
		$result = array();
		foreach ($meta as $field) {
			$result[$field->key] = $field->value;
		}
		return $result;
	}
}
?>