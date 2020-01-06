<?php
/**
 * Transaction management
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Transactions extends AppModel {

	/**
	 * @var int The decimal precision for rounding float values
	 */
	private $float_precision = 4;

	/**
	 * Initialize Transactions
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("transactions"));
	}

	/**
	 * Adds a transaction to the system
	 *
	 * @param array $vars An array of tax info including:
	 *	- client_id The client ID this transaction applies to.
	 *	- amount The transaction amount
	 *	- currency The currency code of this transaction
	 *	- type The transaction type (cc, ach, other)
	 *	- transaction_type_id The transaction type ID (optional, default NULL)
	 *	- account_id The account ID (optional, default NULL)
	 *	- gateway_id The gateway ID (optional, default NULL)
	 *	- transaction_id The gateway supplied transaction ID (optional, default NULL)
	 *	- parent_transaction_id The gateway supplied parent transaction ID (optional, default null)
	 *	- reference_id The gateway supplied reference ID (optional, default NULL)
	 *	- status The transaction status (optional, default 'approved')
	 *	- date_added The date the transaction was received (optional, defaults to current date)
	 * @return int The ID of the transaction created, void on error
	 */
	public function add(array $vars) {

		$rules = $this->getTransactionRules();

		if (!isset($vars['date_added']))
			$vars['date_added'] = date("c");
		if (isset($vars['gateway_id']) && $vars['gateway_id'] == "")
			$vars['gateway_id'] = null;

		$this->Input->setRules($rules);

		if ($this->Input->validates($vars)) {
			// Add a transaction
			$fields = array("client_id", "amount", "currency", "type", "transaction_type_id",
				"account_id", "gateway_id", "transaction_id", "parent_transaction_id", "reference_id", "status", "date_added"
			);
			$this->Record->insert("transactions", $vars, $fields);

			$transaction_id = $this->Record->lastInsertId();

			$this->Events->register("Transactions.add", array("EventsTransactionsCallback", "add"));
			$this->Events->trigger(new EventObject("Transactions.add", compact("transaction_id")));

			return $transaction_id;
		}
	}

	/**
	 * Updates a transaction
	 *
	 * @param int $transaction_id The transaction ID
	 * @param array $vars An array of tax info including:
	 *	- amount The transaction amount (optional)
	 *	- currency The currency code of this transaction (optional)
	 *	- type The transaction type (cc, ach, other) (optional)
	 *	- transaction_type_id The transaction type ID (optional, default NULL)
	 *	- account_id The account ID (optional, default NULL)
	 *	- gateway_id The gateway ID (optional, default NULL)
	 *	- transaction_id The gateway supplied transaction ID (optional, default NULL)
	 *	- parent_transaction_id The gateway supplied parent transaction ID (optional, default null)
	 *	- reference_id The reference ID (optional, default NULL)
	 *	- status The transaction status (optional, default 'approved')
	 *	- date_added The date the transaction was received (optional, defaults to current date)
	 * @param int $staff_id The ID of the staff member that made the edit for logging purposes
	 * @return int The ID of the transaction created, void on error
	 */
	public function edit($transaction_id, array $vars, $staff_id = null) {

		$rules = $this->getTransactionRules();
		unset($rules['client_id']);

		$this->Input->setRules($rules);

		if ($this->Input->validates($vars)) {
			$old_transaction = $this->get($transaction_id);

			// Add a transaction
			$fields = array("amount", "currency", "type", "transaction_type_id", "account_id", "gateway_id", "transaction_id", "parent_transaction_id", "reference_id", "status");
			$this->Record->where("id", "=", $transaction_id)->update("transactions", $vars, $fields);

			// Unapply this transaction from any applied invoice if the status is no longer approved
			if (isset($vars['status']) && $vars['status'] != "approved")
				$this->unApply($transaction_id);

			$new_transaction = $this->get($transaction_id);

			// Calculate the changes made to the contact and log those results
			$diff = array_diff_assoc((array)$old_transaction, (array)$new_transaction);

			$fields = array();
			foreach ($diff as $key => $value) {
				$fields[$key]['prev'] = $value;
				$fields[$key]['cur'] = $new_transaction->$key;
			}

			if (!empty($fields)) {
				if (!isset($this->Logs))
					Loader::loadModels($this, array("Logs"));
				$this->Logs->addTransaction(array('transaction_id'=>$transaction_id,'fields'=>$fields,'staff_id'=>$staff_id));
			}

			$this->Events->register("Transactions.edit", array("EventsTransactionsCallback", "edit"));
			$this->Events->trigger(new EventObject("Transactions.edit", compact("transaction_id")));

			return $transaction_id;
		}
	}

	/**
	 * Removes a transaction from the system. This method has not been implemented and may
	 * never be implemented for proper accounting purposes. Nevertheless it is defined
	 * for circulatiry.
	 *
	 * @param int $transaction_id The transaction ID to remove
	 */
	public function delete($transaction_id) {
		#
		# TODO: delete a transaction (must first unapply the transaction! see Transactions::unApply())
		#
	}

	/**
	 * Retrieves a transaction and any applied amounts
	 *
	 * @param int $transaction_id The ID of the transaction to fetch (that is, transactions.id NOT transactions.transaction_id)
	 * @return mixed A stdClass object representing the transaction, false if it does not exist
	 * @see Transactions::getByTransactionId()
	 */
	public function get($transaction_id) {

		$this->Record = $this->getTransaction();
		$transaction = $this->Record->where("transactions.id", "=", $transaction_id)->fetch();

		// Fetch amounts credited
		if ($transaction)
			$transaction->credited_amount = $this->getCreditedAmount($transaction->id);

		return $transaction;
	}

	/**
	 * Retrieves a transaction and any applied amounts
	 *
	 * @param int $transaction_id The ID of the transaction to fetch (that is, transactions.transaction_id NOT transactions.id)
	 * @param int $client_id The ID of the client to fetch a transaction for
	 * @param int $gateway_id The ID of the gateway used to process the transaction
	 * @return mixed A stdClass object representing the transaction, false if it does not exist
	 * @see Transactions::get()
	 */
	public function getByTransactionId($transaction_id, $client_id=null, $gateway_id=null) {

		$this->Record = $this->getTransaction();

		if ($client_id !== null)
			$this->Record->where("transactions.client_id", "=", $client_id);

		if ($gateway_id !== null)
			$this->Record->where("transactions.gateway_id", "=", $gateway_id);

		$transaction = $this->Record->where("transactions.transaction_id", "=", $transaction_id)->fetch();

		// Fetch amounts credited
		if ($transaction)
			$transaction->credited_amount = $this->getCreditedAmount($transaction->id);

		return $transaction;
	}

	/**
	 * Returns a partially built query Record object used to fetch a single transaction
	 *
	 * @return Record The Record object representing this partial query
	 */
	private function getTransaction() {
		$fields = array("transactions.id", "transactions.client_id", "transactions.amount",
			"transactions.currency", "transactions.type", "transactions.transaction_type_id", "transactions.account_id",
			"transactions.gateway_id", "gateways.name"=>"gateway_name", "gateways.type"=>"gateway_type",
			"transactions.reference_id", "transactions.transaction_id", "transactions.parent_transaction_id",
			"transactions.status", "transactions.date_added",
			"SUM(IFNULL(transaction_applied.amount,?))" => "applied_amount",
			"transaction_types.name" => "type_name"
		);

		return $this->Record->select($fields)->appendValues(array(0))->from("transactions")->
			leftJoin("transaction_types", "transactions.transaction_type_id", "=", "transaction_types.id", false)->
			leftJoin("transaction_applied", "transactions.id", "=", "transaction_applied.transaction_id", false)->
			leftJoin("gateways", "gateways.id", "=", "transactions.gateway_id", false)->
			group("transactions.id");
	}

	/**
	 * Retrieves a list of transactions and any applied amounts for the given client
	 *
	 * @param int $client_id The client ID (optional, default null to get transactions for all clients)
	 * @param string $status The status type of the transactions to fetch (optional, default 'approved') - 'approved', 'declined', 'void', 'error', 'pending', 'returned'
	 * @param int $page The page to return results for
	 * @param array $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return array An array of stdClass objects representing transactions, or false if none exist
	 */
	public function getList($client_id=null, $status="approved", $page=1, $order_by=array('date_added'=>"DESC")) {
		$this->Record = $this->getTransactions($client_id, $status);

		$transactions = $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();

		// Fetch amounts credited
		foreach ($transactions as &$trans) {
			$trans->credited_amount = $this->getCreditedAmount($trans->id);
		}

		return $transactions;
	}

	/**
	 * Returns the total number of transactions for a client, useful
	 * in constructing pagination for the getList() method.
	 *
	 * @param int $client_id The client ID (optional, default null to get transactions for all clients)
	 * @param string $status The status type of the transactions to fetch (optional, default 'approved') - 'approved', 'declined', 'void', 'error', 'pending', 'returned'
	 * @return int The total number of transactions
	 * @see Services::getList()
	 */
	public function getListCount($client_id=null, $status="approved") {
		return $this->getStatusCount($client_id, $status);
	}

	/**
	 * Search transactions
	 *
	 * @param string $query The value to search transactions for
	 * @param int $page The page number of results to fetch (optional, default 1)
	 * @return array An array of transactions that match the search criteria
	 */
	public function search($query, $page=1) {
		$this->Record = $this->searchTransactions($query);
		return $this->Record->order(array('date_added'=>"DESC"))->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}

	/**
	 * Return the total number of transactions returned from Transactions::search(), useful
	 * in constructing pagination
	 *
	 * @param string $query The value to search transactions for
	 * @see Transactions::search()
	 */
	public function getSearchCount($query) {
		$this->Record = $this->searchTransactions($query);
		return $this->Record->numResults();
	}

	/**
	 * Partially constructs the query for searching transactions
	 *
	 * @param string $query The value to search transactions for
	 * @return Record The partially constructed query Record object
	 * @see Transactions::search(), Transactions::getSearchCount()
	 */
	private function searchTransactions($query) {
		$this->Record = $this->getTransactions(null, "all");

		$this->Record->open()
			->like(
				"REPLACE(clients.id_format, '"
				. $this->replacement_keys['clients']['ID_VALUE_TAG']
				. "', clients.id_value)",
				"%" . $query . "%",
				true,
				false
			)
			->orLike("contacts.company", "%" . $query . "%")
			->orLike("CONCAT_WS(' ', contacts.first_name, contacts.last_name)", "%" . $query . "%", true, false)
			->orLike("contacts.address1", "%" . $query . "%")
			->orLike("transactions.transaction_id", "%" . $query . "%")
			->close();

		return $this->Record;
	}

	/**
	 * Partially constructs the query required by Transactions::getList() and Transactions:getListCount()
	 *
	 * @param int $client_id The client ID (optional, default null to get transactions for all clients)
	 * @param string $status The status type of the transactions to fetch (optional, default 'approved') - 'approved', 'declined', 'void', 'error', 'pending', 'refunded', 'returned' (or 'all' for all approved/declined/void/error/pending/refunded/returned)
	 * @return Record The partially constructed query Record object
	 */
	private function getTransactions($client_id=null, $status="approved") {
		$fields = array("transactions.id", "transactions.client_id", "transactions.amount",
			"transactions.currency", "transactions.type", "transactions.transaction_type_id", "transactions.account_id",
			"transactions.gateway_id", "transactions.reference_id", "transactions.transaction_id",
			"transactions.parent_transaction_id", "transactions.status", "transactions.date_added",
			"SUM(IFNULL(transaction_applied.amount,?))" => "applied_amount",
			"transaction_types.name" => "type_name", 'transaction_types.is_lang' => "type_is_lang",
			'gateways.name' => "gateway_name",
			'gateways.type' => "gateway_type",
			'REPLACE(clients.id_format, ?, clients.id_value)' => "client_id_code",
			'contacts.first_name'=>"client_first_name",
			'contacts.last_name'=>"client_last_name",
			'contacts.company'=>"client_company"
		);

		// Filter based on company ID
		$company_id = Configure::get("Blesta.company_id");

		$this->Record->select($fields)->appendValues(array(0,$this->replacement_keys['clients']['ID_VALUE_TAG']))->from("transactions")->
			innerJoin("clients", "clients.id", "=", "transactions.client_id", false)->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
			on("contacts.contact_type", "=", "primary")->
			innerJoin("contacts", "contacts.client_id", "=", "clients.id", false)->
			leftJoin("transaction_types", "transactions.transaction_type_id", "=", "transaction_types.id", false)->
			leftJoin("gateways", "transactions.gateway_id", "=", "gateways.id", false)->
			leftJoin("transaction_applied", "transactions.id", "=", "transaction_applied.transaction_id", false)->
			where("client_groups.company_id", "=", $company_id);

		if ($status != "all")
			$this->Record->where("transactions.status", "=", $status);

		$this->Record->group("transactions.id");

		// Get transactions for a specific client
		if ($client_id != null)
			$this->Record->where("transactions.client_id", "=", $client_id);

		return $this->Record;
	}

	/**
	 * Returns all invoices that have been applied to this transaction. Must supply
	 * either a transaction ID, invoice ID or both.
	 *
	 * @param int $transaction_id The ID of the transaction to fetch
	 * @param int $invoice_id The ID of the invoice to filter applied amounts on
	 * @return array An array of stdClass objects representing applied amounts to invoices
	 */
	public function getApplied($transaction_id=null, $invoice_id=null) {

		// Must supply either a transaction ID, invoice ID or both
		if ($transaction_id === null && $invoice_id === null)
			return array();

		$fields = array("transactions.id", "transactions.amount", "transactions.currency", "transactions.date_added",
			"transactions.type", "transactions.transaction_type_id",
			"transaction_applied.transaction_id", "transaction_applied.invoice_id",
			'transaction_applied.amount'=>"applied_amount", 'transaction_applied.date' => "applied_date",
			"transaction_types.name" => "type_name",
			'REPLACE(invoices.id_format, ?, invoices.id_value)' => "invoice_id_code",
			"transactions.client_id", "transactions.account_id", "transactions.gateway_id",
			"gateways.name"=>"gateway_name", "gateways.type"=>"gateway_type",
			"transactions.reference_id", "transactions.status",
			// Set the transaction ID as the transaction number since transaction_id is already taken
			'transactions.transaction_id' => "transaction_number", "transactions.parent_transaction_id"
		);

		$this->Record->select($fields)->appendValues(array($this->replacement_keys['invoices']['ID_VALUE_TAG']))->
			from("transactions")->
			innerJoin("transaction_applied", "transactions.id", "=", "transaction_applied.transaction_id", false)->
			innerJoin("invoices", "transaction_applied.invoice_id", "=", "invoices.id", false)->
			leftJoin("transaction_types", "transactions.transaction_type_id", "=", "transaction_types.id", false)->
			leftJoin("gateways", "gateways.id", "=", "transactions.gateway_id", false)->
			order(array('transaction_applied.date'=>"DESC"));

		if ($transaction_id !== null)
			$this->Record->where("transactions.id", "=", $transaction_id);

		if ($invoice_id !== null)
			$this->Record->where("transaction_applied.invoice_id", "=", $invoice_id);

		return $this->Record->fetchAll();
	}

	/**
	 * Returns the amount of payment for the given transaction ID that is currently
	 * available as a credit
	 *
	 * @param int $transaction_id The ID of the transaction to fetch a credit value for
	 * @return float The amount of the transaction that is currently available as a credit
	 */
	public function getCreditedAmount($transaction_id) {
		$fields = array("transactions.amount", 'SUM(IFNULL(transaction_applied.amount,?))'=>"applied_amount");
		$credits = $this->Record->select($fields)->appendValues(array(0))->from("transactions")->
			leftJoin("transaction_applied", "transaction_applied.transaction_id", "=", "transactions.id", false)->
			where("transactions.id", "=", $transaction_id)->
			where("transactions.status", "=", "approved")->group("transactions.id")->fetch();

		if ($credits) {
			return round($credits->amount - $credits->applied_amount, 4);
		}
		return 0;
	}

	/**
	 * Returns the amount of payment for the given client ID that is currently
	 * available as a credit for each currency
	 *
	 * @param int $client_id The ID of the client to fetch a credit value for
	 * @param string $currency The ISO 4217 3-character currency code (optional)
	 * @return array A list of credits by currency containing:
	 * 	- transaction_id The transaction ID that the credit belongs to
	 * 	- credit The total credit for this transaction
	 */
	public function getCredits($client_id, $currency=null) {
		$fields = array("transactions.id", "transactions.currency", "transactions.amount", 'SUM(IFNULL(transaction_applied.amount,?))'=>"applied_amount");
		$this->Record->select($fields)->appendValues(array(0))->from("transactions")->
			leftJoin("transaction_applied", "transaction_applied.transaction_id", "=", "transactions.id", false)->
			where("transactions.client_id", "=", $client_id)->
			where("transactions.status", "=", "approved");

		// Filter on currency
		if ($currency)
			$this->Record->where("transactions.currency", "=", $currency);

		$transactions = $this->Record->group("transactions.id")->
			having("applied_amount", "<", "transactions.amount", false)->
			fetchAll();

		$total_credits = array();
		foreach ($transactions as $transaction) {
			$credit_amount = round($transaction->amount - $transaction->applied_amount, 4);
			if ($credit_amount > 0) {
				if (!isset($total_credits[$transaction->currency]))
					$total_credits[$transaction->currency] = array();
				$total_credits[$transaction->currency][] = array('transaction_id'=>$transaction->id, 'credit'=>$credit_amount);
			}
		}
		return $total_credits;
	}

	/**
	 * Retrieves the total credit amount available to the client in the given currency
	 *
	 * @param int $client_id The ID of the client to fetch a credit value for
	 * @param string $currency The ISO 4217 3-character currency code
	 * @return float The total credit available to the client in the given currency
	 */
	public function getTotalCredit($client_id, $currency) {
		$credits = $this->getCredits($client_id, $currency);

		$total = 0;
		foreach ($credits as $currency_code => $amounts) {
			foreach ($amounts as $amount)
				$total += $amount['credit'];
		}

		return $total;
	}

	/**
	 * Retrieves the number of transactions given a transaction status type for the given client
	 *
	 * @param int $client_id The client ID (optional, default null to get transactions for all clients)
	 * @param string $status The transaction status type (optional, default 'approved')
	 * @return int The number of transactions of type $status for $client_id
	 */
	public function getStatusCount($client_id=null, $status="approved") {
		$this->Record->select(array("COUNT(*)"=>"status_count"))->from("transactions")->
			innerJoin("clients", "clients.id", "=", "transactions.client_id", false)->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
			where("client_groups.company_id", "=", Configure::get("Blesta.company_id"))->
			where("transactions.status", "=", $status);

		if ($client_id != null)
			$this->Record->where("client_id", "=", $client_id);

		$count = $this->Record->fetch();

		if ($count)
			return $count->status_count;
		return 0;
	}

	/**
	 * Applies a transaction to a list of invoices. Each invoice must be in the transaction's currency to be applied
	 *
	 * @param int transaction_id The transaction ID
	 * @param array $vars An array of transaction info including:
	 * 	- date The date in local time (Y-m-d H:i:s format) the transaction was applied (optional, default to current date/time)
	 * 	- amounts A numerically indexed array of amounts to apply including:
	 * 		- invoice_id The invoice ID
	 * 		- amount The amount to apply to this invoice (optional, default 0.00)
	 */
	public function apply($transaction_id, array $vars) {

		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));

		$vars['transaction_id'] = $transaction_id;

		if (!isset($vars['date']))
			$vars['date'] = date("c");

		// Attempt to apply a transaction to a list of invoices with the
		// given amounts for each
		if ($this->verifyApply($vars)) {
			// Add an applied transaction
			$fields = array("transaction_id", "invoice_id", "amount", "date");

			for ($i=0, $num_amounts=count($vars['amounts']); $i<$num_amounts; $i++) {
				// Set fields
				$vars['amounts'][$i]['transaction_id'] = $transaction_id;
				$vars['amounts'][$i]['date'] = $vars['date'];

				if (!array_key_exists('amount', $vars['amounts'][$i]))
					$vars['amounts'][$i]['amount'] = 0;

				// Add applied amount or update existing applied amount
				if ($vars['amounts'][$i]['amount'] > 0)
					$this->Record->duplicate("amount", "=", "amount + '" . ((float)$vars['amounts'][$i]['amount']) . "'", false, false)->insert("transaction_applied", $vars['amounts'][$i], $fields);

				// Mark each invoice as "paid" if paid in full
				$this->Invoices->setClosed($vars['amounts'][$i]['invoice_id']);
			}
		}

	}

	/**
	 * Applies available client credits in the given currency to all open invoices, starting from the oldest.
	 * If specific amounts are specified, credits will only be applied to the invoices given.
	 *
	 * @param int $client_id The ID of the client whose invoices to apply from credits
	 * @param string $currency The ISO 4217 3-character currency code. Must be set if $amounts are specified (optional, null will apply from all currencies; default null)
	 * @param array $amounts A numerically-indexed array of specific amounts to apply to given invoices:
	 * 	- invoice_id The invoice ID
	 * 	- amount The amount to apply to this invoice (optional, default 0.00)
	 * @return mixed Void, or an array indexed by a credit's transaction ID, containing the amounts that were actually applied, including:
	 *  - invoice_id The invoice ID
	 *  - amount The amount that was applied to this invoice
	 */
	public function applyFromCredits($client_id, $currency=null, array $amounts=array()) {
		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));

		// Fetch available credits
		$credits = $this->getCredits($client_id, $currency);
		if (empty($credits))
			return;

		// Apply credits to all open invoices
		if (empty($amounts)) {
			// Fetch all invoices we can apply credits to
			$invoices = $this->Invoices->getAll($client_id, "open", array('date_due'=>"ASC"), $currency);
			if (empty($invoices))
				return;
		}
		else {
			// Currency must be set since we are assuming all the $amounts belong to the same currency
			if (empty($currency)) {
				$this->Input->setErrors(array('currency' => array('missing' => Language::_("Transactions.!error.currency.missing", true))));
				return;
			}

			// Fetch the associated invoices to apply credits to
			$invoices = array();
			foreach ($amounts as $amount) {
				$invoice = $this->Invoices->get($this->ifSet($amount['invoice_id']));
				if (!$invoice || $invoice->currency != $currency) {
					$this->Input->setErrors(array('currency' => array('invalid' => Language::_("Transactions.!error.currency.mismatch", true))));
					return;
				}
				$invoices[] = $invoice;
			}
		}

		// Fetch the amounts to apply to each invoice
		$apply_amounts = $this->getCreditApplyAmounts($credits, $invoices, $amounts);

		// Begin a transaction
		$this->begin();

		// Apply all credits
		foreach ($apply_amounts as $transaction_id => $trans_amounts) {
			$this->apply($transaction_id, array('amounts' => $trans_amounts));

			if (($errors = $this->errors())) {
				// Roll back
				$this->rollBack();
				return;
			}
		}

		// Commit transaction
		$this->commit();

		return $apply_amounts;
	}

	/**
	 * Creates a list of credits to be applied to invoices for each transaction
	 *
	 * @param array $credits A list of client credits for all currencies, containing:
	 *  - transaction_id The transaction ID
	 *  - credit The credit available for this transaction
	 * @param array $invoices An array of stdClass objects representing client invoices
	 * @param array $amounts A list of specific amounts to be applied per invoice (optional)
	 * @return array A list of credits set to be applied, keyed by transaction ID, with each containing a list of amounts:
	 * 	- invoice_id The invoice ID to apply the credit to
	 * 	- amount The amount to apply to the invoice_id for this particular transaction
	 */
	private function getCreditApplyAmounts(array $credits=array(), array $invoices=array(), array $amounts=array()) {
		if (!isset($this->CurrencyFormat))
			Loader::loadHelpers($this, array("CurrencyFormat"));
		$apply_amounts = array();

		// Group each invoice by its currency
		$currencies = array();
		foreach ($invoices as $invoice) {
			if (!isset($currencies[$invoice->currency]))
				$currencies[$invoice->currency] = array();
			$currencies[$invoice->currency][] = $invoice;
		}
		unset($invoices, $invoice);


		// Set specific amounts to apply to each invoice, if any are given. Assumed to be matching currency
		$amounts_to_apply = array();
		foreach ($amounts as $amount) {
			$amounts_to_apply[$this->ifSet($amount['invoice_id'])] = $this->ifSet($amount['amount'], 0);
		}

		// Set all apply amounts for each invoice and credit
		foreach ($currencies as $currency_code => $invoices) {
			// No credits available in this currency to apply to the invoice
			if (empty($credits[$currency_code]))
				continue;

			foreach ($invoices as $invoice) {
				$invoice_credit = 0;

				// Set specific amounts to apply to this invoice, if given, from this credit
				$apply_amt = null;
				if (isset($amounts_to_apply[$invoice->id])) {
					$apply_amt = $amounts_to_apply[$invoice->id];
				}

				foreach ($credits[$currency_code] as &$credit) {
					// This credit has been used up
					if ($credit['credit'] <= 0) {
						unset($credit);
						continue;
					}

					// Set invoice credit to be applied (partially or in full) if specified
					$credit_amount = ($apply_amt === null || $apply_amt > $credit['credit'] ? $credit['credit'] : $apply_amt);
					if ($credit_amount >= ($invoice->due - $invoice_credit))
						$credit_amount = $invoice->due - $invoice_credit;

					// Set apply amount
					if (!isset($apply_amounts[$credit['transaction_id']]))
						$apply_amounts[$credit['transaction_id']] = array();
					$apply_amounts[$credit['transaction_id']][] = array('invoice_id'=>$invoice->id, 'amount'=>$this->CurrencyFormat->cast($credit_amount, $currency_code));

					// Decrease credit available
					$credit['credit'] -= $credit_amount;
					$invoice_credit += $credit_amount;
					if ($apply_amt !== null)
						$apply_amt -= $credit_amount;

					// Credit covers entire invoice, or the total we're applying to it, so move on
					if ($invoice_credit >= $invoice->due || ($apply_amt !== null && $apply_amt <= 0)) {
						// Don't re-visit this invoice
						unset($invoice);
						break;
					}
				}
			}
		}

		return $apply_amounts;
	}

	/**
	 * Verifies that transaction can be applied to a list of invoices
	 *
	 * @param array $vars An array of transaction info including:
	 * 	- transaction_id The transaction ID (only evaluated if $validate_trans_id is true)
	 * 	- date The date in UTC time (Y-m-d H:i:s format) the transaction was applied (optional, default to current date/time)
	 * 	- amounts A numerically indexed array of amounts to apply including:
	 * 		- invoice_id The invoice ID
	 * 		- amount The amount to apply to this invoice (optional, default 0.00)
	 * @param boolean $validate_trans_id True to validate the transaction ID in $vars, false otherwise
	 * @param float $total The total amount of the transaction used to ensure that the sum of the apply amounts does not exceed the total transaction amount. Only used if $validate_trans_id is false (optional)
	 * @return boolean True if the transaction can be applied, false otherwise (sets Input errors on failure)
	 */
	public function verifyApply(array &$vars, $validate_trans_id=true, $total=null) {
		// Determine the transaction total
		if ($validate_trans_id) {
			$total = null;

			// The total remaining transaction amount is the maximum that amounts can apply
			if (isset($vars['transaction_id']) && ($transaction = $this->get((int)$vars['transaction_id']))) {
				$total = max(0, ($transaction->amount - $transaction->applied_amount));
			}
		}

		$rules = array(
			'transaction_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "transactions"),
					'message' => $this->_("Transactions.!error.transaction_id.exists")
				),
				'currency_matches' => array(
					'rule' => array(array($this, "validateCurrencyAmounts"), (array)$this->ifSet($vars['amounts'], array())),
					'message' => $this->_("Transactions.!error.transaction_id.currency_matches")
				)
			),
			'amounts[][invoice_id]' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "invoices"),
					'message' => $this->_("Transactions.!error.invoice_id.exists")
				)
			),
			'amounts[][amount]' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Transactions.!error.amount.format")
				)
			),
			'amounts' => array(
				'overage' => array(
					'rule'=>array(array($this, "validateApplyAmounts"), $total),
					'message' => $this->_("Transactions.!error.amounts.overage")
				),
				'positive' => array(
					'rule'=>array(array($this, "validatePositiveAmounts")),
					'message' => $this->_("Transactions.!error.amounts.positive")
				)
			),
			'date' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'post_format'=>array(array($this, "dateToUtc")),
					'message' => $this->_("Transactions.!error.date.format")
				)
			)
		);

		if (!$validate_trans_id)
			unset($rules['transaction_id']);

		$this->Input->setRules($rules);

		return $this->Input->validates($vars);
	}

	/**
	 * Unapplies a transactions from one or more invoices.
	 *
	 * @param int $transaction_id The ID of the transaction to unapply
	 * @param array $invoice A numerically indexed array of invoice IDs to unapply this transaction from, or null to unapply this transaction from any and all invoices
	 */
	public function unApply($transaction_id, array $invoices=null) {

		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));

		$applied_invoices = $this->getApplied($transaction_id);

		$this->Record->from("transactions")->from("transaction_applied")->
			where("transactions.id", "=", $transaction_id)->
			where("transaction_applied.transaction_id", "=", "transactions.id", false);
		// If a list of invoice was given, unapply this transaction from those invoices only
		if (!empty($invoices))
			$this->Record->where("transaction_applied.invoice_id", "in", $invoices);
		$this->Record->delete(array("transaction_applied.*"));

		// remove "paid" status for each invoice if no longer paid in full
		if (!empty($invoices)) {
			for ($i=0; $i<count($invoices); $i++)
				$this->Invoices->setClosed($invoices[$i]);
		}
		else {
			if ($applied_invoices) {
				for ($i=0; $i<count($applied_invoices); $i++)
					$this->Invoices->setClosed($applied_invoices[$i]->invoice_id);
			}
		}
	}

	/**
	 * Retrieves a list of all transaction types
	 *
	 * @return mixed An array of stdClass objects representing transaction types, or false if none exist
	 */
	public function getTypes() {
		$transaction_types = $this->Record->select()->from("transaction_types")->fetchAll();

		// Set a real_name to the language definition, if applicable
		foreach ($transaction_types as &$payment_type) {
			if ($payment_type->is_lang == "1")
				$payment_type->real_name = $this->_("_PaymentTypes." . $payment_type->name, true);
			else
				$payment_type->real_name = $payment_type->name;
		}

		return $transaction_types;
	}

	/**
	 * Retrieves a single payment type
	 *
	 * @param int $type_id The payment type ID
	 * @return mixed An array of stdClass objects representing a payment type, or false if one does not exist
	 */
	public function getType($type_id) {
		$transaction_type = $this->Record->select()->from("transaction_types")->where("id", "=", $type_id)->fetch();

		// Set a real_name to the language definition, if applicable
		if ($transaction_type) {
			if ($transaction_type->is_lang == "1")
				$transaction_type->real_name = $this->_("_PaymentTypes." . $transaction_type->name, true);
			else
				$transaction_type->real_name = $transaction_type->name;
		}

		return $transaction_type;
	}

	/**
	 * Retrieves a set of key/value pairs representing transaction debit types and language
	 *
	 * @return array An array of key/value pairs representing transaction debit types and their names
	 */
	public function getDebitTypes() {
		return array(
			'debit' => $this->_("Transactions.debit_types.debit"),
			'credit' => $this->_("Transactions.debit_types.credit")
		);
	}

	/**
	 * Retrieves all transaction types and their name in key=>value pairs
	 *
	 * @return array An array of key=>value pairs representing transaction types and their names
	 */
	public function transactionTypeNames() {
		// Standard types
		$names = array(
			'ach'=>$this->_("Transactions.types.ach"),
			'cc'=>$this->_("Transactions.types.cc"),
			'other'=>$this->_("Transactions.types.other")
		);

		// Custom types
		$types = $this->getTypes();
		foreach ($types as $type)
			$names[$type->name] = $type->is_lang == "1" ? $this->_("_PaymentTypes." . $type->name) : $type->name;

		return $names;
	}

	/**
	 * Retrieves all transaction status values and their name in key/value pairs
	 *
	 * @return array An array of key/value pairs representing transaction status values
	 */
	public function transactionStatusNames() {
		return array(
			'approved'=>$this->_("Transactions.status.approved"),
			'declined'=>$this->_("Transactions.status.declined"),
			'void'=>$this->_("Transactions.status.void"),
			'error'=>$this->_("Transactions.status.error"),
			'pending'=>$this->_("Transactions.status.pending"),
			'refunded'=>$this->_("Transactions.status.refunded"),
			'returned'=>$this->_("Transactions.status.returned")
		);
	}

	/**
	 * Adds a transaction type
	 *
	 * @param array $vars An array of transaction types including:
	 * 	- name The transaction type name
	 * 	- type The transaction debit type ('debit' or 'credit')
	 * 	- is_lang Whether or not the 'name' parameter is a language definition 1 - yes, 0 - no (optional, default 0)
	 * @return int The transaction type ID created, or void on error
	 */
	public function addType(array $vars) {

		$this->Input->setRules($this->getTypeRules());

		if ($this->Input->validates($vars)) {
			//Add a transaction type
			$fields = array("name", "type", "is_lang");
			$this->Record->insert("transaction_types", $vars, $fields);

			return $this->Record->lastInsertId();
		}
	}

	/**
	 * Updates a transaction type
	 *
	 * @param int $type_id The type ID to update
	 * @param array $vars An array of transaction types including:
	 * 	- name The transaction type name
	 * 	- type The transaction debit type ('debit' or 'credit')
	 * 	- is_lang Whether or not the 'name' parameter is a language definition 1 - yes, 0 - no (optional, default 0)
	 */
	public function editType($type_id, array $vars) {

		$rules = $this->getTypeRules();
		$rules['type_id'] = array(
			'exists' => array(
				'rule' => array(array($this, "validateExists"), "id", "transaction_types"),
				'message' => $this->_("Transactions.!error.type_id.exists")
			)
		);

		$this->Input->setRules($rules);

		$vars['type_id'] = $type_id;

		if ($this->Input->validates($vars)) {
			// Update a transaction type
			$fields = array("name", "type", "is_lang");
			$this->Record->where("id", "=", $type_id)->update("transaction_types", $vars, $fields);
		}
	}

	/**
	 * Delete a transaction type and update all affected transactions, setting their type to null
	 *
	 * @param int $type_id The ID for this transaction type
	 */
	public function deleteType($type_id) {

		// Update all transactions with this now defunct transaction type ID to null
		$this->Record->where("transaction_type_id", "=", $type_id)->
			update("transactions", array("transaction_type_id"=>null));

		// Finally delete the transaction type
		$this->Record->from("transaction_types")->where("id", "=", $type_id)->delete();
	}

	/**
	 * Validates a transaction's 'type' field
	 *
	 * @param string $type The type to check
	 * @return boolean True if the type is validated, false otherwise
	 */
	public function validateType($type) {
		switch ($type) {
			case "cc":
			case "ach":
			case "other":
				return true;
		}
		return false;
	}

	/**
	 * Validates a transaction's 'status' field
	 *
	 * @param string $status The status to check
	 * @return boolean True if the status is validated, false otherwise
	 */
	public function validateStatus($status) {
		switch ($status) {
			case "approved":
			case "declined":
			case "void":
			case "error":
			case "pending":
			case "refunded":
			case "returned":
				return true;
		}
		return false;
	}

	/**
	 * Validates whether the given amounts can be applied to the given invoices, or if the amounts
	 * would exceed the amount due on the invoices. Also ensures the invoices can have amounts applied to them.
	 *
	 * @param array $amounts An array of apply amounts including:
	 * 	- invoice_id The invoice ID
	 * 	- amount The amount to apply to this invoice (optional, default 0.00)
	 * @param float $total The total amount of the transaction used to ensure that the sum of the apply amounts does not exceed the total transaction amount
	 * @return boolean True if the amounts given can can be applied to the invoices given, false if it exceeds the amount due or the invoice can not receive payments
	 */
	public function validateApplyAmounts(array $amounts, $total=null) {
		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));

		$apply_total = 0;
		foreach ($amounts as $apply) {
			if (!isset($apply['amount']))
				continue;

			$apply_total += $apply['amount'];
			$invoice = $this->Invoices->get($apply['invoice_id']);

			// If rounding is enabled, round the total due to ensure it best matches
			// currency formatting input values (e.g. total due of 2.035 becomes 2.04,
			// thus accepting payment for 2.04)
			$pay_total = round($invoice->paid+$apply['amount'], $this->float_precision);
			if (Configure::get("Blesta.transactions_validate_apply_round")) {
				$invoice->total = round($invoice->total, 2);
				$pay_total = round($pay_total, 2);
			}

			$active_types = array("active", "proforma");
			if (!$invoice || !in_array($invoice->status, $active_types) || $pay_total > $invoice->total)
				return false;
		}

		// Ensure that the available amount of the transaction does not exceed the apply amount (if total given)
		if ($total !== null && round($total, $this->float_precision) < round($apply_total, $this->float_precision)) {
			return false;
		}

		return true;
	}

	/**
	 * Validates whether the invoice amounts being applied are in the transaction's currency
	 *
	 * @param int $transaction_id The ID of the transaction
	 * @param array $amounts An array of apply amounts including:
	 * 	- invoice_id The invoice ID
	 * 	- amount The amount to apply to this invoice (optional)
	 * @return boolean True if each invoice is in the transaction's currency, or false otherwise
	 */
	public function validateCurrencyAmounts($transaction_id, array $amounts) {
		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));

		if (($transaction = $this->get($transaction_id))) {
			foreach ($amounts as $apply) {
				if (!isset($apply['invoice_id'])) {
					continue;
				}

				// Check the currencies match
				$invoice = $this->Invoices->get($apply['invoice_id']);
				if ($invoice && strtolower($invoice->currency) != strtolower($transaction->currency)) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Validates whether each of the given amounts is greater than or equal to zero
	 *
	 * @param array $amounts An array of apply amounts including:
	 * 	- invoice_id The invoice ID
	 * 	- amount The amount to apply to this invoice (optional, default 0.00)
	 * @return boolean True if all amounts are greater than or equal to zero, false otherwise
	 */
	public function validatePositiveAmounts(array $amounts) {
		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));

		foreach ($amounts as $apply) {
			if (!isset($apply['amount']))
				continue;

			if ($apply['amount'] < 0)
				return false;
		}

		return true;
	}

	/**
	 * Returns the rule set for adding/editing transactions
	 *
	 * @return array Transaction rules
	 */
	private function getTransactionRules() {
		$rules = array(
			'client_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "clients"),
					'message' => $this->_("Transactions.!error.client_id.exists")
				)
			),
			'amount' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Transactions.!error.amount.format")
				)
			),
			'currency' => array(
				'length' => array(
					'if_set' => true,
					'rule' => array("matches", "/^(.*){3}$/"),
					'message' => $this->_("Transactions.!error.currency.length")
				)
			),
			'type' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateType")),
					'message' => $this->_("Transactions.!error.type.format")
				)
			),
			'transaction_type_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "transaction_types"),
					'message' => $this->_("Transactions.!error.transaction_type_id.exists")
				)
			),
			'gateway_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "gateways", false),
					'message' => $this->_("Transactions.!error.gateway_id.exists")
				)
			),
			'status' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStatus")),
					'message' => $this->_("Transactions.!error.status.format")
				)
			),
			'date_added' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("isDate"),
					'message' => $this->_("Transactions.!error.date_added.format"),
					'pre_format'=>array(array($this, "dateToUtc"), "Y-m-d H:i:s", true)
				)
			)
		);
		return $rules;
	}

	/**
	 * Returns the rule set for adding/editing transaction types
	 *
	 * @return array Transaction type rules
	 */
	private function getTypeRules() {
		$rules = array(
			'name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Transactions.!error.name.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 32),
					'message' => $this->_("Transactions.!error.name.length")
				)
			),
			'type' => array(
				'valid' => array(
					'rule' => array("in_array", array_keys($this->getDebitTypes())),
					'message' => $this->_("Transactions.!error.type.valid")
				)
			),
			'is_lang' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Transactions.!error.is_lang.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 1),
					'message' => $this->_("Transactions.!error.is_lang.length")
				)
			)
		);
		return $rules;
	}
}
