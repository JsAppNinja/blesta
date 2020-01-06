<?php
/**
 * Transactions Applied report
 *
 * @package blesta
 * @subpackage blesta.components.reports.transactions_applied
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class TransactionsApplied implements ReportType {

	/**
	 * Load language
	 */
	public function __construct() {
		Loader::loadComponents($this, array("Record", "SettingsCollection"));
		Loader::loadModels($this, array("Transactions"));
		Loader::loadHelpers($this, array("DataStructure"));
		$this->ArrayHelper = $this->DataStructure->create("Array");

		// Load the language required by this report
		Language::loadLang("transactions_applied", null, dirname(__FILE__) . DS . "language" . DS);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return Language::_("TransactionsApplied.name", true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getOptions($company_id, array $vars = array()) {
		Loader::loadHelpers($this, array("Javascript"));
		Loader::loadModels($this, array("GatewayManager"));

		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("options", "default");
		$this->view->setDefaultView("components" . DS . "reports" . DS . "transactions_applied" . DS);

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$any = array('' => Language::_("TransactionsApplied.option.any", true));

		$this->view->set("vars", (object)$vars);
		$this->view->set("statuses", array_merge($any, $this->Transactions->transactionStatusNames()));

		// Set payment types
		$types = $this->Transactions->transactionTypeNames();
		unset($types['other']);
		$this->view->set("payment_types", ($any + $types));

		// Set gateways
		$gateways = $this->GatewayManager->getAll($company_id);
		$merchant = $this->ArrayHelper->numericToKey(
			isset($gateways['merchant'])
			? $gateways['merchant']
			: array(),
			"id",
			"name"
		);
		$nonmerchant = $this->ArrayHelper->numericToKey(
			isset($gateways['nonmerchant'])
			? $gateways['nonmerchant']
			: array(),
			"id",
			"name"
		);
		$this->view->set("gateways", ($any + $merchant + $nonmerchant));

		return $this->view->fetch();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getColumns() {
		$payment_types = $this->Transactions->transactionTypeNames();
		unset($payment_types['other']);
		$income_types = $this->Transactions->getDebitTypes();

		return array(
			'id' => array('name' => Language::_("TransactionsApplied.heading.id", true)),
			'transaction_id' => array('name' => Language::_("TransactionsApplied.heading.transaction_id", true)),
			'reference_id' => array('name' => Language::_("TransactionsApplied.heading.reference_id", true)),
			'client_id_code' => array('name' => Language::_("TransactionsApplied.heading.client_id_code", true)),
			'invoice_id_code' => array('name' => Language::_("TransactionsApplied.heading.invoice_id_code", true)),
			'gateway_name' => array('name' => Language::_("TransactionsApplied.heading.gateway_name", true)),
			'type_name' => array(
				'name' => Language::_("TransactionsApplied.heading.type_name", true),
				'format' => "replace",
				'options' => $payment_types
			),
			'income_type' => array(
				'name' => Language::_("TransactionsApplied.heading.income_type", true),
				'format' => "replace",
				'options' => $income_types
			),
			'applied_amount' => array('name' => Language::_("TransactionsApplied.heading.applied_amount", true)),
			'amount' => array('name' => Language::_("TransactionsApplied.heading.amount", true)),
			'currency' => array('name' => Language::_("TransactionsApplied.heading.currency", true)),
			'status' => array(
				'name' => Language::_("TransactionsApplied.heading.status", true),
				'format' => "replace",
				'options' => $this->Transactions->transactionStatusNames()
			),
			'applied_date' => array(
				'name' => Language::_("TransactionsApplied.heading.applied_date", true),
				'format' => "date"
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function fetchAll($company_id, array $vars) {
		Loader::loadHelpers($this, array("Date"));

		// Set the keys for ID codes
		$replacement_keys = Configure::get("Blesta.replacement_keys");

		// Format dates
		$timezone = $this->SettingsCollection->fetchSetting(null, $company_id, "timezone");
		$timezone = (array_key_exists("value", $timezone) ? $timezone['value'] : "UTC");
		$this->Date->setTimezone($timezone, "UTC");

		// Set filter options
		$format = "Y-m-d H:i:s";
		$start_date = !empty($vars['start_date'])
			? $this->Date->format($format, $vars['start_date'] . " 00:00:00")
			: null;
		$end_date = !empty($vars['end_date'])
			? $this->Date->format($format, $vars['end_date'] . " 23:59:59")
			: null;
		$status = !empty($vars['status'])
			? $vars['status']
			: null;
		$payment_type = !empty($vars['payment_type'])
			? $vars['payment_type']
			: null;
		$gateway = !empty($vars['gateway'])
			? $vars['gateway']
			: null;

		$fields = array("transactions.*",
			"transaction_applied.invoice_id",
			'transaction_applied.date' => "applied_date",
			'IFNULL(transaction_applied.amount,?)' => "applied_amount",
			'IF(transactions.type=?,transaction_types.name,transactions.type)' => "type_name",
			'IFNULL(transaction_types.type,?)' => "income_type",
			'transaction_types.is_lang' => "type_is_lang",
			'gateways.name' => "gateway_name",
			'REPLACE(clients.id_format, ?, clients.id_value)' => "client_id_code",
			'REPLACE(invoices.id_format, ?, invoices.id_value)' => "invoice_id_code",
		);
		$values = array(
			0,
			"other",
			"debit", // income type
			$replacement_keys['clients']['ID_VALUE_TAG'],
			$replacement_keys['invoices']['ID_VALUE_TAG']
		);

		$this->Record->select($fields, false)->appendValues($values)
			->from("transactions")
			->innerJoin("clients", "clients.id", "=", "transactions.client_id", false)
			->on("client_groups.company_id", "=", $company_id)
			->innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)
			->on("contacts.contact_type", "=", "primary")
			->innerJoin("contacts", "contacts.client_id", "=", "clients.id", false)
			->innerJoin("transaction_applied", "transactions.id", "=", "transaction_applied.transaction_id", false)
			->innerJoin("invoices", "invoices.id", "=", "transaction_applied.invoice_id", false)
			->leftJoin("transaction_types", "transactions.transaction_type_id", "=", "transaction_types.id", false)
			->leftJoin("gateways", "transactions.gateway_id", "=", "gateways.id", false);

		// Filter
		if ($start_date) {
			$this->Record->where("transactions.date_added", ">=", $start_date);
		}
		if ($end_date) {
			$this->Record->where("transactions.date_added", "<=", $end_date);
		}
		if ($status) {
			$this->Record->where("transactions.status", "=", $status);
		}
		if ($gateway) {
			$this->Record->where("gateways.id", "=", $gateway);
		}
		if ($payment_type) {
			// Payment type may be the transaction type or a transaction type name
			if ($this->Transactions->validateType($payment_type)) {
				$this->Record->where("transactions.type", "=", $payment_type);
			} else {
				$this->Record->where("transaction_types.name", "=", $payment_type);
			}
		}

		$this->Record->group("transaction_applied.invoice_id")
			->order(array('transaction_applied.date' => "ASC", 'transactions.id' => "ASC"));

		return new IteratorIterator($this->Record->getStatement());
	}
}
