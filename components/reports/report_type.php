<?php
/**
 * Report Type interface that all reports must implement
 *
 * @package blesta
 * @subpackage blesta.components.reports
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
interface ReportType {
	/**
	 * Retrieves the name of this report
	 *
	 * @return string The name of the report
	 */
	public function getName();

	/**
	 * Retrieves the view containing any additional fields to filter on for the report
	 *
	 * @param int $company_id The ID of the company to generate the report from
	 * @param array A list of input vars (optional)
	 * @return string The view
	 */
	public function getOptions($company_id, array $vars = array());

	/**
	 * Retrieves a list of names for each report column to contain
	 *
	 * @return array A list of key/value pairs indicating the field, its name, and any formatting to apply
	 */
	public function getColumns();

	/**
	 * Execute the report
	 *
	 * @param int $company_id The ID of the company to generate the report from
	 * @param array $vars A list of fields as given as options to the view
	 * @return Iterator An iterable object containing the report data
	 */
	public function fetchAll($company_id, array $vars);
}
