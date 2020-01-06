<?php
/**
 * System Overview main controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.system_overview
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminMain extends SystemOverviewController {
	/**
	 * @var array A list of graph line hex colors
	 */
	private $line_colors = array("#6ab31d", "#a80000", "#0075b2", "#444a42");
	/**
	 * A list of time-frames and class names for recently active users.
	 */
	private $activity_time_frames = array(
		'user' => array('class' => "user", 'seconds' => null),
		'latest' => array('class' => "latest", 'seconds' => 1800),
		'recent' => array('class' => "recent", 'seconds' => 14400),
		'old' => array('class' => "old", 'seconds' => null)
	);
	
	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		$this->requireLogin();
		
		Language::loadLang("admin_main", null, PLUGINDIR . "system_overview" . DS . "language" . DS);
		$this->uses(array("SystemOverview.SystemOverviewSettings"));
	}

	/**
	 * Renders the system overview widget
	 */
	public function index() {
		// Only available via AJAX
		if (!$this->isAjax())
			$this->redirect($this->base_uri);
		
		$this->set("content", $this->partial("admin_main_overview", $this->overview(false)));
		
		return $this->renderAjaxWidgetIfAsync(isset($this->get[0]) ? false : null);
	}
	
	/**
	 * System overview
	 *
	 * @param boolean $echo Whether or not to print the data via json, or to return it
	 */
	public function overview($echo=true) {
		$this->uses(array("SystemOverview.SystemOverviewStatistics", "SystemOverview.SystemOverviewUsers"));
		
		// Set staff ID
		$staff_id = $this->Session->read("blesta_staff_id");
		
		// Set dates
		$datetime = $this->SystemOverviewStatistics->dateToUtc($this->Date->format("c"));
		$dates = array(
			'today_start' => $this->Date->cast($datetime, "Y-m-d 00:00:00"),
			'today_end' => $this->Date->cast($datetime, "Y-m-d 23:59:59")
		);
		
		// Get the statistics to show for this user
		$overview_settings = $this->SystemOverviewSettings->getSettings($staff_id, $this->company_id);
		
		// Set default settings for this staff member if none yet exist
		if (empty($overview_settings)) {
			$this->SystemOverviewSettings->addDefault($staff_id, $this->company_id);
			$overview_settings = $this->SystemOverviewSettings->getSettings($staff_id, $this->company_id);
		}
		
		// Set which statistics to show
		$active_statistics = array();
		foreach ($overview_settings as $setting) {
			if ($setting->value == 1)
				$active_statistics[] = $setting->key;
		}
		
		$statistics = array();
		foreach ($active_statistics as $statistic) {
			$url = null;
			$value = null;
			switch ($statistic) {
				case "clients_active":
					$value = $this->SystemOverviewStatistics->getClientCount($this->company_id);
					$url = $this->base_uri . "clients/";
					break;
				case "services_active":
					$value = $this->SystemOverviewStatistics->getServiceCount($this->company_id);
					$url = $this->base_uri . "billing/services/";
					break;
				case "services_scheduled_cancellation":
					$value = $this->SystemOverviewStatistics->getServiceCount($this->company_id, "scheduled_cancellation");
					$url = $this->base_uri . "billing/services/scheduled_cancellation/";
					break;
				case "active_users_today":
					$value = $this->SystemOverviewStatistics->getActiveUsersCount($this->company_id, $dates['today_start'], $dates['today_end']);
					break;
				case "recurring_invoices":
					$value = $this->SystemOverviewStatistics->getRecurringInvoiceCount();
					$url = $this->base_uri . "billing/invoices/recurring/";
					break;
				case "pending_orders":
					if (!isset($this->PluginManager))
						$this->uses(array("PluginManager"));
						
					if (!$this->PluginManager->isInstalled("order", $this->company_id))
						continue 2;
					
					$this->uses(array("Order.OrderOrders"));
					$value = $this->OrderOrders->getListCount("pending");
					$url = $this->base_uri . "billing/";
					break;
				case "open_tickets":
					if (!isset($this->PluginManager))
						$this->uses(array("PluginManager"));
						
					if (!$this->PluginManager->isInstalled("support_manager", $this->company_id))
						continue 2;

					$this->uses(array("SupportManager.SupportManagerTickets"));
					$value = $this->SupportManagerTickets->getListCount("open");
					$url = $this->base_uri . "plugin/support_manager/admin_tickets/";					
					break;
				default:
					// This setting is not a valid statistic
					continue 2;
			}
			
			$statistics[] = array(
				'class' => $statistic,
				'name' => Language::_("AdminMain.overview.statistic." . $statistic, true),
				'value' => $value,
				'url' => $url
			);
		}
		
		// Get the current tab
		$tabs = $this->getTabs($overview_settings);
		$current_tab = null;
		foreach ($tabs as $tab) {
			if ($tab['current']) {
				$current_tab = $tab['tab'];
				break;
			}
		}
		
		// Create tabs partial
		$tabs_data = array(
			'graphs' => $this->getGraphs($overview_settings, $current_tab)
		);
		$tab_partial = $this->partial("admin_main_tab_overview", $tabs_data);
		
		$data = array(
			'statistics' => $statistics,
			'recent_users' => $this->getRecentUsers(),
			'tabs' => $tabs,
			'tab_content' => $tab_partial,
		);
		
		if (!$echo)
			return $data;
		
		$this->outputAsJson(array('overview'=>$this->partial("admin_main_overview", $data)));
		return false;
	}
	
	/**
	 * AJAX Fetch tab content
	 */
	public function tab() {
		if (!$this->isAjax() || !isset($this->get[0]))
			exit();
		
		$data = array(
			'graphs' => $this->getGraphs(array(), $this->get[0])
		);
		
		$this->outputAsJson(array('content'=>$this->partial("admin_main_tab_overview", $data)));
		return false;
	}
	
	/**
	 * Settings
	 */
	public function settings() {
		// Only available via AJAX
		if (!$this->isAjax())
			$this->redirect($this->base_uri);
		
		$this->uses(array("PluginManager"));
		
		// Get all overview settings
		$settings = array();
		$overview_settings = $this->SystemOverviewSettings->getSettings($this->Session->read("blesta_staff_id"), $this->company_id);
		
		foreach ($overview_settings as $setting)
			$settings[$setting->key] = $setting->value;
		
		$plugins = array();
		// Check whether Support Manager plugin is installed
		$plugins['support_manager'] = $this->PluginManager->isInstalled("support_manager", $this->company_id);
		// Check whether Order plugin is installed
		$plugins['order'] = $this->PluginManager->isInstalled("order", $this->company_id);
		
		$this->set("plugins", $plugins);
		$this->set("vars", $settings);
		$this->set("date_ranges", $this->getDateRanges());
		
		return $this->renderAjaxWidgetIfAsync(false);
	}
	
	/**
	 * Update settings
	 */
	public function update() {
		// Set default value for each unset checkbox
		$checkboxes = array("clients_active", "active_users_today", "pending_orders", "open_tickets",
			"services_active", "services_scheduled_cancellation", "graph_clients", "graph_services",
			"show_one_tab", "show_legend", "recurring_invoices");
		foreach ($checkboxes as $checkbox) {
			if (!isset($this->post[$checkbox]))
				$this->post[$checkbox] = 0;
		}
		
		// Set each setting into indexed array for adding
		$settings = array();
		foreach ($this->post as $key=>$value)
			$settings[] = array('key'=>$key, 'value'=>$value);
		
		// Add the settings
		$this->SystemOverviewSettings->add($this->Session->read("blesta_staff_id"), $this->company_id, $settings);
		
		if (($errors = $this->SystemOverviewSettings->errors())) {
			// Error
			$this->flashMessage("error", $errors);
		}
		else {
			// Success
			$this->flashMessage("message", Language::_("AdminMain.!success.options_updated", true));
		}
		
		$this->redirect($this->base_uri);
	}
	
	/**
	 * Get graph date ranges
	 */
	private function getDateRanges() {
		// Set graph date ranges
		return array(
			7 => Language::_("AdminMain.date_range.days", true, 7),
			30 => Language::_("AdminMain.date_range.days", true, 30)
		);
	}
	
	/**
	 * Retrieves a formatted list of users that were recently active
	 *
	 * @return array A sorted list of stdClass objects representing each user
	 */
	private function getRecentUsers() {
		if (!isset($this->SettingsCollection))
			$this->components(array("SettingsCollection"));
		
		// Set whether GeoIp is enabled
		$system_settings = $this->SettingsCollection->fetchSystemSettings();
		$geo_ip_db_path = $system_settings['uploads_dir'] . "system" . DS . "GeoLiteCity.dat";
		$use_geo_ip = (($system_settings['geoip_enabled'] == "true") && file_exists($geo_ip_db_path));
		if ($use_geo_ip) {
			// Load GeoIP database
			$this->components(array("Net"));
			if (!isset($this->NetGeoIp))
				$this->NetGeoIp = $this->Net->create("NetGeoIp", array($geo_ip_db_path));
		}
		
		$recent_users = $this->SystemOverviewUsers->getRecentUsers($this->company_id);
		
		// Set class names to represent colors to separate time sections
		$current_user_id = $this->Session->read("blesta_id");
		$current_timestamp = $this->Date->toTime($this->SystemOverviewUsers->dateToUtc(date("c")));
		foreach ($recent_users as &$user) {
			// Set GeoIP info
			$user->geo_ip = array();
			if ($use_geo_ip) {
				try {
					$user->geo_ip = array('location' => $this->NetGeoIp->getLocation($user->ip_address));
				}
				catch (Exception $e) {
					// Nothing to do
				}
			}
			
			// Set last activity time language (in minutes)
			$user_activity_timestamp = $this->Date->toTime($user->date_updated);
			$last_activity = ($current_timestamp - $user_activity_timestamp)/60;
			if ($last_activity < 1)
				$user->last_activity = Language::_("AdminMain.overview.tooltip_last_activity_now", true);
			elseif ($last_activity == 1)
				$user->last_activity = Language::_("AdminMain.overview.tooltip_last_activity_minute", true);
			else
				$user->last_activity = Language::_("AdminMain.overview.tooltip_last_activity_minutes", true, ceil($last_activity));
			
			// Set a class for the user
			$user->class = null;
			if ($user->user_id == $current_user_id) {
				$user->class = $this->activity_time_frames['user']['class'];
				continue;
			}
			
			// Set a class for this user in terms of last activity
			if ($current_timestamp < ($user_activity_timestamp + $this->activity_time_frames['latest']['seconds']))
				$user->class = $this->activity_time_frames['latest']['class'];
			elseif ($current_timestamp < ($user_activity_timestamp + $this->activity_time_frames['recent']['seconds']))
				$user->class = $this->activity_time_frames['recent']['class'];
			else
				$user->class = $this->activity_time_frames['old']['class'];
		}
		
		return $recent_users;
	}
	
	/**
	 * Retrieves a list of tabs to be shown on the overview
	 * @see AdminMain::overview()
	 *
	 * @param array $settings A list of system overview settings (optional)
	 * @param string $current_tab The currently selected tab (optional, default "")
	 * @return array A list of tabs
	 */
	private function getTabs(array $settings=array(), $current_tab="") {
		// Get settings if not given
		if (empty($settings))
			$settings = $this->SystemOverviewSettings->getSettings($this->Session->read("blesta_staff_id"), $this->company_id);
		
		// Set the tabs to show
		$tabs = array();
		$base_url = $this->base_uri . "widget/system_overview/admin_main/tab/";
		foreach ($settings as $setting) {
			if ($setting->value == 1) {
				switch ($setting->key) {
					case "graph_clients":
						$tabs[] = array(
							'name'=>Language::_("AdminMain.overview.tab_clients", true),
							'tab' => "clients",
							'url'=>$base_url . "clients/",
							'current' => ($current_tab == "clients")
						);
						break;
					case "graph_services":
						$tabs[] = array(
							'name'=>Language::_("AdminMain.overview.tab_services", true),
							'tab' => "services",
							'url'=>$base_url . "services/",
							'current' => ($current_tab == "services")
						);
						break;
					case "show_one_tab":
						$tabs = array(
							array(
								'name'=>Language::_("AdminMain.overview.tab_all", true),
								'tab' => "all",
								'url'=>$base_url . "all/",
								'current' => ($current_tab == "all")
							)
						);
						break 2;
				}
			}
		}
		
		// Set current tab if none given
		if (empty($current_tab) && !empty($tabs))
			$tabs[0]['current'] = true;
		
		return $tabs;
	}
	
	
	/**
	 * Sets up the data for each graph
	 *
	 * @param array $settings The plugin settings (optional)
	 * @param string $current_tab The current tab to get graphs for
	 * @return array A list of graph data and settings
	 */
	private function getGraphs(array $settings=array(), $current_tab=null) {
		// Get settings if not given
		if (empty($settings))
			$settings = $this->SystemOverviewSettings->getSettings($this->Session->read("blesta_staff_id"), $this->company_id);
		
		// Get date range of graphs
		$graph_settings = array();
		foreach ($settings as $setting) {
			if ($setting->key == "date_range" || $setting->key == "show_legend")
				$graph_settings[$setting->key] = $setting->value;
		}
		
		// Get each graph in use
		$graphs = array('graphs'=>array(), 'settings'=>$graph_settings, 'line_colors'=>$this->line_colors);
		
		// Set tabs and their graph content keys
		$tabs = array(
			'all' => array(),
			'clients' => array("graph_clients"),
			'services' => array("graph_services"),
		);
		foreach ($tabs as $tab)
			$tabs['all'] = array_merge($tabs['all'], $tab);
		
		if (isset($tabs[$current_tab])) {
			foreach ($settings as $setting) {
				switch ($setting->key) {
					case "graph_clients":
					case "graph_services":
						// Check this graph belongs in this tab or skip it
						if (!in_array($setting->key, $tabs[$current_tab]))
							break;
						
						if ($setting->value == 1) {
							// Get the graph data over the set interval
							$graph = $this->getGraph($setting->key, (isset($graph_settings['date_range']) ? $graph_settings['date_range'] : 0));
							
							// Set each graph line name
							$line_names = array();
							$points = array();
							foreach ($graph as $key=>$value) {
								$line_names[] = Language::_("AdminMain.graph_line_name." . $key, true);
								$points[] = $graph[$key];
							}
							
							$graphs['graphs'][$setting->key] = array(
								'name'=>Language::_("AdminMain.graph_name." . $setting->key, true),
								'data' => array(
									'names' => $line_names,
									'points' => $this->Json->encode($points)
								)
							);
						}
						break;
				}
			}
		}
		
		return $graphs;
	}
	
	/**
	 * Retrieves graph data over a given interval
	 *
	 * @param string $key The graph setting key
	 * @param int $days The time interval in days to retrieve data from
	 * @return array An array of line data representing the graph
	 */
	private function getGraph($key, $days) {
		$this->uses(array("SystemOverview.SystemOverviewStatistics"));
		
		// Set values for each graph line
		$lines = array();
		$datetime = $this->SystemOverviewStatistics->dateToUtc(date("c"));
		for ($i=0; $i<max(0, (int)$days); $i++) {
			// Set start/end dates
			$date = date("Y-m-d 23:59:59", strtotime($datetime . " -" . $i . " days"));
			$day_start = $this->Date->cast($date, "Y-m-d 00:00:00");
			$day_end = $this->Date->cast($date, "Y-m-d 23:59:59");
			
			// Set each graph data point
			switch ($key) {
				case "graph_services":
					$lines['active'][] = array(
						$day_start,
						$this->SystemOverviewStatistics->getServices($this->company_id, $day_start, $day_end)
					);
					$lines['canceled'][] = array(
						$day_start,
						$this->SystemOverviewStatistics->getServices($this->company_id, $day_start, $day_end, "canceled")
					);
					$lines['suspended'][] = array(
						$day_start,
						$this->SystemOverviewStatistics->getServices($this->company_id, $day_start, $day_end, "suspended")
					);
					break;
				case "graph_clients":
					$lines['new'][] = array(
						$day_start,
						$this->SystemOverviewStatistics->getClients($this->company_id, $day_start, $day_end)
					);
					break;
			}
		}
		
		return $lines;
	}
}
?>