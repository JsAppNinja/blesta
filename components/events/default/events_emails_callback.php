<?php
/**
 * Handle all default Emails events callbacks
 *
 * @package blesta
 * @subpackage blesta.components.events.default
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class EventsEmailsCallback extends EventCallback {
	
	/**
	 * Handle Emails.send events
	 *
	 * @param EventObject $event An event object for Emails.send events
	 * @return EventObject The processed event object
	 */
	public static function send(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}

	/**
	 * Handle Emails.sendCustom events
	 *
	 * @param EventObject $event An event object for Emails.sendCustom events
	 * @return EventObject The processed event object
	 */	
	public static function sendCustom(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
}
?>