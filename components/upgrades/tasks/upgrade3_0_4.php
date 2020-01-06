<?php
/**
 * Upgrades to version 3.0.4
 * 
 * @package blesta
 * @subpackage blesta.components.upgrades.tasks
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Upgrade3_0_4 extends UpgradeUtil {
	
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
			'setTaxRuleStatuses',
			'updateWelcomeEmail'
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
	 * Sets tax rule statuses to inactive if they are invalid
	 *
	 * @param boolean $undo True to undo the change false to perform the change
	 */
	private function setTaxRuleStatuses($undo = false) {
		if ($undo) {
			// Nothing to undo
			return;
		}
		
		// Update all taxes records, set status to 'inactive' if currently an invalid status
		$vars = array('status' => "inactive");
		$this->Record->where("status", "!=", "active")->where("status", "!=", "inactive")->
			update("taxes", $vars, array("status"));
	}
	
	private function updateWelcomeEmail($undo = false) {
		if ($undo) {
			// Nothing to undo
			return;
		}
		
		// Update the welcome email template to include the http protocol for the client_url tag
		$emails = $this->Record->select(array("emails.id", "emails.text", "emails.html"))->from("emails")->
			on("email_groups.id", "=", "emails.email_group_id", false)->
			innerJoin("email_groups", "email_groups.action", "=", "account_welcome")->
			getStatement();
		
		foreach ($emails as $email) {
			$vars = array(
				'text' => str_replace("{client_url}", "http://{client_url}login/", $email->text),
				'html' => str_replace("<a href=\"{client_url}\">{client_url}</a>", "<a href=\"http://{client_url}login/\">http://{client_url}login/</a>", $email->html)
			);
			
			if ($vars['text'] != $email->text || $vars['html'] != $email->html) {
				$this->Record->where("id", "=", $email->id)->update("emails", $vars);
			}
		}
	}
}
?>