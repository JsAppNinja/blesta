<?php
/**
 * Cron Task management
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class CronTasks extends AppModel {
	
	/**
	 * Initialize Cron Tasks
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("cron_tasks"));
	}

	/**
	 * Retrieves a cron task
	 *
	 * @param int $id The cron task ID
	 * @return mixed An stdClass object representing the cron task, or false if it does not exist
	 */
	public function get($id) {
		$task = $this->Record->select()->from("cron_tasks")->
			where("id", "=", $id)->fetch();
		
		// Set language defines for this task
		if ($task)
			$this->setLanguage($task);
		
		return $task;
	}
	
	/**
	 * Retrieves a cron task
	 *
	 * @param string $key The cron task key
	 * @param string $plugin_dir The plugin directory of the plugin this cron task belongs to
	 * @return mixed An stdClass object representing the cron task, or false if it does not exist
	 */
	public function getByKey($key, $plugin_dir=null) {
		$task = $this->Record->select()->from("cron_tasks")->
			where("key", "=", $key)->
			where("plugin_dir", "=", $plugin_dir)->fetch();
		
		// Set language defines for this task
		if ($task)
			$this->setLanguage($task);
			
		return $task;
	}
	
	/**
	 * Retrieves a list of all cron tasks in the system
	 *
	 * @return array An array of stdClass objects representing each cron task
	 */
	public function getAll() {
		$tasks = $this->Record->select()->from("cron_tasks")->fetchAll();
		
		// Set language defines for each task
		foreach ($tasks as $task)
			$this->setLanguage($task);
		
		return $tasks;
	}
	
	/**
	 * Adds a new cron task
	 *
	 * @param array $vars An array of key=>value fields including:
	 * 	- key A unique key representing this cron task
	 * 	- plugin_dir The plugin directory of the plugin this cron task belongs to (optional)
	 * 	- name The name of this cron task
	 * 	- description The description of this cron task (optional)
	 * 	- is_lang 1 if name and description are language definitions in the language file, 0 otherwise (optional, default 0)
	 * 	- type The type of cron task this is ("time" or "interval" based, optional, default "interval")
	 * @return mixed The cron task ID created, or void on error
	 */
	public function add(array $vars) {
		$this->Input->setRules($this->getRules($vars));
		
		if ($this->Input->validates($vars)) {
			$fields = array("key", "plugin_dir", "name", "description", "is_lang", "type");
			
			$this->Record->insert("cron_tasks", $vars, $fields);
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Edits a cron task
	 *
	 * @param int $task_id The cron task ID to edit
	 * @param array $vars A list of key=>value fields to update, including:
	 * 	- name The name of this cron task
	 * 	- description The description of this cron task (optional)
	 * 	- is_lang 1 if name and description are language definitions in the language file, 0 otherwise (optional, default 0)
	 */
	public function edit($task_id, array $vars) {
		$vars['id'] = $task_id;
		
		$this->Input->setRules($this->getRules($vars, true));
		
		if ($this->Input->validates($vars)) {
			$fields = array("name", "description", "is_lang");
			
			$this->Record->where("id", "=", $task_id)->update("cron_tasks", $vars, $fields);
		}
	}
	
	/**
	 * Deletes a plugin's cron task
	 *
	 * @param int $task_id The ID of this cron task
	 * @param int $plugin_dir The plugin directory of the plugin this cron task belongs to
	 */
	public function delete($task_id, $plugin_dir) {
		// Delete all cron task runs associated with this cron task
		$this->Record->from("cron_task_runs")->
			innerJoin("cron_tasks", "cron_tasks.id", "=", "cron_task_runs.task_id", false)->
			where("cron_tasks.plugin_dir", "=", $plugin_dir)->
			where("cron_task_runs.task_id", "=", $task_id)->delete(array("cron_task_runs.*"));
		
		// Delete the cron task
		$this->Record->from("cron_tasks")->where("id", "=", $task_id)->
			where("plugin_dir", "=", $plugin_dir)->delete();
	}
	
	/**
	 * Sets when a cron task should run for a given company
	 *
	 * @param int $task_id The cron task ID associated with this runnable task
	 * @param array $vars A list of key=>value fields to add, including:
	 * 	- time The daily 24-hour time that this task should run (e.g. "14:25", optional, required if interval is not given)
	 *	- interval The interval, in minutes, that this cron task should run (optional, required if time is not given)
	 *	- enabled 1 if this cron task is enabled, 0 otherwise (optional, default 1)
	 * @return mixed The cron task run ID created, or void on error
	 */
	public function addTaskRun($task_id, array $vars) {
		$vars['task_id'] = $task_id;
		$vars['company_id'] = Configure::get("Blesta.company_id");
		$vars['date_enabled'] = $this->dateToUtc(date("c"));
		
		$rules = $this->getTaskRunRules($vars);
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			$fields = array("task_id", "company_id", "enabled", "date_enabled");
			
			// Allow interval to be set if the rule is set, otherwise time, but not both
			if (isset($rules['interval']))
				$fields[] = "interval";
			else
				$fields[] = "time";
			
			$this->Record->insert("cron_task_runs", $vars, $fields);
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Updates when a cron task should run for the given company
	 *
	 * @param int $task_run_id The cron task run ID
	 * @param array $vars A list of key=>value fields to update, including:
	 * 	- time The daily 24-hour time that this task should run (e.g. "14:25", optional, required if interval is not given)
	 *	- interval The interval, in minutes, that this cron task should run (optional, required if time is not given)
	 *	- enabled 1 if this cron task is enabled, 0 otherwise (optional, default 1)
	 */
	public function editTaskRun($task_run_id, array $vars) {
		$vars['id'] = $task_run_id;
		
		$rules = $this->getTaskRunRules($vars, true);
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			$fields = array("enabled", "date_enabled");
			
			// Allow interval to be set if the rule is set, otherwise time, but not both
			if (isset($rules['interval']))
				$fields[] = "interval";
			else
				$fields[] = "time";
			
			// Get the current task and change the date_enabled
			if (isset($vars['enabled']) && ($task_run = $this->getTaskRun($task_run_id))) {
				// Re-enable the task
				if ($vars['enabled'] == "1") {
					if ($task_run->enabled == "0")
						$vars['date_enabled'] = $this->dateToUtc(date("c"));
				}
				else {
					// Disabling this cron task run
					$vars['date_enabled'] = null;
					$vars['enabled'] = "0";
				}
			}
			
			$this->Record->where("id", "=", $task_run_id)->update("cron_task_runs", $vars, $fields);
		}
	}
	
	/**
	 * Deletes when a cron task should run for the given company.
	 * NOTE: This will also delete the cron task itself iff the cron task is no longer used by any other company
	 * and this cron task is related to a plugin
	 *
	 * @param int $task_run_id The cron task run ID
	 */
	public function deleteTaskRun($task_run_id) {
		$fields = array("cron_tasks.plugin_dir", "cron_task_runs.task_id"=>"id", "cron_task_runs.company_id");
		
		// Fetch the cron task associated with this run task, but only those related to a plugin
		$cron_task = $this->Record->select($fields)->from("cron_task_runs")->
			innerJoin("cron_tasks", "cron_tasks.id", "=", "cron_task_runs.task_id", false)->
			where("cron_task_runs.id", "=", $task_run_id)->
			where("cron_tasks.plugin_dir", "!=", null)->fetch();
		
		// Check and delete the cron task itself if it belongs to a plugin and is no longer in use
		// by another company
		if ($cron_task) {
			// Fetch all the cron task runs that are associated with this plugin for any other company
			$this->Record = $this->getAllTaskRuns();
			$num_run_tasks = $this->Record->where("cron_tasks.id", "=", $cron_task->id)->
				where("cron_tasks.plugin_dir", "=", $cron_task->plugin_dir)->
				where("cron_task_runs.company_id", "!=", $cron_task->company_id)->numResults();
			
			// Delete the cron task if no other company uses it
			if ($num_run_tasks == 0)
				$this->Record->from("cron_tasks")->where("cron_tasks.id", "=", $cron_task->id)->
					where("cron_tasks.plugin_dir", "=", $cron_task->plugin_dir)->delete();
		}
		
		// Delete the cron task run
		$this->Record->from("cron_task_runs")->where("id", "=", $task_run_id)->delete();
	}
	
	/**
	 * Retrieves a cron task and its company-specific run settings
	 *
	 * @param int $task_run_id The cron task run ID
	 * @param boolean $system True to fetch only system cron tasks, false to fetch company cron tasks (default false)
	 * @return mixed An stdClass object representing the runnable cron task, or false if one does not exist
	 */
	public function getTaskRun($task_run_id, $system=false) {
		$fields = array("cron_tasks.id", "cron_tasks.key", "cron_tasks.plugin_dir", "cron_tasks.name",
			"cron_tasks.description", "cron_tasks.is_lang", "cron_tasks.type",
			"cron_task_runs.id"=>"task_run_id", "cron_task_runs.company_id", "cron_task_runs.time",
			"cron_task_runs.interval", "cron_task_runs.enabled", "cron_task_runs.date_enabled",
			"plugins.id"=>"plugin_id", "plugins.name"=>"plugin_name", "plugins.version"=>"plugin_version"
		);
		
		$this->Record->select($fields)->from("cron_task_runs")->
			innerJoin("cron_tasks", "cron_tasks.id", "=", "cron_task_runs.task_id", false)->
			leftJoin("plugins", "plugins.dir", "=", "cron_tasks.plugin_dir", false)->
			where("cron_task_runs.id", "=", $task_run_id);
		
		if ($system)
			$this->Record->where("cron_task_runs.company_id", "=", 0);
		else {
			// Filter based on company ID
			if (Configure::get("Blesta.company_id"))
				$this->Record->where("cron_task_runs.company_id", "=", Configure::get("Blesta.company_id"));
		}
		
		$task = $this->Record->fetch();
		
		// Set language defines for this task
		if ($task)
			$this->setLanguage($task);
		
		return $task;
	}
	
	/**
	 * Retrieves a cron task and its company-specific run settings
	 *
	 * @param string $key The cron task key
	 * @param string $plugin_dir The cron task plugin directory (optional, default null)
	 * @param boolean $system True to fetch only system cron tasks, false to fetch company cron tasks (default false)
	 * @return mixed An stdClass object representing the runnable cron task, or false if one does not exist
	 */
	public function getTaskRunByKey($key, $plugin_dir=null, $system=false) {
		// Fetch the cron task run ID
		$this->Record->select("cron_task_runs.id")->from("cron_tasks")->
			innerJoin("cron_task_runs", "cron_task_runs.task_id", "=", "cron_tasks.id", false)->
			where("cron_tasks.key", "=", $key)->
			where("cron_tasks.plugin_dir", "=", $plugin_dir);
		
		if ($system)
			$this->Record->where("cron_task_runs.company_id", "=", 0);
		else {
			// Filter based on company ID
			if (Configure::get("Blesta.company_id")) {
				$this->Record->where("cron_task_runs.company_id", "=", Configure::get("Blesta.company_id"));
			}
		}
		
		$cron_task_run = $this->Record->fetch();
		
		// Return the cron task run
		if ($cron_task_run)
			return $this->getTaskRun($cron_task_run->id, $system);
		return false;
	}
	
	/**
	 * Retrieves a list of all cron tasks and their company-specific run settings for this company
	 *
	 * @param boolean $system True to fetch only system cron tasks, false to fetch company cron tasks (default false)
	 * @return array A list of stdClass objects representing each cron task, or an empty array if none exist
	 */
	public function getAllTaskRun($system=false) {
		$this->Record = $this->getAllTaskRuns();
		
		if ($system)
			$this->Record->where("cron_task_runs.company_id", "=", 0);
		else {
			// Filter based on company ID
			if (Configure::get("Blesta.company_id")) {
				$this->Record->where("cron_task_runs.company_id", "=", Configure::get("Blesta.company_id"))->
					open()->
						where("cron_tasks.plugin_dir", "=", null)->
						orWhere("plugins.company_id", "=", "cron_task_runs.company_id", false)->
					close();
			}
		}
		
		$tasks = $this->Record->fetchAll();
		
		// Set language defines for each task
		foreach ($tasks as $task)
			$this->setLanguage($task);
		
		return $tasks;
	}
	
	/**
	 * Partially constructs a Record object for queries required by both CronTasks::getAllTaskRun() and
	 * CronTasks::deleteTaskRun()
	 *
	 * @return Record The partially constructed query Record object
	 */
	private function getAllTaskRuns() {
		$fields = array("cron_tasks.id", "cron_tasks.key", "cron_tasks.plugin_dir", "cron_tasks.name",
			"cron_tasks.description", "cron_tasks.is_lang", "cron_tasks.type",
			"cron_task_runs.id"=>"task_run_id", "cron_task_runs.company_id", "cron_task_runs.time",
			"cron_task_runs.interval", "cron_task_runs.enabled", "cron_task_runs.date_enabled",
			'plugins.id' => "plugin_id", 'plugins.name' => "plugin_name", 'plugins.version' => "plugin_version",
			'plugins.enabled' => "plugin_enabled"
		);
		
		$this->Record->select($fields)->from("cron_task_runs")->
			innerJoin("cron_tasks", "cron_tasks.id", "=", "cron_task_runs.task_id", false)->
			leftJoin("plugins", "plugins.dir", "=", "cron_tasks.plugin_dir", false);
		
		return $this->Record;
	}
	
	/**
	 * Sets the real name and description values, including language defines, for
	 * the given task by reference
	 *
	 * @param stdClass $task A cron task object containing:
	 * 	- name The name of the cron task
	 * 	- description The description of the cron task
	 * 	- is_lang 1 if the name and description are language definitions, or 0 otherwise
	 */
	private function setLanguage(&$task) {
		// Set name and description to language define
		$task->real_name = $task->name;
		$task->real_description = $task->description;
		
		if ($task->is_lang == "1") {
			$task->real_name = $this->_($task->name);
			$task->real_description = $this->_($task->description);
		}
	}
	
	/**
	 * Retrieves the rules for adding/editing cron task runs
	 *
	 * @param array $vars A list of input fields
	 * @param boolean $edit Trtue for edit rules, false for add rules (optional, default false)
	 * @return array A list of rules
	 */
	private function getTaskRunRules(array $vars, $edit=false) {
		$rules = array(
			'enabled' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("CronTasks.!error.enabled.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 1),
					'message' => $this->_("CronTasks.!error.enabled.length")
				)
			),
			'time' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("matches", "/^([0-9]{1,2}):([0-9]{2})(:([0-9]{2}))?$/"),
					'message' => $this->_("CronTasks.!error.time.format"),
					'post_format' => array(array($this, "dateToUtc"), "H:i:s")
				)
			),
			'interval' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("CronTasks.!error.interval.format")
				)
			)
		);
		
		// Check the cron task type is as expected, and verify IDs exist
		$cron_task = false;
		if ($edit) {
			// Validate the cron task run ID exists if editing this task
			$rules['id'] = array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "cron_task_runs"),
					'message' => $this->_("CronTasks.!error.run_id.exists")
				)
			);
			
			// Also retrieve the cron task to verify its type
			if (!empty($vars['id'])) {
				$cron_task = $this->getTaskRun($vars['id']);
			}
		}
		else {
			// Check that the cron task ID exists
			$rules['task_id'] = array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "cron_tasks"),
					'message' => $this->_("CronTasks.!error.id.exists")
				)
			);
			
			// Also retrieve the cron task to verify its type
			if (!empty($vars['task_id'])) {
				$cron_task = $this->get($vars['task_id']);
			}
		}
		
		// Require a specific cron task type for the cron task run
		if ($cron_task) {
			if ($cron_task->type == "time") {
				// Require time to be set
				unset($rules['time']['format']['if_set'], $rules['interval']);
			}
			else {
				// Require interval to be set
				unset($rules['interval']['format']['if_set'], $rules['time']);
			}
		}
		
		return $rules;
	}
	
	/**
	 * Retrieves the rules for adding/editing cron tasks
	 *
	 * @param array $vars A list of input fields
	 * @param boolean $edit True for edit rules, false for add rules (optional, default false)
	 * @return array A list of rules
	 */
	private function getRules(array $vars, $edit=false) {
		$rules = array(
			'name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("CronTasks.!error.name.empty")
				)
			),
			'is_lang' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("CronTasks.!error.is_lang.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 1),
					'message' => $this->_("CronTasks.!error.is_lang.length")
				)
			)
		);
		
		// Validate cron task ID if editing this task
		if ($edit) {
			$rules['id'] = array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "cron_tasks"),
					'message' => $this->_("CronTasks.!error.id.exists")
				)
			);
		}
		else {
			// Add-only rules for cron tasks 
			$rules['type'] = array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateTaskType")),
					'message' => $this->_("CronTasks.!error.type.format")
				)
			);
			$rules['key'] = array(
				'unique' => array(
					'rule' => array(array($this, "validateKeyUnique"), (empty($vars['plugin_dir']) ? null : $vars['plugin_dir']), (empty($vars['id']) ? null : $vars['id'])),
					'message' => $this->_("CronTasks.!error.key.unique")
				),
				'length' => array(
					'rule' => array("maxLength", 64),
					'message' => $this->_("CronTasks.!error.key.length")
				)
			);
			$rules['plugin_dir'] = array(
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 64),
					'message' => $this->_("CronTasks.!error.plugin_dir.length")
				)
			);
		}
		
		return $rules;
	}
	
	/**
	 * Validates whether the given cron task key and plugin directory are in use by another cron task
	 *
	 * @param string $key The key to check
	 * @param string $plugin_dir The plugin directory
	 * @param int $cron_task_id The cron task ID to exclude from the check (optional)
	 * @return boolean True if the given key is unique, false otherwise
	 */
	public function validateKeyUnique($key, $plugin_dir, $cron_task_id=null) {
		$this->Record->select("id")->from("cron_tasks")->where("key", "=", $key)->where("plugin_dir", "=", $plugin_dir);
		
		// Exclude the given cron task ID
		if ($cron_task_id != null)
			$this->Record->where("id", "!=", $cron_task_id);
		
		$num_tasks = $this->Record->numResults();
		
		if ($num_tasks > 0)
			return false;
		return true;
	}
	
	/**
	 * Validates that the given task type is a valid type
	 *
	 * @param string $type The cron task type
	 * @return boolean True if the type is valid, false otherwise
	 */
	public function validateTaskType($type) {
		return in_array($type, array("time", "interval"));
	}
}
?>