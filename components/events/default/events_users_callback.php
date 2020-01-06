<?php
/**
 * Handle all default Users events callbacks
 *
 * @package blesta
 * @subpackage blesta.components.events.default
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class EventsUsersCallback extends EventCallback {
	
	/**
	 * Handle Users.login events
	 *
	 * @param EventObject $event An event object for Users.login events
	 * @return EventObject The processed event object
	 */
	public static function login(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}

	/**
	 * Handle Users.logout events
	 *
	 * @param EventObject $event An event object for Users.logout events
	 * @return EventObject The processed event object
	 */	
	public static function logout(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
}
?>