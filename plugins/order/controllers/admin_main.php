<?php
/**
 * Order main controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.order
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminMain extends OrderController {
	
	/**
	 * Pre action
	 */
	public function preAction() {
		parent::preAction();
		
		$this->requireLogin();
		
		$this->uses(array("Order.OrderOrders"));
		
		Language::loadLang("admin_main", null, PLUGINDIR . "order" . DS . "language" . DS);
	}

	/**
	 * Renders the orders widget
	 */
	public function index() {
		// Only available via AJAX
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "billing/");
			
		$status = (isset($this->get[0]) ? $this->get[0] : "pending");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_added");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");

		if (isset($this->get[0]))
			$status = $this->get[0];
			
		// If no page set, fetch counts
		if (!isset($this->get[1])) {
			$status_count = array(
				'pending' => $this->OrderOrders->getListCount("pending"),
				'accepted' => $this->OrderOrders->getListCount("accepted"),
				'fraud' => $this->OrderOrders->getListCount("fraud"),
				'canceled' => $this->OrderOrders->getListCount("canceled"),
			);
			$this->set("status_count", $status_count);
		}
		
		$statuses = $this->OrderOrders->getStatuses();
		unset($statuses[$status]);
		
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		$this->set("status", $status);
		$this->set("statuses", $statuses);
		
		$total_results = $this->OrderOrders->getListCount($status);
		$this->set("orders", $this->OrderOrders->getList($status, $page, array($sort => $order)));
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "widget/order/admin_main/index/" . $status . "/[p]/",
				'params'=>array('sort' => $sort, 'order' => $order),
			)
		);
		$this->helpers(array("Pagination" => array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		return $this->renderAjaxWidgetIfAsync(isset($this->get['sort']) ? true : (isset($this->get[1]) || isset($this->get[0]) ? false : null));
	}
	
	/**
	 * List related information for a given order
	 */
	public function orderInfo() {
		// Ensure a department ID was given
		if (!$this->isAjax() || !isset($this->get[0]) ||
			!($order = $this->OrderOrders->get($this->get[0]))) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}
		
		$this->uses(array("Transactions", "Services", "Packages"));
		
		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period => $lang) {
			$periods[$period . "_plural"] = $lang;
		}
		
		// Set services
		$services = array();
		foreach ($order->services as $temp) {
			$services[] = $this->Services->get($temp->service_id);
		}
		
		$vars = array(
			'order' => $order,
			'applied'=> $this->Transactions->getApplied(null, $order->invoice_id),
			'services' => $services,
			'periods' => $periods,
			'transaction_types' => $this->Transactions->transactionTypeNames()
		);
		
		// Send the template
		echo $this->partial("admin_main_orderinfo", $vars);
		
		// Render without layout
		return false;
	}
	
	/**
	 * Outputs the badge response for the current number of orders with the given status
	 */
	public function statusCount() {
		// Only available via AJAX
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "billing/");
			
		$this->uses(array("Order.OrderOrders"));
		$status = isset($this->get[0]) ? $this->get[0] : "pending";
		
		echo $this->OrderOrders->getListCount($status);
		return false;
	}
	
	/**
	 * Settings
	 */
	public function settings() {
		
		// Only available via AJAX
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "billing/");
		
		$this->helpers(array("DataStructure"));
		$this->ArrayHelper = $this->DataStructure->create("Array");
		
		$this->uses(array("Order.OrderStaffSettings"));
		
		$settings = $this->ArrayHelper->numericToKey($this->OrderStaffSettings->getSettings($this->Session->read("blesta_staff_id"), $this->company_id), "key", "value");
		$this->set("vars", $settings);
		
		return $this->renderAjaxWidgetIfAsync(false);
	}
	
	/**
	 * Update settings
	 */
	public function update() {
		
		$this->uses(array("Order.OrderStaffSettings"));
		
		// Get all overview settings
		if (!empty($this->post)) {
			
			$this->OrderStaffSettings->setSettings($this->Session->read("blesta_staff_id"), $this->company_id, $this->post);
		
			$this->flashMessage("message", Language::_("AdminMain.!success.settings_updated", true));
		}
		
		$this->redirect($this->base_uri . "billing/");
	}

	/**
	 * Update status for the given set of orders
	 */
	public function updateStatus() {
		
		if (isset($this->post['order_id']))
			$this->OrderOrders->setStatus($this->post);
		
		$this->flashMessage("message", Language::_("AdminMain.!success.status_updated", true));
		$this->redirect($this->base_uri . "billing/");
	}
}
?>