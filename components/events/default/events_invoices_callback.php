<?php
/**
 * Handle all default Invoices events callbacks
 *
 * @package blesta
 * @subpackage blesta.components.events.default
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class EventsInvoicesCallback extends EventCallback {
	
	/**
	 * Handle Invoices.add events
	 *
	 * @param EventObject $event An event object for Invoices.add events
	 * @return EventObject The processed event object
	 */
	public static function add(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}

	/**
	 * Handle Invoices.edit events
	 *
	 * @param EventObject $event An event object for Invoices.edit events
	 * @return EventObject The processed event object
	 */	
	public static function edit(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}

	/**
	 * Handle Invoices.setClosed events
	 *
	 * @param EventObject $event An event object for Invoices.setClosed events
	 * @return EventObject The processed event object
	 */	
	public static function setClosed(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
}
?>