<?php
/**
 * Upgrades to version 3.0.2
 * 
 * @package blesta
 * @subpackage blesta.components.upgrades.tasks
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Upgrade3_0_2 extends UpgradeUtil {
	
	/**
	 * @var array An array of all tasks completed
	 */
	private $tasks = array();
	
	/**
	 * Setup
	 */
	public function __construct() {
		Loader::loadComponents($this, array("Record"));
	}
	
	/**
	 * Returns a numerically indexed array of tasks to execute for the upgrade process
	 *
	 * @retrun array A numerically indexed array of tasks to execute for the upgrade process
	 */
	public function tasks() {
		return array(
			'setRootWebDir'
		);
	}
	
	/**
	 * Processes the given task
	 *
	 * @param string $task The task to process
	 */
	public function process($task) {
		$tasks = $this->tasks();
		
		// Ensure task exists
		if (!in_array($task, $tasks))
			return;
		
		$this->tasks[] = $task;
		$this->{$task}();
	}
	
	/**
	 * Rolls back all tasks completed for the upgrade process
	 */
	public function rollback() {
		// Undo all tasks
		while(($task = array_pop($this->tasks))) {
			$this->{$task}(true);
		}
	}
	
	/**
	 * Sets root web directory for use with CLI processes
	 *
	 * @param boolean $undo True to undo the change false to perform the change
	 */
	private function setRootWebDir($undo = false) {
		
		$webdir = str_replace("/", DS, str_replace("index.php/", "", WEBDIR));
		$public_root_web = rtrim(str_replace($webdir == DS ? "" : $webdir, "", ROOTWEBDIR), DS) . DS;
		
		if ($undo) {
			$this->Record->from("settings")->where("key", "=", "root_web_dir")->delete();
		}
		else {
			$this->Record->insert("settings", array('key' => "root_web_dir", 'value' => $public_root_web));
		}
	}
}
?>