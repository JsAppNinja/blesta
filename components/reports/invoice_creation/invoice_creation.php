<?php
/**
 * Invoice Creation report
 *
 * @package blesta
 * @subpackage blesta.components.reports.invoice_creation
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class InvoiceCreation implements ReportType {

	/**
	 * Load language
	 */
	public function __construct() {
		Loader::loadComponents($this, array("Record", "SettingsCollection"));
		Loader::loadModels($this, array("Invoices"));

		// Load the language required by this report
		Language::loadLang("invoice_creation", null, dirname(__FILE__) . DS . "language" . DS);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return Language::_("InvoiceCreation.name", true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getOptions($company_id, array $vars = array()) {
		Loader::loadHelpers($this, array("Javascript"));

		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("options", "default");
		$this->view->setDefaultView("components" . DS . "reports" . DS . "invoice_creation" . DS);

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("vars", (object)$vars);

		// Set statuses
		$any = array('' => Language::_("InvoiceCreation.option.any", true));
		$this->view->set("statuses", array_merge($any, $this->Invoices->getStatuses()));

		return $this->view->fetch();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getColumns() {
		return array(
			'id_code' => array('name' => Language::_("InvoiceCreation.heading.id_code", true)),
			'client_id_code' => array('name' => Language::_("InvoiceCreation.heading.client_id_code", true)),
			'subtotal' => array('name' => Language::_("InvoiceCreation.heading.subtotal", true)),
			'total' => array('name' => Language::_("InvoiceCreation.heading.total", true)),
			'paid' => array('name' => Language::_("InvoiceCreation.heading.paid", true)),
			'currency' => array('name' => Language::_("InvoiceCreation.heading.currency", true)),
			'status' => array(
				'name' => Language::_("InvoiceCreation.heading.status", true),
				'format' => "replace", 'options' => $this->Invoices->getStatuses()
			),
			'date_billed' => array(
				'name' => Language::_("InvoiceCreation.heading.date_billed", true),
				'format' => "date"
			),
			'date_due' => array(
				'name' => Language::_("InvoiceCreation.heading.date_due", true),
				'format' => "date"
			),
			'date_closed' => array(
				'name' => Language::_("InvoiceCreation.heading.date_closed", true),
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
		$timezone = array_key_exists("value", $timezone)
			? $timezone['value']
			: "UTC";
		$this->Date->setTimezone($timezone, "UTC");

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

		$fields = array("invoices.*",
			'REPLACE(invoices.id_format, ?, invoices.id_value)' => "id_code",
			'REPLACE(clients.id_format, ?, clients.id_value)' => "client_id_code",
		);
		$values = array(
			$replacement_keys['invoices']['ID_VALUE_TAG'],
			$replacement_keys['clients']['ID_VALUE_TAG']
		);

		$this->Record->select($fields, false)->appendValues($values)
			->from("invoices")
			->innerJoin("clients", "clients.id", "=", "invoices.client_id", false)
			->on("client_groups.company_id", "=", $company_id)
			->innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false);

		// Filter
		if ($start_date) {
			$this->Record->where("invoices.date_billed", ">=", $start_date);
		}
		if ($end_date) {
			$this->Record->where("invoices.date_billed", "<=", $end_date);
		}
		if ($status) {
			$this->Record->where("invoices.status", "=", $status);
		}

		$this->Record->group(array("invoices.id"))
			->order(array('invoices.date_billed' => "ASC", 'invoices.id' => "ASC"));

		return new IteratorIterator($this->Record->getStatement());
	}
}
