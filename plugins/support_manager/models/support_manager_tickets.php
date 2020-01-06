<?php
/**
 * SupportManagerTickets model
 *
 * @package blesta
 * @subpackage blesta.plugins.support_manager
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class SupportManagerTickets extends SupportManagerModel {

    /**
     * The system-level staff ID
     */
    private $system_staff_id = 0;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		Language::loadLang("support_manager_tickets", null, PLUGINDIR . "support_manager" . DS . "language" . DS);
	}

	/**
	 * Adds a support ticket
	 *
	 * @param array $vars A list of ticket vars, including:
	 * 	- department_id The ID of the department to assign this ticket
	 * 	- staff_id The ID of the staff member this ticket is assigned to (optional)
	 * 	- service_id The ID of the service this ticket is related to (optional)
	 * 	- client_id The ID of the client this ticket is assigned to (optional)
	 * 	- email The email address that a ticket was emailed in from (optional)
	 * 	- summary A brief title/summary of the ticket issue
	 * 	- priority The ticket priority (i.e. "emergency", "critical", "high", "medium", "low") (optional, default "low")
	 * 	- status The status of the ticket (i.e. "open", "awaiting_reply", "in_progress", "closed") (optional, default "open")
	 * @param boolean $require_email True to require the email field be given, false otherwise (optional, default false)
	 * @return mixed The ticket ID, or null on error
	 */
	public function add(array $vars, $require_email = false) {
		// Generate a ticket number
		$vars['code'] = $this->generateCode();

		if (isset($vars['staff_id']) && $vars['staff_id'] == "")
			$vars['staff_id'] = null;
		if (isset($vars['service_id']) && $vars['service_id'] == "")
			$vars['service_id'] = null;

		$vars['date_added'] = date("c");
		$this->Input->setRules($this->getRules($vars, false, $require_email));

		if ($this->Input->validates($vars)) {
			// Add the support ticket
			$fields = array("code", "department_id", "staff_id", "service_id", "client_id",
				"email", "summary", "priority", "status", "date_added");
			$this->Record->insert("support_tickets", $vars, $fields);

			return $this->Record->lastInsertId();
		}
	}

	/**
	 * Updates a support ticket
	 *
	 * @param int $ticket_id The ID of the ticket to update
	 * @param array $vars A list of ticket vars, including (all optional):
	 * 	- department_id The department to reassign the ticket to
	 * 	- staff_id The ID of the staff member to assign the ticket to
	 * 	- service_id The ID of the client service this ticket relates to
	 * 	- client_id The ID of the client this ticket is to be assigned to (can only be set if it is currently null)
	 * 	- summary A brief title/summary of the ticket issue
	 * 	- priority The ticket priority (i.e. "emergency", "critical", "high", "medium", "low")
	 * 	- status The status of the ticket (i.e. "open", "awaiting_reply", "in_progress", "closed")
	 * 	- by_staff_id The ID of the staff member performing the edit (optional, defaults to null to signify the edit is performed by the client)
	 * @param boolean $log True to update the ticket for any loggable changes, false to explicitly not log changes (optional, default true)
	 * @return stdClass An stdClass object representing the ticket (without replies)
	 */
	public function edit($ticket_id, array $vars, $log = true) {
		$vars['ticket_id'] = $ticket_id;

		if (isset($vars['staff_id']) && $vars['staff_id'] == "")
			$vars['staff_id'] = null;
		if (isset($vars['service_id']) && $vars['service_id'] == "")
			$vars['service_id'] = null;

		$this->Input->setRules($this->getRules($vars, true));

		// Update the ticket
		if ($this->Input->validates($vars)) {
			$fields = array("department_id", "staff_id", "service_id", "client_id", "summary",
				"priority", "status");

			// Allow the date closed to be set
			if (isset($vars['status'])) {
				$fields[] = "date_closed";
				if ($vars['status'] == "closed") {
					if (empty($vars['date_closed']))
						$vars['date_closed'] = $this->dateToUtc(date("c"));
				}
				else
					$vars['date_closed'] = null;
			}

            // Log any changes and update the ticket
            if ($log) {
                $log_vars = array('type' => "log", 'by_staff_id' => (isset($vars['by_staff_id']) ? $vars['by_staff_id'] : null));

                foreach (array("by_staff_id", "department_id", "summary", "priority", "status", "ticket_staff_id") as $field) {
                    if (isset($vars[$field]))
                        $log_vars[$field] = $vars[$field];
                }

                // Set the staff that updated the ticket
                if (array_key_exists("by_staff_id", $log_vars)) {
                    $log_vars['staff_id'] = $log_vars['by_staff_id'];
                    unset($log_vars['by_staff_id']);
                }

                $this->addReply($ticket_id, $log_vars);

                // Adding the reply does not update client_id, nor service_id, so update those manually
                $ticket_vars = array();
                if (isset($vars['client_id']))
                    $ticket_vars['client_id'] = $vars['client_id'];
                if (isset($vars['service_id']))
                    $ticket_vars['service_id'] = $vars['service_id'];

                if (!empty($ticket_vars))
                    $this->Record->where("id", "=", $ticket_id)->update("support_tickets", $ticket_vars, $fields);
            }
            else {
                // Only update the ticket
                $this->Record->where("id", "=", $ticket_id)->update("support_tickets", $vars, $fields);
            }

			return $this->Record->get($ticket_id, false);
		}
	}

    /**
     * Updates multiple support tickets at once
     * @see SupportManagerTickets::edit()
     *
     * @param array $ticket_ids An array of ticket IDs to update
     * @param array $vars An array consisting of arrays of ticket vars whose index refers to the index of the $ticket_ids array representing the vars of the specific ticket to update; or an array of vars to apply to all tickets; each including (all optional):
     *  - department_id The department to reassign the ticket to
	 * 	- staff_id The ID of the staff member to assign the ticket to
	 * 	- service_id The ID of the client service this ticket relates to
	 * 	- client_id The ID of the client this ticket is to be assigned to (can only be set if it is currently null)
	 * 	- summary A brief title/summary of the ticket issue
	 * 	- priority The ticket priority (i.e. "emergency", "critical", "high", "medium", "low")
	 * 	- status The status of the ticket (i.e. "open", "awaiting_reply", "in_progress", "closed")
	 * 	- by_staff_id The ID of the staff member performing the edit (optional, defaults to null to signify the edit is performed by the client)
     */
    public function editMultiple(array $ticket_ids, array $vars) {
        // Determine whether to apply vars to all tickets, or whether each ticket has separate vars
        $separate_vars = (isset($vars[0]) && is_array($vars[0]));

        $rules = array(
            'tickets' => array(
                // Check whether the tickets can be assigned to the given service(s)
                'service_matches' => array(
                    'rule' => array(array($this, "validateServicesMatchTickets"), $ticket_ids),
                    'message' => $this->_("SupportManagerTickets.!error.tickets.service_matches")
                ),
                // Check whether the tickets can be assigned to the given department(s)
                'department_matches' => array(
                    'rule' => array(array($this, "validateDepartmentsMatchTickets"), $ticket_ids),
                    'message' => $this->_("SupportManagerTickets.!error.tickets.department_matches")
                )
            )
        );

        $multiple_vars = array('tickets' => $vars);

        $this->Input->setRules($rules);
        if ($this->Input->validates($multiple_vars)) {
            // Validate each ticket individually
            foreach ($ticket_ids as $key => $ticket_id) {
                // Each ticket has separate vars
                $temp_vars = $vars;
                if ($separate_vars) {
                    // Since all fields are optional, we don't need to require any vars be given for every ticket
                    // and they will simply not be updated at all
                    if (!isset($vars[$key]) || empty($vars[$key]))
                        $vars[$key] = array();

                    $temp_vars = $vars[$key];
                }

                // Validate an individual ticket
                $temp_vars['ticket_id'] = $ticket_id;
                $this->Input->setRules($this->getRules($temp_vars, true));
                if (!$this->Input->validates($temp_vars))
                    return;
            }

            // All validation passed, update all tickets accordingly
            foreach ($ticket_ids as $key => $ticket_id) {
                $temp_vars = $vars;
                if ($separate_vars)
                    $temp_vars = $vars[$key];

                $this->edit($ticket_id, $temp_vars);
            }
        }
    }

	/**
	 * Closes a ticket and logs that it has been closed
	 *
	 * @param int $ticket_id The ID of the ticket to close
	 * @param int $staff_id The ID of the staff that closed the ticket (optional, default null if client closed the ticket)
	 */
	public function close($ticket_id, $staff_id = null) {
		// Update the ticket to closed
		$vars = array('status' => "closed", 'date_closed' => date("c"));

        // Set who closed the ticket
		if ($staff_id !== null)
			$vars['by_staff_id'] = $staff_id;

        // Set the current assigned ticket staff member as the staff member on edit, so that it does not get removed
        $ticket = $this->get($ticket_id, false);
        if ($ticket)
            $vars['staff_id'] = $ticket->staff_id;

		$this->edit($ticket_id, $vars);
	}

	/**
	 * Closes all open tickets (not "in_progress") based on the department settings
	 *
	 * @param int $department_id The ID of the department whose tickets to close
	 */
	public function closeAllByDepartment($department_id) {
		Loader::loadModels($this, array("Companies", "SupportManager.SupportManagerDepartments"));

		$department = $this->SupportManagerDepartments->get($department_id);
		if ($department && $department->close_ticket_interval !== null) {
			$reply = "";
			if ($department->response_id !== null) {
				$response = $this->Record->select()->from("support_responses")->
					where("id", "=", $department->response_id)->fetch();
				$reply = ($response ? $response->details : "");
			}

			$company = $this->Companies->get($department->company_id);
			$hostname = isset($company->hostname) ? $company->hostname : "";
			$last_reply_date = $this->dateToUtc(date("c", strtotime("-" . abs($department->close_ticket_interval) . " minutes")));

			$sub_query = $this->Record->select(array("MAX(support_replies.id)"))->from("support_replies")->
				where("support_replies.ticket_id", "=", "support_tickets.id", false)->
				where("support_replies.type", "=", "reply")->get();
			$values = $this->Record->values;
			$this->Record->reset();

			$tickets = $this->Record->select(array("support_tickets.id"))->
				from("support_replies")->
				innerJoin("support_tickets", "support_replies.ticket_id", "=", "support_tickets.id", false)->
				appendValues($values)->
				where("support_replies.id", "in", array($sub_query), false)->
				where("support_tickets.department_id", "=", $department->id)->
				where("support_tickets.status", "!=", "in_progress")->
				where("support_tickets.status", "!=", "closed")->
				where("support_replies.type", "=", "reply")->
				where("support_replies.staff_id", "!=", null)->
				where("support_replies.date_added", "<=", $last_reply_date)->
				fetchAll();

			// Close the tickets
			foreach ($tickets as $ticket) {
                // Add any reply and email, and close the ticket
                $this->staffReplyEmail($reply, $ticket->id, $hostname, $this->system_staff_id);
				$this->close($ticket->id, $this->system_staff_id);
			}
		}
	}

	/**
	 * Adds a reply to a ticket. If ticket data (e.g. department_id, status, priority, summary) have changed
	 * then this will also invoke SupportManagerTickets::edit() to update the ticket, and record any log entries.
	 *
	 * Because of this functionality, this method is assumed to (and should) already be in a transaction when called,
	 * and SupportManagerTickets::edit() should not be called separately.
	 *
	 * @param int $ticket_id The ID of the ticket to reply to
	 * @param array $vars A list of reply vars, including:
	 * 	- staff_id The ID of the staff member this reply is from (optional)
	 * 	- client_id The ID of the client this reply is from (optional)
	 * 	- contact_id The ID of a client's contact that this reply is from (optional)
	 * 	- type The type of reply (i.e. "reply, "note", "log") (optional, default "reply")
	 * 	- details The details of the ticket (optional)
	 * 	- department_id The ID of the ticket department (optional)
	 * 	- summary The ticket summary (optional)
	 * 	- priority The ticket priority (optional)
	 * 	- status The ticket status (optional)
	 * 	- ticket_staff_id The ID of the staff member the ticket is assigned to (optional)
	 * @param array $files A list of file attachments that matches the global FILES array, which contains an array of "attachment" files
	 * @param boolean $new_ticket True if this reply is apart of ticket being created, false otherwise (default false)
	 * @return int The ID of the ticket reply on success, void on error
	 */
	public function addReply($ticket_id, array $vars, array $files = null, $new_ticket = false) {
		$vars['ticket_id'] = $ticket_id;
		$vars['date_added'] = date("c");
		if (!isset($vars['type']))
			$vars['type'] = "reply";

		// Remove reply details if it contains only the signature
		if (isset($vars['details']) && isset($vars['staff_id'])) {
			if (!isset($this->SupportManagerStaff))
				Loader::loadModels($this, array("SupportManager.SupportManagerStaff"));

			$staff_settings = $this->SupportManagerStaff->getSettings($vars['staff_id'], Configure::get("Blesta.company_id"));
			if (isset($staff_settings['signature']) && trim($staff_settings['signature']) == trim($vars['details']))
				$vars['details'] = "";
		}

		// Determine whether or not options have changed that need to be logged
		$log_options = array();
		// "status" should be the last element in case it is set to closed, so it will be the last log entry added
		$loggable_fields = array('department_id' => "department_id", 'ticket_staff_id' => "staff_id", 'summary' => "summary",
			'priority' => "priority", 'status' => "status");

		if (!$new_ticket && (isset($vars['department_id']) || isset($vars['summary']) || isset($vars['priority']) || isset($vars['status']) || isset($vars['ticket_staff_id']))) {
			if (($ticket = $this->get($ticket_id, false))) {
				// Determine if any log replies need to be made
				foreach ($loggable_fields as $key => $option) {
					// Save to be logged iff the field has been changed
					if (isset($vars[$key]) && property_exists($ticket, $option) && $ticket->{$option} != $vars[$key])
						$log_options[] = $key;
				}
			}
		}

		// Check whether logs are being added simultaneously, and if so, do not
		// add a reply iff no reply details, nor files, are attached
		// i.e. allow log entries to be added without a reply/note regardless of vars['type']
		$skip_reply = false;
		if (!empty($log_options) && empty($vars['details']) && (empty($files) || empty($files['attachment']['name'][0])))
			$skip_reply = true;

		if (!$skip_reply) {
			$this->Input->setRules($this->getReplyRules($vars, $new_ticket));

			if ($this->Input->validates($vars)) {
				// Create the reply
				$fields = array("ticket_id", "staff_id", "contact_id", "type", "details", "date_added");
				$this->Record->insert("support_replies", $vars, $fields);
				$reply_id = $this->Record->lastInsertId();

				// Handle file upload
				if (!empty($files['attachment'])) {
					Loader::loadComponents($this, array("SettingsCollection", "Upload"));

					// Set the uploads directory
					$temp = $this->SettingsCollection->fetchSetting(null, Configure::get("Blesta.company_id"), "uploads_dir");
					$upload_path = $temp['value'] . Configure::get("Blesta.company_id") . DS . "support_manager_files" . DS;

					$this->Upload->setFiles($files, false);
					$this->Upload->setUploadPath($upload_path);

					$file_vars = array('files' => array());
					if (!($errors = $this->Upload->errors())) {
						// Will not overwrite existing file
						$this->Upload->writeFile("attachment", false, null, array($this, "makeFileName"));
						$data = $this->Upload->getUploadData();

						// Set the file names/paths
						foreach ($files['attachment']['name'] as $index => $file_name) {
							if (isset($data['attachment'][$index])) {
								$file_vars['files'][] = array(
									'name' => $data['attachment'][$index]['orig_name'],
									'file_name' => $data['attachment'][$index]['full_path']
								);
							}
						}

						$errors = $this->Upload->errors();
					}

					// Error, could not upload the files
					if ($errors) {
						$this->Input->setErrors($errors);
						// Attempt to remove the files if they were somehow written
						foreach ($file_vars['files'] as $files) {
							if (isset($files['file_name']))
								@unlink($files['file_name']);
						}
						return;
					}
					else {
						// Add the attachments
						$file_fields = array("reply_id", "name", "file_name");
						foreach ($file_vars['files'] as $files) {
							if (!empty($files))
								$this->Record->insert("support_attachments", array_merge($files, array('reply_id' => $reply_id)), $file_fields);
						}
					}
				}
			}
		}

		// Only attempt to update log options if there are no previous errors
		if (!empty($log_options) && !$this->errors()) {
			// Update the support ticket
			$data = array_intersect_key($vars, $loggable_fields);
			$ticket_staff_id_field = array();
			if (isset($data['ticket_staff_id']))
				$ticket_staff_id_field = (isset($data['ticket_staff_id']) ? array('staff_id' => $data['ticket_staff_id']) : array());

			$this->edit($ticket_id, array_merge($data, $ticket_staff_id_field), false);

			if (!($errors = $this->errors())) {
				// Log each support ticket field change
				foreach ($log_options as $field) {
					$log_vars = array(
                        'staff_id' => (array_key_exists("staff_id", $vars) ? $vars['staff_id'] : $this->system_staff_id),
						'type' => "log"
					);

					$lang_var1 = "";
					switch ($field) {
						case "department_id":
							$department = $this->Record->select("name")->from("support_departments")->
								where("id", "=", $vars['department_id'])->fetch();
							$lang_var1 = ($department ? $department->name : "");
							break;
						case "priority":
							$priorities = $this->getPriorities();
							$lang_var1 = (isset($priorities[$vars['priority']]) ? $priorities[$vars['priority']] : "");
							break;
						case "status":
							$statuses = $this->getStatuses();
							$lang_var1 = (isset($statuses[$vars['status']]) ? $statuses[$vars['status']] : "");
							break;
						case "ticket_staff_id":
							if (!isset($this->Staff))
								Loader::loadModels($this, array("Staff"));

							$staff = $this->Staff->get($vars['ticket_staff_id']);

							if ($vars['ticket_staff_id'] && $staff)
								$lang_var1 = $staff->first_name . " " . $staff->last_name;
							else
								$lang_var1 = Language::_("SupportManagerTickets.log.unassigned", true);
						default:
							break;
					}

					$log_vars['details'] = Language::_("SupportManagerTickets.log." . $field, true, $lang_var1);

					$this->addReply($ticket_id, $log_vars);
				}
			}
		}

		// Return the ID of the reply
		if (isset($reply_id))
			return $reply_id;
	}

    /**
     * Replies to a ticket and sends a ticket updated email
     *
     * @param string $reply The details to include in the reply
     * @param int $ticket_id The ID of the ticket to reply to
     * @param string $hostname The hostname of the company to which this ticket belongs
     * @param int $staff_id The ID of the staff member replying to the ticket (optional, default 0 for system reply)
     * @param array $additional_tags A key=>value list of the email_action=>tags array to send
	 * 	e.g. array('SupportManager.ticket_updated' => array('tag' => "value"))
     */
    private function staffReplyEmail($reply, $ticket_id, $hostname, $staff_id = 0, $additional_tags = array()) {
        // Add the reply and send the email
        if (!empty($reply)) {
            if (!isset($this->Html))
                Loader::loadHelpers($this, array("Html"));

            $key = mt_rand();
            $hash = $this->generateReplyHash($ticket_id, $key);
            $tags = array('SupportManager.ticket_updated' => array('update_ticket_url' => $this->Html->safe($hostname . $this->getWebDirectory() . Configure::get("Route.client") . "/plugin/support_manager/client_tickets/reply/" . $ticket_id . "/?sid=" . rawurlencode($this->systemEncrypt('h=' . substr($hash, -16) . "|k=" . $key)))));
            $tags = array_merge($tags, $additional_tags);
            $reply_id = $this->addReply($ticket_id, array('details' => $reply, 'staff_id' => $staff_id));
            $this->sendEmail($reply_id, $tags);
        }
    }

    /**
     * Merges a set of tickets into another
     *
     * @param int $ticket_id The ID of the ticket that will receive the merges
     * @param array $tickets A list of ticket IDs to be merged
     */
    public function merge($ticket_id, array $tickets) {
        $ticket = $this->get($ticket_id);

        $rules = array(
            'ticket_id' => array(
				'exists' => array(
					'rule' => ($ticket ? true : false),
					'message' => $this->_("SupportManagerTickets.!error.ticket_id.exists")
				)
			),
            'tickets' => array(
                'valid' => array(
                    'rule' => array(array($this, "validateTicketsMergeable"), $ticket_id),
                    'message' => $this->_("SupportManagerTickets.!error.tickets.valid")
                )
            ),
            'merge_into' => array(
                'itself' => array(
                    'rule' => array("in_array", $tickets),
                    'negate' => true,
                    'message' => $this->_("SupportManagerTickets.!error.merge_into.itself")
                )
            )
        );

        $vars = array('ticket_id' => $ticket_id, 'tickets' => $tickets, 'merge_into' => $ticket_id);

        $this->Input->setRules($rules);
        if ($this->Input->validates($vars)) {
            Loader::loadModels($this, array("Companies"));

            foreach ($tickets as $current_ticket_id) {
                // Fetch the ticket
                $current_ticket = $this->get($current_ticket_id, false);

                // Determine the company hostname
                $company = $this->Companies->get($current_ticket->company_id);
                $hostname = isset($company->hostname) ? $company->hostname : "";

                // Merge all ticket notes/replies into the other ticket
                $this->Record->where("ticket_id", "=", $current_ticket->id)->
                    where("type", "!=", "log")->
                    update("support_replies", array('ticket_id' => $ticket->id));

                // Add a new reply to indicate this ticket has been merged with another, and close it
                $reply = Language::_("SupportManagerTickets.merge.reply", true, $ticket->code);
                $this->staffReplyEmail($reply, $current_ticket_id, $hostname, $this->system_staff_id);
                $this->close($current_ticket_id, $this->system_staff_id);
            }
        }
    }

	/**
	 * Splits the given ticket with the given replies, notes, into a new ticket
	 *
	 * @param int $ticket_id The ID of the ticket to split
	 * @param array $replies A list of reply IDs belonging to the given ticket, which should be assigned to a new ticket
	 * @return int The ID of the newly-created ticket on success, or void on error
	 */
	public function split($ticket_id, array $replies) {
		// Fetch the ticket
		$ticket = $this->get($ticket_id);

		$rules = array(
			'ticket_id' => array(
				'exists' => array(
					'rule' => ($ticket ? true : false),
					'message' => $this->_("SupportManagerTickets.!error.ticket_id.exists")
				)
			),
			'replies' => array(
				'valid' => array(
					'rule' => array(array($this, "validateReplies"), $ticket_id),
					'message' => $this->_("SupportManagerTickets.!error.replies.valid")
				),
				'notes' => array(
					'rule' => array(array($this, "validateSplitReplies"), $ticket_id),
					'message' => $this->_("SupportManagerTickets.!error.replies.notes")
				)
			)
		);

		$vars = array('ticket_id' => $ticket_id, 'replies' => $replies);

		$this->Input->setRules($rules);
		if ($this->Input->validates($vars)) {
			// Create the new ticket
			$new_ticket_id = $this->add((array)$ticket);

			if ($new_ticket_id) {
				// Re-assign the replies
				foreach ($replies as $reply_id) {
					$this->Record->where("id", "=", (int)$reply_id)->update("support_replies", array('ticket_id' => $new_ticket_id));
				}
			}

			return $new_ticket_id;
		}
	}

	/**
	 * Retrieves the total number of tickets in the given status assigned to the given staff/client
	 *
	 * @param string $status The status of the support tickets ('open', 'awaiting_reply', 'in_progress', 'closed')
	 * @param int $staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
	 * @param int $client_id The ID of the client assigned to the tickets (optional)
	 * @return int The total number of tickets in the given status
	 */
	public function getStatusCount($status, $staff_id = null, $client_id = null) {
		// Fetch all departments this staff belongs to
		$department_ids = array();
		if ($staff_id)
			$department_ids = $this->getStaffDepartments($staff_id);

		// Fetch tickets
		$this->Record->select(array("support_tickets.id"))->
			from("support_tickets")->
			innerJoin("support_departments", "support_departments.id", "=", "support_tickets.department_id", false)->
			where("support_departments.company_id", "=", Configure::get("Blesta.company_id"));

		// Filter by status
		switch ($status) {
			case "not_closed":
				$this->Record->where("support_tickets.status", "!=", "closed");
				break;
			default:
				$this->Record->where("support_tickets.status", "=", $status);
				break;
		}

		// Filter by tickets staff can view
		if ($staff_id) {
			// Staff must be assigned to the ticket or in the same department as the ticket
			$this->Record->open()->where("support_tickets.staff_id", "=", $staff_id);

			if (!empty($department_ids))
				$this->Record->orWhere("support_tickets.department_id", "in", $department_ids);

			$this->Record->close();
		}

		// Filter by tickets assigned to the client
		if ($client_id)
			$this->Record->where("support_tickets.client_id", "=", $client_id);

		return $this->Record->group("support_tickets.id")->numResults();
	}

	/**
	 * Retrieves a specific ticket
	 *
	 * @param int $ticket_id The ID of the ticket to fetch
	 * @param boolean $get_replies True to include the ticket replies, false not to
	 * @param array $reply_types A list of reply types to include (optional, default null for all)
	 * 	- "reply", "note", "log"
	 * @param int $staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
	 * @return mixed An stdClass object representing the ticket, or false if none exist
	 */
	public function get($ticket_id, $get_replies = true, array $reply_types = null, $staff_id = null) {
		// Get the ticket
		$ticket = $this->getTickets(null, $staff_id, null, $ticket_id)->fetch();

		if ($ticket && $get_replies)
			$ticket->replies = $this->getReplies($ticket->id, $reply_types);

		return $ticket;
	}

	/**
	 * Retrieves a specific ticket
	 *
	 * @param int $code The code of the ticket to fetch
	 * @param boolean $get_replies True to include the ticket replies, false not to
	 * @param array $reply_types A list of reply types to include (optional, default null for all)
	 * 	- "reply", "note", "log"
	 * @return mixed An stdClass object representing the ticket, or false if none exist
	 */
	public function getTicketByCode($code, $get_replies = true, array $reply_types = null) {
		// Get the ticket
		$ticket = $this->getTickets()->where("support_tickets.code", "=", $code)->fetch();

		if ($get_replies)
			$ticket->replies = $this->getReplies($ticket->id, $reply_types);

		return $ticket;
	}

	/**
	 * Converts the given file name into an appropriate file name to store to disk
	 *
	 * @param string $file_name The name of the file to rename
	 * @return string The rewritten file name in the format of YmdTHisO_[hash] (e.g. 20121009T154802+0000_1f3870be274f6c49b3e31a0c6728957f)
	 */
	public function makeFileName($file_name) {
		$ext = strrchr($file_name, ".");
		$file_name = md5($file_name . uniqid()) . $ext;

		return $this->dateToUtc(date("c"), "Ymd\THisO") . "_" . $file_name;
	}

	/**
	 * Retrieve a list of tickets
	 *
	 * @param string $status The status of the support tickets ('open', 'awaiting_reply', 'in_progress', 'closed', 'not_closed')
	 * @param int $staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
	 * @param int $client_id The ID of the client assigned to the tickets (optional)
	 * @param int $page The page number of results to fetch
	 * @param array $order_by A list of sort=>order options
	 * @param boolean $get_replies True to include the ticket replies, false not to
	 * @param array $reply_types A list of reply types to include (optional, default null for all)
	 * 	- "reply", "note", "log"
	 * @return array A list of stdClass objects representing tickets
	 */
	public function getList($status, $staff_id = null, $client_id = null, $page = 1, array $order_by = array('last_reply_date' => "desc"), $get_replies = true, array $reply_types = null) {
		$tickets = $this->getTickets($status, $staff_id, $client_id)->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();

		// Fetch ticket replies
		if ($get_replies) {
			foreach ($tickets as &$ticket)
				$ticket->replies = $this->getReplies($ticket->id, $reply_types);
		}

		return $tickets;
	}

	/**
	 * Retrieves the total number of tickets
	 *
	 * @param string $status The status of the support tickets ('open', 'awaiting_reply', 'in_progress', 'closed', 'not_closed')
	 * @param int $staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
	 * @param int $client_id The ID of the client assigned to the tickets (optional)
	 * @return int The total number of tickets
	 */
	public function getListCount($status, $staff_id = null, $client_id = null) {
		return $this->getTickets($status, $staff_id, $client_id)->numResults();
	}

	/**
	 * Search tickets
	 *
	 * @param string $query The value to search tickets for
	 * @param int $staff_id The ID of the staff member searching tickets (optional)
	 * @param int $page The page number of results to fetch (optional, default 1)
	 * @param array $order_by The sort=>$order options
	 * @return array An array of tickets that match the search criteria
	 */
	public function search($query, $staff_id = null, $page=1, $order_by = array('last_reply_date' => "desc")) {
		$this->Record = $this->searchTickets($query, $staff_id);
		return $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->
			fetchAll();
	}

	/**
	 * Seaches for tickets, specifically by ticket code of a given status
	 *
	 * @param string $query The value to search ticket codes for
	 * @param int $staff_id The ID of the staff member searching tickets (optional)
	 * @param mixed $status The status of tickets to search (optional, default null for all)
	 * @param int $page The page number of results to fetch (optional, default 1)
	 * @param array $order_by The sort=>$order options
	 * @return array An array of tickets that match the search criteria
	 */
	public function searchByCode($query, $staff_id = null, $status = null, $page = 1, $order_by = array('last_reply_date' => "desc")) {
		$this->Record = $this->getTickets($status, $staff_id);

		$this->Record->open()->
			like("support_tickets.code", "%" . $query . "%")->
			close();

		return $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->
			fetchAll();
	}

	/**
	 * Returns the total number of tickets returned from SupportManagerTickets::search(), useful
	 * in constructing pagination
	 *
	 * @param string $query The value to search tickets for
	 * @param int $staff_id The ID of the staff member searching tickets (optional)
	 * @see SupportManagerTickets::search()
	 */
	public function getSearchCount($query, $staff_id = null) {
		$this->Record = $this->searchTickets($query, $staff_id);
		return $this->Record->numResults();
	}

	/**
	 * Partially constructs the query for searching tickets
	 *
	 * @param string $query The value to search tickets for
	 * @param int $staff_id The ID of the staff member searching tickets
	 * @return Record The partially constructed query Record object
	 * @see SupportManagerTickets::search(), SupportManagerTickets::getSearchCount()
	 */
	private function searchTickets($query, $staff_id = null) {
		// Fetch the tickets
		$this->Record = $this->getTickets(null, $staff_id);

		$this->Record->open()->
				like("support_tickets.summary", "%" . $query . "%")->
				orLike("support_tickets.email", "%" . $query . "%")->
				orLike("support_tickets.code", "%" . $query . "%")->
			close();

		return $this->Record;
	}

	/**
	 * Retrieves a specific attachment
	 *
	 * @param int $attachment_id The ID of the attachment to fetch
	 * @return mixed An stdClass object representing the attachment, or false if none exist
	 */
	public function getAttachment($attachment_id) {
		$fields = array("support_attachments.*", "support_replies.ticket_id", "support_tickets.client_id", "support_tickets.department_id");
		return $this->Record->select($fields)->from("support_attachments")->
			innerJoin("support_replies", "support_replies.id", "=", "support_attachments.reply_id", false)->
			innerJoin("support_tickets", "support_tickets.id", "=", "support_replies.ticket_id", false)->
			where("support_attachments.id", "=", $attachment_id)->fetch();
	}

	/**
	 * Retrieves a list of attachments for a given ticket
	 *
	 * @param int $ticket_id The ID of the ticket to fetch attachments for
	 * @param int $reply_id The ID of the reply belonging to this ticket to fetch attachments for
	 * @return array A list of attachments
	 */
	public function getAttachments($ticket_id, $reply_id = null) {
		$fields = array("support_attachments.*");
		$this->Record->select($fields)->from("support_attachments")->
			innerJoin("support_replies", "support_replies.id", "=", "support_attachments.reply_id", false)->
			innerJoin("support_tickets", "support_tickets.id", "=", "support_replies.ticket_id", false)->
			where("support_tickets.id", "=", $ticket_id);

		// Fetch attachments only for a specific reply
		if ($reply_id)
			$this->Record->where("support_replies.id", "=", $reply_id);

		return $this->Record->order(array('support_replies.date_added' => "DESC"))->fetchAll();
	}

	/**
	 * Gets all replies to a specific ticket
	 *
	 * @param $ticket_id The ID of the ticket whose replies to fetch
	 * @param array $types A list of reply types to include (optional, default null for all)
	 * 	- "reply", "note", "log"
	 * @return array A list of replies to the given ticket
	 */
	private function getReplies($ticket_id, array $types = null) {
		$fields = array("support_replies.*",
			'IF(
				support_replies.staff_id IS NULL,
				IF(client_contacts.first_name IS NULL, contacts.first_name, client_contacts.first_name),
				staff.first_name
			)' => "first_name",
			'IF(
				support_replies.staff_id IS NULL,
				IF(client_contacts.last_name IS NULL, contacts.last_name, client_contacts.last_name),
				staff.last_name
			)' => "last_name",
			'IF(
				support_replies.staff_id IS NULL,
				IF(client_contacts.email IS NULL, contacts.email, client_contacts.email),
				staff.email
			)' => "email"
		);

		$this->Record->select($fields, false)->
			select(array('IF(support_replies.staff_id = ?, ?, IF(staff.id IS NULL, IF(support_tickets.email IS NULL, ?, ?), ?))' => "reply_by"), false)->
			appendValues(array($this->system_staff_id, "staff", "client", "email", "staff"))->
			from("support_replies")->
			innerJoin("support_tickets", "support_tickets.id", "=", "support_replies.ticket_id", false)->
			leftJoin("clients", "clients.id", "=", "support_tickets.client_id", false)->
				on("contacts.contact_type", "=", "primary")->
			leftJoin("contacts", "contacts.client_id", "=", "clients.id", false)->
				on("client_contacts.contact_type", "!=", "primary")->
			leftJoin(array('contacts' => "client_contacts"), "client_contacts.id", "=", "support_replies.contact_id", false)->
			leftJoin("staff", "staff.id", "=", "support_replies.staff_id", false)->
			where("support_tickets.id", "=", $ticket_id);

		// Filter by specific types given
		if ($types) {
			$i = 0;
			foreach ($types as $type) {
				if ($i++ == 0)
					$this->Record->open()->where("support_replies.type", "=", $type);
				else
					$this->Record->orWhere("support_replies.type", "=", $type);
			}

			if ($i > 0)
				$this->Record->close();
		}

		$replies = $this->Record->order(array('support_replies.date_added' => "DESC", 'support_replies.id' => "DESC"))->fetchAll();

		// Fetch attachments
		foreach ($replies as &$reply)
			$reply->attachments = $this->getAttachments($ticket_id, $reply->id);

		return $replies;
	}

	/**
	 * Returns a Record object for fetching tickets
	 *
	 * @param string $status The status of the support tickets ('open', 'awaiting_reply', 'in_progress', 'closed', 'not_closed')
	 * @param int $staff_id The ID of the staff member assigned to the tickets or associated departments (optional)
	 * @param int $client_id The ID of the client assigned to the tickets (optional)
	 * @param int $ticket_id The ID of a specific ticket to fetch
	 * @return Record A partially-constructed Record object for fetching tickets
	 */
	private function getTickets($status = null, $staff_id = null, $client_id = null, $ticket_id = null) {
		// Fetch all departments this staff belongs to
		$department_ids = array();
		if ($staff_id)
			$department_ids = $this->getStaffDepartments($staff_id);

		$sub_query = new Record();
		$sub_query->select(array("support_replies.ticket_id", 'MAX(support_replies.date_added)' => "reply_date"))->
			from("support_replies")->where("support_replies.type", "=", "reply")->
			group(array("support_replies.ticket_id"));
		$replies = $sub_query->get();
		$reply_values = $sub_query->values;
		$this->Record->reset();

		$fields = array("support_tickets.*", 'support_replies.date_added' => "last_reply_date", 'support_replies.staff_id' => "last_reply_staff_id",
			'support_departments.name' => "department_name", "support_departments.company_id", 'staff_assigned.first_name' => "assigned_staff_first_name",
            'staff_assigned.last_name' => "assigned_staff_last_name");
		$last_reply_fields = array(
			'IF(support_replies.staff_id IS NULL, IF(support_tickets.email IS NULL, ?, ?), ?)' => "last_reply_by",
			'IF(
				support_replies.staff_id IS NULL,
				IF(client_contacts.first_name IS NULL, contacts.first_name, client_contacts.first_name),
				staff.first_name
			)' => "last_reply_first_name",
			'IF(
				support_replies.staff_id IS NULL,
				IF(client_contacts.last_name IS NULL, contacts.last_name, client_contacts.last_name),
				staff.last_name
			)' => "last_reply_last_name",
			'IF(support_replies.staff_id IS NULL, IFNULL(support_tickets.email, ?), ?)' => "last_reply_email"
		);
		$last_reply_values = array(
			"client", "email", "staff",
			null, null
		);

		$this->Record->select($fields)->
			select($last_reply_fields, false)->appendValues($last_reply_values)->
			from("support_tickets")->
				on("support_replies.type", "=", "reply")->
			innerJoin("support_replies", "support_tickets.id", "=", "support_replies.ticket_id", false)->
				on("support_replies.date_added", "=", "replies.reply_date", false)->
			innerJoin(array($replies => "replies"), "replies.ticket_id", "=", "support_replies.ticket_id", false)->
			appendValues($reply_values)->
				on("support_departments.company_id", "=", Configure::get("Blesta.company_id"))->
			innerJoin("support_departments", "support_departments.id", "=", "support_tickets.department_id", false)->
			leftJoin("clients", "clients.id", "=", "support_tickets.client_id", false)->
				on("contacts.contact_type", "=", "primary")->
			leftJoin("contacts", "contacts.client_id", "=", "clients.id", false)->
				on("client_contacts.contact_type", "!=", "primary")->
			leftJoin(array('contacts' => "client_contacts"), "client_contacts.id", "=", "support_replies.contact_id", false)->
			leftJoin("staff", "staff.id", "=", "support_replies.staff_id", false)->
            leftJoin(array('staff' => "staff_assigned"), "staff_assigned.id", "=", "support_tickets.staff_id", false);

		// Filter by status
		if ($status) {
			switch ($status) {
				case "not_closed":
					$this->Record->where("support_tickets.status", "!=", "closed");
					break;
				default:
					$this->Record->where("support_tickets.status", "=", $status);
					break;
			}
		}

		// Filter by a single ticket
		if ($ticket_id)
			$this->Record->where("support_tickets.id", "=", $ticket_id);

		// Filter by tickets staff can view
		if ($staff_id) {
			// Staff must be assigned to the ticket or in the same department as the ticket
			$this->Record->open()->where("support_tickets.staff_id", "=", $staff_id);

			if (!empty($department_ids))
				$this->Record->orWhere("support_tickets.department_id", "in", $department_ids);

			$this->Record->close();
		}

		// Filter by tickets assigned to the client
		if ($client_id) {
			$this->Record->where("support_tickets.client_id", "=", $client_id);
		}
		
		$this->Record->group(array("support_tickets.id"));

		return $this->Record;
	}

	/**
	 * Retrieves a list of priorities and their language
	 *
	 * @return array A list of priority => language priorities
	 */
	public function getPriorities() {
		return array(
			'emergency' => $this->_("SupportManagerTickets.priority.emergency"),
			'critical' => $this->_("SupportManagerTickets.priority.critical"),
			'high' => $this->_("SupportManagerTickets.priority.high"),
			'medium' => $this->_("SupportManagerTickets.priority.medium"),
			'low' => $this->_("SupportManagerTickets.priority.low")
		);
	}

	/**
	 * Retrieves a list of statuses and their language
	 *
	 * @return array A list of status => language statuses
	 */
	public function getStatuses() {
		return array(
			'open' => $this->_("SupportManagerTickets.status.open"),
			'awaiting_reply' => $this->_("SupportManagerTickets.status.awaiting_reply"),
			'in_progress' => $this->_("SupportManagerTickets.status.in_progress"),
			'closed' => $this->_("SupportManagerTickets.status.closed")
		);
	}

	/**
	 * Retrieves a list of reply types and their language
	 *
	 * @return array A list of type => language reply types
	 */
	public function getReplyTypes() {
		return array(
			'reply' => $this->_("SupportManagerTickets.type.reply"),
			'note' => $this->_("SupportManagerTickets.type.note"),
			'log' => $this->_("SupportManagerTickets.type.log")
		);
	}

	/**
	 * Retrieves a list of department IDs for a given staff member
	 *
	 * @param int $staff_id The ID of the staff member whose departments to fetch
	 * @return array A list of department IDs that this staff member belongs to
	 */
	private function getStaffDepartments($staff_id) {
		// Fetch all departments this staff belongs to
		$departments = $this->Record->select(array("support_staff_departments.department_id"))->
			from("support_staff_departments")->
			where("support_staff_departments.staff_id", "=", $staff_id)->
			fetchAll();

		// Create a list of department IDs this staff belongs to
		$department_ids = array();
		foreach ($departments as $department)
			$department_ids[] = $department->department_id;

		return $department_ids;
	}

	/**
	 * Fetches the client for the given company using the given email address.
	 * Searches first the primary contact of each client, and if no results found
	 * then any contact for the clients in the given company. Returns the first
	 * client found.
	 *
	 * @param int $company_id The ID of the company to fetch a client for
	 * @param string $email The email address to fetch clients on
	 * @return mixed A stdClass object representing the client whose contact matches the email address, false if no client found
	 */
	public function getClientByEmail($company_id, $email) {
		// Fetch client based on primary contact email
		$client = $this->Record->select(array("clients.*"))->
			from("contacts")->
			innerJoin("clients", "clients.id", "=", "contacts.client_id", false)->
			innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
			where("client_groups.company_id", "=", $company_id)->
			where("contacts.email", "=", $email)->
			where("contacts.contact_type", "=", "primary")->fetch();

		// If no client found, fetch client based on any contact email
		if (!$client) {
			$client = $this->Record->select(array("clients.*"))->
				from("contacts")->
				innerJoin("clients", "clients.id", "=", "contacts.client_id", false)->
				innerJoin("client_groups", "client_groups.id", "=", "clients.client_group_id", false)->
				where("client_groups.company_id", "=", $company_id)->
				where("contacts.email", "=", $email)->fetch();
		}
		return $client;
	}

	/**
	 * Fetches a client's contact given the contact's email address
	 *
	 * @param int $client_id The ID of the client whose contact the email address is presumed to be from
	 * @param string $email The email address
	 * @return mixed An stdClass object representing the contact with the given email address, or false if none exist
	 */
	public function getContactByEmail($client_id, $email) {
		// Assume contact emails are unique per client, and only choose the first
		return $this->Record->select(array("contacts.*"))->
			from("contacts")->
			where("contacts.email", "=", $email)->
			where("contacts.client_id", "=", $client_id)->
			fetch();
	}

	/**
	 * Retrieves a list of all contact email addresses that have replied to the given ticket.
	 * This does not include the client's primary contact email.
	 *
	 * @param int $ticket_id The ID of the ticket whose contact emails to fetch
	 * @return array A numerically indexed array of email addresses of each contact that has replied to this ticket.
	 * 	May be an empty array if no contact, or only the primary client contact, has replied.
	 */
	public function getContactEmails($ticket_id) {
		// Fetch the email addresses of all contacts set on the ticket replies
		$emails = $this->Record->select(array("contacts.email"))->
			from("support_replies")->
			innerJoin("contacts", "contacts.id", "=", "support_replies.contact_id", false)->
			where("support_replies.ticket_id", "=", $ticket_id)->
			group(array("contacts.email"))->
			fetchAll();

		$contact_emails = array();
		foreach ($emails as $email) {
			$contact_emails[] = $email->email;
		}

		return $contact_emails;
	}

	/**
	 * Returns the ticket info if any exists
	 *
	 * @param string $body The body of the message
	 * @return mixed Null if no ticket info exists, an array otherwise containing:
	 * 	- ticket_code The ticket code number
	 * 	- code The validation code that can be used to verify the ticket number
	 * 	- valid Whether or not the code is valid for this ticket_code
	 */
	public function parseTicketInfo($str) {
		// Format of ticket number #NUM -CODE-
		// For example: #504928 -efa3-
		// Example in subject: Your Ticket #504928 -efa3- Has a New Comment
		preg_match("/\#([0-9]+) \-([a-f0-9]+)\-/i", $str, $matches);

		if (count($matches) < 3)
			return null;

		$ticket_code = isset($matches[1]) ? $matches[1] : null;
		$code = isset($matches[2]) ? $matches[2] : null;

		return array(
			'ticket_code' => $ticket_code,
			'code' => $code,
			'valid' => $this->validateReplyCode($ticket_code, $code)
		);
	}

	/**
	 * Generates a pseudo-random hash from an sha256 HMAC of the ticket ID
	 *
	 * @param int $ticket_id The ID of the ticket to generate the hash for
	 * @param mixed $key A key to include in the hash
	 * @return string A hexadecimal hash of the given length
	 */
	public function generateReplyHash($ticket_id, $key) {
		return $this->systemHash($ticket_id . $key);
	}

	/**
	 * Generates a pseudo-random reply code from an sha256 HMAC of the ticket ID code
	 *
	 * @param int $ticket_code The ticket code to generate the reply code from
	 * @param int $length The length of the reply code between 4 and 64 characters (optional, default 4)
	 * @return string A hexadecimal reply code of the given length
	 */
	public function generateReplyCode($ticket_code, $length = 4) {
		$hash = $this->systemHash($ticket_code);
		$hash_size = strlen($hash);

		if ($length < 4)
			$length = 4;
		elseif ($length > $hash_size)
			$length = $hash_size;

		return substr($hash, mt_rand(0, $hash_size-$length), $length);
	}

	/**
	 * Generates a pseudo-random reply code from an sha256 HMAC of the ticket ID code
	 * and concatenates it with the ticket ID
	 *
	 * @param int $ticket_code The ticket code to generate the reply code from
	 * @param int $length The length of the reply code between 4 and 64 characters
	 * @return string A formatted reply number (e.g. "#504928 -efa3-")
	 */
	public function generateReplyNumber($ticket_code, $length = 4) {
		// Format of ticket number #NUM -CODE-
		// For example: #504928 -efa3-

		$code = $this->generateReplyCode($ticket_code, $length);
		return "#" . $ticket_code . " -" . $code . "-";
	}

	/**
	 * Sends ticket updated/received emails
	 *
	 * @param int $reply_id The ID of the ticket reply that the email is to use
	 * @param array $additional_tags A key=>value list of the email_action=>tags array to send
	 * 	e.g. array('SupportManager.ticket_received' => array('tag' => "value"))
	 */
	public function sendEmail($reply_id, $additional_tags = array()) {
		// Fetch the associated ticket
		$fields = array("support_tickets.*", 'support_replies.id' => "reply_id",
			'support_replies.staff_id' => "reply_staff_id",
			'support_replies.contact_id' => "reply_contact_id",
			'support_replies.type' => "reply_type", "support_replies.details",
			'support_replies.date_added' => "reply_date_added",
			'support_departments.id' => "department_id", 'support_departments.company_id' => "company_id",
			'support_departments.name' => "department_name", 'support_departments.email' => "department_email",
			"support_departments.override_from_email");
		$ticket = $this->Record->select($fields)->
			select(array('IF(support_replies.staff_id = ?, ?, IF(staff.id IS NULL, IF(support_tickets.email IS NULL, ?, ?), ?))' => "reply_by"), false)->
			appendValues(array($this->system_staff_id, "staff", "client", "email", "staff"))->
			from("support_replies")->
			innerJoin("support_tickets", "support_tickets.id", "=", "support_replies.ticket_id", false)->
			innerJoin("support_departments", "support_departments.id", "=", "support_tickets.department_id", false)->
			leftJoin("clients", "clients.id", "=", "support_tickets.client_id", false)->
				on("contacts.contact_type", "=", "primary")->
			leftJoin("contacts", "contacts.client_id", "=", "clients.id", false)->
			leftJoin("staff", "staff.id", "=", "support_replies.staff_id", false)->
			where("support_replies.id", "=", $reply_id)->
			fetch();

		// Only send email if the ticket is a reply type
		if ($ticket && $ticket->reply_type == "reply") {
			// Determine whether this is the only reply or not
			$total_replies = $this->Record->select(array("support_replies.id"))->from("support_tickets")->
				innerJoin("support_replies", "support_replies.ticket_id", "=", "support_tickets.id", false)->
				where("support_tickets.id", "=", $ticket->id)->
				numResults();

			// Determine whether this ticket has any attachments
			$num_attachments = $this->Record->select(array("support_attachments.*"))->from("support_tickets")->
				innerJoin("support_replies", "support_replies.ticket_id", "=", "support_tickets.id", false)->
				innerJoin("support_attachments", "support_attachments.reply_id", "=", "support_replies.id", false)->
				where("support_tickets.id", "=", $ticket->id)->numResults();
			$ticket->has_attachments = ($num_attachments > 0);

			// Check if this specific reply has any attachments
			$ticket->reply_has_attachments = false;
			if ($num_attachments > 0) {
				$num_reply_attachments = $this->Record->select()->from("support_attachments")->where("reply_id", "=", $reply_id)->numResults();
				$ticket->reply_has_attachments = ($num_reply_attachments > 0);
			}

			// Set status/priority language
			$priorities = $this->getPriorities();
			$statuses = $this->getStatuses();
			$ticket->priority_language = $priorities[$ticket->priority];
			$ticket->status_language = $statuses[$ticket->status];

			// Parse details into HTML for HTML templates
			Loader::loadHelpers($this, array("TextParser"));
			$ticket->details_html = $this->TextParser->encode("markdown", $ticket->details);

			// Send the ticket emails
			$this->sendTicketEmail($ticket, ($total_replies == 1), $additional_tags);
		}
	}

	/**
	 * Sends a notice to the ticket's assigned staff member to notify them that the ticket has been assigned to them
	 *
	 * @param int $ticket_id The ID of the ticket that a staff member has been assigned
	 */
	public function sendTicketAssignedEmail($ticket_id) {
		Loader::loadModels($this, array("Emails", "Staff"));

		// Notify the assigned staff in regards to this ticket
		if (($ticket = $this->get($ticket_id, false)) && !empty($ticket->staff_id) && ($staff = $this->Staff->get($ticket->staff_id)) && $staff->email) {
			// Set status/priority language
			$priorities = $this->getPriorities();
			$statuses = $this->getStatuses();
			$ticket->priority_language = $priorities[$ticket->priority];
			$ticket->status_language = $statuses[$ticket->status];

			$tags = array('ticket' => $ticket, 'staff' => $staff);
			$email_action = "SupportManager.staff_ticket_assigned";

			$this->Emails->send($email_action, $ticket->company_id, null, $staff->email, $tags);
		}
	}

	/**
	 * Sends ticket emails
	 *
	 * @param stdClass $ticket An stdClass object representing the ticket
	 * @param boolean $new_ticket True if this is the first ticket reply, false if it is a reply to an existing ticket
	 * @param array $additional_tags A key=>value list of the email_action=>tags array to send
	 * 	e.g. array('SupportManager.ticket_received' => array('tag' => "value"))
	 */
	private function sendTicketEmail($ticket, $new_ticket, $additional_tags = array()) {
		switch ($ticket->reply_by) {
			case "staff":
				$this->sendTicketByStaffEmail($ticket, $additional_tags);
				break;
			case "email":
			case "client":
				$this->sendTicketByClientEmail($ticket, $new_ticket, $additional_tags);
				break;
			default:
				break;
		}
	}

	/**
	 * Sends a ticket received notice to a client for a new ticket
	 *
	 * @param stdClass $ticket An stdClass object representing the ticket
	 * @param array $additional_tags A key=>value list of the email_action=>tags array to send
	 * 	e.g. array('SupportManager.ticket_received' => array('tag' => "value"))
	 */
	private function sendTicketReceived($ticket, $additional_tags = array()) {
		Loader::loadModels($this, array("Clients", "Contacts", "Emails"));

		// Set options for the email
		$options = array(
			'to_client_id' => $ticket->client_id,
			'from_staff_id' => null,
			'reply_to' => $ticket->department_email
		);

		$to_email = $ticket->email;
		$cc_email = array();
		if ($ticket->client_id > 0) {
			$client = $this->Clients->get($ticket->client_id);
			if ($client) {
				$to_email = $client->email;

				// If the ticket was created by a contact, CC the contact
				if ($ticket->reply_contact_id && ($contact = $this->Contacts->get($ticket->reply_contact_id))) {
					$cc_email[] = $contact->email;
				}
			}
		}
		$language = (isset($client->settings['language']) ? $client->settings['language'] : null);

		$email_action = "SupportManager.ticket_received";

		// Set the tags
		$other_tags = (isset($additional_tags[$email_action]) ? $additional_tags[$email_action] : array());
		$tags = array_merge(array('ticket' => $ticket, 'ticket_hash_code' => $this->generateReplyNumber($ticket->code, 4)), $other_tags);
		$this->Emails->send($email_action, $ticket->company_id, $language, $to_email, $tags, $cc_email, null, null, $options);
	}

	/**
	 * Sends the ticket updated email to staff regarding a ticket created/updated by a client.
	 * In the case $new_ticket is true, a ticket received notice is also sent to the client.
	 *
	 * @param stdClass $ticket An stdClass object representing the ticket
	 * @param boolean $new_ticket True if this is the first ticket reply, false if it is a reply to an existing ticket
	 * @param array $additional_tags A key=>value list of the email_action=>tags array to send
	 * 	e.g. array('SupportManager.ticket_received' => array('tag' => "value"))
	 */
	private function sendTicketByClientEmail($ticket, $new_ticket, $additional_tags = array()) {
		Loader::loadModels($this, array("Emails", "SupportManager.SupportManagerStaff"));

		// Send the ticket received notification to the client
		if ($new_ticket) {
			$this->sendTicketReceived($ticket, $additional_tags);
		}

		// Set the date/time that each staff member must be available to receive notices
		$day = strtolower($this->dateToUtc($ticket->reply_date_added . "Z", "D"));
		$time = $this->dateToUtc($ticket->reply_date_added . "Z", "H:i:s");

		// Fetch all staff available to receive notifications at this time
		$staff = $this->SupportManagerStaff->getAllAvailable($ticket->company_id, $ticket->department_id, array($day => $time));

		$to_addresses = array();
		$to_mobile_addresses = array();

		// Check each staff member is set to receive the notice
		foreach ($staff as $member) {
			// Determine whether this staff is set to receive the ticket email
			if (isset($member->settings['ticket_emails']) && is_array($member->settings['ticket_emails'])) {
				foreach ($member->settings['ticket_emails'] as $priority => $enabled) {
					if ($enabled == "true" && $ticket->priority == $priority) {
						$to_addresses[] = $member->email;
						break;
					}
				}
			}

			// Determine whether this staff is set to receive the ticket mobile email
			if (!empty($member->email_mobile) && isset($member->settings['mobile_ticket_emails']) && is_array($member->settings['mobile_ticket_emails'])) {
				foreach ($member->settings['mobile_ticket_emails'] as $priority => $enabled) {
					if ($enabled == "true" && $ticket->priority == $priority) {
						$to_mobile_addresses[] = $member->email_mobile;
						break;
					}
				}
			}
		}

		$options = array(
			'to_client_id' => null,
			'from_staff_id' => null,
			'reply_to' => $ticket->department_email
		);

		// Set the template from address to the departments'
		if (property_exists($ticket, "override_from_email") && $ticket->override_from_email == 1)
			$options['from'] = $ticket->department_email;

		// Set the tags
		$ticket_hash_code = $this->generateReplyNumber($ticket->code, 6);
		$email_action = "SupportManager.staff_ticket_updated";
		$other_tags = (isset($additional_tags[$email_action]) ? $additional_tags[$email_action] : array());
		$tags = array_merge(array('ticket' => $ticket, 'ticket_hash_code' => $ticket_hash_code), $other_tags);

		// Send the staff ticket updated emails
		foreach ($to_addresses as $key => $address)
			$this->Emails->send($email_action, $ticket->company_id, null, $address, $tags, null, null, null, $options);

		// Set the tags
		$email_action = "SupportManager.staff_ticket_updated_mobile";
		$other_tags = (isset($additional_tags[$email_action]) ? $additional_tags[$email_action] : array());
		$tags = array_merge(array('ticket' => $ticket, 'ticket_hash_code' => $ticket_hash_code), $other_tags);

		// Send the staff ticket updated mobile emails
		foreach ($to_mobile_addresses as $key => $address)
			$this->Emails->send($email_action, $ticket->company_id, null, $address, $tags, null, null, null, $options);
	}

	/**
	 * Sends the ticket email to a client regarding a ticket created/updated by a staff member
	 *
	 * @param stdClass $ticket An stdClass object representing the ticket
	 * @param array $additional_tags A key=>value list of the email_action=>tags array to send
	 * 	e.g. array('SupportManager.ticket_received' => array('tag' => "value"))
	 */
	private function sendTicketByStaffEmail($ticket, $additional_tags = array()) {
		Loader::loadModels($this, array("Clients", "Contacts", "Emails"));

		// Fetch client to set email language
		$to_email = $ticket->email;
		$cc_email = array();
		if ($ticket->client_id > 0) {
			$client = $this->Clients->get($ticket->client_id);
			if ($client) {
				$to_email = $client->email;

				// CC all contacts that have replied to the ticket
				$cc_email = $this->getContactEmails($ticket->id);
			}
		}
		$language = (isset($client->settings['language']) ? $client->settings['language'] : null);

		$email_action = "SupportManager.ticket_updated";

		// Send the email to the client
		$other_tags = (isset($additional_tags[$email_action]) ? $additional_tags[$email_action] : array());
		$tags = array_merge(array('ticket' => $ticket, 'ticket_hash_code' => $this->generateReplyNumber($ticket->code, 4)), $other_tags);
		$options = array(
			'to_client_id' => $ticket->client_id,
			'from_staff_id' => null,
			'reply_to' => $ticket->department_email
		);

		// Set the template from address to the departments'
		if (property_exists($ticket, "override_from_email") && $ticket->override_from_email == 1)
			$options['from'] = $ticket->department_email;

		$this->Emails->send($email_action, $ticket->company_id, $language, $to_email, $tags, $cc_email, null, null, $options);
	}

	/**
	 * Checks whether a particular email address has received more than $count emails
	 * in the last $time_limit seconds
	 *
	 * @param string $email The email address to check
	 * @param int $count The maximum number of allowed emails within the time limit
	 * @param string $time_limit The time length in the past (e.g. "5 minutes")
	 * @return boolean True if the email has received <= $count emails since $time_limit, false otherwise
	 */
	public function checkLoopBack($email, $count, $time_limit) {
		// Fetch the number of emails sent to the email address recently
		$past_date = $this->dateToUtc(strtotime(date("c") . " -" . $time_limit));
		$emails_sent = $this->Record->select()->from("log_emails")->
			where("from_address", "=", $email)->
			where("date_sent", ">=", $past_date)->
			numResults();

		if ($emails_sent <= $count)
			return true;
		return false;
	}

	/**
	 * Validates that the given reply code is correct for the ticket ID code
	 *
	 * @param int $ticket_code The ticket code to validate the reply code for
	 * @return boolean True if the reply code is valid, false otherwise
	 */
	public function validateReplyCode($ticket_code, $code) {
		$hash = $this->systemHash($ticket_code);
		return strpos($hash, $code) !== false;
	}

	/**
	 * Retrieves a list of rules for adding/editing support ticket replies
	 *
	 * @param array $vars A list of input vars
	 * @param boolean $new_ticket True to get the rules if this ticket is in the process of being created, false otherwise (optional, default false)
	 * @return array A list of ticket reply rules
	 */
	private function getReplyRules(array $vars, $new_ticket = false) {
		$rules = array(
			'staff_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStaffExists")),
					'message' => $this->_("SupportManagerTickets.!error.staff_id.exists")
				)
			),
			'contact_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "contacts"),
					'message' => $this->_("SupportManagerTickets.!error.contact_id.exists")
				),
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateClientContact"), $this->ifSet($vars['ticket_id']), $this->ifset($vars['client_id'])),
					'message' => $this->_("SupportManagerTickets.!error.contact_id.valid")
				)
			),
			'type' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("in_array", array_keys($this->getReplyTypes())),
					'message' => $this->_("SupportManagerTickets.!error.type.format")
				)
			),
			'details' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("SupportManagerTickets.!error.details.empty")
				)
			),
			'date_added' => array(
				'format' => array(
					'rule' => true,
					'message' => "",
					'post_format' => array(array($this, "dateToUtc"))
				)
			)
		);

		if ($new_ticket) {
			// The reply type must be 'reply' on a new ticket
			$rules['type']['new_valid'] = array(
				'if_set' => true,
				'rule' => array("compares", "==", "reply"),
				'message' => $this->_("SupportManagerTickets.!error.type.new_valid")
			);
		}
		else {
			// Validate ticket exists
			$rules['ticket_id'] = array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "support_tickets"),
					'message' => $this->_("SupportManagerTickets.!error.ticket_id.exists")
				)
			);
			// Validate client can reply to this ticket
			$rules['client_id'] = array(
				'attached_to' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateClientTicket"), $this->ifSet($vars['ticket_id'])),
					'message' => $this->_("SupportManagerTickets.!error.client_id.attached_to")
				)
			);
		}

		return $rules;
	}

	/**
	 * Retrieves a list of rules for adding/editing support tickets
	 *
	 * @param array $vars A list of input vars
	 * @param boolean $edit True to get the edit rules, false for the add rules (optional, default false)
	 * @param boolean $require_email True to require the email field be given, false otherwise (optional, default false)
	 * @return array A list of support ticket rules
	 */
	private function getRules(array $vars, $edit = false, $require_email = false) {
		$rules = array(
			'code' => array(
				'format' => array(
					'rule' => array("matches", "/^[0-9]+$/"),
					'message' => $this->_("SupportManagerTickets.!error.code.format")
				)
			),
			'department_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "support_departments"),
					'message' => $this->_("SupportManagerTickets.!error.department_id.exists")
				)
			),
			'staff_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStaffExists")),
					'message' => $this->_("SupportManagerTickets.!error.staff_id.exists")
				)
			),
			'service_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "services"),
					'message' => $this->_("SupportManagerTickets.!error.service_id.exists")
				),
				'belongs' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateClientService"), $this->ifSet($vars['client_id'])),
					'message' => $this->_("SupportManagerTickets.!error.service_id.belongs")
				)
			),
			'client_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "clients"),
					'message' => $this->_("SupportManagerTickets.!error.client_id.exists")
				)
			),
			'email' => array(
				'format' => array(
					'rule' => array(array($this, "validateEmail"), $require_email),
					'message' => $this->_("SupportManagerTickets.!error.email.format")
				)
			),
			'summary' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("SupportManagerTickets.!error.summary.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 255),
					'message' => $this->_("SupportManagerTickets.!error.summary.length")
				)
			),
			'priority' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("in_array", array_keys($this->getPriorities())),
					'message' => $this->_("SupportManagerTickets.!error.priority.format")
				)
			),
			'status' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("in_array", array_keys($this->getStatuses())),
					'message' => $this->_("SupportManagerTickets.!error.status.format")
				)
			),
			'date_added' => array(
				'format' => array(
					'rule' => true,
					'message' => "",
					'post_format' => array(array($this, "dateToUtc"))
				)
			),
            'by_staff_id' => array(
                'exists' => array(
                    'if_set' => true,
					'rule' => array(array($this, "validateStaffExists")),
					'message' => $this->_("SupportManagerTickets.!error.by_staff_id.exists")
                )
            )
		);

		if ($edit) {
			// Remove unnecessary rules
			unset($rules['date_added']);

			// Require that a client ID not be set
			$rules['client_id']['set'] = array(
				'rule' => array(array($this, "validateTicketUnassigned"), $this->ifSet($vars['ticket_id'])),
				'message' => Language::_("SupportManagerTickets.!error.client_id.set", true)
			);

			// Set edit-specific rules
			$rules['date_closed'] = array(
				'format' => array(
					'rule' => array(array($this, "validateDateClosed")),
					'message' => $this->_("SupportManagerTickets.!error.date_closed.format"),
					'post_format' => array(array($this, "dateToUtc"))
				)
			);

			// Set all rules to optional
			$rules = $this->setRulesIfSet($rules);

			// Require a ticket be given
			$rules['ticket_id'] = array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "support_tickets"),
					'message' => $this->_("SupportManagerTickets.!error.ticket_id.exists")
				)
			);
		}

		return $rules;
	}

	/**
	 * Validates whether the given client can reply to the given ticket
	 *
	 * @param int $client_id The ID of the client
	 * @param int $ticket_id The ID of the ticket
	 * @return boolean True if the client can reply to the ticket, false otherwise
	 */
	public function validateClientTicket($client_id, $ticket_id) {
		// Ensure this client is assigned this ticket
		$results = $this->Record->select("id")->from("support_tickets")->
			where("id", "=", $ticket_id)->where("client_id", "=", $client_id)->
			numResults();

		return ($results > 0);
	}

	/**
	 * Validates whether the given contact can reply to the given ticket for the ticket's client
	 *
	 * @param int $contact_id The ID of the contact
	 * @param int $ticket_id The ID of the ticket
	 * @param int $client_id The ID of the client assigned to the ticket if the ticket is not known (optional, default null)
	 * @return boolean True if the contact can reply to the ticket, false otherwise
	 */
	public function validateClientContact($contact_id, $ticket_id, $client_id = null) {
		// Contact does not need to be set
		if ($contact_id === null)
			return true;

		$ticket = $this->get($ticket_id, false);

		// In case a ticket is not yet known (e.g. in the process of being created), compare with the given client
		$client_id = ($ticket && $ticket->client_id ? $ticket->client_id : $client_id);

		if ($client_id !== null) {
			// The ticket and the contact must belong to a client
			$found = $this->Record->select()->from("contacts")->
				where("id", "=", $contact_id)->
				where("client_id", "=", $client_id)->
				numResults();

			if ($found)
				return true;
		}
		return false;
	}

	/**
	 * Validates that the given client can be assigned to the given ticket
	 *
	 * @param int $client_id The ID of the client to assign to the ticket
	 * @param int $ticket_id The ID of the ticket
	 * @return boolean True if the client may be assigned to the ticket, false otherwise
	 */
	public function validateTicketUnassigned($client_id, $ticket_id) {
		// Fetch the ticket
		$ticket = $this->get($ticket_id, false);

		// No ticket found, ignore this error
		if (!$ticket)
			return true;

		// Ticket may have either no client, or this client
		if ($ticket->client_id === null || $ticket->client_id == $client_id) {
			// Client must also be in the same company as the ticket
			$count = $this->Record->select(array("client_groups.id"))->
				from("client_groups")->
				innerJoin("clients", "clients.client_group_id", "=", "client_groups.id", false)->
				where("clients.id", "=", $client_id)->
				where("client_groups.company_id", "=", $ticket->company_id)->
				numResults();

			if ($count > 0)
				return true;
		}
		return false;
	}

	/**
	 * Validates that the given staff ID exists when adding/editing tickets
	 *
	 * @param int $staff_id The ID of the staff member
	 * @return boolean True if the staff ID exists, false otherwise
	 */
	public function validateStaffExists($staff_id) {
		if ($staff_id == "" || $staff_id == $this->system_staff_id || $this->validateExists($staff_id, "id", "staff", false))
			return true;
		return false;
	}

	/**
	 * Validates that the given service ID is assigned to the given client ID
	 *
	 * @param int $service_id The ID of the service
	 * @param int $client_id The ID of the client
	 * @return boolean True if the service ID belongs to the client ID, false otherwise
	 */
	public function validateClientService($service_id, $client_id) {
		$count = $this->Record->select()->from("services")->
			where("id", "=", $service_id)->
			where("client_id", "=", $client_id)->
			numResults();

		return ($count > 0);
	}

	/**
	 * Validates the email address given for support tickets
	 *
	 * @param string $email The support ticket email address
	 * @param boolean $require_email True to require the email field be given, false otherwise (optional, default false)
	 * @return boolean True if the email address is valid, false otherwise
	 */
	public function validateEmail($email, $require_email = false) {
		return (empty($email) && !$require_email ? true : $this->Input->isEmail($email));
	}

	/**
	 * Validates the date closed for support tickets
	 *
	 * @param string $date_closed The date a ticket is closed
	 * @return boolean True if the date is in a valid format, false otherwise
	 */
	public function validateDateClosed($date_closed) {
		return (empty($date_closed) ? true : $this->Input->isDate($date_closed));
	}

	/**
	 * Validates that the given replies belong to the given ticket and that they are of the reply/note type.
	 *
	 * @param array $replies A list of IDs representing ticket replies
	 * @param int $ticket_id The ID of the ticket to which the replies belong
	 * @param boolean $all False to require that at least 1 ticket reply not be given for this ticket, or true to allow all (optional, default false)
	 * @return boolean True if all of the given replies are valid; false otherwise
	 */
	public function validateReplies(array $replies, $ticket_id, $all = false) {
		// Must have at least one reply ID
		if (empty($replies) || !($ticket = $this->get($ticket_id)))
			return false;

		// Fetch replies that are valid
		$valid_replies = $this->getValidTicketReplies($ticket_id);
		$num_notes = 0;
		$num_replies = 0;

		// Count the number of ticket notes and replies
		foreach ($valid_replies as $reply) {
			if ($reply->type == "note")
				$num_notes++;
			else
				$num_replies++;
		}

		// Check that all replies given are valid replies
		foreach ($replies as $reply_id) {
			if (!array_key_exists($reply_id, $valid_replies))
				return false;

			// Decrement the number of notes/replies that would be available to the ticket
			if ($valid_replies[$reply_id]->type == "note")
				$num_notes--;
			else
				$num_replies--;
		}

		// At least one reply must be left remaining
		if (!$all && $num_replies <= 0)
			return false;

		// There must be valid replies
		return !empty($valid_replies);
	}

	/**
	 * Validates that the given replies belong to the given ticket, that they are of the reply/note type, and that they
	 * are not all only note types.
	 * i.e. In addition to replies of the 'note' type, at least one 'reply' type must be included
	 *
	 * @param array $replies A list of IDs representing ticket replies
	 * @param int $ticket_id The ID of the ticket to which the replies belong
	 * @return boolean True if no replies are given, or at least one is of the 'reply' type; false otherwise
	 */
	public function validateSplitReplies(array $replies, $ticket_id) {
		// No replies, nothing to validate
		if (empty($replies))
			return true;

		// Fetch the ticket replies
		$valid_replies = $this->getValidTicketReplies($ticket_id);

		foreach ($replies as $reply_id) {
			// At least one ticket reply must be of the 'reply' type
			if (array_key_exists($reply_id, $valid_replies) && $valid_replies[$reply_id]->type == "reply")
				return true;
		}

		return false;
	}

	/**
	 * Retrieves a list of ticket replies of the "reply" and "note" type belonging to the given ticket
	 *
	 * @param int $ticket_id The ID of the ticket
	 * @return array An array of stdClass objects representing each reply, keyed by the reply ID
	 */
	private function getValidTicketReplies($ticket_id) {
		$valid_replies = array();

		if (($ticket = $this->get($ticket_id))) {
			foreach ($ticket->replies as $reply) {
				if (in_array($reply->type, array("reply", "note")))
					$valid_replies[$reply->id] = $reply;
			}
		}

		return $valid_replies;
	}

    /**
     * Validates that the given open tickets can be merged into the given ticket
     *
     * @param array $tickets A list of ticket IDs
     * @param int $ticket_id The ID of the ticket the tickets are to be merged into
     * @return boolean True if all of the given tickets can be merged into the ticket, or false otherwise
     */
    public function validateTicketsMergeable(array $tickets, $ticket_id) {
        // Fetch the ticket
        $ticket = $this->get($ticket_id, false);
        if (!$ticket || $ticket->status == "closed")
            return false;

        // Check whether every ticket belongs to the same client (or email address), belongs to the same company, and are open
        foreach ($tickets as $old_ticket_id) {
            // Fetch the ticket
            $old_ticket = $this->get($old_ticket_id, false);
            if (!$old_ticket)
                return false;

            // Check company matches, client matches, and ticket is open
            if (($old_ticket->company_id != $ticket->company_id) || ($old_ticket->status == "closed") ||
                ($old_ticket->client_id != $ticket->client_id || $old_ticket->email != $ticket->email))
                return false;
        }

        return true;
    }

    /**
     * Validates that all of the given tickets can be updated to the associated service
     *
     * @param array $vars An array consisting of arrays of ticket vars whose index refers to the index of the $ticket_ids array representing the vars of the specific ticket to update; or an array of vars to apply to all tickets; each including:
	 * 	- service_id The ID of the client service this ticket relates to
	 * @param array $ticket_ids An array of ticket IDs to update
	 * @return boolean True if the service(s) match the tickets, or false otherwise
     */
    public function validateServicesMatchTickets(array $vars, array $ticket_ids) {
        // Determine whether to apply vars to all tickets, or whether each ticket has separate vars
        $separate_vars = (isset($vars[0]) && is_array($vars[0]));

        // Check whether the tickets can be assigned to the given service(s)
        foreach ($ticket_ids as $key => $ticket_id) {
            // Each ticket has separate vars specific to that ticket
            $temp_vars = $vars;
            if ($separate_vars) {
                // Since all fields are optional, we don't need to require a service_id be given
                if (!isset($vars[$key]) || empty($vars[$key]))
                    $vars[$key] = array();

                $temp_vars = $vars[$key];
            }

            // Check whether the client has this service
            if (isset($temp_vars['service_id'])) {
                // Fetch the ticket
                $ticket = $this->get($ticket_id, false);
                if ($ticket && !empty($ticket->client_id)) {
                    // Check whether the client has the service
                    $services = $this->Record->select(array("id"))->from("services")->where("client_id", "=", $ticket->client_id)->fetchAll();
                    $temp_services = array();
                    foreach ($services as $service)
                        $temp_services[] = $service->id;

                    if (!in_array($temp_vars['service_id'], $temp_services))
                        return false;
                }
                else
                    return false;
            }
        }

        return true;
    }

    /**
     * Validates that all of the given tickets can be updated to the associated department
     *
     * @param array $vars An array consisting of arrays of ticket vars whose index refers to the index of the $ticket_ids array representing the vars of the specific ticket to update; or an array of vars to apply to all tickets; each including:
	 * 	- department_id The department to reassign the ticket to
	 * @param array $ticket_ids An array of ticket IDs to update
	 * @return boolean True if the department(s) match the tickets, or false otherwise
     */
    public function validateDepartmentsMatchTickets(array $vars, array $ticket_ids) {
        // Determine whether to apply vars to all tickets, or whether each ticket has separate vars
        $separate_vars = (isset($vars[0]) && is_array($vars[0]));

        // Check whether the tickets can be assigned to the given service(s)
        foreach ($ticket_ids as $key => $ticket_id) {
            // Each ticket has separate vars specific to that ticket
            $temp_vars = $vars;
            if ($separate_vars) {
                // Since all fields are optional, we don't need to require a department_id be given
                if (!isset($vars[$key]) || empty($vars[$key]))
                    $vars[$key] = array();

                $temp_vars = $vars[$key];
            }

            if (isset($temp_vars['department_id'])) {
                // Fetch the ticket
                $ticket = $this->get($ticket_id, false);
                if ($ticket) {
                    // Fetch the department company of this ticket
                    $department = $this->Record->select(array("company_id"))->from("support_departments")->where("id", "=", $ticket->department_id)->fetch();

                    // Ensure the new department is in the same company as the ticket's department
                    $same_company = $this->Record->select()->from("support_departments")->
                        where("id", "=", $temp_vars['department_id'])->
                        where("company_id", "=", ($department ? $department->company_id : ""))->
                        fetch();

                    if (!$same_company)
                        return false;
                }
            }
        }

        return true;
    }

	/**
	 * Generates a ticket number
	 *
	 * @return int A ticket number
	 */
	private function generateCode() {
		// Determine the number of digits to contain in the ticket number
		$digits = (int)Configure::get("SupportManager.ticket_code_length");
		$min = str_pad("1", $digits, "1");
		$max = str_pad("9", $digits, "9");

		// Attempt to generate a ticket code without duplicates 3 times
		// and accepts the third ticket code regardless of duplication
		$attempts = 0;
		$ticket_code = "";
		while ($attempts++ < 3) {
			$ticket_code = mt_rand($min, $max);

			// Skip if this ticket already exists
			if ($this->validateExists($ticket_code, "code", "support_tickets"))
				continue;
			return $ticket_code;
		}
		return $ticket_code;
	}
}
?>