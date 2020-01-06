<?php
/**
 * Client portal transactions controller
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ClientTransactions extends ClientController {
	
	/**
	 * Pre Action
	 */
	public function preAction() {
		parent::preAction();
		
		// Load models, language
		$this->uses(array("Clients", "Transactions"));
	}
	
	/**
	 * List transactions
	 */
	public function index() {
		// Set the number of transactions of each type
		$status_count = array(
			'approved' => $this->Transactions->getStatusCount($this->client->id),
			'declined' => $this->Transactions->getStatusCount($this->client->id, "declined"),
			'void' => $this->Transactions->getStatusCount($this->client->id, "void"),
			'error' => $this->Transactions->getStatusCount($this->client->id, "error"),
			'pending' => $this->Transactions->getStatusCount($this->client->id, "pending"),
			'refunded' => $this->Transactions->getStatusCount($this->client->id, "refunded"),
			'returned' => $this->Transactions->getStatusCount($this->client->id, "returned")
		);
		
		$status = (isset($this->get[0]) ? $this->get[0] : "approved");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "date_added");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");
		
		$total_results = $this->Transactions->getListCount($this->client->id, $status);
		$this->set("transactions", $this->Transactions->getList($this->client->id, $status, $page, array($sort => $order)));
		$this->set("client", $this->client);
		$this->set("status", $status);
		$this->set("status_count", $status_count);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		$this->set("widget_state", isset($this->widgets_state['transactions']) ? $this->widgets_state['transactions'] : null);
		// Holds the name of all of the transaction types
		$this->set("transaction_types", $this->Transactions->transactionTypeNames());
		// Holds the name of all of the transaction status values
		$this->set("transaction_status", $this->Transactions->transactionStatusNames());
		$this->structure->set("page_title", Language::_("ClientTransactions.index.page_title", true, $this->client->id_code));
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination_client"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "transactions/index/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order),
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(isset($this->get['whole_widget']) ? null : (isset($this->get[2]) || isset($this->get['sort'])));
	}
	
	/**
	 * AJAX request for all transactions an invoice has applied
	 */
	public function applied() {
		$this->uses(array("Invoices"));
		
		$transaction = $this->Transactions->get((int)$this->get[0]);
		
		// Ensure the transaction belongs to the client and this is an ajax request
		if (!$this->isAjax() || !$transaction || $transaction->client_id != $this->client->id) {
			header($this->server_protocol . " 401 Unauthorized");
			exit();
		}
		
		$vars = array(
			'client'=>$this->client,
			'applied'=>$this->Transactions->getApplied($transaction->id)
		);
		
		// Send the template
		echo $this->partial("client_transactions_applied", $vars);
		
		// Render without layout
		return false;
	}
}
?>