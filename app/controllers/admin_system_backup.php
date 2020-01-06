<?php
/**
 * Admin System Backup Settings
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminSystemBackup extends AppController {
	
	public function preAction() {
		parent::preAction();		
		
		// Require login
		$this->requireLogin();		
		
		$this->uses(array("Navigation", "Settings", "Backup"));
		$this->components(array("SettingsCollection", "Security"));
		Language::loadLang("admin_system_backup");
		
		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getSystem($this->base_uri))));
	}
	
	/**
	 * On Demand backup
	 */
	public function index() {
		
	}
	
	/**
	 * FTP backup Settings
	 */
	public function ftp() {
		$this->uses(array("CronTasks"));
		$vars = array();
		
		if (!empty($this->post)) {
			$cron_task = $this->CronTasks->getTaskRunByKey("backups_sftp", null, true);
			$task_run_id = isset($cron_task) ? $cron_task->task_run_id : null;
			$interval = isset($this->post['ftp_rate']) ? $this->post['ftp_rate'] : null;
			
			// Save the settings
			if (($errors = $this->saveBackupSettings($task_run_id, $interval, $this->post)))
				$vars = $this->post;
		}
		
		if (empty($vars))
			$vars = $this->SettingsCollection->fetchSystemSettings($this->Settings);
		
		$this->set("frequency", $this->Backup->frequencies());
		$this->set("vars", $vars);
	}
	
	/**
	 * Test FTP backup connection
	 */
	public function ftpTest() {
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "settings/system/backup/ftp/");
		
		$error_message = Language::_("AdminSystemBackup.!error.sftp_test", true);
		
		try {
			$this->Net_SFTP = $this->Security->create("Net", "SFTP", array($this->post['ftp_host'], $this->post['ftp_port']));
			
			// Attempt to login to test the connection and navigate to the given path. Show success or error
			if ($this->Net_SFTP->login($this->post['ftp_username'], $this->post['ftp_password']) &&
				$this->Net_SFTP->chdir($this->post['ftp_path'])) {
				
				echo $this->setMessage("message", Language::_("AdminSystemBackup.!success.sftp_test", true), true);
			}
			else
				echo $this->setMessage("error", $error_message, true);
		}
		catch (Exception $e) {
			echo $this->setMessage("error", $error_message, true);
		}
		
		return false;
	}
	
	/**
	 * Amazon S3
	 */
	public function amazon() {
		$this->uses(array("CronTasks"));
		$this->components(array("Net"));
		$this->AmazonS3 = $this->Net->create("AmazonS3", array(null, null));
		$vars = array();
		
		if (!empty($this->post)) {
			$cron_task = $this->CronTasks->getTaskRunByKey("backups_amazons3", null, true);
			$task_run_id = isset($cron_task) ? $cron_task->task_run_id : null;
			$interval = isset($this->post['amazons3_rate']) ? $this->post['amazons3_rate'] : null;
			
			// Save the settings
			if (($errors = $this->saveBackupSettings($task_run_id, $interval, $this->post)))
				$vars = $this->post;
		}
		
		if (empty($vars))
			$vars = $this->SettingsCollection->fetchSystemSettings($this->Settings);
		
		$this->set("regions", $this->AmazonS3->getRegions());
		$this->set("frequency", $this->Backup->frequencies());
		$this->set("vars", $vars);
	}
	
	/**
	 * Test Amazon S3 backup connection
	 */
	public function amazonTest() {
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "settings/system/backup/amazon/");
		
		$this->components(array("Net"));
		$connection = false;
		try {
			$this->AmazonS3 = $this->Net->create("AmazonS3", array($this->post['amazons3_access_key'], $this->post['amazons3_secret_key'], true, $this->post['amazons3_region']));
			$connection = $this->AmazonS3->getBucket($this->post['amazons3_bucket'], null, null, 1);
		}
		catch (Exception $e) {
			// error connecting
		}
		
		// Attempt to login to test the connection and navigate to the given path. Show success or error
		if ($connection !== false)
			echo $this->setMessage("message", Language::_("AdminSystemBackup.!success.amazons3_test", true), true);
		else
			echo $this->setMessage("error", Language::_("AdminSystemBackup.!error.amazons3_test", true), true);
		
		return false;
	}
	
	/**
	 * Download a backup
	 */
	public function download() {
		$this->Backup->download();
		
		if (($errors = $this->Backup->errors())) {
			$this->flashMessage("error", $errors);
			$this->redirect($this->base_uri . "settings/system/backup/");
		}
	}
	
	/**
	 * Upload a backup to the configured remote services
	 */
	public function upload() {
		
		$this->Backup->sendBackup();
		
		if (($errors = $this->Backup->errors())) {
			$this->flashMessage("error", $errors);
			$this->redirect($this->base_uri . "settings/system/backup/");
		}
		
		$this->flashMessage("message", Language::_("AdminSystemBackup.!success.backup_uploaded", true));
		$this->redirect($this->base_uri . "settings/system/backup/");
	}
	
	/**
	 * Saves the backup settings and updates the system cron task
	 *
	 * @param int $cron_task_run_id The cron task run ID
	 * @param mixed $interval The time interval (in hours) to set for the cron task
	 * @param array $vars A list of system settings to set
	 * @return mixed An array of error messages, or false if no errors
	 */
	private function saveBackupSettings($cron_task_run_id, $interval, array $vars) {
		// Get valid frequencies
		$backup_frequencies = $this->Backup->frequencies();
		
		// Create a transaction
		$this->CronTasks->begin();
		
		// Check for valid backup frequency
		$errors = array();
		if (!isset($backup_frequencies[$interval]))
			$errors = array(array('invalid_frequency'=>Language::_("AdminSystemBackup.!error.backup_frequency", true)));
		
		// Convert interval into minutes for the cron task
		$task_vars = array();
		if (is_numeric($interval))
			$task_vars = array('enabled' => 1, 'interval' => ((int)$interval * 60));
		else
			$task_vars = array('enabled' => 0);
		
		// Update the cron task interval
		$this->CronTasks->editTaskRun($cron_task_run_id, $task_vars);
		$task_errors = $this->CronTasks->errors();
		
		$errors = array_merge($errors, ($task_errors ? $task_errors : array()));
		
		// Save the settings
		$fields = array(
			"ftp_host", "ftp_port", "ftp_username", "ftp_password", "ftp_path", "ftp_rate",
			"amazons3_access_key", "amazons3_secret_key", "amazons3_bucket", "amazons3_rate",
			"amazons3_region"
		);
		$this->Settings->setSettings($this->post, $fields);
		
		if ($errors) {
			// Error, rollback
			$this->CronTasks->rollBack();
			$this->setMessage("error", $errors);
		}
		else {
			$this->CronTasks->commit();
			$this->setMessage("message", Language::_("AdminSystemBackup.!success.backup_updated", true));
		}
		
		return (empty($errors) ? false : $errors);
	}
}
?>