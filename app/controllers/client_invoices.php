<?php
/**
 * Client portal invoices controller
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ClientInvoices extends ClientController {
	
	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		$this->uses(array("Clients", "Invoices"));
	}
	
	/**
	 * List invoices
	 */
	public function index() {
		// Get current page of results
		$status = ((isset($this->get[0]) && ($this->get[0] == "closed")) ? $this->get[0] : "open");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_due");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");
		
		// Get the invoices
		$invoices = $this->Invoices->getList($this->client->id, $status, $page, array($sort => $order));
		$total_results = $this->Invoices->getListCount($this->client->id, $status);
		
		// Set the number of invoices of each type
		$status_count = array(
			'open' => $this->Invoices->getStatusCount($this->client->id, "open"),
			'closed' => $this->Invoices->getStatusCount($this->client->id, "closed")
		);
		
		$this->set("status", $status);
		$this->set("client", $this->client);
		$this->set("invoices", $invoices);
		$this->set("status_count", $status_count);
		$this->set("widget_state", isset($this->widgets_state['invoices']) ? $this->widgets_state['invoices'] : null);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		$this->structure->set("page_title", Language::_("ClientInvoices.index.page_title", true, $this->client->id_code));
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination_client"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "invoices/index/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order)
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : (isset($this->get[1]) || isset($this->get['sort'])));
	}
	
	/**
	 * AJAX request for all transactions an invoice has applied
	 */
	public function applied() {
		$this->uses(array("Transactions"));
		
		$invoice = $this->Invoices->get((int)$this->get[0]);
		
		// Ensure the invoice belongs to the client and this is an ajax request
		if (!$this->isAjax() || !$invoice || $invoice->client_id != $this->client->id) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}
		
		$vars = array(
			'client'=>$this->client,
			'applied'=>$this->Transactions->getApplied(null, $this->get[0]),
			// Holds the name of all of the transaction types
			'transaction_types'=>$this->Transactions->transactionTypeNames()
		);
		
		// Send the template
		echo $this->partial("client_invoices_applied", $vars);
		
		// Render without layout
		return false;
	}
	
	/**
	 * Streams the given invoice to the browser
	 */
	public function view() {
		// Ensure we have a invoice to load, and that it belongs to this client
		if (!isset($this->get[0]) || !($invoice = $this->Invoices->get((int)$this->get[0])) ||
			($invoice->client_id != $this->client->id))
			$this->redirect($this->base_uri);
		
		$this->components(array("InvoiceDelivery"));
		$this->InvoiceDelivery->downloadInvoices(array($invoice->id));
		exit;
	}
}
?>