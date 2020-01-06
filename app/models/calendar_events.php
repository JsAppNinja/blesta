<?php
/**
 * CalendarEvents
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class CalendarEvents extends AppModel {
	
	/**
	 * Initialize the CalendarEvents
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("calendar_events"));
	}
	
	
	/**
	 * Adds a calendar event
	 *
	 * @param array $vars An array of calendar event data including:
	 * 	-company_id The ID of the company to add this event under
	 * 	-staff_id The ID of the staff member that created this event
	 * 	-shared Whether or not this event is shared among other staff members of this company (true to share false otherwise, default false)
	 * 	-title The title of the event
	 * 	-url The URL to link this event to (optional, default null)
	 * 	-start_date The start date of the event
	 * 	-end_date The end date of the event
	 * 	-all_day Whether or not this event spans the entire day of the start/end dates (default false)
	 * @return int The ID of the calendar event created, void on error
	 */
	public function add(array $vars) {
		$this->Input->setRules($this->getRules($vars));
		
		if ($this->Input->validates($vars)) {
			$fields = array("company_id", "staff_id", "shared", "title", "url", "start_date", "end_date", "all_day");
			$this->Record->insert("calendar_events", $vars, $fields);
			return $this->Record->lastInsertId();
		}
	}

	/**
	 * Updates a calendar event
	 *
	 * @param int $calendar_event_id The ID of the calendar event to update
	 * @param array $vars An array of calendar event data including (only parameters submitted will be updated):
	 * 	-company_id The ID of the company to add this event under
	 * 	-staff_id The ID of the staff member that created this event (required)
	 * 	-shared Whether or not this event is shared among other staff members of this company (true to share false otherwise)
	 * 	-title The title of the event
	 * 	-url The URL to link this event to
	 * 	-start_date The start date of the event
	 * 	-end_date The end date of the event
	 * 	-all_day Whether or not this event spans the entire day of the start/end dates
	 */	
	public function edit($calendar_event_id, array $vars) {
		$vars['calendar_event_id'] = $calendar_event_id;
		$this->Input->setRules($this->getRules($vars, true));
		
		if ($this->Input->validates($vars)) {
			$fields = array("company_id", "staff_id", "shared", "title", "url", "start_date", "end_date", "all_day");
			$this->Record->where("id", "=", $vars['calendar_event_id'])->update("calendar_events", $vars, $fields);
		}
	}
	
	/**
	 * Deletes a calendar event
	 *
	 * @param int $calendar_event_id The ID of the calendar event to delete
	 */
	public function delete($calendar_event_id) {
		$this->Record->from("calendar_events")->where("id", "=", $calendar_event_id)->delete();
	}
	
	/**
	 * Fetches a calendar event
	 *
	 * @param int $calendar_event_id The ID of the calendar event to fetch
	 * @return mixed A stdClass object representing the calendar event, false if no such event exists
	 */
	public function get($calendar_event_id) {
		$this->Record = $this->getEvents();
		return $this->Record->where("calendar_events.id", "=", $calendar_event_id)->fetch();
	}
	
	/**
	 * Fetches all of events that begin between the given start and end dates
	 *
	 * @param int $company_id The ID of the company to fetch for
	 * @param int $staff_id The ID of the staff member to fetch for
	 * @param string $start_date Defines the lower bound for event start dates
	 * @param string $end_date Defines the upper bound for event start dates
	 * @param boolean $include_shared If true will include shared calendar events, false will only include events for this staff member
	 * @return array An array of stdClass objects, each representing a calendar event
	 * @see CalendarEvents::getRange()
	 */
	public function getAll($company_id, $staff_id, $start_date, $end_date, $include_shared=true) {
		// Convert to UTC date for comparison
		$start_date = $this->dateToUtc($start_date);
		$end_date = $this->dateToUtc($end_date);
		
		$this->Record = $this->getEvents();
		$this->Record->
			where("calendar_events.company_id", "=", $company_id)->
			where("calendar_events.start_date", ">=", $start_date)->
			where("calendar_events.start_date", "<=", $end_date);
		
		// Include shared events, or just this staff members' events
		if ($include_shared) {
			$this->Record->
				open()->
					where("calendar_events.shared", "=", 1)->
					orWhere("calendar_events.staff_id", "=", $staff_id)->
				close();
		}
		else {
			// Only include events from this staff member
			$this->Record->where("calendar_events.staff_id", "=", $staff_id);
		}

		return $this->Record->order(array('calendar_events.start_date'=>"ASC"))->fetchAll();
	}
	
	/**
	 * Fetches a set of events that begin or end between the given start and end dates (e.g. exist between the given start and end dates).
	 *
	 * @param int $company_id The ID of the company to fetch for
	 * @param int $staff_id The ID of the staff member to fetch for
	 * @param string $start_date Defines the lower bound for event start/end dates
	 * @param string $end_date Defines the upper bound for event start/end dates
	 * @param boolean $include_shared If true will include shared calendar events, false will only include events for this staff member
	 * @return array An array of stdClass objects, each representing a calendar event
	 * @see CalendarEvents::getAll()
	 */
	public function getRange($company_id, $staff_id, $start_date, $end_date, $include_shared=true) {
		// Convert to UTC date for comparison
		$start_date = $this->dateToUtc($start_date);
		$end_date = $this->dateToUtc($end_date);
		
		$this->Record = $this->getEvents();
		$this->Record->
			where("calendar_events.company_id", "=", $company_id)->
			where("calendar_events.start_date", "<=", $end_date)->
			where("calendar_events.end_date", ">=", $start_date);
		
		// Include shared events, or just this staff members' events
		if ($include_shared) {
			$this->Record->
				open()->
					where("calendar_events.shared", "=", 1)->
					orWhere("calendar_events.staff_id", "=", $staff_id)->
				close();
		}
		else {
			// Only include events from this staff member
			$this->Record->where("calendar_events.staff_id", "=", $staff_id);
		}

		return $this->Record->order(array('calendar_events.start_date'=>"ASC"))->fetchAll();
	}
	
	/**
	 * Returns a Record objects consisting of a partial query on calendar events
	 *
	 * @return Record A partial query on calendar events
	 */
	private function getEvents() {
		
		$fields = array("calendar_events.*", 'staff.first_name' => "staff_first_name", 'staff.last_name' => "staff_last_name");
		$this->Record->select($fields)->from("calendar_events")->
			innerJoin("staff", "staff.id", "=", "calendar_events.staff_id", false);
		
		return $this->Record;
	}
	
	/**
	 * Retrieves a list of add/edit rules
	 *
	 * @param array $vars A list of input vars
	 * @param boolean $edit True to retrieve the edit rules, false to retrieve the add rules (optional, default false)
	 * @return array A list of input rules
	 */
	private function getRules(array $vars, $edit=false) {
		$rules = array(
			'company_id' => array(
				'exists' => array(
					'rule' =>array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("CalendarEvents.!error.company_id.exists")
				)
			),
			'staff_id' => array(
				'exists' => array(
					'rule' =>array(array($this, "validateExists"), "id", "staff"),
					'message' => $this->_("CalendarEvents.!error.staff_id.exists")
				)
			),
			'shared' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("CalendarEvents.!error.shared.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 1),
					'message' => $this->_("CalendarEvents.!error.shared.length")
				)
			),
			'title' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("CalendarEvents.!error.title.empty")
				)
			),
			'start_date' => array(
				'format' => array(
					'rule' => "isDate",
					'message' => $this->_("CalendarEvents.!error.start_date.format"),
				),
				'order' => array(
					'rule' => array(array($this, "validateDateOrder"), $this->ifSet($vars['end_date'])),
					'message' => $this->_("CalendarEvents.!error.start_date.order"),
					'post_format' => array(array($this, "dateToUtc"))
				)
			),
			'end_date' => array(
				'format' => array(
					'rule' => "isDate",
					'message' => $this->_("CalendarEvents.!error.end_date.format"),
					'post_format' => array(array($this, "dateToUtc"))
				)
			),
			'all_day' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("CalendarEvents.!error.all_day.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 1),
					'message' => $this->_("CalendarEvents.!error.all_day.length")
				)
			)
		);
		
		// Set edit rules
		if ($edit) {
			// Validate this event belongs to this staff member
			$rules['staff_id']['valid'] = array(
				'rule' => array(array($this, "validateStaffEvent"), $this->ifSet($vars['calendar_event_id'])),
				'message' => $this->_("CalendarEvents.!error.staff_id.valid")
			);
			
			// Set all fields as optional
			$rules['company_id']['exists']['if_set'] = true;
			$rules['title']['empty']['if_set'] = true;
			$rules['start_date']['format']['if_set'] = true;
			$rules['end_date']['format']['if_set'] = true;
		}
		
		return $rules;
	}
	
	/**
	 * Validates whether the given event belongs to the given staff member
	 *
	 * @param int $staff_id The ID of the staff member that created this event
	 * @param int $event_id The ID of the calendar event created
	 * @return boolean True if the calendar event belongs to the given staff member, false otherwise
	 */
	public function validateStaffEvent($staff_id, $event_id) {
		$count = $this->Record->select("id")->from("calendar_events")->where("staff_id", "=", $staff_id)->
			where("id", "=", $event_id)->numResults();
		
		if ($count > 0)
			return true;
		return false;
	}
	
	/**
	 * Validates that the given start date is before the given end date, inclusive
	 *
	 * @param string $start_date The start date
	 * @param string $end_date The end date
	 * @param boolean True if the start date comes before the end date, false otherwise
	 */
	public function validateDateOrder($start_date, $end_date) {
		return ($this->Date->toTime($end_date) >= $this->Date->toTime($start_date));
	}
}
?>