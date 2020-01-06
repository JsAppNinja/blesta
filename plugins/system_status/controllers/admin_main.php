<?php
/**
 * System Status main controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.system_status
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminMain extends SystemStatusController {
	/**
	 * @var System status values for each status
	 */
	private $status_values = array(
		'cron' => array('serious'=>75, 'minor'=>50),
		'cron_task_stalled' => array('serious'=>25, 'minor'=>25),
		'trial' => array('serious'=>0, 'minor'=>0),
		'invoices' => array('serious'=>30, 'minor'=>30),
		'backup' => array('serious'=>15, 'minor'=>15),
		'updates' => array('serious'=>15, 'minor'=>0),
	);
	/**
	 * @var Time (in seconds) that must pass without a task ending before we deem it stalled
	 */
	private $stalled_time = 3600;
	
	
	/**
	 * Load language
	 */
	public function preAction() {
		parent::preAction();
		
		$this->requireLogin();
		
		Language::loadLang("admin_main", null, PLUGINDIR . "system_status" . DS . "language" . DS);
	}

	/**
	 * Renders the system status widget
	 */
	public function index() {
		// Only available via AJAX
		if (!$this->isAjax())
			$this->redirect($this->base_uri);
		
		$this->uses(array("License", "Logs"));
		$this->components(array("SettingsCollection"));
		
		// Set default errors
		$errors = new stdClass();
		
		// Set the % status of the system
		$system_status = 100;
		$one_day = 86400; // seconds in a day
		$now_timestamp = $this->Date->toTime($this->Logs->dateToUtc(date("c")));
		
		// Default cron has never run
		$errors->cron = array(
			'icon' => "error",
			'message' => Language::_("AdminMain.index.cron_serious", true),
			'link' => $this->base_uri . "settings/system/automation/",
			'link_text' => Language::_("AdminMain.index.cron_configure", true),
			'status_value' => $this->status_values['cron']['serious']
		);
		// Default no tasks stalled
		$errors->cron_task_stalled = false;
		
		// Determine if the cron has run recently
		if (($cron_last_ran = $this->Logs->getSystemCronLastRun())) {
			// Assume cron ran recently
			$errors->cron = false;
			
			// Set cron icon to exclamation if the cron has not run within the past 24 hours
			if (($this->Date->toTime($cron_last_ran->end_date) + $one_day) < $now_timestamp) {
				$errors->cron = array(
					'icon' => "exclamation",
					'message' => Language::_("AdminMain.index.cron_minor", true),
					'status_value' => $this->status_values['cron']['minor']
				);
			}
			else {
				$stalled_tasks = $this->Logs->getRunningCronTasks($this->stalled_time);
				
				if (!empty($stalled_tasks)) {
					$errors->cron_task_stalled = array(
						'icon' => "exclamation",
						'message' => Language::_("AdminMain.index.cron_task_stalled_minor", true, floor($this->stalled_time/60)),
						'link' => $this->base_uri . "settings/company/automation/",
						'link_text' => Language::_("AdminMain.index.cron_task_stalled_automation", true),
						'status_value' => $this->status_values['cron_task_stalled']['minor']
					);
				}
			}
		}
		
		// Assume the invoices have been run recently
		$errors->invoices = false;
		
		// Determine if the create invoice task has actually run recently
		if (($latest_invoice_cron = $this->Logs->getCronLastRun("create_invoice"))) {
			// Set this invoice icon to exclamation if it has not run in the past 24 hours
			if (($this->Date->toTime($latest_invoice_cron->end_date) + $one_day) < $now_timestamp) {
				$errors->invoices = array(
					'icon' => "exclamation",
					'message' => Language::_("AdminMain.index.invoices_minor", true),
					'status_value' => $this->status_values['invoices']['minor']
				);
			}
		}
		
		// See if the cron has run any of the backups recently
		$sftp_backup_run_recently = false;
		if (($latest_sftp_backup = $this->Logs->getCronLastRun("backups_sftp", null, true))) {
			// Set whether this backup has run in the past 7 days
			if (($this->Date->toTime($latest_sftp_backup->end_date) + 7*$one_day) >= $now_timestamp)
				$sftp_backup_run_recently = true;
		}
		
		$amazon_backup_run_recently = false;
		if (($latest_amazon_backup = $this->Logs->getCronLastRun("backups_amazons3", null, true))) {
			// Set whether this backup has run in the past 7 days
			if (($this->Date->toTime($latest_amazon_backup->end_date) + 7*$one_day) >= $now_timestamp)
				$amazon_backup_run_recently = true;
		}
		
		// Assume the backup has run recently
		$errors->backup = false;
		
		// Fetch system settings
		$system_settings = $this->SettingsCollection->fetchSystemSettings();
		
		// Set error with backup
		if ((!$sftp_backup_run_recently && !empty($system_settings['ftp_host']) && !empty($system_settings['ftp_username']) && !empty($system_settings['ftp_password'])) ||
			(!$amazon_backup_run_recently && !empty($system_settings['amazons3_access_key']) && !empty($system_settings['amazons3_secret_key']) && !empty($system_settings['amazons3_bucket']))) {
			// Set backup error
			$errors->backup = array(
				'icon' => "exclamation",
				'message' => Language::_("AdminMain.index.backup_minor", true),
				'status_value' => $this->status_values['backup']['minor']
			);
		}
		
		// Assume this is not a trial
		$errors->trial = false;
		
		$license_data = $this->License->getLocalData();
		
		// Check whether this is actually a trial
		if ($license_data &&
			!empty($license_data['custom']['license_type']) &&
			$license_data['custom']['license_type'] == "trial" &&
			!empty($license_data['custom']['cancellation_date'])) {
			
			// Set trial notice
			$errors->trial = array(
				'icon' => "exclamation",
				'message' => Language::_("AdminMain.index.trial_minor", true, $this->Date->cast($license_data['custom']['cancellation_date'])),
				'link' => "https://account.blesta.com/order/",
				'link_text' => Language::_("AdminMain.index.trial_buy", true),
				'status_value' => $this->status_values['trial']['minor']
			);
		}
		
		// Check support and updates
		if (array_key_exists("updates", $license_data) && $license_data['updates'] !== false) {
			$expired = false;
			if ($license_data['updates'] !== null)
				$expired = strtotime($license_data['updates']) < time();
			
			$errors->updates = array(
				'icon' => ($expired ? "exclamation" : "success"),
				'message' => ($license_data['updates'] === null ? Language::_("AdminMain.index.updates_forever", true) : Language::_("AdminMain.index.updates_" . ($expired ? "serious" : "minor"), true, $this->Date->cast($license_data['updates']))),
				'status_value' => $this->status_values['updates'][($expired ? "serious" : "minor")]
			);
			
			if ($expired) {
				$errors->updates['link'] = "http://www.blesta.com/support-and-updates/";
				$errors->updates['link_text'] = Language::_("AdminMain.index.updates_buy", true);
			}
		}
		
		// Subtract system status values
		foreach ($errors as $error) {
			if (!$error)
				continue;
			$system_status -= $error['status_value'];
		}
		
		$this->set("errors", $errors);
		$this->set("system_status", max(0, $system_status));
		$this->set("health_status", $this->getStatusLanguage($system_status));
		
		return $this->renderAjaxWidgetIfAsync(isset($this->get[0]) ? false : null);
	}
	
	/**
	 * Settings
	 */
	public function settings() {
		// Only available via AJAX
		if (!$this->isAjax()) {
			$this->redirect($this->base_uri);
		}
		
		return $this->renderAjaxWidgetIfAsync(false);
	}
	
	/**
	 * Retrieves the system status language to use based on the overall status
	 */
	private function getStatusLanguage($system_status) {
		if ($system_status <= 50)
			return Language::_("AdminMain.index.health_poor", true);
		elseif ($system_status <= 75)
			return Language::_("AdminMain.index.health_fair", true);
		elseif ($system_status <= 95)
			return Language::_("AdminMain.index.health_good", true);
		return Language::_("AdminMain.index.health_excellent", true);
	}
}
?>