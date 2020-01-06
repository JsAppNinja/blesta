<?php
/**
 * Billing Overview main controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.billing_overview
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminMain extends BillingOverviewController {
	
	/**
	 * @var array A list of graph line hex colors
	 */
	private $line_colors = array("#0075b2", "#6ab31d", "#444a42", "#a80000");
	
	/**
	 * Pre action
	 */
	public function preAction() {
		parent::preAction();
		
		// Load settings
		$this->uses(array("BillingOverview.BillingOverviewSettings"));
		
		// Load currency helper
		$this->helpers(array("CurrencyFormat", "Form", "Html"));
		
		Language::loadLang("admin_main", null, PLUGINDIR . "billing_overview" . DS . "language" . DS);
	}
	
	/**
	 * Get graph date ranges
	 */
	private function getDateRanges() {
		// Set graph date ranges
		return array(
			7 => "7 " . Language::_("AdminMain.date_range.days", true),
			30 => "30 " . Language::_("AdminMain.date_range.days", true)
		);
	}

	/**
	 * Renders the billing overview widget
	 */
	public function index() {
		// Only available via AJAX
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "billing/");
		
		// Set the overview content
		$this->set("content", $this->partial("admin_main_overview", $this->overview(false)));
		
		return $this->renderAjaxWidgetIfAsync(isset($this->get[0]) ? false : null);
	}
	
	/**
	 * Settings
	 */
	public function settings() {
		// Only available via AJAX
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "billing/");
		
		// Get all overview settings
		$settings = array();
		$overview_settings = $this->BillingOverviewSettings->getSettings($this->Session->read("blesta_staff_id"), $this->company_id);
		
		foreach ($overview_settings as $setting)
			$settings[$setting->key] = $setting->value;
		
		$this->set("vars", $settings);
		$this->set("date_ranges", $this->getDateRanges());
		
		return $this->renderAjaxWidgetIfAsync(false);
	}
	
	/**
	 * Update settings
	 */
	public function update() {
		// Set unchecked checkboxes
		$settings = array("revenue_today", "revenue_month", "revenue_year", "credits_today", "credits_month",
			"credits_year", "invoiced_today", "invoiced_month", "balance_outstanding", "balance_overdue",
			"scheduled_cancelation", "services_active", "services_added_today", "services_canceled_today",
			"graph_revenue", "graph_revenue_year", "graph_invoiced", "show_legend"
		);
		foreach ($settings as $setting) {
			if (!isset($this->post[$setting]))
				$this->post[$setting] = 0;
		}
		unset($settings, $setting);
		
		// Set each setting into indexed array for adding
		$settings = array();
		foreach ($this->post as $key=>$value)
			$settings[] = array('key'=>$key, 'value'=>$value);
		
		// Add the settings
		$this->BillingOverviewSettings->add($this->Session->read("blesta_staff_id"), $this->company_id, $settings);
		
		if (($errors = $this->BillingOverviewSettings->errors())) {
			// Error
			$this->flashMessage("error", $errors);
		}
		else {
			// Success
			$this->flashMessage("message", Language::_("AdminMain.!success.options_updated", true));
		}
		
		$this->redirect($this->base_uri . "billing/");
	}
	
	/**
	 * Retrieves the billing overview inner content
	 */
	public function overview($echo=true) {
		// Load settings, statistics, currencies
		$this->uses(array("BillingOverview.BillingOverviewStatistics", "Currencies"));
		$this->components(array("Json", "SettingsCollection"));
		
		// Set staff ID
		$staff_id = $this->Session->read("blesta_staff_id");
		
		// Set dates
		$datetime = $this->Date->format("c");
		$dates = array(
			'today_start' => $this->Date->cast($datetime, "Y-m-d 00:00:00"),
			'today_end' => $this->Date->cast($datetime, "Y-m-d 23:59:59"),
			'month_start' => $this->Date->cast($datetime, "Y-m-01 00:00:00"),
			'month_end' => $this->Date->cast($datetime, "Y-m-t 23:59:59"),
			'year_start' => $this->Date->cast($datetime, "Y-01-01 00:00:00"),
			'year_end' => $this->Date->cast($datetime, "Y-12-31 23:59:59")
		);
		
		// Set currency
		$default_currency = $this->SettingsCollection->fetchSetting(null, $this->company_id, "default_currency");
		$currency = (isset($default_currency['value']) ? $default_currency['value'] : "");
		
		if (!empty($this->post['currency']))
			$currency = $this->post['currency'];
		
		// Get the statistics to show for this user
		$overview_settings = $this->BillingOverviewSettings->getSettings($staff_id, $this->company_id);
		
		// Set default settings for this staff member if none yet exist
		if (empty($overview_settings)) {
			$this->BillingOverviewSettings->addDefault($staff_id, $this->company_id);
			$overview_settings = $this->BillingOverviewSettings->getSettings($staff_id, $this->company_id);
		}
		
		// Set which statistics to show
		$active_statistics = array();
		foreach ($overview_settings as $setting) {
			if ($setting->value == 1)
				$active_statistics[] = $setting->key;
		}
		
		// Set statistics
		$statistics = array();
		// Get each statistic's data
		foreach ($active_statistics as $statistic) {
			$value = "";
			$value_class = "";
			
			// Set statistic-specific values
			switch ($statistic) {
				case "revenue_today":
					// Get today's revenue
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getRevenue($this->company_id, $currency, $dates['today_start'], $dates['today_end']), $currency);
					$value_class = "more";
					break;
				case "revenue_month":
					// Get this month's revenue
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getRevenue($this->company_id, $currency, $dates['month_start'], $dates['month_end']), $currency);
					$value_class = "more";
					break;
				case "revenue_year":
					// Get this year's revenue
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getRevenue($this->company_id, $currency, $dates['year_start'], $dates['year_end']), $currency);
					$value_class = "more";
					break;
				case "credits_today":
					// Get today's credits
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getCredits($this->company_id, $currency, $dates['today_start'], $dates['today_end']), $currency);
					$value_class = "neutral";
					break;
				case "credits_month":
					// Get this month's credits
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getCredits($this->company_id, $currency, $dates['month_start'], $dates['month_end']), $currency);
					$value_class = "neutral";
					break;
				case "credits_year":
					// Get this year's credits
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getCredits($this->company_id, $currency, $dates['year_start'], $dates['year_end']), $currency);
					$value_class = "neutral";
					break;
				case "invoiced_today":
					// Get today's invoice total
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getAmountInvoiced($this->company_id, $currency, $dates['today_start'], $dates['today_end']), $currency);
					$value_class = "neutral";
					break;
				case "invoiced_month":
					// Get this month's invoice total
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getAmountInvoiced($this->company_id, $currency, $dates['month_start'], $dates['month_end']), $currency);
					$value_class = "neutral";
					break;
				case "balance_outstanding":
					// Get the total amount to be paid
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getOutstandingBalance($this->company_id, $currency), $currency);
					$value_class = "neutral";
					break;
				case "balance_overdue":
					// Get the total amount past due
					$value = $this->CurrencyFormat->format($this->BillingOverviewStatistics->getOverdueBalance($this->company_id, $currency), $currency);
					$value_class = "less";
					break;
				case "scheduled_cancelation":
					// Get the number of service cancelations
					$value = $this->BillingOverviewStatistics->getScheduledCancelationsCount($this->company_id);
					$value_class = "neutral";
					break;
				case "services_active":
					// Get the number of active services
					$value = $this->BillingOverviewStatistics->getActiveServicesCount($this->company_id);
					$value_class = "neutral";
					break;
				case "services_added_today":
					// Get the number of services added today
					$value = $this->BillingOverviewStatistics->getServicesAddedCount($this->company_id, $dates['today_start'], $dates['today_end']);
					$value_class = "neutral";
					break;
				case "services_canceled_today":
					// Get the number of services canceled today
					$value = $this->BillingOverviewStatistics->getServicesCanceledCount($this->company_id, $dates['today_start'], $dates['today_end']);
					$value_class = "neutral";
					break;
				default:
					// Move on, this is not a statistic setting
					continue 2;
			}
			
			$statistics[] = array(
				'class' => $statistic,
				'name' => Language::_("AdminMain.index.statistic." . $statistic, true),
				'value' => $value,
				'value_class' => $value_class
			);
		}
		
		$data = array(
			'vars' => array('currency'=>$currency),
			'currencies' => $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code"),
			'statistics' => $statistics,
			'graphs' => $this->getGraphs($currency, $overview_settings)
		);
		
		// Return the overview data
		if (!$echo)
			return $data;
		
		$this->outputAsJson(array('overview'=>$this->partial("admin_main_overview", $data)));
		return false;
	}
	
	/**
	 * Sets up the data for each graph
	 *
	 * @param string $currency The ISO 4217 currency code
	 * @param array $settings The plugin settings (optional)
	 * @return array A list of graph data and settings
	 */
	private function getGraphs($currency, $settings=null) {
		// Get settings if not given
		if ($settings == null)
			$settings = $this->BillingOverviewSettings->getSettings($this->Session->read("blesta_staff_id"), $this->company_id);
		
		// Get date range of graphs
		$graph_settings = array();
		foreach ($settings as $setting) {
			if ($setting->key == "date_range" || $setting->key == "show_legend")
				$graph_settings[$setting->key] = $setting->value;
		}
		
		// Get each graph in use
		$graphs = array('graphs'=>array(), 'settings'=>$graph_settings, 'line_colors'=>$this->line_colors);
	
		foreach ($settings as $setting) {
			$graph = null;
			
			// Setting is disabled
			if ($setting->value != 1)
				continue;
			
			switch ($setting->key) {
				case "graph_invoiced":
				case "graph_revenue":
					// Get the graph data over the set interval
					$graph = $this->getGraph($setting->key, (isset($graph_settings['date_range']) ? $graph_settings['date_range'] : 0), $currency);
					break;
				case "graph_revenue_year":
					// Get the graph data
					$local_date = clone $this->Date;
					$local_date->setTimezone("UTC", Configure::get("Blesta.company_timezone"));
					
					$now = date("c");
					$start_date = $this->BillingOverviewSettings->dateToUtc($local_date->format("Y-01-01 00:00:00", strtotime($now)));
					$end_date = $this->BillingOverviewSettings->dateToUtc($local_date->format("Y-12-31 23:59:59", strtotime($now)));
					$graph = $this->getGraphBetween($setting->key, $start_date, $end_date, $currency);
					break;
			}
			
			if (isset($graph)) {
				// Set each graph line name
				$line_names = array();
				$points = array();
				foreach ($graph as $key=>$value) {
					$line_names[] = Language::_("AdminMain.graph_line_name." . $key, true);
					$points[] = $value;
				}
				
				$graphs['graphs'][$setting->key] = array(
					'name'=>Language::_("AdminMain.graph_name." . $setting->key, true),
					'data' => array(
						'names' => $line_names,
						'points' => $this->Json->encode($points)
					)
				);
			}
		}
		
		return $graphs;
	}
	
	/**
	 * Retrieves graph date over a given interval
	 *
	 * @param string $key The graph setting key
	 * @param string $start_date The start date from which to fetch data
	 * @param string $end_date The end date from which to fetch data
	 * @param string $currency The ISO 4217 currency code
	 * @param string $interval The interval between data points (i.e. day, week, month, optional, default month)
	 * @return array An array of line data representing the graph
	 */
	private function getGraphBetween($key, $start_date, $end_date, $currency, $interval = "month") {
		$lines = array();
		$interval = (in_array($interval, array("month", "week", "day")) ? $interval : "month");
		
		$local_date = clone $this->Date;
		$local_date->setTimezone("UTC", Configure::get("Blesta.company_timezone"));
		$end = strtotime($local_date->cast($start_date));
		$i = 0;
		
		// Set month interval, and whether to start at the beginning of the month, or sometime in between
		$month_interval = ($interval == "month");
		$start_day_format = ($month_interval && $local_date->format("j", strtotime($start_date)) == "1" ? "01" : "d");
		
		while (strtotime($local_date->cast($end_date)) > $end) {
			$start_format = "Y-m-" . ($i == 0 ? $start_day_format : ($month_interval ? "01" : "d")) . " 00:00:00";
			$date_start = date($start_format, $end);
			$date_end = date("Y-m-" . ($month_interval ? "t" : "d") . " 23:59:59", $end);
			$end = strtotime($date_start . " +1 " . $interval);
			$end = strtotime($this->BillingOverviewStatistics->dateToUtc($end, "c"));
			
			switch ($key) {
				case "graph_revenue_year":
					$lines['credit'][] = array(
						$date_start,
						(float)$this->CurrencyFormat->cast($this->BillingOverviewStatistics->getRevenue($this->company_id, $currency, $date_start, $date_end, "cc"), $currency)
					);
					$lines['other'][] = array(
						$date_start,
						(float)$this->CurrencyFormat->cast($this->BillingOverviewStatistics->getRevenue($this->company_id, $currency, $date_start, $date_end, "other"), $currency)
					);
					$lines['credits'][] = array(
						$date_start,
						(float)$this->CurrencyFormat->cast($this->BillingOverviewStatistics->getCredits($this->company_id, $currency, $date_start, $date_end), $currency)
					);
				break;
			}
			$i++;
		}
		
		// Omit lines that contain no amounts
		return $this->removeEmptyLines($lines, array("other"));
	}
	
	/**
	 * Retrieves graph data over a given interval
	 *
	 * @param string $key The graph setting key
	 * @param int $days The time interval in days to retrieve data from
	 * @param string $currency The ISO 4217 currency code
	 * @return array An array of line data representing the graph
	 */
	private function getGraph($key, $days, $currency) {
		// Set values for each graph line
		$lines = array();
		$datetime = $this->Date->format("c");
		for ($i=0; $i<max(0, (int)$days); $i++) {
			// Set start/end dates
			$date = date("Y-m-d 23:59:59", strtotime($datetime . " -" . $i . " days"));
			$day_start = $this->Date->cast($date, "Y-m-d 00:00:00");
			$day_end = $this->Date->cast($date, "Y-m-d 23:59:59");
			
			// Set each graph data point
			switch ($key) {
				case "graph_revenue":
					/*
					$lines['total'][] = array(
						$day_start,
						(float)$this->CurrencyFormat->cast($this->BillingOverviewStatistics->getRevenue($this->company_id, $currency, $day_start, $day_end), $currency)
					);
					*/
					
					$lines['credit'][] = array(
						$day_start,
						(float)$this->CurrencyFormat->cast($this->BillingOverviewStatistics->getRevenue($this->company_id, $currency, $day_start, $day_end, "cc"), $currency)
					);
					$lines['ach'][] = array(
						$day_start,
						(float)$this->CurrencyFormat->cast($this->BillingOverviewStatistics->getRevenue($this->company_id, $currency, $day_start, $day_end, "ach"), $currency)
					);
					$lines['other'][] = array(
						$day_start,
						(float)$this->CurrencyFormat->cast($this->BillingOverviewStatistics->getRevenue($this->company_id, $currency, $day_start, $day_end, "other"), $currency)
					);
					$lines['credits'][] = array(
						$day_start,
						(float)$this->CurrencyFormat->cast($this->BillingOverviewStatistics->getCredits($this->company_id, $currency, $day_start, $day_end), $currency)
					);
					break;
				case "graph_invoiced":
					$lines['total'][] = array(
						$day_start,
						(float)$this->CurrencyFormat->cast($this->BillingOverviewStatistics->getAmountInvoiced($this->company_id, $currency, $day_start, $day_end), $currency)
					);
					break;
			}
		}
		
		// Omit lines that contain no amounts
		return $this->removeEmptyLines($lines, array("total", "other"));
	}
	
	/**
	 * Removes lines from a graph that contain only zero values
	 * @see AdminMain::getGraph()
	 *
	 * @param array An array of lines
	 * @param array A list of type exceptions that should not be removed even when zero (e.g. "total")
	 * @return array An updated array with line types of only zero values removed
	 */
	private function removeEmptyLines(array $lines, array $exceptions=array()) {
		foreach ($lines as $key=>$index) {
			// Skip key exceptions
			if (in_array($key, $exceptions))
				continue;
			
			foreach ($index as $value) {
				// Skip non-zero types
				if (isset($value[1]) && $value[1] != 0)
					continue 2;
			}
			
			unset($lines[$key]);
		}
		
		return $lines;
	}
}
?>