<?php
/**
 * Admin Client's Service Management
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2015, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminClientsService extends AdminController {
	
	/**
	 * Admin pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		$this->uses(array("Clients", "Services"));
		Language::loadLang(array("admin_clients_service"));
	}
	
	/**
	 * AJAX Retrieves a partial template of totals based on service changes
	 */
	public function updateTotals() {
		// Ensure we have a valid AJAX request with the given client and service
		if (!$this->isAjax() || !isset($this->get[0]) || !isset($this->get[1]) ||
			!($client = $this->Clients->get((int)$this->get[0])) ||
			!($service = $this->Services->get((int)$this->get[1])) ||
			$service->client_id != $client->id) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}
		
		$this->uses(array("Invoices", "ServiceChanges"));
		
		// White-list only specific fields
		$fields = array("pricing_id", "configoptions", "coupon_code");
		if (isset($this->post['price_override']) && $this->post['price_override'] == "true") {
			$fields = array_merge($fields, array("override_price", "override_currency"));
		}
		$vars = array_intersect_key($this->post, array_flip($fields));
		
		// Determine the pricing being used
		$pricing = null;
		if (isset($this->post['pricing_id'])) {
			$pricing = $this->getPricing($this->post['pricing_id']);
		}
		if (!$pricing) {
			$pricing = $service->package_pricing;
		}
		
		// Determine the items/totals
		$items = $this->ServiceChanges->getItems($service->id, $vars);
		$totals = $this->Invoices->getItemTotals($items['items'], $items['discounts'], $items['taxes']);
		
		echo $this->outputAsJson($this->totals($totals, $pricing->currency));
		return false;
	}
	
	/**
	 * Builds and returns the totals partial
	 *
	 * @param array $items An array of items including their totals
	 * @param string $currency The ISO 4217 currency code
	 * @return string The totals partial template
	 */
	private function totals(array $items, $currency) {
		return $this->partial("admin_clients_service_totals", array('items' => $items, 'currency' => $currency));
	}
	
	/**
	 * Retrieves package pricing info from the given pricing ID
	 *
	 * @param int $pricing_id The ID of the pricing to fetch
	 * @return mixed An stdClass representing the package pricing, or false if it is invalid
	 */
	private function getPricing($pricing_id) {
		$this->uses(array("Packages"));
		
		// Determine the matching pricing
		if (($package = $this->Packages->getByPricingId($pricing_id))) {
			foreach ($package->pricing as $price) {
				if ($price->id == $pricing_id) {
					return $price;
				}
			}
		}
		
		return false;
	}
}
