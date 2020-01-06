<?php
/**
 * Report Manager
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ReportManager extends AppModel {

	/**
	 * @var The path to the temp directory
	 */
	private $temp_dir = null;
	/**
	 * @var The company ID for this report
	 */
	private $company_id = null;

	/**
	 * Load language
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("report_manager"));
		Loader::loadComponents($this, array("Download", "SettingsCollection"));

		// Set the date formats
		if (!isset($this->Companies))
			Loader::loadModels($this, array("Companies"));

		$this->company_id = Configure::get("Blesta.company_id");
		$this->Date->setTimezone("UTC", $this->Companies->getSetting($this->company_id, "timezone")->value);
		$this->Date->setFormats(array(
			'date'=>$this->Companies->getSetting($this->company_id, "date_format")->value,
			'date_time'=>$this->Companies->getSetting($this->company_id, "datetime_format")->value
		));

		// Set the temp directory
		$temp_dir = $this->SettingsCollection->fetchSystemSetting(null, "temp_dir");
		if (isset($temp_dir['value']))
			$this->temp_dir = $temp_dir['value'];
	}

	/**
	 * Instantiates the given report and returns its instance
	 *
	 * @param string $class The name of the class in file_case to load
	 * @return An instance of the report specified
	 */
	private function loadReport($class) {
		// Load the report factory if not already loaded
		if (!isset($this->Reports))
			Loader::loadComponents($this, array("Reports"));

		// Instantiate the module and return the instance
		return $this->Reports->create($class);
	}

	/**
	 * Retrieves a list of report formats
	 *
	 * @return array A list of report formats and their language
	 */
	public function getFormats() {
		return array(
			'csv' => $this->_("ReportManager.getformats.csv")
		);
	}

	/**
	 * Retrieves the name for the given report type
	 *
	 * @param string $type The type of report to fetch the name for
	 * @return string The name of the report
	 */
	public function getName($type) {
		$this->Input->setRules($this->getRules());
		$params = array('type' => $type);

		if ($this->Input->validates($params)) {
			// Instantiate the report
			$report = $this->loadReport($type);

			return $report->getName();
		}
	}

	/**
	 * Retrieves a list of all available reports (those that exist on the file system)
	 *
	 * @return array An array representing each report and its name
	 */
	public function getAvailable() {
		$reports = array();

		$dir = opendir(COMPONENTDIR . "reports");
		while (false !== ($report = readdir($dir))) {
			// If the file is not a hidden file, and is a directory, accept it
			if (substr($report, 0, 1) != "." && is_dir(COMPONENTDIR . "reports" . DS . $report)) {

				try {
					$rep = $this->loadReport($report);
					$reports[$report] = $rep->getName();
				}
				catch (Exception $e) {
					// The report could not be loaded, try the next
					continue;
				}
			}
		}
		return $reports;
	}

	/**
	 * Retrieves the options for the given report type. Sets Input errors on failure
	 *
	 * @param string $type The type of report to fetch the options for
	 * @param array $vars A list of option values to pass to the report (optional)
	 * @return string The options as a view
	 */
	public function getOptions($type, array $vars=array()) {
		$this->Input->setRules($this->getRules());
		$params = array('type' => $type);

		if ($this->Input->validates($params)) {
			// Instantiate the report
			$report = $this->loadReport($type);

			return $report->getOptions($this->company_id, $vars);
		}
	}

	/**
	 * Generates the report type with the given vars. Sets Input errors on failure
	 *
	 * @param string $type The type of report to fetch
	 * @param array $vars A list of option values to pass to the report
	 * @param string $format The format of the report to generate (optional, default csv)
	 * @param string $return (optional, default "download") One of the following:
	 * 	- download To build and send the report to the browser to prompt for download; returns null
	 * 	- false To build and send the report to the browser to prompt for download; returns null
	 * 	- object To return a PDOStatement object representing the report data; returns PDOStatement
	 * 	- true To return a PDOStatement object representing the report data; returns PDOStatement
	 * 	- file To build the report and store it on the file system; returns the path to the file
	 * @return mixed A PDOStatement, string, or void based on the $return parameter
	 */
	public function fetchAll($type, array $vars, $format = "csv", $return = "download") {
		// Accept boolean return value for backward compatibility
		// Convert return to one of the 3 accepted types: download, object, file
		if ($return === true || $return == "true") {
			$return = "object";
		} elseif ($return === false || $return == "false") {
			$return = "download";
		}

		// Default to download
		$return = in_array($return, array("download", "object", "file"))
			? $return
			: "download";

		// Validate the report type/format are valid
		$rules = array(
			'format' => array(
				'valid' => array(
					'rule' => array("array_key_exists", $this->getFormats()),
					'message' => $this->_("ReportManager.!error.format.valid", true)
				)
			)
		);

		$params = array('type' => $type, 'format' => $format);
		$this->Input->setRules(array_merge($this->getRules(), $rules));

		if ($this->Input->validates($params)) {
			// Instantiate the report
			$report = $this->loadReport($type);

			// Build the report data
			$results = $report->fetchAll($this->company_id, $vars);

			if (method_exists($report, "errors")) {
				if (($errors = $report->errors())) {
					$this->Input->setErrors($errors);
					return;
				}
			}

			// Return the Iterator
			if ($return === "object") {
				return $results;
			}

			// Create the file
			$path_to_file = rtrim($this->temp_dir, DS) . DS . $this->makeFileName($format);

			if (empty($this->temp_dir) || !is_dir($this->temp_dir)
				|| (file_put_contents($path_to_file, "") === false)
				|| !is_writable($path_to_file)
			) {
				$this->Input->setErrors(
					array(
						'temp_dir' => array(
							'writable' => $this->_("ReportManager.!error.temp_dir.writable", true)
						)
					)
				);
				return;
			}

			// Build the report and send it to the browser
			$headings = $report->getColumns();

			$heading_names = array();
			$heading_format = array();
			$heading_options = array();
			foreach ($headings as $key => $value) {
				// Set name
				if (isset($value['name'])) {
					$heading_names[] = $value['name'];
				}
				// Set any formatting
				if (isset($value['format'])) {
					$heading_format[$key] = $value['format'];

					// Set any options
					if (isset($value['options'])) {
						$heading_options[$key] = $value['options'];
					}
				}
			}

			// Add the data to a temp file
			$content = $this->buildCsvRow($heading_names);
			// Create the file
			file_put_contents($path_to_file, $content);

			// Add row data
			foreach ($results as $fields) {
				$row = array();
				// Build each cell value
				foreach ($headings as $key => $value) {
					$cell = (property_exists($fields, $key) ? $fields->{$key} : "");
					$formatting = (array_key_exists($key, $heading_format) ? $heading_format[$key] : null);
					$options = (array_key_exists($key, $heading_options) ? $heading_options[$key] : null);

					// Add a date format to the cell
					if ($formatting == "date" && !empty($cell)) {
						$cell = $this->Date->cast($cell, "date_time");
					} elseif ($formatting == "replace" && !empty($options) && is_array($options)) {
						// Replace the value with one of the options provided
						$cell = array_key_exists($cell, $options)
							? $options[$cell]
							: $cell;
					}

					$row[] = $cell;
				}

				// Add the row to the file
				file_put_contents($path_to_file, $this->buildCsvRow($row), FILE_APPEND);
			}

			// Return the path to the file on the file system
			if ($return == "file") {
				return $path_to_file;
			}

			// Download the data
			$new_file_name = "report-" . $type . "-" . $this->Date->cast(date("c"), "Y-m-d") . "." . $format;
			$this->Download->setContentType("text/" . $format);

			// Download from temp file
			$this->Download->downloadFile($path_to_file, $new_file_name);
			@unlink($path_to_file);
			exit();
		}
	}

	/**
	 * Creates a temporary file name to store to disk
	 *
	 * @param string $ext The file extension
	 * @return string The rewritten file name in the format of YmdTHisO_[hash].[ext] (e.g. 20121009T154802+0000_1f3870be274f6c49b3e31a0c6728957f.txt)
	 */
	private function makeFileName($ext) {
		$file_name = md5(uniqid()) . $ext;

		return $this->Date->format("Ymd\THisO", date("c")) . "_" . $file_name;
	}

	/**
	 * Uses Excel-style formatting for CSV fields (individual cells)
	 *
	 * @param mixed $field A single string of data representing a cell, or an array of fields representing a row
	 * @return mixed An escaped and formatted single cell or array of fields as given
	 */
	protected function formatCsv($field) {
		if (is_array($field)) {
			foreach ($field as &$cell)
				$cell = "\"" . str_replace('"', '""', $cell) . "\"";

			return $field;
		}
		return "\"" . str_replace('"', '""', $field) . "\"";
	}

	/**
	 * Builds a CSV row
	 *
	 * @param array $fields A list of data to place in each cell
	 * @return string A CSV row containing the field data
	 */
	protected function buildCsvRow(array $fields) {
		$row = "";
		$formatted_fields = $this->formatCsv($fields);
		$num_fields = count($fields);

		$i = 0;
		foreach ($fields as $key => $value)
			$row .= $formatted_fields[$key] . (++$i == $num_fields ? "\n" : ",");

		return $row;
	}

	/**
	 * Validates that the given report type exists
	 *
	 * @param string $type The report type
	 * @return boolean True if the report type exists, false otherwise
	 */
	public function validateType($type) {
		$reports = $this->getAvailable();

		return array_key_exists($type, $reports);
	}

	/**
	 * Returns the rules to validate the report type
	 *
	 * @return array A list of rules
	 */
	private function getRules() {
		return array(
			'type' => array(
				'valid' => array(
					'rule' => array(array($this, "validateType")),
					'message' => $this->_("ReportManager.!error.type.valid", true)
				)
			)
		);
	}

	/**
	 * Fetch a custom report
	 *
	 * @param int $id The ID of the custom report
	 * @return stdClass The custom report
	 */
	public function getReport($id) {
		$fields = array('id', 'company_id', 'name', 'query', 'date_created');
		$report = $this->Record->select($fields)
			->from("reports")
			->where("id", "=", $id)
			->fetch();
		if ($report) {
			$report->fields = $this->getReportFields($id);
		}
		return $report;
	}

	/**
	 * Fetch custom report fields
	 *
	 * @param int $id The ID of the custom report
	 * @return array An array of stdClass objects representing custom report fields
	 */
	protected function getReportFields($id) {
		$fields = array('id', 'report_id', 'name', 'label', 'type', 'values', 'regex');

		$report_fields = $this->Record->select($fields)
			->from("report_fields")
			->where("report_id", "=", $id)
			->fetchAll();

		foreach ($report_fields as $field) {
			if ($field->values != "") {
				$field->values = unserialize($field->values);
			}

			$field->required = 'no';
			if ($field->regex !== null) {
				$field->required = 'yes';
				if ($field->regex !== $this->reportDefaultRegex($field->type)) {
					$field->required = 'custom';
				}
			}
		}
		return $report_fields;
	}

	/**
	 * Get all custom reports available
	 *
	 * @return array An array of stdClass objects each representing a report
	 */
	public function getReports() {
		$fields = array('id', 'company_id', 'name', 'query', 'date_created');
		return $this->Record->select($fields)
			->from("reports")
			->where("company_id", "=", $this->company_id)
			->fetchAll();
	}

	/**
	 * Add a custom report
	 *
	 * @param array $vars
	 * @return stdClass The report added
	 */
	public function addReport(array $vars) {
		$vars['date_created'] = date("c");
		$vars['company_id'] = $this->company_id;

		$this->Input->setRules($this->getReportRules($vars));

		if ($this->Input->validates($vars)) {
			$fields = array('id', 'company_id', 'name', 'query', 'date_created');
			$this->Record->insert("reports", $vars, $fields);

			$id = $this->Record->lastInsertId();

			$this->saveReportFields($id, $vars['fields']);
			return $this->getReport($id);
		}
	}

	/**
	 * Edit a custom report
	 *
	 * @param int $id
	 * @param array $vars
	 * @return stdClass The report updated
	 */
	public function editReport($id, array $vars) {
		$this->Input->setRules($this->getReportRules($vars, true));

		if ($this->Input->validates($vars)) {
			$fields = array('company_id', 'name', 'query');
			$this->Record->where("id", "=", $id)
				->update("reports", $vars, $fields);

			$this->saveReportFields($id, $vars['fields']);
			return $this->getReport($id);
		}
	}

	/**
	 * Save report fields
	 *
	 * @param int $id
	 * @param array $report_fields A numerically indexed array of report fields containing:
	 * - id
	 * - name
	 * - label
	 * - type
	 * - values
	 * - regex
	 */
	protected function saveReportFields($id, array $report_fields) {
		$fields = array("report_id", "name", "label", "type", "values", "regex");
		foreach ($report_fields as $field) {
			$field['report_id'] = $id;
			$field['regex'] = $this->requiredToRegex(
				$field['type'],
				$field['required'],
				$field['regex']
			);

			if (empty($field['values'])) {
				$field['values'] = null;
			}
			else {
				$values = explode(",", $field['values']);
				foreach ($values as $val) {
					$values[] = trim($val);
				}
				$field['values'] = serialize($values);
			}

			// Remove field
			if (!empty($field['id']) && empty($field['name'])) {
				$this->Record->from("report_fields")
					->where("report_id", "=", $id)
					->where("id", "=", $field['id'])->delete();
			}
			elseif (!empty($field['id'])) {
				$this->Record->where("id", "=", $field['id'])
					->where("report_id", "=", $id)
					->update("report_fields", $field, $fields);
			}
			elseif (!empty($field['name'])) {
				$this->Record->insert("report_fields", $field, $fields);
			}
		}
	}

	/**
	 * Delete a custom report
	 *
	 * @param int $id The ID of the custom report to delete
	 */
	public function deleteReport($id) {
		$this->Record->from("reports")
			->leftJoin("report_fields", "report_fields.report_id", "=", "reports.id", false)
			->where("reports.id", "=", $id)
			->where("reports.company_id", "=", $this->company_id)
			->delete(array("reports.*", "report_fields.*"));
	}

	/**
	 * Fetches rules for adding/editing custom reports
	 *
	 * @param array $vars
	 * @param boolean $edit
	 * @return array Rules
	 */
	private function getReportRules(array $vars, $edit = false) {
		$rules = array(
			'name' => array(
				'valid' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("ReportManager.!error.name.valid")
				)
			),
			'query' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'last' => true,
					'message' => $this->_("ReportManager.!error.query.empty")
				),
				'valid' => array(
					'rule' => array(array($this, "validateQuery")),
					'message' => $this->_("ReportManager.!error.query.valid")
				)
			),
			'date_created' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'post_format' => array(array($this, 'dateToUtc')),
					'message' => $this->_("ReportManager.!error.date_created.format")
				)
			)
		);

		return $rules;
	}

	/**
	 * Validate that only one query is present, and it is a select query
	 *
	 * @param string $sql The user provided query
	 * @return boolean True if the query is valid, false otherwise
	 */
	public function validateQuery($sql) {
		$pos = strpos($sql, ';');
		$filtered_sql = substr($sql, 0, $pos+1);
		if ($pos === false) {
			$filtered_sql = $sql;
		}

		$filtered_sql = trim($filtered_sql);

		return (
			$filtered_sql === trim($sql)
			&& stripos($filtered_sql, "SELECT") === 0
		);
	}

	/**
	 * Fetch report field types
	 *
	 * @return array An array of key/value pairs
	 */
	public function reportFieldTypes() {
		return array(
			'text' => $this->_("ReportManager.reportfieldtypes.text"),
			'select' => $this->_("ReportManager.reportfieldtypes.select"),
			'date' => $this->_("ReportManager.reportfieldtypes.date")
		);
	}

	/**
	 * Fetche report field required types
	 *
	 * @return array An array of key/value pairs
	 */
	public function reportRequiredType() {
		return array(
			'no' => $this->_("ReportManager.reportrequiredtypes.no"),
			'yes' => $this->_("ReportManager.reportrequiredtypes.yes"),
			'custom' => $this->_("ReportManager.reportrequiredtypes.custom")
		);
	}

	/**
	 * Convert the report field type and required status to a regex
	 *
	 * @param string $type The field type
	 * @param string $required Required option
	 * @param string $regex Defined regex (if any)
	 * @return string The regex to use
	 */
	protected function requiredToRegex($type, $required, $regex = null) {
		if ($required == "custom") {
			return $regex;
		}
		if ($required == "no") {
			return null;
		}

		return $this->reportDefaultRegex($type);
	}

	/**
	 * Fetch the default regex for the given report field type
	 *
	 * @param string $type The field type
	 * @return string The default regex to use for that type
	 */
	protected function reportDefaultRegex($type) {
		switch ($type) {
			case "date":
				return "/[0-9]{4}-[0-9]{2}-[0-9]{2}/";
			case "select":
				// no break
			case "text":
				return "/.+/";
		}
	}
}
