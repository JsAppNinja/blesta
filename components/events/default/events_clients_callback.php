<?php
/**
 * Handle all default Clients events callbacks
 *
 * @package blesta
 * @subpackage blesta.components.events.default
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class EventsClientsCallback extends EventCallback {
	
	/**
	 * Handle Clients.create events
	 *
	 * @param EventObject $event An event object for Clients.create events
	 * @return EventObject The processed event object
	 */
	public static function create(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
}
?>