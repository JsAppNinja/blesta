<?php
/**
 * Admin Search. Searches clients, invoices, transactions, services, and plugin
 * events.
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminSearch extends AppController {
	
	/**
	 * Search pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		// Require login
		$this->requireLogin();
		
		Language::loadLang(array("admin_search"));
	}
	
	/**
	 * Handle search requests and results
	 */
	public function index() {
		$this->uses(array("Staff"));
		
		// Hold all results for each searched criteria
		$results = array();
		$search_state = null;
		
		if (!empty($this->post)) {
			
			// Redirect custom search options
			if (strpos($this->post['search_type'], "/") !== false) {
				$this->saveSearchType($this->post['search_type']);
				$this->redirect($this->post['search_type'] . "?search=" . $this->post['search']);
			}
			
			// Use smart search if none given
			if (!isset($this->post['search_type']))
				$this->post['search_type'] = "smart";
			
			// Use smart search if invalid type given
			if (!$this->isValid($this->post['search_type']))
				$this->post['search_type'] = "smart";
			
			// Save the search type
			$search_state = $this->post['search_type'];
			$this->saveSearchType($search_state);
			
			if (isset($this->post['search']))
				$results = $this->{$this->post['search_type']}($this->post['search']);
			
			$this->set("vars", (object)$this->post);
		}
		elseif (!empty($this->get)) {
			
			// Redirect custom search options
			if (strpos($this->get['search_type'], "/") !== false)
				$this->redirect($this->get['search_type'] . "?search=" . $this->get['search']);
			
			// Use smart search if none given
			if (!isset($this->get['search_type']))
				$this->get['search_type'] = "smart";
			
			// Use smart search if invalid type given
			if (!$this->isValid($this->get['search_type']))
				$this->get['search_type'] = "smart";
			
			if (isset($this->get['search']))
				$results = $this->{$this->get['search_type']}($this->get['search'], (isset($this->get['p']) ? $this->get['p'] : 1));
			
			$this->set("vars", (object)$this->get);
		}
		else
			$this->redirect($this->base_uri);
		
		$this->set("results", $results);
		
		// Render the request if AJAX
		if ($this->isAjax())
			return $this->renderAjaxWidgetIfAsync(true);
			
		// Set the search type in the structure, just in case it changed
		if ($search_state != null)
			$this->structure->set("default_search_option", $search_state);
		
		$search_query = (isset($this->post['search']) ? $this->post['search'] : $this->get['search']);
		$this->structure->set("page_title", Language::_("AdminSearch.index.page_title", true, $search_query));
	}
	
	/**
	 * Smart search, search all criteria
	 *
	 * @param string $search The search query to perform
	 */
	private function smart($search) {
		
		return array_merge(
			$this->clients($search, 1, false),
			$this->invoices($search, 1, false),
			$this->transactions($search, 1, false),
			$this->services($search, 1, false),
			$this->packages($search, 1, false)
		);
	}
	
	/**
	 * Search clients
	 *
	 * @param string $search The search query to perform
	 * @param int $page The page to fetch
	 * @param boolean $pagination Whether or not to include pagination
	 * @return array An multi-dimensional array of parameters to set in a full view (contents include partial view)
	 */
	private function clients($search, $page=1, $pagination=true) {
		
		$this->uses(array("Clients", "ClientGroups"));
		$this->helpers(array("Color"));
		$clients = array();
		
		// Verify authorization to view these results
		if (!$this->authorized("admin_clients", "*"))
			return $clients;
		
		// Search clients
		$clients = $this->Clients->search($search);
		
		// If only 1 result found, redirect to that result
		if (count($clients) == 1)
			$this->redirect($this->base_uri . "clients/view/" . $clients[0]->id);

		// Add client group info to each client
		$client_groups = array();
		$num_clients = count($clients);
		for ($i=0; $i<$num_clients; $i++) {
			if (!array_key_exists($clients[$i]->client_group_id, $client_groups))
				$client_groups[$clients[$i]->client_group_id] = $this->ClientGroups->get($clients[$i]->client_group_id);
				
			$clients[$i]->group = $client_groups[$clients[$i]->client_group_id];
		}
		
		if ($pagination) {
			// Overwrite default pagination settings
			$settings = array_merge(Configure::get("Blesta.pagination"), array(
					'total_results' => $this->Clients->getSearchCount($search),
					'uri'=>$this->base_uri . "search/",
					'params'=>array('search_type'=>"clients", 'p'=>"[p]", 'search'=>$search)
				)
			);
			$this->helpers(array("Pagination"=>array($this->get, $settings)));
			$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		}
		
		// Display message that results have been truncated
		if (!$pagination && $num_clients >= Configure::get("Blesta.results_per_page"))
			$this->setMessage("notice", Language::_("AdminSearch.!notice.results_truncated", true));

		return array('clients' => $this->partial("admin_search_clients", array('clients' => $clients)));
	}
	
	/**
	 * Search invoices
	 *
	 * @param string $search The search query to perform
	 * @param int $page The page to fetch
	 * @param boolean $pagination Whether or not to include pagination
	 * @return array An multi-dimensional array of parameters to set in a full view (contents include partial view)
	 */
	private function invoices($search, $page=1, $pagination=true) {
		
		$this->uses(array("Invoices"));
		$invoices = array();
		
		// Verify authorization to view these results
		if ((!$this->authorized("admin_clients", "invoices") && !$this->authorized("admin_billing", "invoices")))
			return $invoices;
		
		// Search invoices
		$invoices = $this->Invoices->search($search, $page);
		
		// Set the invoice status type (for display)
		foreach ($invoices as &$invoice) {
			// Set invoice status
			switch ($invoice->status) {
				case "active":
					$invoice->status = "open";
					if ($invoice->date_closed != null)
						$invoice->status = "closed";
					break;
				case "void":
				case "draft":
				default:
					break;
			}
		}
		
		if ($pagination) {
			// Overwrite default pagination settings
			$settings = array_merge(Configure::get("Blesta.pagination"), array(
					'total_results' => $this->Invoices->getSearchCount($search),
					'uri'=>$this->base_uri . "search/",
					'params'=>array('search_type'=>"invoices", 'p'=>"[p]", 'search'=>$search)
				)
			);
			$this->helpers(array("Pagination"=>array($this->get, $settings)));
			$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		}
		
		// Display message that results have been truncated
		$num_invoices = count($invoices);
		if (!$pagination && $num_invoices >= Configure::get("Blesta.results_per_page"))
			$this->setMessage("notice", Language::_("AdminSearch.!notice.results_truncated", true));
		
		return array('invoices' => $this->partial("admin_search_invoices", array('invoices' => $invoices)));
	}
	
	/**
	 * Search transactions
	 *
	 * @param string $search The search query to perform
	 * @param int $page The page to fetch
	 * @param boolean $pagination Whether or not to include pagination
	 * @return array An multi-dimensional array of parameters to set in a full view (contents include partial view)
	 */
	private function transactions($search, $page=1, $pagination=true) {
		
		$this->uses(array("Transactions"));
		$transactions = array();
		
		// Verify authorization to view these results
		if ((!$this->authorized("admin_clients", "transactions") && !$this->authorized("admin_billing", "transactions"))) {
			return $transactions;
		}
		
		$transactions = $this->Transactions->search($search, $page);
		
		// Set credited amount
		foreach ($transactions as &$transaction) {
			$transaction->credited_amount = $this->Transactions->getCreditedAmount($transaction->id);
		}
		
		// If only 1 result found, redirect to that result
		if (count($transactions) == 1)
			$this->redirect($this->base_uri . "clients/edittransaction/" . $transactions[0]->client_id . "/" . $transactions[0]->id);
		
		$vars = array(
			'transactions' => $transactions,
			'transaction_types' => $this->Transactions->transactionTypeNames(),
			'transaction_status' => $this->Transactions->transactionStatusNames()
		);
		
		if ($pagination) {
			// Overwrite default pagination settings
			$settings = array_merge(Configure::get("Blesta.pagination"), array(
					'total_results' => $this->Transactions->getSearchCount($search),
					'uri'=>$this->base_uri . "search/",
					'params'=>array('search_type'=>"transactions", 'p'=>"[p]", 'search'=>$search)
				)
			);
			$this->helpers(array("Pagination"=>array($this->get, $settings)));
			$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		}
		
		// Display message that results have been truncated
		$num_transactions = count($transactions);
		if (!$pagination && $num_transactions >= Configure::get("Blesta.results_per_page"))
			$this->setMessage("notice", Language::_("AdminSearch.!notice.results_truncated", true));
		
		return array('transactions' => $this->partial("admin_search_transactions", $vars));
	}
	
	/**
	 * Search services
	 *
	 * @param string $search The search query to perform
	 * @param int $page The page to fetch
	 * @param boolean $pagination Whether or not to include pagination
	 * @return array An multi-dimensional array of parameters to set in a full view (contents include partial view)
	 */
	private function services($search, $page=1, $pagination=true) {
		
		$this->uses(array("Services"));
		$services = array();
		
		// Verify authorization to view these results
		if ((!$this->authorized("admin_clients", "services") && !$this->authorized("admin_billing", "services"))) {
			return $services;
		}
		
		$services = $this->Services->search($search, $page, $pagination);
		
		if ($pagination) {
			// Overwrite default pagination settings
			$settings = array_merge(Configure::get("Blesta.pagination"), array(
					'total_results' => $this->Services->getSearchCount($search, $pagination),
					'uri'=>$this->base_uri . "search/",
					'params'=>array('search_type'=>"services", 'p'=>"[p]", 'search'=>$search)
				)
			);
			$this->helpers(array("Pagination"=>array($this->get, $settings)));
			$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		}
		
		// Display message that results have been truncated
		$num_services = count($services);
		if (!$pagination && $num_services >= Configure::get("Blesta.results_per_page"))
			$this->setMessage("notice", Language::_("AdminSearch.!notice.results_truncated", true));
		
		return array('services' => $this->partial("admin_search_services", array('services' => $services, 'service_status'=>$this->Services->getStatusTypes())));
	}
	
	/**
	 * Search packages
	 *
	 * @param string $search The search query to perform
	 * @param int $page The page to fetch
	 * @param boolean $pagination Whether or not to include pagination
	 * @return array An multi-dimensional array of parameters to set in a full view (contents include partial view)
	 */
	private function packages($search, $page=1, $pagination=true) {
		
		$this->uses(array("Packages"));
		$packages = array();
		
		// Verify authorization to view these results
		if (!$this->authorized("admin_packages", "*"))
			return $packages;
		
		$packages = $this->Packages->search($search, $page);
		
		if ($pagination) {
			// Overwrite default pagination settings
			$settings = array_merge(Configure::get("Blesta.pagination"), array(
					'total_results' => $this->Packages->getSearchCount($search),
					'uri'=>$this->base_uri . "search/",
					'params'=>array('search_type'=>"packages", 'p'=>"[p]", 'search'=>$search)
				)
			);
			$this->helpers(array("Pagination"=>array($this->get, $settings)));
			$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		}
		
		// Display message that results have been truncated
		$num_packages = count($packages);
		if (!$pagination && $num_packages >= Configure::get("Blesta.results_per_page"))
			$this->setMessage("notice", Language::_("AdminSearch.!notice.results_truncated", true));
		
		return array('packages' => $this->partial("admin_search_packages", array('packages' => $packages, 'package_status' => $this->Packages->getStatusTypes())));
	}
	
	/**
	 * Validate whether the given search type is acceptable or not
	 *
	 * @param string $search_type The type of search to validate
	 * @return boolean True if the search type is valid, false otherwise
	 */
	private function isValid($search_type) {
		// Valid search types
		$search_types = array("smart", "clients", "invoices", "transactions",
			"services", "packages");
		
		if (!in_array($search_type, $search_types))
			return false;
		
		return true;
	}
	
	/**
	 * Updates the staff setting, saving the currently selected search type
	 *
	 * @param string $search_type The type of search to save
	 */
	private function saveSearchType($search_type) {
		// Update Staff member's search setting
		if ($search_type != null)
			$this->Staff->setSetting($this->Session->read("blesta_staff_id"), "search_" . Configure::get("Blesta.company_id") . "_state", $search_type);
	}
}
?>