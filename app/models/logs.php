<?php
/**
 * Log System
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Logs extends AppModel {
	
	/**
	 * Initialize Logs
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("logs"));
	}
	
	/**
	 * Logs a single email
	 *
	 * @param array $vars An array of variable log info, including
	 * 	- company_id The company ID
	 * 	- to_client_id The client ID this log is to (optional)
	 * 	- from_staff_id The staff ID this log is from (optional)
	 * 	- to_address A To email address
	 * 	- from_address A From email address
	 * 	- from_name A from name
	 * 	- cc_address A comma separated list of CC addresses (optional)
	 * 	- subject An email subject
	 * 	- body_text Plain text email body (optional)
	 * 	- body_html HTML email body (optional)
	 * 	- sent Whether this email has been sent, either 0 (default) or 1 (optional)
	 * 	- error A message to be used on error
	 * @return int The email log ID for this record, void if error
	 */	
	public function addEmail(array $vars) {
		$rules = array(
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("Logs.!error.company_id.exists")
				)
			),			
			'to_address' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.to_address.format")
				)
			),
			'from_address' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.from_address.format")
				)
			),
			'from_name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.from_name.empty")
				)
			),
			'subject' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.subject.empty")
				)
			),
			'sent' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Logs.!error.sent.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 1),
					'message' => $this->_("Logs.!error.sent.length")
				)
			)			
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Insert into email log
			$vars['date_sent'] = $this->dateToUtc(date("c"));
			
			$fields = array("company_id", "to_client_id", "from_staff_id", "to_address",
				"from_address", "from_name", "cc_address", "subject", "body_text", "body_html",
				"sent", "error", "date_sent"
			);
			$this->Record->insert("log_emails", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}		
	}
	
	/**
	 * Logs a gateway requests
	 *
	 * @param array $vars An array of variable log info, including
	 * 	- staff_id The staff ID
	 * 	- gateway_id The gateway ID
	 * 	- direction The direction type, either 'input' (default) or 'output' (optional)
	 * 	- url The URL
	 * 	- data Gateway data (optional)
	 * 	- from_name A from name
	 * 	- status The status type, either 'error' (default) or 'success' (optional)
	 * 	- group The gateway group
	 * @return int The gateway log ID for this record, void if error
	 */
	public function addGateway(array $vars) {
		$rules = array(
			'gateway_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "gateways"),
					'message' => $this->_("Logs.!error.gateway_id.exists")
				)
			),
			'direction' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateDirection")),
					'message' => $this->_("Logs.!error.direction.format")
				)
			),
			'url' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.url.empty")
				)
			),
			'status' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStatus")),
					'message' => $this->_("Logs.!error.status.format")
				)
			),
			'group' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.group.empty")
				),
				'maxlength' => array(
					'rule' => array("maxLength", 8),
					'message' => $this->_("Logs.!error.group.maxlength")
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Insert into gateway logs
			$vars['date_added'] = $this->dateToUtc(date("c"));
			
			$fields = array("staff_id", "gateway_id", "direction", "url", "data",
				"date_added", "status", "group"
			);
			$this->Record->insert("log_gateways", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Logs a module request
	 *
	 * @param array $vars An array of variable log info, including
	 * 	- staff_id The staff ID (optional)
	 * 	- module_id The module ID
	 * 	- direction The direction type, either 'input' (default) or 'output' (optional)
	 * 	- url The URL
	 * 	- data Gateway data (optional)
	 * 	- from_name A from name
	 * 	- status The status type, either 'error' (default) or 'success' (optional)
	 * 	- group The module group
	 * @return int The module log ID for this record, void if error
	 */
	public function addModule(array $vars) {
		$rules = array(
			'module_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "modules"),
					'message' => $this->_("Logs.!error.gateway_id.exists")
				)
			),
			'direction' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateDirection")),
					'message' => $this->_("Logs.!error.direction.format")
				)
			),
			'url' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.url.empty")
				)
			),
			'status' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStatus")),
					'message' => $this->_("Logs.!error.status.format")
				)
			),
			'group' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.group.empty")
				),
				'maxlength' => array(
					'rule' => array("maxLength", 8),
					'message' => $this->_("Logs.!error.group.maxlength")
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Insert into gateway logs
			$vars['date_added'] = $this->dateToUtc(date("c"));
			
			$fields = array("staff_id", "module_id", "direction", "url", "data",
				"date_added", "status", "group"
			);
			$this->Record->insert("log_modules", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Logs a user log in 
	 *
	 * @param array $vars An array of variable log info, including
	 * 	- user_id The user ID
	 * 	- ip_address The IP address
	 * 	- company_id The company ID
	 * 	- result The result of the login attempt:
	 * 		- success
	 * 		- failure
	 * @return int The user log ID for this record, void if error
	 */
	public function addUser(array $vars) {
		$rules = array(
			'user_id' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.user_id.empty")
				)
			),
			'ip_address' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.ip_address.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 39),
					'message' => $this->_("Logs.!error.ip_address.length")
				)
			),
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("Logs.!error.company_id.exists")
				)
			),
			'result' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("in_array", array("success", "failure")),
					'message' => $this->_("Logs.!error.result.format")
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Insert into user logs
			$current_date = $this->dateToUtc(date("c"));
			$vars['date_added'] = $current_date;
			$vars['date_updated'] = $current_date;
			
			$fields = array("user_id", "ip_address", "company_id", "date_added", "date_updated", "result");
			$this->Record->insert("log_users", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Updates the user log for a user
	 *
	 * @param int $user_id The user's ID
	 * @param string $ip_address The user's IP address
	 * @param int $company_id The company ID
	 */
	public function updateUser($user_id, $ip_address, $company_id) {
		$rules = array(
			'user_log_exists' => array(
				'empty' => array(
					'rule' => array(array($this, "validateUserLogExists"), $ip_address, $company_id),
					'message' => $this->_("Logs.!error.user_log_exists.empty")
				)
			)
		);
		
		$vars = array('user_log_exists'=>$user_id);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Update the most recent user that matches
			$user_log = $this->Record->select("id")->from("log_users")->
				where("user_id", "=", $user_id)->where("ip_address", "=", $ip_address)->
				where("company_id", "=", $company_id)->
				where("result", "=", "success")->
				order(array('date_added'=>"DESC"))->limit(1)->
				fetch();
			
			if ($user_log) {
				$this->Record->where("id", "=", $user_log->id)->
					update("log_users", array('date_updated'=>$this->dateToUtc(date("c"))), array("date_updated"));
			}
		}
	}
	
	/**
	 * Logs a change to contact information
	 *
	 * @param array $vars An array of contact change data including:
	 * 	- contact_id The ID of the contact that has been modified
	 * 	- fields An array of fields where the key if the field that changed and contains:
	 * 		- prev The value of the field prior to the update
	 * 		- cur The value of that field after the update
	 * @return int The contact log ID for this record, void if error
	 */
	public function addContact(array $vars) {
		
		$rules = array(
			'contact_id' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.contact_id.empty")
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Insert into contact logs
			$vars['date_changed'] = $this->dateToUtc(date("c"));
			$vars['change'] = base64_encode(serialize($vars['fields']));
			
			$fields = array("contact_id", "change", "date_changed");
			$this->Record->insert("log_contacts", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Logs access to customer payment account details
	 *
	 * @param array $vars An array of access information including:
	 * 	- staff_id The ID of the staff member that accessed the account details
	 * 	- first_name The first name of the account holder
	 * 	- last_name The last name of the account holder
	 * 	- type The account type ('ach','cc')
	 * 	- account_type The ACH or CC account type (i.e. 'savings', or 'visa')
	 * 	- last4 The encrypted last 4 digits of the account number (must be encrypted using AppModel::systemEncrypt())
	 * 	- account_id The ID of the account accessed (accounts_ach.id or accounts_cc.id)
	 * @return int The account access log ID for this record, void if error
	 * @see AppModel::systemEncrypt()
	 */
	public function addAccountAccess(array $vars) {

		$rules = array(
			'staff_id' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.staff_id.empty")
				)
			),
			'type' => array(
				'format' => array(
					'rule' => array(array($this, "validateType")),
					'message' => $this->_("Logs.!error.type.format")
				)
			),
			'account_id' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.account_id.empty")
				)
			),
			'first_name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.first_name.empty")
				)
			),
			'last_name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Logs.!error.last_name.empty")
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Insert into account access logs
			$vars['date_accessed'] = $this->dateToUtc(date("c"));
			
			$fields = array("staff_id", "first_name", "last_name", "type", "account_type", "last4", "account_id", "date_accessed");
			$this->Record->insert("log_account_access", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Logs cron task details
	 *
	 * @param array $vars An array of cron task information including:
	 *  - run_id The cron task run ID
	 *  - event This cron log event (optional, default "")
	 *  - group The group associated with this cron event
	 *  - output The output from running this task (optional)
	 *  - start_date The date time that the cron task began running
	 *  - end_date The date time that the cron task completed (optional)
	 * @return int The cron log ID, or void on error
	 */
	public function addCron(array $vars) {
		$rules = array(
			'run_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateCronExists"), (!empty($vars['run_id']) ? $vars['run_id'] : null)),
					'message' => $this->_("Logs.!error.run_id.exists")
				)
			),
			'event' => array(
				'maxlength' => array(
					'rule' => array("maxLength", 32),
					'message' => $this->_("Logs.!error.event.maxlength")
				)
			),
			'group' => array(
				'betweenlength' => array(
					'rule' => array("betweenLength", 1, 32),
					'message' => $this->_("Logs.!error.group.betweenlength")
				),
				'unique' => array(
					'rule' => array(array($this, "validateCronLogUnique"), (!empty($vars['run_id']) ? $vars['run_id'] : null), (!empty($vars['event']) ? $vars['event'] : null)),
					'message' => $this->_("Logs.!error.group.unique")
				)
			),
			'start_date' => array(
				'format' => array(
					'rule' => "isDate",
					'message' => $this->_("Logs.!error.start_date.format"),
					'post_format' => array(array($this, "dateToUtc"))
				)
			),
			'end_date' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'message' => $this->_("Logs.!error.end_date.format"),
					'post_format' => array(array($this, "dateToUtc"))
				)
			)
		);
		
		// Set event to empty string if not given
		if (!isset($vars['event']))
			$vars['event'] = "";
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Insert into cron log
			$fields = array("run_id", "event", "group", "output", "start_date", "end_date");
			$this->Record->insert("log_cron", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Updates the cron log for a particular logged task
	 *
	 * @param int $cron_task_run_id The cron task run ID of the logged task
	 * @param string $group The group of the logged task
	 * @param string $event The cron log event (optional, default "")
	 * @param array $vars An array of cron task information including:
	 *  - output The output from running this task (optional)
	 *  - end_date The date time that the cron task completed (optional)
	 */
	public function updateCron($cron_task_run_id, $group, $event="", array $vars=array()) {
		$rules = array(
			'end_date' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isDate",
					'message' => $this->_("Logs.!error.end_date.format"),
					'post_format' => array(array($this, "dateToUtc"))
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Update the cron log for this task
			$this->Record->where("run_id", "=", $cron_task_run_id)->where("group", "=", $group)->
				where("event", "=", $event)->update("log_cron", $vars, array("output", "end_date"));
		}
	}
	
	/**
	 * Logs a change to transaction information
	 *
	 * @param array $vars An array of transaction change data including:
	 *  - staff_id The ID of the staff member that made the change (optional)
	 * 	- transaction_id The ID of the transaction that has been modified
	 * 	- fields An array of fields where the key if the field that changed and contains:
	 * 		- prev The value of the field prior to the update
	 * 		- cur The value of that field after the update
	 * @return int The transaction log ID for this record, void if error
	 */
	public function addTransaction(array $vars) {
		
		$rules = array(
			'transaction_id' => array(
				'empty' => array(
					'rule' => array(array($this, "validateExists"), "id", "transactions"),
					'message' => $this->_("Logs.!error.transaction_id.empty")
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Insert into contact logs
			$vars['date_changed'] = $this->dateToUtc(date("c"));
			$vars['change'] = base64_encode(serialize($vars['fields']));
			
			$fields = array("staff_id", "transaction_id", "change", "date_changed");
			$this->Record->insert("log_transactions", $vars, $fields);
			
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Fetches a list of all module log entries
	 *
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @param boolean $group_results True to group results by group, false to not group at all (useful for page nav, optional, default false)
	 * @return mixed An array of stdClass objects representing module logs, or false if no logs found
	 */
	public function getModuleList($page=1, array $order_by=array('date_added'=>"DESC"), $group_results=false) {
		$this->Record = $this->getModuleLogs(null, $group_results);
		
		// Return the results
		return $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Returns the total number of module log entries returned from Logs::getModuleList(),
	 * useful in constructing pagination for the getModuleList() method.
	 *
	 * @param boolean $group_results True to group results by group, false to not group at all (useful for page nav, optional, default false)
	 * @return int The total number of module logs
	 * @see Logs::getModuleList()
	 */
	public function getModuleListCount($group_results=false) {
		$this->Record = $this->getModuleLogs(null, $group_results);
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Retrieves a list of all module log entries for a particular module group
	 * 
	 * @param string $group The name of the group whose logs to retrieve
	 * @return mixed An array of stdClass objects representing module logs for a particular group, false otherwise
	 */
	public function getModuleGroupList($group) {
		$this->Record = $this->getModuleLogs($group);
		
		return $this->Record->fetchAll();
	}
	
	/**
	 * Partially constructs the query required by Logs::getModuleList(), and
	 * Logs::getModuleListCount()
	 *
	 * @param string $group The name of the group whose logs to retrieve (optional, default null for all)
	 * @param boolean $group_results True to group results by group, false to not group at all (useful for page nav, optional, default false)
	 * @return Record The partially constructed query Record object
	 */
	private function getModuleLogs($group=null, $group_results=false) {
		$fields = array("log_modules.id", "log_modules.staff_id", "log_modules.module_id",
			"log_modules.direction", "log_modules.url", "log_modules.data",
			"log_modules.date_added", "log_modules.status", "log_modules.group",
			"modules.name"=>"module_name", "staff.first_name"=>"staff_first_name",
			"staff.last_name"=>"staff_last_name"
		);
		
		$this->Record->select($fields)->from("log_modules")->
			leftJoin("staff", "staff.id", "=", "log_modules.staff_id", false)->
			innerJoin("modules", "modules.id", "=", "log_modules.module_id", false);
		
		// Filter based on company ID
		if (Configure::get("Blesta.company_id"))
			$this->Record->where("modules.company_id", "=", Configure::get("Blesta.company_id"));
		
		// Filter for only a single module group
		if ($group != null)
			$this->Record->where("log_modules.group", "=", $group);
		
		// Group results
		if ($group_results)
			$this->Record->group("log_modules.group");
		
		return $this->Record;
	}
	
	/**
	 * Deletes all of the module logs up until the date given for the current company
	 *
	 * @param string $datetime The datetime of the earliest log to keep. All older logs will be purged
	 * @return int The number of records deleted
	 */
	public function deleteModuleLogs($datetime) {
		// Set rules
		$vars = array('datetime'=>$datetime);
		$this->Input->setRules($this->getDeleteLogRules());
		
		if ($this->Input->validates($vars)) {
			// Delete module logs
			$this->Record->from("log_modules")->
				leftJoin("modules", "modules.id", "=", "log_modules.module_id", false)->
				where("log_modules.date_added", "<", $vars['datetime']);
			
			// Filter by company ID
			if (Configure::get("Blesta.company_id")) {
				$this->Record->open()->
					where("modules.company_id", "=", Configure::get("Blesta.company_id"))->
					orWhere("modules.company_id", "=", null)->
					close();
			}
			
			$this->Record->delete(array("log_modules.*"));
			return $this->Record->affectedRows();
		}
		return 0;
	}
	
	/**
	 * Fetches a list of all gateway log entries
	 *
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @param boolean $group_results True to group results by group, false to not group at all (useful for page nav, optional, default false)
	 * @return mixed An array of stdClass objects representing gateway logs, or false if no logs found
	 */
	public function getGatewayList($page=1, array $order_by=array('date_added'=>"DESC"), $group_results=false) {
		$this->Record = $this->getGatewayLogs(null, $group_results);
		
		// Return the results
		return $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Returns the total number of gateway log entries returned from Logs::getGatewayList(),
	 * useful in constructing pagination for the getGatewayList() method.
	 *
	 * @param boolean $group_results True to group results by group, false to not group at all (useful for page nav, optional, default false)
	 * @return int The total number of gateway logs
	 * @see Logs::getGatewayList()
	 */
	public function getGatewayListCount($group_results=false) {
		$this->Record = $this->getGatewayLogs(null, $group_results);
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Retrieves a list of all gateway log entries for a particular gateway group
	 * 
	 * @param string $group The name of the group whose logs to retrieve
	 * @return mixed An array of stdClass objects representing gateway logs for a particular group, false otherwise
	 */
	public function getGatewayGroupList($group) {
		$this->Record = $this->getGatewayLogs($group);
		
		return $this->Record->fetchAll();
	}
	
	/**
	 * Partially constructs the query required by Logs::getGatewayList(), and
	 * Logs::getGatewayListCount()
	 *
	 * @param string $group The name of the group whose logs to retrieve (optional, default null for all)
	 * @param boolean $group_results True to group results by group, false to not group at all (useful for page nav, optional, default false)
	 * @return Record The partially constructed query Record object
	 */
	private function getGatewayLogs($group=null, $group_results=false) {
		$fields = array("log_gateways.id", "log_gateways.staff_id", "log_gateways.gateway_id",
			"log_gateways.direction", "log_gateways.url", "log_gateways.data",
			"log_gateways.date_added", "log_gateways.status", "log_gateways.group",
			"gateways.name"=>"gateway_name", "staff.first_name"=>"staff_first_name",
			"staff.last_name"=>"staff_last_name"
		);
		
		$this->Record->select($fields)->from("log_gateways")->
			leftJoin("staff", "staff.id", "=", "log_gateways.staff_id", false)->
			innerJoin("gateways", "gateways.id", "=", "log_gateways.gateway_id", false);
		
		// Filter based on company ID
		if (Configure::get("Blesta.company_id"))
			$this->Record->where("gateways.company_id", "=", Configure::get("Blesta.company_id"));
		
		// Filter for only a single gateway group
		if ($group != null)
			$this->Record->where("log_gateways.group", "=", $group);
		
		// Group results
		if ($group_results)
			$this->Record->group("log_gateways.group");
		
		return $this->Record;
	}
	
	/**
	 * Deletes all of the gateway logs up until the date given for the current company
	 *
	 * @param string $datetime The datetime of the earliest log to keep. All older logs will be purged
	 * @return int The number of records deleted
	 */
	public function deleteGatewayLogs($datetime) {
		// Set rules
		$vars = array('datetime'=>$datetime);
		$this->Input->setRules($this->getDeleteLogRules());
		
		if ($this->Input->validates($vars)) {
			// Delete gateway logs
			$this->Record->from("log_gateways")->
				leftJoin("gateways", "gateways.id", "=", "log_gateways.gateway_id", false)->
				where("log_gateways.date_added", "<", $vars['datetime']);
			
			// Filter by company ID
			if (Configure::get("Blesta.company_id")) {
				$this->Record->open()->
					where("gateways.company_id", "=", Configure::get("Blesta.company_id"))->
					orWhere("gateways.company_id", "=", null)->
					close();
			}
			
			$this->Record->delete(array("log_gateways.*"));
			return $this->Record->affectedRows();
		}
		return 0;
	}
	
	/**
	 * Fetches a single email log
	 *
	 * @param int $email_log_id The email log ID of the email to get
	 * @return mixed An stdClass representing the email log, or false if none found
	 */
	public function getEmail($email_log_id) {
		$this->Record = $this->getEmailLogs();
		
		return $this->Record->where("log_emails.id", "=", $email_log_id)->fetch();
	}
	
	/**
	 * Fetches a list of all email log entries
	 *
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return array An array of stdClass objects representing email logs
	 */
	public function getEmailList($page=1, array $order_by=array('date_sent'=>"DESC")) {
		$this->Record = $this->getEmailLogs();
		
		// Return the results
		return $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Returns the total number of email log entries returned from Logs::getEmailList(),
	 * useful in constructing pagination for the getEmailList() method.
	 *
	 * @return int The total number of email logs
	 * @see Logs::getEmailList()
	 */
	public function getEmailListCount() {
		$this->Record = $this->getEmailLogs();
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Partially constructs the query required by Logs::getEmailList(), and
	 * Logs::getEmailListCount()
	 *
	 * @return Record The partially constructed query Record object
	 */
	private function getEmailLogs() {
		$fields = array(
			"id", "company_id", "to_client_id", "from_staff_id", "to_address",
			"from_address", "from_name", "cc_address", "subject", "body_text",
			"body_html", "sent", "error", "date_sent"
		);
		
		$this->Record->select($fields)->from("log_emails");
		
		// Filter based on company ID
		if (Configure::get("Blesta.company_id"))
			$this->Record->where("log_emails.company_id", "=", Configure::get("Blesta.company_id"));
		
		return $this->Record;
	}
	
	/**
	 * Deletes all of the email logs up until the date given for the current company
	 *
	 * @param string $datetime The datetime of the earliest log to keep. All older logs will be purged
	 * @return int The number of records deleted
	 */
	public function deleteEmailLogs($datetime) {
		// Set rules
		$vars = array('datetime'=>$datetime);
		$this->Input->setRules($this->getDeleteLogRules());
		
		if ($this->Input->validates($vars)) {
			// Delete email logs
			$this->Record->from("log_emails")->
				where("date_sent", "<", $vars['datetime']);
			
			// Filter by company ID
			if (Configure::get("Blesta.company_id"))
				$this->Record->where("company_id", "=", Configure::get("Blesta.company_id"));
			
			$this->Record->delete();
			return $this->Record->affectedRows();
		}
		return 0;
	}
	
	/**
	 * Fetches the last log entry for the given user
	 *
	 * @param int $user_id The ID of the user
	 * @param string $type The specific log result to fetch (optional), one of:
	 * 	- success The last successful log entry
	 * 	- failure The last failure log entry
	 * 	- any The last log entry (default)
	 * @return mixed An stdClass object representing the user log entry, or false if none exist
	 */
	public function getUserLog($user_id, $type = "any") {
		$this->Record->select()->from("log_users")->
			where("user_id", "=", $user_id);
		
		// Filter on result type
		if ($type != "any")
			$this->Record->where("result", "=", $type);
		
		// Filter based on company ID
		if (Configure::get("Blesta.company_id"))
			$this->Record->where("company_id", "=", Configure::get("Blesta.company_id"));
		
		return $this->Record->order(array("date_updated" => "desc"))->fetch();
	}
	
	/**
	 * Fetches a list of all user log entries
	 *
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return mixed An array of stdClass objects representing user logs, or false if no logs found
	 */
	public function getUserList($page=1, array $order_by=array('date_added'=>"DESC")) {
		$this->Record = $this->getUserLogs();
		
		// Return the results
		return $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Returns the total number of user log entries returned from Logs::getUserList(),
	 * useful in constructing pagination for the getUserList() method.
	 *
	 * @return int The total number of user logs
	 * @see Logs::getUserList()
	 */
	public function getUserListCount() {
		$this->Record = $this->getUserLogs();
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Partially constructs the query required by Logs::getUserList(), and
	 * Logs::getUserListCount()
	 *
	 * @return Record The partially constructed query Record object
	 */
	private function getUserLogs() {
		$fields = array(
			"log_users.id", "log_users.user_id", "log_users.ip_address",
			"log_users.company_id", "log_users.date_added", "log_users.date_updated",
			"log_users.result", "users.username", "type", "temp.first_name",
			"temp.last_name"
		);
		
		// Select all the staff members
		$sql1 = $this->Record->select(array("user_id", "first_name", "last_name"))->select(array("'staff'"=>"type"), false)->from("staff")->get();
		$this->Record->reset();
		
		// Select all the clients
		$sql2 = $this->Record->select(array("clients.user_id", "contacts.first_name", "contacts.last_name"))->select(array("'client'"=>"type"), false)->
			from("clients")->
			on("contacts.contact_type", "=", "primary")->
			innerJoin("contacts", "contacts.client_id", "=", "clients.id", false)->get();
		$values = $this->Record->values;
		$this->Record->reset();
		
		// Create a subquery of the union between staff and clients
		$sub_query_record = new Record();
		$sub_query = $sub_query_record->select()->appendValues($values)->from(array("((" . $sql1 . ") UNION (" . $sql2 . "))"=>"temp1"))->get();
		
		$values = $sub_query_record->values;
		unset($sub_query_record);
		$this->Record->reset();
		
		// Select all user logs
		$this->Record->select($fields)->appendValues($values)->
			from("log_users")->
			innerJoin("users", "users.id", "=", "log_users.user_id", false)->
			innerJoin(array($sub_query=>"temp"), "temp.user_id", "=", "users.id", false);
			
		// Filter based on company ID
		if (Configure::get("Blesta.company_id"))
			$this->Record->where("log_users.company_id", "=", Configure::get("Blesta.company_id"));
		
		$this->Record->group("log_users.id");
		
		return $this->Record;
	}
	
	/**
	 * Deletes all of the users logs up until the date given for the current company
	 *
	 * @param string $datetime The datetime of the earliest log to keep. All older logs will be purged
	 * @return int The number of records deleted
	 */
	public function deleteUserLogs($datetime) {
		// Set rules
		$vars = array('datetime'=>$datetime);
		$this->Input->setRules($this->getDeleteLogRules());
		
		if ($this->Input->validates($vars)) {
			// Delete user logs
			$this->Record->from("log_users")->
				where("date_added", "<", $vars['datetime']);
			
			// Filter by company ID
			if (Configure::get("Blesta.company_id"))
				$this->Record->where("company_id", "=", Configure::get("Blesta.company_id"));
			
			$this->Record->delete();
			return $this->Record->affectedRows();
		}
		return 0;
	}
	
	/**
	 * Fetches a list of all contact log entries
	 *
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return mixed An array of stdClass objects representing contact logs, or false if no logs found
	 */
	public function getContactList($page=1, array $order_by=array('date_changed'=>"DESC")) {
		$this->Record = $this->getContactLogs();
		
		// Return the results
		$results = $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
			
		foreach ($results as &$result) {
			$result->change = unserialize(base64_decode($result->change));
		}
		return $results;
	}
	
	/**
	 * Returns the total number of contact log entries returned from Logs::getContactList(),
	 * useful in constructing pagination for the getContactList() method.
	 *
	 * @return int The total number of contact logs
	 * @see Logs::getContactList()
	 */
	public function getContactListCount() {
		$this->Record = $this->getContactLogs();
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Partially constructs the query required by Logs::getContactLogs(), and
	 * Logs::getContactListCount()
	 *
	 * @return Record The partially constructed query Record object
	 */
	private function getContactLogs() {
		$fields = array(
			"log_contacts.id", "log_contacts.contact_id", "log_contacts.change",
			"log_contacts.date_changed", "contacts.first_name", "contacts.last_name",
			"contacts.client_id"
		);
		
		$this->Record->select($fields)->from("log_contacts")->
			innerJoin("contacts", "contacts.id", "=", "log_contacts.contact_id", false)->
			innerJoin("clients", "clients.id", "=", "contacts.client_id", false)->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false);
		
		// Filter based on company ID
		if (Configure::get("Blesta.company_id"))
			$this->Record->where("client_groups.company_id", "=", Configure::get("Blesta.company_id"));
		
		return $this->Record;
	}
	
	/**
	 * Deletes all of the contact logs up until the date given for the current company
	 *
	 * @param string $datetime The datetime of the earliest log to keep. All older logs will be purged
	 * @return int The number of records deleted
	 */
	public function deleteContactLogs($datetime) {
		// Set rules
		$vars = array('datetime'=>$datetime);
		$this->Input->setRules($this->getDeleteLogRules());
		
		if ($this->Input->validates($vars)) {
			// Delete contact logs
			$this->Record->from("log_contacts")->
				leftJoin("contacts", "contacts.id", "=", "log_contacts.contact_id", false)->
				leftJoin("clients", "clients.id", "=", "contacts.client_id", false)->
				leftJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
				where("log_contacts.date_changed", "<", $vars['datetime']);
			
			// Filter by company ID
			if (Configure::get("Blesta.company_id")) {
				$this->Record->open()->
					where("client_groups.company_id", "=", Configure::get("Blesta.company_id"))->
					orWhere("client_groups.company_id", "=", null)->
					close();
			}
			
			$this->Record->delete(array("log_contacts.*"));
			return $this->Record->affectedRows();
		}
		return 0;
	}
	
	/**
	 * Fetches a list of all transaction log entries
	 *
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return array An array of stdClass objects representing transaction logs
	 */
	public function getTransactionList($page=1, array $order_by=array('date_changed'=>"DESC")) {
		$this->Record = $this->getTransactionLogs();
		
		// Return the results
		$results = $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
			
		foreach ($results as &$result) {
			$result->change = unserialize(base64_decode($result->change));
		}
		return $results;
	}
	
	/**
	 * Returns the total number of transaction log entries returned from Logs::getTransactionLogs(),
	 * useful in constructing pagination for the getTransactionList() method.
	 *
	 * @return int The total number of transaction logs
	 * @see Logs::getTransactionList()
	 */
	public function getTransactionListCount() {
		$this->Record = $this->getTransactionLogs();
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Partially constructs the query required by Logs::getTransactionLogs(), and
	 * Logs::getTransactionListCount()
	 *
	 * @return Record The partially constructed query Record object
	 */
	private function getTransactionLogs() {
		$fields = array(
			"log_transactions.id", "log_transactions.staff_id", "log_transactions.transaction_id",
			"log_transactions.change", "log_transactions.date_changed", 'clients.id' => "client_id",
			'staff.first_name' => "staff_first_name", 'staff.last_name' => "staff_last_name",
			'contacts.first_name' => "client_first_name", 'contacts.last_name' => "client_last_name"
		);
		
		$this->Record->select($fields)->from("log_transactions")->
				leftJoin("transactions", "transactions.id", "=", "log_transactions.transaction_id", false)->
				leftJoin("clients", "clients.id", "=", "transactions.client_id", false)->
				leftJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
				on("contacts.contact_type", "=", "primary")->
				leftJoin("contacts", "clients.id", "=", "contacts.client_id", false)->
				leftJoin("staff", "staff.id", "=", "log_transactions.staff_id", false);
		
		// Filter based on company ID
		if (Configure::get("Blesta.company_id"))
			$this->Record->where("client_groups.company_id", "=", Configure::get("Blesta.company_id"));
		
		return $this->Record;
	}
	
	/**
	 * Deletes all of the transaction logs up until the date given for the current company
	 *
	 * @param string $datetime The datetime of the earliest log to keep. All older logs will be purged
	 * @return int The number of records deleted
	 */
	public function deleteTransactionLogs($datetime) {
		// Set rules
		$vars = array('datetime'=>$datetime);
		$this->Input->setRules($this->getDeleteLogRules());
		
		if ($this->Input->validates($vars)) {
			// Delete transaction logs
			$this->Record->from("log_transactions")->
				leftJoin("transactions", "transactions.id", "=", "log_transactions.transaction_id", false)->
				leftJoin("clients", "clients.id", "=", "transactions.client_id", false)->
				leftJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
				where("log_transactions.date_changed", "<", $vars['datetime']);
			
			// Filter by company ID
			if (Configure::get("Blesta.company_id")) {
				$this->Record->open()->
					where("client_groups.company_id", "=", Configure::get("Blesta.company_id"))->
					orWhere("client_groups.company_id", "=", null)->
					close();
			}
			
			$this->Record->delete(array("log_transactions.*"));
			return $this->Record->affectedRows();
		}
		return 0;
	}
	
	/**
	 * Fetches a list of all account access log entries
	 *
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return mixed An array of stdClass objects representing account access logs, or false if no logs found
	 */
	public function getAccountAccessList($page=1, array $order_by=array('date_accessed'=>"DESC")) {
		$this->Record = $this->getAccountAccessLogs();
		
		// Return the results
		$results = $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
		
		foreach ($results as &$result) {
			$result->last4 = $this->systemDecrypt($result->last4);
		}
		return $results;
	}
	
	/**
	 * Returns the total number of contact log entries returned from Logs::getAccountAccessList(),
	 * useful in constructing pagination for the getAccountAccessList() method.
	 *
	 * @return int The total number of account access logs
	 * @see Logs::getAccountAccessList()
	 */
	public function getAccountAccessListCount() {
		$this->Record = $this->getAccountAccessLogs();
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Fetches account data that was accessed
	 *
	 * @param int $log_id The access log ID
	 * @return An array of stdClass objects representing an account access log, or false if no log found
	 */
	public function getAccountAccessLog($log_id) {
		$this->Record = $this->getAccountAccessLogs();
		
		$result = $this->Record->where("log_account_access.id", "=", $log_id)->fetch();

		if ($result) {
			$result->last4 = $this->systemDecrypt($result->last4);
			$account = $this->Record->select(array("contacts.client_id"))->from("accounts_" . $result->type)->
				innerJoin("contacts", "contacts.id", "=", "accounts_" . $result->type . ".contact_id", false)->
				where("accounts_" . $result->type . ".id", "=", $result->account_id)->fetch();
			if ($account)
				$result->client_id = $account->client_id;
			
		}

		return $result;
	}
	
	/**
	 * Partially constructs the query required by Logs::getAccountAccessLogs(), and
	 * Logs::getAccountAccessListCount()
	 *
	 * @return Record The partially constructed query Record object
	 */
	private function getAccountAccessLogs() {
		$fields = array(
			"log_account_access.id", "log_account_access.staff_id", "log_account_access.first_name",
			"log_account_access.last_name", "log_account_access.type",
			"log_account_access.account_type", "log_account_access.last4",
			"log_account_access.account_id", "log_account_access.date_accessed",
			"staff.first_name"=>"staff_first_name", "staff.last_name"=>"staff_last_name"
		);
		
		$this->Record->select($fields)->from("log_account_access")->
			innerJoin("staff", "staff.id", "=", "log_account_access.staff_id", false)->
			on("log_account_access.type", "=", "ach")->
			leftJoin("accounts_ach", "accounts_ach.id", "=", "log_account_access.account_id", false)->
			on("log_account_access.type", "=", "cc")->
			leftJoin("accounts_cc", "accounts_cc.id", "=", "log_account_access.account_id", false)->
			on("contacts.id", "=", "accounts_ach.contact_id", false)->
			orOn("contacts.id", "=", "accounts_cc.contact_id", false)->
			innerJoin("contacts")->
			innerJoin("clients", "clients.id", "=", "contacts.client_id", false)->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false);
		
		// Filter based on company ID
		if (Configure::get("Blesta.company_id"))
			$this->Record->where("client_groups.company_id", "=", Configure::get("Blesta.company_id"));
		
		return $this->Record;
	}
	
	/**
	 * Deletes all of the account access logs up until the date given for the current company
	 *
	 * @param string $datetime The datetime of the earliest log to keep. All older logs will be purged
	 * @return int The number of records deleted
	 */
	public function deleteAccountAccessLogs($datetime) {
		// Set rules
		$vars = array('datetime'=>$datetime);
		$this->Input->setRules($this->getDeleteLogRules());
		
		if ($this->Input->validates($vars)) {
			// Delete account access logs
			$this->Record->from("log_account_access")->
				leftJoin("staff", "staff.id", "=", "log_account_access.staff_id", false)->
				leftJoin("staff_group", "staff_group.staff_id", "=", "staff.id", false)->
				leftJoin("staff_groups", "staff_groups.id", "=", "staff_group.staff_group_id", false)->
				where("log_account_access.date_accessed", "<", $vars['datetime']);
			
			// Filter by company ID
			if (Configure::get("Blesta.company_id")) {
				$this->Record->open()->
					where("staff_groups.company_id", "=", Configure::get("Blesta.company_id"))->
					orWhere("staff_groups.company_id", "=", null)->
					close();
			}
			
			$this->Record->delete(array("log_account_access.*"));
			return $this->Record->affectedRows();
		}
		return 0;
	}
	
	/**
	 * Fetches the latest cron log for the given run task belonging to this company
	 *
	 * @param int $cron_task_run_id The cron task run ID
	 * @param string $group The group the cron task is apart of (optional, default null)
	 * @return mixed An stdClass object representing the cron log for this task, or false if none exist
	 */
	public function getLatestCron($cron_task_run_id, $group=null) {
		$this->Record = $this->getCronLogs();
		
		$this->Record->where("log_cron.run_id", "=", $cron_task_run_id);
		
		// Filter on group
		if ($group)
			$this->Record->where("log_cron.group", "=", $group);
		
		return $this->Record->order(array("log_cron.start_date"=>"DESC"))->limit(1)->fetch();
	}
	
	/**
	 * Fetches the date at which the given cron task has last been executed
	 *
	 * @param int $cron_task_key The cron task key
	 * @param string $plugin_dir The plugin directory of the plugin this task belongs to (optional)
	 * @param boolean $system True to fetch only system cron tasks, false to fetch company cron tasks (default false)
	 * @return mixed An stdClass object representing the date this cron task was last executed, or false if it has never run
	 */
	public function getCronLastRun($cron_task_key, $plugin_dir=null, $system=false) {
		$this->Record->select(array("log_cron.start_date", "log_cron.end_date"))->from("log_cron")->
			innerJoin("cron_task_runs", "cron_task_runs.id", "=", "log_cron.run_id", false)->
			innerJoin("cron_tasks", "cron_tasks.id", "=", "cron_task_runs.task_id", false);
		
		if ($system)
			$this->Record->where("cron_task_runs.company_id", "=", 0);
		else {
			// Filter based on company ID
			if (Configure::get("Blesta.company_id"))
				$this->Record->where("cron_task_runs.company_id", "=", Configure::get("Blesta.company_id"));
		}
		
		return $this->Record->where("cron_tasks.key", "=", $cron_task_key)->
			where("cron_tasks.plugin_dir", "=", $plugin_dir)->
			order(array("log_cron.start_date"=>"DESC"))->limit(1)->fetch();
	}
	
	/**
	 * Fetches the date at which the system cron has last been executed
	 *
	 * @param string $group The group the cron task is apart of (optional, default null)
	 * @return mixed An stdClass object representing the system cron, or false if it has never run
	 */
	public function getSystemCronLastRun($group=null) {
		$this->Record->select()->from("log_cron")->
			where("run_id", "=", 0)->where("event", "=", "")->
			where("end_date", "!=", null);
			
		if ($group)
			$this->Record->where("group", "=", $group);
		
		return $this->Record->order(array("end_date"=>"DESC"))->limit(1)->fetch();
	}
	
	/**
	 * Clears an incomplete cron task if it has not finished
	 *
	 * @param int $cron_task_run_id The cron task run ID
	 * @param string $group The group the cron task is apart of (optional, default null)
	 */
	public function clearCronTask($cron_task_run_id, $group=null) {
		$this->Record->where("run_id", "=", $cron_task_run_id)->
			where("end_date", "=", null);
		
		if ($group)
			$this->Record->where("group", "=", $group);
		
		$this->Record->update("log_cron", array('end_date' => $this->dateToUtc(date("c"))));
	}
	
	/**
	 * Fetches a list of cron tasks that are currently running for this company within the past 24 hours (i.e. started but not finished)
	 *
	 * @param int $seconds Filter on the number of seconds that have passed since the task has started but not yet completed (optional, null to fetch all tasks currently running)
	 * @return array A list of stdClass objects representing each cron task
	 */
	public function getRunningCronTasks($seconds = null) {
		// Fetch all cron tasks for this company that have not ended recently
		#
		# TODO: time should be a config setting
		#
		$this->Record = $this->getCronLogs()->
			where("log_cron.run_id", "!=", 0)->
			where("log_cron.end_date", "=", null)->
			where("log_cron.start_date", ">=", $this->dateToUtc(strtotime("-24 hours")))->
			order(array('log_cron.start_date' => "DESC"));
		
		// Filter on the number of seconds that have passed
		if (is_numeric($seconds)) {
			$this->Record->having("log_cron.start_date", "<=", $this->dateToUtc(strtotime("-" . (int)abs($seconds) . " seconds")));
		}
		
		$tasks = $this->Record->fetchAll();
		
		// Filter and group the tasks that have not finished running
		$running_tasks = array();
		$run_ids = array();
		
		foreach ($tasks as $task) {
			if (!isset($run_ids[$task->run_id]) && ($latest_task = $this->getLatestCron($task->run_id)) && $latest_task->end_date === null) {
				// Fetch the associated system task that ran this child task (if any)
				$system_task = $this->getSystemCronLastRun($latest_task->group);
				if (!$system_task || ($system_task && $system_task->end_date !== null)) {
					// Set the task as running
					$running_tasks[] = $latest_task;
					// Set the run ID of this task so we don't come back to it again
					$run_ids[$task->run_id] = $task->run_id;
				}
			}
		}
		
		return $running_tasks;
	}
	
	/**
	 * Fetches a list of all cron log entries
	 *
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return mixed An array of stdClass objects representing user logs, or false if no logs found
	 */
	public function getCronList($page=1, array $order_by=array('start_date'=>"DESC")) {
		Language::loadLang(array("cron_tasks"));
		$this->Record = $this->getCronLogs();
		
		// Fetch the cron log results
		$results = $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
		
		// Set the language defines for each task
		foreach ($results as &$task_log) {
			// Set name and description to language define
			$task_log->real_name = $task_log->name;
			$task_log->real_description = $task_log->description;
			
			if ($task_log->is_lang == "1") {
				$task_log->real_name = $this->_($task_log->name);
				$task_log->real_description = $this->_($task_log->description);
			}
		}
		
		return $results;
	}
	
	/**
	 * Returns the total number of cron log entries returned from Logs::getCronList(),
	 * useful in constructing pagination for the getCronList() method.
	 *
	 * @return int The total number of cron logs
	 * @see Logs::getCronList()
	 */
	public function getCronListCount() {
		$this->Record = $this->getCronLogs();
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Partially constructs the query required by Logs::getCronLogs(), and
	 * Logs::getCronListCount()
	 *
	 * @return Record The partially constructed query Record object
	 */
	private function getCronLogs() {
		$fields = array(
			"log_cron.run_id", "log_cron.group", "log_cron.output",
			"log_cron.start_date", "log_cron.end_date", "cron_tasks.id"=>"cron_task_id",
			"cron_tasks.plugin_dir", "cron_tasks.name", "cron_tasks.description",
			"cron_tasks.is_lang", "cron_tasks.type", "cron_task_runs.company_id",
			"cron_task_runs.time", "cron_task_runs.interval", "cron_task_runs.enabled"
		);
		
		$this->Record->select($fields)->from("log_cron")->
			innerJoin("cron_task_runs", "cron_task_runs.id", "=", "log_cron.run_id", false)->
			innerJoin("cron_tasks", "cron_tasks.id", "=", "cron_task_runs.task_id", false);
		
		// Filter based on company ID
		if (Configure::get("Blesta.company_id"))
			$this->Record->where("cron_task_runs.company_id", "=", Configure::get("Blesta.company_id"));
		
		return $this->Record;
	}
	
	/**
	 * Deletes all of the cron logs up until the date given for the current company
	 *
	 * @param string $datetime The datetime of the earliest log to keep. All older logs will be purged
	 * @return int The number of records deleted
	 */
	public function deleteCronLogs($datetime) {
		// Set rules
		$vars = array('datetime'=>$datetime);
		$this->Input->setRules($this->getDeleteLogRules());
		
		if ($this->Input->validates($vars)) {
			// Delete cron logs
			$this->Record->from("log_cron")->
				where("start_date", "<", $vars['datetime'])->
				delete();
			return $this->Record->affectedRows();
		}
		return 0;
	}
	
	/**
	 * Retrieves a list of log rules for deletion
	 *
	 * @return array A list of log deletion rules
	 */
	private function getDeleteLogRules() {
		return array(
			'datetime' => array(
				'format' => array(
					'rule' => "isDate",
					'message' => $this->_("Logs.!error.deletelog_datetime", true),
					'post_format' => array(array($this, "dateToUtc"))
				)
			)
		);
	}
	
	/**
	 * Validates the 'direction' field for module and gateway logs
	 *
	 * @param string $direction The direction
	 * @return boolean True if direction is validated, false otherwise
	 */
	public function validateDirection($direction) {
		return in_array($direction, array("input", "output"));
	}
	
	/**
	 * Validates the 'status' field for module and gateway logs
	 *
	 * @param string $status The status
	 * @return boolean True if status is validated, false otherwise
	 */
	public function validateStatus($status) {
		return in_array($status, array("error", "success"));
	}
	
	/**
	 * Validates the 'type' field for the account access logs
	 *
	 * @param string $type The type 
	 * @return boolean True if type is validated, false otherwise
	 */
	public function validateType($type) {
		return in_array($type, array("ach", "cc"));
	}
	
	/**
	 * Validates that the given cron task run ID exists
	 *
	 * @param int $cron_task_run_id The cron task run ID
	 * @return boolean True if the cron task run ID exists, false otherwise
	 */
	public function validateCronExists($cron_task_run_id) {
		if ($cron_task_run_id == 0)
			return true;
		
		// Fetch the number of cron task runs with this ID
		$count = $this->Record->select("id")->from("cron_task_runs")->
			where("id", "=", $cron_task_run_id)->numResults();
		
		if ($count > 0)
			return true;
		return false;
	}
	
	/**
	 * Checks whether the given cron task ID and group are unique
	 *
	 * @param string $group The cron task group
	 * @param int $cron_task_run_id The cron task run ID
	 * @param string $event The cron event
	 * @return boolean True if the given cron task run ID, group, and event are unique, false otherwise
	 */
	public function validateCronLogUnique($group, $cron_task_run_id, $event) {
		$count = $this->Record->select(array("run_id", "event", "group"))->from("log_cron")->
			where("run_id", "=", $cron_task_run_id)->where("group", "=", $group)->
			where("event", "=", $event)->numResults();
		
		if ($count > 0)
			return false;
		return true;
	}
	
	/**
	 * Checks whether the given information corresponds to a valid user log entry
	 *
	 * @param int $user_id The user's ID to check
	 * @param string $ip_address The IP address of the user
	 * @param int $company_id The ID of the company
	 * @return boolean True if the given information matches a user in the log, false otherwise
	 */
	public function validateUserLogExists($user_id, $ip_address, $company_id) {
		$count = $this->Record->select("id")->from("log_users")->
			where("user_id", "=", $user_id)->where("ip_address", "=", $ip_address)->
			where("company_id", "=", $company_id)->
			order(array('date_added'=>"DESC"))->limit(1)->
			numResults();
		
		if ($count > 0)
			return true;
		return false;
	}
}
?>