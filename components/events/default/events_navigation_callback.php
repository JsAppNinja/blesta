<?php
/**
 * Handle all default Navigation events callbacks
 *
 * @package blesta
 * @subpackage blesta.components.events.default
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class EventsNavigationCallback extends EventCallback {
	
	/**
	 * Handle Navigation.getSearchOptions events
	 *
	 * @param EventObject $event An event object for Navigation.getSearchOptions events
	 * @return EventObject The processed event object
	 */
	public static function getSearchOptions(EventObject $event) {
		return parent::triggerPluginEvent($event);
	}
}
?>