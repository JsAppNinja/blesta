<?php
/**
 * Tax Liability report
 *
 * @package blesta
 * @subpackage blesta.components.reports.tax_liability
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class TaxLiability implements ReportType {

	/**
	 * Load language
	 */
	public function __construct() {
		Loader::loadComponents($this, array("Record", "SettingsCollection"));

		// Load the language required by this report
		Language::loadLang("tax_liability", null, dirname(__FILE__) . DS . "language" . DS);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return Language::_("TaxLiability.name", true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getOptions($company_id, array $vars = array()) {
		Loader::loadHelpers($this, array("Javascript"));

		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("options", "default");
		$this->view->setDefaultView("components" . DS . "reports" . DS . "tax_liability" . DS);

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("vars", (object)$vars);

		return $this->view->fetch();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getColumns() {
		return array(
			'id_code' => array('name' => Language::_("TaxLiability.heading.id_code", true)),
			'client_id_code' => array('name' => Language::_("TaxLiability.heading.client_id_code", true)),
			'subtotal' => array('name' => Language::_("TaxLiability.heading.subtotal", true)),
			'taxable_amount' => array('name' => Language::_("TaxLiability.heading.taxable_amount", true)),
			'level1_tax_rate' => array('name' => Language::_("TaxLiability.heading.level1_tax_rate", true)),
			'level1_tax_amount' => array('name' => Language::_("TaxLiability.heading.level1_tax_amount", true)),
			'level1_tax_country' => array('name' => Language::_("TaxLiability.heading.level1_tax_country", true)),
			'level1_tax_state' => array('name' => Language::_("TaxLiability.heading.level1_tax_state", true)),
			'level2_tax_rate' => array('name' => Language::_("TaxLiability.heading.level2_tax_rate", true)),
			'level2_tax_amount' => array('name' => Language::_("TaxLiability.heading.level2_tax_amount", true)),
			'level2_tax_country' => array('name' => Language::_("TaxLiability.heading.level2_tax_country", true)),
			'level2_tax_state' => array('name' => Language::_("TaxLiability.heading.level2_tax_state", true)),
			'cascade' => array(
				'name' => Language::_("TaxLiability.heading.cascade", true),
				'format' => "replace",
				'options' => array(
					0 => Language::_("TaxLiability.options.field_cascade_false", true),
					1 => Language::_("TaxLiability.options.field_cascade_true", true)
				)
			),
			'currency' => array('name' => Language::_("TaxLiability.heading.currency", true)),
			'date_closed' => array(
				'name' => Language::_("TaxLiability.heading.date_closed", true),
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

		$format = "Y-m-d H:i:s";
		$start_date = null;
		$end_date = null;

		// Format date to UTC
		if (!empty($vars['start_date'])) {
			$start_date = $this->Date->format($format, $vars['start_date'] . " 00:00:00");
		}
		if (!empty($vars['end_date'])) {
			$end_date = $this->Date->format($format, $vars['end_date'] . " 23:59:59");
		}

		$fields = array(
			"invoices.*",
			'REPLACE(invoices.id_format, ?, invoices.id_value)' => "id_code",
			'REPLACE(clients.id_format, ?, clients.id_value)' => "client_id_code",
			'SUM(ROUND(GREATEST(invoice_lines.amount*invoice_lines.qty,?)*IFNULL(taxes1.amount,?)/?,?))' => "level1_tax_amount",
			'MAX(invoice_line_taxes.cascade)' => "cascade",
			'IFNULL(MAX(taxes1.amount),?)' => "level1_tax_rate",
			'IFNULL(MAX(taxes1.country),?)' => "level1_tax_country",
			'IFNULL(MAX(taxes1.state),?)' => "level1_tax_state",
			'IFNULL(MAX(taxes2.amount),?)' => "level2_tax_rate",
			'IFNULL(MAX(taxes2.country),?)' => "level2_tax_country",
			'IFNULL(MAX(taxes2.state),?)' => "level2_tax_state",
			'ROUND(invoice_lines.amount*GREATEST(invoice_lines.qty,?),?)' => "taxable_amount"
		);
		$values = array(
			$replacement_keys['invoices']['ID_VALUE_TAG'],
			$replacement_keys['clients']['ID_VALUE_TAG'],
			0,
			0,
			100,
			4,
			'0.0000',
			null,
			null,
			'0.0000',
			null,
			null,
			0,
			4
		);

		$this->Record->select($fields, false)
			->appendValues($values)
			->from("invoices")
			->innerJoin("clients", "clients.id", "=", "invoices.client_id", false)
			->on("client_groups.company_id", "=", $company_id)
			->innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)
			->innerJoin("invoice_lines", "invoice_lines.invoice_id", "=", "invoices.id", false)
			->innerJoin("invoice_line_taxes", "invoice_line_taxes.line_id", "=", "invoice_lines.id", false)
			->on("taxes1.level", "=", 1)
			->leftJoin(array('taxes'=>"taxes1"), "taxes1.id", "=", "invoice_line_taxes.tax_id", false)
			->on("taxes2.level", "=", 2)
			->leftJoin(array('taxes'=>"taxes2"), "taxes2.id", "=", "invoice_line_taxes.tax_id", false)
			->where("invoices.status", "in", array("active", "proforma"))
			->where("invoices.date_closed", "!=", null);

		if ($start_date) {
			$this->Record->where("invoices.date_closed", ">=", $start_date);
		}
		if ($end_date) {
			$this->Record->where("invoices.date_closed", "<=", $end_date);
		}

		$this->Record->group(array("invoices.id"));

		$invoice_sql = $this->Record->get();
		$invoice_values = $this->Record->values;
		$this->Record->reset();

		// Fetch the invoices
		$fields = array(
			"temp.*",
			'ROUND(GREATEST(IF(temp.cascade > ?, (temp.taxable_amount+level1_tax_amount)*temp.level2_tax_rate/?, temp.taxable_amount*temp.level2_tax_rate/?),?),?)' => "level2_tax_amount"
		);
		$this->Record->select($fields, false)
			->appendValues(array(0, 100, 100, 0, 4))
			->from(array($invoice_sql => "temp"))
			->appendValues($invoice_values)
			->order(array('temp.date_closed' => "ASC", 'temp.id' => "ASC"));

		return new IteratorIterator($this->Record->getStatement());
	}
}
