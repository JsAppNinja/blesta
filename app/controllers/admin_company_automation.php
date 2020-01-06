<?php
/**
 * Admin Company Automation Settings
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminCompanyAutomation extends AppController {
	
	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		// Require login
		$this->requireLogin();		
		
		$this->uses(array("Companies", "CronTasks", "Logs", "Navigation"));
		$this->components(array("SettingsCollection"));
		$this->helpers(array("DataStructure"));
		
		$this->ArrayHelper = $this->DataStructure->create("Array");
		
		Language::loadLang("admin_company_automation");
		
		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getCompany($this->base_uri))));
	}
	
	/**
	 * Automation settings
	 */
	public function index() {
		$vars = (object)$this->ArrayHelper->numericToKey($this->CronTasks->getAllTaskRun());
		
		// Get the company timezone
		$company_timezone = $this->SettingsCollection->fetchSetting($this->Companies, $this->company_id, "timezone");
		$company_timezone = $company_timezone['value'];
		
		if (!empty($this->post)) {
			// Merge cron task updates with the task itself when updating
			$data = array_merge(array('enabled' => array()), $this->post);
			$cron_tasks = $this->ArrayHelper->keyToNumeric(array_merge((array)$vars, $data));
			
			// Start a transaction
			$this->CronTasks->begin();
			
			$errors = array();
			
			// Update each cron task
			foreach ($cron_tasks as $cron_task) {
				// Set missing checkboxes
				if ($cron_task['enabled'] == null)
					$cron_task['enabled'] = "0";
				
				// Set plugin directory to null if empty
				if (empty($cron_task['plugin_dir']))
					$cron_task['plugin_dir'] = null;
				
				$this->CronTasks->editTaskRun($cron_task['task_run_id'], $cron_task);
				
				// Keep the most recent errors by breaking out
				if ($this->CronTasks->errors())
					break;
			}
			
			// Use only the most recent cron task's errors.
			// Note: there should never be errors
			if (($errors = $this->CronTasks->errors())) {
				// Error, rollback and reset vars
				$this->CronTasks->rollBack();
				
				$this->setMessage("error", $errors);
				$vars = (object)$this->ArrayHelper->numericToKey($cron_tasks);
			}
			else {
				// Success, commit changes
				$this->CronTasks->commit();
				
				$this->flashMessage("message", Language::_("AdminCompanyAutomation.!success.automation_updated", true));
				$this->redirect($this->base_uri . "settings/company/automation/");
			}
		}
		
		// Cast the time to the proper time format
		if (isset($vars->time)) {
			foreach ($vars->time as &$time)
				$time = $this->Date->cast($time, "H:i:s");
		}
		
		// Fetch currently running tasks
		$all_running_tasks = $this->Logs->getRunningCronTasks();
		$running_tasks = array();
		foreach ($all_running_tasks as $task) {
			$running_tasks[$task->run_id] = $task;
		}
		
		// Set the time that each task has last ran
		$vars->last_ran = array();
		$vars->is_running = array();
		$vars->is_stalled = array();
		if (isset($vars->task_run_id)) {
			$base_cron_tasks = array();
			
			foreach ($vars->task_run_id as $task_run_id) {
				// Fetch the base cron task in this tasks' group to check whether it has completed
				$group = null;
				if (isset($running_tasks[$task_run_id])) {
					// Create a hash of base cron tasks by group
					if (!isset($base_cron_tasks[$running_tasks[$task_run_id]->group]))
						$base_cron_tasks[$running_tasks[$task_run_id]->group] = $this->Logs->getSystemCronLastRun($running_tasks[$task_run_id]->group);
					
					// Set the group to filter this latest cron task on
					if ($base_cron_tasks[$running_tasks[$task_run_id]->group]) {
						$base_task = $base_cron_tasks[$running_tasks[$task_run_id]->group];
						$group = ($base_task->end_date !== null ? $base_task->group : null);
						unset($base_task);
					}
				}
				
				// Fetch the latest cron task
				$task_log = $this->Logs->getLatestCron($task_run_id, $group);
				
				// Set the date this cron task last ran
				$vars->last_ran[] = (!empty($task_log) ? $this->Date->cast($task_log->start_date, "date_time") : null);
				$vars->is_running[] = (!empty($task_log) && $task_log->end_date === null);
				$vars->is_stalled[] = (!empty($task_log) && $task_log->end_date === null && $group !== null);
			}
		}
		
		$this->set("company_timezone", str_replace("_", " ", $company_timezone));
		$this->set("time_values", $this->getTimes(5));
		$this->set("interval_values", $this->getIntervals());
		$this->set("vars", $vars);
	}
	
	/**
	 * Clears the POSTed cron task
	 */
	public function clearTask() {
		// Clear the cron task
		if (!empty($this->post) && !empty($this->post['run_id'])) {
			$this->Logs->clearCronTask($this->post['run_id']);
		}
		
		$this->flashMessage("message", Language::_("AdminCompanyAutomation.!success.task_cleared", true));
		$this->redirect($this->base_uri . "settings/company/automation/");
	}
	
	/**
	 * Retrieve a list of available cron task intervals (in minutes)
	 *
	 * @return array A list of intervals
	 */
	private function getIntervals() {
		$intervals = array(5=>5, 10=>10, 15=>15, 30=>30, 45=>45);
		
		foreach ($intervals as &$interval)
			$interval = $interval . " " . Language::_("AdminCompanyAutomation.getintervals.text_minutes", true);
		
		// Set each hour up to 24 hours
		for ($i=1; $i<=24; $i++)
			$intervals[$i*60] = $i . " " . Language::_("AdminCompanyAutomation.getintervals.text_hour" . (($i == 1) ? "" : "s"), true);
		
		return $intervals;
	}
}
?>