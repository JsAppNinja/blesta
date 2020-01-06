<?php
/**
 * Handle all default Services events callbacks
 *
 * @package blesta
 * @subpackage blesta.components.events.default
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class EventsServicesCallback extends EventCallback {
	
	/**
	 * Handle Services.add events.
	 *
	 * @param EventObject $event An event object for Services.add events
	 * @return EventObject The processed event object
	 */
	public static function add(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
	
	/**
	 * Handle Services.edit events.
	 *
	 * @param EventObject $event An event object for Services.edit events
	 * @return EventObject The processed event object
	 */
	public static function edit(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
	
	/**
	 * Handle Services.cancel events.
	 *
	 * @param EventObject $event An event object for Services.cancel events
	 * @return EventObject The processed event object
	 */
	public static function cancel(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
	
	/**
	 * Handle Services.suspend events.
	 *
	 * @param EventObject $event An event object for Services.suspend events
	 * @return EventObject The processed event object
	 */
	public static function suspend(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
	
	/**
	 * Handle Services.unsuspend events.
	 *
	 * @param EventObject $event An event object for Services.unsuspend events
	 * @return EventObject The processed event object
	 */
	public static function unsuspend(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
}
?>