<?php
/**
 * Order System summary controller
 *
 * @package blesta
 * @subpackage blesta.plugins.order.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Summary extends OrderFormController {
	
	/**
	 * Returns the order summary partial, or, if this is an AJAX request, outputs
	 * the order summary partial.
	 */
	public function index() {
		// Allow temporary items to appear in the summary
		$item = null;
		$items = array();
		if (!empty($this->post)) {
			$items = array($this->post);
			$items[0]['addons'] = array();
			if (isset($items[0]['addon'])) {
				foreach ($items[0]['addon'] as $addon_group_id => $addon) {
					// Queue addon items for configuration
					if (array_key_exists('pricing_id', $addon) && !empty($addon['pricing_id'])) {
						$uuid = uniqid();
						$items[] = array(
							'pricing_id' => $addon['pricing_id'],
							'group_id' => $addon_group_id,
							'uuid' => $uuid
						);
						$items[0]['addons'][] = $uuid;
					}
				}
			}
			unset($item['addon'], $item['submit']);
		}
		$summary = $this->getSummary($items, isset($this->get['item']) ? $this->get['item'] : null);
		
		$client = $this->client;
		$order_form = $this->order_form;
		$periods = $this->getPricingPeriods();
		extract($this->getPaymentOptions());
		$vars = (object)$this->post;
		$temp_coupon = $this->SessionCart->getData("temp_coupon");
		
		$this->set(compact("summary", "client", "order_form", "periods", "nonmerchant_gateways", "merchant_gateway", "payment_types", "vars", "temp_coupon"));

		return $this->renderView();
	}
	
}
?>